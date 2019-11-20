<?php

set_error_handler("exception_error_handler", E_ALL);
try
{
	require_once("/webroot/includes/string.inc.php");
	require_once("/scripts/includes/blab.inc.php");
	require_once("/webroot/invoicing/backend/reminders.inc.php");

	$blab = new Blab(null, 0);;
	$db = new mysqli("localhost", "root", "k8!05Er-05", "listing_system");
	$reminders = new Reminders($db, $blab);

	$date = new DateTime();
	$date->sub(new DateInterval("P21D"));

	$list = $reminders->list($date);
	
	var_dump($list);
}
catch(exception $e)
{
	echo $e->__toString();
}
?>
