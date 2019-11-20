<?php
/*
Script: process_cart_order.inc.php



Written by Aaron Kennedy, kennedy@postpro.net, December 2011
*/
if(date("Y") > "2016")
{
	define("INVOICE_NUMBER_MIN", 500001); //183179);
	define("INVOICE_NUMBER_MAX", 599999);
}
else
{
	define("INVOICE_NUMBER_MIN", 300001); //183179);
	define("INVOICE_NUMBER_MAX", 399999);
}

function process_cart_order($data, $data2, $db, $quote_only = false, $override_pending = false)
{
	try
	{
		if($data2 && !empty($data['payment']) && array_key_exists("paypal", $data['payment']))
		{
			if($override_pending)
				$payment_instant = true;
			else
				$payment_instant = $data2->DoExpressCheckoutPaymentResponseDetails->PaymentInfo->PaymentType == "instant";
			
			$transaction_id = $data2->DoExpressCheckoutPaymentResponseDetails->PaymentInfo->TransactionID;
		}
		else
		{
			$payment_instant = true;
			$transaction_id = false;
		}
		
		
		
		
		/*
			Put in address, billing address, and credit card
		*/
		$data['customer']['email'] = trim($data['customer']['email']); //We had a space that messed things up. - AK, 2012-12-07  
		
		$customer_id = insert_new_customer($data, $db);
		
		if(array_key_exists("billing_address", $data['customer']))
		{
			add_bill_to_address($data, $customer_id, $db);
		}
		
		if($quote_only === false && array_key_exists("credit_card", $data['payment']))
		{
			add_credit_card($data['payment']['credit_card'], $customer_id, $db);
		}
		
		
		
		
		/*
			Consistency check auction items, and add fixed price items
		*/
		$child_invoices = array();
		
		
		foreach($data['order']['items'] as $key => $item)
		{
			if($item['Quantity'] != 1)
				throw new Exception("I can't yet handle more than one of an item.", 10030);
			
			if(!array_key_exists("Amount", $item)) //Indicates pre-paid Pay & Hold item
			{
				if(preg_match("/^F[0-9]*$/i", $item['Number']))
				{
					add($child_invoices, check_ph_bin_item($item, $data['customer']['email'], $db), $item['Number']);
				}
				else
				{
					add($child_invoices, check_ph_item($item, $data['customer']['email'], $db), $item['Number']);
				}
			}
			elseif(preg_match("/^F[0-9]*$/i", $item['Number']))
			{
				$r = $db->query(sprintf("select 1 from invoicing.sales where ebay_item_number = '%s'", 
					$db->escape_string($item['Number'])));
				
				if($r->num_rows)
				{
					check_bin_item($item, $data['customer']['email'], $db);
				}
				else
				{
					add_item_to_sales($data, $key, $customer_id, $db);
					add_item_to_fixed_price_sales($data, $key, $customer_id, $db, $quote_only);
				}
			}
			elseif($quote_only === false)
			{
				check_item($item, $data['customer']['email'], $db);
			}
		}
		
		
		
		
		/*
			Check child P&H invoices when applicable
		*/
		
		foreach($child_invoices as $invoice_number => &$items)
		{
			sort($items);
			if($items != $items2 = ph_invoice_items($db, $invoice_number))
			{
				throw new exception("Invoice #$invoice_number: Items sent by checkout do ".
					"not match items on invoice. (".implode(",", $items)."), (".implode(",", $items2).")", 10015);
			}
		}
		unset($items);
		
		
		
		
		/*
			Consistency check credits
		*/
		if(!empty($data['order']['credits']) && is_array($data['order']['credits']))
		{
			foreach($data['order']['credits'] as $credit)
			{
				if($credit['Use_Credit'])
				{
					check_credit($credit['autonumber'], $data['customer']['email'], $credit['amount'], $db);
				}
			}
		}
		
		
		
		
		/*
			
		*/
		$invoices = array();
		
		if($quote_only === true)
		{
			$invoice_number = "<b>quote</b>";
		}
		elseif($payment_instant === false)
		{
			$invoice_number = "<b>pending</b>";
		}
		elseif(empty($data['autoship']))
		{
			
			
			/*
				Pay & Hold
			*/
			if(!array_key_exists("shipping", $data['order']))
			{
				$item_numbers = array();
				
				
				/*
					Get a list of all the items being paid for
				*/
				foreach($data['order']['items'] as $key => $item)
				{
					if(array_key_exists("Amount", $item)) //Item is being paid for. 20160902 AK
					{
						$item_numbers[] = $item['Number'];
					}
				}
				
				
				/*
					Loop over each combine type
				*/
				$r = $db->query("select ebay_item_number, snst_type ".
					"from invoicing.sales ".
					"join listing_system.new_specialnotes_shiptype on (snst_id = combine_type) ".
					"where ebay_item_number in ('".implode("','", $item_numbers)."') ");
				
				while(list($item_number, $snst_combine) = $r->fetch_row())
				{
					if(array_key_exists($snst_combine, $invoices))
					{
						$invoice_number = $invoices[$snst_combine];
					}
					else
					{
						$invoice_number = next_invoice_number($db);
					
						add_invoice($data, $customer_id, $invoice_number, $transaction_id, 
							"", //Notes
							"0", //shipping_charged
							"Paid & Hold", $db);
						
						$invoices[$snst_combine] = $invoice_number;
					}
					
					//The arrays are kinda hacky because they're supposed to be 
					//all the columns for an item. But it'll work for now. AK 2016-09-28
					
					assign_item_invoice_number(array("Number" => $item_number), $invoice_number, $db);
						
					assign_item_customer_id(array("Number" => $item_number), $customer_id, $db);
				}
		   	}
			
			/*
				If this is not a quoted order, or the customer is U.S., treat it normally
			*/
			elseif(empty($data['quote_packages']) || $data['customer']['shipping_address']['country'] == "US")
			{
				
				
				
				/*
					Get shipping method; create package notes
				*/
				$invoice_notes = "";
				
				if(!empty($data['quote_packages']))
				{
					if(count($data['quote_packages']) == 1)
					{
						$r = $db->query("select shipping_methods ".
							"from quotes_amounts ".
							"left join quotes_services using(ship_service) ".
							"left join invoicing.shipping_methods on (quotes_amounts.ship_service = shipping_methods.ship_service) ".
							"where quote_amount_id = '".$data['quote_packages'][0]['serviceId']."'");
							
						if($r->num_rows)
						{
							list($shipping_method) = $r->fetch_row();
						}
						else
						{
							$shipping_method = null;
						}
					}
					else
					{
						$package_number = 1;
						
						foreach($data['quote_packages'] as $p)
						{
							$r = $db->query("select package_id, service_name, cost, free_item, shipping_methods ".
								"from quotes_amounts ".
								"left join quotes_services using(ship_service) ".
								"left join invoicing.shipping_methods on (quotes_amounts.ship_service = shipping_methods.ship_service) ".
								"where quote_amount_id = '".$p['serviceId']."'");
							
							if($r->num_rows)
							{
								list($package_id, $service_name, $cost, $free_item, $shipping_method) = $r->fetch_row();
								
								$invoice_notes .= "Package $package_number of ".count($data['quote_packages'])."\r\n".
									sprintf("Pkg %s: $%s %s, %s\r\n", $package_id, number_format($cost, 2), $service_name, ($free_item ? "free" : "nofree"))."\r\n";
							}
							else
								throw new Exception("I couldn't find quote_amount_id $p[serviceId] in invoicing.quotes_amounts.", 10050);
						}
						
						$shipping_method = "Multiple Packages (see below)";
					}
				}	
				elseif(!array_key_exists("shipping", $data['order']))
				{
					$shipping_method = "Paid & Hold";
				}
				elseif(count($data['order']['shipping_charges']) > 1)
				{
					$shipping_method = "Multiple Packages";
				}
				else
				{
					$shipping_method = null;
				}
				
				
				
				
				/*
					Create invoice; assign items to invoice
				*/
				$invoice_number = next_invoice_number($db);
				
				add_invoice($data, $customer_id, $invoice_number, $transaction_id, $invoice_notes, 
					str_replace(",", "", $data['order']['shipping']), $shipping_method, $db);
				
				foreach($data['order']['items'] as $key => $item)
				{
					if(array_key_exists("Amount", $item)) //Item is being paid for
					{
						assign_item_invoice_number($item, $invoice_number, $db);
						
						assign_item_customer_id($item, $customer_id, $db);
					}
				}
				
				foreach($child_invoices as $ph_invoice_number => $items)
				{
					assign_ph_invoice_number($db, $ph_invoice_number, $invoice_number);
				}
				
				$invoices[] = $invoice_number;
			}
			
			
			
			
			/*
				If this order was quoted and the customer is foreign, create one invoice per package.
			*/
			else
			{
				$package_number = 1;
				foreach($data['quote_packages'] as $p)
				{
					$r = $db->query(sprintf("select package_id, service_name, cost, free_item, shipping_methods ".
						"from quotes_amounts ".
						"left join quotes_services using(ship_service) ".
						"left join invoicing.shipping_methods on (quotes_amounts.ship_service = shipping_methods.ship_service) ".
						"where quote_amount_id = '%s'", 
						$p['serviceId']));
					
					if($r->num_rows)
					{
						list($package_id, $service_name, $cost, $free_item, $shipping_method) = $r->fetch_row();
						
						$invoice_number = next_invoice_number($db);						
						
						add_invoice($data, $customer_id, $invoice_number, $transaction_id, 
							(count($data['quote_packages']) > 1 ? "Invoice $package_number of ".count($data['quote_packages'])."\r\n" : "").
							sprintf("Pkg %s: $%s %s, %s\r\n", $package_id, number_format($cost, 2), $service_name, ($free_item ? "free" : "nofree")),
							$cost, $shipping_method, $db);
						
						$invoices[$package_id] = $invoice_number;
						
						$package_number++;
					}
					else
						throw new Exception("I couldn't find quote_amount_id $p[serviceId] in invoicing.quotes_amounts.", 10050);
				}
				
				//This was added 2014-10-09 after Osvaldo Fredes' issue
				//where he had already paid for some of the items in a package. AK
				foreach($data['order']['items'] as $key => $item)
				{
					if(array_key_exists("Amount", $item)) //Item is being paid for. 20160902 AK
					{
						$r = $db->query("select package_id from sales where ebay_item_number = '$item[Number]'");
						
						list($package_id) = $r->fetch_row();
						
						assign_item_invoice_number($item, $invoices[$package_id], $db);

						assign_item_customer_id($item, $customer_id, $db);
					}
				}
				
				foreach($child_invoices as $ph_invoice_number => $items)
				{
					$package_id = check_ph_invoice($db, $ph_invoice_number);
					
					if(empty($package_id))
						assign_ph_invoice_number($db, $ph_invoice_number, reset($invoices));
					else
						assign_ph_invoice_number($db, $ph_invoice_number, $invoices[$package_id]);
				}
				
				
				
			}
		}
		else
		{
			$invoice_number = "<b>autoship</b>";
		}
		
		
		
		
		/*
			Pick credits to use; add sales tax
		*/
		$credits_to_use = array();
		
		if($quote_only === false && empty($data['autoship']))
		{
			if($payment_instant)
			{
				if(!empty($data['order']['credits']) && is_array($data['order']['credits']))
				{
					foreach($data['order']['credits'] as $credit)
					{
						if($credit['Use_Credit'])
						{
							$credits_to_use[] = $credit['autonumber'];
						}
					}
				}
			}
			
			if($data['order']['tax'] > 0)
				add_sales_tax_item($data, $invoice_number, $customer_id, $db);
		}
		
		$message = "";
		
		if(count($credits_to_use))
			$message = ", Credits used";
		
		
		
		
		/*
			Assign credits to invoices; calculate grand totals
		*/
		foreach($invoices as $invoice_number)
		{
			if(count($credits_to_use))
			{
				while(0 < $total = get_invoice_total($invoice_number, $db) && $credit = array_shift($credits_to_use))
				{
					assign_credit_invoice_number($credit, $invoice_number, $db);
				}
				
				
				
				if(0 >= $total = get_invoice_total($invoice_number, $db))
				{
					$db->query("update invoicing.invoices ".
						"set payment_method = 'Credit on account', ".
						"extra_info = trim(concat(coalesce(extra_info, ''), '\r\nRemaining credit on account is \$".number_format(0-$total, 2)."')) ".
						"where invoice_number = '".$db->escape_string($invoice_muber)."' ");
					
					if($total < 0)
					{
						$credits_to_use[] = add_credit_remaining($total, $data, $customer_id, $db);
					}
				}
			}
			
			do_grand_totals($invoice_number, $db);
		}
		
		if(count($credits_to_use))
			$message .= ", credits remaining";
	}
	catch(Exception $e)
	{
		if($e->getCode() != 10015)
			email_error($e->__toString());
		throw $e;
	}

	return "$invoice_number, $customer_id".$message;
}


function assign_credit_invoice_number($item_number, $invoice_number, $db)
{
	try
	{
		$db->query(sprintf("update invoicing.sales set invoice_number = '%s' where autonumber = '%s'", 
			$db->escape_string($invoice_number), $db->escape_string($item_number)));
	}
	catch(Exception $e)
	{
		throw new Exception("I couldn't assign invoice number $invoice_number to credit $item_number.", 10500);
	}
	return true;
}


function assign_item_invoice_number($item, $invoice_number, $db)
{
	try
	{
		$db->query(sprintf("update invoicing.sales set invoice_number = '%s' where ebay_item_number = '%s' and ebay_item_number != ''",
			$db->escape_string($invoice_number), $db->escape_string($item['Number'])));
	}
	catch(Exception $e)
	{
		throw new Exception("I couldn't assign invoice number $invoice_number to item $item[Number].".$e->__toString(), 10500);
	}
	
	return true;
}


function assign_item_customer_id($item, $customer_id, $db)
{
	try
	{
		$db->query(sprintf("update invoicing.sales set customer_id = '%s' where ebay_item_number = '%s' and ebay_item_number != ''",
		$db->escape_string($customer_id), $db->escape_string($item['Number'])));
	}
	catch(Exception $e)
	{
		throw new Exception("I couldn't assign customer_id $customer_id to item $item[Number].", 10500);
	}
	
	return true;
}

function get_invoice_total($invoice_number, $db)
{
	$r = $db->query("select sum(price*quantity)+shipping_charged ".
		"from invoicing.sales ".
		"join invoicing.invoices using(invoice_number) ".
		"where invoice_number = '$invoice_number'");
	
	list($total) = $r->fetch_row();
	
	return $total;
}

function do_grand_totals($invoice_number, $db)
{
	$r = $db->query("select sum(price*quantity), shipping_charged ".
		"from invoicing.sales ".
		"join invoicing.invoices using(invoice_number) ".
		"where invoice_number = '$invoice_number'");
	
	list($subtotal, $shipping) = $r->fetch_row();
	
	$db->query("insert into invoicing.grand_totals ".
		"set invoice_number = '$invoice_number', SumOfprice = '$subtotal', ".
		"SumOfshipping_charged = '$shipping', grand_total = '".($subtotal + $shipping)."'");
	
	return true;
}


function check_credit($autonumber, $customer_email, $amount, $db)
{
	$r = $db->query(sprintf("select ebay_email, price, invoice_number ".
		"from invoicing.sales where autonumber = '%s'",
		$db->escape_string($autonumber)));
	
	if($r->num_rows == 1)
	{
		list($email, $price, $invoice_number) = $r->fetch_row();
		
		if($invoice_number != "")
			throw new Exception("Credit $autonumber is assigned to an invoice number", 10015);
		
		if(strtolower($email) != strtolower($customer_email))
			throw new Exception("Credit $autonumber is not assigned to '$customer_email'. It is assigned to '$email'.", 10015);
			
		if($price != $amount)
			throw new Exception("Credit $autonumber does not have amount '$amount'. Its amount is '$price'.", 10015);
	}
	else
		throw new Exception("Credit $autonumber does not exist in tbl_total_sales.", 10015);
	
	return true;
}

function check_item($item, $customer_email, $db)
{	
	$r = $db->query(sprintf("select ebay_email, price, invoice_number from invoicing.sales where ebay_item_number = '%s'", 
		$db->escape_string($item['Number'])));
	
	switch($r->num_rows)
	{
		case 1:
			list($email, $price, $invoice_number)= $r->fetch_row();
			
			if($invoice_number != "")
				throw new Exception("Item $item[Number] is already assigned an invoice number", 10015);
			
			if(strtolower($email) != strtolower($customer_email))
				throw new Exception("Item $item[Number] is not assigned to '$customer_email'. It is assigned to '$email'.", 10015);
			
			if(is_array($item['Amount']))
			{
				if($price != str_replace(",", "", $item['Amount']['_']))
					throw new Exception("Item $item[Number] does not have price '".$item['Amount']['_']."'. Its price is '$price'.", 10015);
			}
			else
			{
				if($price != str_replace(",", "", $item['Amount']))
					throw new Exception("Item $item[Number] does not have price '$item[Amount]'. Its price is '$price'.", 10015);
			}
			break;
			
		case 0:
			throw new Exception("Item $item[Number] is missing from tbl_total_sales.", 10015);
			break;
			
		default:
			throw new Exception("More than one of $item[Number] exists in tbl_total_sales.", 10015);
			break;
	};
	
	return true;
}


function check_ph_item($item, $customer_email, $db)
{	
	$r = $db->query(sprintf("select ebay_email, price, invoice_number from invoicing.sales where ebay_item_number = '%s'", 
		$db->escape_string($item['Number'])));
	
	switch($r->num_rows)
	{
		case 1:
			list($email, $price, $invoice_number)= $r->fetch_row();
			
			if(empty($invoice_number))
				throw new exception("Item $item[Number] is supposed to be paid for, but it has no invoice number.", 10015);
			
			if(strtolower($email) != strtolower($customer_email))
				throw new Exception("Item $item[Number] is not assigned to '$customer_email'. It is assigned to '$email'.", 10015);
			
			break;
		
		case 0:
			throw new Exception("Item $item[Number] is missing from tbl_total_sales.", 10015);
			break;
			
		default:
			throw new Exception("More than one of $item[Number] exists in tbl_total_sales.", 10015);
			break;
	};
	
	return $invoice_number;
}


function check_ph_bin_item($item, $customer_email, $db)
{
	$r = $db->query(sprintf("select ebay_email, price, invoice_number ".
		"from invoicing.sales where ebay_item_number = '%s'", 
		$db->escape_string($item['Number'])));
	
	switch($r->num_rows)
	{
		case 1:
			list($email, $price, $invoice_number)= $r->fetch_row();
			
			if(empty($invoice_number))
				throw new exception("Item $item[Number] is supposed to be paid for, but it has no invoice number.", 10015);
			
			if(strtolower($email) != strtolower($customer_email))
				throw new Exception("Item $item[Number] is not assigned to '$customer_email'. It is assigned to '$email'.", 10015);
			break;
			
		case 0:
			throw new Exception("Item $item[Number] is missing from tbl_total_sales.", 10015);
			break;
			
		default:
			throw new Exception("More than one of $item[Number] exists in tbl_total_sales.", 10015);
			break;
	};
	
	
	$r = $db->query("select * from invoicing.fixed_price_sales ".
		"where item_number = '".$db->escape_string($item['Number'])."'");
	
	switch($r->num_rows)
	{
		case 1:
			$row = $r->fetch_assoc();
			
			if(strtolower($row['user_email']) != strtolower($customer_email))
			{
				throw new Exception("Item $item[Number] should be assigned to ".
					"'$customer_email' ".
					"but is assigned to '$row[user_email]' in fixed_price_sales (tbl_BINs).", 10015);
			}
			break;
		
		case 0:
			throw new Exception("Item $item[Number] is missing from fixed_price_sales (tbl_BINs).", 10015);
			break;
		
		default:
			throw new Exception("More than one of $item[Number] exists in fixed_price_sales (tbl_BINs).", 10015);
			break;
	};
	
	return $invoice_number;
}



function check_bin_item($item, $customer_email, $db)
{	
	$r = $db->query(sprintf("select ebay_email, price, invoice_number ".
		"from invoicing.sales where ebay_item_number = '%s'", 
		$db->escape_string($item['Number'])));
	
	switch($r->num_rows)
	{
		case 1:
			list($email, $price, $invoice_number)= $r->fetch_row();
			
			if($invoice_number != "")
				throw new Exception("Item $item[Number] is already assigned an invoice number", 10015);
			
			if(strtolower($email) != strtolower($customer_email))
				throw new Exception("Item $item[Number] is not assigned to '$customer_email'. It is assigned to '$email'.", 10015);
			
			/*
			if(is_array($item['Amount']))
			{
				if($price != str_replace(",", "", $item['Amount']['_']))
					throw new Exception("Item $item[Number] does not have price '".$item['Amount']['_']."'. Its price is '$price'.", 10015);
			}
			else
			{
				if($price != str_replace(",", "", $item['Amount']))
					throw new Exception("Item $item[Number] does not have price '$item[Amount]'. Its price is '$price'.", 10015);
			}
			*/
			break;
			
		case 0:
			throw new Exception("Item $item[Number] is missing from tbl_total_sales.", 10015);
			break;
			
		default:
			throw new Exception("More than one of $item[Number] exists in tbl_total_sales.", 10015);
			break;
	};
	
	$r = $db->query("select * from invoicing.fixed_price_sales ".
		"where item_number = '".$db->escape_string($item['Number'])."'");
		
	switch($r->num_rows)
	{
		case 1:
			$row = $r->fetch_assoc();
			
			if(strtolower($row['user_email']) != strtolower($customer_email))
			{
				throw new Exception("Item $item[Number] should be assigned to ".
					"'$customer_email' ".
					"but is assigned to '$row[user_email]' in fixed_price_sales (tbl_BINs).", 10015);
			}
			
			if(is_array($item['Amount']))
			{
				if($row['price'] != str_replace(",", "", $item['Amount']['_']))
				{
					throw new Exception("Item $item[Number] should have price ".
						"'".$item['Amount']['_']."' but its price is '$row[price]' in fixed_price_sales (tbl_BINs).", 10015);
				}
			}
			else
			{
				if($row['price'] != str_replace(",", "", $item['Amount']))
				{
					throw new Exception("Item $item[Number] should have price '$item[Amount]' ".
						"but its price is '$row[price]' in fixed_price_sales (tbl_BINs).", 10015);
				}
			}
			break;
		
		case 0:
			throw new Exception("Item $item[Number] is missing from fixed_price_sales (tbl_BINs).", 10015);
			break;
		
		default:
			throw new Exception("More than one of $item[Number] exists in fixed_price_sales (tbl_BINs).", 10015);
			break;
	};
	
	return true;
}


function insert_new_customer($data, $db)
{
	$customer_data = Array(
		"name"					=> $data['customer']['shipping_address']['first_name']." ".$data['customer']['shipping_address']['last_name'],
		"phone_number_1"		=> $data['customer']['phone'],
		"fax_number"			=> $data['customer']['fax'],
		"ship_attention_line"	=> $data['customer']['shipping_address']['attn'],
		"ship_address_line1"	=> $data['customer']['shipping_address']['address1'],
		"ship_address_line2"	=> $data['customer']['shipping_address']['address2'],
		"ship_address_line3"	=> "",
		"ship_city"		  		=> $data['customer']['shipping_address']['city'],
		
		"ship_state"			=> (($data['customer']['shipping_address']['state'] == "") ? 
			(!empty($data['customer']['shipping_address']['nus_state']) ? $data['customer']['shipping_address']['nus_state'] : "") :
			$data['customer']['shipping_address']['state']),
		
		"ship_zip"				=> $data['customer']['shipping_address']['zip'],
		"ship_country"			=> $data['customer']['shipping_address']['country'],
	);
	
	$r = $db->query("select customer_id ".
		"from invoicing.customers as t1 ".
		"left join invoicing.customers_emails as t2 using(customers_id) ".
		"where t1.email = '".$db->escape_string($data['customer']['email'])."' or ".
		"t2.email = '".$db->escape_string($data['customer']['email'])."' ".
		"order by t1.email = '".$db->escape_string($data['customer']['email'])."' desc ".
		"limit 1");
	
	if($r->num_rows > 0) //If customer id already exists
	{
		list($customer_id) = $r->fetch_row();
		
		$query = "update invoicing.customers set ";
		
		foreach($customer_data as $field => $value)
		{
			$query .= sprintf("`%s` = '%s', ", $db->escape_string($field),
				$db->escape_string($value));
		}
		
		$query = substr($query, 0, -2) . sprintf(" where customer_id = '%s'", $db->escape_string($customer_id));
		
		$db->query($query);
		
		return $customer_id;
	}
	else
	{
		//If user id does not exist, generate one
		$customer_data["customer_id"] = generate_customer_id(
			$data['customer']['shipping_address']['first_name'], 
			$data['customer']['shipping_address']['last_name'],
			(empty($data['customer']['billing_address']) ? 
				$data['customer']['shipping_address']['country'] == "US" : 
				$data['customer']['billing_address']['country'] == "US"),
			$db);
	}
	
	$customer_data["notes_for_invoice"] = "Automatically Created ".date("m/d/Y H:i:s");
	
	$customer_data['email'] = $data['customer']['email'];
   
	$db->query(assemble_insert_query3($customer_data, "invoicing.customers", $db));
	
	return $customer_data['customer_id'];
}


function generate_customer_id($first_name, $last_name, $domestic, $db)
{
	//This will probably no longer be executed now that customer ids are 
	//generated upon end-of-auction, rather than when processing orders. AK 2017-07-25
	
	//Domestic is like "kena03" and foreign is like "ken03" for the name "Aaron Kennedy"
	
	//Tweak. Last name was "Gonzalez Espinosa". It chose "gon" when they wanted it
	//to choose "esp". AK 2013-11-08
	$last_name = array_filter(array_map("trim", explode(" ", $last_name)), "strlen");
	$last_name = array_pop($last_name);
	
	if($domestic)
	{
		$prefix = strtolower(substr(preg_replace("/[^a-zA-Z]/", "", $last_name), 0, 3) . 
			substr(preg_replace("/[^a-zA-Z]/", "", $first_name), 0, 1));
		
		if(strlen($prefix) != 4)
		{
			#throw new Exception("Not the correct number of characters for a customer id prefix ('$prefix').", 10000);
			$prefix = str_pad(strtolower(substr(preg_replace("/[^a-zA-Z]/", "", $last_name), 0, 2)), 4, "x");
		}
	}
	else
	{
		$prefix = strtolower(substr(preg_replace("/[^a-zA-Z]/", "", $last_name), 0, 3));
		
		if(strlen($prefix) != 3)
		{
			#throw new Exception("Not the correct number of characters for a customer id prefix ('$prefix').", 10000);
			$prefix = str_pad(strtolower(substr(preg_replace("/[^a-zA-Z]/", "", $last_name), 0, 2)), 3, "x");
		}
	}
	
	$r = $db->query(sprintf("select customer_id from invoicing.customers where customer_id like '%s%%'", 
		$db->escape_string($prefix)));

	if($r->num_rows)
	{
		$max = "00";
		
		while(list($customer_id) = $r->fetch_row())
		{
			if(preg_match("/[0-9][0-9]$/", $customer_id) && substr($customer_id, -2) > $max)
				$max = substr($customer_id, -2);
		}
		
		if($max < 99)
			return $prefix.str_pad($max+1, 2, "0", STR_PAD_LEFT);
		else
			throw new Exception("Customer id suffix exceeded {$prefix}99", 10600);
	}
	else
		return $prefix . "01";
}


function add_bill_to_address($data, $customer_id, $db)
{	
	$r = $db->query(sprintf("select customer_id from invoicing.bill_to_address where customer_id = '%s'", 
		$db->escape_string($customer_id)));
	
	$bill_to_data = Array(
		"customer_id"			=> $customer_id,
		"name"					=> $data['customer']['billing_address']['first_name']." ".$data['customer']['billing_address']['last_name'],
		"bill_attention_line"	=> $data['customer']['billing_address']['attn'],
		"bill_address_line1"	=> $data['customer']['billing_address']['address1'],
		"bill_address_line2"	=> $data['customer']['billing_address']['address2'],
		"bill_city"				=> $data['customer']['billing_address']['city'],
		
		"bill_state"			=> (($data['customer']['billing_address']['state'] == "") ? 
			(!empty($data['customer']['billing_address']['nus_state']) ? $data['customer']['billing_address']['nus_state'] : "") :
			$data['customer']['billing_address']['state']),
			
		"bill_zip"				=> $data['customer']['billing_address']['zip'],
		"bill_country"			=> $data['customer']['billing_address']['country'],
	);
	
	if($r->num_rows)
	{
		$query = "update invoicing.bill_to_address set ";
		foreach($bill_to_data as $field => $value)
		{
			if($field == "customer_id")
				continue;
				
			$query .= sprintf("`%s` = '%s', ", $db->escape_string($field),
				$db->escape_string($value));
		}
		
		$query = substr($query, 0, -2) . sprintf(" where customer_id = '%s'", $db->escape_string($customer_id));
		 
		$db->query($query);
	}
	else
	{
		$field_part = $value_part = "";
		foreach($bill_to_data as $field => $value)
		{
			$field_part .= "`".$db->escape_string($field)."`, ";
			$value_part .= "'".$db->escape_string($value)."', ";
		}
		$field_part = substr($field_part, 0, -2);
		$value_part = substr($value_part, 0, -2);
		
		$query = sprintf("insert into invoicing.bill_to_address (%s) values (%s)", $field_part, $value_part);
		
		$db->query($query);
	}
}


function generate_bins_id($invoicing)
{
	$invoicing->direct_query("select item_number from tbl_BINs where item_number like 'W%'");
	
	if($invoicing->num_rows() == 0)
		return "W000001";
	else
	{
		$max = "000000";
		
		while(list($item_number) = $invoicing->fetch_row())
		{
			if(preg_match("/^W[0-9]{6}$/", $item_number) && substr($item_number, 1) > $max)
				$max = substr($item_number, 1);
		}
	}
	
	if($max < "999999")
		return "W".str_pad($max+1, 6, "0", STR_PAD_LEFT);
	else
		throw new Exception("Buy-it-now item number exceeded W999999.". 10600);
}


function generate_total_sales_id($db)
{
	$r = $db->query("select max(ebay_item_number) from invoicing.sales where ebay_item_number regexp '^W[0-9]{6}$'");
	
	list($max) = $r->fetch_row();
	
	if(is_null($max))
		return "W000001";
	
	if(++$max < "W999999")
		return $max;
	else
		throw new Exception("Item number exceeded W999999. $max". 10600);
}


function add_credit_remaining($amount, $data, $customer_id, $db)
{
	$sales_data = Array(
		"ebay_title"		=> "Remaining Credit",
		"ebay_item_number"	=> "credit",
		"ebay_email"		=> $data['customer']['email'],
		"price"				=> $amount,
		"quantity"		=> "1", 
		"customer_id"		=> $customer_id,
	);
	
	$field_part = $value_part = "";
	foreach($sales_data as $field => $value)
	{
		$field_part .= "`".$db->escape_string($field)."`, ";
		$value_part .= "'".$db->escape_string($value)."', ";
	}
	$field_part = substr($field_part, 0, -2);
	$value_part = substr($value_part, 0, -2);
	
	$db->query(sprintf("insert into invoicing.sales (%s) values (%s)", $field_part, $value_part));
	
	return $db->insert_id;
}


function add_sales_tax_item($data, $invoice_number, $customer_id, $db)
{
	$sales_data = Array(
		"ebay_title"		=> "Missouri Sales Tax",
		"ebay_item_number"	=> "salestax",
		"ebay_id"			=> "website",
		"ebay_email"		=> $data['customer']['email'],
		"price"				=> $data['order']['tax'],
		"invoice_number"	=> $invoice_number,
		"quantity"		=> "1", //$data['order']['items'][$item_index]['Quantity'],
		"customer_id"		=> $customer_id,
	);
	
	$field_part = $value_part = "";
	foreach($sales_data as $field => $value)
	{
		$field_part .= "`".$db->escape_string($field)."`, ";
		$value_part .= "'".$db->escape_string($value)."', ";
	}
	$field_part = substr($field_part, 0, -2);
	$value_part = substr($value_part, 0, -2);
	
	$db->query(sprintf("insert into invoicing.sales (%s) values (%s)", $field_part, $value_part));
}

function generate_bins_date($timestamp)
{
	if(in_array(date("w", $timestamp), array(0, 2, 4)))
	{
		return date("Y-m-d", strtotime("+1 day", $timestamp));
	}
	else
	{
		return date("Y-m-d", $timestamp);
	}
}


function add_item_to_sales($data, $item_index, $customer_id, $db)
{
	if(is_array($data['order']['items'][$item_index]['Amount']))
	{
		$price = $data['order']['items'][$item_index]['Amount']['_'];
	}
	else
	{
		$price = $data['order']['items'][$item_index]['Amount'];
	}	
	
	$sales_data = Array(
		"date"				=> generate_bins_date($data['timestamp']),
		"ebay_item_number"	=> $data['order']['items'][$item_index]['Number'],
		"ebay_title"		=> $data['order']['items'][$item_index]['Name'],
		"ebay_id"			=> "website",
		"ebay_email"		=> $data['customer']['email'],
		"price"				=> $price,
		"shipping_notes"	=> "BIN",
		"quantity"			=> "1", //$data['order']['items'][$item_index]['Quantity'],
		"customer_id"		=> $customer_id,
		"combine_type"		=> $data['order']['items'][$item_index]['combine_type'],
	);
	
	$r = $db->query("select * from listing_system.new_specialnotes_shiptype ".
		"where snst_id = '".$data['order']['items'][$item_index]['combine_type']."'");
	
	if($r->num_rows)
	{
		$row = $r->fetch_assoc();
		
		$sales_data['shipping_notes'] =  empty($row['snst_amount']) ? "ACTUAL COST OF SHIPPING" : "$".$row['snst_amount'];
		$sales_data['shipping_notes'] .= " - ".$row['snst_text'];
	}
	
	$field_part = $value_part = "";
	foreach($sales_data as $field => $value)
	{
		$field_part .= "`".$db->escape_string($field)."`, ";
		$value_part .= "'".$db->escape_string($value)."', ";
	}
	$field_part = substr($field_part, 0, -2);
	$value_part = substr($value_part, 0, -2);
	
	$db->query(sprintf("insert into invoicing.sales (%s) values (%s)", $field_part, $value_part));
}

function add_item_to_fixed_price_sales($data, $item_index, $customer_id, $db, $quote_only = false)
{
	if(is_array($data['order']['items'][$item_index]['Amount']))
	{
		$price = $data['order']['items'][$item_index]['Amount']['_'];
	}
	else
	{
		$price = $data['order']['items'][$item_index]['Amount'];
	}	
	
	$sales_data = Array(
		"date_sold"			=> generate_bins_date($data['timestamp']),
		"item_number"		=> $data['order']['items'][$item_index]['Number'],
		"title"				=> $data['order']['items'][$item_index]['Name'],
		"user_id"			=> "website",
		"user_email"		=> $data['customer']['email'],
		"price"				=> $price,
		"copied_TotSales"	=> "-1",
		"BIN_price_updated"	=> "-1",
		"qnty"				=> "1", //$data['order']['items'][$item_index]['Quantity'],
	);
	
	if($quote_only === false)
		$sales_data['invoiced'] = "-1";
	
	if(!preg_match("/^[0-9]{4}/", $sales_data['title']))
		throw new exception("Fixed price title '$sales_data[title]' does not start with four numbers.", 10600);
	
	$field_part = $value_part = "";
	foreach($sales_data as $field => $value)
	{
		$field_part .= "`".$db->escape_string($field)."`, ";
		$value_part .= "'".$db->escape_string($value)."', ";
	}
	$field_part = substr($field_part, 0, -2);
	$value_part = substr($value_part, 0, -2);
	
	$db->query(sprintf("insert into invoicing.fixed_price_sales (%s) values (%s)", $field_part, $value_part));
	
	$r = $db->query("select 1 from listing_system.`00-00-00 BINs (BINs)` where ".
		"lot_number = '".$db->escape_string(substr($sales_data['title'], 0, 4))."'");
		
	if($r->num_rows != 1)
		throw new exception("I couldn't find a record in `00-00-00 BINs (BINs)` that matched the title '$sales_data[title]'.", 10600);
	
	$db->query("update listing_system.`00-00-00 BINs (BINs)` set ebay_email = '1' where ".
		"lot_number = '".$db->escape_string(substr($sales_data['title'], 0, 4))."'");
}

function add_item_to_bins($data, $item_index, $customer_id, $invoicing)
{
	$item_number = generate_bins_id($invoicing);
	
	$bins_data = Array(
		"date_sold"		=> date("m/d/Y", $data['timestamp']),
		"item_number"	=> $item_number,
		"title"			=> $data['order']['items'][$item_index]['Name'],
		"price"			=> $data['order']['items'][$item_index]['Amount'],
		"user_id"		=> $customer_id,
		"user_email"	=> $data['customer']['email'],
		"qnty"			=> $data['order']['items'][$item_index]['Quantity'],
		"invoiced"		=> "-1",
		"copied_TotSales"=> "-1",
	);
	
	$field_part = $value_part = "";
	foreach($bins_data as $field => $value)
	{
		$field_part .= "`".$invoicing->escape_string($field)."`, ";
		$value_part .= "'".$invoicing->escape_string($value)."', ";
	}
	$field_part = substr($field_part, 0, -2);
	$value_part = substr($value_part, 0, -2);
	
	$query = sprintf("insert into tbl_BINs (%s) values (%s)", $field_part, $value_part);

	$invoicing->direct_query($query);
	
	return $item_number;
}


function add_credit_card($cc_details, $customer_id, $db)
{
	if($cc_details['type'] == "Discover")
		$cc_details['type'] = "Discover Card";
	
	$cc_data = Array(
		"customer_id"	=> $customer_id,
		"cc_name"		=> $cc_details['name'],
		"cc_num"		=> $cc_details['number'],
		"cc_CVC"		=> $cc_details['cvc'],
		"cc_exp"		=> str_pad(str_replace("/", "", $cc_details['expires']), 4, "0", STR_PAD_LEFT),
		"type_of_cc"	=> $cc_details['type'],
		"date_removed"	=> null,
	);
	
	$db->query(assemble_insert_query3($cc_data, "invoicing.tbl_cc", $db, true));
}


function credit_card_update_email($data, $db)
{
	$r = $db->query("select customers.customer_id ".
		"from invoicing.customers ".
		"join autoship using(customer_id) ".
		"where customers.customers_id = '".$db->escape_string($data['customers_id'])."'");
	
	if($row = $r->fetch_assoc())
	{
		Mailer::mail(
			"aaron@emovieposter.com, lana@emovieposter.com", 
			"Autoship customer credit card update", 
			"Autoship customer ".$row['customer_id']." added or updated a credit card. ".
			"<a href='http://poster-server/wiki/index.php/Autoship_customer_credit_card_update'>About this email</a>",
			array("Content-Type" => "text/html"));
	}
}


function add_credit_card_from_message($data, $db)
{
	$db->query("set @userId = -1;");
		
	if(empty($data['card']['cc_id']))
	{
		$db->query("insert into invoicing.tbl_cc set ".
			"cc_name = '".$db->escape_string($data['card']['cc_name'])."', ".
			"cc_num = '".str_replace(" ", "", $db->escape_string($data['card']['cc_num']))."', ".
			"cc_exp = '".$db->escape_string($data['card']['cc_exp'])."', ".
			"type_of_cc = '".$db->escape_string($data['card']['type_of_cc'])."', ".
			"customers_id = '".$db->escape_string($data['customers_id'])."', ".
			"customer_id = (select customer_id from invoicing.customers where customers_id = '".$db->escape_string($data['customers_id'])."') ".
			"on duplicate key update cc_name = '".$db->escape_string($data['card']['cc_name'])."', ".
				"cc_num = '".$db->escape_string($data['card']['cc_num'])."', ".
				"cc_exp = '".$db->escape_string($data['card']['cc_exp'])."', ".
				"type_of_cc = '".$db->escape_string($data['card']['type_of_cc'])."', ".
				"date_removed = null");
	}
	else
	{
		$query = "update invoicing.tbl_cc set ";
		foreach($data['card'] as $k => $v)
		{
			$query .= "`$k` = '".$db->escape_string($v)."', ";
		}
		$query = substr($query, 0, -2)." where cc_id = '".$db->escape_string($data['card']['cc_id'])."'";
		
		$db->query($query);
	}
	
	return true;
}


function add_invoice($data, $customer_id, $invoice_number, $transaction_id, $notes, $shipping_charged, $shipping_method, $db)
{
	require_once("/webroot/invoicing/backend/customer.inc.php");
	
	$customerWrite = new CustomerWrite($db, null);
	
	if(array_key_exists("credit_card", $data['payment']))
		$payment_method = $data['payment']['credit_card']['type'];
	elseif(array_key_exists("credit", $data['payment']))
		$payment_method = "Credit on account";
	elseif(array_key_exists("paypal", $data['payment']))
		$payment_method = "PayPal";
	else
		$payment_method = "Other";
	
	$extra_info = array_filter(Array(
		$data['comments'], 
		($data['order']['shipping'] == 0 ? "Needs shipping quote." : ""),
		(!empty($transaction_id) ? "P=$transaction_id;" : ""),
	), "strlen");
	
	if(array_key_exists("credit_card_onfile", $data['payment']))
	{
		if(empty($data['payment']['credit_card_onfile_update']))
		{
			$extra_info[] = "Customer has instructed us to use credit card on file.";
		}
		else
		{
			$db->query(sprintf("update invoicing.tbl_cc set cc_exp = '%s', cc_CVC = '%s' where cc_id = '%s'", 
				$db->escape_string(str_pad(str_replace("/", "", $data['payment']['credit_card_onfile_update']['expires']), "4", "0", STR_PAD_LEFT)),
				$db->escape_string(trim($data['payment']['credit_card_onfile_update']['cvc'])), 
				$db->escape_string($data['payment']['credit_card_onfile_update']['id'])));
				
			$r = $db->query(sprintf("select substring(cc_num, -4) as last4, cc_num, type_of_cc from tbl_cc where cc_id = '%s'",
				$db->escape_string($data['payment']['credit_card_onfile_update']['id'])));
			
			if($r->num_rows)
			{
				list($last4, $cc_num, $type_of_cc) = $r->fetch_row();
				
				$extra_info[] = "Use card $last4";
				
				$payment_method = $type_of_cc;
				
				$cc_which_one = $cc_num;
			}
			else
				throw new Exception("I expected to see a tbl_cc record with id #".$data['payment']['credit_card_onfile_update']['id'].", but it does not exist.", 10050);
		}
	}
	else if(array_key_exists("credit_card", $data['payment']))
	{
		$extra_info[] = "Use card ".substr($data['payment']['credit_card']['number'], -4);
	}

	if(!empty($notes))
		$extra_info[] = $notes;
	
	$shipping_address = $customerWrite->get_primary_address2($customer_id);
	$pobox = substr(preg_replace("/[^a-z]/i", "", strtolower($shipping_address['ship_address_line1'])), 0, 5) == "pobox";
	
	
	$invoice_data = Array(
		"invoice_number"	=> strval($invoice_number),
		"customer_id"		=> $customer_id,
		"shipping_charged"	=> $shipping_charged,
		"payment_method"	=> $payment_method,
		"who_did"			=> "auto2",
		"extra_info"		=> implode("\r\n", $extra_info),
		"cc_which_one"		=> (empty($data['payment']['credit_card']['number']) ? 
			(empty($cc_which_one) ? null : $cc_which_one) : 
			$data['payment']['credit_card']['number']),
		"shipto_address_id" => $customerWrite->get_address_id($shipping_address),
		"billto_address_id" => $customerWrite->get_address_id(
					$customerWrite->get_billing_address_legacy($customer_id)),
	);
	
	if(is_null($shipping_method))
	{
		//P.O. Boxes should never say "UPS"
		if($pobox)
			$invoice_data['shipping_method'] = "Priority Mail";
		else
			$invoice_data['shipping_method'] = "UPS/Priority Mail";
	}
	else
	{
		$invoice_data['shipping_method'] = $shipping_method;		
	}
	
	$field_part = $value_part = "";
	foreach($invoice_data as $field => $value)
	{
		$field_part .= "`".$db->escape_string($field)."`, ";
		$value_part .= "'".$db->escape_string($value)."', ";
	}
	$field_part = substr($field_part, 0, -2);
	$value_part = substr($value_part, 0, -2);
	
	$query = sprintf("insert into invoices (%s) values (%s)", $field_part, $value_part);
	
	$db->query($query);
}


function next_invoice_number($db)
{
	$r = $db->query(sprintf("select max(invoice_number) from invoicing.invoices ".
		"where invoice_number >= '%s' and invoice_number <= '%s'",
		INVOICE_NUMBER_MIN, INVOICE_NUMBER_MAX));

	if($r->num_rows)
	{
		list($invoice_number) = $r->fetch_row();
		
		if($invoice_number === NULL)
			return INVOICE_NUMBER_MIN;
		
		if($invoice_number >= INVOICE_NUMBER_MAX)
			throw new Exception("'$invoice_number' exceeds the maximum allowed invoice number.", 10600);
		else
			return $invoice_number+1;
	}
	else
		return INVOICE_NUMBER_MIN;
}

function ph_invoice_items($db, $invoice_number)
{
	$r = $db->query("select ebay_item_number from invoicing.sales ".
		"where price > 0 and invoice_number = '".$db->escape_string($invoice_number)."' and ebay_item_number != 'salestax'");
	
	$list = array();
	
	while(list($item_number) = $r->fetch_row())
	{
		$list[] = $item_number;
	}
	
	sort($list);
	
	return $list;
}

function check_ph_invoice($db, $invoice_number)
{
	$r = $db->query("select package_id, invoice_number from invoicing.sales ".
		"where `reference` = '".$db->escape_string($invoice_number)."'");
	
	$row = $r->fetch_assoc();
	
	if($row === false)
		throw new exception("No 'Pay & Hold Invoice #' item present for $invoice_number");
	elseif(!empty($row['invoice_number']))
		throw new exception("Pay & Hold Invoice #$invoice_number is already assigned to an invoice (#$row[invoice_number])", 10015);
		
	return $row['package_id'];
}

function assign_ph_invoice_number($db, $child_invoice, $invoice_number)
{
	$db->query("update invoicing.sales ".
		"set invoice_number = '".$db->escape_string($invoice_number)."' ".
		"where `reference` = '".$db->escape_string($child_invoice)."'");
}

?>
