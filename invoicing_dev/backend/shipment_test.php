<?php
//Template for miscellaneous database work
require_once("/webroot/includes/string.inc.php");
set_error_handler("exception_error_handler", E_ALL);
mysqli_report(MYSQLI_REPORT_STRICT | MYSQLI_REPORT_ERROR);
$db = new mysqli("localhost", "root", "k8!05Er-05", "listing_system");
$wdb = new mysqli(WEB_MYSQL_HOST, WEB_MYSQL_USER, WEB_MYSQL_PASS);

$db->query("begin");
$wdb->query("begin");

require_once("shipments.inc.php");
require_once("/scripts/includes/blab.inc.php");

$blab = new Blab();

$s = new Shipments($db, $blab);

print_r($s->call("package_statuses_summary", array()));



//$db->query("commit");

?>
