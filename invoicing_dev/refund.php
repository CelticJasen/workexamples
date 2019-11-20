<?php
try
{	
	set_error_handler("exception_error_handler", E_ALL);
	mysqli_report(MYSQLI_REPORT_STRICT | MYSQLI_REPORT_ERROR);
	$db = new mysqli("localhost", "office", "never eat shredded wheat", "invoicing");
	$db->set_charset("utf8");
	$db->query("begin");

	if(!empty($_REQUEST['user_id']))
	{
		$db->query("set @userId = '".$db->escape_string($_REQUEST['user_id'])."'");
	}
	
	require_once("/webroot/includes/string.inc.php");
	require_once("/scripts/includes/blab.inc.php");
	require_once("backend/customer.inc.php");
	require_once("backend/items.inc.php");
	
	$blab = new Blab(__FILE__.".log", Blab::LOGGED);
	
	$customer = new CustomerWrite($db, $blab);
	
	usleep(150000);
	
	if(!empty($_REQUEST['amount']))
	{
		if(!is_numeric($_REQUEST['amount']))
			throw new exception("There is something wrong with the values you typed in.", 10000);
		
		$customer->add_cc_refund($_REQUEST['customers_id'], $_REQUEST['invoice_number'], 
			$_REQUEST['amount'], $_REQUEST['user_id'], $_REQUEST['cc_id'], $_REQUEST['memo']);
			
		$db->query("commit");
		
		//Mailer::mail("aaron@emovieposter.com", "\"Record a refund\" used", "Request:\r\n".print_r($_REQUEST, true));
		
		die(json_encode(array("success" => "1")));
	}
}
catch(exception $e)
{
	if($e->getCode() < 20000 && $e->getCode() > 9999) //User error
	{
		echo json_encode(array("error" => $e->getMessage()));
	}
	elseif($e instanceof mysqli_sql_exception)
	{
		echo json_encode(array("error" => $e->getMessage()));
	}
	else
	{
		email_error($e->__toString());
		echo json_encode(array("error" => "Something broke!\r\n\r\n".$e->__toString()));
	}
}



?>