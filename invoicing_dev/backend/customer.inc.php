<?php

class Customer
{
	/*
		TODO: Add blab debugging
		TODO: Switch to numeric customer id in the future
		TODO: Insert/Update/Delete
		TODO: Generate customer id
	*/
	
	function __construct($db, $blab, $wdb = null)
	{
		require_once("/webroot/shipping_quotes/format_address.inc.php");
		require_once("address.inc.php");
		require_once("/webroot/includes/time.inc.php");
		
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
	 * Gets a ton of information concerning customer $customer_id.
	 */
	function get($customer_id)
	{
		//TODO: Blockedness
		//TODO: Validate address with USPS or UPS (async of course)
		//TODO: Auction anything record?
		//TODO: Stats / achievements / score / reputation
		//TODO: invoices, items, fixed price items, tracking numbers, consignments, pay & hold
		//TODO: country name, province name
		//TODO: record locking
		//TODO: Google map
		//TODO: Frequency of purchase, rank
		//TODO: Links to BAA, Auction Anything, Members area (admin login method), quotes form, consignor form
		//TODO: username
		//TODO: Quotes summary, link to quotes form
		
		$time = microtime(true);
		
		if($address = $this->call("address", array($customer_id)))
		{
			$other_emails = array();
			foreach($address['other_emails'] as $e)
			{
				$other_emails[] = $e['email'];
			}
			
			$results = array(
				"customer" => $address,
				"billing_address" => $this->call("billing_address", array($customer_id)),
				"autoship" => $this->call("autoship", array($customer_id)),
				"consignor" => $this->call("consignor", array($customer_id, $address['address']['email'])),
				"cards" => $this->call("cards", array($customer_id)),
				//"purchase_frequency" => $this->call("purchase_frequency", array($address['address']['email'])), //TODO: This takes 750 ms to run. Make it faster or make it asynchronous.
				"pay_and_hold_due_dates" => $this->call("pay_and_hold_due_dates", array($customer_id)),
				"stats" => $this->call("stats", array($customer_id, $address['address']['email'])),
				"shared_accounts" => $this->call("shared_accounts", array($address['address']['customers_id'])),
				"printout_history" => $this->call("printout_history", array($customer_id)),
				"customer_id" => $customer_id,
				"alerts" => $this->call("alerts", array($customer_id, $address['address']['customers_id'])),
				"lowest_lot_numbers" => $this->call("lowest_lot_numbers", array($address['address']['email'])),
				"other_addresses" => $this->call("other_addresses", array($address['address']['customers_id'], $customer_id)),
				"credit_card_charges" => $this->call("credit_card_charges", array($address['address']['customers_id'])),
				"returns" => $this->call("returns", array($customer_id)),
			);
			
			$results['similar_names'] = $this->call("similar_names", array($results, $customer_id));
			
			if(count($results['printout_history']))
			{
				$results['customer']['last_printout'] = Time::fuzzy(strtotime($results['printout_history'][0]['ts']));
				$results['customer']['last_printout_days'] = round((time() - strtotime($results['printout_history'][0]['ts'])) / 86400);
			}
			
			//$results["other_emails"] = $this->parse_other_emails($address['address']['other_emails']);
			
			$results['block_records'] = $this->call("blocked_bidders_multi", array($address['address']['email'], $other_emails));
			
			$results["paypal_payments"] = $this->call("paypal_payments", array(array_merge($other_emails, array($address['address']['email']))));
			
			if($address['address']['email'] == "mail@emovieposter.com")
				$results['paypal_payments_received'] = array();
			else
				$results["paypal_payments_received"] = $this->call("paypal_payments_received", array(array_merge($other_emails, array($address['address']['email']))));
			
			$results["website_orders"] = $this->call("website_orders", array(array_merge($other_emails, array($address['address']['email']))));
			
			$results["aa_accounts"] = $this->call("aa_accounts", array(array_merge($other_emails, array($address['address']['email']))));
			
			$results["expert"] = $this->call("expert", array(array_merge($other_emails, array($address['address']['email']))));
			
			$results["pitascore"] = $this->call("pitascore", array($results));
			
			$results['aa_active_accounts'] = array();
			
			foreach($results['aa_accounts'] as $account)
			{
				if($account['Status_Account'] == 1)
				{
					$results['aa_active_accounts'][] = $account;
				}
				
				if(strtolower($account['email']) == strtolower($results['customer']['address']['email']))
				{
					$results['customer']['address']['users_id'] = $account['AutoNumber'];
					$results['customer']['address']['AA_ID'] = $account['ID'];
					$results['customer']['address']['username'] = $account['Username'];
				}
			}
			
			if(empty($results['customer']['address']['AA_ID']) && count($results['aa_active_accounts']))
			{
				$results['customer']['address']['users_id'] = $results['aa_active_accounts'][0]['ID'];
				$results['customer']['address']['AA_ID'] = $results['aa_active_accounts'][0]['ID'];
				$results['customer']['address']['username'] = $results['aa_active_accounts'][0]['Username'];
				$results['alerts'][] = "No Auction Anything account associated with primary email address. ".
					"The username shown is from a related account.";
			}
			
			if(!empty($results['customer']['address']['username']))
			{
				$results['quote_requests'] = $this->call("quote_requests", array($results['customer']['address']['username']));
			}
			
			$this->blab->blab("Get '$customer_id' took ".(microtime(true) - $time)." seconds");
			
			return $results;
		}
		else
			throw new exception("No customer with customer id '$customer_id'", 10000);
	}
	
	
	function tickets($customers_id)
	{		
		$list = TicketDatabase::findOpenByCustomer($customers_id, $this->db);
		
		return $list;
	}
	
	
	
	
	/** 
	 * Gets less information than get()
	 */
	function get_lite($customer_id)
	{
		if($address = $this->call("address", array($customer_id)))
		{
			$other_emails = array();
			foreach($address['other_emails'] as $e)
			{
				$other_emails[] = $e['email'];
			}
			
			$results = array(
				"customer" => $address,
				"billing_address" => $this->call("billing_address", array($customer_id)),
				"autoship" => $this->call("autoship", array($customer_id)),
				"consignor" => $this->call("consignor", array($customer_id, $address['address']['email'])),
				"cards" => $this->call("cards", array($customer_id)),
				"other_addresses" => $this->call("other_addresses", array($address['address']['customers_id'], $customer_id)),
				"customer_id" => $customer_id,
				"all_emails" => array_merge($other_emails, array($address['address']['email'])),
				"aa_accounts" => $this->call("aa_accounts", array(array_merge($other_emails, array($address['address']['email']))))
			);			
			
			
			//$results["other_emails"] = $this->parse_other_emails($address['address']['other_emails']);
			
			$results["block_records"] = $this->call("blocked_bidders_multi", array($address['address']['email'], $other_emails));
			
			$results["pitascore"] = $this->call("pitascore", array($results));
			
			return $results;
		}
		else
			throw new exception("No customer with customer id '$customer_id'", 10000);
	}
	
	
	
	function returns($customer_id)
	{
		$r = $this->db->query("select * ".
			"from listing_system.returns ".
			"where customer_id = '".$this->db->escape_string($customer_id)."' and ".
			"`status` != 'deleted'");
			
		$data = array();
		
		while($row = $r->fetch_assoc())
		{
			$return = json_decode($row['data'], true);
			
			$row['item_title'] = $return['items'][0]['item']['title_45'];		
			
			$row['autonumbers'] = array();
			
			foreach($return['items'] as $i)
				$row['autonumbers'][] = $i['item']['autonumber'];
			
			$data[] = $row;
		}
		
		return $data;
	}
	
	function credit_card_charges($customers_id, $limit = null)
	{
		$query = "select cc_log.status = 'success' as success, amount_charged, ".
			"coalesce(invoices.invoice_number, reference) as invoice_number, substr(cc_num, -4) as last_four, ".
			"timestamp, cc_log.status, reason, coalesce(payment_method, type_of_cc) as payment_method ".
			"from invoicing.cc_log ".
			"left join invoicing.tbl_cc using(cc_id) ".
			"left join invoicing.invoices on (coalesce(cc_log.invoice_number, reference) = invoices.invoice_number) ".
			"where cc_log.customers_id = '$customers_id' ".
			"order by id desc ";
		
		if(!empty($limit))
		{
			$query .= "limit $limit";
		}
		
		$r = $this->db->query($query);
			
		$data = array();
		
		while($row = $r->fetch_assoc())
		{
			$row['timestamp'] = date("m/d/y", strtotime($row['timestamp']));
			
			if($row['status'] == "success")
				$row['status_img'] = "checkmark.png";
			else
				$row['status_img'] = "alert16.png";
			
			if(!empty($row['amount_charged']))
				$row['amount_charged'] = "$".number_format($row['amount_charged'], 2);
			
			if(!empty($row['reason']))
				$row['status'] = "$row[status] ($row[reason])";
			
			if(empty($row['last_four']))
				$row['last_four'] = "&#9587;";
				
			$row['payment_icon'] = Invoice::get_payment_icon($row['payment_method']);
			
			$data[] = $row;
		}
		
		return $data;
	}
	
	/**
	 * Gets the invoicing.customers record for customer $customer_id,
	 * and also gives a human-readable address.
	 */
	function address($customer_id)
	{
		$r = $this->db->query("select customers.*, countrycode_name ".
			"from invoicing.customers ".
			"left join invoicing.tbl_countrycode on ship_country = countrycode_alpha2 ".
			"where customer_id = '".$this->db->escape_string($customer_id)."'");
			
		if($r->num_rows)
		{
			$address = $r->fetch_assoc();
			
			$r = $this->db->query("select * ".
				"from invoicing.customers_emails ".
				"where customers_id = '$address[customers_id]' and email != '".$this->db->escape_string($address['email'])."'");
			
			$other_emails = array();
			
			while($row = $r->fetch_assoc())
			{
				$other_emails[] = $row;
			}
			
			return array(
				"other_emails" => $other_emails,
				"address" => $address,
				"formatted" => Address::format($address),
			);
		}
		else
			return false;
	}
	
	
	/**
	 * Just returns the email address for a customer_id.
	*/
	function get_email($customer_id)
	{
		$r = $this->db->query("select email from invoicing.customers ".
			"where customer_id = '".$this->db->escape_string($customer_id)."'");
			
		if($r->num_rows)
		{
			list($email) = $r->fetch_row();
			
			return $email;
		}
		else
			return false;
	}
	
	
	
	/**
	 * The "lowest lot number" is the smallest lot number 
	 * of the items bought by a specific customer on a 
	 * specific auction.
	 */
	function lowest_lot_numbers($email)
	{
		$r = $this->db->query("select distinct `date`, ebay_email from invoicing.sales ".
			"where invoice_printed = '0' and ebay_title regexp '^[0-9][a-z][0-9]{3,4}' and ".
			"ebay_email = '".$this->db->escape_string($email)."'");
		
		$lowest_lot_numbers = array();
		
		while(list($date, $email) = $r->fetch_row())
		{
			$r2 = $this->db->query(sprintf("select min(ebay_title) from invoicing.sales ".
				"where `date` = '%s' and ebay_email = '%s' and ebay_title regexp '^[0-9][a-z][0-9]{3,4} '",
				$this->db->escape_string($date), $this->db->escape_string($email)));
			
			if($r2->num_rows == 0)
				continue;
			
			list($title) = $r2->fetch_row();
			
			preg_match("/^([0-9][a-z][0-9]{3,4}) /i", $title, $match);
			
			$lowest_lot_numbers[] = $match[1];
		}

		$r = $this->db->query("select 1 from invoicing.sales ".
			"join invoicing.fixed_price_sales on ebay_item_number = item_number ".
			"where user_email = '".$this->db->escape_string($email)."'");
			
		if($r->num_rows)
			$lowest_lot_numbers[] = "BIN";

		return $lowest_lot_numbers;
	}
	
	
	
	
	/**
	 * Gets the invoicing.bill_to_address record for customer $customer_id,
	 * and also gives a human-readable address. Keep in mind the human-readable
	 * address uses the same column names as the invoicing.customers table.
	 */
	function billing_address($customer_id)
	{
		$r = $this->db->query("select * from invoicing.bill_to_address ".
				"where customer_id = '".$this->db->escape_string($customer_id)."'");
			
		if($r->num_rows)
		{
			$row = $r->fetch_assoc();
			
			return array(
				"billing_address" => $row, 
				"formatted" => Address::format(Address::convert_bill_to($row)),
			);
		}
		else
			return false;
	}
	
	
	
	
	
	function addresses($customer_id)
	{
		$addresses = array();
		
		//Get customers_id from customer_id
		$r = $this->db->query("select customers_id from invoicing.customers ".
			"where customer_id = '".$this->db->escape_string($customer_id)."'");
			
		list($customers_id) = $r->fetch_row();
		
		
		//Get billing address
		
		$billing = $this->billing_address($customer_id);
		if(!empty($billing) && !$this->billing_address_is_empty($billing['billing_address']))
		{
			$address = Address::convert_bill_to($billing['billing_address']);
			$address['formatted'] = $billing['formatted'];
			$address['label'] = "Billing";
			
			$addresses[] = $address;
		}
		
		
		//Get mailing address
		$mailing = $this->address($customer_id);
		$address = $mailing['address'];
		$address['formatted'] = $mailing['formatted'];
		$address['label'] = "Primary";
		
		$addresses[] = $address;
		
		$addresses = array_merge($this->other_addresses($customers_id, $customer_id), $addresses);
		
		return $addresses;
	}
	
	
	
	
	function other_addresses($customers_id, $customer_id)
	{
		$addresses = array();
		
		//Get other addresses
		$r = $this->db->query("select *  ".
			"from invoicing.customers_addresses ".
			"where hide = '0' and ".
			"customer_id = '".$this->db->escape_string($customer_id)."'");
		
		while($row = $r->fetch_assoc())
		{
			$row['formatted'] = Address::format($row);
			
			if(!empty($row['probable_customer_id']))
				$row['label'] = "Probable - $row[address_label]";
			elseif(empty($row['address_label']))
				$row['label'] = "Other";
			else
				$row['label'] = $row['address_label'];
			
			$row['customer_id'] = $customer_id;
				
			$addresses[] = $row;
		}
		
		return $addresses;
	}
	
	
	
	
	/**
	 * Returns the invoicing.autoship record for customer $customer_id.
	 */
	function autoship($customer_id)
	{
		$r = $this->db->query("select * ".
			"from invoicing.autoship ".
			"where customer_id = '".$this->db->escape_string($customer_id)."'");
		
		if($r->num_rows)
		{
			return $r->fetch_assoc();
		}
		else
			return false;
	}
	
	
	
	/**
	 * Returns all listing_system.tbl_consignorlist records for 
	 * customer $customer_id, if applicable. Returns false
	 * when no records were found.
	 */
	function consignor($customer_id, $email)
	{
		$r = $this->db->query("select sum(price) from invoicing.sales ".
			"where ebay_email = '".$this->db->escape_string($email)."' and ".
			"invoice_number is null");
		
		list($uninvoiced_total) = $r->fetch_row();
			
		
		//TODO: Parse emails list
		$r = $this->db->query("select * ".
			"from listing_system.tbl_consignorlist ".
			"where cust_id = '".$this->db->escape_string($customer_id)."'");
		
		if($r->num_rows)
		{
			$rows = $names = array();
			
			while($row = $r->fetch_assoc())
			{
				$names[] = $row['ConsignorName'];
			}
			
			$r = $this->db->query("select * ".
				"from listing_system.tbl_consignorlist ".
				"where cust_id = '".$this->db->escape_string($customer_id)."' or ".
				"SameAs in ('use \"".implode("\"', 'use \"", $names)."\"')");
			
			while($row = $r->fetch_assoc())
			{				
				$row['uninvoiced_total'] = $uninvoiced_total;
				
				try
				{
					if($row['ConsignorName'] == "Bruce")
						$row['unpaid_consignments'] = "0";
					else
						$row['unpaid_consignments'] = $this->unpaid_consignments($row['ConsignorName']);
				}
				catch(exception $e)
				{
					$row['unpaid_consignments'] = "0";
				}
				
				$r2 = $this->db->query("select balance from accounting.receivable_accounts ".
					"where consignor_id = '$row[auto_id]'");
					
				if($r2->num_rows)
				{
					list($row['balance_due']) = $r2->fetch_row();
				}
				else
				{
					$row['balance_due'] = 0;
				}
				
				$row['if_applied'] = bcsub(bcadd($row['uninvoiced_total'], $row['balance_due'], 2), $row['unpaid_consignments'], 2);
				
				$row['uninvoiced_total'] = "$".number_format($row['uninvoiced_total'], 2);
				$row['unpaid_consignments'] = "$-".number_format($row['unpaid_consignments'], 2);
				$row['balance_due'] = "$".number_format($row['balance_due'], 2);
				$row['if_applied'] = "$".number_format($row['if_applied'], 2);
				
				$row['pending_items'] = $this->call("consignor_pending_items", array($row['ConsignorName']));
				
				$row['search_auction_tables_link'] = 
					"/consignor_search/index.php?linked_consignor=".urlencode($row['ConsignorName']);
					
				$row['edit_consignors_link'] = 
					"/consignors/?search_field=consignorname&search_value=".urlencode($row['ConsignorName']);
				
				if($row['dateadded'] == "2001-01-01 00:00:00")	
					$row['since'] = "forever";
				else
					$row['since'] = date("m/d/Y", strtotime($row['dateadded']));
				
					
				
				$rows[] = $row;
			}
			
			return $rows;
		}
		else
			return false;
	}
	
	function owed($customer_id)
	{
		$owed = array();
		
		$r = $this->db->query("select email from invoicing.customers ".
			"where customer_id = '".$this->db->escape_string($customer_id)."'");
		
		list($email) = $r->fetch_row();
		
		$this->db->query("create temporary table invoice_numbers ".
			"select invoice_number from invoices ".
			"where customer_id = '".$this->db->escape_string($customer_id)."' and cc_notes not like '%auth%' and cc_notes like '%decline%'");

		$r = $this->db->query("select sum(price) as amount, date(min(`date`)) as begin, date(max(`date`)) as end, ".
			"group_concat(distinct invoice_numbers.invoice_number) as invoices ".
			"from sales ".
			"left join invoice_numbers using (invoice_number) ".
			"where (invoice_printed = 0 or invoice_numbers.invoice_number is not null) and ebay_email = '".$this->db->escape_string($email)."' ");
			
		list($owed['amount'], $owed['begin'], $owed['end'], $owed['invoices']) = $r->fetch_row();
		
		$owed['amount'] = "$".number_format($owed['amount'], 2);
		
		$this->db->query("drop temporary table if exists invoice_numbers");
		
		$owed['consignor_accounts'] = $this->consignor($customer_id, $email);
		
		$this->db->query("drop temporary table if exists invoice_numbers");
		
		return $owed;		
	}
	
	
	function declined_invoices($customer_id)
	{
		$r = $this->db->query("select email from invoicing.customers ".
			"where customer_id = '".$this->db->escape_string($customer_id)."'");
		
		list($email) = $r->fetch_row();
		
		$this->db->query("create temporary table invoice_numbers ".
			"select invoice_number from invoices ".
			"where customer_id = '".$this->db->escape_string($customer_id)."' and ".
			"cc_notes not like '%auth%' and cc_notes like '%decline%'");
		
		$r = $this->db->query("select sum(price) as amount, ".
			"group_concat(distinct invoice_numbers.invoice_number) as invoices ".
			"from sales ".
			"join invoices using (invoice_number) ".
			"join invoice_numbers using (invoice_number)");
			
		list($amount, $invoices) = $r->fetch_row();
		
		$r = $this->db->query("select sum(shipping_charged) as amount ".
			"from invoices  ".
			"join invoice_numbers using (invoice_number)");
			
		list($shipping) = $r->fetch_row();
		
		$amount += $shipping;
		
		$amount = "$".number_format($amount, 2);
		
		$this->db->query("drop temporary table if exists invoice_numbers");
		
		return array("amount" => $amount, "invoices" => $invoices);
	}
	
	function unpaid_consignments($consignor_name)
	{
		require_once("/webroot/tools/commission_reports/commission_reports.inc.php");
		
		list($groups, $rate) = report($consignor_name, $this->db);
	
		return $groups['Total'][3] - $groups['Total'][1];
	}
	
	function consignor_pending_items($consignor_name)
	{
		$r = $this->db->query("select count(*) from listing_system.`00-00-00 main (MAIN)` ".
					"where consignor = '".$this->db->escape_string($consignor_name)."' ");
					
		list($total) = $r->fetch_row();
		
		$r = $this->db->query("show tables from listing_system");
		
		while(list($table) = $r->fetch_row())
		{
			if(preg_match("/^[0-9]{2}-[0-9]{2}-[0-9]{2} /", $table) && !preg_match("/^00-00-00/", $table))
			{
				$r2 = $this->db->query("select count(*) from listing_system.`".$this->db->escape_string($table)."` ".
					"where consignor = '".$this->db->escape_string($consignor_name)."' ");
					
				list($count) = $r2->fetch_row();
				
				$total += $count;
			}
		}
		
		return $total;
	}
	
	
	/**
	 * Returns the invoicing.tbl_cc records for the customer $customer_id,
	 * or false when no records exist.
	 */
	function cards($customer_id)
	{
		$r = $this->db->query("select tbl_cc.* ".
			"from invoicing.tbl_cc ".
			"left join invoicing.invoices on cc_num = cc_which_one ".
			"where tbl_cc.customer_id = '".$this->db->escape_string($customer_id)."' and date_removed is null ".
			"group by cc_id ".
			"order by cc_order_to_use is null, cc_order_to_use");
		
		if($r->num_rows)
		{
			$rows = array();
			
			while($row = $r->fetch_assoc())
			{
				$row = self::process_card($row);
				
				$rows[] = $row;
			}
			
			usort($rows, array("self", "card_sort"));
			
			return $rows;
		}
		else
			return false;
	}
	
	
	function one_card($cc_id)
	{
		$r = $this->db->query("select tbl_cc.* ".
			"from invoicing.tbl_cc ".
			"left join invoicing.invoices on cc_num = cc_which_one ".
			"where tbl_cc.cc_id = '".$this->db->escape_string($cc_id)."'  ".
			"group by cc_id ".
			"order by cc_order_to_use is null, cc_order_to_use");
		
		if($r->num_rows)
			return self::process_card($r->fetch_assoc());
		else
			return false;
	}
	
	
	function process_card($row)
	{
		$icons = array(
			"MasterCard" => "mastercard.png",
			"Visa" => "visa.png",
			"American Express" => "amex.png",
			"Discover Card" => "discover.png",
		);
		
		$row['last_four'] = substr($row['cc_num'], -4);
		
		
		$row['redacted'] = substr($row['cc_num'], 0, strlen($row['cc_num'])-4);
		
		$row['icon'] = $icons[$row['type_of_cc']];
		
		$row['expired'] = date("ym") > substr($row['cc_exp'], 2, 2).substr($row['cc_exp'], 0, 2);
		
		if($row['ts'] > "2013-05-17 14:31:36") //The "zero" date for this table
			$row['updated'] = Time::days_ago($row['ts']);
		
		
		if(!empty($row['date_added']))
			$row['added'] = Time::days_ago($row['date_added']);
		
		$r2 = $this->db->query("select max(cc_date_processed) ".
			"from invoicing.invoices ".
			"where cc_which_one = '".$this->db->escape_string($row['cc_num'])."' ");
			
		if($r2->num_rows)
		{
			list($used) = $r2->fetch_row();
			$row['date_used'] = $used;
			$row['used'] = Time::days_ago($used);
		}
		else
		{
			
		}
		
		return $row;
	}
	
	
	function date_field_sort($one, $two)
	{
		return strcmp($two['timestamp'], $one['timestamp']);
	}
	
	
	function card_sort($one, $two)
	{
		if(!empty($one['cc_order_to_use']) && !empty($two['cc_order_to_use']))
		{
			//lowest is favored
			return strcmp($one['cc_order_to_use'], $two['cc_order_to_use']);
		}
		
		$one = array_filter(array($one['date_used'], $one['date_added']), "strlen");
		$two = array_filter(array($two['date_used'], $two['date_added']), "strlen");
		
		if(count($one) && !count($two))
		{
			//$one is favored
			return -1;
		}
		elseif(count($two) && !count($one))
		{
			//$two is favored
			return 1;
		}
		elseif(count($one) && count($two))
		{
			$one = max($one);
			$two = max($two);
			
			if($one > $two)
			{
				//$one is favored
				return -1;
			}
			elseif($two > $one)
			{
				//$two is favored
				return 1;
			}
		}
		
		return 0;
	}
	
	
	
	/**
	 * Takes the invoicing.customers.other_emails field for a customer
	 * and returns an array of email addresses.
	 */
	function parse_other_emails($emails, $notes = false)
	{
		$emails = array_map("trim", explode(";", $emails));
		$list = array();
		
		
		foreach($emails as $e)
		{
			if(preg_match("/^([A-Z0-9\._&%+-]+@[A-Z0-9\.-]+\.[A-Z]{2,4})([ ]+\([^\(\);]+\))?$/i", $e, $match))
			{
				if($notes)
				{
					if(empty($match[2]))
						$list[] = array($match[1], "");
					else
						$list[] = array($match[1], trim($match[2], " ()"));
				}
				else
				{
					$list[] = $match[1];
				}
			}
			else
			{
				//TODO: Maybe throw a warning or notify somebody
			}
		}
		
		return $list;
	}
	
	
	
	/**
	 * Takes an array of email addresses and 
	 * returns the invoicing.aa_customers records for those email addresses.
	 *
	 * Intended to get all of a customer's Auction Anything accounts.
	 */
	function aa_accounts($emails)
	{
		$query = "select * from invoicing.aa_customers where ";
		
		foreach($emails as $e)
		{
			$query .= "email = '".$this->db->escape_string($e)."' or ";
		}
		
		$query = substr($query, 0, -4);
		
		$r = $this->db->query($query);
		
		$rows = array();
		
		while($row = $r->fetch_assoc())
		{
			$rows[] = $row;
		}
		
		return $rows;
	}
	
	
	
	/**
	 * Takes an email address and returns an array containing
	 * the number of items this person has purchased over the last
	 * year, quarter, month, and week.
	 */
	function purchase_frequency($email)
	{
		$query = "select count(*) from invoicing.sales ".
			"where `ebay_email` = '".$this->db->escape_string($email)."' and ".
			"`date` > date_sub(now(), interval %s day) and ".
			"`reference` is null and price > 0";
		
		$r = $this->db->query(sprintf($query, 365));
		list($year) = $r->fetch_row();
		
		if($year > 0)
		{
			$r = $this->db->query(sprintf($query, 30*3));
			list($quarter) = $r->fetch_row();
			
			if($quarter > 0)
			{
				$r = $this->db->query(sprintf($query, 30));
				list($month) = $r->fetch_row();
				
				if($month > 0)
				{
					$r = $this->db->query(sprintf($query, 7));
					list($week) = $r->fetch_row();
					
					return array("year" => $year, "quarter" => $quarter, "month" => $month, "week" => $week);
				}
				else
					return array("year" => $year, "quarter" => $quarter, "month" => 0, "week" => 0);
			}
			else
				return array("year" => $year, "quarter" => 0, "month" => 0, "week" => 0);
		}
		else
			return array("year" => 0, "quarter" => 0, "month" => 0, "week" => 0);
	}
	
	
	
	function stats($customer_id, $email)
	{
		$result = array();
		
		$r = $this->db->query("select count(*) from invoicing.invoices ".
			"where customer_id = '".$this->db->escape_string($customer_id)."'");
			
		list($result['invoices']) = $r->fetch_row();
		$result['invoices'] = number_format($result['invoices']);
			
		$r = $this->db->query("select price from invoicing.sales ".
			"where price > 0 and (ebay_item_number regexp '^F?[0-9]{4,}' or ebay_item_number = 'book') and ".
			"customer_id = '".$this->db->escape_string($customer_id)."'");
			
		$prices = array();
			
		while(list($price) = $r->fetch_row())
		{
			$prices[] = $price;
		}
		
		
		
		$r = $this->db->query("select rank, vip ".
			"from customers_scores ".
			"where customer_id = '".$this->db->escape_string($customer_id)."'");
		list($result["rank"], $result['vip']) = $r->fetch_row();
		$result['rank'] = intval($result['rank']);
		
		$r = $this->db->query("select count(*) from customers_scores");		
		list($result['out_of']) = $r->fetch_row();
		$result['out_of'] = intval($result['out_of']);
		
		$result["total"] = "$".number_format(round(array_sum($prices)));
		if(count($prices))
		{
			$result["median"] = "$".number_format(round(Math::median($prices)));
			$result["average"] = "$".number_format(round(array_sum($prices) / count($prices)));
		}
		$result["items"] = number_format(count($prices));
		
		
		
		//Get "customer since"
		$r = $this->db->query("select customer_since from invoicing.customers where customer_id = '$customer_id'");
		list($result['customer_since']) = $r->fetch_row();
		
		if(false == strtotime($result['customer_since']))
		{
			$r = $this->db->query("select date(min(`date_of_invoice`)) from invoicing.invoices ".
				"where date_of_invoice > 0 and  customer_id = '$customer_id'");
			
			if($r->num_rows)
			{
				list($result['customer_since']) = $r->fetch_row();
			}
			else
			{
				$result['customer_since'] = "";
			}
		}
		else
		{
			$result['customer_since'] = date("m/d/y", strtotime($result['customer_since']));
		}
		
		
		//Get last purchase
		$r = $this->db->query("select max(`date`) from invoicing.sales where ebay_email = '".$this->db->escape_string($email)."'");
		
		list($result['last_purchase']) = $r->fetch_row();
		
		if(!empty($result['last_purchase']))
			$result['last_purchase'] = date("m/d/y", strtotime($result['last_purchase']));

		if($result['vip'] != 0)
		{
			$r = $this->db->query("select count(*) ".
				"from customers_scores ".
				"where `vip` != 0 and rank <= $result[rank]");
				
			list($result['vip_rank']) = $r->fetch_row();
		}

			
		return $result;
	}
	
	
	
	/**
	 * Gets all invoicing.tbl_blocked_bidders records associated with
	 * an email address.
	 */
	function blocked_bidders($email)
	{
		$email = $this->db->escape_string($email);
		
		$r = $this->db->query(
			"(select ebay_id, email, how_blocked, reason, date_blocked, autoid from invoicing.tbl_blocked_bidders where email = '$email')".
			"union ".
			"(select ebay_id, email, how_blocked, reason, date_blocked, autoid from invoicing.tbl_blocked_bidders where AA_id = ".
			"(select ID from invoicing.aa_customers where email = '$email' limit 1)) ".
			//"order by ebay_id = '$email' desc, email = '$email' desc, ebay_id like '%@%'"
			"order by email = '$email' desc, ebay_id like '%@%', ebay_id = '$email' desc " //Phillip wanted it ordered this way. 2015-02-24 AK
			);
		
		$rows = array();
		
		if($r->num_rows)
		{
			while($row = $r->fetch_assoc())
			{
				$rows[] = $row;
			}
		}
		
		return $rows;		
	}
	
	/**
	 * Gets all invoicing.tbl_blocked_bidders records associated with
	 * an email address.
	 */
	function blocked_bidders_multi($first_email, $emails)
	{
		$emails[] = $first_email;
		
		$first_email = $this->db->escape_string($first_email);
		
		$r = $this->db->query("select AA_id from invoicing.tbl_blocked_bidders ".
			"where email = '$first_email' and AA_id != ''");
			
		if($r->num_rows == 1)
		{
			list($aa_id) = $r->fetch_row();
		}
		
		$list = "('".implode("','", array_map(array($this->db, "escape_string"), $emails))."')";
		
		$r = $this->db->query(
			"(select ebay_id, email, how_blocked, reason, date_blocked, autoid, AA_id from invoicing.tbl_blocked_bidders where email in $list)".
			"union ".
			"(select ebay_id, email, how_blocked, reason, date_blocked, autoid, AA_id from invoicing.tbl_blocked_bidders where AA_id in ".
			"(select ID from invoicing.aa_customers where email in $list)) ".
			//"order by ebay_id = '$email' desc, email = '$email' desc, ebay_id like '%@%'"
			"order by email = '$first_email' desc, ebay_id like '%@%', ebay_id = '$first_email' desc "
			);
		
		$rows = array();
		
		if($r->num_rows)
		{
			while($row = $r->fetch_assoc())
			{
				if(isset($aa_id) && empty($row['AA_id']))
					$row['autolinked'] = "autolinked";
				$rows[] = $row;
			}
		}
		
		return $rows;		
	}
	
	
	
	/**
	 * Takes the full name and country code (from the billing address)
	 * for returns the next available "letters & numbers" customer ID.
	 * 
	 * Generates the "letters & numbers" customer id for the invoicing system.
	 * For example, give it "Phillip Wages" and "US" and it will return 
	 * the next available customer id, "wagp03".
	 */
	function generate_customer_id($name, $country_code)
	{
		$split_name = preg_split("/\s+/", preg_replace("/[^a-zA-Z ]/", "", strtolower(trim($name))));

		$last_name = array_pop($split_name);
		$first_name = implode(" ", $split_name);

		$prefix = substr($last_name, 0, 3);
		
		if($country_code == "US")
		{
			$prefix .= substr($first_name."xxxx", 0, 4-strlen($prefix));
		}
		else
		{
			$prefix .= substr($first_name."xxxx", 0, 3-strlen($prefix));
		}
		

		if(($number = $this->generate_customer_id_number($prefix)) > 99)
		{
			$prefix = substr($prefix, 0, 3)."x";
				
			$number = $this->generate_customer_id_number($prefix);
		}

		return $prefix.str_pad($number, 2, "0", STR_PAD_LEFT);
	}
	
	private function generate_customer_id_number($prefix)
	{
		$r = $this->db->query("select customer_id from invoicing.customers ".
			"where customer_id regexp '^{$prefix}[0-9]+$' ".
			"order by customer_id desc limit 1");

		if($r->num_rows)
		{
			list($customer_id) = $r->fetch_row();
			$number = substr($customer_id, -2);
			
			return ++$number;
		}
		
		return 1;
	}
	
	
	/**
	 * Takes a list of email addresses and gets a list of checkout payments
	 * for those emails. Intended to get a single customer's 
	 * checkout payments.
	 */
	function website_orders($emails)
	{
		require_once("secure_message_opener.inc.php");
		$opener = new Secure_Message_Opener();
		
		$query = "select data, `key`, data2, key2, id, email, timestamp ".
			"from invoicing.website_orders where ";
		
		foreach($emails as $e)
		{
			$query .= "email = '".$this->db->escape_string($e)."' or ";
		}
		
		$query = substr($query, 0, -4)." order by timestamp desc";
		
		$r = $this->db->query($query);
		
		if(!defined("PVT_KEY_PASS"))
			define("PVT_KEY_PASS", "How come nobody ever uses spaces in their passphrases?");
		
		$rows = array();
		
		while(list($data, $key, $data2, $key2, $id, $email, $timestamp) = $r->fetch_row())
		{
			try
			{
				$unserialized = $opener->open($data, $key);
			
				
				
				if(isset($unserialized[0]))
				{
					$orders = $unserialized;
				}
				else
				{
					$orders = array($unserialized);
				}
				
				foreach($orders as $unserialized)
				{
					$row = array(
						"id" => $id,
						"date" => date("m/d/y", $unserialized['timestamp']),
						"timestamp" => $unserialized['timestamp'],
						"total" => 0,
						"payment" => "",
						"items" => array()
					);
				
					if(!empty($unserialized['order']['subtotal']))
						$row['total'] += $unserialized['order']['subtotal'];
						
					if(!empty($unserialized['order']['shipping']))
						$row['total'] += $unserialized['order']['shipping'];
					
					if(!empty($row['total']))
						$row['total'] = number_format($row['total'], 2);
					
					if(!empty($unserialized['payment']['paypal']))
					{
						$row['payment'] = "PayPal";
					}
					elseif(!empty($unserialized['payment']['credit_card_onfile']))
					{
						if(!empty($unserialized['payment']['credit_card_onfile_update']['id']))
						{
							$r2 = $this->db->query("select type_of_cc from invoicing.tbl_cc ".
								"where cc_id = '".$this->db->escape_string($unserialized['payment']['credit_card_onfile_update']['id'])."'");
							list($row['payment']) = $r2->fetch_row();
						}
						else
						{
							$row['payment'] = "Card";
						}
					}
					elseif(!empty($unserialized['payment']['credit_card']))
					{
						$row['payment'] = $unserialized['payment']['credit_card']['type'];
					}
					elseif(empty($unserialized['payment']) && $unserialized['op'] == "quote_request")
					{
						$row['payment'] = "quote";
					}
					else
					{
						$row['payment'] = "?";
					}
						
					if(is_array($unserialized['order']))
						$row['items'] = count($unserialized['order']['items']);
					
					$rows[] = $row;
				}
			
				
			}
			catch(exception $e)
			{
				
				$rows[] = array(
					"id" => $id,
					"date" => date("m/d/y", strtotime($timestamp)),
					"timestamp" => $timestamp,
					"subtotal" => "error",
					"payment" => "e",
				);
			}
		}
		
		usort($rows, array("self", "date_field_sort"));
		
		return $rows;
	}
	
	function validate($customer_id)
	{
		$warnings = array();
		
		$r = $this->db->query("select * from invoicing.customers where customer_id = '$customer_id'");
		
		$row = $r->fetch_assoc();
		
		if(empty($row['ship_country']))
			$warnings[] = "Country is empty";
		
		return $warnings;
	}
	
	/**
	 * Takes a list of email addresses and gets the list of PayPal 
	 * payments from the PayPal Instant Notification System.
	 *
	 * Order descending by transaction id then timestamp.
	 * Only use the first record of each transaction id.
	 */
	function paypal_payments($emails)
	{
		$query = "select * from invoicing.paypal_notifications where ";
		
		foreach($emails as $e)
		{
			$query .= "payer_email = '".$this->db->escape_string($e)."' or ";
		}
		
		$query = substr($query, 0, -4)." order by payment_date desc";
		
		$r = $this->db->query($query);
		
		$rows = array();
		
		while($row = $r->fetch_assoc())
		{
			if(!array_key_exists($row['txn_id'], $rows))
			{
				try
				{
					$payment = unserialize($row['raw']);
					
					$payment['payment_date'] = date("m/d/y", strtotime($payment['payment_date']));
					$payment['payment_status'] = $row['payment_status'];
					
					$rows[$row['txn_id']] = $payment;
				}
				catch(exception $e)
				{
					unset($row['raw']);
					$rows[$row['txn_id']] = $row;
				}				
			}
		}
		
		return $rows;
	}
	
	
	
	
	function paypal_payments_received($emails)
	{
		/*
		"payment_date" 	: "When",
		"name" 			: "Name",
		"payer_email" 	: "Email",
		"mc_gross" 		: "Amt",
		"payment_status": "St",
		"txn_type"		: "Typ",
		"txn_id"		: "Txn id",
		"memo"			: "M",
		id
		timestamp
		*/
		
		$query = "select concat(`Date`, ' ', Time) as payment_date, concat(`Date`, ' ', Time) as timestamp, ".
			"Name as name, `To Email Address` as payer_email, Gross as mc_gross, Status as payment_status, Type as txn_type, ".
			"`Transaction ID` as txn_id, `Transaction ID` as id ".
			"from accounting.paypal_history where ";
		
		foreach($emails as $e)
		{
			$query .= "`To Email Address` = '".$this->db->escape_string($e)."' or ";
		}
		
		$query = substr($query, 0, -4)." order by Date desc, Time desc limit 1000";
		
		$r = $this->db->query($query);
		
		$rows = array();
		
		while($payment = $r->fetch_assoc())
		{
			$payment['name'] = "";
				
			if($payment['txn_type'] == "Payment Sent")
				$payment['txn_type'] = "";
			
			if(!array_key_exists($payment['txn_id'], $rows))
			{
				$payment['payment_date'] = date("m/d/y", strtotime($payment['payment_date']));
			
				$rows[$payment['txn_id']] = $payment;
			}
		}
		
		return $rows;
	}
	
	
	
	/**
	 * Pay & Hold calculator
	 * Calculate a customer's Pay & Hold due dates
	 *
	 * Group all Paid & Hold Invoice items on a customer's account 
	 * by the shipping_notes field (which usually says 'flat' or 'rolled' and 
	 * indicates whether an invoice can be combined with another invoice into
	 * one shipment). For each group, get the purchase date of the 
	 * oldest item with a positive price and add PAY_AND_HOLD_DAYS days
	 * to it, add one extra day, and this is the pay and hold due date
	 * for this group of invoices.
	 *
	 * PAY_AND_HOLD_DAYS is calculated as follows:
	 * If customer record has a non-null pay_and_hold_days field, use that. This is for customers with special exceptions.
	 * If customer's shipping address is in the United States, use 21 days. now 28 2019-02-15
	 * If customer's shipping address is outside the United States, use 42 days. now 56 2019-02-15
	 */
	function pay_and_hold_due_dates($customer_id)
	{
		$r = $this->db->query("select t1.shipping_notes as shipping_notes, min(t2.`date`) as min_date, ".
			"if(pay_and_hold_days is null, date_add(if(ship_country = 'US', date_add(min(t2.`date`), interval 28 day), ".
			"date_add(min(t2.`date`), interval 56 day)), interval 1 day), date_add(min(t2.`date`), interval pay_and_hold_days+1 day)) as due ".
			"from invoicing.sales as t1 ".
			"join invoicing.customers on (ebay_email = email) ".
			"join invoicing.sales as t2 on (t1.reference = t2.invoice_number) ".
			"where customers.customer_id = '".$this->db->escape_string($customer_id)."' and t1.invoice_printed = 0 and t2.price > 0 ".
			"group by shipping_notes");
		
		$due_dates = array();
		
		while($row = $r->fetch_assoc())
		{
			$row['min_date'] = date("Y-m-d", strtotime($row['min_date']));
			$row['due'] = date("Y-m-d", strtotime($row['due']));
			
			$due_dates[] = $row;
		}
			
		return $due_dates;
	}
	
	
	
	
	/**
	 * Get a customer's invoice records.
	 */
	function invoices($customer_id, $shipped = false)
	{
		$r = $this->db->query("select invoices.*, ship_country, pay_and_hold_days ".
			"from invoicing.invoices ".
			"join invoicing.customers using(customer_id) ".
			"left join sales on invoices.invoice_number = reference ".
			"where invoices.customer_id = '".$this->db->escape_string($customer_id)."' and ".
			"date_of_invoice > '2010' and (`status` = 'unshipped' and sales.invoice_number is null) ".
			"order by invoices.invoice_printed = '0' desc, date_of_invoice desc");
		
		$invoices = array();
		
		while($row = $r->fetch_assoc())
		{
			$invoices[] = Invoice::process($row, $this->db);
		}
		
		return $invoices;
	}
	
	
	
	/**
	 * Get experts information.
	 * Takes an array of email addresses.
	 */
	function expert($emails)
	{
		$query = "select * from listing_system.experts where (";
	  
		foreach($emails as $e)
		{
			$query .= "email = '".$this->db->escape_string($e)."' or ";
		}
	  
		$query = substr($query, 0, -4).")";
		
		
		
		$r = $this->db->query($query);
		
		$data = array();
		
		while($row = $r->fetch_assoc())
		{
			$data[] = $row;
		}
		
		return $data;
	}
	
	
	function shared_accounts($customers_id)
	{
		$shared_accounts = $this->shared_accounts2($customers_id);
		
		if(count($shared_accounts) == 0)
			return array();
		
		$r = $this->db->query("select name, customer_id, email, ship_city, ship_state, ship_country, customers_id ".
			"from customers ".
			"where customers_id in (".implode(",", $shared_accounts).")");
		
		$shared_accounts = $emails = array();
		
		while($row = $r->fetch_assoc())
		{
			if($row['customers_id'] != $customers_id)
				$shared_accounts[$row['customers_id']] = $row;
			
			$emails[] = $row['email'];
		}
		
		
		if(empty($emails))
			return array_values($shared_accounts);
		
		/*
			$ids = array of aa_ids from tbl_blocked_bidders found using $emails
		*/
		$ids = array();
		
		$r = $this->db->query("select AA_id from invoicing.tbl_blocked_bidders ".
			"where AA_id != '' and email in ('".implode("','", array_map(array($this->db, "escape_string"), $emails))."')");
		
		while(list($id) = $r->fetch_row())
			$ids[] = $id;
		
		
		if(empty($ids))
			return array_values($shared_accounts);
		
		/*
			$emails = array of emails in tbl_blocked_bidders found using $ids
		*/
		$emails = array();
		
		$r = $this->db->query("select email from invoicing.tbl_blocked_bidders ".
			"where email != '' and AA_id in ('".implode("','", array_map(array($this->db, "escape_string"), $ids))."')");
			
		while(list($email) = $r->fetch_row())
			$emails[] = $email;
		
		
		if(empty($emails))
			return array_values($shared_accounts);
		
		/*
			Find any accounts from $emails and add to $shared_accounts
		*/
		$r = $this->db->query("select name, customer_id, customers.email, ship_city, ship_state, ship_country, customers_id ".
			"from customers ".
			"join customers_emails using (customers_id) ".
			"where customers_emails.email in ('".implode("','", array_map(array($this->db, "escape_string"), $emails))."')");
			
		while($row = $r->fetch_assoc())
		{
			if($row['customers_id'] == $customers_id)
				continue;
			
			if(empty($shared_accounts[$row['customers_id']]))
				$shared_accounts[$row['customers_id']] = $row;
		}
		
		return array_values($shared_accounts);
	}
	
	
	function shared_accounts2($customers_id, $shared_accounts = array())
	{
		$r = $this->db->query("select t2.customers_id ".
			"from invoicing.customers_emails as t1 ".
			"join invoicing.customers_emails as t2 on (t1.email = t2.email) ".
			"where t1.customers_id = '$customers_id' and t2.customers_id != '$customers_id'");
			
		while(list($customers_id) = $r->fetch_row())
		{
			if(!in_array($customers_id, $shared_accounts))
			{
				$shared_accounts[] = $customers_id;
				
				$shared_accounts = $this->shared_accounts2($customers_id, $shared_accounts);
			}
		}
		
		return $shared_accounts;
	}
	
	
	/**
	 * Get customers with similar names.
	 * Takes the name (first & last), and the 
	 * current customer_id, so we can exclude that from the results.
	 */
	function similar_names($data, $exclude_account = "")
	{
		$output = array();
		
		if(empty($data['customer']['address']))
			return array();
		
		$names = preg_split("/ +/", $data['customer']['address']['name'], 2);
		
		if(count($names) == 2)
		{
		
		list($first, $last) = $names;
		
		$first_names = array($first);
		
		$r = $this->db->query("select nick from invoicing.nicknames where name = '".$this->db->escape_string($first)."'");
		
		while(list($nick) = $r->fetch_row())
		{
			$first_names[] = $nick;
		}
		
		$query = "select customer_id, name, email, ship_country, ship_state, ship_city ".
			"from invoicing.customers ".
			//"left join invoicing.aa_customers using(email) ".
			"where customer_id != '".$this->db->escape_string($exclude_account)."' and ".
			//"email like '%@%' and ".
			//"and (Status_Account = 1 or Status_Account is null) ".
			"(";
		
		foreach($first_names as $first)
			$query .= "name = '".$this->db->escape_string("$first $last")."' or ";
		
		$query = substr($query, 0, -4).")";

		$r = $this->db->query($query);
		
			
		
		while($row = $r->fetch_assoc())
		{
				$output[] = $row;
		}
		
			
			
			//Shipping address => Billing address 
			$query = "select t1.customer_id, t1.name, bill_country as ship_country, bill_state as ship_state, bill_city as ship_city ".
				"from invoicing.bill_to_address t1 ".
				"join invoicing.customers using(customer_id) ".
				"where t1.customer_id != '".$this->db->escape_string($exclude_account)."' and (";
			
			foreach($first_names as $first)
				$query .= "t1.name = '".$this->db->escape_string("$first $last")."' or ";
				
			$query = substr($query, 0, -4).")";
			
			$r = $this->db->query($query);
			
			while($row = $r->fetch_assoc())
			{
				$output[] = $row;
			}
			
			
			//AKAs
			$query = "select customer_id, name, email, ship_country, ship_state, ship_city ".
				"from invoicing.customers ".
				"join invoicing.customers_aka using(customers_id) ".
				"where customer_id != '".$this->db->escape_string($exclude_account)."' and ".
				"(";
			
			foreach($first_names as $first)
				$query .= "aka = '".$this->db->escape_string("$first $last")."' or ";
			
			$query = substr($query, 0, -4).")";

			$r = $this->db->query($query);
			
			while($row = $r->fetch_assoc())
			{
				$output[] = $row;
			}
						
		}
		
		
		
		//Billing address => Shipping address
		if(!empty($data['billing_address']))
		{
			
			$names2 = preg_split("/ +/", $data['billing_address']['billing_address']['name'], 2);
			
			if(count($names2) == 2 && $names != $names2)
			{
				list($first, $last) = $names2;
				
				$first_names = array($first);
				
				$r = $this->db->query("select nick from invoicing.nicknames where name = '".$this->db->escape_string($first)."'");
				
				while(list($nick) = $r->fetch_row())
				{
					$first_names[] = $nick;
				}
				
				$query = "select customer_id, name, email, ship_country, ship_state, ship_city ".
					"from invoicing.customers ".
					//"left join invoicing.aa_customers using(email) ".
					"where customer_id != '".$this->db->escape_string($exclude_account)."' and ".
					//"email like '%@%' and ".
					//"and (Status_Account = 1 or Status_Account is null) ".
					"(";
				
				foreach($first_names as $first)
					$query .= "name = '".$this->db->escape_string("$first $last")."' or ";
				
				$query = substr($query, 0, -4).")";
				
				$r = $this->db->query($query);
				
				while($row = $r->fetch_assoc())
				{
					$output[] = $row;
				}
			}
		}
		
		return $output;
	}
	
	
	/**
	 * Check for potential duplicate accounts before inserting new record.
	 */
	//TODO: Use the dupe codes and the customer_dedupe table
	function dupe_check($data)
	{
		$similar = array();
		
		$records = $this->similar_names($data['name']);
		
		foreach($records as $r)
		{
			$r['reason'] = "similar name";
			$similar[] = $r;
		}
		
		if(!empty($data['email']))
		{
			$r = $this->db->query("select customer_id, name, email, ship_country, ship_state, ship_city ".
				"from invoicing.customers ".
				"where email = '".$this->db->escape_string($data['email'])."'");
				
			if($r->num_rows)
			{
				while($row = $r->fetch_assoc())
				{
					if(empty($similar[$row['customer_id']]))
					{
						$row['reason'] = "same email";
						$similar[] = $row;
					}
				}
			}
			
			$r = $this->db->query("select customer_id, name, customers.email, ship_country, ship_state, ship_city ".
				"from invoicing.customers ".
				"join invoicing.customers_emails using(customers_id) ".
				"where customers_emails.email = '".$this->db->escape_string($data['email'])."'");
				
			if($r->num_rows)
			{
				while($row = $r->fetch_assoc())
				{
					if(empty($similar[$row['customer_id']]))
					{
						$row['reason'] = "email in other_emails";
						$similar[] = $row;
					}
				}
			}
		}
		
		
		
		return $similar;
	}
	
	
	/**
	 * Format human-readable city/state/country
	 */
	static function format_location($data)
	{
		$output = $data['ship_city'];
		
		if(!empty($data['state_name']))
			$output .= ", ".$data['state_name'];
		
		if(in_array($data['ship_country'], array("US", "GB", "FR", "AU", "CA")))
			$output .= ", ".$data['ship_country'];
		else
			$output .= ", ".$data['country_name'];
		
		return $output;
	}
	
	/**
	 * Calculate pitascore.
	 */
	function pitascore($data)
	{
		$score = max(pow(strlen($data['customer']['address']['notes_for_invoice']), 0.5) / 25, 1);
		
		foreach($data['block_records'] as $b)
		{
			$score *= max(strlen($b['reason']) / 25, 1);
			
			$score *= max(pow(substr_count(strtolower($b['reason']), "never unblock")+substr_count(strtolower($b['reason']), "ever unblock"), 2.5), 1);
			
			$score *= max(pow(substr_count(strtolower($b['reason']), "never paid")+substr_count(strtolower($b['reason']), "didn't pay"), 2), 1);
			
			$score *= max(pow(substr_count(strtolower($b['reason']), "cancel"), 1.5), 1);
			
			$score *= max(substr_count(strtolower($b['reason']), "no response")+substr_count(strtolower($b['reason']), "didn't respond")+substr_count(strtolower($b['reason']), "ignor"), 1);
		}
		
		$score *= max(pow(count($data['cards']), 0.5), 1);
		
		//$score *= max(pow(strlen($data['customer']['address']['other_emails']) / 25, 0.5), 1);
		
		$r = $this->db->query("select count(distinct `date`) from sales ".
			"where reminder_notes != '' and ebay_email = '".$this->db->escape_string($data['customer']['address']['email'])."'");
			
		list($count) = $r->fetch_row();
		
		$score *= max($count, 1);
		
		$r = $this->db->query("select count(*) from invoicing.invoices ".
			"where customer_id = '".$this->db->escape_string($data['customer']['address']['customer_id'])."' and ".
			"(payment_method like 'check%' or payment_method like 'money order%' or payment_method like 'split%')");
		
		list($count) = $r->fetch_row();
		
		$score *= max(pow($count, 0.5), 1);
		
		$r = $this->db->query("select count(*) from invoicing.cc_log ".
			"join invoices using(invoice_number) ".
			"where customer_id = '".$this->db->escape_string($data['customer']['address']['customer_id'])."' and ".
			"cc_log.`status` = 'failure'");
		
		list($count) = $r->fetch_row();
		
		$score *= max($count, 1);
		
		return round($score);
	}
	
	
	function printout_history($customer_id)
	{
		$r = $this->db->query("select phone_order_printouts.id, `ts`, `name` ".
			"from invoicing.phone_order_printouts ".
			"left join `poster-server`.users on (`who` = users.id) ".
			"where customer_id = '".$this->db->escape_string($customer_id)."' order by ts desc");
			
		$data = array();
		
		while($row = $r->fetch_assoc())
			$data[] = $row;
			
		return $data;
	}
	
	
	function alerts($customer_id, $customers_id)
	{
		$alerts = array();
		
		/*
			Declined card
     * 2018-10-18 Terri/Angie show declines for shipped invoices as well
		*/
		$r = $this->db->query("select invoice_number,status "
		.	"from invoices ".
			"where invoices.customer_id = '".$this->db->escape_string($customer_id)."' and ".
			//"date_of_invoice > '2010' and (`status` = 'unshipped') and ".
			"date_of_invoice > '2010' and ".
			"cc_notes like '%decline%' and cc_notes not like '%authorized%'");
		
		if($r->num_rows)
		{
			$alert = "Declined card charges: ";
			
			while(list($invoice_number,$status) = $r->fetch_row())
				$alert .= "<a href='single_invoice.php?invoice_number=$invoice_number' target='_blank'>#".$invoice_number . ($status=='shipped' ? ' (SHIPPED)' : '') . "</a>, ";
			
			$alert = substr($alert, 0, -2);
			
			$alerts[] = $alert;
		}
		
		/*
			Tickets
		*/
		if($tickets = $this->tickets($customers_id))
		{
			foreach($tickets as $t)
			{
				$alerts[] = "<b>Open Ticket:</b> <a href='javascript:void(0)' onclick='load_ticket(".$t->getId().", invoicing_ticket_onsave)'> ".htmlspecialchars($t->getTitle())."</a>";
			}
		}
		
		return $alerts;
	}
	
	
	function billing_address_is_empty($bill_to)
	{
		return (trim($bill_to['name'].$bill_to['bill_attention_line'].$bill_to['bill_address_line1'].
			$bill_to['bill_address_line2'].$bill_to['bill_city'].$bill_to['bill_state'].
			$bill_to['bill_zip'].$bill_to['bill_country']) == "");
	}
	
	function quote_requests($username)
	{
		if($this->wdb)
		{
			$r = $this->wdb->query("select t2.item_id, t2.bonus_item, ".
				//"t3.package_id, ".
				"t1.qpp_id, t1.qpp_datetime, t1.`type`, item_lotnum ".
				"from members.quotependingprint as t1 ".
				"join members.users on (users_id = users.id) ".
				"join members.checkout t2 using(qpp_id) ".
				"left join members.items_winners t3 using(item_id) ".
				"where users.username = '".$this->wdb->escape_string($username)."' ".
				"order by qpp_id desc");
			
			$list = $item_ids = array();
			
			while($row = $r->fetch_assoc())
			{
				if(!isset($list[$row['qpp_id']]))
				{
					$list[$row['qpp_id']] = array(
						"qpp_id" => $row['qpp_id'],
						"datetime" => $row['qpp_datetime'],
						"qpp_datetime" => date("m/d/y H:i", strtotime($row['qpp_datetime'])),
						"type" => $row['type'],
						"items" => array(),
						"item_ids" => array(),
					);
				}
				
				unset($row['qpp_datetime'], $row['type']);
				
				add($list[$row['qpp_id']]['items'], substr($row['item_lotnum'], 0, 2), $row);	
				$list[$row['qpp_id']]['item_ids'][] = $row['item_id'];
			}
			
			
			
			foreach($list as $qpp_id => $quote_request)
			{
				$r = $this->db->query("select ticket.* ".
					"from invoicing.ticket ".
					"where reference_type = 'qpp_id' and `reference` = '$qpp_id'");
					
				if($r->num_rows)
				{
					$row = $r->fetch_assoc();
					
					if(!empty($row['finished']))
						$row['icon'] = "ticket_g.png";
					else if(!empty($row['pending']))
						$row['icon'] = "ticket_y.png";
					else
						$row['icon'] = "ticket_r.png";
					
					$row['onclick'] = "invoicing_load_ticket(this)";
				
					$list[$qpp_id]['ticket'] = $row;
				}
				else
				{
					$list[$qpp_id]['ticket'] = array(
						"icon" => "ticket.png",
						"onclick" => "invoicing_new_ticket_dialog(this)",
					);
				}
				
				$r = $this->db->query("select ebay_item_number, package_id, `ready`, `approved`, combine_type, invoices.`status` ".
					"from invoicing.sales ".
					"left join invoicing.invoices on (sales.invoice_number = invoices.invoice_number) ".
					"left join invoicing.quotes_packages using(package_id) ".
					"left join invoicing.quotes using(quote_id) ".
					"where ebay_item_number in ".
					"('".implode("','", array_map(array($this->db, "escape_string"), $quote_request['item_ids']))."')");
				
				$list[$qpp_id]['statuses'] = $list[$qpp_id]['combine_types'] = array();
				
				$list[$qpp_id]['quoted'] = true;
				
				$list[$qpp_id]['hide'] = true;
				
				while(list($item_number, $package_id, $ready, $approved, $combine_type, $status) = $r->fetch_row())
				{
					if(empty($status) || $status != "shipped")
						$list[$qpp_id]['hide'] = false;
					
					if(empty($package_id))
						$list[$qpp_id]['quoted'] = false;
					
					if(empty($ready))
					{
						increment($list[$qpp_id]['statuses'], "Not Ready");
					}
					elseif(empty($approved))
					{
						increment($list[$qpp_id]['statuses'], "Ready");
					}
					else
					{
						increment($list[$qpp_id]['statuses'], "Approved");
					}
					
					increment($list[$qpp_id]['combine_types'], $combine_type);
					
					//$package_ids[$item_number] = $package_id;
				}
				
				if($list[$qpp_id]['quoted'] === false && $list[$qpp_id]['combine_types'] && 
					(!empty($list[$qpp_id]['combine_types'][9]) || !empty($list[$qpp_id]['combine_types'][12]) || !empty($list[$qpp_id]['combine_types'][28])))
				{
					$list[$qpp_id]['autoquote'] = "<img class='hoverbutton' title='Autoquote' ".
						"src='/includes/graphics/quote.png' onclick='autoquote($qpp_id)' />";
				}
				else
				{
					$list[$qpp_id]['autoquote'] = "";
				}
				
				if(!empty($list[$qpp_id]['statuses']['Not Ready']))
				{
					$list[$qpp_id]['status'] = "Not Ready";
				}
				else if(!empty($list[$qpp_id]['statuses']['Ready']))
				{
					$list[$qpp_id]['status'] = "Ready";
				}
				else if(!empty($list[$qpp_id]['statuses']['Approved']))
				{
					$list[$qpp_id]['status'] = "Approved";
				}
					
				
				$title = "";
				
				foreach($quote_request['items'] as $lot => $items)
				{
					if(count($items) == 1)
					{
						$title .= $items[0]['item_lotnum'].", ";
					}
					else
					{
						$title .= "$lot (".count($items)."), ";
					}
				}
				
				$list[$qpp_id]['title'] = substr($title, 0, -2);
			}
			
			foreach($list as $k => $quote_request)
			{
				if($quote_request['hide'])
					unset($list[$k]);
			}
			
			$list = array_values($list);
			
			usort($list, "order_quote_requests");
			
			return $list;
			
		}
		
		return false;
	}
}


function order_quote_requests($row1, $row2)
{
	return strcmp($row2['datetime'], $row1['datetime']);
}


/**
 * Class used for searching for a customer by
 * various methods.
 */
class CustomerSearch
{
	//TODO: use nicknames and levenshtein
	//TODO: load names and customer ids into browsers and do it in javascript, then asynchronously for more thorough search.
	/*
		Search by:
		
		any value in customer table
		item title or item number
		consignor name
		username
		invoice number
		package id
		quote id
	*/
	function __construct($db, $blab)
	{
		require_once("/webroot/includes/string.inc.php");
		$this->db = $db;
		$this->blab = $blab;
	}
	
	
	function call($method, $args)
	{
		$time = microtime(true);
		
		$ret = call_user_func_array(array($this, $method), $args);
		
		$this->last_interval = microtime(true) - $time;
		
		$this->blab->blab("Method '$method' took ".$this->last_interval." seconds to complete.");
		
		return $ret;
	}
	
	
	
	function nicks($name)
	{
		$r = $this->db->query(
			"select distinct nick from invoicing.nicknames ".
			"where `name` = '".$this->db->escape_string($name)."' ".
			"union ".
			"select distinct `name` from invoicing.nicknames ".
			"where `nick` = '".$this->db->escape_string($name)."'");
			
		$names = array($name);
		
		while(list($nick) = $r->fetch_row())
		{
			if(!in_array($nick, $names))
				$names[] = $nick;
		}
		
		return $names;
	}
	
	function __invoke()
	{
		$args = func_get_args();
		
		$ret = call_user_func_array(array($this, "search"), $args);
		
		return $ret;
	}
	
	
	/**
	 * Tries to find an Auction Anything account to get information
	 * to create a new customer
	 */
	function aa_search($term)
	{
		$term = $this->db->escape_string(trim($term));
		
		$r = $this->db->query("select * ".
			"from invoicing.aa_customers ".
			"where concat(FirstName, ' ', LastName) = '$term' or ".
			"email = '$term' or ID = '$term' or Username = '$term' ".
			"order by Status_Account = 1 desc");
			
		if($r->num_rows)
		{
			return $r->fetch_assoc();
		}
		else
		{
			$r = $this->db->query("select distinct ebay_email ".
				"from invoicing.sales ".
				"where ebay_item_number = '$term' or ".
				"ebay_title like '$term%'");
			
			if($r->num_rows == 1)
			{
				list($email) = $r->fetch_row();
				
				$r = $this->db->query("select * ".
					"from invoicing.aa_customers ".
					"where email = '".$this->db->escape_string($email)."'");
					
				if($r->num_rows)
					return $r->fetch_assoc();
			}
		}
		
		return false;
	}
	
	
	/**
	 * Takes customer data from Auction Anything and 
	 * converts it to be invoicing system compatible.
	 */
	function aa_to_invoicing($row)
	{
		return array(
			"name" => "$row[FirstName] $row[LastName]",
			"ship_attention_line" => $row['Company'],
			"ship_address_line1" => $row['Address'],
			"ship_city" => $row['City'],
			"ship_state" => $row['State'],
			"ship_zip" => $row['Zip'],
			"ship_country" => $row['Country'],
			"email" => $row['email'],
			"phone_number_1" => $row['PhoneH'],
			"phone_number_2" => $row['PhoneW'],
			"fax_number" => $row['Fax'],
		);
	}
	
	
	/**
	 * This function is for when they type something into the box and
	 * press enter without selecting anything from the drop-down.
	 * It is expected that they put in an entire customer_id,
	 * invoice number, item number, email address, or package id.
	 */
	function search($term)
	{
		$term = String::xtrim($term);
		
		try
		{
			if(ctype_digit($term))
			{
				/*
					If numeric, try invoice numbers, item numbers,
					package numbers, quote numbers.
				*/
				
				//invoice number
				if($customer_id = $this->call("by_customers_id", array($term)))
				{
					return array(
						"message" => "Found customers_id '$term'", 
						"result" => $customer_id
					);
				}
				
				//invoice number
				if($customer_id = $this->call("by_invoice", array($term)))
				{
					return array(
						"message" => "Found invoice number '$term'", 
						"result" => $customer_id
					);
				}
				
				//quote number
				if($customer_id = $this->call("by_quote_number", array($term)))
				{				
					return array(
						"message" => "Found quote number '$term'",
						"result" => $customer_id,
					);
				}
				
				if(strlen($term) >= 7)
				{
					//item number
					if($customer_id = $this->call("by_item_number", array($term)))
					{				
						return array(
							"message" => "Found item number '$term'",
							"result" => $customer_id,
						);
					}
				}
				
				if(strlen($term) >= 5)
				{
					//quote id
					if($customer_id = $this->call("by_package_id", array($term)))
					{
						return array(
							"message" => "Found package_id '$term'",
							"result" => $customer_id,
						);
					}
				}
			}
			else
			{
				if(stripos($term, "@") !== false)
				{
					//customer_id
					if($customer_id = $this->call("by_customer_id", array($term)))
					{
						return array(
							"message" => "Found customer_id '$term'",
							"result" => $customer_id,
						);
					}
						
					//email
					if($customer_id = $this->call("by_email", array($term)))
					{
						return array(
							"message" => "Found email '$term'",
							"result" => $customer_id,
						);
					}
				}
				else
				{
					//customer_id
					if($customer_id = $this->call("by_customer_id", array($term)))
					{
						return array(
							"message" => "Found customer_id '$term'",
							"result" => $customer_id,
						);
					}
					
					//exact consignor name
					if($customer_id = $this->call("by_exact_consignor_name", array($term)))
					{
						return array(
							"message" => "Found '$term'",
							"result" => $customer_id,
							"consignor" => true,
						);
					}
					
					//exact name
					if($customer_id = $this->call("by_exact_name", array($term)))
					{
						return array(
							"message" => "Found '$term'",
							"result" => $customer_id,
						);
					}
					
					//exact username
          if($customer_id = $this->call("by_exact_username", array($term)))
          {
            return array(
              "message" => "Found '$term'",
              "result" => $customer_id,
            );
          }
					
					//exact item name
					if($customer_id = $this->call("by_exact_item_name", array($term)))
					{
						return array(
							"message" => "Found item '$term'",
							"result" => $customer_id,
						);
					}
					
					//AKA
					if($customer_id = $this->call("by_aka", array($term)))
					{
						return array(
							"alert" => "I found \"AKA $term\" in this customer's notes. Make sure this is the customer you're looking for!",
							"message" => "Found AKA '$term'",
							"result" => $customer_id,
						);
					}
				}
			}
			
			if($row = $this->call("by_tracking_number", array($term)))
			{
				return array(
					"message" => "Found package '$term'",
					"result" => $row['customer_id'],
					"tracking_id" => $row['tracking_id'],
					"tracking_number" => $row['tracking_num'],
				);
			}
			
			return array(
				"message" => "I don't recognize '$term'.",
			);
		}
		catch(exception $e)
		{
			if($e->getCode() == 10001)
			{
				return array("message" => $e->getMessage(), "dupe" => true);
			}
			elseif($e->getCode() == 10000)
			{
				return array("message" => $e->getMessage());
			}
			else
			{
				throw $e;
			}
		}
	}
	
	function by_aka($name)
	{
		$r = $this->db->query("select customers.customer_id ".
			"from invoicing.customers ".
			"where notes_for_invoice regexp '(^|[^a-z0-9])aka(: *| +)([a-z]+ )?".$this->db->escape_string(preg_quote($name))."($|[^a-z0-9])'");
			
		if($r->num_rows == 1)
		{
			list($customer_id) = $r->fetch_row();
			return $customer_id;
		}
		elseif($r->num_rows > 1)
		{
			throw new exception("More than one \"AKA $name\". Please use the drop-down.", 10000);
		}
		
		return false;
	}
	
	function by_exact_item_name($item)
	{
		$r = $this->db->query("select customers.customer_id ".
			"from invoicing.sales ".
			"join invoicing.customers on (ebay_email = email) ".
			"where ebay_title = '".$this->db->escape_string($item)."'");
			//"where ebay_title = '".$this->db->escape_string($item)."' AND ebay_title LIKE '% %'");
		
		if($r->num_rows == 1)
		{
			list($customer_id) = $r->fetch_row();
			return $customer_id;
		}
		
		return false;
	}
	
	
	function by_exact_consignor_name($name)
	{
		$r = $this->db->query("select cust_id ".
			"from listing_system.tbl_consignorlist ".
			"where ConsignorName = '".$this->db->escape_string($name)."' ");
			
		if($r->num_rows == 1)
		{
			list($customer_id) = $r->fetch_row();
			return $customer_id;
		}
		elseif($r->num_rows > 0)
		{
			throw new exception("There is more than one customer named '$name'. Please use the drop-down.", 10001);
		}
		
		return false;
	}
	
	
	function by_exact_name($name)
	{
		$r = $this->db->query("select customers.customer_id ".
			"from invoicing.customers ".
			"left join invoicing.bill_to_address using(customer_id) ".
			"left join listing_system.tbl_consignorlist on (cust_id = customer_id) ".
			"where bill_to_address.`name` = '".$this->db->escape_string($name)."' or ".
			"customers.`name` = '".$this->db->escape_string($name)."' or real_name = '".$this->db->escape_string($name)."' or ConsignorName = '".$this->db->escape_string($name)."'".
			"group by customer_id");
			
		if($r->num_rows == 1)
		{
			list($customer_id) = $r->fetch_row();
			return $customer_id;
		}
		elseif($r->num_rows > 0)
		{
			throw new exception("There is more than one customer named '$name'. Please use the drop-down.", 10001);
		}
		
		return false;
	}
	
	function by_exact_username($name)
	{
		$r = $this->db->query("select customer_id, aa_customers.email ".
			"from invoicing.aa_customers ".
			"left join invoicing.customers using(email) ".
			"where `username` = '".$this->db->escape_string($name)."'  or ".
			"`ID` = '".$this->db->escape_string($name)."'  ".
			"group by customer_id");
			
		if($r->num_rows == 1)
		{
			list($customer_id, $email) = $r->fetch_row();
			
			if(empty($customer_id))
			{
				$r = $this->db->query("select customer_id ".
					"from invoicing.customers_emails ".
					"join invoicing.customers using(customers_id) ".
					"where customers_emails.email = '".$this->db->escape_string($email)."'");
					
				if($r->num_rows == 1)
				{
					list($customer_id) = $r->fetch_row();
					
					return $customer_id;
				}
				elseif($r->num_rows > 1)
				{
					throw new exception("There is more than one customer linked to the username '$name'. ".
						"Please use the drop-down.", 10001);
				}
				else
				{
					return false;
				}
				
			}
			
			return $customer_id;
		}
		elseif($r->num_rows > 0)
		{
			throw new exception("There is more than one customer named '$name'. Please use the drop-down.", 10001);
		}
		
		return false;
	}
	
	function by_customer_id($customer_id)
	{
		$r = $this->db->query("select customer_id from invoicing.customers ".
			"where customer_id = '".$this->db->escape_string($customer_id)."'");
			
		if($r->num_rows)
		{
			list($customer_id) = $r->fetch_row();
			return $customer_id;
		}
		
		return false;
	}
	
	function by_email($email)
	{
		$r = $this->db->query("select t1.customer_id ".
			"from invoicing.customers as t1 ".
			"left join customers_emails as t2 using (customers_id) ".
			"where t1.email like '".$this->db->escape_string($email)."%' or ".
			"t2.email like '".$this->db->escape_string($email)."%' ".
			"group by t1.customer_id");
	
		if($r->num_rows == 1)
		{
			list($customer_id) = $r->fetch_row();
			
			return $customer_id;
		}
		elseif($r->num_rows > 1)
		{
			throw new exception("There is more than one account with the email '$email'. Please use the drop-down.", 10001);
		}
		
		return false;
	}
	
	function by_customers_id($term)
	{
		$r = $this->db->query("select customer_id from invoicing.customers where customers_id = '$term'");
		
		if($r->num_rows)
		{
			list($customer_id) = $r->fetch_row();
			return $customer_id;
		}
		
		return false;
	}
	
	function by_invoice($term)
	{
		$r = $this->db->query("select customer_id from invoicing.invoices where invoice_number = '$term'");
		
		if($r->num_rows)
		{
			list($customer_id) = $r->fetch_row();
			return $customer_id;
		}
		
		return false;
	}
	
	
	
	function by_item_number($term)
	{
		$r = $this->db->query("select customers.customer_id from invoicing.sales ".
				"join invoicing.customers on ebay_email = email ".
				"where ebay_item_number = '$term'");
				
		if($r->num_rows)
		{
			list($customer_id) = $r->fetch_row();
			return $customer_id;
		}
		
		return false;
	}
	
	
	
	function by_package_id($term)
	{
		$r = $this->db->query("select distinct customer_id ".
			"from invoicing.quotes_packages ".
			"join invoicing.quotes using(quote_id) ".
			"join invoicing.customers on (customer_email = email) ".
			"where package_id = '$term'");
			
		if($r->num_rows == 1)
		{
			list($customer_id) = $r->fetch_row();
			return $customer_id;
		}
		
		return false;
		
	}
	
	
	
	function by_quote_number($term)
	{
		$r = $this->db->query("select distinct customer_id ".
			"from invoicing.quotes ".
			"join invoicing.customers on customer_email = email ".
			"where quote_id = '$term'");
			
		if($r->num_rows == 1)
		{
			list($customer_id) = $r->fetch_row();
			return $customer_id;
		}
		
		return false;
	}
	
	
	
	
	
	function by_text($term)
	{
		$escaped = $this->db->escape_string($term);
		
		$words = String::split_list($escaped, " ");
    
    $doSlowQuery = false;
		
    //print_r($words);
    
    if(count($words)==1 && preg_match('/[a-z]{3,4}[0-9]{1,3}/', $words[0])==1){
    
      $query = "select customers.customer_id, customers.name ".
        "from invoicing.customers ".
        "left join invoicing.aa_customers using(email) ".
        "left join listing_system.tbl_consignorlist on (cust_id = customers.customer_id) ".
        "left join invoicing.tbl_cc using(customer_id) ".
        "left join invoicing.bill_to_address using(customer_id) ".
        "left join invoicing.customers_addresses using(customer_id) ".
        "left join invoicing.customers_emails on (customers.customers_id = customers_emails.customers_id) ".
        "where ".
        "customers.customer_id like '$escaped%' or ".
        "notes_for_invoice regexp '(^|[^a-z0-9])aka(: *| +)".$this->db->escape_string(preg_quote($term))."($|[^a-z0-9])' ";
      
      $query .= "group by customer_id ";
      $query .= "order by customers.name like '%".implode(" ", $words)."%' desc, customers.name";
      
      $r = $this->db->query($query);
    
    }else{
      $doSlowQuery = true;
    }
    
    if($doSlowQuery || mysql_num_rows($r)==false){
    
		$query = "select customers.customer_id, customers.name ".
			"from invoicing.customers ".
			"left join invoicing.aa_customers using(email) ".
			"left join listing_system.tbl_consignorlist on (cust_id = customers.customer_id) ".
			"left join invoicing.tbl_cc using(customer_id) ".
			"left join invoicing.bill_to_address using(customer_id) ".
			"left join invoicing.customers_addresses using(customer_id) ".
			"left join invoicing.customers_emails on (customers.customers_id = customers_emails.customers_id) ".
			"where ".
			"customers.customer_id like '$escaped%' or ".
			"customers.email like '%$escaped%' or ".
			"customers_emails.email like '%$escaped%' or ".
			"username like '%$escaped%' or ".
			"ConsignorName like '$escaped%' or ".
			"customers.ship_city like '$escaped%' or ".
			"cc_name like '$escaped%' or ".
			"real_name like '$escaped%' or ".
			"username like '$escaped%' or ".
			"peachtree_id like '$escaped%' or ".
			"notes_for_invoice regexp '(^|[^a-z0-9])aka(: *| +)".$this->db->escape_string(preg_quote($term))."($|[^a-z0-9])' or ";
		
		$query .= "(customers.name like '%".implode("%' and customers.name like '%", $words)."%') or ";
		$query .= "(bill_to_address.name like '%".implode("%' and bill_to_address.name like '%", $words)."%') or ";
		$query .= "(customers_addresses.name like '%".implode("%' and customers_addresses.name like '%", $words)."%') or ";
		$query .= "(real_name like '%".implode("%' and real_name like '%", $words)."%') ";
		$query .= "group by customer_id ";
		$query .= "order by customers.name like '%".implode(" ", $words)."%' desc, customers.name";
		
		$r = $this->db->query($query);
		
		
    }
		/*
			I don't intend on selecting all the data to be displayed here,
			I intend to only select the customer id then select the data 
			elsewhere, to keep this function simple.
		*/
		$results = array();
		
		while(list($customer_id, $name) = $r->fetch_row())
		{
			$results[] = $customer_id;
		}
		
		return $results;
	}
	
	
	
	function by_name_approximate($term)
	{
		/*
			I will probably want to make this asynchronous since 
			it's so slow.
		*/
		$words = String::split_list(preg_replace("/[^a-z]/", "", $term), " ");
		
		$query = "select customer_id, name from invoicing.customers ".
			"where ".
			"name like '".substr($words[0], 0, 1)."%' and ".
			"listing_system.levenshtein_ratio(name, '".$this->db->escape_string($term)."') > 80 ";
		
		$r = $this->db->query($query);
		
		$results = array();
		
		while(list($customer_id, $name) = $r->fetch_row())
		{
			$results[] = $customer_id;
		}
		
		return $results;
	}
	
	
	
	function by_name($name)
	{
		/*
			For finding customers with similar names.
		*/
		$words = String::split_list($name, " ");
		$nicks = array_map(array($this->db, "escape_string"), $this->nicks(array_shift($words)));
		$last = $this->db->escape_string((count($words) ? end($words) : ""));
		
		$query = "select customer_id, name from invoicing.customers ".
			"left join invoicing.aa_customers using(email) ".
			"where ";

		foreach($nicks as $nick)
		{
			$query .= "(name like '$nick %' and name like '% $last') or ";
		}
		
		$query = substr($query, 0, -3);
		
		$query .= "order by name like '%".implode(" ", $words)."%' desc, name";
		echo "\n\n$query\n\nlala\n";
		$r = $this->db->query($query);
		
		/*
			I don't intend on selecting all the data to be displayed here,
			I intend to only select the customer id then select the data 
			elsewhere, to keep this function simple.
		*/
		$results = array();
		
		while(list($customer_id, $name) = $r->fetch_row())
		{
			$results[] = $customer_id;
			echo "$customer_id: $name\n";
		}
		
		return $results;
	}
	
	function by_paypal($string)
	{
		$r = $this->db->query("select customer_id ".
			"invoicing.paypal_notifications on (payer_email = customers_emails.email or payer_email = customers.email) ".
			"left join invoicing.customers_emails on (payer_email = customers_emails.email) ".
			"join invoicing.customers on (payer_email = customers.email or payer_email = customers_emails.email)".
			"where txn_id like '%".$this->db->escape_string($string)."'");
			
		$results = array();
		
		while(list($customer_id) = $r->fetch_row())
		{
			$results[] = $customer_id;
		}
		
		return $results;
	}
	
	function by_tracking_number($string)
	{
		$r = $this->db->query("select customer_id, ID as tracking_id, tracking_num ".
			"from invoicing.tbl_tracking_numbers ".
			"join invoices on (invoice_num = invoice_number) ".
			"where tracking_num = '".$this->db->escape_string($string)."' ".
			"order by ship_date desc limit 1");
			
		if($r->num_rows)
			return $r->fetch_assoc();
			
		return false;
	}
}



class CustomerWrite
{
	/*
		TODO: Add blab debugging
		TODO: Switch to numeric customer id in the future
		TODO: Insert/Update/Delete
		TODO: Convert formatted address to fields, for pasting in new customers.
	*/
	
	function __construct($db, $blab)
	{
		require_once("/webroot/shipping_quotes/format_address.inc.php");
		require_once("address.inc.php");
		
		$this->db = $db;
		$this->blab = $blab;
	}
	
	
	
	function call($method, $args)
	{
		$time = microtime(true);
		
		$ret = call_user_func_array(array($this, $method), $args);
		
		$this->last_interval = microtime(true) - $time;
		
		$this->blab->blab("Method '$method' took ".$this->last_interval." seconds to complete.");
		
		return $ret;
	}
	
	
	function customers_website($data, $wdb)
	{
		if(!empty($data['email']))
		{
			$data['email_address'] = $data['email'];
			unset($data['email']);
		}
		
		$query = assemble_insert_query3($data, "members.customers", $wdb, true);
		
		$wdb->query($query);
		
		$this->blab->blab($query);
		
		return true;
	}
	
	
	function consignor($data)
	{
		if(preg_match("/^use \"([^\"]+)\"$/i", trim($data['SameAs']), $matches))
		{
			$r = $this->db->query("select cust_id from listing_system.tbl_consignorlist ".
				"where ConsignorName = '$matches[1]'");
				
			if($r->num_rows == 0)
			{
				throw new exception("Invalid ConsignorName in SameAs field '$matches[1]'", 10000);
			}
			else
			{
				list($cust_id) = $r->fetch_row();
				
				if($cust_id != $data['cust_id'])
				{
					throw new exception("You can only create a 'use' record that points to ".
						"this customer. The one you typed in points to $cust_id", 10000);
				}
			}
			
			unset($data['cust_id']);
		}
		elseif(isset($data['CommissionRate']))
		{
			$dir = scandir2("/webroot/accounting/backend/commission_rates");
			if(!in_array($data['CommissionRate'].".txt", $dir))
			{
				throw new exception("Invalid commission rate given: '".$data['CommissionRate']."'", 10000);
			}
		}
		
		$query = assemble_insert_query3($data, "listing_system.tbl_consignorlist", $this->db, true);
		
		$this->db->query($query);
		
		$this->blab->blab($query);
		
		return true;
	}
	
	
	function customers($data)
	{
		/*
			TODO: Validation and logging.
		*/
		
		foreach(array("preferred_package_value", "pay_and_hold_days") as $field)
		{
			if(isset($data[$field]) && $data[$field] === "")
			  	$data[$field] = null;
		}
		
		if(empty($data['customers_id']) && empty($data['customer_id']))
			throw new exception("Empty customer id");
		
		$query = assemble_insert_query3($data, "invoicing.customers", $this->db, true);
		
		$this->db->query($query);
		
		
		
		$this->blab->blab($query);
		
		if(empty($data['customers_id']))
		{
			
			if(false == $insert_id = $this->db->insert_id)
			{
				if(!empty($data['customer_id']))
				{
					$r = $this->db->query("select customers_id ".
						"from invoicing.customers ".
						"where customer_id = '".$this->db->escape_string($data['customer_id'])."'");
						
					list($customers_id) = $r->fetch_row();
					
					return $customers_id;
				}
				
				throw new exception("\$data[customer_id] not set");
			}
			else
			{
				return $insert_id;
			}
		}
		
		/*
			Parse other_emails, insert into customers_emails
		*/
		/*if(isset($data['other_emails']))
		{
			$r = $this->db->query("select email, other_emails, customers_id from invoicing.customers ".
				"where customer_id = '".$this->db->escape_string($data['customer_id'])."'");
			
			$row = $r->fetch_assoc();
			
			$emails = Customer::parse_other_emails($row['other_emails']);
			$emails[] = $row['email'];
			
			foreach($emails as $email)
	        {
				$this->db->query("insert into invoicing.customers_emails (customers_id, email) ".
					"values ('".$this->db->escape_string($row['customers_id'])."', '".$this->db->escape_string($email)."') ".
					"on duplicate key update customers_id = customers_id");
	        }
		}*/
		
		return $data['customers_id'];
	}
	
	
	function autoship($data, $customers_id = null)
	{
		if($customers_id > 0 && empty($data['customers_id']))
			$data['customers_id'] = $customers_id;

			
		if(empty($data['customers_id']) && empty($data['customer_id']))
			throw new exception("Empty customer id");
		
		if(!isset($data['autoship']))
			throw new exception("No autoship info.");
		
		if($data['autoship'] === "0")
		{
			//Delete autoship record
			
			$this->db->query("delete from invoicing.autoship ".
				"where customer_id = '".$this->db->escape_string($data['customer_id'])."'");
		}
		else
		{
			//Insert or update autoship record
			unset($data['autoship']);
		
			$data['date_added'] = date("Y-m-d", strtotime($data['date_added']));
			
			$query = assemble_insert_query3($data, "invoicing.autoship", $this->db, true);
			
			$this->db->query($query);
		
			$this->blab->blab($query);
		}
		
		return true;
	}
	
	
	function update_notes_for_invoice($customers_id, $notes_for_invoice)
	{
		$this->db->query("update invoicing.customers ".
			"set notes_for_invoice = '".$this->db->escape_string($notes_for_invoice)."' ".
			"where customers_id = '".$this->db->escape_string($customers_id)."'");
	}
	
	function get_primary_address($customers_id)
	{
		$r = $this->db->query("select * from invoicing.customers ".
			"where customers_id = '$customers_id'");
		
		return $r->fetch_assoc();
	}
	
	
	function get_primary_address2($customer_id)
	{
		$r = $this->db->query("select * from invoicing.customers ".
			"where customer_id = '$customer_id'");
		
		return $r->fetch_assoc();
	}
	
	
	function get_secondary_address($address_id)
	{
		$r = $this->db->query("select * from invoicing.customers_addresses ".
			"where address_id = '$address_id'");
			
		return $r->fetch_assoc();
	}
	
	
	function get_billing_address($customers_id)
	{
		$r = $this->db->query("select t1.*, coalesce(t1.name, t2.name) as name, ".
			"null as phone_number_1, null as phone_number_2, null as fax_number ".
			"from invoicing.bill_to_address t1 ".
			"join invoicing.customers as t2 using(customer_id) ".
			"where t2.customers_id = '$customers_id'");
		
		return Address::convert_bill_to($r->fetch_assoc());
	}
	
	
	
	function get_billing_address_legacy($customer_id)
	{
		$result = $this->db->query(sprintf(
			"select t1.customer_id, bill_attention_line, bill_address_line1, ".
			"bill_address_line2, bill_address_line3, bill_city, bill_state, ".
			"bill_zip, bill_country, ".
			"t1.name as name, t2.name as ship_name, ".
			"null as phone_number_1, null as phone_number_2, null as fax_number ".
			"from invoicing.bill_to_address as t1 ".
			"join invoicing.customers as t2 using(customer_id) ".
			"where t1.customer_id = '%s'", $this->db->escape_string($customer_id)));
		
		if($result->num_rows == 1)
		{
			$bill_to_address = array_map("trim", $result->fetch_assoc());
			
			foreach(array("name", "bill_attention_line", "bill_address_line1", "bill_address_line2", 
				"bill_address_line3", "bill_city", "bill_state", "bill_zip", "bill_country") as $field)
			{			
				if(trim($bill_to_address[$field]) != "")
				{
					$broke = true;
					break;
				}
			}
			
			if(empty($broke))
				unset($bill_to_address);
			elseif(empty($bill_to_address['name']))
				$bill_to_address['name'] = $bill_to_address['ship_name'];
		}
		
		if(empty($bill_to_address))
		{
			$r = $this->db->query("select * from invoicing.customers ".
				"where customer_id = '$customer_id'");
		
			return $r->fetch_assoc();
		}
		
		return Address::convert_bill_to($bill_to_address);
	}
	
	
	
	function get_address_id($raw)
	{
		$hash = md5(implode("\n", array($raw['name'], $raw['phone_number_1'], $raw['phone_number_2'], $raw['fax_number'], 
			$raw['ship_attention_line'], $raw['ship_address_line1'], $raw['ship_address_line2'], $raw['ship_city'], 
			$raw['ship_state'], $raw['ship_zip'], $raw['ship_country'])), true);
			
		$r = $this->db->query("select invoices_addresses_id ".
			"from invoicing.invoices_addresses ".
			"where hash = '".$this->db->escape_string($hash)."'");
			
		if($r->num_rows == 1)
		{
			list($address_id) = $r->fetch_row();
			
			return $address_id;
		}
		elseif($r->num_rows > 1)
		{
			throw new exception ("Hash collision? ".print_r($raw, true));
		}
		else
		{
			$row = array(
				"name" => $raw['name'],
				"phone_number_1" => $raw['phone_number_1'],
				"phone_number_2" => $raw['phone_number_2'],
				"fax_number" => $raw['fax_number'],
				"ship_attention_line" => $raw['ship_attention_line'],
				"ship_address_line1" => $raw['ship_address_line1'],
				"ship_address_line2" => $raw['ship_address_line2'],
				"ship_city" => $raw['ship_city'],
				"ship_state" => $raw['ship_state'],
				"ship_zip" => $raw['ship_zip'],
				"ship_country" => $raw['ship_country'],
				"hash" => $hash,
				"raw" => serialize($raw),
			);
			
			$row = array_map(array("self", "null2blank"), $row);
			
			$this->db->query(assemble_insert_query3($row, "invoicing.invoices_addresses", $this->db, false));
			
			return $this->db->insert_id;
		}
	}
	
	
	
	function null2blank($value)
	{
		if(is_null($value))
			return "";
		return $value;
	}
	
	
	
	
	function customers_addresses($data)
	{
		/*
			TODO: this
		*/
		
		if(empty($data['customers_id']) && empty($data['customer_id']))
			throw new exception("Empty customer id");
		
		$query = assemble_insert_query3($data, "invoicing.customers_addresses", $this->db, true);
		
		$this->db->query($query);
		
		$this->blab->blab($query);
		
		return true;
	}
	
	
	
	
	function other_address($data)
	{
		if(empty($data['address_id']))
			throw new exception("Empty address id");
		
		$query = assemble_insert_query3($data, "invoicing.customers_addresses", $this->db, true);
		
		$this->db->query($query);
		
		$this->blab->blab($query);
		
		return true;
	}
	
	
	
	function delete_other_address($address_id)
	{
		$r = $this->db->query("select * from customers_addresses where address_id = '$address_id'");
		
		$row = $r->fetch_assoc();
		
		Event::log(array(
			"event_type" => "invoicing-customer-delete-other-address",
			"customer_id" => $row['customer_id'],
			"address_id" => $address_id,
			"record" => $row,
			"user_id" => $_REQUEST['user_id'],
		));
		
		$this->db->query("delete from invoicing.customers_addresses ".
			"where address_id = '$address_id'");
		
		return $this->db->affected_rows;
	}
	
	
	
	function bill_to_address($data)
	{
		/*
			TODO: Validation and logging.
		*/
		$query = assemble_insert_query3($data, "invoicing.bill_to_address", $this->db, true);
			
		$this->db->query($query);
		
		$this->blab->blab($query);
		
		return true;
	}
	
	
	
	function update_card($data)
	{
		$cc_id = $data['cc_id'];
		unset($data['cc_id']);
		
		if(empty($data['cc_order_to_use']))
			$data['cc_order_to_use'] = null;
		
		$query = assemble_update_query("invoicing.tbl_cc", $data, "cc_id = '".$this->db->escape_string($cc_id)."'", $this->db);
		
		$this->db->query($query);
		
		$this->blab->blab($query);
		
		return true;
	}
	
	
	
	function new_card($data)
	{
		/*
			TODO: Validation and logging.
		*/
		$data['date_removed'] = null;
		
		$query = assemble_insert_query3($data, "invoicing.tbl_cc", $this->db, true);
			
		$this->db->query($query);
		
		$this->blab->blab($query);
		
		return true;
	}
	
	
	function delete_card($cc_id)
	{
		$this->db->query("delete from invoicing.tbl_cc ".
			"where cc_id = '".$this->db->escape_string($cc_id)."'");
		
		return true;
	}
	
	//TODO: The functions from Bidder Account Admin that updates auction anything stuff.
	
	function extend_flat_rate_shipping($users_id, $who_did, $days, $wdb)
	{
		$r = $wdb->query("select id, email ".
			"from members.users where account_number = '$users_id'");
		
		list($users_id, $email) = $r->fetch_row();
		
		$wdb->query(assemble_insert_query3(array(
			"users_id" => $users_id,
			"ceu_date_added" => date("Y-m-d H:i:s"),
			"ceu_date_expires" => date("Y-m-d H:i:s", strtotime("23:59:59 +".$days." days")),
			"ceu_authorized_by" => $who_did,
			"ceu_active" => 1,
		), "members.checkout_exceptions_user", $wdb));
		
		$wdb->query("update members.checkout, members.items_winners ".
			"set quote_requested = null ".
			"where checkout.item_id = items_winners.item_id and ".
			"paid is null and quote_requested is not null and ".
			"users_email = '".$wdb->escape_string($email)."'");
		
		return true;
	}
	

	function cancel_quote_request($users_id, $wdb)
	{
		$r = $wdb->query("select id, userid, email ".
			"from members.users where account_number = '$users_id'");
		
		list($users_id, $userid, $email) = $r->fetch_row();
		
		$wdb->query("update members.checkout, members.items_winners ".
			"set quote_requested = null ".
			"where checkout.item_id = items_winners.item_id and bonus_item = '0' and ".
			"(paid is null or package_id is null) and quote_requested is not null and ".
			"users_AAID = '".$wdb->escape_string($userid)."'");
		
		$affected_rows = $wdb->affected_rows;
		
		$wdb->query("update members.checkout, members.items_bin ".
			"set quote_requested = null ".
			"where checkout.item_id = items_bin.item_id and bonus_item = '2' and ".
			"(paid is null or package_id is null) and quote_requested is not null and ".
			"users_AAID = '".$wdb->escape_string($userid)."'");
		
		$affected_rows += $wdb->affected_rows;
		
		Event::log(array(
			"event_type" => "invoicing-customer-cancel-quote-request",
			"email" => $email,
			"user_id" => $_REQUEST['user_id'],
		));
		
		return $affected_rows;
	}
	
	
	function add_cc_refund($customers_id, $invoice_number, $amount, $who_did, $cc_id, $reason = null)
	{
		$this->db->query(assemble_insert_query3(array(
			"invoice_number" => $invoice_number,
			"timestamp" => date("Y-m-d H:i:s"),
			"status" => "success",
			"amount_charged" => 0-abs($amount),
			"user" => $who_did,
			"reason" => $reason,
			"cc_id" => $cc_id,
			"customers_id" => $customers_id,
		), "invoicing.cc_log", $this->db));
		
		return $this->db->insert_id;
	}
	
	/**
	 * Switches the "primary" address for the extra address,
	 * specified by $address_id.
	*/
	function set_primary_address($customer_id, $address_id, $name = "Old Primary")
	{
		$r = $this->db->query("select * from invoicing.customers ".
			"where customer_id = '".$this->db->escape_string($customer_id)."'");
			
		if($r->num_rows == 0)
			throw new exception("No such customer");
			
		$customer = $r->fetch_assoc();
		
		$r = $this->db->query("select * from invoicing.customers_addresses ".
			"where address_id = '$address_id'");
			
		if($r->num_rows == 0)
			throw new exception("No such address");
			
		$address = $r->fetch_assoc();
		
		$r = $this->db->query("delete from invoicing.customers_addresses where address_id = '$address_id'");
		
		$notes = $customer['notes_for_invoice'];
		
		$junk = array_filter(array(
			"address_notes" => $address['address_notes'],
			"peachtree_id"	=> $address['peachtree_id'],
			"peachtree_email" => $address['peachtree_email'],
		), "strlen");
		
		if(!empty($junk))
		{
			$notes .= print_r($junk, true);
		}
		
		$this->db->query(assemble_update_query(
			"invoicing.customers", 
			array(
				"name" 				=> $address['name'],
				"phone_number_1" 	=> $address['phone_number_1'],
				"phone_number_2" 	=> $address['phone_number_2'],
				"fax_number" 		=> $address['fax_number'],
				"ship_attention_line" => $address['ship_attention_line'],
				"ship_address_line1" => $address['ship_address_line1'],
				"ship_address_line2" => $address['ship_address_line2'],
				"ship_city"			=> $address['ship_city'],
				"ship_state"		=> $address['ship_state'],
				"ship_country"		=> $address['ship_country'],		
				"account_number"	=> $address['account_number'],
				"notes_for_invoice"	=> $notes,
			),
			"customer_id = '".$this->db->escape_string($customer_id)."'",
			$this->db));
			
			
		$this->db->query(assemble_insert_query3(
			array(
				"customer_id"		=> $customer['customer_id'],
				"name" 				=> $customer['name'],
				"phone_number_1" 	=> $customer['phone_number_1'],
				"phone_number_2" 	=> $customer['phone_number_2'],
				"fax_number" 		=> $customer['fax_number'],
				"ship_attention_line" => $customer['ship_attention_line'],
				"ship_address_line1" => $customer['ship_address_line1'],
				"ship_address_line2" => $customer['ship_address_line2'],
				"ship_city"			=> $customer['ship_city'],
				"ship_state"		=> $customer['ship_state'],
				"ship_country"		=> $customer['ship_country'],
				"account_number"	=> $customer['account_number'],
				"address_label"		=> $name,
			),
			"invoicing.customers_addresses",
			$this->db,
			true
		));
		
		Event::log(array(
			"event_type" => "invoicing-customer-set-primary-address",
			"user_id" => $_REQUEST['user_id'],
			"address_id" => $address_id,
			"customer_id" => $customer_id,
		));
	}
	
	
	function update_other_emails($other_emails, $customers_id = null)
	{
		$ids = array();
		
		if($other_emails === "")
		{
			$this->db->query("delete from invoicing.customers_emails ".
				"where customers_id = '$customers_id'");
		}
		else
		{
			foreach($other_emails as $row)
			{
				$row['email'] = trim($row['email']);
				
				if(!preg_match("/^[^@]+@[^@]+\.[^@]+$/i", $row['email']))
				{
					throw new exception("Invalid email in other_emails '$row[email]'.", 10040);
				}
				
				if(empty($row['customers_id']) && $customers_id > 0)
				{
					$row['customers_id'] = $customers_id;
				}
				
				$customers_id = $row['customers_id'];
				
				$this->db->query(assemble_insert_query3($row, "invoicing.customers_emails", $this->db, true));
				
				if(empty($row['customers_emails_id']))
				{
					$ids[] = $this->db->insert_id;
				}
				else
				{
					$ids[] = $row['customers_emails_id'];
				}
			}
			
			$this->db->query("delete from invoicing.customers_emails ".
				"where customers_id = '$customers_id' and ".
				"customers_emails_id not in (".implode(",", $ids).")");
		}
			
		return true;
	}
}






class CustomerMove
{
	function __construct($db, $wdb, $blab)
	{
		require_once("/webroot/shipping_quotes/format_address.inc.php");
		require_once("address.inc.php");
		
		$this->db = $db;
		$this->wdb = $wdb;
		$this->blab = $blab;
	}
	
	
	
	function delete_customer($customer_id)
	{
		Event::log(array(
			"event_type" => "invoicing-customer-delete",
			"customer_id" => $customer_id,
			"user_id" => $_REQUEST['user_id'],
		));
		
		$r = $this->db->query("select 1 from invoicing.customers ".
			"where notes_for_invoice like '%do not delete%' and ".
			"customer_id = '".$this->db->escape_string($customer_id)."'");
		
		if($r->num_rows)
			throw new exception("'do not delete' is in this customer's notes");
		
		$this->db->query("delete from invoicing.bill_to_address ".
			"where customer_id = '".$this->db->escape_string($customer_id)."'");
		
		$this->db->query("delete from invoicing.customers ".
			"where customer_id = '".$this->db->escape_string($customer_id)."'");
			
		return true;
	}
	
	
	
	function move_customer_email($from_email, $to_email, $add_to_other_emails = true)
	{
		//TODO: Choose which steps to do?
		$r = $this->db->query("select other_emails, email, customers_id, customer_id ".
			"from invoicing.customers ".
			"where email = '".$this->db->escape_string($from_email)."'");
		
		if($r->num_rows == 0)
			throw new exception("No account with email '$from_email'", 10000);
		
		$row = $r->fetch_assoc();
		
		Event::log(array(
			"event_type" => "invoicing-customer-email-move",
			"from_email" => $from_email,
			"to_email" => $to_email,
			"user_id" => $_REQUEST['user_id'],
			"customer_id" => $row['customer_id'],
		));
		
		if($add_to_other_emails)
		{
			$this->db->query("insert into invoicing.customers_emails ".
				"set customers_id = '$row[customers_id]', ".
				"email = '".$this->db->escape_string($from_email)."' ".
				"on duplicate key update email = email");
		}

		$this->db->query("update invoicing.customers ".
			"set email = '".$this->db->escape_string($to_email)."' ".
			"where email = '".$this->db->escape_string($from_email)."'");
		
		$this->move_consignorlist($from_email, $to_email, $row['customer_id']);
		
		$this->move_consignments_email($from_email, $to_email);
		
		$this->move_customer_email2($from_email, $to_email);
		
		$this->move_customer_images_list($from_email, $to_email);
		
		require_once("/webroot/invoicing/backend/email.inc.php");
	  	$mail = new MailIndex();
		$mail->update_subscriptions();
		
		return true;
	}
	
	
	//Test account number: 327896
	function move_aa_email($account_number, $from_email, $to_email)
	{
		require_once("/webroot/includes/auction_anything/auction_anything.inc.php");
				
		$aa = new AA(null, null); //Arguments not required for AA::set_customer_email

		/*
		 * Update AuctionAnything
		 */
		$aa->set_customer_email($account_number, $to_email);	
		
		
		/*
		 * Update website.members.users
		 */			
		$this->wdb->query("UPDATE members.users SET ".
			"email='".$this->wdb->escape_string($to_email)."' ".
			" WHERE users.account_number='$account_number' ");
	 	
		/*
		 * Update website.members.user_options
		 */
		$this->wdb->query("update members.users, members.user_options ".
			"set wantlist_emailaddress = '".$this->wdb->escape_string($to_email)."' ".
			"where user_options.users_id = users.id and ".
			"wantlist_emailaddress = '".$this->wdb->escape_string($from_email)."' and ".
			"users.account_number = '$account_number' ");
	 	
		/*
		 * Update website.members.customers
		 */
		$this->wdb->query("UPDATE members.customers SET ".
			"notes_for_invoice = CONCAT(coalesce(notes_for_invoice, ''), ' Changed Email ".date('m/d/Y h:i:s A')."'), ".
			"last_changed = NOW() ".
			"WHERE customers.email_address = '".$this->wdb->escape_string($from_email)."' ");
		
		
		/*
		 * Update local.invoicing.aa_customers
		 */
		$this->db->query("UPDATE invoicing.aa_customers SET ".
				"email = '".$this->db->escape_string($to_email)."' ".
				"WHERE email = '".$this->db->escape_string($from_email)."' ");		
		
		return true;
	}
	
	
	function move_customer_email2($from_email, $to_email)
	{
		$r = $this->wdb->query("select * from members.users ".
			"where email = '".$this->db->escape_string($from_email)."'");
		
		if($r->num_rows)
		{
			$row = $r->fetch_assoc();
			
			/*
				If the new email address already exists in an account,
				fail if any account is active, and if the account(s) are inactive, 
				change those to the old address.
			*/
			$r2 = $this->wdb->query("SELECT * FROM members.users ".
				"WHERE email = '".$this->wdb->escape_string($to_email)."'");
			
			$inactive_accounts = Array();
			
			if($r2->num_rows > 0)
			{
				while($row2 = $r2->fetch_assoc())
				{
					if($row2['blocked'] == 0)
					{
						throw new exception("There was another ".
								"*active* registered user with the email address ".
								"'$to_email' (username '$row2[username]').\n".
								"I don't know how to handle this situation, ".
								"so I won't change anything, and I'll let you ".
								"figure this out. Sorry!", 10000);
					}
					else
						$inactive_accounts[] = $row2;
				}
			}
			else
			{
				/*
					If the account does not exist, we still need to deal with
					old email block record in blocked bidders
				*/
				$r6 = $this->db->query("SELECT ".
					"(SELECT COUNT(*) FROM invoicing.tbl_blocked_bidders WHERE ".
					"ebay_id = '".$this->db->escape_string($to_email)."'), ".
					"(SELECT COUNT(*) FROM invoicing.tbl_blocked_bidders WHERE ".
					"ebay_id = '".$this->db->escape_string($from_email)."')");
				
				list($new, $old) = $r6->fetch_row();
				
				if($new > 0 && $old > 0)
				{
					throw new exception("There is a block record for ".
						"'$from_email' that you must deal with manually ".
						"before you can perform this operation.\n".
						"Please search the blocked bidders table for '$to_email' and do ".
						"something with that record.", 10000);
				}
			}
		
			/*
		 		Change inactive accounts to a temp value
			*/
			if(count($inactive_accounts) > 0)
			{
				$tempnames = Array();
				
				foreach($inactive_accounts as $ia)
				{
					/*
						Select a temporary name that does not conflict
					*/
					$tempname = "temp_" . $ia['email'];

					$r3 = $this->wdb->query("SELECT 1 FROM members.users ".
						"WHERE email = '".$this->wdb->escape_string($tempname)."'");
					
					//Find a tempname that is unused
					while($r3->num_rows != 0)
					{
						$tempname = "temp_" . $tempname;
						
						$r3 = $this->wdb->query("SELECT 1 FROM members.users ".
							"WHERE email = '".$this->wdb->escape_string($tempname)."'");
						
						$iterations++;
						if($iterations > 9)
							throw new Exception("ERROR: Infinite loop?", 5000);
					}
					
					
					// Store temporary email address for later
					$tempnames[] = $tempname;
					
					
					/*
						Change this account to the temporary name
					*/
					$this->move_aa_email($ia['account_number'], $ia['email'], $tempname);
				}
			}
			
			
			/*
				Change the account to new email address
			*/				
			$this->move_aa_email($row['account_number'], $from_email, $to_email);
			
			
			/*
				Change inactive accounts from temporary placeholder
				to old email address
			*/
			if(count($inactive_accounts) > 0)
			{
				foreach($inactive_accounts as $k => $ia)
				{
					$tempname = $tempnames[$k];
					
					$this->move_aa_email($ia['account_number'], $tempname, $from_email);
				}
			}
			
			/*
				Work on the tbl_blocked_bidders records, if safe
			*/
			$this->db->query("begin");
			
			if($this->db->query("select 1 from invoicing.tbl_blocked_bidders ".
				"where ebay_id = '$to_email' or email = '$to_email'")->num_rows == 0)
			{
				//This part is easy. The real block record gets its email changed.
				$this->db->query("update invoicing.tbl_blocked_bidders set AA_id = '$row[userid]', email = '$to_email' ".
					"where ebay_id = '".$this->db->escape_string($row['username'])."'");
				
				if($this->db->query("select 1 from invoicing.tbl_blocked_bidders ".
					"where email = '$from_email' and ebay_id = '$from_email'")->num_rows == 1)
				{
					$this->db->query("update invoicing.tbl_blocked_bidders set AA_id = '$row[userid]' ".
						"where email = '$from_email' and ebay_id = '$from_email'");
					
					//Get the contents of the email block record.
					$r = $this->db->query("select * from invoicing.tbl_blocked_bidders ".
						"where email = '$from_email' and ebay_id = '$from_email'");
					
					$eblock = $r->fetch_assoc();
					
					if($eblock['how_blocked'] != "Delivery Failure")
					{
						//The NEW email inherits the settings from the old email block record.
						$this->db->query("update invoicing.tbl_blocked_bidders set ebay_id = '$to_email', email = '$to_email' ".
							"where ebay_id = '".$this->db->escape_string($eblock['ebay_id'])."'");
						
						
						//The OLD email gets changed to "Wants No Email".
						$query = "insert into invoicing.tbl_blocked_bidders set ";
						foreach($eblock as $k => $v)
						{
							if($k == "autoid")
								continue;
							
							if($k == "ebay_id" or $k == "email")
								$v = $from_email;
								
							if($k == "how_blocked")
								$v = "Wants No Email";
							
							if(is_null($v))
								$query .= "`".$this->db->escape_string($k)."` = null, ";
							else
								$query .= "`".$this->db->escape_string($k)."` = '".$this->db->escape_string($v)."', ";
						}
						$query = substr($query, 0, -2);
						
						$this->db->query($query);
					}
				}
				elseif($this->db->query("select 1 from invoicing.tbl_blocked_bidders ".
					"where email = '$from_email' and ebay_id = '$from_email'")->num_rows == 0)
				{
					//Do nothing for now.
				}
				else
				{
					/*
					echo "<p><big style='color: tomato;'>READ: This is not an error; There is more you need to do.".
						"I changed the real block record for you, but I can't handle the email block records ".
						"for you because of one of the following reasons: ".
						"<ol><li>There is no identifiable \"email block\" record.</li> ".
						"<li>There is more than one record identified as \"email block\".</li>".
						"<li>There is one email block record, but its ID and email fields have different email addresses.</li></ol> </big></p> ".$reason;
					*/
					throw new exception("Please handle the blocked bidder records manually.", 10000);
				}
			}
			else
			{
				/*
				echo "<p><big style='color: tomato;'>READ: This is not an error; There is more you need to do.".
					"I can't handle the blocked bidder records for you ".
					"because there are already records using the new email, and I don't know how ".
					"to handle that situation.</big></p>".$reason;
				*/
				throw new exception("Please handle the blocked bidder records manually.", 10000);
			}
			
			$this->db->query("commit");
		}
		
		return true;
	}
	
	
	function get_emails($from_id, $to_id)
	{
		$r = $this->db->query("select email from invoicing.customers where customer_id = '".$this->db->escape_string($from_id)."'");
		
		if($r->num_rows == 0)
			throw new exception("No account with customer_id '$from_id'", 10000);
		
		list($from_email) = $r->fetch_row();
		
		$r = $this->db->query("select email from invoicing.customers where customer_id = '".$this->db->escape_string($to_id)."'");
		
		if($r->num_rows == 0)
			throw new exception("No account with customer_id '$to_id'", 10000);
			
		list($to_email) = $r->fetch_row();
		
		return array($from_email, $to_email);
	}
	
	
	function get_ids($from_email, $to_email)
	{
		$r = $this->db->query("select customer_id from invoicing.customers where email = '".$this->db->escape_string($from_email)."'");
		
		if($r->num_rows == 0)
			throw new exception("No account with email address '$from_email'", 10000);
		
		list($from_id) = $r->fetch_row();
		
		$r = $this->db->query("select customer_id from invoicing.customers where email = '".$this->db->escape_string($to_email)."'");
		
		if($r->num_rows == 0)
			throw new exception("No account with email address '$from_email'", 10000);
			
		list($to_id) = $r->fetch_row();
		
		return array($from_id, $to_id);
	}
	
	function move_returns($from_id, $to_id)
	{
		$r = $this->db->query("select customers_id from invoicing.customers ".
			"where customer_id = '".$this->db->escape_string($to_id)."'")        ;
		list($customers_id) = $r->fetch_row();
		
		$this->db->query("update listing_system.returns ".
			"set customer_id = '".$this->db->escape_string($to_id)."', ".
			"customers_id = '$customers_id' ".
			"where customer_id = '".$this->db->escape_string($from_id)."'");
			
		return $this->db->affected_rows;
	}
	
	function change_email_of_items($from_email, $to_email, $from_id = null, $to_id = null)
	{		
		if(is_null($from_id) || is_null($to_id))
		{
			list($from_id, $to_id) = $this->get_ids($from_email, $to_email);
		}
		
		Event::log(array(
			"event_type" => "invoicing-customer-change-email-of-items",
			"from_customer_id" => $from_id,
			"to_customer_id" => $to_id,
			"user_id" => $_REQUEST['user_id'],
		));
		
		$methods = array(
			"move_sales" => array($from_email, $to_email), 
			"move_fixed_price_sales" => array($from_email, $to_email), 
			"move_consignments_email" => array($from_email, $to_email), 
			"move_quotes" => array($from_email, $to_email), 
			"move_consignorlist" => array($from_email, $to_email, $to_id),
			"move_autoship" => array($from_email, $to_email, $to_id),
			"move_sales_customer_id" => array($from_id, $to_id),
			"move_invoices" => array($from_id, $to_id),
			"move_cards" => array($from_id, $to_id),
			"move_experts" => array($from_email, $to_email),
			"move_returns" => array($from_id, $to_id),
			"move_old_account" => array($from_id, $to_id),
			"move_customer_images_list" => array($from_email, $to_email),
			"move_other_addresses" => array($from_id, $to_id),
			"move_checks" => array($from_id, $to_id),
			"move_block_records" => array($from_id, $to_id)
		);
		
		foreach($methods as $method => $args)
		{
			try
			{
				$methods[$method]["message"] = call_user_func_array(array($this, $method), $args);
				
				if($methods[$method]["message"] == 0)
					$methods[$method]["message"] = "n/a";
				elseif($methods[$method]["message"] === true)
					$methods[$method]["message"] = "row(s) affected";
				elseif($methods[$method]["message"] == 1)
					$methods[$method]["message"] .= " row affected";
				else
					$methods[$method]["message"] .= " rows affected";
			}
			catch(exception $e)
			{
				$methods[$method]["message"] = "Error executing method '$method': ".$e->__toString();
				$methods[$method]["error"] = 1;
			}
		}
			
		return array($from_id, $to_id, $methods);
	}
	
	
	function move_block_records($from_email, $to_email)
	{
		$r = $this->db->query("select `ID` from invoicing.aa_customers ".
			"where email = '".$this->db->escape_string($to_email)."'");
		
		if($r->num_rows != 1)
			return 0;
		
		list($aa_id) = $r->fetch_row();
		
		$this->db->query("update invoicing.tbl_blocked_bidders ".
			"set AA_id = '".$this->db->escape_string($aa_id)."' ".
			"where email = '".$this->db->escape_string($from_email)."'");
			
		return $this->db->affected_rows;
	}
	
	
	function move_old_account($from_id, $to_id)
	{
		$r = $this->db->query("select * from invoicing.customers where customer_id = '".$this->db->escape_string($from_id)."'");
		
		$customer = $r->fetch_assoc();
		
		$r = $this->db->query("select customer_since, customers_id ".
			"from invoicing.customers ".
			"where customer_id = '".$this->db->escape_string($to_id)."'");
		
		list($customer_since, $customers_id) = $r->fetch_row();
		
		//If the "from" account has an older customer_since, use it.
		if(!empty($customer['customer_since']) && strtotime($customer['customer_since']) < strtotime($customer_since))
		{
			$this->db->query("update invoicing.customers ".
				"set customer_since = '".date("Y-m-d H:i:s", strtotime($customer['customer_since']))."' ".
				"where customer_id = '".$this->db->escape_string($to_id)."'");
		}

		
		
		//Merge other_emails
		$r = $this->db->query("select t1.notes, t1.email_type, t1.customers_emails_id, t2.notes, t2.email_type, t2.customers_emails_id ".
			"from customers_emails t1 ".
			"join customers_emails t2 using(email) ".
			"where t1.customers_id = '$customer[customers_id]' and t2.customers_id = '$customers_id'");
			
		while(list($new_notes, $type1, $id1, $notes2, $type2, $id2) = $r->fetch_row())
		{	 	
			$new_notes .= "; $type2 email from merged account";	
			
			if(!empty($notes2))
			{
				$new_notes .= ": ".$notes2;
			}
			
			$new_notes = trim($new_notes, " ;:");
		
			$this->db->query("update invoicing.customers_emails ".
				"set notes = '".$this->db->escape_string($new_notes)."' ".
				"where customers_emails_id = '$id1'");
				
			$this->db->query("delete from invoicing.customers_emails ".
				"where customers_emails_id = '$id2'");
		}
		
		$this->db->query("update invoicing.customers_emails ".
			"set customers_id = '$customers_id' ".
			"where customers_id = '$customer[customers_id]'");
			
		$this->db->query("insert into invoicing.customers_emails ".
			"set customers_id = '$customers_id', email = '".$this->db->escape_string($customer['email'])."', ".
			"notes = 'Primary email from merged account' ".
			"on duplicate key update notes = trim(both ' ;:' from concat(coalesce(notes, ''), '; Primary email from merged account'))");
		
		
		
		//Add address from merged account
		$this->db->query(assemble_insert_query3(array(
			"customer_id" => $to_id,
			"name" => $customer['name'],
			"phone_number_1" => $customer['phone_number_1'],
			"phone_number_2" => $customer['phone_number_2'],
			"fax_number" => $customer['fax_number'],
			"ship_attention_line" => $customer['ship_attention_line'],
			"ship_address_line1" => $customer['ship_address_line1'],
			"ship_address_line2" => $customer['ship_address_line2'],
			"ship_city" => $customer['ship_city'],
			"ship_state" => $customer['ship_state'],
			"ship_zip" => $customer['ship_zip'],
			"ship_country" => $customer['ship_country'],
			"address_label" => "Other Shipping",
			"address_label2" => $customer['customer_id'],
			"type" => "merged",
		), "invoicing.customers_addresses", $this->db));
		
		$r = $this->db->query("select * from invoicing.bill_to_address ".
			"where customer_id = '".$this->db->escape_string($from_id)."'");
		
		if($r->num_rows)
		{
			$bill_to = $r->fetch_assoc();
			
			if(!Customer::billing_address_is_empty($bill_to))
			{
				$customer = Address::convert_bill_to($bill_to);
					
				$this->db->query(assemble_insert_query3(array(
					"customer_id" => $to_id,
					"name" => $customer['name'],
					"ship_attention_line" => $customer['ship_attention_line'],
					"ship_address_line1" => $customer['ship_address_line1'],
					"ship_address_line2" => $customer['ship_address_line2'],
					"ship_city" => $customer['ship_city'],
					"ship_state" => $customer['ship_state'],
					"ship_zip" => $customer['ship_zip'],
					"ship_country" => $customer['ship_country'],
					"address_label" => "Other Billing",
					"address_label2" => $customer['customer_id'],
					"type" => "merged",
				), "invoicing.customers_addresses", $this->db));
			}
		}
		
		$text = "\n\nMerged With $customer[customer_id]\n\n";
		
		if($extra = $this->extra_info($customer))
		{
			$text .= "Extra:\n$extra";
			$text .= "----------\n";
		}
		
		$this->db->query("update invoicing.customers set notes_for_invoice = ".
			"concat(coalesce(notes_for_invoice, ''), '".$this->db->escape_string($text)."') ".
			"where customer_id = '".$this->db->escape_string($to_id)."'");
		
		return true;
	}
	
	
	
	function extra_info($customer)
	{
		$text = "";
		if(!empty($customer['phone_number_1']))
			$text .= "Tel1: $customer[phone_number_1]\n";
		
		if(!empty($customer['phone_number_2']))
			$text .= "Tel2: $customer[phone_number_2]\n";
			
		if(!empty($customer['fax_number']))
			$text .= "Fax: $customer[fax_number]\n";
			
		if(!empty($customer['tshirt_field']))
			$text .= "Shirt: $customer[tshirt_field]\n";
		
		if(!empty($customer['book_field']))
			$text .= "Books: $customer[book_field]\n";
		
		//Let's not.
		//if(!empty($customer['preferred_package_value']))
			//$text .= "Preferred Package Value: $customer[preferred_package_value]
			
		if(!empty($customer['pay_and_hold_days']))
			$text .= "Pay & Hold Days: ".$customer['pay_and_hold_days']."\n";
		
		if(!empty($customer['notes_for_invoice']))
			$text .= "Notes: ".$customer['notes_for_invoice']."\n";
		
		return $text;
	}
	
	function move_customer_images_list($from_email, $to_email)
	{
		$ssh = ssh2_connect("images");
		$r = ssh2_fingerprint($ssh);
		$r = ssh2_auth_password($ssh, "server", "haluho");
		$sftp = ssh2_sftp($ssh);
		
		
		$lines = file("ssh2.sftp://".intval($sftp)."/webroot/customer_images/customer_list.txt", FILE_IGNORE_NEW_LINES);
		
		$from_email = strtolower($from_email);
		
		$change = false;
		
		foreach($lines as $k => $v)
		{
			if(strtolower($v) == $from_email)
			{
				$lines[$k] = $to_email;
				$change = true;
			}
		}
		
		if($change)
		{
			$f = fopen("ssh2.sftp://".intval($sftp)."/webroot/customer_images/customer_list.txt", "w");
			fwrite($f, implode("\r\n", $lines));
			fclose($f);
		}
		
		return $change ? 1 : 0;
	}
	
	
	function move_experts($from_email, $to_email)
	{
		$this->db->query("update listing_system.experts ".
			"set email = '".$this->db->escape_string($to_email)."' ".
			"where email = '".$this->db->escape_string($from_email)."'");
		
		return $this->db->affected_rows;
	}
	
	
	function move_sales_customer_id($from_id, $to_id)
	{
		$this->db->query("update invoicing.sales ".
			"set customer_id = '".$this->db->escape_string($to_id)."' ".
			"where customer_id = '".$this->db->escape_string($from_id)."'");
			
		return $this->db->affected_rows;
	}
	
	
	
	function move_cards($from_id, $to_id)
	{
		$this->db->query("update invoicing.tbl_cc ".
			"set customer_id = '".$this->db->escape_string($to_id)."' ".
			"where customer_id = '".$this->db->escape_string($from_id)."'");
			
		return $this->db->affected_rows;
	}
	
	
	
	function move_invoices($from_id, $to_id)
	{                                         
		$r = $this->db->query("select customers_id from invoicing.customers ".
			"where customer_id = '".$this->db->escape_string($to_id)."'")        ;
		list($customers_id) = $r->fetch_row();
		
		$this->db->query("update invoicing.invoices ".
			"set customer_id = '".$this->db->escape_string($to_id)."', ".
			"customers_id = '$customers_id' ".
			"where customer_id = '".$this->db->escape_string($from_id)."'");
			
		return $this->db->affected_rows;
	}
	
	
	
	function move_sales($from_email, $to_email)
	{
		$this->db->query("update invoicing.sales ".
			"set ebay_email = '".$this->db->escape_string($to_email)."' ".
			"where ebay_email = '".$this->db->escape_string($from_email)."'");
		
		return $this->db->affected_rows;
	}
	
	
	
	function move_fixed_price_sales($from_email, $to_email)
	{
		$this->db->query("update invoicing.fixed_price_sales ".
			"set user_email = '".$this->db->escape_string($to_email)."' ".
			"where user_email = '".$this->db->escape_string($from_email)."'");
		
		return $this->db->affected_rows;
	}
	
	
	
	function move_consignments_email($from_email, $to_email)
	{
		$this->db->query("update listing_system.tbl_Current_Consignments ".
			"set `High Bidder email` = '".$this->db->escape_string($to_email)."' ".
			"where `High Bidder email` = '".$this->db->escape_string($from_email)."'");
		
		return $this->db->affected_rows;
	}
	
	
	
	function move_quotes($from_email, $to_email)
	{
		$this->db->query("update invoicing.quotes ".
			"set customer_email = '".$this->db->escape_string($to_email)."' ".
			"where customer_email = '".$this->db->escape_string($from_email)."'");
		
		return $this->db->affected_rows;
	}
	
	
	
	function move_consignorlist($from_email, $to_email, $to_id)
	{
		$from_email = strtolower($from_email);
		$to_email = strtolower($to_email);
		
		$r = $this->db->query("select * from listing_system.tbl_consignorlist ".
			"where linking_email = '".$this->db->escape_string($from_email)."'");
		
		$affected = 0;
		
		while($row = $r->fetch_assoc())
		{
			$emails = array_map("strtolower", array_filter(array_map("trim", explode(",", $row['email'])), "strlen"));
			
			if(!in_array($from_email, $emails))
				$emails[] = $from_email;
			
			$this->db->query("update listing_system.tbl_consignorlist ".
				"set linking_email = '".$this->db->escape_string($to_email)."', ".
				"email = '".$this->db->escape_string(implode(", ", $emails))."', ".
				"cust_id = '".$this->db->escape_string($to_id)."' ".
				"where linking_email = '".$this->db->escape_string($from_email)."'");
				
			$affected++;
		}
		
		return $affected;
	}
	
	
	
	function move_autoship($from_email, $to_email, $to_id)
	{
		$r = $this->db->query("select customers_id from invoicing.customers ".
			"where customer_id = '".$this->db->escape_string($to_id)."'");
		
		list($customers_id) = $r->fetch_row();
		
		$this->db->query("update invoicing.autoship ".
			"set email_address = '".$this->db->escape_string($to_email)."', ".
			"customer_id = '".$this->db->escape_string($to_id)."', ".
			"customers_id = '".$customers_id."' ".
			"where email_address = '".$this->db->escape_string($from_email)."'");
			
		return $this->db->affected_rows;
	}
	
	function move_other_addresses($from_id, $to_id)
	{
		$r = $this->db->query("select customers_id from invoicing.customers ".
			"where customer_id = '".$this->db->escape_string($to_id)."'");
		
		list($to_customers_id) = $r->fetch_row();
		
		$r = $this->db->query("select customers_id from invoicing.customers ".
			"where customer_id = '".$this->db->escape_string($from_id)."'");
		
		list($from_customers_id) = $r->fetch_row();
		
		$this->db->query("update invoicing.customers_addresses ".
			"set customers_id = '$to_customers_id' ".
			"where customers_id = '$from_customers_id'");
			
		$this->db->query("update invoicing.customers_addresses ".
			"set customer_id = '".$this->db->escape_string($to_id)."' ".
			"where customer_id = '".$this->db->escape_string($from_id)."'");
			
		return true;
	}
	
	function move_checks($from_id, $to_id)
	{
		$this->db->query("update accounting.checks ".
			"set customer_id = '".$this->db->escape_string($to_id)."' ".
			"where customer_id = '".$this->db->escape_string($from_id)."'");
		
		return $this->db->affected_rows;
	}
}


class CustomerDupe
{
	function city($city)
	{
		$city = preg_replace("/^(st|saint|san|mt|mount|the|north|east|south|west|n|e|s|w|los|las|el|la)(\.| )/", "", strtolower($city));
		
		return preg_replace("/[^a-z]/", "", $city);
	}
	
	function phone($phone)
	{
		return strrev(preg_replace("/[^0-9]/", "", $phone));
	}
	
	function name($name)
	{
		$name = preg_replace("/(^| )(mr|mrs|ms|miss|dr|md|phd|jr|i+)( |$)/", "", preg_replace("/[^a-z -]/", "", strtolower($name)));
		
		$names = array_filter(array_map("trim", explode(" ", $name)), "strlen");
		
		
		return array_pop($names)." ".array_shift($names)." ".implode(" ", $names);
	}
	
	function street($row)
	{
		$address = preg_replace("/ (st|street|dr|drive|ln|lane|ave|avenue|rd|road|ct|court|hwy|highway|circle)\.?( |$)/", " ", preg_replace("/[^0-9a-z ]/", "", strtolower($row['ship_address_line1'])));
		$address = preg_replace("/(^| )(the|north|east|south|west|n|e|s|w|los|las|el|la|ne|se|sw|nw|po|p o)( |$)/", " ", $address);
		
		return trim(preg_replace("/[^a-z]/", "", strtolower($row['ship_state']))." ".self::city($row['ship_city'])." ".preg_replace("/ +/", " ", $address));
	}
}

class CustomerValidation
{
	
}


class Invoice
{
	function __construct($db, $wdb, $blab)
	{
		require_once("/webroot/includes/time.inc.php");
		
		$this->db = $db;
		$this->wdb = $wdb;
		$this->blab = $blab;
	}
	
	public static function get($invoice_number, $db)
	{
		$r = $db->query("select invoices.*, ship_country, pay_and_hold_days ".
			"from invoicing.invoices ".
			"join invoicing.customers using(customer_id) ".
			"where invoice_number = '$invoice_number'");
			
		if($row = $r->fetch_assoc())
		{
			return self::process($row, $db);
		}
		else
		{
			throw new exception("No such invoice '$invoice_number'");
		}
	}
	
	
	/**
	 *	Takes an invoicing.invoices row and adds information
	 *	necessary for the invoicing system.
	*/
	public static function process($row, $db)
	{
		require_once("/webroot/sync_website/generate_invoice.inc.php");
		
		$now = new DateTimeImmutable();
		$then = new DateTime($row['date_of_invoice']);
		$diff = $now->diff($then);
		
		$row['days_old'] = $diff->days;
		
		$row['payment_icon'] = self::get_payment_icon($row['payment_method']);
			
		$row['lot_numbers'] = self::lot_numbers($row['invoice_number'], $db);
		
		if(!empty($row['cc_which_one']))
			$row['last_four'] = substr($row['cc_which_one'], -4);
		
		$row['shipping_charged'] = floatval($row['shipping_charged']);
		
		//If fractional, use two digits.
		if(ceil($row['shipping_charged']) != $row['shipping_charged'])
			$row['shipping_charged'] = number_format($row['shipping_charged'], 2);
		
		$row['date_formatted'] = date("n/j/Y", strtotime($row['date_of_invoice']));
		
		$r2 = $db->query("select sum(price) from invoicing.sales ".
			"where price > 0 and invoice_number = '$row[invoice_number]'");
			
		list($row['subtotal']) = $r2->fetch_row();
		
		$row['subtotal'] = "$".number_format($row['subtotal']);
		
		$row['oldest_item'] = self::oldest_item($row['invoice_number'], $db);
		
		//TODO: If it's a pay and hold invoice, get oldest item and figure out due date
		/*$r = $this->db->query("select t1.shipping_notes as shipping_notes, min(t2.`date`) as min_date, ".
			"if(pay_and_hold_days is null, date_add(if(ship_country = 'US', date_add(min(t2.`date`), interval 21 day), ".
			"date_add(min(t2.`date`), interval 42 day)), interval 1 day), date_add(min(t2.`date`), interval pay_and_hold_days day)) as due ".
			"from invoicing.sales as t1 ".
			"join invoicing.customers on (ebay_email = email) ".
			"join invoicing.sales as t2 on (t1.reference = t2.invoice_number) ".
			"where customers.customer_id = '".$this->db->escape_string($customer_id)."' and t1.invoice_printed = 0 and t2.price > 0 ".
			"group by shipping_notes");*/
			
		list($items, $item_numbers, $sum_of_price) = flatten_ph_items($row['invoice_number'], $db);
		
		foreach($items as $i)
		{
			if($i['price'] > 0 && (!isset($row['min_date']) || $row['min_date'] > $i['date']))
			{
				$row['min_date'] = $i['date'];
			}
		}
		
		if(isset($row['min_date']))
		{
			$min_date = new DateTime($row['min_date']);	
			
			if(!empty($row['pay_and_hold_days']))
			{
				$row['pay_and_hold_due_date'] = $min_date->add(new DateInterval("P".$row['pay_and_hold_days']."D"))->format("Y-m-d");
			}
			elseif($row['ship_country'] == "US")
			{
				$row['pay_and_hold_due_date'] = $min_date->add(new DateInterval("P22D"))->format("Y-m-d");
			}
			else
			{
				$row['pay_and_hold_due_date'] = $min_date->add(new DateInterval("P43D"))->format("Y-m-d");
			}
			
			$then = new DateTime($row['pay_and_hold_due_date']);
			
			if($then < $now)
			{
				$diff = $now->diff($then);
			
				$row['days_past_pay_and_hold_due_date'] = $diff->days;
			}
			
			$row['min_date'] = date("m/d/y", strtotime($row['min_date']));
			$row['pay_and_hold_due_date'] = date("m/d/y", strtotime($row['pay_and_hold_due_date']));
		}
		
		return $row;
	}
	
	/**
	 * Takes a payment method from an invoice and outputs
	 * the filename of the payment method icon to use in a UI.
	 */
	public static function get_payment_icon($payment_method)
	{
		$payment_methods = array(
			"credit on account" => "accountcredit.png",
			"amazon order" => "amazon.png",
			"american express" => "americanexpress.png",
			"balance due" => "balancedue.png",
			"balance paid" => "paid.png",
			"bank wire" => "bankwire.png",
			"wire transfer" => "bankwire.png",
			"cash" => "cash.png",
			"check" => "check.png",
			"consignment proceeds" => "consignmentproceeds.png",
			"discover card" => "discover.png",
			"free" => "free.png",
			"mastercard" => "mastercard.png",
			"money order" => "moneyorder.png",
			"other" => "other.png",
			"paypal" => "paypal.png",
			"split payment" => "splitpayment.png",
			"split payments" => "splitpayment.png",
			"visa" => "visa.png",
			"western union" => "westernunion.png",
		);
	
		if(array_key_exists(strtolower(preg_replace("/#[0-9]+/", "", $payment_method)), $payment_methods))
			return $payment_methods[strtolower(preg_replace("/#[0-9]+/", "", $payment_method))];
		else
			return "other.png";
	}
	
	public static function lot_numbers($invoice, $db)
	{
		$lot_numbers = array();
		
		$r = $db->query("select 1 from sales ".
			"where invoice_number = '$invoice' and ebay_item_number regexp '^F[0-9]{4,}'");
			
		if($r->num_rows)
			$lot_numbers[] = "fixed";
		
		$r = $db->query("select distinct substring(ebay_title, 1, 2) ".
			"from sales ".
			"where invoice_number = '$invoice' and ebay_title regexp '^[0-9][a-z][0-9]{3}'");
			
		while(list($prefix) = $r->fetch_row())
		{			
			$r2 = $db->query("select min(ebay_title) ".
				"from sales ".
				"where invoice_number = '$invoice' and ebay_title like '$prefix%' and ebay_title regexp '^[0-9][a-z][0-9]{3}'");
				
			list($title) = $r2->fetch_row();
			
			preg_match("/^([0-9][a-z][0-9]{3,4}) /i", $title, $match);
			
			$lot_numbers[] = substr($match[1], 0, 2);
		}
		
		return $lot_numbers;
	}
	
	
	public static function oldest_item($invoice, $db)
	{
		$r = $db->query("select min(`date`) ".
			"from invoicing.sales ".
			"left join invoicing.fixed_price_sales on ebay_item_number = item_number ".
			"where invoice_number = '$invoice' and (ebay_item_number regexp '^[0-9]{7}$' or fixed_price_sales.item_number is not null)");
			
		list($date) = $r->fetch_row();
		
		return date("n/j/Y", strtotime($date));
	}
	
	
	function mark_printed($invoice_numbers)
	{
		//I had this in a joined update query but it caused a trigger to break, so
		//I had to split it into two queries.
		
		$r = $this->db->query("update invoicing.invoices set invoice_printed = '-1' where invoice_number in (".implode(",", $invoice_numbers).")");
		
		$r = $this->db->query("update invoicing.sales set invoice_printed = '-1' where invoice_number in (".implode(",", $invoice_numbers).")");
		
		$r = $this->db->query("update invoicing.fixed_price_sales, invoicing.sales ".
			"set invoiced = '1' ".
			"where sales.ebay_item_number = fixed_price_sales.item_number ".
			"and sales.invoice_number in (".implode(",", $invoice_numbers).")");
			
		return true;
	}
	
	
	function all_unprinted($name)
	{
		$r = $this->db->query(
			"select who_did, invoices.customer_id, date_of_invoice, invoice_number, name, ".
            "if(invoices.pay_and_hold, 'PH', '') as pay_and_hold ".
			"from invoicing.invoices ".
			"left join invoicing.customers using(customer_id) ".
			"left join invoicing.sales using(invoice_number) ".
			"where invoices.invoice_printed = '0' ".
			"group by invoices.invoice_number ".
			"order by invoices.invoice_number");
			
		$data = array();
		
		while($row = $r->fetch_assoc())
		{
			$who = $row['who_did'];
			unset($row['who_did']);
			
			if(!isset($data[$who]))
			{
				$data[$who] = array();
			}
			
			$data[$who][] = $row;
		}
		
		krsort($data);
		
		if(isset($data[$name]))
		{
			$mine = $data[$name];
			unset($data[$name]);
			$data[$name] = $mine;
		}
		
		
		$data = array_reverse($data);
		
		
		
		return $data;
	}
	
}
?>
