<?php
header("Content-Type: text/html; charset=utf-8");
ob_start();
require_once("/webroot/auth/auth.php");
$db = new mysqli("localhost", "office", "never eat shredded wheat", "invoicing");
$db->set_charset("utf8");
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

//require_once("../auth.inc.php");
require_once("/webroot/invoicing/backend/tracking.inc.php");
require_once("/webroot/invoicing/backend/customer.inc.php");

if(!empty($_REQUEST['test']))
	require_once("/webroot/sync_website/generate_invoice_dev.inc.php");
else
	require_once("/webroot/sync_website/generate_invoice.inc.php");

Auth::require_permissions(PERM_ACCESS_INVOICING, $user);


$db->query(sprintf("set @userId = '%s'", $db->escape_string($user->getName())));

if(!empty($_GET['customer']))
{
	$mysql = mysql_connect2("localhost", "office", "never eat shredded wheat");
	list($invoice, $email, $subinvoices, $customer) = generate_shipping_invoice($_REQUEST['invoice_number'], $mysql);
}
else
{
	list($invoice, $email, $subinvoices, $customer) = generate_invoice_plus_subinvoices_for_office($_REQUEST['invoice_number'], $db, array(), false);
}

if(empty($_GET['recipient']))
	ob_end_flush();
else
{
	//TODO: we need a log of when who sent what to who
	
	$body = file_get_contents("/scripts/sync_website/invoice.css")."\n".$invoice;
	
	Mailer::mail($_GET['recipient'], "eMoviePoster.com Invoice #".$_REQUEST['invoice_number'], $body, array("content-type" => "text/html", "From" => "shippingdept@emovieposter.com"));
	
	echo json_encode(array("done" => "1"));
	
	die();
}

?>
<html>
<head>
	<?
		if(!empty($_REQUEST['invoice_number']))
			echo "<title>Invoice #$_REQUEST[invoice_number]</title>\n";
		else
			echo "<title>View Invoice</title>\n";
	?>
	<link rel='stylesheet' href='/style/style.css' />
	<link rel="stylesheet" href="/includes/jquery-ui-1.10.3/css/no-theme/jquery-ui-1.10.3.custom.css" />
	<style type="text/css">
		 .ui-autocomplete {
			max-height: 500px;
			overflow-y: auto;
			/* prevent horizontal scrollbar */
			overflow-x: hidden;
			font-size: 9pt;
		}
		
		a.item_number
		{
			text-decoration: none;
			color: black;
		}
	</style>
	<style type='text/css' media='print'>
		.noprint, .noprint2 /*noprint2 is just for single_invoice.php to hide some additional stuff*/
		{
			display: none;
		}
		
		a
		{
			text-decoration: none;
			color: black;
		}
	</style>
	<script type="text/javascript" src='/includes/jquery/jquery.js'></script>
	<script type='text/javascript' src='/includes/jquery-ui-1.10.3/js/jquery-1.9.1.js'></script>
	<script type='text/javascript' src='/includes/jquery-ui-1.10.3/js/jquery-ui-1.10.3.custom.js'></script>
	<script type='text/javascript'>
		function send_to_customer(invoice_number)
		{
			email = $("#email_address").val()
			
			if(email.match(/^[^@]+@[^@]+$/))
			{
				if(confirm("You are about to send this invoice to "+email+"."))
				{
					$("#status").append("<img src='/includes/graphics/indicator.gif' />")
					$.ajax({
						type: "get",
						data: "invoice_number="+invoice_number+"&recipient="+encodeURIComponent(email),
						dataType: "json",
						success: function(data){
							if("error" in data)
								alert(data.error)
							$("#status").empty().append("<span style='color:green'>Sent.</span>")
						}
						
					})
				}
			}
			else
			{
				alert("'"+email+"' is not a valid email address")
			}
		}
		
		$(window).load(function(){
<?
	if(!empty($_REQUEST['print']))
			echo "window.print();\n";
?>
			
			//$("#search", window.parent.document).val("").focus()
		})
	</script>
</head>
<body>

<span class='noprint'>
<?PHP
include ("../includes/subnav.inc.php");
?>

<div id='lognavigation'>
<div>Related:</div>
<ul class='listmenu'>
<li><a href="/invoicing/?customer_id=<?=$customer['customer_id']?>">Invoicing (<?=$customer['customer_id']?>)</a></li>
</ul>
</div>



<?
//include ("../includes/status.html");
Auth::status_bar($user);


/*
	Find who created the invoice
*/
$r = $db->query("select full_name ".
	"from invoicing.audit_log_important ".
	"join `poster-server`.users on `user` = `users`.id ".
	"where `table` = 'invoices' and `operation` = 'insert' and ".
	"`key` = '".$db->escape_string($_REQUEST['invoice_number'])."'");

if($r->num_rows)
{
	list($who_did) = $r->fetch_row();
	echo "<div style='margin: 10px; '>Invoice #".$_REQUEST['invoice_number']." created by $who_did</div>";
}


echo "<div style='margin: 15px 5px 15px 5px'>\n";


/*
	View Customer Copy | View Office Copy
*/
echo "<p>";
if(empty($_GET['customer']))
{
	echo "<a href='".$_SERVER['PHP_SELF']."?invoice_number=".$_GET['invoice_number']."&customer=y'>View Customer Copy</a>";
	
}
else
{
	echo "<a href='".$_SERVER['PHP_SELF']."?invoice_number=".$_GET['invoice_number']."'>View Office Copy</a> | ";
	echo "<button onclick='send_to_customer(".$_GET['invoice_number'].")'>Send To Customer</button> ".
		"<span id='status' style='width: 20px'></span> ".
		"<input id='email_address' size='40' value=\"".htmlspecialchars($email)."\" />";
	
}

echo '<br /><br />';
echo '<a href=\'/audit_log/?criteria=[["table","=","invoices"],["id","=","'.$_GET['invoice_number'].'"],["ts",">","2014-01-01"]]&database=invoicing\'><img src=\'/includes/graphics/log.png\' /></a>';







/*
	Extra Info
*/
if(empty($_GET['customer']))
{
	/*
		Card charges completed | Card not yet charged
	*/
	$r = $db->query("select cc_notes, cc_date_processed, payment_method ".
		"from invoices ".
		"where invoice_number = '".$db->escape_string($_REQUEST['invoice_number'])."'");
	list($cc_notes, $date_processed, $payment_method) = $r->fetch_row();
	
	echo "<img style='margin-left: 15px' src='/includes/graphics/payment/".Invoice::get_payment_icon($payment_method)."' /><br /><br />";
	
	switch(invoice_charged($_REQUEST['invoice_number'], $db))
	{
		case 0:
			echo "<h3 style='color: tomato'>Card not yet charged</h3>";
			$charged = false;
			break;
		case 2:
			echo "<h3 style='color: darkgreen'>Card charges completed</h3>";
			$charged = true;
			break;
	}
	
	if(!empty($date_processed))
		echo "<h3 style='color: darkgreen'>Processed ".date("n/j/Y", strtotime($date_processed))."</h3>";
	
	if(!empty($cc_notes))
		echo "<h3 style='color: ".($charged ? "darkgreen" : "tomato")."'>".htmlspecialchars($cc_notes)."</h3>";
	
	
	/*
		Charges history
	*/
	$r = $db->query("select sum(`status` = 'success'), sum(`status` != 'success'), date_format(max(`timestamp`), '%c/%d/%Y') ".
		"from invoicing.cc_log ".
		"where invoice_number = '".$db->escape_string($_REQUEST['invoice_number'])."'");
		
	list($success, $failure, $last) = $r->fetch_row();
	
	if($success > 0 || $failure > 0)
	{
		echo "<h3 style='color: ".($success == 0 ? "tomato" : "darkgreen")."'>";
		
		if($failure > 0)
		{
			echo "Failed $failure time".($failure > 1 ? "s" : "").". ";
		}
		
		if($success > 0)
		{
			$r = $db->query("select date_format(`timestamp`, '%c/%d/%Y') from invoicing.cc_log ".
				"where `status` = 'success' and ".
				"invoice_number = '".$db->escape_string($_REQUEST['invoice_number'])."'");
			
			list($timestamp) = $r->fetch_row();
			
			echo "Charge succeeded on $timestamp. ";
		}
		else
		{
			echo "Last failed charge was on $last. ";
		}
		
		echo "</h3>";
	}
	
	/*
		Tracking information
	*/
	$r = $db->query(sprintf("select *, (ship_type like '%%fedex%%') as 'fedex' ".
		"from invoicing.tbl_tracking_numbers ".
		"where invoice_num = '%s' ".
		"order by package_id, pull_sheet_id", $db->escape_string($_REQUEST['invoice_number'])));
	
	if($r->num_rows)
	{
		require_once("/webroot/invoicing/backend/shipments.inc.php");
		
		echo "<table cellpadding='6' cellspacing='0' border='1' style='border-collapse: collapse; float: left; margin-right: 10px; margin-bottom: 10px;' >";
		echo "<tr><th>Items</th><th>Tracking</th><th>Ship Date</th><th>Service</th><th>Addons</th><th>Insurance Purchased</th><th>Declared Value</th><th>Postage Paid</th><th>Weight</th><th>Sent To</th></tr>\n";

		while($row = $r->fetch_assoc())
		{
			if(!empty($row['xml']))
			{
				$obj = @simplexml_load_string("<xml>$row[xml]</xml>");
				
				if($obj->Services)
				{
					$services = (array) $obj->Services;
					$services = $services["@attributes"];
					
					$new_services = array();
					foreach($services as $k => $v)
					{
						if($v == "ON" && $k != "USPSTracking")
							$new_services[] = $k;
					}
				}
				
				$registered = $obj->Services['RegisteredMail'] != "OFF";
				
				list($row['endicia'], $addressee) = Shipments::parse_endicia_xml($row['xml']);
				$addressee = Shipments::format_endicia_address($addressee);
				
				if($obj->ActualWeightText)
				{
					$actual_weight = $obj->ActualWeightText;
				}
				else
				{
					$actual_weight = "";
				}
			}
			else
			{
				unset($services, $new_services);
				unset($actual_weight);
				unset($addressee);
				$registered = false;
			}
			
			if(is_null($row['insurance_purchased']))
				$insurance_column = "Unknown";
			else
				$insurance_column = "$".number_format($row['insurance_purchased'], 2);
				
			if(is_null($row['declared_value']))
				$declared_value = "Unknown";
			else
				$declared_value = "$".number_format($row['declared_value'], 2);
			
			list($url, $tracking_number) = Tracking::url($row['tracking_num'], $row['fedex']);
			$tracking_link = "<a href='$url' target='_blank'>$tracking_number</a>";
			
			if(!empty($row['alt_tracking_num']) && $row['alt_tracking_num'] != $row['tracking_num'])
			{
				list($url, $tracking_number) = Tracking::url($row['alt_tracking_num'], $row['fedex']);
				$tracking_link .= " | <a href='$url' target='_blank'>$tracking_number</a>";
			}
			
			if(!empty($row['package_id']))
				$id = "Package #".$row['package_id'];
			elseif(!empty($row['pull_sheet_id']))
				$id = "Pull Sheet #".$row['pull_sheet_id'];
			else
				$id = "Invoice #".$row['invoice_num'];
				
			
			printf("<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td>\n", 
				$id,
				$tracking_link,
				date("m/d/Y H:i:s", strtotime($row['ship_date'])),
				$row['ship_type'].($registered ? " <small>(reg)</small>" : ""),
				"<span style='font-size: 7pt'>".implode("<br />", $new_services)."</span>",
				
				$insurance_column, $declared_value, "$".number_format($row['postage'] + $row['insurance_fee'], 2), 
				$actual_weight);
				
			echo "<td><div style='font-size: 7pt; line-height: 100%'>".$addressee."</div></td>\n";
				
			echo "</tr>";
		}

		echo "</table>";
	}
	else
	{
		echo "<h3>No Tracking Information Available</h3>";
	}


	echo "<br />";
}
echo "</p>";



/*
	Extra information about packages
*/
$r = $db->query("select quotes_packaging.`type`, first_item, pounds, ounces, value, count(package_id), package_id from invoicing.sales ".
	"join invoicing.quotes_packages using(package_id) ".
	"join invoicing.quotes_packaging using(packaging_id) ".
	"where invoice_number = '".$db->escape_string($_REQUEST['invoice_number'])."' ".
	"group by package_id order by package_id");

if($r->num_rows)
{
	echo "<table cellpadding='4' cellspacing='0' border='1' style='border-collapse: collapse; float: left; margin-bottom: 10px; '>";
	echo "<tr><th>Pkg Id</th><th>Type</th><th>1st item</th><th>Weight</th><th>Items</th><th>Value</th></tr>\n";
	while(list($type, $item, $pounds, $ounces, $value, $items, $package_id) = $r->fetch_row())
	{
		echo "<tr><td><a href='/shipping_quotes/?customer=p$package_id'>$package_id</a></td><td>$type</td><td>$item</td><td>$pounds lb $ounces oz</td><td>$items</td><td>$".number_format($value, 2)."</td></tr>\n";
	}
	echo "</table>";
}

echo "<br style='clear: both' />";
echo "</div>\n";




/*
	Display the invoice
*/
$r = $db->query(sprintf("select invoice_number from invoicing.sales where reference = '%s'", $db->escape_string($_GET['invoice_number'])));
if($r->num_rows)
{
	list($invoice_number) = $r->fetch_row();
	
	echo "<b>Parent Invoice: <a href='?invoice_number=$invoice_number'>#$invoice_number</a></b><br />";
}
	
$r = $db->query(sprintf("select reference from invoicing.sales where invoice_number = '%s' and reference is not null", $db->escape_string($_GET['invoice_number'])));
while(list($child_invoice) = $r->fetch_row())
{
	printf("<b>Child Invoice: <a href='?invoice_number=%s'>#%s</a></b><br />", $child_invoice, $child_invoice);
}



?>
</span>
<?
if(empty($_GET['customer']))
	echo file_get_contents("/scripts/sync_website/invoice_for_office.css");
else
	echo file_get_contents("/scripts/sync_website/invoice.css");

echo $invoice;
?>





</body>
</html>
