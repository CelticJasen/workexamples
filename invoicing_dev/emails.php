<?php
//header('Content-Type: text/plain');
try
{
	set_error_handler("exception_error_handler", E_ALL);
	mysqli_report(MYSQLI_REPORT_STRICT | MYSQLI_REPORT_ERROR);
	
	require_once("/scripts/includes/blab.inc.php");
	require_once("backend/email.inc.php");
	
	$blab = new Blab(__FILE__.".log", Blab::LOGGED);
	
	$result = array();
	
	/*
		Get emails sent to the customer
	*/
	try
	{
		if(!empty($_REQUEST['startdate']))
		{
			$options['startdate'] = $_REQUEST['startdate'];
		}
		else
		{
			$options['startdate'] = null;
		}
		
		if(!empty($_REQUEST['enddate']))
		{
			$options['enddate'] = $_REQUEST['enddate'];
		}
		else
		{
			$options['enddate'] = null;
		}
		
		if(!empty($_REQUEST['subjectsearch']))
		{
			$options['subjectsearch'] = $_REQUEST['subjectsearch'];
		}
		else
		{
			$options['subjectsearch'] = null;
		}
		
		if(!empty($_REQUEST['page']))
		{
			$options['page'] = $_REQUEST['page'];
		}
		else
		{
			$options['page'] = 1;
		}
		
		
		$mail = new MailIndex();
		$result['mail'] = $mail->process_results($mail->get_mail_to($_REQUEST['emails'], 1000, $options), true);
		
		$result['pageinfo'] = array_shift($result['mail']); //This seems a bit hacky but is necessary for retaining paging info
	}
	catch(exception $e)
	{
		$result['status'] = "Couldn't get emails. Maybe that server is offline? ".$e->getMessage();
	}		

	require_once("Services/JSON.php");
	$json = new Services_JSON();

	die($json->encode($result));
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