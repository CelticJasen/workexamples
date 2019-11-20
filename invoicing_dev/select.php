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
		$wdb->query("set session max_execution_time = 20000");
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

	$search = new CustomerSearch($db, $blab);
	$customer = new Customer($db, $blab);
	$shipments = new Shipments($db, $blab);
	$invoicing = new Invoicing($db, $blab);
	$items = new Items($db, null, $blab);
	
	$result = array();
	
	if(!empty($_REQUEST['invoice_number']))
	{
		$result['address'] = $customer->address($_REQUEST['customer_id']);
		$result = array_merge($result, $items->invoice($_REQUEST['customer_id'], $_REQUEST['invoice_number']));
	}
	elseif(!empty($_REQUEST['main_menu']))
	{
		require_once("/webroot/invoicing/backend/email.inc.php");
		$mail = new MailIndex();
		$result['subscribed'] = $mail->check_subscription($_REQUEST['customers_id'], $_REQUEST['user_id']);
	}
	
	echo json_encode($result);
}
catch(exception $e)
{
	if($e->getCode() == 10040)
	{
		echo json_encode(Array("error" => $e->getMessage()));
	}
	else
	{
		echo json_encode(Array("error" => "There was an error. I will inform the admin of this problem.".$e->__toString()));
		email_error($e->__toString());
	}
}

?>