<?php
header("Content-Type: text/html; charset=utf-8");

require_once("/webroot/auth/auth.php");

session_write_close();

header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // Date in the past

//permissions_required(PERM_DESCRIPTIONS);
mysqli_report(MYSQLI_REPORT_STRICT | MYSQLI_REPORT_ERROR);

$db = new mysqli("localhost", "office", "never eat shredded wheat", "invoicing");

$db->query(sprintf("set @userId = '%s'", $db->escape_string($user->getName())));

unset($result);

require_once("backend/items.inc.php");
require_once("backend/customer.inc.php");

function fixed_price_items($invoice_number, $db)
{
	$r = $db->query("select sales.*, type_code, how_stored, `00-00-00 BINs (BINs)`.film_title ".
		"from sales ".
		"join fixed_price_sales on (item_number = ebay_item_number) ".
		"left join listing_system.`00-00-00 BINs (BINs)` on (substr(ebay_title, 1, 4) = lot_number) ".
		"where invoice_number = '".$db->escape_string($invoice_number)."' ".
		"group by sales.autonumber");
	
	$records = array();
	
	while($row = $r->fetch_assoc())
	{
		$records[] = $row;
	}
	
	usort($records, "fixed_price_sort");
	
	return $records;
}

function fixed_price_sort($a, $b)
{
	if($a['type_code'] != $b['type_code'])
	{
		return strcmp($a['type_code'], $b['type_code']);
	}
	
	if(substr($a['ebay_title'], 4, 2) != substr($b['ebay_title'], 4, 2))
	{
		return strcmp(substr($a['ebay_title'], 4, 2), substr($b['ebay_title'], 4, 2));
	}
	
	if($a['film_title'] != $b['film_title'])
	{
		return strcmp($a['film_title'], $b['film_title']);
	}
	
	return strcmp($a['ebay_item_number'], $b['ebay_item_number']);
}

?>
<html>
<head>
<title>Print Invoices</title>
<link rel="stylesheet" media='print' href='/invoicing/print.css' />
<style>

.pagedot
{
	position: absolute;
	top: 0px;
	right: 0px;
}

div.root
{
	width: 7in;
	font-family: Verdana, sans-serif;
	position: relative;
	page-break-after: always;
}

div.notes
{
	display: inline-block;
	max-width: 300px; 
	margin: 10px; 
	border: 1px solid gray; 
	padding: 5px;
	vertical-align: top;
}

div.notes div.number
{
	text-align: center;
	margin-bottom: 5px;
	font-size: 8pt;
}

div.notes span.extra_info
{
	font-size: 10pt;
	white-space: pre-wrap;
}


table.bins td
{
	overflow: hidden;
	text-overflow: ellipsis;
}

table.bins tr.gray
{
	background: rgb(230,230,230);
}

</style>
<script type="text/javascript" src="/includes/jquery/jquery.js"></script>
<script type="text/javascript" src="/includes/PrinterSettings.js"></script>
<script type="text/javascript">
$(window).load(function(){
      setPrinterSettings({
        'marginTop' : 12.7,
        'marginBottom' : 12.7,
        'marginLeft' : 12.7,
        'marginRight' : 12.7,
        'headerStrLeft' : '',
        'headerStrCenter' : '',
        'headerStrRight' : '',
        'footerStrLeft' : '',
        'footerStrCenter' : '',
        'footerStrRight' : '',
        'scaling' : 100,
        'shrinkToFit' : 0,
        'printBGColors' : 1,
        'printBGImages' : 1
      });
	  
	window.print()
})
</script>
</head>
<body>

<?php

require_once("/scripts/sync_website/generate_invoice.inc.php"); //TODO: Un-dev this

$items = new ItemsWrite($db, null);

if(!empty($_GET['invoice_numbers']))
{
	$r = $db->query("select invoice_number, sum(price * quantity) as subtotal, ship_country, shipping_method, invoices.pay_and_hold, payment_method, extra_info ".
		"from invoices ".
		"left join sales using(invoice_number) ".
		"join customers on(invoices.customer_id = customers.customer_id) ".
		"where invoice_number in ($_GET[invoice_numbers]) ".
		"group by invoice_number ".
		"order by invoice_number ");
}
elseif(!empty($_GET['a']))
{
	$r = $db->query("select invoice_number, sum(price * quantity) as subtotal, ship_country, shipping_method, invoices.pay_and_hold, payment_method, extra_info ".
		"from invoices ".
		"left join sales using(invoice_number) ".
		"join customers on(invoices.customer_id = customers.customer_id) ".
		"where date_of_invoice >= date(now()) and who_did = 'Terri' ".
		"group by invoice_number ".
		"order by invoice_number ");
}
elseif(!empty($_GET['b']))
{
	$r = $db->query("select invoice_number, sum(price * quantity) as subtotal, ship_country, shipping_method, invoices.pay_and_hold, payment_method, extra_info ".
		"from invoices ".
		"left join sales using(invoice_number) ".
		"join customers on(invoices.customer_id = customers.customer_id) ".
		"where date_of_invoice >= date(now()) and who_did = 'Terri' ".
		"group by invoice_number ".
		"order by invoice_number ");
}
else
{
	$r = $db->query("select invoice_number, sum(price * quantity) as subtotal, ship_country, shipping_method, invoices.pay_and_hold, payment_method, extra_info ".
		"from invoices ".
		"left join sales using(invoice_number) ".
		"join customers on(invoices.customer_id = customers.customer_id) ".
		"where invoices.invoice_printed = '0' ".
		"group by invoice_number ".
		"order by invoice_number ");
}
	
//$r = $db->query("select invoice_number from invoices join aaron_audit_log on invoice_number = `key` order by invoice_number");	

echo "<div class='noprint' style='border-bottom: 4px double black; margin-bottom: 30px;'>";

echo "<p>".$r->num_rows." invoices to print.</p>";
echo "<p>";
while($row = $r->fetch_assoc())
{
	echo $row['invoice_number']." &nbsp;";
}
echo "</p>";

$r->data_seek(0);

while($row = $r->fetch_assoc())
{
	$warnings = $items->validate_invoice($row['invoice_number']);
	
	if(!empty($warnings))
	{
		echo "<div class='notes' style='color: tomato; border: 1px solid tomato;' ><div class='number'><a href='/invoicing/#customer_id=$row[invoice_number]&tab=items_tab' target='_blank'>$row[invoice_number]</a></div>".
			"<span class='extra_info'>".implode("\r\n", $warnings)."</span></div>";
	}
}


$r->data_seek(0);

while($row = $r->fetch_assoc())
{
	if(!empty($row['extra_info']) && !preg_match("/^(\(use card on file\)\s*)?(P=[0-9A-Z]{17};|Use card [0-9]{4})$/", $row['extra_info']))
	{
		echo "<div class='notes'><div class='number'><a href='/invoicing/#customer_id=$row[invoice_number]&tab=items_tab' target='_blank'>$row[invoice_number]</a></div>".
			"<span class='extra_info'>".htmlspecialchars($row['extra_info'])."</span></div>";
	}
}

echo "</div>";



try
{
	ob_start();
	$r->data_seek(0);
	$first = false;
	
	while($row = $r->fetch_assoc())
	{
		list($item_numbers, $items_total) = total_ph_items($row['invoice_number'], $db);
		
		list($main_invoice) = generate_hybrid_invoice($row['invoice_number'], $db, false);
		
		list($invoices, $email, $invoice_numbers_done, $css) = generate_invoice_plus_subinvoices_for_office_array($row['invoice_number'], $db, $invoice_numbers);
		
		list($shipping_invoice) = generate_hybrid_invoice($row['invoice_number'], $db, true);
		
		$fixed_price_items = fixed_price_items($row['invoice_number'], $db);
		
		if($first == false)
		{
			echo $css;
			$first = true;
		}
		
		if($row['pay_and_hold'] != 0)
		{
			$copies = 1;
		}
		elseif($row['ship_country'] == "CA")
		{
			/*if($items_total < 200)
			{
				$copies = 2;
			}
			else
			{
				$copies = 5;
			}*/
			
			$copies = 5;
		}
		elseif($row['ship_country'] != "US")
		{
			if(preg_match("/(^| )insured/i", $row['shipping_method']))
			{
				/*if($items_total < 200)
				{
					$copies = 2;
				}
				else
				{
					$copies = 5;
				}*/
				
				$copies = 5;
			}
			else
			{
				$copies = 1;
			}
		}
		else
		{
			$copies = 1;
		}
		
		
		if($row['pay_and_hold'] != 0 && count($fixed_price_items))
		{
			/*
				Per Phil, when there is a fixed price item on a pay and hold
				invoice, we need one extra copy to give to Eric.
				
				AK 2014-01-15
			*/
			$copies++;
		}
		
		
		//Consignment Proceeds invoices - print an extra copy for Angie.
		if(strtolower($row['payment_method']) == "consignment proceeds" || stripos($row['payment_method'], "proceeds") === 0)
			$copies++;	
		
		if(strtolower($row['payment_method']) == "invoice to bruce")
			$copies++;
		
		if(!empty($_GET['a']))
			$copies = 1;
		
		/*if($copies > 1 && $separator == false)
		{
			echo "<div class='noprint'>separator page<hr /></div><div style='page-break-after: always'>&nbsp;</div>";
		}*/
		
		
		
		for($x = 0; $x < $copies; $x++)
		{
			/*echo "<div class='root' style='position: relative'>\n";
			echo $main_invoice;
			echo "</div>\n";*/
				
			if($x == 0)
			{
				echo "<div class='root' style='position: relative'>\n";
				echo $main_invoice;
				echo "</div>\n";
			}
			else
			{
				echo "<div class='root' style='position: relative'>\n";
				echo "<span class='pagedot'>&bull;&bull;</span>";
				
				if($x == 1 and strtolower($row['payment_method']) == "consignment proceeds" || stripos($row['payment_method'], "proceeds") === 0)
				{
					list($consignor_invoice) = generate_hybrid_invoice($row['invoice_number'], $db, true, null, true);
					
					echo $consignor_invoice;
					//echo $invoices[0]."\n";
					//echo str_replace(">Office Copy<", ">&nbsp;<", $main_invoice)."\n";
				}
				else
				{
					echo $shipping_invoice."\n";
				}
				
				echo "</div>";
				
				/*
				for($y = 0; $y < count($invoices); $y++)
				{
					echo "<div class='root' style='position: relative'>\n";
					
					if(($copies > 1 || count($invoices) > 1) and $y == count($invoices)-1)
					{
						if($x == $copies - 1)
							echo "<span class='pagedot'>&bull;&bull;</span>";
						elseif(count($invoices) > 1)
							echo "<span class='pagedot'>&bull;</span>";
					}
					
					echo $invoices[$y]."\n";
					
					
					echo "</div>";
				}
				*/
			}
			
			
			
			echo "<hr style='margin: 30px 0px 30px 0px;' class='noprint' />\n";
			//$separator = false;
		}
		
		//Extra page for Eric 2017-02-22 AK
		if(count($fixed_price_items))
		{
			$alternate = false;
			?>
			<div class='root' style='position: relative'>
			<h2>Fixed Price Item Pull Sheet for Invoice #<?=$row['invoice_number']?></h2><br />
			<table class='bins' border='1' cellpadding='5' style='border-collapse: collapse; font-size: 9pt; width: 7in; table-layout: fixed'>
			
			<col style='width: 1in' />
			<col style='width: 4.25in' />
			<col style='width: .75in;' />
			<col style='width: 1in' />
			
			<?
			foreach($fixed_price_items as $item)
			{
				if(isset($prev) && ($prev['type_code'] != $item['type_code'] or substr($prev['ebay_title'], 4, 2) != substr($item['ebay_title'], 4, 2)))
				{
					$alternate = !$alternate;
				}
				
				echo "<tr ".($alternate ? "class='gray'" : "")."><td>$item[ebay_item_number]</td><td>$item[ebay_title]</td><td style='text-align: right'>\$".number_format($item['price'], 2)."</td>".
					"<td>$item[type_code]</td><td>$item[film_title]</td></tr>\r\n";
				$prev = $item;
			}
			
			?>
			</table>
			</div>
			<?
		}
		
		
		
		/*if($copies > 1)
		{
			echo "<div class='noprint'>separator page<hr /></div><div style='page-break-after: always'>&nbsp;</div>";
			$separator = true;
		}*/
		
	}
	ob_end_flush();
}
catch(Exception $e)
{
	while(ob_end_clean());
	echo $e->__toString();
	email_error($e->__toString());
}


?>
</body>
</html>