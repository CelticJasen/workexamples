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
	
	chdir("/webroot/credit_card_processor");
	require_once("charge.inc.php");

	$processor = new Credit_Card();
	
	if($_REQUEST['type'] == "custom")
	{
		$itemsWrite = new FakeItemsWrite($db, $blab);
	}
	else
	{
		$itemsWrite = new ItemsWrite($db, $blab);
	}
	
	
	usleep(150000);
	
	if(!empty($_REQUEST['amount']))
	{
		if(!is_numeric($_REQUEST['amount']) || //(!empty($_REQUEST['invoice']) && !ctype_digit($_REQUEST['invoice'])) || 
			empty($_REQUEST['title']))
			throw new exception("There is something wrong with the values you typed in.", 10000);
		
		$item = $processor->process_forcredit($db, $blab, $itemsWrite, 
			$_REQUEST['max_timestamp'], $_REQUEST['cc_id'], $_REQUEST['amount'], $_REQUEST['title'], null); //$_REQUEST['invoice']);
		
		if($_REQUEST['type'] == "custom")
		{
			Mailer::mail("mail@emovieposter.com", "\"Charge this credit card\" used: don't forget to record credit", 
				"Credit card was charged, but credit was not automatically added. Info: \r\n".print_r($item, true));
		}
		
		//Mailer::mail("aaron@emovieposter.com", "\"Charge this credit card\" used", "Request:\r\n".print_r($_REQUEST, true)."\r\n\r\nInfo (item not created):\r\n".print_r($item, true));
		
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