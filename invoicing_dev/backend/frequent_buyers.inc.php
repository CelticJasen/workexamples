<?php
namespace FrequentBuyers;



function credit_date()
{
	$today = new DateTimeImmutable();
	
	switch($today->format("/"))
	{
		case "Tuesday":
		case "Thursday":
		case "Sunday":
			return new DateTimeImmutable("Tomorrow");
			break;
		
		default:
			return $today;
	}
}

function insert_credit(mysqli $db, $total, $email, DateTimeImmutable $ym, DateTimeImmutable $date)
{
	$db->query("insert into invoicing.sales ".
		"set `date` = '".$date->format("Y-m-d")."', ".
		"ebay_item_number = 'bonus', ".
		"ebay_title = 'In ".$ym->format("F").", you won a total of ".$total." items, for a rebate of', ".
		"ebay_email = '".$db->escape_string($email)."', ".
		"price = '-".credit_amount($total_items)."', ".
		"shipping_notes = 'Frequent Buyers\' Program Rebate; apply to next order'");
}

function credit_amount($total_items)
{
	if($total_items < 50)
	{
		return 0;
	}
	elseif($total_items < 100)
	{
		return 25;
	}
	elseif($total_items > 199)
	{
		return 200;
	}
}

function get(mysqli $db, DateTimeImmutable $ym)
{
	$r = $db->query("select count(*) as items, ebay_email ".
		"from invoicing.sales ".
		"left join invoicing.customers on (email = ebay_email) ".
		"where frequent_buyer != 0 and price > 0 and ".
		"`date` like '".$ym->format("Y-m")."-%' and ".
		"ebay_item_number regexp '^[0-9]{7,}$' ".
		"group by ebay_email ".
		"having count(*) > 49");
		
	while(list($total, $email) = $r->fetch_row())
	{
		yield array($total, $email);
	}
}

?>