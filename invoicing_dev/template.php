<?php
//Template for miscellaneous database work

set_error_handler("exception_error_handler", E_ALL);
mysqli_report(MYSQLI_REPORT_STRICT | MYSQLI_REPORT_ERROR);
$db = new mysqli("localhost", "root", "k8!05Er-05", "invoicing");
$db->set_charset("utf8");
$wdb = new mysqli(WEB_MYSQL_HOST, WEB_MYSQL_USER, WEB_MYSQL_PASS);

require_once("/webroot/includes/string.inc.php");
require_once("/scripts/includes/blab.inc.php");

$blab = new Blab(null, Blab::NORMAL);

$db->query("begin");
$wdb->query("begin");

$r = $db->query("select ");

while($row = $r->fetch_assoc())
{
	
}

//$db->query("commit");

?>
