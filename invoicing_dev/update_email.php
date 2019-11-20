<?php
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

$blab(print_r($_REQUEST, true));

usleep(100000);

if(!empty($_REQUEST['from_email']) && !empty($_REQUEST['to_email']))
{
	if(!empty($_REQUEST['run']))
	{
		$db->query("update listing_system.tbl_Current_Consignments set ".
			"`High Bidder email` = '".$db->escape_string($_REQUEST['to_email'])."' ".
			"where `High Bidder email` = '".$db->escape_string($_REQUEST['from_email'])."'");	
		
		$rows = $db->affected_rows;
		
		$db->query("commit");
		
		die(json_encode(array("success" => "$rows rows affected.")));
	}
	else
	{
		$r = $db->query("select count(*) from listing_system.tbl_Current_Consignments ".
			"where `High Bidder email` = '".$db->escape_string($_REQUEST['from_email'])."'");
			
		list($count) = $r->fetch_row();
		
		$r = $db->query("select group_concat(distinct `High Bidder ID` separator ', ') from listing_system.tbl_Current_Consignments ".
			"where `High Bidder email` = '".$db->escape_string($_REQUEST['from_email'])."'");
			
		list($usernames) = $r->fetch_row();
		
		die(json_encode(array("confirm" => "You are about to update $count Consignments records from ".
			"email '$_REQUEST[from_email]' to '$_REQUEST[to_email]'. <br />".
			"Username(s): '$usernames'. <br /><br />Continue?")));
	}
}


?>