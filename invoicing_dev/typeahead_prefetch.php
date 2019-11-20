<?php
set_error_handler("exception_error_handler", E_ALL);
mysqli_report(MYSQLI_REPORT_STRICT | MYSQLI_REPORT_ERROR);
$db = new mysqli("localhost", "root", "k8!05Er-05", "invoicing");
$db->set_charset("utf8");

require_once("/webroot/includes/string.inc.php");
require_once("/scripts/includes/blab.inc.php");

$blab = new Blab(null, Blab::NORMAL);

switch($_REQUEST['name'])
{
	case "customer":
		require_once("backend/customer.inc.php");
		
		$r = $db->query("select customers.customer_id, name, ship_city, ".
			"coalesce(statecode_name, ship_state) as state_name, ".
			"coalesce(countrycode_name, ship_country) as country_name, ".
			"ship_country, ship_state, customers.email ".
			"from sales ".
			"join customers on (ebay_email = email) ".
			"join invoicing.tbl_countrycode on ship_country = countrycode_alpha2 ".
			"join invoicing.tbl_statecode on (ship_state = statecode_value and tbl_statecode.countrycode_id = tbl_countrycode.countrycode_id) ".
			"where `date` > date_sub(now(), interval 14 day) ".
			"group by customers.customer_id");

		$data = array();

		while($row = $r->fetch_assoc())
		{
			$row['location'] = Customer::format_location($row);
			$data[] = $row;
		}
		
		echo json_encode($data);
   		break;
		
	case "vendor":
		require_once("backend/customer.inc.php");
		
		$r = $db->query("select customers.customer_id, name, ship_city, ".
			"coalesce(statecode_name, ship_state) as state_name, ".
			"coalesce(countrycode_name, ship_country) as country_name, ".
			"ship_country, ship_state, customers.email ".
			"from customers ".
			"join invoicing.tbl_countrycode on ship_country = countrycode_alpha2 ".
			"join invoicing.tbl_statecode on (ship_state = statecode_value and tbl_statecode.countrycode_id = tbl_countrycode.countrycode_id) ".
			"where notes_for_invoice regexp 'acc(oun)?t *#' ".
			"group by customers.customer_id");
		
		$data = array();
		
		while($row = $r->fetch_assoc())
		{
			$row['location'] = Customer::format_location($row);
			$data[] = $row;
		}
		
		echo json_encode($data);
   		break;
		
	case "item":
		$r = $db->query("select autonumber, ebay_title from invoicing.sales ".
			"where price > 0 and `date` > date_sub(now(), interval 7 day) ".
			"order by `date` desc limit 1000");
		
		$data = array();
		
		while($row = $r->fetch_assoc())
		{
			$data[] = $row;
		}
		
		echo json_encode($data);
		break;
		
	
}

?>