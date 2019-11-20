<?php
require_once("customer.inc.php");
/**
 * A class for retrieving information about items.
 */
class Items
{
	/*
		TODO: Add blab debugging
		TODO: Switch to numeric customer id in the future
		TODO: Insert/Update/Delete
		TODO: Special behavior for fixed price items
	*/
	const REGULAR_ITEM = 0;
	const BONUS_ITEM = 1;
	const FIXED_PRICE_ITEM = 2;
	const PH_INVOICE_ITEM = 4; //Why is this not three? AK 20140916 //Because binary? AK 2016-06-24
	const UNKNOWN_ITEM = 255;
	
	function __construct($db, $wdb, $blab)
	{
		require_once("/webroot/shipping_quotes/format_address.inc.php");
		
		$this->db = $db;
		$this->wdb = $wdb;
		$this->blab = $blab;
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
	 * Get all information about item specified by $item_id.
	 */
	function get($item_id)
	{
		/*
			I plan on this function getting all the information about a single item.
				- sales record
				- auction table record
				- current consignments record
				- auction anything link
				- find the image for this item
				- bulk lot info
				- checkout table status
				- items_winners table
				- auction history
		*/
		$output = array();
		
		$output['sales'] = $this->call("sales", array($item_id));
			
		if(empty($output['sales']))
		{
			$output['consignments'] = $this->call("consignments", array($item_id));
			
		}
		else
		{
			
		}
		
		$output['link'] = $this->call("link", array($item_id));
	}
	
	
	
	function get_next_bins_number()
	{
		$r = $this->db->query("select max(ebay_item_number) from sales ".
			"where ebay_item_number regexp '^F[0-9]{8}$' and ".
			"`date` > date_sub(curdate(), interval 2 month)"); //2015-08-13 Added this to speed it up. Query went from 1.5 seconds to 0.05 seconds.
		
		list($ebay_item_number) = $r->fetch_row();
		
		if(empty($ebay_item_number))
		{
			$r = $this->db->query("select max(ebay_item_number) from sales ".
				"where ebay_item_number regexp '^F[0-9]{8}$' and `date` > '2015-01-01'");
				
			list($ebay_item_number) = $r->fetch_row();
		}
		
		if($ebay_item_number < "F10001001")
			return "F10001001";
		
		$ebay_item_number++;
		
		return $ebay_item_number;
	}
	
	
	/**
	 * Returns the invoicing.sales record for item $item_id.
	 */
	function sales($item_id)
	{
		/*
			Retrieves an item's record from invoicing.sales. 
			Throws exception if there is more than one record.
		*/
		
		$r = $this->db->query("select * from invoicing.sales ".
			"where ebay_item_number = '".$this->db->escape_string($item_id)."'");
			
		if($r->num_rows == 1)
			return $r->fetch_assoc();
		elseif($r->num_rows > 1)
			throw new exception("More than one item with item number '$item_id'.", 10000);
		else
			return false;
	}
	
	function unpaid($customer_id)
	{
		return $this->all($customer_id, true);
	}
	
	/**
	 * Returns information about items for a specific customer.
	 */
	function all($customer_id, $unpaid = true, $page = 0, $perpage = 1000)
	{
		$r = $this->db->query(
			"select t1.*, ready, approved, group_concat(notes) as deletion_notes, count(tbl_Current_Consignments.id) as in_consignments, ".
			"quotes.ready as quote_ready, quotes.approved as quote_approved, quotes.type, quote_id, quotes.type as quote_type ".
			"from invoicing.sales as t1 ".
			"left join invoicing.customers as t2 on ebay_email = email ".
			"left join invoicing.quotes_packages using(package_id) ".
			"left join invoicing.quotes using (quote_id) ".
			"left join listing_system.tbl_Current_Consignments on (eBay_Item_Num = ebay_item_number) ".
			"where t2.customer_id = '".$this->db->escape_string($customer_id)."' and ".
			($unpaid ? "invoice_printed = '0' " : "1 ").
			"group by t1.autonumber ".
			"order by t1.`date` ".($unpaid === false ? "desc" : "").", ebay_title ".
			"limit ".($page*$perpage).",".$perpage);
		
		return $this->process_items_results($r);
	}
	
	
	/**
	 * Returns all of a customer's credits.
	 */
	function credits($customer_id)
	{
		$r = $this->db->query("select * ".
			"from invoicing.sales ".
			"where customer_id = '".$this->db->escape_string($customer_id)."' ".
			"and price < 0 ".
			"order by invoice_number is not null, `date` desc");
		
		$data = array();
		
		while($row = $r->fetch_assoc())
		{
			$row['price'] = number_format(0-$row['price'], 2);
			$row['date'] = date("m/d/Y", strtotime($row['date']));
			$data[] = $row;
		}
		
		return $data;
	}
			
	
	/**
	 * Returns information about items for a customer, searching by email address
	 */
	function unpaid_by_email($email)
	{		
		$r = $this->db->query(
			"select t1.*, ready, approved, group_concat(notes) as deletion_notes, count(tbl_Current_Consignments.id) as in_consignments, ".
			"quotes.ready as quote_ready, quotes.approved as quote_approved, quotes.type, quote_id, quotes.type as quote_type ".
			"from invoicing.sales as t1 ".
			//"left join invoicing.customers as t2 on ebay_email = email ".
			"left join invoicing.quotes_packages using(package_id) ".
			"left join invoicing.quotes using (quote_id) ".
			"left join listing_system.tbl_Current_Consignments on (eBay_Item_Num = ebay_item_number) ".
			"where ebay_email = '".$this->db->escape_string($email)."' and ".
			"invoice_printed = '0' ".
			"group by t1.autonumber ".
			"order by t1.`date`, ebay_title");
		
		return $this->process_items_results($r);
	}
	
	
	/**
	 * Takes result set from all(), or unpaid_by_email(),
	 * adds information to the rows, and returns the result set as an array.
	*/
	function process_items_results($r)
	{
		$data = $item_groups = $index = array();
		
		while($row = $r->fetch_assoc())
		{
			list($row['item_number'], $row['item_type']) = self::get_type($row);
			
			$row['link'] = self::link($row);
			$row['link'] = array("link" => $row['link']); //Gotta do this because Mustache templates
			$row['date'] = date("m/d/Y", strtotime($row['date']));
			
			/*
				Create $item_numbers, a two-dimensional array
				with the key being the item_type and the 
				value being an array of item numbers. We'll use this
				later for querying the website.
			*/
			if($row['item_type'] != self::UNKNOWN_ITEM)
			{
				if(empty($item_groups[$row['item_type']]))
				{
					$item_groups[$row['item_type']] = array();
					$index[$row['item_type']] = array();
				}
					
				
				$index[$row['item_type']][$row['item_number']] = $row['autonumber'];
				$item_groups[$row['item_type']][] = $row['item_number'];
			}
			
			if(!empty($row['reference']))
			{
				$row['invoice'] = Invoice::get($row['reference'], $this->db);
			}
			
			if(!empty($row['quote_ready']))
				$row['quote_ready'] = date("m/d/Y g:i a", strtotime($row['quote_ready']));
			
			if(!empty($row['quote_approved']))
				$row['quote_approved'] = date("m/d/Y g:i a", strtotime($row['quote_approved']));
			
			$data[$row['autonumber']] = $row;
		}
		
		if($this->wdb)
		{
			foreach($item_groups as $item_type => $item_numbers)
			{
				$r = $this->wdb->query("select item_id, quote_requested ".
					"from members.checkout ".
					"where item_id in ('".implode("','", $item_numbers)."') and bonus_item = '$item_type'");
				
				while(list($item_id, $quote_requested) = $r->fetch_row())
				{
					if(!empty($quote_requested))
					{
						$data[$index[$item_type][$item_id]]['quote_requested'] = date("m/d/Y g:i a", strtotime($quote_requested));
					}
				}
			}
		}
		
		return array_values($data);
	}
	
	
	
	/**
	 * Takes item number $item_id and returns a URL to view the item's 
	 * page online.
	 */
	static function link($item)
	{
		list($item_number, $item_type) = self::get_type($item);
		if($item_type == self::REGULAR_ITEM)
			return "http://auctions.emovieposter.com/Bidding.taf?_function=detail&Auction_uid1=$item[ebay_item_number]";
		else
			return false;
	}
	
	
	
	/**
	 * Returns the listing_system.tbl_Current_Consignments record
	 * for item $item_id.
	 */
	function consignments($item_id)
	{
		/*
			Items in tbl_Current_Consignments can have multiple records.
			
			This is because tbl_Current_Consignments is more like a journal
			of the sales, returns, cancellations, and relists than it is 
			a table of items.
			
			The table is named totally wrong. It's a log of sales, not
			of consignments, and they aren't current, they're previous.
			But I guess the original idea was to keep track of which consignors
			still need to be paid, so I guess it makes sense.
		*/
		$r = $this->db->query("select * from listing_system.tbl_Current_Consignments ".
			"where eBay_Item_Num = '".$this->db->escape_string($item_id)."'");
		
		if($r->num_rows)
		{
			$rows = array();
			
			while($row = $r->fetch_assoc())
			{
				$rows[] = $row;
			}
			
			return $rows;
		}
		else
		{
			return false;
		}
	}
	
	
	
	/**
	 * Returns the members.checkout record for item $item_id.
	 */
	function checkout($item_id)
	{
		/*
			Gets the record from members.checkout.
		*/
		if(preg_match("/^[0-9]{5,}$/", $item_id))
		{
			$r = $this->wdb->query("select * from members.checkout where item_id = '$item_id'");
			
			if($r->num_rows)
			{
				return $r->fetch_assoc();
			}
		}
		
		return false;
	}
	
	
	
	function invoice($customer_id, $invoice_number)
	{
		$r = $this->db->query("select * from invoicing.invoices where invoice_number = '$invoice_number'");
		
		if($r->num_rows == 0)
			throw new exception("No such invoice '$invoice_number'", 10000);
			
		$invoice = $r->fetch_assoc();
		
		$r = $this->db->query(
			"select t1.*, ready, approved, group_concat(notes) as deletion_notes, count(tbl_Current_Consignments.id) as in_consignments, ".
			"quotes.ready as quote_ready, quotes.approved as quote_approved, quotes.type, quote_id ".
			"from invoicing.sales as t1 ".
			"left join invoicing.customers as t2 on ebay_email = email ".
			"left join invoicing.quotes_packages using(package_id) ".
			"left join invoicing.quotes using (quote_id) ".
			"left join listing_system.tbl_Current_Consignments on (eBay_Item_Num = ebay_item_number) ".
			"where (t2.customer_id = '".$this->db->escape_string($customer_id)."' and invoice_printed = '0') or invoice_number = '$invoice_number' ".
			"group by t1.autonumber ".
			"order by t1.`date`, ebay_title");
			
		return array("invoice" => $invoice, "items" => $this->process_items_results($r));
	}
	
	
	
	/**
	 * Takes a sales table row and returns an array with two elements.
	 * The first element being the item number without its prefix.
	 * The second element is the type of item. See class constants.
	 */
	static function get_type($row)
	{
		if(!empty($row['reference']))
		{
			/*
				Paid & Hold invoice. Type is "4" and invoice number is the 
				number of the Paid & Hold invoice.
			*/
			return array($row['reference'], self::PH_INVOICE_ITEM);
		}
		
		if(preg_match("/^[0-9]{7,}$/", $row['ebay_item_number']))
		{
			/*
				Regular items are type "0"
			*/
			return array($row['ebay_item_number'], self::REGULAR_ITEM);
		}
		
		if(preg_match("/^(B|F)([0-9]+)$/", $row['ebay_item_number'], $match))
		{
			switch(strtoupper($match[1]))
			{
				case "B":
					/*
						Bonus items are type "1"
					*/
					return array($match[2], self::BONUS_ITEM);
					break;
				
				case "F":
					/*
						Fixed price items are type "2"
					*/
					return array($match[2], self::FIXED_PRICE_ITEM);
					break;
				
				default:
					return array($row['ebay_item_number'], self::UNKNOWN_ITEM);
					break;
			}
		}
		
		return array($row['ebay_item_number'], self::UNKNOWN_ITEM);
	}
}


class ItemsSearch
{
	function __construct($db, $blab)
	{
		require_once("/webroot/shipping_quotes/format_address.inc.php");
		
		$this->db = $db;
		$this->blab = $blab;
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
	 * Takes a string and searches for an item by item number 
	 * or title. Currently only searches the sales table.
	 * 
	 * Returns an array of sales table autonumbers.
	 */
	function by_text($term)
	{
		if(preg_match("/[0-9][a-z][0-9]{3}($| )/i", $term))
		{
			$r = $this->db->query("select autonumber from invoicing.sales ".
				"where ebay_title like '".$this->db->escape_string($term)."%' ".
				"order by `date` desc ".
				"limit 50");
		}
		else
		{
			$r = $this->db->query("select autonumber from invoicing.sales ".
				"where ebay_item_number = '".$this->db->escape_string($term)."'".
				"order by `date` desc ".
				"limit 50");
		}

		if($r->num_rows == 0)
		{
			$r = $this->db->query("select autonumber from invoicing.sales ".
				"where `date` > date_sub(now(), interval 30 day) and price > 0 and ".
				"ebay_title like '%".$this->db->escape_string($term)."%' ".
				"and `date` > date_sub(now(), interval 30 day) ".
				"order by `date` desc ".
				"limit 50");
		}
		
		$results = array();
		
		while(list($autonumber) = $r->fetch_row())
		{
			$results[] = $autonumber;
		}

			
		return $results;
	}
}


/*
 * Just like the standard ItemsWrite, with none
 * of that pesky functionality!
 */
class FakeItemsWrite
{
	
	function __construct($db, $blab)
	{
		//require_once("/webroot/shipping_quotes/format_address.inc.php");
		
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
	
	
	function create($data)
	{
		unset($data['fixed_price_sale']);
		
		$data['autonumber'] = "";
		
		return $data;
	}
	
}





define("SHIPTO_PRIMARY", 1);
define("SHIPTO_EXTRA",   2);

define("BILLTO_PRIMARY",   1);
define("BILLTO_SHIPPING",  2);
define("BILLTO_EXTRA",     3);

class ItemsWrite
{
	/*
		TODO: Add blab debugging
		TODO: Switch to numeric customer id in the future
		TODO: Insert/Update/Delete
	*/
	
	function __construct($db, $blab, $wdb = null, $customer_write = null, $adb = null, $user_id = null)
	{
		require_once("/webroot/shipping_quotes/format_address.inc.php");
		
		$this->db = $db;
		$this->wdb = $wdb;
		$this->adb = $adb;
		$this->blab = $blab;
		$this->customer_write = $customer_write;
		
		if(isset($user_id))
		{
			$this->user_id = $user_id;
			$r = $this->db->query("select full_name ".
				"from `poster-server`.`users` ".
				"where `id` = '$user_id'");
			list($this->username) = $r->fetch_row();
		}
	}
	
	
	
	function call($method, $args)
	{
		$time = microtime(true);
		
		$ret = call_user_func_array(array($this, $method), $args);
		
		$this->last_interval = microtime(true) - $time;
		
		$this->blab->blab("Method '$method' took ".$this->last_interval." seconds to complete.");
		
		return $ret;
	}
	
	
	function get_next_bins_number()
	{
		$r = $this->db->query("select max(ebay_item_number) from sales ".
			"where ebay_item_number regexp '^F[0-9]{8}$'");
		
		list($ebay_item_number) = $r->fetch_row();
		
		if($ebay_item_number < "F10001001")
			return "F10001001";
		
		$ebay_item_number++;
		
		return $ebay_item_number;
	}
	
	
	
	function delete($autonumbers)
	{
		$output = true;
		require_once("backend/shipments.inc.php");
		$shipments = new Shipments($this->db, $this->blab);
		
		try
		{
			$tracking_id = $shipments->item_package($autonumbers[0]);
			
			if($tracking_id !== false)
			{
				$r = $this->db->query("select date(ship_date) = curdate()".
					"from invoicing.tbl_tracking_numbers ".
					"where ID = '$tracking_id'");
					
				list($shipped_today) = $r->fetch_row();
				
				if($shipped_today)
				{
					$output = "That item has a tracking number. ".
						"Be sure shipping removes the tracking number if necessary. ";
				}
			}
		}
		catch(exception $e)
		{
			email_error($e->__toString());
		}
		
		$this->db->query("delete fixed_price_sales from invoicing.sales, invoicing.fixed_price_sales ".
			"where autonumber in ('".implode("','", $autonumbers)."') and length(ebay_item_number) >= 5 ".
			"and item_number = ebay_item_number");
		
		$this->db->query("delete from invoicing.sales where autonumber in ('".implode("','", $autonumbers)."')");
		
		return $output;
	}
	
	
	
	function create($data)
	{
		//TODO: Validation
		if($data['fixed_price_sale'])
		{
			if(!preg_match("/^[0-9]{4}([a-z]{2})? /i", $data['ebay_title']))
				throw new exception("That isn't any fixed price item I've ever heard of!", 10000);
			
			if(empty($data['ebay_id']))
				throw new exception("You can't leave ebay_id blank.", 10000);
			
			if(!empty($data['AA_ID']))
			{
				$data['date_added'] = date("Y-m-d H:i:s");
				$items_bin = $this->insert_items_bin($data);
				$data['ebay_item_number'] = "F".$items_bin['item_id'];
			}
			
			unset($data['AA_ID'], $data['date_added']);
			
			$this->insert_fixed_price_sales($data);
		}
		
		unset($data['AA_ID'], $data['date_added']);
		
		if(empty($data['ebay_item_number']))
			throw new exception("You can't leave ebay_item_number blank.", 10000);
		
		unset($data['fixed_price_sale']);
		
		$this->db->query(assemble_insert_query3(
			$data,
			"sales",
			$this->db
		));
		
		$r = $this->db->query("select * from invoicing.sales where autonumber = '".$this->db->insert_id."'");
		
		$row = $r->fetch_assoc();
		
		$row['date'] = date("m/d/Y", strtotime($row['date']));
		
		return $row;
	}
	
	
	
	function reminder_note($autonumbers, $note)
	{
		$this->db->query(assemble_update_query(
			"invoicing.sales", 
			array("reminder_notes" => $note), 
			"autonumber in ('".implode("','", $autonumbers)."')", $this->db));
			
		return $this->db->affected_rows;
	}
	
	
	
	function edit($autonumber, $data)
	{
		$this->db->query(assemble_update_query("invoicing.sales", $data, "autonumber = '$autonumber'", $this->db));
		
		$r = $this->db->query("select * from invoicing.sales where autonumber = '$autonumber'");
		
		$item = $r->fetch_assoc();
		
		if(isset($this->wdb) && !empty($data['combine_type']) && preg_match("/^[0-9]{7,}$/", $item['ebay_item_number']))
		{
			$this->wdb->query("update members.items_winners ".
				"set combine_type = '$data[combine_type]' ".
				"where item_id = '$item[ebay_item_number]'");
		}
		
		if(preg_match("/^F[0-9]{4,}$/", $item['ebay_item_number'], $match))
		{
			$new_data = array();
			
			$map = array(
				"date" => "date_sold",
				"ebay_item_number" => "item_number",
				"ebay_title" => "title",
				"price" => "price",
				"ebay_id" => "user_id",
				"ebay_email" => "user_email",				
			);
			
			foreach($data as $k => $v)
			{
				if(!empty($map[$k]))
					$new_data[$map[$k]] = $v;
			}
			
			if(!empty($new_data))
			{
				$this->db->query(assemble_update_query("invoicing.fixed_price_sales", $new_data, 
					"item_number = '".$this->db->escape_string($item['ebay_item_number'])."'", $this->db));
			}
		}
		
		return $item;
	}
	
	
	function insert_fixed_price_sales($data)
	{
		$new_data = array(
			"date_sold" => $data['date'],
			"item_number" => $data['ebay_item_number'],
			"title" => $data['ebay_title'],
			"price" => $data['price'],
			"user_id" => $data['ebay_id'],
			"user_email" => $data['ebay_email'],
			"copied_TotSales" => -1,
			"BIN_price_updated" => -1,
		);
		
		//TODO: Compare inserted data with selected data to see if date changed, for example
		
		$this->db->query("update listing_system.`00-00-00 BINs (BINs)` set ebay_email = '1' ".
			"where lot_number = '".$this->db->escape_string(substr($data['ebay_title'], 0, 4))."'");
		
		$this->db->query(assemble_insert_query3(
			$new_data,
			"fixed_price_sales",
			$this->db
		));
		
		$r = $this->db->query("select * from invoicing.fixed_price_sales where auto_id = '".$this->db->insert_id."'");
		
		return $r->fetch_assoc();
	}
	
	
	
	function insert_items_bin($data)
	{
		$new_data = array(
			"item_title" => $data['ebay_title'],
			"item_price" => $data['price'],
			"users_AAID" => $data['AA_ID'],
			"users_email" => $data['ebay_email'],
			"item_lotnum" => substr($data['ebay_title'], 0, 4),
			"date_added" => $data['date_added'],
		);
		
		$this->wdb->query(assemble_insert_query3(
			$new_data,
			"members.items_bin",
			$this->wdb
		));
		
		$r = $this->wdb->query("select * from members.items_bin ".
			"where item_id = '".$this->wdb->insert_id."'");
		
		return $r->fetch_assoc();
	}
	
	
	
	function delete_invoice($invoice_number)
	{
		$this->db->query("update invoicing.sales ".
			"set invoice_printed = 0, customer_id = null ".
			"where invoice_number = '".$this->db->escape_string($invoice_number)."'");
	
		$this->db->query("delete from invoicing.invoices ".
			"where invoice_number = '".$this->db->escape_string($invoice_number)."'");
		
		$r = $this->adb->query("select distinct ConsignorName ".
			"from accounting.receivable ".
			"join listing_system.tbl_consignorlist on (consignor_id = auto_id) ".
			"where invoice = '".$this->db->escape_string($invoice_number)."'");
		
		$names = array();	
		while(list($consignor) = $r->fetch_row())
			$names[] = $consignor;
		
		$this->adb->query("delete from accounting.receivable ".
			"where invoice = '".$this->db->escape_string($invoice_number)."'");
		
		return implode(" & ", $names);
	}
	
	function validate_invoice($invoice_number)
	{
		$warnings = array();
		
		$r = $this->db->query("select invoices.*, grand_total, ".
			"count(sales.autonumber) as items, sum(books.`type` = 'book') as books, ".
			"book_field like 'no%' as no_books, (grand_total is null) as no_grand_total, ".
			"ship_country, ship_state, sum(ebay_title like 'Missouri Sales Tax%') as sales_tax, ".
			"sum(sales.price < 0) as credits, countrycode_paypal, email, name, cc_id ".
			"from invoicing.invoices ".
			"join invoicing.customers using(customer_id) ".
			"left join invoicing.grand_totals using(invoice_number) ".
			"left join invoicing.sales using(invoice_number) ".
			"left join invoicing.books on (books = ebay_title) ".
			"left join invoicing.tbl_countrycode on (ship_country = countrycode_alpha2) ".
			"left join invoicing.tbl_cc on (cc_which_one = cc_num) ".
			"where invoice_number = '$invoice_number'".
			"group by invoices.invoice_number ");
		
		$row = $r->fetch_assoc();
		
		
		/*
			Check for address change
		*/
		$r2 = $this->db->query("select * from invoicing.customers ".
			"where customer_id = '".$this->db->escape_string($row['customer_id'])."'");
			
		$customer = $r2->fetch_assoc();
		
		if($customer['address_changed'] > $row['date_of_invoice'])
			$warnings[] = "Customer's address changed. Does address still match?";
		
		/*echo "<pre>";
		print_r($row);
		echo "</pre>";*/
		$r = $this->db->query("select 1 ".
			"from sales ".
			"where ebay_email = '".$this->db->escape_string($row['email'])."' and ".
			"invoice_number is null and price < 0");
		
		if($row['grand_total'] > 250 && stristr($row['payment_method'], "check") !== false)
		{
			//Mailer::mail("aaron@emovieposter.com", "approve personal check for invoice #$row[invoice_number]", 
				//"Invoice #$row[invoice_number] for $row[name] ($row[email]) was more than $250 and paid with a check. Has it been approved by Bruce?", array("From" => "tech@emovieposter.com"));
		}
		
		if($r->num_rows)
			$warnings[] = "Unused credit remaining on account.";
		
		if($row['ship_country'] == "US" && $row['ship_state'] == "MO" && $row['sales_tax'] == 0 && $row['customer_id'] != "herb01")
			$warnings[] = "No Missouri sales tax charged.";
		
		if($row['no_grand_total'])
			$warnings[] = "No grand total record.";
		
		if($row['no_books'] > 0 && $row['books'] > 0)
			$warnings[] = "Customer does not want books, but there are books on this invoice.";
		
		if($row['shipping_method'] == "Paid & Hold" && $row['shipping_charged'] != 0)
			$warnings[] = "Invoice is Pay & Hold but shipping_charged is not zero.";
		
		if($row['shipping_charged'] == 0 && !in_array($row['shipping_method'], array("Paid & Hold", "Hand Delivered", "No Shipping")) && $row['customer_id'] != "herb01")
			$warnings[] = "Shipping charged is zero.";
			
		if($row['shipping_charged'] > 100)
			$warnings[] = "A shipping cost of '\$$row[shipping_charged]' seems pretty high.";
			
		if(!empty($_REQUEST['user_id']) && ($_REQUEST['user_id'] == 118 || $_REQUEST['user_id'] == 7))
		{
			if(mt_rand(1,5) == 1)
			{
				//$warnings[] = "You seem pretty high. Are you sure you know what you're doing?";
			}
		}
			
		if(empty($row['payment_method']))
			$warnings[] = "Empty payment method.";
		
		if(empty($row['shipping_method']))
			$warnings[] = "Empty shipping method.";
			
		if(!empty($row['cc_which_one']) && empty($row['cc_id']))
			$warnings[] = "The credit card number on this invoice does not match any card in the database.";
		
		if(in_array($row['payment_method'], array("Visa", "MasterCard", "American Express", "Discover Card")))
		{
			if(empty($row['cc_which_one']) || !preg_match("/^[0-9]+$/", $row['cc_which_one']))
				$warnings[] = "No credit card selected";
		}
		else
		{
			if(!empty($row['cc_which_one']))
				$warnings[] = "Payment method is '$row[payment_method]', but you have selected a credit card.";
			
			if(empty($row['extra_info']) && !in_array($row['payment_method'], array("Amazon Order", "Invoice to Bruce")))
				$warnings[] = "No payment details in the notes field.";
		}
		
		if($row['payment_method'] == "Credit on account" && $row['credits'] == 0)
			$warnings[] = "'Credit on account' selected, but no credit is on the invoice.";
		
		if($row['countrycode_paypal'] == "0")
			$warnings[] = "Customer is from a country we may not be able to accept payment from.";
		
		//if($row['grand_total'] >= 400 && stristr($row['shipping_method'], "registered") === false)
			//$warnings[] = "Price over $399.99 and shipping method is not Registered";
			
		if($row['items'] == 0)
			$warnings[] = "No items on this invoice.";
		
		return $warnings;
	}
	
	
	
	function mark_invoice_shipped($invoice_number)
	{
		$this->db->query("update invoicing.invoices set status = 'shipped' ".
			"where invoice_number = '".$this->db->escape_string($invoice_number)."'");
		
		return true;
	}
	
	
	function invoice($data, $autonumbers, $update = false)
	{
		if($data['shipping_method'] == "Paid & Hold")
			$data['pay_and_hold'] = 1;
		else
			$data['pay_and_hold'] = 0;
		
		if($data['payment_method'] == "Invoice to Bruce")
		{
			$data['customer_id'] = "herb01";
			$data['customers_id'] = "13224"; //Bruce
			$data['shipto'] = "primary";
			unset($data['billto']);
		}
		
		/*
			Work with shipto and billto addresses
		*/
		if(!$update || isset($data['shipto']))
		{
			//Store shipping address and get address_id
			if(!isset($data['shipto']) || $data['shipto'] == "primary")
			{
				$data['shipto_address_id'] = $this->customer_write->get_address_id(
					$this->customer_write->get_primary_address($data['customers_id']));
			}
			elseif($data['shipto'] == "billing")
			{
				$data['shipto_address_id'] = $this->customer_write->get_address_id(
					$this->customer_write->get_billing_address($data['customers_id']));
			}
			else
			{
				$data['shipto_address_id'] = $this->customer_write->get_address_id(
					$this->customer_write->get_secondary_address($data['shipto']));
			}
			
			
			//Store billing address and get address_id
			if(!isset($data['billto']))
			{
				$data['billto_address_id'] = $this->customer_write->get_address_id(
					$this->customer_write->get_billing_address_legacy($data['customer_id']));
			}
			elseif($data['billto'] == "primary")
			{
				$data['billto_address_id'] = $this->customer_write->get_address_id(
					$this->customer_write->get_billing_address($data['customers_id']));
			}
			elseif($data['billto'] == "shipping")
			{
				$data['billto_address_id'] = $this->customer_write->get_address_id(
					$this->customer_write->get_primary_address($data['customers_id']));
			}
			else
			{
				$data['billto_address_id'] = $this->customer_write->get_address_id(
					$this->customer_write->get_secondary_address($data['billto']));
			}
		}
		
		unset($data['shipto'], $data['billto']);
		
		if(isset($data['reason_for_invoicing_to_bruce']))
		{
			$note = date("Ymd")." invoiced to Bruce because \"".$data['reason_for_invoicing_to_bruce']."\". ".$this->username;
			unset($data['reason_for_invoicing_to_bruce']);
		}
		
		/*
			Insert/Update invoice record
		*/
		$this->db->query(assemble_insert_query3(
			$data,
			"invoices",
			$this->db,
			$update
		));
		
		
		
		/*
			Work with items on invoice
		*/
		if(!empty($autonumbers))
		{
			if($update)
			{
				$this->db->query("update invoicing.sales ".
					"set invoice_number = null, customer_id = null, invoice_printed = 0 ".
					"where invoice_number = '$data[invoice_number]'");
			}
			
			if($data['payment_method'] == "Invoice to Bruce")
			{
				$this->db->query("update invoicing.sales set ebay_email = 'mail@emovieposter.com' ".
					"where autonumber in (".implode(",", $autonumbers).")");
				
				$this->db->query("update invoicing.sales t1, listing_system.tbl_Current_Consignments t2 ".
					"set t2.notes = concat('".$this->db->escape_string($note)."\r\n', coalesce(t2.notes, '')) ".
					"where autonumber in (".implode(",", $autonumbers).") and t1.ebay_item_number = t2.eBay_Item_Num");
				
				$data['payment_method'] = "Consignment Proceeds";
			}
			
			$this->db->query("select * from invoicing.sales ".
				"where autonumber in (".implode(",", $autonumbers).")");
			
			$this->db->query("update invoicing.sales set invoice_number = '$data[invoice_number]' ".
				"where autonumber in (".implode(",", $autonumbers).")");
				
			$this->db->query("update invoicing.sales set customer_id = '$data[customer_id]' ".
				"where autonumber in (".implode(",", $autonumbers).")");
				
			$this->db->query("update invoicing.sales t1, invoicing.invoices t2 ".
				"set t1.invoice_printed = t2.invoice_printed ".
				"where t1.invoice_number = t2.invoice_number and t2.invoice_number = '$data[invoice_number]'");
		}
		
		
		
		/*
			Change Shipto address for Pay & Hold invoices
		*/
		$r = $this->db->query("select shipto_address_id from invoicing.invoices ".
			"where invoice_number = '$data[invoice_number]' and shipto_address_id is not null");
		
		if($r->num_rows)
		{
			list($shipto_address_id) = $r->fetch_row();
			
			$this->db->query("update invoicing.sales, invoicing.invoices ".
				"set shipto_address_id = '$shipto_address_id' ".
				"where sales.reference = invoices.invoice_number and sales.invoice_number = '$data[invoice_number]'");
		}
		
		
		
		/*
			Insert totals into grand_totals table
		*/
		$r = $this->db->query("select sum(price*quantity) from sales where invoice_number = '$data[invoice_number]'");
		
		list($subtotal) = $r->fetch_row();
		
		$grand_total = bcadd($subtotal, $data['shipping_charged'], 2);
		
		$this->db->query(assemble_insert_query3(
			array(
				"invoice_number" => $data['invoice_number'],
				"SumOfprice" => $subtotal,
				"SumOfshipping_charged" => $data['shipping_charged'],
				"grand_total" =>  $grand_total,
			),
			"grand_totals",
			$this->db,
			$update
		));
		
		
		if(preg_match("/^Proceeds \((.*)\)$/", $data['payment_method'], $match))
		{
			require_once("/webroot/accounting/backend/accounting.inc.php");
			
			$accounting = new Accounting($this->adb, null);
			
			$r = $this->db->query("select auto_id from listing_system.tbl_consignorlist where ConsignorName = '".$this->db->escape_string($match[1])."'");
			list($consignor_id) = $r->fetch_row();
			
			$r = $this->db->query("select receivable_id, `date`, invoice, description, amount, ConsignorName ".
				"from accounting.receivable ".
				"left join listing_system.tbl_consignorlist on (consignor_id = auto_id) ".
				"where invoice = '$data[invoice_number]'");
		
			if($r->num_rows)
			{
				if($update)
				{
					while($dupe = $r->fetch_assoc())
					{
						$accounting->delete_transaction($dupe['receivable_id'], $_REQUEST['user_id']);
					}
				}
				else
				{
					$dupe = $r->fetch_assoc();
					
					throw new exception("There is already a record in the $dupe[ConsignorName] consignment account for ".
						"invoice #$data[invoice_number]. ($dupe[amount] on $dupe[date] ($dupe[description])). ", 10040);
				}
			}
			
			
			//Insert the items total + shipping as a transaction
			$r = $this->db->query("select sum(price*quantity) from sales where invoice_number = '$data[invoice_number]' and price >= 0");
			list($items_total) = $r->fetch_row();
			
			$accounting->add_transaction(
				array(
					"date" => $data['date_of_invoice'],
					"invoice" => $data['invoice_number'],
					"description" => "Invoice #".$data['invoice_number'],
					"debit" => bcadd($items_total, $data['shipping_charged'], 2),
					"consignor_id" => $consignor_id,
				),
				$_REQUEST['user_id'],
				1
			);
			
			//Insert each "credit" as a separate transaction
			$r = $this->db->query("select ebay_title, 0-price from sales where invoice_number = '$data[invoice_number]' and price < 0");
			
			while(list($title, $price) = $r->fetch_row())
			{
				$accounting->add_transaction(
					array(
						"date" => $data['date_of_invoice'],
						"invoice" => $data['invoice_number'],
						"description" => $title,
						"credit" => $price,
						"consignor_id" => $consignor_id,
					),
					$_REQUEST['user_id'],
					1
				);
			}

		}
		
		
		return true;
	}
}



?>