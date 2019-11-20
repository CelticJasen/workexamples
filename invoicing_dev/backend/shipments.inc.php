<?php
/**
 * A class for dealing with packages and tracking.
 */

/*



	WARNING WARNING WARNING 

	This is already included by a file, so if you move this, you must also 
	fix where it is referenced.
	






*/
class Shipments
{
	/*
		TODO: Add blab debugging
	*/
	
	
	function __construct($db, $blab)
	{
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
	 * Takes a customer ID and returns tbl_tracking_numbers records
	 * for that customer's packages, and returns package status
	 * information for each package.
	 *
	 * returns array($records, $status)
	 *		- $records is an array of tbl_tracking_numbers records
	 *		- $status is a 2d array of tracking_status records
	 */
	function customer($customer_id)
	{
		require_once("/webroot/sync_website/generate_invoice.inc.php");
		
		$r = $this->db->query("select customers_id from invoicing.customers ".
			"where customer_id = '".$this->db->escape_string($customer_id)."' ");
		
		list($customers_id) = $r->fetch_row();
		
		/*
			Get all "tracked" invoices so we can figure out "untracked" invoices
		*/
		$r = $this->db->query("select invoice_num ".
			"from tbl_tracking_numbers ".
			"where customers_id = '$customers_id'");
			
		$invoices = $families = array();
		
		while(list($invoice_num) = $r->fetch_row())
		{
			$invoices[] = $invoice_num;
			
			try
			{
				if($relatives = invoice_family($invoice_num, $this->db))
				{
					$invoices = array_merge($relatives, $invoices);
					
					array_shift($relatives);
					if(count($relatives))
						$families[$invoice_num] = $relatives;
				}
			}
			catch(exception $e)
			{
				email_error($e->__toString());
				$invoices[] = "error";
			}
		}
		
		$untracked_invoices = array();
		
		/*
			Get untracked invoices
		*/
		$r = $this->db->query("select payment_method, date_of_invoice, invoice_number from invoices ".
			"where ".
			(!empty($invoices) ? "invoice_number not in ('".implode("','", $invoices)."') and " : "").
			"customer_id = '".$this->db->escape_string($customer_id)."' ".
			"order by date_of_invoice desc");
		
		
		
		while($row = $r->fetch_assoc())
		{
			$untracked_invoices[] = $this->process_untracked_invoice($row);
		}
		
		
		
		/*
			Get packages
		*/
		$r = $this->db->query("select t1.ID, invoice_num, tracking_num, ship_date, ship_type, ".
			"insurance_purchased, declared_value, original_value, t1.timestamp, weight_text, weight, postage, t1.package_id, ".
			"pull_sheet_id, insurance_fee, icon, xml, payment_method, shipto_address_id ".
			"from invoicing.tbl_tracking_numbers as t1 ".
			"left join invoicing.invoices on (invoice_num = invoice_number) ".
			"left join invoicing.quotes_packages on (quotes_packages.package_id = t1.package_id) ".
			"left join invoicing.quotes_packaging using(packaging_id) ".
			"where t1.customers_id = '$customers_id' ".
			"order by ship_date desc ");
		
		$records = array();
		$status = array();
		$address_ids = array();
		
		while($row = $r->fetch_assoc())
		{
			if(!in_array($row['shipto_address_id'], $address_ids))
				$address_ids[] = $row['shipto_address_id'];
				
			$address_index = array_search($row['shipto_address_id'], $address_ids);
			
			$p = $this->process_package($row);
			$p['address'] = chr(65+$address_index);
			
			$records[] = $p;
			$status[] = $this->package_status($row['ID']);
			
			
			// I had to rewrite this because Firefox Javascript suddenly 
			// started ordering associative arrays by their indexes rather than
			// their order of insertion. AK 2016-02-16
			/*
			$records[$row['ID']] = $this->process_package($row);
			
			$records[$row['ID']]['address'] = chr(65+$address_index);
			
			$status[$row['ID']] = $this->package_status($row['ID']);
			*/
		}
		
		return array($records, $status, $untracked_invoices, $families);
	}
	
	
	
	/**
	 * Takes a tbl_tracking_numbers ID and returns
	 * an array of tracking_status records, ordered
	 * be newest first.
	 */
	function package_status($tracking_id)
	{
		
		$r = $this->db->query("select tracking_status_id.* ".
			"from invoicing.tracking_status ".
			"join invoicing.tracking_status_id using(status_id) ".
			"where tracking_id = '".$this->db->escape_string($tracking_id)."' ".
			"order by `time` desc");
			
		$records = array();
		
		while($row = $r->fetch_assoc())
		{
			$records[] = $row;
		}
		
		return $records;
	}
	
	
	
	function tracking_number($tracking_id)
	{
		$r = $this->db->query("select tracking_num from invoicing.tbl_tracking_numbers where ID = '$tracking_id'");
		if($r->num_rows)
		{
			list($num) = $r->fetch_row();
			return $num;
		}
		return false;
	}
	
	
	/**
	 * Takes a USPS, UPS, or FedEx tracking number and generates
	 * the URL to view tracking information online. Returns
	 * an array with the first entry being the URL and the second
	 * being the tracking number.
	 * 
	 * If you are passing a FedEx tracking number, you must either set
	 * $fedex = true, or prepend the tracking number with a letter 'F'.
	 */
	static function url($tracking_number, $fedex = false)
	{
		if(strtolower(substr($tracking_number, 0, 1)) == "f")
		{
			$fedex = true;
			$tracking_number = substr($tracking_number, 1);
		}

		if($fedex)
		{
			$url = "https://www.fedex.com/fedextrack/index.html?tracknumbers=".$tracking_number."&cntry_code=us";
		}
		elseif(strtolower(substr($tracking_number, 0, 2)) == "1z")
		{
			$url = "http://wwwapps.ups.com/etracking/tracking.cgi?tracknum=".$tracking_number;
		}
		else
		{
			$url = "http://trkcnfrm1.smi.usps.com/PTSInternetWeb/InterLabelInquiry.do".
				"?strOrigTrackNum=".$tracking_number;
		}
		
		return array($url, $tracking_number);
	}
	
	
	function get_invoice_from_tracking_id($tracking_id)
	{
		$r = $this->db->query("select * from invoicing.tbl_tracking_numbers where ID = '$tracking_id'");
		
		if($r->num_rows == 0)
		{
			throw new exception("No such tracking id '$tracking_id'", 10000);
		}
		
		$tracking_row = $r->fetch_assoc();
		
		return $tracking_row['invoice_num'];
	}
	
	/**
	 *
	 *
	 *
	 */
	function other_info($invoice_number)
	{
		require_once("/webroot/invoicing/backend/customer.inc.php");
		require_once("/webroot/sync_website/generate_invoice.inc.php");
		
		$invoice = Invoice::get($invoice_number, $this->db);
		
		list($items, $item_numbers, $sum_of_price) = flatten_ph_items($invoice_number, $this->db);
		
		/*
			const REGULAR_ITEM = 0;
			const BONUS_ITEM = 1;
			const FIXED_PRICE_ITEM = 2;
			const PH_INVOICE_ITEM = 4; //Why is this not three? AK 20140916
			const UNKNOWN_ITEM = 255;
		*/
		$list = array();
		foreach($items as $item)
		{
			list($item_number, $type) = Items::get_type($item);
			
			if($type == Items::UNKNOWN_ITEM)
				$list[] = $item;
		}
		
		return array(
			"invoice" => $invoice,
			"items" => $list,
		);
	}
	
	
	
	
	function destination_address($tracking_id)
	{
		$r = $this->db->query("select * from invoicing.tbl_tracking_numbers ".
			"where ID = '$tracking_id'");
		
		if($r->num_rows == 0)
		{
			throw new exception("No such tracking id '$tracking_id'", 10000);
		}
		
		$tracking_row = $r->fetch_assoc();
		
		
		if(!empty($tracking_row['xml']))
		{
			list($output, $addr) = self::parse_endicia_xml($tracking_row['xml']);
		
			return self::format_endicia_address($addr);
		}
		else
			return null;
	}
	
	
	
	function item_package($autonumber)
	{
		$r = $this->db->query("select package_id, invoice_number, max(pull_sheet_id) ".
			"from invoicing.sales ".
			"left join invoicing.pull_sheet_id_map on (sales_id = autonumber) ".
			"where autonumber = '$autonumber' ".
			"group by autonumber");
			
		list($package_id, $invoice_number, $pull_sheet_id) = $r->fetch_row();
		
		if(!empty($package_id))
		{
			$r = $this->db->query("select ID from invoicing.tbl_tracking_numbers ".
				"where package_id = '$package_id'");
				
			
		}
		elseif(!empty($pull_sheet_id))
		{
			$r = $this->db->query("select ID from invoicing.tbl_tracking_numbers ".
				"where pull_sheet_id = '$pull_sheet_id'");
		}
		else
		{
			$r = $this->db->query("select ID from invoicing.tbl_tracking_numbers ".
				"where invoice_num = '$invoice_number'");
		}
		
		if($r->num_rows)
		{
			list($tracking_id) = $r->fetch_row();
		
			return $tracking_id;
		}
		
		return false;
	}
	
	
	
	/**
	 * Takes the id for the record in tbl_tracking_numbers and 
	 * returns a MySQL result set of the items in that package.
	 *
	 * Uses the invoice number, pull sheet id, or package id to
	 * fetch all the items in the package. 
	 */
	function contents($tracking_id)
	{
		require_once("/webroot/sync_website/generate_invoice.inc.php");
		
		$r = $this->db->query("select * from invoicing.tbl_tracking_numbers where ID = '$tracking_id'");
		
		if($r->num_rows == 0)
		{
			throw new exception("No such tracking id '$tracking_id'", 10000);
		}
		
		$tracking_row = $r->fetch_assoc();
		
		if(empty($tracking_row['pull_sheet_id']) && empty($tracking_row['package_id']))
		{
			if(empty($tracking_row['invoice_num']))
			{
				throw new exception("empty invoice number", 10000);
			}
			else
			{
				$r = $this->db->query("select 1 from invoicing.tbl_tracking_numbers ".
					"where invoice_num = '".$this->db->escape_string($tracking_row['invoice_num'])."' ");
				
				if($r->num_rows > 1)
				{
					throw new exception("multiple packages with invoice number ".$tracking_row['invoice_num'], 10000);
				}
				else
				{
					list($items, $item_numbers, $sum_of_price) = flatten_ph_items($tracking_row['invoice_num'], $this->db);
					
					usort($items, array("self", "titlesort"));
					
					return $items;
				}
			}
		}
		elseif(empty($tracking_row['package_id']))
		{
			$r = $this->db->query("select sales.* ". //, invoices.customer_id = 'herb01' as invoiced_to_bruce ".
				"from invoicing.sales ".
				//"join invoicing.invoices using(invoice_number) ".
				"join invoicing.pull_sheet_id_map on (sales_id = autonumber and pull_sheet_id = '".$this->db->escape_string($tracking_row['pull_sheet_id'])."') ".
				"order by ebay_title");
				
			$items = array();
				
			while($row = $r->fetch_assoc())
			{
				$items[] = $row;
			}
			
			return $items;
		}
		else
		{
			$r = $this->db->query("select sales.*, invoices.customer_id = 'herb01' as invoiced_to_bruce ".
				"from invoicing.sales ".
				"join invoicing.invoices using(invoice_number) ".
				"where package_id = '".$this->db->escape_string($tracking_row['package_id'])."' ".
				"order by ebay_title");
				
			$items = array();
				
			while($row = $r->fetch_assoc())
			{
				$items[] = $row;
			}
			
			return $items;
		}
	}
	
	
	
	/**
	 * Takes an id of a record from the tbl_tracking_numbers
	 * and returns a dollar value.
	*/
	function value($tracking_id)
	{
		require_once("/webroot/sync_website/generate_invoice.inc.php");
		
		$r = $this->db->query("select * from invoicing.tbl_tracking_numbers where ID = '$tracking_id'");
		
		if($r->num_rows == 0)
		{
			throw new exception("No such tracking id '$tracking_id'", 10000);
		}
		
		$tracking_row = $r->fetch_assoc();
		
		if(empty($tracking_row['pull_sheet_id']) && empty($tracking_row['package_id']))
		{
			if(empty($tracking_row['invoice_num']))
			{
				throw new exception("empty invoice number", 10000);
			}
			else
			{
				$r = $this->db->query("select 1 from invoicing.tbl_tracking_numbers ".
					"where invoice_num = '".$this->db->escape_string($tracking_row['invoice_num'])."' ");
				
				if($r->num_rows > 1)
				{
					throw new exception("multiple packages with invoice number ".$tracking_row['invoice_num'], 10000);
				}
				else
				{
					list($item_numbers, $sum_of_price) = total_ph_items($tracking_row['invoice_num'], $this->db);
					
					return $sum_of_price;
				}
			}
		}
		elseif(empty($tracking_row['package_id']))
		{
			$r = $this->db->query("select sum(price) from invoicing.sales ".
				"join invoicing.pull_sheet_id_map on (sales_id = autonumber and ".
				"pull_sheet_id = '".$this->db->escape_string($tracking_row['pull_sheet_id'])."') ".
				"where price > 0");
			
			list($total) = $r->fetch_row();
			
			return $total;
		}
		else
		{
			$r = $this->db->query("select sum(price) from invoicing.sales ".
				"where package_id = '".$this->db->escape_string($tracking_row['package_id'])."' and ".
				"price > 0");
			
			list($total) = $r->fetch_row();
			
			return $total;
		}
	}
	
	
	
	function titlesort($a, $b)
	{
		return strcasecmp($a['ebay_title'], $b['ebay_title']);
	}
	
	
	function item_search($search, $customer_id)
	{
		require_once("/webroot/includes/mysqli_extensions.inc.php"); //TODO: This wasn't working
		
		
		$r = $this->db->query("select autonumber, invoice_number, max(pull_sheet_id) as pull_sheet_id, package_id ".
			"from invoicing.sales ".
			"left join invoicing.pull_sheet_id_map on (autonumber = sales_id) ".
			"where (shipping_notes like '%".$this->db->escape_string($search)."%' or ".
			"ebay_title like '%".$this->db->escape_string($search)."%' or ".
			"invoice_number = '".$this->db->escape_string($search)."') and ".
			"customer_id = '".$this->db->escape_string($customer_id)."' and invoice_number is not null ".
			"group by autonumber");
		
		if($r->num_rows)
		{
			$rows = array();
	        while($row = $r->fetch_assoc())
	        {
	        	unset($r2);
				
	        	if(!empty($row['package_id']))
				{
					$r2 = $this->db->query("select ID from tbl_tracking_numbers ".
						"where package_id = '$row[package_id]'");
				}
				
				if((empty($r2) || $r2->num_rows === 0) && !empty($row['pull_sheet_id']))
				{
					$r2 = $this->db->query("select ID from tbl_tracking_numbers ".
						"where pull_sheet_id = '$row[pull_sheet_id]'");
				}
				
				if(empty($r2) || $r2->num_rows === 0)
				{
					$r2 = $this->db->query("select ID from tbl_tracking_numbers ".
						"where invoice_num = '$row[invoice_number]'");
				}
				
				if(empty($r2) || $r2->num_rows === 0)
				{
					$r2 = $this->db->query("select ID from invoicing.tbl_tracking_numbers ".
						"join invoicing.sales t2 on (invoice_num = t2.invoice_number) ".
						"where t2.reference = '$row[invoice_number]'");
				}
	        	
				if($r2->num_rows > 0)
					list($row['tracking_id']) = $r2->fetch_row();
				
	            $rows[] = $row;
	        }
	        return $rows;
		}
		
		$r = $this->db->query("select ID as tracking_id from tbl_tracking_numbers where tracking_num = '".$this->db->escape_string($search)."'");
		
		if($r->num_rows)
		{
			return array($r->fetch_assoc());
		}
		
		return false;
	}
	
	
	
	/**
	 * Return a list of all the possible tracking statuses a USPS
	 * package can have.
	 */ 
	function tracking_statuses()
	{
		$r = $this->db->query("select status_id, text from invoicing.tracking_status_id order by text");
		
		$tracking_statuses = array();
		
		while(list($status_id, $text) = $r->fetch_row())
		{
			$tracking_statuses[] = array("status_id" => $status_id, "text" => $text);
		}
		
		return $tracking_statuses;
	}
	
	
	
	/**
	 * For all the possible tracking statuses a package can have,
	 * count how many packages are currently at that status.
	 */
	function package_statuses_summary()
	{
		$tracking_statuses = $this->tracking_statuses();
		
		foreach($tracking_statuses as $k => $t)
		{
			$r = $this->db->query("select count(*) from invoicing.tbl_tracking_numbers ".
				"where last_status = '$t[status_id]' and hide_until_status_change = '0' and ".
				"ship_date > date_sub(now(), interval 90 day)");
			
			list($tracking_statuses[$k]["count"]) = $r->fetch_row();
			
			if($tracking_statuses[$k]["count"] == 0)
				unset($tracking_statuses[$k]);
		}
		
		return $tracking_statuses;
	}
	

	
	
	
	/**
	 * Takes the XML string vomited out by Endicia and 
	 * returns an associative array of information we
	 * actually give a shit about.
	 */
	function parse_endicia_xml($xml)
	{
		//TODO: Look at aaron_tracking.php
		//TODO: Make this suck less
		
		$xml = @simplexml_load_string("<?xml version=\"1.0\" encoding=\"ISO-8859-1\" ?><xml>".$xml."</xml>");
		
		$addr = array(
			"ToName" => (string) $xml->ToName,
			"ToAddress1" => (string) $xml->ToAddress1,
			"ToAddress2" => (string) $xml->ToAddress2,
			"ToAddress3" => (string) $xml->ToAddress3,
			"ToAddress4" => (string) $xml->ToAddress4,
			"ToAddress5" => (string) $xml->ToAddress5,
			"ToAddress6" => (string) $xml->ToAddress6,
			"ToCity" => (string) $xml->ToCity,
			"ToState" => (string) $xml->ToState,
			"ToPostalCode" => (string) $xml->ToPostalCode,
			"ToCountry" => (string) $xml->ToCountry,
		);
		
		$output = array(
			"MailClass" => (string) $xml->MailClass,
			"Registered" => (((string) $xml->Services->attributes()->RegisteredMail) == "ON" ? "Registered" : ""),
		);
		
		$output = array_merge($output, $addr);
		
		$output = array_map("ucwords", array_map("strtolower", $output));
		
		if(strlen($output['ToState']) == 2)
			$output['ToState'] = strtoupper($output['ToState']);
		
		return array($output, $addr);
	}
	
	
	
	function format_endicia_address($address)
	{		
		$lines = array(
			$address['ToName'],
			$address['ToAddress1'],
			$address['ToAddress2'],
			$address['ToAddress3'],
			$address['ToAddress4'],
			$address['ToAddress5'],
			$address['ToAddress6'],
		);
		
		$lines[] = trim($address['ToCity'].", ".$address['ToState']." ".$address['ToPostalCode'], " ,");
		
		$lines[] = $address['ToCountry'];
		
		return implode("\n", array_filter(array_map("trim", $lines), "strlen"));
	}
	
	
	
	/**
	 * Used by the current_shipments page, this is for selecting
	 * all packages with a certain status_id.
	 */
	function packages_with_status($status_id)
	{
		require_once("/webroot/includes/time.inc.php");
		
		$r = $this->db->query("select ID, customer_id, name, ship_city, ship_state, ship_country, ".
			"tracking_num, ship_date, ship_type, xml, t1.package_id, pull_sheet_id, t1.invoice_num, ".
			"icon, type, insurance_purchased, notes ".
			"from invoicing.tbl_tracking_numbers as t1 ".
			"left join invoicing.invoices on (invoice_num = invoice_number) ".
			"left join invoicing.customers using (customer_id) ".
			"left join invoicing.quotes_packages on (quotes_packages.package_id = t1.package_id) ".
			"left join invoicing.quotes_packaging using(packaging_id) ".
			"where last_status = '$status_id' and hide_until_status_change = '0' and ".
			"ship_date > date_sub(now(), interval 90 day) ".
			"order by ship_date desc");
		
		$rows = array();
		
		while($row = $r->fetch_assoc())
		{
			$rows[] = $this->process_package($row);
		}
		
		return $rows;
	}
	
	
	function ups_packages()
	{
		require_once("/webroot/includes/time.inc.php");
		
		$r = $this->db->query("select ID, customer_id, name, ship_city, ship_state, ship_country, ".
			"tracking_num, ship_date, ship_type, xml, t1.package_id, pull_sheet_id, t1.invoice_num, ".
			"icon, type, insurance_purchased, notes ".
			"from invoicing.tbl_tracking_numbers as t1 ".
			"left join invoicing.invoices on (invoice_num = invoice_number) ".
			"left join invoicing.customers using (customer_id) ".
			"left join invoicing.quotes_packages on (quotes_packages.package_id = t1.package_id) ".
			"left join invoicing.quotes_packaging using(packaging_id) ".
			"where hide_until_status_change = '0' and ".
			"ship_date > date_sub(now(), interval 180 day) and original_value >= 1000 and tracking_num like '1z%' ".
			"order by ship_date desc");
		
		$rows = array();
		
		while($row = $r->fetch_assoc())
		{
			$rows[] = $this->process_package($row);
		}
		
		return $rows;
	}
	
	function process_untracked_invoice($row)
	{
		require_once("/webroot/includes/time.inc.php");
		
		$row['date_of_invoice'] = substr($row['date_of_invoice'], 0, 10);
		
		if(empty($row['date_of_invoice']) || $row['date_of_invoice'] == "0000-00-00")
		{
			$row['date_of_invoice'] = "";
			$row['date_of_invoice_formatted'] = "";
			$row['fuzzy_time'] = "";
		}
		else
		{
			$row['date_of_invoice_formatted'] = date("n/j/Y", strtotime($row['date_of_invoice']));
			
			/*
				How long ago was this?
			*/
			$row['fuzzy_time'] = Time::fuzzy(strtotime($row['date_of_invoice']));
		}
		
		try
		{
			list($item_numbers, $sum_of_price) = total_ph_items($row['invoice_number'], $this->db);
			$row['value'] = number_format($sum_of_price, 2);
		}
		catch(exception $e)
		{
			$row['value'] = "error";
		}
					
		
		
		return $row;
	}
	
	/**
	 * Takes an associative array with package information,
	 * extracts information from the Endicia XML dump, 
	 * and adds some more human-readable information to the 
	 * row. Returns the associative array.
	 *
	 * Data comes from invoicing.tbl_tracking_numbers.
	 */
	function process_package($row)
	{
		require_once("/webroot/includes/time.inc.php");
		
		if(empty($row['icon']))
			$row['icon'] = "box.png";
		
		$row['insurance_purchased'] = intval($row['insurance_purchased']);
		
		$row['ship_date'] = substr($row['ship_date'], 0, 10);
		
		if(empty($row['ship_date']) || $row['ship_date'] == "0000-00-00")
		{
			$row['ship_date'] = "";
			$row['ship_date_formatted'] = "";
			$row['fuzzy_time'] = "";
		}
		else
		{
			$row['ship_date_formatted'] = date("n/j/Y", strtotime($row['ship_date']));
			
			/*
				How long ago was this?
			*/
			$row['fuzzy_time'] = Time::fuzzy(strtotime($row['ship_date']));
		}
		
		
		/*
			Parse Endicia XML
		*/
		if(!empty($row['xml']))
			list($row['endicia']) = $this->parse_endicia_xml($row['xml']);
		
		unset($row['xml']);
		
		/*
			Get URL
		*/
		list($url, $tracking_num) = self::url($row['tracking_num']);
		
		$row['url'] = $url;
		
		/*
			Get value of items in package (that are >$0)
		*/
		if(!isset($row['original_value']) || is_null($row['original_value']))
			$row['original_value'] = "&#9587;";
		
		return $row;
	}
}



?>