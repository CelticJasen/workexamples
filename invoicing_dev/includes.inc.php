<?php
require_once("/webroot/auth/auth.php");
session_write_close();
require_once("/webroot/sync_website/generate_invoice.inc.php");

header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // Date in the past

//permissions_required(PERM_DESCRIPTIONS);
mysqli_report(MYSQLI_REPORT_STRICT | MYSQLI_REPORT_ERROR);

$db = new mysqli("localhost", "office", "never eat shredded wheat", "invoicing");

//@mysql_query(sprintf("set @userId = '%s'", mysql_real_escape_string($user->getName())));
$db->query(sprintf("set @userId = '%s'", mysql_real_escape_string($user->getName())));

unset($result);

function lookup_tracking_numbers($query, $db)
{
	$query = trim($query);
	
	if(preg_match("/^[0-9]+$/", $query))
	{
		$query = parent_invoice_mysqli($query, $db); //If this is a subinvoice number, look up the root invoice.
		
		$r = $db->query("select ID, invoice_num, tracking_num, ship_type, package_id, name, email, ship_date, customer_id ".
			"from tbl_tracking_numbers ".
			"left join invoices on invoice_num = invoice_number ".
			"left join customers using(customer_id) ".
			"where invoice_num = '$query' or package_id = '$query' and ship_date > date_sub(now(), interval 30 day) order by ship_date");
	}
	elseif(preg_match("/^p([0-9]+)$/i", $query, $match))
	{
		$r = $db->query("select ID, invoice_num, tracking_num, ship_type, package_id, name, email, ship_date, customer_id ".
			"from tbl_tracking_numbers ".
			"left join invoices on invoice_num = invoice_number ".
			"left join customers using(customer_id) ".
			"where package_id = '$match[1]' and ship_date > date_sub(now(), interval 30 day) order by ship_date");
	}
	elseif(preg_match("/^[a-z0-9]+$/i", $query))
	{
		$r = $db->query("select ID, invoice_num, tracking_num, ship_type, package_id, name, email, ship_date, customer_id ".
			"from tbl_tracking_numbers ".
			"join invoices on invoice_num = invoice_number ".
			"join customers using(customer_id) ".
			"where customer_id = '$query' and ship_date > date_sub(now(), interval 30 day) order by ship_date ");
	}
	else
		throw new exception("I don't know how to search for '$query'.", 10040);
		
	if($r->num_rows)
	{
		$results = array();
		
		while($row = $r->fetch_assoc())
			$results[] = $row;
		
		return $results;
	}
	else
		throw new Exception("No packages found searching for '$query'.", 10040);
}


function fetch_tracking_number($id, $db)
{
	$r = $db->query("select ID, invoice_num, tracking_num, ship_type, package_id, name, email, ship_date, customer_id ".
			"from tbl_tracking_numbers ".
			"left join invoices on invoice_num = invoice_number ".
			"left join customers using(customer_id) ".
			"where ID = '$id'");
	
	return $r->fetch_assoc();
}


function fixed_price_items($invoice_number, $db)
{
	$r = $db->query("select sales.* from sales ".
		"join fixed_price_sales on (item_number = ebay_item_number) ".
		"where invoice_number = '".$db->escape_string($invoice_number)."'");
	
	$records = array();
	
	while($row = $r->fetch_assoc())
	{
		$records[] = $row;
	}
	
	return $records;
}


$tracking_email = <<<A
eMoviePoster.com has shipped your order!

Thank you for your purchase. We have recently shipped your order. Here is the tracking information:



<tracking>



Thank you very much!

Bruce Hershenson and the eMoviePoster.com team
A;

?>