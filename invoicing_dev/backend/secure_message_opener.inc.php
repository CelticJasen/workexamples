<?

class Secure_Message_Opener
{
	const PVT_KEY_PASS = "How come nobody ever uses spaces in their passphrases?";
	
	function __construct()
	{
		$this->private_key = openssl_pkey_get_private("file:///data/www/checkout/private/private.key", self::PVT_KEY_PASS);
	}
	
	function open($data, $key)
	{
		if(false == openssl_open($data, $serialized, $key, $this->private_key))
	         throw new Exception("Could not decrypt", 10010);
		return unserialize($serialized);
	}
}


class Orders
{
	static function processed($date, $db)
	{
		require_once("/webroot/includes/time.inc.php");
		$secure_message_opener = new Secure_Message_Opener();
		
		$r = $db->query("select website_orders.*, customer_id ".
			"from invoicing.website_orders ".
			"left join invoicing.customers using(email) ".
			"where processed like '$date %' ".
			"order by timestamp desc");
		
		$data = array();
		
		while($row = $r->fetch_assoc())
		{
			try
			{
				$data = array_merge($data, self::process_row($row, $db, $secure_message_opener));
			}
			catch(exception $e)
			{
				$data[] = array("pending" => "Error!");
				email_error($e->__toString());
			}
		}
		
		return $data;
	}
	
	static function unprocessed($db)
	{
		require_once("/webroot/includes/time.inc.php");
		$secure_message_opener = new Secure_Message_Opener();
		
		$r = $db->query("select website_orders.*, customer_id ".
			"from invoicing.website_orders ".
			"left join invoicing.customers using(email) ".
			"where processed is null ".
			"order by timestamp desc");
		
		$data = array();
		
		while($row = $r->fetch_assoc())
		{			
			try
			{
				$data = array_merge($data, self::process_row($row, $db, $secure_message_opener));
			}
			catch(exception $e)
			{
				$data[] = array();
			}
		}
		
		//Mailer:mail('steven@emovieposter.com', 'DEBUG - ' . __FILE__,var_export($data,true));
		
		return $data;
	}
	
	static function process_row($row, $db, $secure_message_opener)
	{
		$unserialized = $secure_message_opener->open($row['data'], $row['key']);
		
		if(isset($unserialized[0]))
			$orders = $unserialized;
		else
			$orders = array($unserialized);
		
		unset($row['data']);
		
		$output = array();
				
		foreach($orders as $k => $order)
		{
			if(!is_numeric($k))
				continue;
			
			$order['id'] = $row['id'];
			
			if(!empty($row['data2']) && isset($order['payment']) && array_key_exists("paypal", $order['payment']))
			{
				$extra = $secure_message_opener->open($row['data2'], $row['key2']);	
				
				if(empty($row['processed']) && !empty($extra) && $extra->DoExpressCheckoutPaymentResponseDetails->PaymentInfo->PaymentStatus != "Completed")
				{
					$r = $db->query("select 1 from invoicing.paypal_notifications ".
						"where payment_status = 'Completed' and txn_id = '".$db->escape_string($extra->DoExpressCheckoutPaymentResponseDetails->PaymentInfo->TransactionID)."'");
						
					if($r->num_rows)
					{
						$order['pending'] = "<b style='color: green'>Cleared</b>";
					}
					else
					{
				
						$a = new DateTime($row['timestamp']);
						$b = new DateTime(date("Y-m-d"));
						
						if($a->diff($b)->format('%a') > 3)
							$order['pending'] = "<b style='color: red'>Pending</b>";
						else
							$order['pending'] = "<b>Pending</b>";
					}
				}
				else
				{
					$order['pending'] = "";
				}
			}
			
			$order['contains'] = array();
			
			
			
			foreach($order['order']['items'] as $i)
			{
				if(preg_match("/^F[0-9]+/i", $i['Number']))
				{
					$order['contains'][] = "fixed";
					break;
				}
			}
			
			$order['contains'] = implode(",", $order['contains']);
			$order['customer'] = $order['customer'];
			$order['ago'] = Time::fuzzy(strtotime($row['timestamp']));
			
			if($order['op'] == "quote_request")
				$order['payment'] = "quote request";
			
			if(is_array($order['payment']))
				$order['payment'] = implode("<br />", array_keys($order['payment']));
			
			
			
			$order['autoship'] = (empty($order['autoship']) ? "" : "<img src='/includes/graphics/truck16.png' />");
			
			if(!empty($order['quote_packages']))
				$order['quote_packages_count'] = count($order['quote_packages'])." pkg".(count($order['quote_packages']) != 1 ? "s" : "");
			
			$output[] = $order;
		}

//Mailer:mail('steven@emovieposter.com', 'DEBUG - ' . __FILE__,var_export($output,true));
		
		return $output;
		
		
		
			
		
		
	}
}


?>
