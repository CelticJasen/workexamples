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
	require_once("backend/invoicing.inc.php");
	require_once("backend/items.inc.php");
	require_once("/webroot/includes/record_locking_mysqli.inc.php");
	require_once("backend/shipments.inc.php");
	
	$blab = new Blab(__FILE__.".log", Blab::LOGGED);
	
	$items = new Items($db, $wdb, $blab);
	
	$result = array(
		"credits" => $items->credits($_REQUEST['customer_id']),
		"items" => $items->all($_REQUEST['customer_id'], false, $_REQUEST['page'], $_REQUEST['perpage']),
		"page" => $_REQUEST['page'],
	);

	if(!empty($status))
		$result['status'] = $status;

	die(json_encode($result));
}
catch(exception $e)
{
	if($e->getCode() == 10040)
	{
		echo json_encode(Array("error" => $e->getMessage()));
	}
	else
	{
		echo json_encode(Array("error" => "There was an error. I will inform the admin of this problem.".$e->getMessage()));
		email_error($e->__toString());
	}
}

?>