<?PHP
require_once("includes.inc.php");
permissions_required(PERM_ISINVOICER);

if(!empty($_REQUEST['from']))
{
	/*
		Total Balance Dues
	*/
	$query = "select invoices.* from invoicing.invoices join customers using(customer_id) ".
		"where ".
		"date_of_invoice >= '$_REQUEST[from]' ";
	
	if(!empty($_REQUEST['to']))
		$query .= "and date_of_invoice <= '$_REQUEST[to]' ";
	
	$query .= "and payment_method = 'balance due' ";
	
	$query .= "and customers.customers_id != '23468' ";
	
	$r = $db->query($query." order by date_of_invoice");
	
	$invoices = $notes = array();
	
	$total = $items = $balance_due = $uninvoiced = 0;
	
	while($row = $r->fetch_assoc())
	{
		if(preg_match("/balance *due *\\$? *([0-9,]*(?:\.[0-9]*))/i", $row['extra_info'], $match))
		{			
			$invoices[] = $row['invoice_number'];
			
			$balance_due += intval(str_replace(",", "", $match[1]));
			
			$total += intval(str_replace(",", "", $match[1]));
			
			if(preg_match("/paid/i", $row['extra_info']))
			{
				$notes[] = "Invoice <a target='_blank' href='http://poster-server/invoicing/single_invoice.php?invoice_number=$row[invoice_number]'>".
					"#$row[invoice_number]</a>; shipping_method = 'balance due' but extra_info contains 'paid'";
			}
		}
		else
		{
			$notes[] = "Invoice <a target='_blank' href='http://poster-server/invoicing/single_invoice.php?invoice_number=$row[invoice_number]'>".
				"#$row[invoice_number]</a>; shipping_method = 'balance due' but not correctly recorded in extra_info.";
		}
	}
	
	
	/*
		Total uninvoiced items
	*/
	$query = "select sales.* from invoicing.sales join customers on ebay_email = email ".
		"where customers.customers_id != '23468' and invoice_printed = '0' and invoice_number is null and ".
		"`date` >= '$_REQUEST[from]' and price > '0' ";
	
	if(!empty($_REQUEST['to']))
		$query .= "and `date` <= '$_REQUEST[to]' ";
	
	$r = $db->query($query);
	
	while($row = $r->fetch_assoc())
	{
		$uninvoiced += $row['price'] * $row['quantity'];
		$total += $row['price'] * $row['quantity'];
		$items++;
	}
	
	
	/*
		Final output
	*/
	echo json_encode(array(
		"invoices" => $invoices, 
		"total" => number_format($total), 
		"balance_dues" => number_format($balance_due),
		"uninvoiced" => number_format($uninvoiced),
		"notes" => $notes, "items" => $items, 
		"from" => $_REQUEST['from'], "to" => $_REQUEST['to']));
	
	die();
}
?>
<html>
<head>
	<title>Accounts Receivable Calculator</title>
	<link rel='stylesheet' href='/style/style.css' />
	<link rel='stylesheet' href='style.css' />
	<link rel="stylesheet" href="/includes/jquery-ui-1.10.3/css/ui-lightness/jquery-ui-1.10.3.custom.css" />
	<style type="text/css">
		 .ui-autocomplete {
			max-height: 500px;
			overflow-y: auto;
			/* prevent horizontal scrollbar */
			overflow-x: hidden;
			font-size: 9pt;
		}
	
	</style>
	<style type='text/css'>
	</style>
	<script type='text/javascript' src='/includes/jquery-ui-1.10.3/js/jquery-1.9.1.js'></script>
	<script type='text/javascript' src='/includes/jquery-ui-1.10.3/js/jquery-ui-1.10.3.custom.js'></script>
	<script type='text/javascript'>
		$(document).ready(function(){
			$("[name=from]")
				.datepicker({dateFormat: "yy-mm-dd"})
				.change(function(){
					request()
				})
			
			$("[name=to]")
				.datepicker({dateFormat: "yy-mm-dd"})
				.change(function(){
					request()
				})
				
			request()
		})
		
		function request()
		{
			if($("[name=from]").val() == "")
			{
				alert("'From' must be set.")
				return false
			}
			
			$.ajax({
				type: "post",
				dataType: "json",
				data: "from="+encodeURIComponent($("[name=from]").val())+"&to="+encodeURIComponent($("[name=to]").val()),
				success: function(data){
					$("#results")
						.empty()
						.append(
							$("<div />")
								.css({
									marginBottom: "10px",
									fontSize: "15pt",
								})
								.html("Approximate Accounts Receivable From '"+data.from+"' To '"+data.to+"'")
						)
						.append(
							$("<div />")
								.html("Uninvoiced Items")
						)
						.append(
							$("<input />")
								.attr({
									"readonly": true
								})
								.css("fontSize", "20pt")
								.val("$"+data.uninvoiced)
						)
						.append(
							$("<div />")
								.html("'Balance Due' Invoices")
						)
						.append(
							$("<input />")
								.attr({
									"readonly": true
								})
								.css("fontSize", "20pt")
								.val("$"+data.balance_dues)
						)
						.append(
							$("<div />")
								.html("Total")
						)
						.append(
							$("<input />")
								.attr({
									"readonly": true
								})
								.css("fontSize", "20pt")
								.val("$"+data.total)
						)
						.append(
							$("<div />")
								.css("marginTop", "10px")
								.html("'Balance Due' Invoices")
						)
					
					invoices = $("<div />")
					
					for(x in data.invoices)
					{
						$(invoices)
							.append(
								$("<a />")
									.attr({
										target: "_blank",
										href: "http://poster-server/invoicing/single_invoice.php?invoice_number="+data.invoices[x],
									})
									.html(data.invoices[x])
							)
						
						if(x < data.invoices.length-1)
						{
							$(invoices)
								.append(", ")
						}
					}
					
					$("#results")
						.append(
							invoices
						)
					
					if(data.notes.length)
					{
						$("#results")
							.append(
								$("<div />")
									.css("marginTop", "10px")
									.html("Notes")
							)
							.append(
								$("<div />")
									.html(data.notes.join("<br />"))
							)
					}
				}
			})
		}
	</script>
</head>
<body>
<?PHP
#include ("../includes/subnav.inc.php");

/* Code for related menu
<div id='lognavigation'>
<div>Related:</div>
<ul class='listmenu'>
<li><a href='/consignors/'>Edit Consignors</a></li>
</ul>
</div>
*/
?>

<?
include("../includes/status.html");
?>

<p style='width: 700px'>
This calculator takes a date range and calculates totals for uninvoiced items and 
Balance Due invoices inside that range. Anything for "Partners Publishers Group, Inc" is 
excluded from these totals. These totals are approximate because mistakes
in recordkeeping can cause large variations from what is shown here. The notes 
section shows any recordkeeping mistakes found.
</p>

<table>
	<tr><td>From</td><td><input name='from' value='<?=date("Y-01-01")?>' /></td></tr>
	<tr><td>To</td><td><input name='to' /></td></tr>
</table>

<div id='results' style='margin: 10px'>

</div>

</body>
</html>