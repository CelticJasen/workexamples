<?php
try
{
	set_error_handler("exception_error_handler", E_ALL);
	mysqli_report(MYSQLI_REPORT_STRICT | MYSQLI_REPORT_ERROR);
	$db = new mysqli("localhost", "root", "k8!05Er-05", "invoicing");
	$db->set_charset("utf8");
	
	try
	{
		$wdb = new mysqli();
		$wdb->options(MYSQLI_OPT_CONNECT_TIMEOUT, 3);
		$wdb->real_connect(WEB_MYSQL_HOST, WEB_MYSQL_USER, WEB_MYSQL_PASS);
	}
	catch(exception $e)
	{
		$wdb = false;
		$status = "Could not connect to website.";
	}

	require_once("/webroot/includes/string.inc.php");
	require_once("/scripts/includes/blab.inc.php");
	require_once("backend/customer.inc.php");
	require_once("backend/items.inc.php");
	require_once("/webroot/includes/record_locking_mysqli.inc.php");
	
	$blab = new Blab(__FILE__.".log", Blab::LOGGED);

	$items = new Items($db, $wdb, $blab);
	$customer = new Customer($db, $blab);
	
	$items = $items->unpaid($_REQUEST['customer_id']);
	
	$invoices = $customer->call("invoices", array($_REQUEST['customer_id']));
	$cards = $customer->call("cards", array($_REQUEST['customer_id']));
	$address = $customer->call("address", array($_REQUEST['customer_id']));
	
	//I don't think we need this. Only select.php and search.php need to provide locking.
	//RecordLock::release_all("invoicing", $db, $_REQUEST['user_id']);
	//RecordLock::get("customer", $customer->get_email($_REQUEST['customer_id']), "invoicing", $db, $_REQUEST['user_id']);
	
	$data = array("unpaid" => $items, "invoices" => $invoices, "cards" => $cards, "address" => $address);
	
	if(!empty($status))
		$data['status'] = $status;
	
	echo json_encode($data);
}
catch(exception $e)
{
	echo json_encode(array("error" => $e->__toString()));
}

?>
