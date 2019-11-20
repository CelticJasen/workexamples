<?php

try
{
	set_error_handler("exception_error_handler", E_ALL);
	mysqli_report(MYSQLI_REPORT_STRICT | MYSQLI_REPORT_ERROR);
	$db = new mysqli("localhost", "root", "k8!05Er-05", "invoicing");
	$db->set_charset("utf8");

	require_once("/webroot/includes/string.inc.php");
	require_once("/scripts/includes/blab.inc.php");
	require_once("/webroot/invoicing/backend/shipments.inc.php");
	require_once("/webroot/invoicing/backend/customer.inc.php");
	require_once("/webroot/includes/record_locking_mysqli.inc.php");
	
	$blab = new Blab(__FILE__.".log", Blab::LOGGED);

	$shipments = new Shipments($db, $blab);
	
	if(!empty($_REQUEST['item_search']) && !empty($_REQUEST['customer_id']))
	{
		$items = $shipments->item_search($_REQUEST['item_search'], $_REQUEST['customer_id']);
		
		echo json_encode(array("result" => $items));
	}
	elseif(!empty($_REQUEST['customer_id']))
	{
		$data = $shipments->call("customer", array($_REQUEST['customer_id']));
		
		foreach($data[0] as $id => &$package)
				$package['payment_icon'] = Customer::get_payment_icon($package['payment_method']);
		
		//I don't think we need this. Only select.php and search.php need to provide locking.
		//RecordLock::release_all("invoicing", $db, $_REQUEST['user_id']);
		//RecordLock::get("customer", $customer->get_email($_REQUEST['customer_id']), "invoicing", $db, $_REQUEST['user_id']);
		
		echo json_encode(array("result" => $data));
	}
	elseif(!empty($_REQUEST['invoice_number']))
	{
		require_once("/webroot/sync_website/generate_invoice.inc.php");
		list($items, $item_numbers, $sum_of_price) = flatten_ph_items($_REQUEST['invoice_number'], $db);
		
		usort($items, array($shipments, "titlesort"));
		
		$rows = array();
			
		foreach($items as $row)
		{
			if($row['price'] < 0 || !empty($row['reference']))
				continue;
			
			$row = array("ebay_title" => $row['ebay_title'], "autonumber" => $row['autonumber']);
			
			$rows[] = $row;
		}
		
		echo json_encode(array(
			"contents" => $rows, 
			"other_info" => $shipments->call("other_info", array($_REQUEST['invoice_number']))));
	}
	elseif(!empty($_REQUEST['tracking_id']))
	{
		$output = array();
		
		try
		{
			$output['tracking_number'] = $shipments->call("tracking_number", array($_REQUEST['tracking_id']));
			
			$output['destination'] = $shipments->call("destination_address", array($_REQUEST['tracking_id']));
			
			$output['other_info'] = $shipments->call("other_info", array($shipments->get_invoice_from_tracking_id($_REQUEST['tracking_id'])));
			
			$items = $shipments->call("contents", array($_REQUEST['tracking_id']));
			
			$rows = array();
			
			foreach($items as $row)
			{
				if($row['price'] < 0 || !empty($row['reference']))
					continue;
				
				
				
				$new_row = array("ebay_title" => $row['ebay_title'], "autonumber" => $row['autonumber']);
				
				if(!empty($row['invoiced_to_bruce']))
					$new_row['invoiced_to_bruce'] = 1;
				
				$rows[] = $new_row;
			}
		
			$output['contents'] = $rows;
		}
		catch(exception $e)
		{
			if($e->getCode() == 10000)
			{
				$output['html_message'] = $e->getMessage();
			}
			else
				throw $e;
		}
		
		die(json_encode($output));
	}
}
catch(exception $e)
{
	echo json_encode(array("error" => $e->__toString()));
}

?>