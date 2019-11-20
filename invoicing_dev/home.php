<?php
try
{
	set_error_handler("exception_error_handler", E_ALL & ~E_DEPRECATED);
	mysqli_report(MYSQLI_REPORT_STRICT | MYSQLI_REPORT_ERROR);
	$db = new mysqli("localhost", "root", "k8!05Er-05", "invoicing");
	$db->set_charset("utf8");

	require_once("/webroot/includes/string.inc.php");
	require_once("/scripts/includes/blab.inc.php");
	require_once("backend/customer.inc.php");
	require_once("backend/invoicing.inc.php");
	require_once("backend/items.inc.php");
	require_once("/webroot/includes/record_locking_mysqli.inc.php");
	require_once("backend/shipments.inc.php");
	require_once("backend/secure_message_opener.inc.php");
	require_once("backend/email.inc.php");
	
	$blab = new Blab(__FILE__.".log", Blab::LOGGED);

	//$search = new CustomerSearch($db, $blab);
	//$customer = new Customer($db, $blab);	
	//$shipments = new Shipments($db, $blab);
	
	try
	{
		$wdb = new mysqli();
		$wdb->options(MYSQLI_OPT_CONNECT_TIMEOUT, 1);
		$wdb->real_connect(WEB_MYSQL_HOST, WEB_MYSQL_USER, WEB_MYSQL_PASS);
	}
	catch(exception $e)
	{
		$status = "Could not connect to website.";
		$wdb = null;
	}
	
	$invoicing = new Invoicing($db, $blab, $wdb);
	
	
	//When $_REQUEST[search] is set, we are requesting only paypal payment results.
	if(!isset($_REQUEST['search']))
	{
		$data = array("payments" => $invoicing->paypal_payments(), "autoships" => $invoicing->autoships());
	}
	else
	{
		die(json_encode(array("payments" => $invoicing->paypal_payments($_REQUEST['search']))));
	}
	
	$invoices = new Invoice($db, null, $blab);
	
	$data['invoices'] = $invoices->all_unprinted($_REQUEST['user_name']);
	
	$data['orders'] = Orders::unprocessed($db);
	$data['processed'] = Orders::processed(date("Y-m-d"), $db);
	
	$data['debtors'] = $invoicing->debtors();

	
	
	$data['quote_requests'] = $invoicing->quote_requests();
	
	try
	{
		$mail = new MailIndex();
		$customers_ids = $mail->subscriptions($_REQUEST['user_id']);
		
		if(!empty($customers_ids))
		{	
			$data['subscriptions'] = array();
			
			$r = $db->query("select email, name, customer_id ".
				"from invoicing.customers ".
				"where customers_id in (".implode(",", $customers_ids).")");
			
			while($row = $r->fetch_assoc())
				$data['subscriptions'][] = $row;
		}
	}
	catch(exception $e)
	{
		email_error($e->__toString());
	}
	
	require_once("Services/JSON.php");
	$json = new Services_JSON();
	echo $json->encode($data);
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