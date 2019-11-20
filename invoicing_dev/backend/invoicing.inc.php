<?php

class Invoicing
{
	function __construct($db, $blab, $wdb = null)
	{		
		$this->db = $db;
		$this->blab = $blab;
		$this->wdb = $wdb;
	}
	
	
	
	/**
	 * Calls a method in this class, times how long it took
	 * to run, and outputs the time using Blab.
	 */
	function call($method, $args)
	{
		$time = microtime(true);
		
		$ret = call_user_func_array(array($this, $method), $args);
		
		$this->last_interval = microtime(true) - $time;
		
		$this->blab->blab("Method '$method' took ".$this->last_interval." seconds to complete.");
		
		return $ret;
	}
	
	
	/**
	 *
	 */
	function next_invoice_number_block()
	{
		$r = $this->db->query("select max(end) from invoicing.invoice_numbers");
		
		list($max) = $r->fetch_row();
		
		if(empty($max))
			return 401000;
		else
			return (floor($max/1000)*1000)+1000;
	}
	 
	
	/**
	 *
	 */
	function reserve_invoice_number_block($user_id)
	{
		$begin = $this->next_invoice_number_block();
		
		$this->db->query(assemble_insert_query3(
			array(
				"begin" => $begin,
				"end" => $begin+999,
				"autoincrement" => $begin,
				"user_id" => $user_id,
			),
			"invoicing.invoice_numbers",
			$this->db
		));
		
		return $begin;
	}
	
	
	function get_next_invoice_number($user_id)
	{
		$r = $this->db->query("select * ".
			"from invoicing.invoice_numbers ".
			"where user_id = '$user_id' ".
			"and (autoincrement <= end) ".
			"order by begin desc limit 1");
		
		if($r->num_rows)
		{
			$block = $r->fetch_assoc();
			
			for($x = $block['autoincrement']; $x <= $block['end']; $x++)
			{
				$r = $this->db->query("select invoice_number from invoicing.invoices ".
					"where invoice_number = $x");
					
				if($r->num_rows == 0)
				{
					return $x;
				}
				else
				{
					$this->db->query("update invoicing.invoice_numbers ".
						"set autoincrement = ".($x+1)." where id = $block[id]");
				}
			}
		}
		
		/*
			Only certain users are allowed to reserve new blocks for themselves.
			Other users must have their blocks created manually.
		*/
		if(in_array($user_id, array(18, 92, 81, 112, 1, 118, 123, 127)))
		{
			$this->reserve_invoice_number_block($user_id);
			
			return $this->get_next_invoice_number($user_id);
		}
		else
			return "";
	}
	
	
	function mb_unserialize($string) {
	    $string = preg_replace('!s:(\d+):"(.*?)";!se', "'s:'.strlen('$2').':\"$2\";'", $string);
	    return unserialize($string);
	}
	
	
	function paypal_payments($search = "")
	{
		$query = "select raw, name, payment_date, id, timestamp, payment_status ".
			"from invoicing.paypal_notifications ".
			"where status != 'finished' and txn_type != 'virtual_terminal' and coalesce(custom, '') != 'Auction/WebPurchase' "; //Exclude hidden, virtual termal, and credit card processor payments
		
		$search = trim($search);
		
		if(!empty($search))
		{
			$search_fields = Array(
				"txn_id",
				"txn_type",
				"parent_txn_id",
				"payment_type",
				"payment_status",
				"name",
				"payer_business_name",
				"payer_email",
				"payer_id",
				"invoice",
				"mc_gross",
				"payment_date_search",
			);
			
			$query .= "and (";
			
			foreach($search_fields as $f)
			{
				$query .= sprintf("`%s` like '%%%s%%' or ", 
					$this->db->escape_string($f), 
					$this->db->escape_string($search));
			}
			
			$query = substr($query, 0, -4) . ") ";
		}

		/*if(!empty($_POST['max_id']) || !empty($_POST['timestamp']))
		{
			$temp = "";
			if(!empty($_POST['max_id']))
				$temp .= "id > '$_POST[max_id]' or ";
			if(!empty($_POST['timestamp']))
				$temp .= "timestamp > '$_POST[timestamp]' or ";
			$temp = substr($temp, 0, -4);
			$query .= " and ($temp)";
		}*/

		$query .=  "order by payment_date desc limit 75";
		$r = $this->db->query($query);
		
		$data = array();
		
		while(list($raw, $name, $payment_date, $id, $timestamp, $payment_status) = $r->fetch_row())
		{
			$payment = array();
			try
			{
				//$payment = unserialize(mb_convert_encoding($raw, "iso-8859-1", "utf8"));
				$payment = self::mb_unserialize($raw);
				
				json_encode($payment);
			}
			catch(exception $e)
			{
				//email_error($e->__toString());
				//email_error($e->)
				$payment = array("payer_email" => $e->getMessage());
			}
			
			$item = Array(
				"id" 		=> $id,
				"payer_id" 	=> get($payment, 'payer_id'),
				"invoice"	=> get($payment, 'invoice'),
				"shipping" 	=> get($payment, 'shipping'),
				"mc_gross" 	=> get($payment, 'mc_gross'),
				"txn_type" 	=> get($payment, 'txn_type'),
				"txn_id" 	=> get($payment, 'txn_id'),
				"memo" 		=> get($payment, 'memo'),
				"payer_email" => get($payment, 'payer_email'),
				"name" 		=> $name,
				"payment_date" => date("m/d/Y H:i:s", strtotime($payment_date)),
				"payment_status" => $payment_status,
				"timestamp" => $timestamp,
				"ebay" => !empty($payment['for_auction']),
			);
			
			$item = array_filter($item, "strlen");
			
			$data[] = $item;
		}
		
		return $data;
	}
	
	
	
	function history_put($user_id, $customers_id)
	{
		$this->db->query(assemble_insert_query3(array(
			"user_id" => $user_id, 
			"customers_id" => $customers_id,
			), "invoicing.search_history", $this->db));
			
		return true;
	}
	
	
	
	function history_get($user_id = null)
	{
		$history = array();
		
		$r = $this->db->query("select customer_id, name ".
			"from invoicing.search_history ".
			"join customers using(customers_id) ".
			(empty($user_id) ? "where user_id != '7' " : "where user_id = '$user_id' ").
			"order by timestamp desc limit 100");
			
		while(list($customer_id, $name) = $r->fetch_row())
		{
			if(empty($history[$customer_id]))
			{
				$history[$customer_id] = array($name);
			}
		}
		
		$history = array_slice($history, 0, 20);
		
		return $history;
	}
	
	
	function debtors($last_run = false)
	{
		require_once("backend/email.inc.php");
		require_once("backend/customer.inc.php");
		$customer = new Customer($this->db, null, null);
		$mail = new MailIndex;

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


			try
			{
				//Get Recent Correspondence

				$emails = array($row['email']);

				$address = $customer->address($row['customer_id']);

				if(!empty($address['other_emails']))
				{
					foreach($address['other_emails'] as $em)
						$emails[] = $em['email'];
				}

				$row['last_email_from'] = $mail->last_email_from($emails);
			}
			catch(exception $e)
			{
				
			}

			$debtors[] = $row;
		}
		
		return $debtors;
	}
	
	
	function autoships()
	{
		$data = array();
		
		$r = $this->db->query("select `name`, customers.customer_id, ship_country, sum(price) ".
			"from customers ".
			"join autoship using(customer_id) ".
			"join sales on (email = ebay_email) ".
			"where invoice_number is null and price > 0 and `date` > date_sub(now(), interval 180 day) ".
			"group by email ".
			//"having(if(ship_country = 'US', sum(price) >= 20, sum(price) >= 60)) ".
			"order by sum(price) desc");		
		
		while($row = $r->fetch_assoc())
		{
			$data[] = $row;
		}
		
		return $data;
	}


	function quote_requests()
	{
		if($this->wdb)
		{
			$r = $this->wdb->query("select t4.customer_id, t1.qpp_id, t1.qpp_datetime, ".
				"t1.`type`, t4.pay_and_hold, ".
				"group_concat(distinct combine_type) as combine_type, count(*) as items ".
				"from members.quotependingprint as t1 ".
				"join members.users t2 on (users_id = t2.id) ".
				"join members.checkout t3 using(qpp_id) ".
				"join members.customers t4 on (t2.email = email_address) ".
				"join members.items_winners t5 using(item_id) ".
				"where t5.package_id is null and paid is null ".
				"group by users_id order by t1.qpp_datetime desc");
			
			$list = $item_ids = array();
			
			while($row = $r->fetch_assoc())
			{
				if($row['combine_type'] == "9" || $row['combine_type'] == "9,29" || $row['combine_type'] == "29,9")
					$list[] = $row;
			}
			
			return $list;
			
		}
		
		return false;
	}
}

function get($array, $key)
{
	if(isset($array[$key]))
		return $array[$key];
	else
		return null;
}

?>