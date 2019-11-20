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
	
	

	$adb = new mysqli("localhost", "accounting", "been accounted for", "accounting");
	$adb->set_charset("utf8");
	$adb->query("begin");


	$db->query("set session max_execution_time = 20000");
	$adb->query("set session max_execution_time = 20000");
	
	require_once("/webroot/includes/string.inc.php");
	require_once("/scripts/includes/blab.inc.php");
	require_once("backend/customer.inc.php");
	require_once("backend/items.inc.php");
	
	
	$blab = new Blab(__FILE__.".log", Blab::LOGGED);

	$customer = new Customer($db, $blab);
	$write = new CustomerWrite($db, $blab);
	$items = new ItemsWrite($db, $blab, null, $write, $adb, $_REQUEST['user_id']);
	
	function fix_newline(&$val, $key)
	{
		$val = preg_replace('/\r\n|\r|\n/', "\r\n", $val);
	}
	

	usleep(max(round(586636-(time() - 1501181576)/3.5), 50000));
	
	/*
		Delete credit card
	*/
	if(!empty($_REQUEST['delete_card']))
	{
		$write->call("delete_card", array($_REQUEST['delete_card']));
		
		$db->query("commit");
		
		echo json_encode(array("done" => 1));
	}
	
	
	
	/*
		Delete extra address
	*/
	if(!empty($_REQUEST['delete_extra_address']))
	{
		$affected_rows = $write->call("delete_other_address", array($_REQUEST['address_id']));
		
		$db->query("commit");
			
		die(json_encode(array("affected_rows" => $affected_rows)));
	}
	
	
	
	/*
		New credit card (or update existing credit card)
	*/
	elseif(!empty($_REQUEST['cc_data']))
	{
		if(empty($_REQUEST['cc_update']))
		{
			$write->call("new_card", array($_REQUEST['cc_data']));
		
			$card = $customer->call("one_card", array($db->insert_id));
		}
		else
		{
			$write->call("update_card", array($_REQUEST['cc_data']));
			
			$card = $customer->call("one_card", array($_REQUEST['cc_data']['cc_id']));
		}
		
		$db->query("commit");
		
		die(json_encode(array("request" => $_REQUEST, "card" => $card)));
	}
	
	
	/*
		Delete invoice
	*/
	elseif(!empty($_REQUEST['delete_invoice']))
	{
		$consignor_names = $items->call("delete_invoice", array($_REQUEST['delete_invoice']));
		
		$db->query("commit");
		$adb->query("commit");
		
		$result = array("deleted_invoice" => $_REQUEST['delete_invoice']);
		
		if($consignor_names != "")
		{
			$result['warning'] = "I deleted record(s) from the consignor account: $consignor_names";
			
			Mailer::mail("mail@emovieposter.com", 
				"Consignor account modified: $consignor_names", 
				"Invoice #$_REQUEST[delete_invoice] was deleted ".
				"so the corresponding record in consignor ".
				"account ($consignor_names) was deleted as well.");
		}
		
		die(json_encode($result));
	}
	
	
	
	/*
		New invoice or update invoice
	*/
	elseif(!empty($_REQUEST['invoice']))
	{
		array_walk($_REQUEST['invoice'], "fix_newline");
		
		if(!empty($_REQUEST['invoice']['cc_which_one']))
		{
			$r = $db->query("select cc_num from tbl_cc ".
				"where cc_id = '".$db->escape_string($_REQUEST['invoice']['cc_which_one'])."'");
			
			if($r->num_rows)
			{
				list($_REQUEST['invoice']['cc_which_one']) = $r->fetch_row();
			}
			else
			{
				throw new exception("Can't create invoice: I couldn't find a credit card record with that cc_id.");
			}
		}
		
		if(empty($_REQUEST['items']))
			$_REQUEST['items'] = array();
		
		if(!empty($_REQUEST['update']))
		{
			$result = $items->call("invoice", array($_REQUEST['invoice'], $_REQUEST['items'], true)); //Allow overwrite
		}
		else
		{
			$result = $items->call("invoice", array($_REQUEST['invoice'], $_REQUEST['items']));
		}
		
		$warnings = $items->validate_invoice($_REQUEST['invoice']['invoice_number']);
		$warnings = array_merge($warnings, $customer->validate($_REQUEST['invoice']['customer_id']));
		
		if(!empty($warnings) && empty($_REQUEST['ignore']))
		{
			die(json_encode(array("warnings" => $warnings)));
		}
		
		$db->query("commit");
		$adb->query("commit");
		
		if(is_array($result))
		{
			Mailer::mail("aaron@emovieposter.com, mail@emovieposter.com", 
				"Offset Against Proceeds: $result[consignor] for invoice #$result[invoice_number]",
				"\$$result[grand_total] has been deducted from $result[consignor] ($result[customer_id]) for invoice #$result[invoice_number]. ".
				(empty($result['extra_info']) ? "" : "Notes: ".$result['extra_info']));
		}
		
		if(empty($_REQUEST['update']))
			Event::log(array("user_id" => $_REQUEST['user_id'], "request" => $_REQUEST, "type" => "invoicing-invoice-create", "invoice_number" => $_REQUEST['invoice']['invoice_number']));
		else
			Event::log(array("user_id" => $_REQUEST['user_id'], "request" => $_REQUEST, "type" => "invoicing-invoice-update", "invoice_number" => $_REQUEST['invoice']['invoice_number']));
		
		die(json_encode(array("created_invoice" => 1)));
	}
	
	
	
	/*
		New item
	*/
	elseif(!empty($_REQUEST['item']))
	{
		try
		{
			$wdb = new mysqli();
			$wdb->options(MYSQLI_OPT_CONNECT_TIMEOUT, 3);
			$wdb->real_connect(WEB_MYSQL_HOST, WEB_MYSQL_USER, WEB_MYSQL_PASS);
			$items = new ItemsWrite($db, $blab, $wdb);
		}
		catch(exception $e)
		{
			$status = "Could not connect to website.";
			die(json_encode(array("status" => $status, "error" => $e->getMessage())));
		}
		
		$_REQUEST['item'] = array_map("trim", $_REQUEST['item']);
		
		$row = $items->call("create", array($_REQUEST['item']));
		
		$db->query("commit");
		
		die(json_encode(array("item" => $row, "next_bins_number" => $items->get_next_bins_number())));
	}
	
	
	
	/*
		Delete item(s)
	*/
	elseif(!empty($_REQUEST['delete_items']))
	{		
		$result = array("deleted_items" => $_REQUEST['delete_items']);
		
		$message = $items->call("delete", array($_REQUEST['delete_items']));

		if($message !== true)
		{
			$result['warning'] = $message;
		}
		
		$db->query("commit");
		
		die(json_encode($result));
	}
	
	
	
	/*
		Insert into the phone orders printout history
	*/
	elseif(!empty($_REQUEST['printout']))
	{
		$_REQUEST['printout']['data'] = json_format($_REQUEST['printout']['data']);		
		
		$db->query(assemble_insert_query3(
			$_REQUEST['printout'],
			"invoicing.phone_order_printouts",
			$db
		));
		
		$db->query("commit");
		
		die(json_encode(array("ok" => 1)));
	}
	
	
	
	/*
		Make an invoice disappear from the items tab
	*/
	elseif(!empty($_REQUEST['mark_invoice_shipped']))
	{
		$items->mark_invoice_shipped($_REQUEST['mark_invoice_shipped']);
		
		$db->query("commit");
		
		die(json_encode(array("ok" => 1)));
	}
	
	
	/*
		Update customer on website
	*/
	elseif(!empty($_REQUEST['customer_website']))
	{
		try
		{
			$wdb = new mysqli();
			$wdb->options(MYSQLI_OPT_CONNECT_TIMEOUT, 3);
			$wdb->real_connect(WEB_MYSQL_HOST, WEB_MYSQL_USER, WEB_MYSQL_PASS);
		}
		catch(exception $e)
		{
			$status = "Could not connect to website.";
			die(json_encode(array("status" => $status, "error" => $e->getMessage())));
		}
		
		array_walk($_REQUEST['data'], "fix_newline");
		
		$write->call("customers_website", array($_REQUEST['data'], $wdb));
		
		die(json_encode(array("ok" => 1)));
	}
	
	
	
	/*
		Update customer or new customer
	*/
	elseif(!empty($_REQUEST['data']))
	{
		array_walk($_REQUEST['data'], "fix_newline");
		
		if($_REQUEST['table'] == "customers")
		{
			$data = array("request" => $_REQUEST);
			
			if(empty($_REQUEST['data']['customer_id']))
			{
				//TODO: Dupe check
				if(empty($_REQUEST['ignore']))
				{
					$similar = $customer->call("dupe_check", array($_REQUEST['data']));					
					
					if(count($similar))
						die(json_encode(array("similar" => $similar)));
				}
				
				//TODO: Make this based off billing address, when available.
				$data['new_customer_id'] = $_REQUEST['autoship']['customer_id'] = $_REQUEST['data']['customer_id'] = $customer->generate_customer_id($_REQUEST['data']['name'], $_REQUEST['data']['ship_country']);
				
				if(empty($_REQUEST['data']['email']))
				{
					$_REQUEST['data']['email'] = $data['new_customer_id'];
				}
			}
			
			$customers_id = $write->call("customers", array($_REQUEST['data']));
			
			if(!empty($_REQUEST['autoship']))
			{
				$write->call("autoship", array($_REQUEST['autoship'], $customers_id));
			}
			
			if(array_key_exists("other_emails", $_REQUEST))
			{
				$write->call("update_other_emails", array($_REQUEST['other_emails'], $customers_id));
			}
			
			$data['customer'] = $customer->get_lite($_REQUEST['data']['customer_id']);
			
			echo json_encode2($data);
		}
		else if($_REQUEST['table'] == "customers_addresses")
		{
			if(empty($_REQUEST['data']['customer_id']))
			{
				throw new exception("customer_id was blank", 10000);
			}
			
			foreach($_REQUEST['data'] as $k => $v)
			{
				if(empty($v))
				{
					$_REQUEST['data'][$k] = null;
				}
			}
			
			$data = array("request" => $_REQUEST);
			
			$write->call("customers_addresses", array($_REQUEST['data']));
			
			if(!empty($_REQUEST['notes_for_invoice']))
			{
				$write->call("update_notes_for_invoice", array($_REQUEST['customers_id'], $_REQUEST['notes_for_invoice']));
			}
			
			//Force reload of form
			$data['new_customer_id'] = $_REQUEST['data']['customer_id'];
			
			echo json_encode($data);			
		}
		else if($_REQUEST['table'] == "bill_to_address")
		{
			$write->call("bill_to_address", array($_REQUEST['data']));
			
			echo json_encode(array("request" => $_REQUEST));
		}
		
		$db->query("commit");
	}
	
	
	/*
		Update consignor record
	*/
	elseif(!empty($_REQUEST['consignor']))
	{
		array_walk($_REQUEST['consignor'], "fix_newline");
		
		$write->call("consignor", array($_REQUEST['consignor']));
		
		$db->query("commit");
		
		die(json_encode(array("ok" => 1)));
	}
	
	
	/*
		Change email address
	*/
	elseif(!empty($_REQUEST['from_id']) || !empty($_REQUEST['to_id']))
	{
		try
		{
			$wdb = new mysqli();
			$wdb->options(MYSQLI_OPT_CONNECT_TIMEOUT, 3);
			$wdb->real_connect(WEB_MYSQL_HOST, WEB_MYSQL_USER, WEB_MYSQL_PASS);
		}
		catch(exception $e)
		{
			$status = "Could not connect to website.";
			die(json_encode(array("status" => $status, "error" => $e->getMessage())));
		}
		
		$move = new CustomerMove($db, $wdb, $blab);
		
		list($from_email, $to_email) = $move->get_emails($_REQUEST['from_id'], $_REQUEST['to_id']);
		
		if(!empty($_REQUEST['move_items']))
		{
			move_items($from_email, $to_email, $move, $db);
		}
		elseif(!empty($_REQUEST['move_customer']))
		{
			move_customer($from_email, $to_email, $move, $db);
		}
		else
		{
			throw new exception("Invalid request");
		}
	}
	
	
	
	/*
		Change customer_id
	*/
	elseif(!empty($_REQUEST['from_customers_id']) && !empty($_REQUEST['to_customer_id']))
	{
		$db->query("update invoicing.customers set customer_id = '".$db->escape_string($_REQUEST['to_customer_id'])."' ".
			"where customers_id = '".$db->escape_string($_REQUEST['from_customers_id'])."'");
		
		$db->query("commit");
		
		die(json_encode(array("customer_id" => $_REQUEST['to_customer_id'])));
	}
	
	
	
	/*
		Change email address
	*/
	elseif(!empty($_REQUEST['from_email']) || !empty($_REQUEST['to_email']))
	{
		try
		{
			$wdb = new mysqli();
			$wdb->options(MYSQLI_OPT_CONNECT_TIMEOUT, 3);
			$wdb->real_connect(WEB_MYSQL_HOST, WEB_MYSQL_USER, WEB_MYSQL_PASS);
		}
		catch(exception $e)
		{
			$status = "Could not connect to website.";
			die(json_encode(array("status" => $status, "error" => $e->getMessage())));
		}
		
		$move = new CustomerMove($db, $wdb, $blab);
	
		if(!empty($_REQUEST['move_items']))
		{
			move_items($_REQUEST['from_email'], $_REQUEST['to_email'], $move, $db);
		}
		elseif(!empty($_REQUEST['move_customer']))
		{
			move_customer($_REQUEST['from_email'], $_REQUEST['to_email'], $move, $db);
		}
		else
		{
			throw new exception("Invalid request");
		}
	}
	
	
	
	/*
		Delete customer
	*/
	elseif(!empty($_REQUEST['delete_customer']))
	{
		try
		{
			$wdb = new mysqli();
			$wdb->options(MYSQLI_OPT_CONNECT_TIMEOUT, 3);
			$wdb->real_connect(WEB_MYSQL_HOST, WEB_MYSQL_USER, WEB_MYSQL_PASS);
		}
		catch(exception $e)
		{
			$status = "Could not connect to website.";
			die(json_encode(array("status" => $status, "error" => $e->getMessage())));
		}
		
		if(!empty($_REQUEST['delete_customer']))
		{
			$move = new CustomerMove($db, $wdb, $blab);
			
			try
			{
				$result = $move->delete_customer($_REQUEST['delete_customer']);
			}
			catch(mysqli_sql_exception $e)
			{
				die(json_encode(array("error" => "There are probably still some items ".
					"attached to this customer's account. You must deal with them ".
					"manually before you can delete this account.\n\n".$e->getMessage())));
			}
			
			$db->query("commit");
			
			die(json_encode(array("result" => $result)));
		}
	}
	
	
	/*
		Edit reminder notes for multiple items
	*/
	elseif(isset($_REQUEST['reminder_note']))
	{
		$items->call("reminder_note", array($_REQUEST['autonumbers'], $_REQUEST['reminder_note']));
		
		$db->query("commit");
		
		die(json_encode(array("ok" => 1)));
	}
	
	
	/*
		Update item
	*/
	elseif(!empty($_REQUEST['edit_item']))
	{
		$output = array();
		
		try
		{
			$wdb = new mysqli();
			$wdb->options(MYSQLI_OPT_CONNECT_TIMEOUT, 3);
			$wdb->real_connect(WEB_MYSQL_HOST, WEB_MYSQL_USER, WEB_MYSQL_PASS);
			$items = new ItemsWrite($db, $blab, $wdb);
		}
		catch(exception $e)
		{
			$output['status'] = "Could not connect to website.";
		}
		
		foreach($_REQUEST['item_data'] as $k => $v)
		{
			if(empty($v))
				$_REQUEST['item_data'][$k] = null;
		}
		
		$record = $items->call("edit", array($_REQUEST['edit_item'], $_REQUEST['item_data']));
		
		foreach($record as $k => $v)
		{
			if(is_null($v))
				$record[$k] = "";
		}
		
		$db->query("commit");
		
		$output['item'] = $record;
		$output['ok'] = 1;
		
		die(json_encode($output));
	}
	
	
	/*
		Mark Invoices Printed
	*/
	elseif(!empty($_REQUEST['mark_invoices_printed']))
	{
		$invoices = new Invoice($db, null, $blab);
		
		$invoices->mark_printed($_REQUEST['mark_invoices_printed']);
		
		$blab->blab($_REQUEST['user_name']." has marked invoices printed: ".implode(", ", $_REQUEST['mark_invoices_printed']));
		
		$db->query("commit");
		
		die(json_encode(array("ok" => 1)));
	}
	
	elseif(!empty($_REQUEST['extend_flat_rate_shipping']))
	{
		try
		{
			$wdb = new mysqli();
			$wdb->options(MYSQLI_OPT_CONNECT_TIMEOUT, 3);
			$wdb->real_connect(WEB_MYSQL_HOST, WEB_MYSQL_USER, WEB_MYSQL_PASS);
			$wdb->query("begin");
		}
		catch(exception $e)
		{
			$status = "Could not connect to website.";
			die(json_encode(array("status" => $status, "error" => $e->getMessage())));
		}
		
		$write->extend_flat_rate_shipping($_REQUEST['users_id'], $_REQUEST['user_name'], $_REQUEST['extend_flat_rate_shipping'], $wdb);
		
		$wdb->query("commit");
		
		die(json_encode(array("ok" => 1)));
	}
	
	elseif(!empty($_REQUEST['cancel_quote_request']))
	{
		try
		{
			$wdb = new mysqli();
			$wdb->options(MYSQLI_OPT_CONNECT_TIMEOUT, 3);
			$wdb->real_connect(WEB_MYSQL_HOST, WEB_MYSQL_USER, WEB_MYSQL_PASS);
			$wdb->query("begin");
		}
		catch(exception $e)
		{
			$status = "Could not connect to website.";
			die(json_encode(array("status" => $status, "error" => $e->getMessage())));
		}
		
		$affected_rows = $write->cancel_quote_request($_REQUEST['users_id'], $wdb);
		
		$wdb->query("commit");
		
		die(json_encode(array("ok" => 1, "affected_rows" => $affected_rows)));
	}
	
	elseif(!empty($_REQUEST['set_primary_address']))
	{
		$write->call("set_primary_address", array(
			$_REQUEST['customer_id'],
			$_REQUEST['address_id'],
		));
		
		$db->query("commit");
		
		die(json_encode(array("ok" => 1)));
	}
	
	elseif(!empty($_REQUEST['delete_other_email']))
	{
		$write->call("delete_other_email", array($_REQUEST['delete_other_email']));
		
		$db->query("commit");
		
		die(json_encode(array("ok" => 1)));
	}
	
	elseif(!empty($_REQUEST['update_other_email']))
	{
		$write->call("update_other_email", array($_REQUEST['update_other_email']));
		
		$db->query("commit");
		
		die(json_encode(array("ok" => 1)));
	}
	elseif(isset($_REQUEST['subscribe']))
	{
		if($_REQUEST['subscribe'] == 0)
		{
			require_once("/webroot/invoicing/backend/email.inc.php");
	  		$mail = new MailIndex();
	  		$mail->remove_subscription($_REQUEST['customers_id'], $_REQUEST['user_id']);
			die(json_encode(array("ok" => 1)));
		}
		else
		{
			require_once("/webroot/invoicing/backend/email.inc.php");
			$mail = new MailIndex();
			$mail->add_subscription($_REQUEST['customers_id'], $_REQUEST['user_id']);
			die(json_encode(array("ok" => 1)));
		}
	}
	elseif(!empty($_REQUEST['qpp_id']))
	{
		require_once("/webroot/includes/autoquote/autoquote.inc.php");
		
		$wdb = new mysqli();
		$wdb->options(MYSQLI_OPT_CONNECT_TIMEOUT, 3);
		$wdb->real_connect(WEB_MYSQL_HOST, WEB_MYSQL_USER, WEB_MYSQL_PASS);
		
		$autoquote = new Autoquote($db, $wdb, $blab);
		
		$package_id = $autoquote->create_quote_package($_REQUEST['qpp_id']);
		
		$result = $autoquote->run($package_id);
		
		$autoquote->update_quote($package_id, $result);
		
		$db->query("commit");
		die(json_encode(array("package_id" => "p$package_id")));
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
		email_error($e->__toString());
		echo json_encode(array("error" => $e->getMessage()));
	}
	else
	{
		email_error($e->__toString());
		echo json_encode(array("error" => $e->__toString()));
	}
}


function move_customer($from_email, $to_email, $move, $db)
{	
	$move->move_customer_email($from_email, $to_email);
	
	$db->query("commit");
	
	die(json_encode(array("success" => 1)));
}


function move_items($from_email, $to_email, $move, $db)
{
	list($from_id, $to_id, $result) = $move->change_email_of_items($from_email, $to_email);
	
	$errors = array();
	
	foreach($result as $k => $v)
	{
		if(!empty($v['error']))
			$errors[] = $v['message'];
	}
	
	if(count($errors))
		die(json_encode(array("error2" => "There was a problem you must correct first.\n".implode("\n", $errors), "result" => $result, "request" => $_REQUEST)));
	
	try
	{
		$move->delete_customer($from_id);
		$result['delete_customer'] = 1;
	}
	catch(exception $e)
	{
		$db->query("commit");
		die(json_encode(array("result" => $result, "error2" => "Customer merged successfully, but I was unable to delete the old customer record.\n\n".$e->getMessage())));
	}
	
	$db->query("commit");
	
	die(json_encode(array("result" => $result)));
}

?>