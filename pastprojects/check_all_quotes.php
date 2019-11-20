<?php
/*
Script: check_all_quotes.php

A somewhat crude script that is meant to be run from command line,
which does the quote sanity check on all quotes that haven't been
totally paid for yet.

Aaron Kennedy, kennedy@postpro.net, 2012-12-13
*/

require("/webroot/shipping_quotes/check_quote.inc.php");

mysql_connect2("localhost", "office", "never eat shredded wheat");

$r = mysql_query3("select distinct quotes.quote_id, customer_email, quotes.timestamp ".
	"from invoicing.quotes join invoicing.quotes_packages on(quotes_packages.quote_id = quotes.quote_id) ".
	"join invoicing.sales on (quotes_packages.package_id = sales.package_id and sales.invoice_printed = '0') order by quotes.timestamp");

echo mysql_num_rows($r)."\n";sleep(1);

while(list($quote_id, $email, $timestamp) = mysql_fetch_row($r))
{
	$r2 = mysql_query3("select Country, Status_Account from invoicing.aa_customers where email = '$email'");
	
	if(mysql_num_rows($r2))
	{
		$customer = mysql_fetch_assoc($r2);		
		$country = mysql_result($r2, 0, 0);
		if($customer['Status_Account'] == "0")
			continue;
	}
	else
	{
		echo ("couldnt find $email\n");
		continue;
	}

	echo $quote_id."\t".$timestamp."\n";
	try
	{
		
		check_quote($quote_id, false, $country);
	}
	catch(Exception $e)
	{
		if(stristr($e->getMessage(), "A price requirement failed") === false)
			echo $quote_id.": ".$e->getMessage()."\n";
	}
}
