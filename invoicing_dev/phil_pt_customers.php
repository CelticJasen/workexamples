<?php
/*
	A template for writing
		- bulk database work
		- sanity-check scripts
*/
set_error_handler("exception_error_handler", E_ALL);

require_once("/webroot/includes/string.inc.php");
require_once("/scripts/includes/blab.inc.php");
require_once("/webroot/includes/photo_folders.inc.php");

mysql_connect("localhost", "root", "k8!05Er-05");
mysqli_report(MYSQLI_REPORT_STRICT | MYSQLI_REPORT_ERROR);
$db = new mysqli("localhost", "root", "k8!05Er-05", "listing_system");

/*
	Main program block
*/
$data = array();
$db->query("begin");

$r = $db->query(
	"select email, customer_id, name ".
	"from invoicing.customers ".
	"where notes_for_invoice like '%created automatically from peachtree%' ".
	"order by customer_id"
);

while($row = $r->fetch_assoc())
{
	$data[] = $row;
}

//$db->query("commit");
require_once("/webroot/includes/Mustache/Autoloader.php");
Mustache_Autoloader::register();
$mustache = new Mustache_Engine;

echo $mustache->render(file_get_contents("templates/html/customer_resultset.html"), $data);


?>
