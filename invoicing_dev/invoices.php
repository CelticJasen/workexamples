<?php
try
{

	set_error_handler("exception_error_handler", E_ALL);
	mysqli_report(MYSQLI_REPORT_STRICT | MYSQLI_REPORT_ERROR);
	$db = new mysqli("localhost", "root", "k8!05Er-05", "invoicing");
	$db->set_charset("utf8");
	$wdb = new mysqli(WEB_MYSQL_HOST, WEB_MYSQL_USER, WEB_MYSQL_PASS);

	require_once("/webroot/includes/string.inc.php");
	require_once("/scripts/includes/blab.inc.php");
	require_once("backend/customer.inc.php");
	
	$blab = new Blab(__FILE__.".log", Blab::LOGGED);

	$customer = new Customer($db, $blab);
		
	$data = $customer->call("invoices", array($_REQUEST['customer_id']));
	
	echo json_encode(array("result" => $data));
}
catch(exception $e)
{
	echo json_encode(array("error" => $e->__toString()));
}

?>
