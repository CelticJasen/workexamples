<?php
require_once("get_quotes.inc.php");
require_once("get_packages.inc.php");
require_once("get_items.inc.php");
require_once("get_package_prices.inc.php");

function price_assert($assertion, $p)
{
	try
	{
		if(eval("return ".$assertion.";") == false)
			throw new Exception("A price requirement failed.\n".$assertion, 10040);
	}
	catch(Exception $e)
	{
		if($e->getCode() != 10040)
			return true;
		else
			throw $e;
	}
}

function check_quote($quote_id, $force, $country, $approve = false, $send_type = 'send_email')
{
	/*
	 * Function: check_quote
	 * 
	 * Parameters:
	 * - quote id
	 * - true or false, whether the user chose to skip certain skippable checks
	 * - two-letter country code of the customer, used for insured/uninsured checks
	 * 
	 * Checks the quote for readiness to be approved or sent to the website,
	 * and on failure, throws an exception.
	 */
	 
	global $shipping_service_names;
	 
	$quote = get_quote($quote_id);
	
	$skippable_errors = Array();
	
	if(empty($quote))
		throw new Exception("Couldn't find quote with id $quote_id", 10000);
	
	
	//if(!preg_match("/^[^@]+@[^@]+$/", $quote['customer_email']))
	if(empty($quote['customer_email'])) //We don't have email addresses for Amazon customers. AK 2012-12-18
		throw new Exception("Invalid email address assigned to quote with id $quote_id ('$quote[customer_email]')", 10000);
	
	$packages = get_packages($quote_id);
	
	if(empty($packages))
		throw new Exception("This quote doesn't have any packages.", 10040);
	
	foreach($packages as $package)
	{
		if($package['value'] === NULL)
			throw new Exception("Package #$package[package_id]: No value.", 10040);
		
		if($package['pounds'] == "" || $package['ounces'] == "")
			throw new Exception("Package #$package[package_id]: You must fill out poonds and oonces.", 10040);
			
		$items = get_package_items($package['package_id']);
		
		if(empty($items) && !$force)
		{
			$skippable_errors[] = "Package #$package[package_id]: No items.";
		}
		
		if(!$approve && !$force && !empty($package['estimated_oz']) && $package['quoted_oz'] > $package['estimated_oz']+14)
		{
			Mailer::mail("aaron@emovieposter.com, shippingdept@emovieposter.com, jasen@emovieposter.com", 
				"Package much heavier than estimated",
				"Package #$package[package_id] is much heavier than estimated (estimated at $package[estimated_oz]oz / quoted at $package[quoted_oz]oz)! ".
				"Send an explanation to Steven to help improve autoquoting. ".
				"\r\n\r\nInfo dump: \r\n\r\n".
				print_r(json_decode($package['pkg_autoquote_json'], true), true)."\r\n\r\n".print_r($package, true)."\r\n\r\n".print_r($items, true));
			
			$skippable_errors[] = "Package #$package[package_id] is much heavier than estimated (estimated at $package[estimated_oz]oz)! ".
				"Send an explanation to Steven to help improve autoquoting.";
		}
		
		if(!$force)
		{
			if(stristr($package['type'], "tube") !== false)
			{
				foreach($items as $i)
				{
					if(stristr($i['snst_type_name'], "flat") !== false)
					{
						$skippable_errors[] = "There is a flat item in package $package[package_id], which is a tube.";
						break;
					}
				}
			}
			elseif(stristr($package['type'], "box") !== false)
			{
				foreach($items as $i)
				{
						if(stristr($i['snst_type_name'], "rolled") !== false)
						{
							$skippable_errors[] = "There is a rolled item in package $package[package_id], which is a box.";
							break;
						}
				}
			}
		}
			
		$prices = get_package_prices($package['package_id']);
		$value = get_package_value($package['package_id']);
		
		if(empty($prices))
			throw new Exception("Package #$package[package_id]: No prices.", 10040);
		
		$prices_unfree = $prices_free = $services_quoted = Array();
		
		foreach($prices as $price)
		{
			if($price['cost'] < 7 && !$force)
				$skippable_errors[] = "Package #$package[package_id]: Price of '\$$price[cost]'. That's pretty low.";
			if($price['cost'] > 150 && !$force)
				$skippable_errors[] = "Package #$package[package_id]: Price of '\$$price[cost]'. That's pretty high.";
			
			if($price['free_item'])
				$prices_free[$shipping_service_names[$price['ship_service']]] = $price['cost'];
			else
				$prices_unfree[$shipping_service_names[$price['ship_service']]] = $price['cost'];
			
			$services_quoted[] = $price['ship_service'];
		}
		
		if($value >= 350 && $country == "US" && !$force) //Considered valuable and must be sent insured by carrier. AK, 2012-12-10
		{
			if(in_array(1, $services_quoted) || in_array(3, $services_quoted) || in_array(17, $services_quoted) || in_array(6, $services_quoted))
			{
				$skippable_errors[] = "You should quote this package as -insured only- because it is worth $350 or more and is domestic. ".
					"Package #$package[package_id]";
				//throw new Exception("You must quote this package as -insured only- because it is worth $350 or more and is domestic. ".
					//"Package #$package[package_id]", 10040);
			}
		}
		
		
		if(!$force && (
			$send_type!='no_email' && $country == "US" and
			(in_array(1, $services_quoted) && in_array(2, $services_quoted) or //Priority
			in_array(3, $services_quoted) && in_array(4, $services_quoted) or //Express
			in_array(17, $services_quoted) && in_array(20, $services_quoted) or //Parcel Post
			in_array(6, $services_quoted) && in_array(19, $services_quoted)) //UPS Ground
		)){ //They quoted both insured and non-insured. How dare they? AK, 2012-12-10
		  $skippable_errors[] = "You can't quote a U.S. package with both insured and non-insured options. Package #$package[package_id].";
			//throw new Exception("You can't quote a U.S. package with both insured and non-insured options. Package #$package[package_id].", 10042);
    }
		
		try
		{
			foreach(Array($prices_free, $prices_unfree) as $p)
			{
				if(isset($p["Priority Mail Insured"]) && isset($p["Priority Mail"]))
					price_assert('$p["Priority Mail Insured"] > $p["Priority Mail"]', $p);
				
				if(isset($p["Express Mail Insured"]) && isset($p["Express Mail"]))
					price_assert('$p["Express Mail Insured"] > $p["Express Mail"]', $p);
				
				if(isset($p["Express Mail"]) && isset($p["Priority Mail"]))
					price_assert('$p["Express Mail"] > $p["Priority Mail"]', $p);
				
				if(isset($p["Express Mail Insured"]) && isset($p["Priority Mail Insured"]))
					price_assert('$p["Express Mail Insured"] > $p["Priority Mail Insured"]', $p);
			}
		}
		catch(Exception $e)
		{
			if(substr($e->getCode(), 0, -1) == 1004)
				throw new Exception("Package #$package[package_id]: ".$e->getMessage(), $e->getCode());
			else
				throw $e;
		}
	}

	if(count($skippable_errors))
	{
		throw new Exception(implode("\n", $skippable_errors)."\n\nPress OK to continue anyway.", 10041);
	}
}
