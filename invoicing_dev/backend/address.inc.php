<?php
/**
 * A collection of functions for working with physical addresses.
 */
class Address
{
	/**
	 * Takes an invoicing.customers record and returns
	 * a string, the human-readable formatted address.
	 */
	static function format($customer)
	{
		$lines = Array(
			$customer['name'],
			$customer['ship_attention_line'],
			$customer['ship_address_line1'],
			$customer['ship_address_line2'],
			
		);
		
		if(isset($customer['ship_address_line3']))
			$lines[] = $customer['ship_address_line3'];
		
		if(!empty($customer['ship_city']))
			$lines[] = $customer['ship_city'].", ".$customer['ship_state']." ".$customer['ship_zip'];
		else
			$lines[] = $customer['ship_state']." ".$customer['ship_zip'];
		
		if(!empty($customer['countrycode_name']))
			$lines[] = $customer['countrycode_name'];
		else
			$lines[] = $customer['ship_country'];
		
		return implode("\n", array_filter(array_map("trim", $lines), "strlen"));
	}


	/**
	 * Takes an invoicing.bill_to_address record and changes 
	 * the keys so that it is compatible with Address::format()
	 */
	static function convert_bill_to($billto)
	{
		$converted = array();
		
		foreach($billto as $k => $v)
		{
			$converted[str_replace("bill_", "ship_", $k)] = $v;
		}
		
		return $converted;
	}



	/**
	 * Takes an invoicing.aa_customers record and returns
	 * a string, the human-readable formatted address.
	 */
	static function format_aa($customer)
	{
		$lines = Array(
			$customer['Company'],
			$customer['FirstName']." ".$customer['LastName'],
			$customer['Address'],
			$customer['AddressLine2'],		
		);
		
		if(!empty($customer['City']))
			$lines[] = $customer['City'].", ".$customer['State']." ".$customer['Zip'];
		else
			$lines[] = $customer['State']." ".$customer['Zip'];
		
		$lines[] = $customer['Country'];
		
		return implode("\n", array_filter(array_map("trim", $lines), "strlen"));
	}
}
?>