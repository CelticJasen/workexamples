<?php
set_error_handler("exception_error_handler", E_ALL);
mysqli_report(MYSQLI_REPORT_STRICT | MYSQLI_REPORT_ERROR);
$db = new mysqli("localhost", "root", "k8!05Er-05", "invoicing");
$db->set_charset("utf8");

require_once("/webroot/includes/string.inc.php");
require_once("/scripts/includes/blab.inc.php");




$blab = new Blab("suggest.log", Blab::LOGGED);

switch($_REQUEST['name'])
{
	case "customer":
		require_once("backend/customer.inc.php");
		$search = new CustomerSearch($db, $blab);

		$customer_ids = $search->call("by_text", array($_REQUEST['q']));

		$customer_ids = array_map(array($db, "escape_string"), $customer_ids);

		$r = $db->query("select customer_id, name, ship_city, ".
			"coalesce(statecode_name, ship_state) as state_name, ".
			"coalesce(countrycode_name, ship_country) as country_name, ".
			"ship_country, ship_state, customers.email, count(ConsignorName) as consignor, notes_for_invoice, ".
			"notes_for_invoice regexp '(^|[^a-z0-9])aka(: *| +)".$db->escape_string(preg_quote($_REQUEST['q']))."($|[^a-z0-9])' as aka ".
			"from customers ".
			"left join listing_system.tbl_consignorlist on customer_id = cust_id ".
			"left join invoicing.tbl_countrycode on ship_country = countrycode_alpha2 ".
			"left join invoicing.tbl_statecode on (ship_state = statecode_value and tbl_statecode.countrycode_id = tbl_countrycode.countrycode_id) ".
			"where customer_id in ('".implode("','", $customer_ids)."') ".
			"group by customer_id ".
			"order by `name` like '%".$db->escape_string($_REQUEST['q'])."%' desc ");
		
		$data = array();

		while($row = $r->fetch_assoc())
		{
			if($row['aka'])
			{
			 	preg_match("/(?:^|[^a-z0-9])aka(?: *| +)(".preg_quote($_REQUEST['q'], "/")."[^\r\n$]*)(?:\r|\n|$)/i", $row['notes_for_invoice'], $matches);
				
				if(!empty($matches[1]))
					$row['name'] .= " AKA $matches[1]";
			}
			
			unset($row['notes_for_invoice']);
			
			$row['location'] = Customer::format_location($row);
			$data[] = $row;
		}

		echo json_encode($data);
		break;
	
	case "business":
		require_once("backend/customer.inc.php");
		$search = new CustomerSearch($db, $blab);

		$customer_ids = $search->call("by_text", array($_REQUEST['q']));

		$customer_ids = array_map(array($db, "escape_string"), $customer_ids);

		$r = $db->query("select customer_id, name, ship_city, ".
			"coalesce(statecode_name, ship_state) as state_name, ".
			"coalesce(countrycode_name, ship_country) as country_name, ".
			"ship_country, ship_state, customers.email, count(ConsignorName) as consignor ".
			"from customers ".
			"left join listing_system.tbl_consignorlist on customer_id = cust_id ".
			"left join invoicing.tbl_countrycode on ship_country = countrycode_alpha2 ".
			"left join invoicing.tbl_statecode on (ship_state = statecode_value and tbl_statecode.countrycode_id = tbl_countrycode.countrycode_id) ".
			"where customer_id in ('".implode("','", $customer_ids)."') ".
			"group by customer_id ".
			"order by customer_id regexp 'x[0-9]' or customer_id regexp '^p-' desc");
		
		$data = array();

		while($row = $r->fetch_assoc())
		{
			$row['location'] = Customer::format_location($row);
			$data[] = $row;
		}

		echo json_encode($data);
		break;
	
	case "customer_close_match":
		require_once("backend/customer.inc.php");
		$search = new CustomerSearch($db, $blab);

		$customer_ids = $search->by_name_approximate($_REQUEST['q']);

		$customer_ids = array_map(array($db, "escape_string"), $customer_ids);

		$r = $db->query("select customer_id, name, ship_city, ".
			"coalesce(statecode_name, ship_state) as state_name, ".
			"coalesce(countrycode_name, ship_country) as country_name, ".
			"ship_country, ship_state, email ".
			"from customers ".
			"left join invoicing.tbl_countrycode on ship_country = countrycode_alpha2 ".
			"left join invoicing.tbl_statecode on (ship_state = statecode_value and tbl_statecode.countrycode_id = tbl_countrycode.countrycode_id) ".
			"where customer_id in ('".implode("','", $customer_ids)."')");
		
		$data = array();

		while($row = $r->fetch_assoc())
		{
			$row['location'] = Customer::format_location($row);
			$data[] = $row;
		}

		echo json_encode($data);
		break;
	
	case "item":
		require_once("backend/items.inc.php");
		
		$search = new ItemsSearch($db, $blab);
		
		$items = $search->by_text($_REQUEST['q']);
		
		if(empty($items))
			die("[]");
		
		$r = $db->query("select ebay_title, customers.customer_id, payout_id ".
			"from sales ".
			"left join customers on ebay_email = email ".
			"left join listing_system.tbl_Current_Consignments on (ebay_item_number = eBay_Item_Num and payout_id is not null) ".
			"where autonumber in (".implode(",", $items).") ".
			"group by sales.autonumber ".
			"order by sales.date desc ");
		
		$data = array();

		while($row = $r->fetch_assoc())
		{
			$data[] = $row;
		}

		echo json_encode($data);
		break;
	
	case "payment":		
		$r = $db->query("select * ".
			"from invoicing.customers ".
			"join invoicing.customers_emails using(customers_id) ".
			"join invoicing.paypal_notifications on payer_email = customers_emails.email ".
			"where payment_date > date_sub(now(), interval 90 day) and ".
			"txn_id like '%".$db->escape_string($_REQUEST['q'])."' and payer_email != '' ".
			"group by customers.customers_id");
		
		$data = array();
		
		while($row = $r->fetch_assoc())
		{
			/*
				The reason I selected "*" in the query and then 
				only used the three fields, instead of just selecting the 
				three fields in the query is because when I tried that,
				the query didn't return any results. I couldn't figure out why.
				I feel stupid. AK 2015-01-28
			*/
			$data[] = array("customer_id" => $row['customer_id'], "txn_id" => $row['txn_id'], "payer_email" => $row['payer_email']);
		}
		
		echo json_encode($data);
		break;
	
	case "newitem":
		//For fixed price items and books
		$r = $db->query("select books, price, item_number, ".
			"if(date_soldout is not null, 'SOLD OUT', `type`) as `type`, if(date_soldout is not null, 'SOLD OUT', sales_type) as sales_type ".
			"from invoicing.books ".
			"left join listing_system.`00-00-00 BINs (BINs)` on (`type` = 'BIN' and lot_number = item_number) ".
			"where ".
			//"`display` != '0' and ".
			"books like '%".$db->escape_string($_REQUEST['term'])."%' limit 15");
		
		$data = array();
		
		while($row = $r->fetch_assoc())
		{
			$row['label'] = $row['books'];
			$data[] = $row;
		}

		echo json_encode($data);
		break;
	
	case "country":
		$r = $db->query("select code, countries ".
			"from invoicing.countries ".
			"where code like '".$db->escape_string($_REQUEST['term'])."%' or ".
			"countries like '".$db->escape_string($_REQUEST['term'])."%' or ".
			"search like '%".$db->escape_string($_REQUEST['term'])."%' ".
			"order by code = '%s' desc");
		
		$data = array();
		
		while($row = $r->fetch_assoc())
		{
			$row['label'] = $row['code'];
			$data[] = $row;
		}
		
		echo json_encode($data);
		break;
	
	case "state":
		if(empty($_REQUEST['country']))
			$_REQUEST['country'] = "US";
		
		$r = $db->query("select * from cities ".
			"where `type` = 'RE' and `iso` like '".$db->escape_string($_REQUEST['country'])."-%' and ".
			"(`iso` like 'US-".$db->escape_string($_REQUEST['term'])."%' or ".
			"local_name like '".$db->escape_string($_REQUEST['term'])."%') ".
			"order by `iso` = 'US-".$db->escape_string($_REQUEST['term'])."' desc");
		
		$data = array();
		
		while($row = $r->fetch_assoc())
		{
			$split = explode("-", $row['iso']);
			
			if($split[0] == "US")
				$row['label'] = $split[1];
			else
				$row['label'] = $row['local_name'];
		
			$data[] = $row;
		}
		
		echo json_encode($data);
		break;
		
	case "city":
		//Figure out the id of the state.
		$r = $db->query("select id from invoicing.cities ".
			"where (iso = '".$db->escape_string($_REQUEST['country'])."-".$db->escape_string($_REQUEST['state'])."' or ".
			"(iso like '".$db->escape_string($_REQUEST['country'])."-%' and ".
			"local_name = '".$db->escape_string($_REQUEST['state'])."')) and ".
			"`type` = 'RE'");
			
		$data = array();
		
		if($r->num_rows)
		{
			list($state_id) = $r->fetch_row();
			
			$r = $db->query("select local_name from invoicing.cities ".
				"where in_location = '$state_id' and ".
				"local_name like '".$db->escape_string($_REQUEST['term'])."%' ".
				"order by local_name");
			
			while($row = $r->fetch_row())
			{
				$data[] = $row[0];
			}
		}
		
		echo json_encode($data);
		
		break;
		
	case "lots":
		$r = $db->query("select lots ".
			"from listing_system.tbl_consignorlist ".
			"where lots != '' ".
			"group by lots ".
			"having count(*) > 1 ".
			"order by count(*) desc ");
		
		$data = array();
			
		while(list($lots) = $r->fetch_row())
		{
			$data[] = array("label" => $lots);
		}
		
		die(json_encode($data));
		break;
		
	case "payment_preference":
		$r = $db->query("select payment_preference ".
			"from listing_system.tbl_consignorlist ".
			"where payment_preference not like '%paypal to %' and payment_preference != '' ".
			"group by payment_preference ".
			"having count(*) > 1 ".
			"order by count(*) desc");
			
		$data = array();
			
		while(list($val) = $r->fetch_row())
		{
			$data[] = array("label" => $val);
		}
		
		die(json_encode($data));
		break;
}

?>