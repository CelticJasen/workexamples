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
		$wdb->options(MYSQLI_OPT_CONNECT_TIMEOUT, 2);
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
	require_once("backend/email.inc.php");
	require_once("Services/JSON.php");
	$json = new Services_JSON();
	
	$blab = new Blab(__FILE__.".log", Blab::LOGGED);

	$search = new CustomerSearch($db, $blab);
	$customer = new Customer($db, $blab, $wdb);	
	$shipments = new Shipments($db, $blab);
	$invoicing = new Invoicing($db, $blab);
	
	$result = array();
	
	Event::log(array("term" => $_REQUEST['term'], "user_id" => $_REQUEST['user_id'], "event_type" => "invoicing-search"));
	
	$data = $search->call("search", array($_REQUEST['term']));
	
	if(empty($data['result']))
	{
		if(empty($data['dupe'])) //Only execute if no accounts found
		{
			if($aa_data = $search->aa_search($_REQUEST['term']))
			{
				$data['new_account'] = $search->aa_to_invoicing($aa_data);
				foreach($data['new_account'] as $k => $v)
				{
					$data['new_account'][$k] = mb_convert_encoding($v, "ISO-8859-1", "UTF-8");
				}
				
				$data['aa_account'] = $aa_data;
				
				$items = new Items($db, $wdb, $blab);
				
				$data['unpaid'] = $items->unpaid_by_email($aa_data['email']);
				
				$data['similar_names'] = $customer->similar_names($aa_data['FirstName']." ".$aa_data['LastName']);
				
				$data['block_records'] = $customer->blocked_bidders($aa_data['email']);
			}
			elseif($blocked_bidders = $customer->blocked_bidders($_REQUEST['term']))
			{
				$data['new_account'] = array(
					"email" => $_REQUEST['term'],
				);
				
				$data['block_records'] = $blocked_bidders;
			}
		}
		
		if(!empty($status))
			$data['status'] = $status;
		
		echo $json->encode($data);
	}
	else
	{
		if(!empty($data['tracking_id'])) //In case user was searching for a tracking number
		{
			$result['tracking_id'] = $data['tracking_id'];
			$result['tracking_number'] = $data['tracking_number'];
		}
		
		if(!empty($data['alert']))
			$alert = $data['alert'];
			
		if(!empty($data['consignor']))
			$result['consignor'] = 1;
			
		$data = $customer->get($data['result']);
		
		if(!empty($alert))
			$data['alerts'][] = $alert;
		
		$items = new Items($db, $wdb, $blab);
	 	
		
		if(empty($_REQUEST['nolock']))
		{
			/*
				Lock the customer record
			*/
			try
			{
				RecordLock::release("customer", $data['customer']['address']['email'], "invoicing", $db, $_REQUEST['user_id']);
				RecordLock::get("customer", $data['customer']['address']['email'], "invoicing", $db, $_REQUEST['user_id']);
			}
			catch(exception $e)
			{
				if($e->getCode() == 10022)
					$result['record_lock'] = $e->who_locked;
				else
					throw $e;
			}
		}
		
		/*
			Insert into history
		*/
		$invoicing->history_put($_REQUEST['user_id'], $data['customer']['address']['customers_id']);
		Event::log(array("user_id" => $_REQUEST['user_id'], "customers_id" => $data['customer']['address']['customers_id'], "type" => "invoicing-customer-open"));
		$result['history'] = $invoicing->history_get($_REQUEST['user_id']);
		$result['history2'] = $invoicing->history_get();
		
		
		/*
			Get information for packages tab
		*/
		$shipments = $shipments->call("customer", array($data['customer_id'], 20));
		
		foreach($shipments[0] as $id => &$package)
			$package['payment_icon'] = Invoice::get_payment_icon($package['payment_method']);
		
		unset($package);
		
		foreach($shipments[2] as $id => &$package)
			$package['payment_icon'] = Invoice::get_payment_icon($package['payment_method']);
			
		unset($package);
		
		
		/*
			Get emails sent to the customer
		*/
		//This is being slow 2015-11-20
		/*
		try
		{
			$mail = new MailIndex();
			$result['mail_to'] = $mail->process_results($mail->get_mail_to(array_merge(array($data['customer']['address']['email']), $data['other_emails'])));
		}
		catch(exception $e)
		{
			$status = "Couldn't get emails. Maybe that server is offline? ".$e->getMessage();
		}
		*/
		
		$result['next_invoice_number'] = $invoicing->call("get_next_invoice_number", array($_REQUEST['user_id']));
		$result['next_bins_number'] = $items->call("get_next_bins_number", array());
		
		$result['customer'] = $data;
		$result['unpaid'] = $items->call("unpaid", array($data['customer_id']));
		$result['invoices'] = $customer->call("invoices", array($data['customer_id']));
		$result['packages'] = $shipments;
		
		if(!empty($status))
			$result['status'] = $status;
	
		$output = $json->encode($result);
		$blab("JSON output is ".(round(strlen($output) / 1024))." KB");
		echo $output;
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