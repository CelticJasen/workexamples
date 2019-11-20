<?php

class Reminders
{
	
	
	function __construct($db, $blab, $MailIndex)
	{
		require_once("/webroot/includes/time.inc.php");
		require_once("backend/customer.inc.php");
		require_once("backend/email.inc.php");
		
		$this->db = $db;
		$this->blab = $blab;
		$this->customer = new Customer($this->db, $this->blab);
		$this->MailIndex = $MailIndex;
	}
	
	
	function debtors($last_run = false)
	{
		$debtors = array();
		
		$r = $this->db->query("select debtors.*, customers.customer_id, group_concat(quote_id) as quotes, ".
			"group_concat(how_blocked) as blocked ".
			"from invoicing.debtors ".
			"left join invoicing.customers using (email) ".
			"left join invoicing.quotes on (customer_email = debtors.email and approved is null) ".
			"left join invoicing.tbl_blocked_bidders on ".
			"(debtors.email = tbl_blocked_bidders.email and ebay_id not like '%@%') ".
			"group by debtors.email ".
			"having ( amount >= 1500 or (completed_orders = '0' and amount >= 500)) ".
			"order by begin ");
		
		while($row = $r->fetch_assoc())
		{
			if($row['blocked'] == "Unblocked")
				$row['blocked'] = "";
			$row['begin'] = date("m/d/Y", strtotime($row['begin']));
			$row['end'] = date("m/d/Y", strtotime($row['end']));
			$row['amount'] = number_format($row['amount']);
			$row['invoices'] = explode(",", $row['invoices']);
			
			if($last_run !== false && $row['added'] > $last_run)
				$row['style'] = "background-color: rgba(255,255,0,0.2)";
			
			$debtors[] = $row;
		}
		
		return $debtors;
	}
	
	function day($date = null)
	{
		if(is_null($date))
			$date = new DateTimeImmutable();
		
		switch($date->format("l"))
		{
			case "Monday":
				return $date->modify("last Sunday -28 day");
				break;
				
			case "Tuesday":
				return $date->modify("Today -28 day");
				break;
			
			case "Wednesday":
				return $date->modify("last Tuesday -28 day");
				break;
			
			case "Thursday":
				return $date->modify("Today -28 day");
				break;
			
			case "Friday":
				return $date->modify("last Thursday -28 day");
				break;
			
			case "Saturday":
				throw new exception("What are you doing here on a Saturday?", 10040);
				break;
			
			case "Sunday":
				return $date->modify("last Thursday -28 day");
				break;
				
			default:
				throw new exception("What?");
				break;
		}
	}
	
	
	function do_list($date)
	{
		$query = "select distinct ebay_email, customers.customer_id ".
			"from invoicing.sales ".
			"left join invoicing.customers on (ebay_email = customers.email) ".
			"left join invoicing.autoship on (autoship.email_address = sales.ebay_email) ".
			"where shipping_notes != 'BIN' and price > 0 and invoice_number is null and ".
			"sales.`date` between '".$date->format("Y-m-d 00:00:00")."' and '".$date->format("Y-m-d 23:59:59")."' and ".
			"autoship.email_address is null ".
			"order by customers.customer_id";
		
		$r = $this->db->query($query);
		
		$list = array();
		
		while(list($email) = $r->fetch_row())
		{
			$list[] = $this->get($email, $date);
		}
		
		return $list;
	}
	
	
	function get($email, $date)
	{
		$data = array(
			"reminder_notes" => array(),
			"auction_date" => $date->format("Y-m-d"),
		);
		
		$query = "select * ".
			"from invoicing.sales ".
			"left join invoicing.autoship on (autoship.email_address = sales.ebay_email) ".
			"where shipping_notes != 'BIN' and invoice_number is null and ".
			//"sales.`date` between '".$date->format("Y-m-d 00:00:00")."' and '".$date->format("Y-m-d 23:59:59")."' and ".
			"ebay_email = '".$this->db->escape_string($email)."'";
		
		$r = $this->db->query($query);
		
		$data['items'] = array();
		
		$data['subtotal'] = 0;
		
		while($row = $r->fetch_assoc())
		{
			$data['subtotal'] += $row['price'];
			
			if($row['price'] <= 0)
			{
				continue;
			}
			
			$row['reminder_notes'] = trim($row['reminder_notes']);
			
			$data['items'][] = $row;
			
			if(!empty($row['reminder_notes']) && !in_array($row['reminder_notes'], $data['reminder_notes']))
				$data['reminder_notes'][] = $row['reminder_notes'];
		}
		
		$r = $this->db->query("select customer_id ".
			"from invoicing.customers ".
			"where email = '".$this->db->escape_string($email)."'");
		
		if($r->num_rows == 0)
			throw new exception("No such customer $email");
		
		list($customer_id) = $r->fetch_row();
		
		$data['customer'] = $this->customer->call("get_lite", array($customer_id));
		
		$data['reminder_notes'] = implode("; ", $data['reminder_notes']);
		
		//Get last email
		$data['last_email_from'] = $this->last_email_from($data['customer']['customer']['address']['customers_id']);
		
		$data['last_email_to'] = $this->last_email_to($data['customer']['customer']['address']['customers_id']);
		
		$data['last_email_about'] = $this->last_email_about($data['customer']['customer']['address']['customers_id']);
		
		if($data['last_email_about']['email_id'] == $data['last_email_to']['email_id'] || 
			$data['last_email_about']['email_id'] == $data['last_email_from']['email_id'])
		{
			unset($data['last_email_about']);
		}
		
		$data['blocked'] = $this->blockedness($email);
		
		$data['quote'] = $this->quotes($email, $date);
		
		$data['balances'] = $this->balances($customer_id);
		
		$data['printout'] = $this->printout($customer_id, $date);
		
		$data['paypal'] = $this->paypals($data['customer']['all_emails'], $date);
		
		return $this->format($data);
	}
	
	
	
	function paypals($all_emails, $date)
	{
		$r = $this->db->query("select payment_date, mc_gross ".
			"from invoicing.paypal_notifications ".
			"where payer_email in ('".implode("','", array_map(array($this->db, "escape_string"), $all_emails))."') and ".
			"payment_date > '".$date->format("Y-m-d 00:00:00")."' ".
			"order by payment_date desc");
			
		if($row = $r->fetch_assoc())
		{
			$row['fuzzy'] = Time::less_fuzzy(strtotime($row['payment_date']));
		}
		
		return $row;
	}
	
	function printout($customer_id, $date)
	{
		$r = $this->db->query("select id, `ts` ".
			"from invoicing.phone_order_printouts ".
			"where `ts` >= '".$date->format("Y-m-d 00:00:00")."' and customer_id = '".$this->db->escape_string($customer_id)."' ".
			"order by `ts` desc");
			
		if($row = $r->fetch_assoc())
		{
			$row['fuzzy'] = Time::less_fuzzy(strtotime($row['ts']));
		}
		
		return $row;
	}
	
	
	function balances($customer_id)
	{
		$r = $this->db->query("select ConsignorName, balance, consignor_id ".
			"from accounting.receivable_accounts ".
			"join listing_system.tbl_consignorlist on (consignor_id = auto_id) ".
			"where cust_id = '".$this->db->escape_string($customer_id)."'");
			
		$data = array();
		
		while($row = $r->fetch_assoc())
			$data[] = $row;
			
		return $data;
	}
	
	
	function blockedness($email)
	{
		$r = $this->db->query("select Status_Account = 1 ".
			"from invoicing.aa_customers ".
			"where email = '".$this->db->escape_string($email)."'");
			
		if($r->num_rows)
		{
			list($status) = $r->fetch_row();
			
			if($status)
				return "&nbsp;";
			else
				return "<b style='color: tomato'>Blocked</b>";
		}
		else
		{
			return "Error";
		}
	}
	
	function format($data)
	{
		$data['subtotal'] = number_format($data['subtotal'], 2);
		$data['email'] = $data['customer']['customer']['address']['email'];
		$data['email_formatted'] = str_replace("@", "@<wbr />", $data['email']);
		
		
		/*if(isset($data['last_email_from']))
			$data['last_email_from']['fuzzy_date'] = str_replace(" ", "&nbsp;", $data['last_email_from']['fuzzy_date']);
		
		if(isset($data['last_email_to']))
			$data['last_email_to']['fuzzy_date'] = str_replace(" ", "&nbsp;", $data['last_email_to']['fuzzy_date']);
			
		if(isset($data['last_email_about']))
			$data['last_email_about']['fuzzy_date'] = str_replace(" ", "&nbsp;", $data['last_email_about']['fuzzy_date']);*/
		
		if(empty($data['reminder_notes']))
			$data['reminder_notes'] = "<small style='color: gray'>(blank)</small>";
			
		if($data['customer']['customer']['address']['vip'])
			$data['css'] = "background-image: url(/invoicing/graphics/coin.png)";
		
		$data['thermometer'] = min(9, max(0, floor(log10($data['customer']['pitascore'])*1.5)));
		$data['pitascore'] = number_format($data['customer']['pitascore']);
		
		return $data;
	}
	
	
	
	
	
	function quotes($email, $date)
	{
		$r = $this->db->query("select quote_id, `approved`, `ready`, `timestamp` ".
			"from invoicing.quotes ".
			"where customer_email = '".$this->db->escape_string($email)."' and `timestamp` > '".$date->format("Y-m-d 00:00:00")."' ".
			"order by `approved` is not null, timestamp desc");
			
		if($r->num_rows)
		{
			$row = $r->fetch_assoc();
			
			if(empty($row['ready']))
			{
				$row['status'] = "<a href='/shipping_quotes/?customer=$row[quote_id]' target='_blank'>".
					"<img src='/includes/graphics/red.png' />&nbsp;".str_replace(" ", "&nbsp;", Time::less_fuzzy(strtotime($row['timestamp'])))."</a>";
			}
			
			if(empty($row['approved']))
			{
				$row['status'] = "<a href='/shipping_quotes/?customer=$row[quote_id]' target='_blank'>".
					"<img src='/includes/graphics/yellow.png' />&nbsp;".str_replace(" ", "&nbsp;", Time::less_fuzzy(strtotime($row['ready'])))."</a>";
			}
			else
			{
				$row['status'] = "<a href='/shipping_quotes/?customer=$row[quote_id]' target='_blank'>".
					"<img src='/includes/graphics/green.png' />&nbsp;".str_replace(" ", "&nbsp;", Time::less_fuzzy(strtotime($row['approved'])))."</a>";
			}
			
			$row['status'] = str_replace("&nbsp;", " ", $row['status']);
			
			return $row;
		}
		
		return array("status" => "<a style='color: gray; font-size: 90%' target='_blank' href='/shipping_quotes/?customer=".urlencode($email)."'>(no quote)</a>");
	}
	
	function last_email_from($customers_id)
	{
		$r = $this->db->query("select email from invoicing.customers where customers_id = '$customers_id' and email like '%@%' ".
			"union ".
			"select email from invoicing.customers_emails where customers_id = '$customers_id' and email like '%@%'");
			
		$emails = array();
		while(list($email) = $r->fetch_row())
			$emails[] = $email;
		
		if(false !== $email = $this->MailIndex->last_email_from($emails))
		{
			$email['fuzzy_date'] = Time::less_fuzzy(strtotime($email['date']));
		}
		
		
		
		return $email;
	}
	
	
	function last_email_to($customers_id)
	{
		$r = $this->db->query("select email from invoicing.customers where customers_id = '$customers_id' and email like '%@%' ".
			"union ".
			"select email from invoicing.customers_emails where customers_id = '$customers_id' and email like '%@%'");
			
		$emails = array();
		while(list($email) = $r->fetch_row())
			$emails[] = $email;
		
		if(false !== $email = $this->MailIndex->last_email_to($emails))
		{
			$email['fuzzy_date'] = Time::less_fuzzy(strtotime($email['date']));
		}
		
		
		
		return $email;
	}
	
	function last_email_about($customers_id)
	{
		$r = $this->db->query("select email from invoicing.customers where customers_id = '$customers_id' and email like '%@%' ".
			"union ".
			"select email from invoicing.customers_emails where customers_id = '$customers_id' and email like '%@%'");
			
		$emails = array();
		while(list($email) = $r->fetch_row())
			$emails[] = $email;
		
		if(false !== $email = $this->MailIndex->last_email_about($emails))
		{
			$email['fuzzy_date'] = Time::less_fuzzy(strtotime($email['date']));
		}
		
		
		
		return $email;
	}
	
	
	function template_data($customer_id, $date)
	{
		$block = new DateTime("+7 day");
		$block->modify("Thursday");
		
		$data = array(
			"customer" => $this->customer->call("get_lite", array($customer_id)),
			"subtotal" => 0,
			"from" => "lana@emovieposter.com",
			"subject" => "You have outstanding purchase(s)",
			"auction_date" => $date->format("l, F jS, Y"),
			"block_date" => $block->format("l, F jS, Y"),
		);
		
		$query = "select *, date(`date`) as date ".
			"from invoicing.sales ".
			"where shipping_notes != 'BIN' and price > 0 and invoice_number is null and ".
			//"sales.`date` between '".$date->format("Y-m-d 00:00:00")."' and '".$date->format("Y-m-d 23:59:59")."' and ".
			"ebay_email = '".$this->db->escape_string($data['customer']['customer']['address']['email'])."'";
		
		$r = $this->db->query($query);
		
		$data['items'] = array();
		$data['max_date'] = "0000-00-00";
		$data['min_date'] = "9999-99-99";
		
		while($row = $r->fetch_assoc())
		{	
			$data['items'][] = $row;
			$data['subtotal'] += $row['price'];
			
			if($row['date'] > $data['max_date'])
				$data['max_date'] = $row['date'];
			
			if($row['date'] < $data['min_date'])
				$data['min_date'] = $row['date'];
		}
		
		
		$data['min_date'] = new DateTime($data['min_date']);
		$data['min_date'] = $data['min_date']->format("l, F jS, Y");
		
		$data['max_date'] = new DateTime($data['max_date']);
		$data['max_date'] = $data['max_date']->format("l, F jS, Y");
		
		if($data['min_date'] == $data['max_date'])
		{
			$data['auction_blurb'] = "$data[max_date] auction";
		}
		else
		{
			$data['auction_blurb'] = "$data[min_date] to $data[max_date] auctions";
		}
		
		return $data;
	}
	
	
	function blocks($data, $user)
	{
		$data = array(
			"user_first_name" => $user->first_name,
			"blocks" => $data,
		);
		
		require_once('Mail.php');
		require_once('Mail/mime.php');
		
		require_once("/webroot/includes/Mustache/Autoloader.php");
		Mustache_Autoloader::register();
		$mustache = new Mustache_Engine;
		
		/*
			email to send to the customer
		*/
		$body = str_replace(array("\r", "\n"), array("", "\r\n"), $mustache->render(file_get_contents("/webroot/invoicing/templates/email/blocks.html"), $data));
		
		$email = new Mail_mime();
		
		$headers = array(
			"From" => "tech@emovieposter.com",
			"To" => "phillip@emovieposter.com",
			"Subject" => "To Block or Not To Block?",
			"X-EMP-Type" => "R",
		);
		
		$email->setHTMLBody($body);
		
		$mail = Mail::factory('sendmail');
		
		$body = $email->get(); //This has to be called prior to headers()
		
		$mail->send($user->email, $email->headers($headers), $body);
	}
	
	
	function send($data, $user)
	{
		$mail = Mail::factory('sendmail');
		
		foreach($data as $d)
		{
			list($body, $headers) = $this->generate_email($d[0], $d[1], new DateTimeImmutable($d[2]), $d[3], $user);
			
			$mail->send($user->email, $headers, $body);
		}
	}
	
	static function textify($str)
	{
		return preg_replace( "/\n\s+/", "\n", rtrim(html_entity_decode(strip_tags($str))) );
	}
	
	function generate_email($customer_id, $email_address, $date, $template, $user)
	{
		require_once('Mail.php');
		require_once('Mail/mime.php');
		
		$data = $this->template_data($customer_id, $date);
		
		$data['user_first_name'] = $user->first_name;
		
		require_once("/webroot/includes/Mustache/Autoloader.php");
		Mustache_Autoloader::register();
		$mustache = new Mustache_Engine;
		
		$body = str_replace(array("\r", "\n"), array("", "\r\n"), $mustache->render(file_get_contents("/webroot/invoicing/templates/email/reminders/".$template), $data));
		
		$email = new Mail_mime();
		
		$headers = array(
			"From" => "tech@emovieposter.com",
			"To" => "".$email_address,
			"Subject" => "Your outstanding purchase(s)",
			"X-EMP-Type" => "R",
		);
		
		if(pathinfo($template, PATHINFO_EXTENSION) == "txt")
		{
			$email->setTXTBody($body);
		}
		else
		{
			$email->setHTMLBody($body);
			
		}
		
		return array(
			$email->get(),
			$email->headers($headers),
		);
	}
	
	
}

?>