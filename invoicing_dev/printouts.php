<?PHP
require_once("/webroot/auth/auth.php");
require_once("/scripts/includes/blab.inc.php");
require_once("/scripts/timeclock.inc.php");

mysqli_report(MYSQLI_REPORT_STRICT | MYSQLI_REPORT_ERROR);
$db = new mysqli("localhost", "root", "k8!05Er-05", "invoicing");
$db->set_charset("utf8");
require_once("/webroot/includes/record_locking_mysqli.inc.php");
//RecordLock::release_all("invoicing", $db, $user->getId());



function pp($arr){
    $retStr = '<ul>';
    if (is_array($arr)){
        foreach ($arr as $key=>$val){
            if (is_array($val)){
                $retStr .= '<li><b>' . $key . '</b> : ' . pp($val) . '</li>';
            }else{
                $retStr .= '<li><b>' . $key . '</b> : ' . $val . '</li>';
            }
        }
    }
    $retStr .= '</ul>';
    return $retStr;
}

/*
TODO:

Integrate with Wiki
*/
?>
<html>
<head>
	<title>Invoicing</title>
	<link rel='stylesheet' href='card_20160629.css' />
	<link rel='stylesheet' href='typeahead.css' />
	
	<link rel='stylesheet' href='/style/style.css' />
	<link rel='stylesheet' href='style20121211.css' />
	<link rel="stylesheet" href="/includes/jquery-ui-1.10.3/css/ui-lightness/jquery-ui-1.10.3.custom.css" />
	<link rel="stylesheet" media='print' href='print.css' />
	<link href='http://fonts.googleapis.com/css?family=Open+Sans:600,400|Raleway:400,600' rel='stylesheet' type='text/css'>
	<script type='text/javascript' src='/includes/jquery-ui-1.10.3/js/jquery-1.9.1.js'></script>
	<script type='text/javascript' src='/includes/jquery-ui-1.10.3/js/jquery-ui-1.10.3.custom.js'></script>
	<!--<script type='text/javascript' src='/includes/jquery-ui-1.10.3/js/jquery-ui-position.js'></script>-->
	<script type='text/javascript' src='/includes/jquery/jquery.typeahead.js'></script>
	<script type='text/javascript' src='/includes/jquery/bloodhound.js'></script>
	<script type='text/javascript' src='/includes/jquery/nextindom.jquery.js'></script>
	<script type='text/javascript' src="https://maps.googleapis.com/maps/api/js?v=3.exp"></script>
	<script type='text/javascript' src='functions.js'></script>
	<script type='text/javascript' src='/includes/mustache.js'></script>
	<script type='text/javascript' src='/includes/wiki.js'></script> <!--TODO: Use this-->
	<script type='text/javascript' src='/includes/shortcut_keys.js'></script> <!--TODO: Document this-->
	<script type='text/javascript' src='/includes/jquery/readmore.js'></script>
	<script type='text/javascript' src='/includes/jquery/ajaxq.jquery.js'></script>
</head>
<body style='background: white'>
<?PHP
//include ("/webroot/includes/status.html");
Auth::status_bar($user);
?>
<div class='noprint' id='lognavigation'>
	<div>Related:</div>
	<ul class="listmenu">
		<li><a target='_blank' id='bidder_account_admin' href="/website_tools/user_account_admin.php">Bidder Account Admin</a></li>
		<li><a target='_blank' id='auction_anything' style='display: none' href="http://auctions.emovieposter.com/AdminMemberProfile.taf?_function=detail">Auction Anything Profile</a></li>
		<li><a target='_blank' id='quote_request_printout' href="https://www.emovieposter.com/secure/test_tools/quote_printout.php">Quote Request Printout Tool</a></li>
		<li><a target='_blank' id='shipping_quotes' href="/shipping_quotes/">Shipping Quotes</a></li>
		<li><a target='_blank' id='print_invoices' href="http://poster-server/invoicing/print_invoices.php">Print Invoices</a></li>
		<li><a target='_blank' id='mo_sales_tax' href="http://poster-server/tools/salestax.php">MO Sales Tax Calculator</a></li>
		<li><a target="_blank" id='whos_here' href="http://poster-server/tools/who_is_here.php">Who's Here</a></li>
	</ul>
</div>
<?
if(!empty($_GET['id']))
{
	$r = $db->query("SELECT printout, `name`, `ts` ".
		"FROM invoicing.phone_order_printouts ".
		"left join `poster-server`.`users` on `who` = `users`.`id` ".
		"WHERE phone_order_printouts.id = '$_GET[id]'");
		
	if($r->num_rows)
	{
		list($printout, $who, $timestamp) = $r->fetch_row();
		
		echo "<div class='noprint' style='margin-bottom: 10px'><strong>Printed on </strong>$timestamp ";
		
		if($who)
			echo "<strong>by</strong> $who";
		
		echo "</div>";
		
		echo $printout;
	}
	else
		echo "<h1>No such printout</h1>";
}

if(!empty($_GET['website_order']))
{
	require_once("backend/secure_message_opener.inc.php");
	$opener = new Secure_Message_Opener();
	$r = $db->query("select data, `key`, data2, key2, id, email from invoicing.website_orders where id = '$_GET[website_order]'");
	list($data, $key, $data2, $key2, $id, $email) = $r->fetch_row();
	
	if(!empty($data))
	{
		$unserialized = $opener->open($data, $key);
		echo pp($unserialized);
	}
	
	if(!empty($data2))
	{
		$unserialized2 = $opener->open($data2, $key2);
		echo pp($unserialized2);
	}
	
	
}


?>
</body>
</html>
