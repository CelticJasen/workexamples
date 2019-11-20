<?php
try
{
	set_error_handler("exception_error_handler", E_ALL & ~E_DEPRECATED);
	mysqli_report(MYSQLI_REPORT_STRICT | MYSQLI_REPORT_ERROR);
	require_once("/webroot/auth/auth.php");
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
	require_once("backend/reminders.inc.php");
	
	$blab = new Blab(__FILE__.".log", Blab::LOGGED);
	
	$data = array();
	
	//$search = new CustomerSearch($db, $blab);
	//$customer = new Customer($db, $blab);	
	//$shipments = new Shipments($db, $blab);
	$invoicing = new Invoicing($db, $blab);
	
	$mail = new MailIndex();
	
	$reminders = new Reminders($db, $blab, $mail);
	
	Event::log(array("user_id" => $_REQUEST['user_id'], "request" => $_REQUEST, "type" => "reminders-request"));
	
	if(!empty($_REQUEST['send']) || !empty($_REQUEST['blocks']))
	{
		if(empty($user->email))
		{
			throw new exception("You have no email address attached to your user account. You need to have I.T. add one for you.", 10040);
		}
		
		include 'Mail.php';
		include 'Mail/mime.php' ;
		
		if(!empty($_REQUEST['send']))
		{
			$reminders->send($_REQUEST['send'], $user);
		}
		
		if(!empty($_REQUEST['blocks']))
		{
			$reminders->blocks($_REQUEST['blocks'], $user);
		}
		
		die(json_encode2(array()));
	}
	
	
	
	
	if(empty($_REQUEST['template']))
	{
		$day = $reminders->day(new DateTimeImmutable($_REQUEST['date']));
		
		$data['reminders'] = $reminders->do_list($day);
		
		$data['selected_day'] = $day->format("l, F jS, Y");
		
		$date = $day->add(new DateInterval("P7D"));
			
		$data['due_date'] = $date->format("l, F jS, Y");
		
		$since = $date->diff(new DateTime());
		
		$data['since'] = $since->format("%d days");		
		
		require_once("Services/JSON.php");
		$json = new Services_JSON();
		echo $json->encode($data);
	}
	else
	{
		if(empty($user->email))
		{
			throw new exception("You have no email address attached to your user account. You need to have I.T. add one for you.", 10040);
		}
		
		include 'Mail.php';
		include 'Mail/mime.php' ;
		//header("Content-Type: message/rfc822");
		//header("Content-Disposition: attachment; filename=email".date("Ymd_His").".eml");
		
		list($body, $headers) = $reminders->generate_email($_REQUEST['customer_id'], "garble_".$_REQUEST['email'], new DateTimeImmutable($_REQUEST['date']));
		
		$mail = Mail::factory('sendmail');
		$mail->send($user->email, $headers, $body);
		//mail("aaron@emovieposter.com", "You have outstanding purchase(s)", $email, "Content-Type: text/html\r\nTo: aaron@brucehershenson.com");
		
		//Todo: all emails for customer?
		die(json_encode2(array()));
	}
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