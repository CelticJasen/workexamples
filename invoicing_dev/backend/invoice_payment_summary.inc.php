<?php

//require_once("/webroot/sync_website/generate_invoice.inc.php");


function invoice_payment_summary($invoice_number, $db)
{
	$invoice_numbers = invoice_children_mysqli($invoice_number, $db);
	
	$payments = array();
	
	foreach($invoice_numbers as $invoice_number)
	{
		$items_total = $payments_total = 0;
		
		
		$r = $db->query("select payment_method, shipping_charged, date_of_invoice ".
			"from invoices ".
			"where invoice_number = '".$db->escape_string($invoice_number)."'");
			
		list($payment_method, $shipping_charged, $date_of_invoice) = $r->fetch_row();
		
		$items_total += $shipping_charged;	
	
		$r = $db->query("select price*quantity, ebay_title, `date` ".
			"from invoicing.sales ".
			"where invoice_number = '".$db->escape_string($invoice_number)."'");
			
		while(list($price, $title, $date) = $r->fetch_row())
		{
			if($price > 0)
			{
				$items_total += $price;
			}
			elseif($price < 0)
			{
				$payments[] = array(abs($price), $title, $date, $invoice_number);
				$payments_total += abs($price);
			}
		}
		
		
		if($items_total > $payments_total)
		{
			$payments[] = array($items_total - $payments_total, $payment_method, $date_of_invoice, $invoice_number);
		}
		elseif($items_total < $payments_total)
		{
			$payments[] = array(0-($payments_total - $items_total), "Credit remaining", $date_of_invoice, $invoice_number);
		}
	}
	
	return $payments;
}
/*
set_error_handler("exception_error_handler", E_ALL);
mysqli_report(MYSQLI_REPORT_STRICT | MYSQLI_REPORT_ERROR);
$db = new mysqli("localhost", "root", "k8!05Er-05", "invoicing");
$db->set_charset("utf8");

$summary = invoice_payment_summary(276686, $db);

print_r($summary);*/

?>