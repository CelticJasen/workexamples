<?php

class Returns
{
	function __construct($db, $wdb, $blab, $user_id = null)
	{
		require_once("/webroot/includes/time.inc.php");
		require_once("/webroot/includes/Mustache/Autoloader.php");
		Mustache_Autoloader::register();
		
		$this->db = $db;
		$this->wdb = $wdb;
		$this->blab = $blab;
		$this->mustache = new Mustache_Engine;
		$this->user_list = $this->user_list();
		$this->user_id = $user_id;
	}
	
	
	function user_list()
	{
		$users = array();
		$r = $this->db->query("select * from `poster-server`.users ");

		while($row = $r->fetch_assoc())
		{
			$users[$row['id']] = $row;
		}

		return $users;
	}
	
	
	/**
	 * Calls a method in this class, times how long it took
	 * to run, and outputs the time using Blab.
	 */
	function call($method, $args)
	{
		$time = microtime(true);
		
		$ret = call_user_func_array(array($this, $method), $args);
		
		$this->last_interval = microtime(true) - $time;
		
		$this->blab->blab("Method '$method' took ".$this->last_interval." seconds to complete.");
		
		return $ret;
	}
	
	
	function validate($data)
	{
		
	}
	
	
	function delete($return_id, $user_id, $reason)
	{
		$return = $this->get($return_id);
		
		$emails = $this->generate_emails($return['data'], $return_id);
		
		$this->undo_consignments_table($return['data']);
		
		
		$this->add_blocked_bidder_delete_note($return['data'], $user_id, $reason);
		$this->add_delete_note($return['data'], $reason);
		
		$return['data']['reason_deleted'] = $reason;
		$return['data']['who_deleted'] = $user_id;
		$return['data']['date_deleted'] = date("Y-m-d H:i:s");
		
		$this->db->query(assemble_update_query(
			"listing_system.`returns`", 
			array(
				"data" => json_format(json_encode2($return['data'])),
				"status" => "deleted",
			), 
			"return_id = '$return_id'", 
			$this->db));
		
		$body = $this->mustache->render(
			file_get_contents("templates/email.html"),
			$emails[0]['data']);
		
		Mailer::mail("aaron@emovieposter.com, phil@emovieposter.com", 
			"Return #".$return['return_id']." deleted by ".$this->user_list[$user_id]['full_name'],
			"Reason given: <pre>".$reason."</pre><hr />".$body, array("Content-Type" => "text/html"));
		
		return true;
	}
	
	
	function edit($return_id, $data, $user_id)
	{
	 	$old_data = $this->get($return_id);
		
		if(!empty($data['package']) && !empty($data['package']['package_location']) && !empty($data['refund']) && !empty($data['refund']['refund_date']))
			$status = "received/refunded";
		elseif(!empty($data['package']) && !empty($data['package']['package_location']))
			$status = "received";
		elseif(!empty($data['refund']) && !empty($data['refund']['refund_date']))
			$status = "refunded";
		else
			$status = "started";
			
		if($data['details']['Invoice to Bruce?'] != $old_data['details']['Invoice to Bruce?'] || 
			$data['details']['Reason for return'] != $old_data['details']['Reason for return'] || 
			$data['details']['Reason for invoicing to Bruce'] != $old_data['details']['Reason for invoicing to Bruce'])
		{
			$this->update_consignments_table($data, true);
		}
		
		$this->db->query(assemble_update_query(
			"listing_system.`returns`", 
			array(
				"data" => json_format(json_encode2($data)),
				"status" => $status,
			), 
			"return_id = '$return_id'", 
			$this->db));
		
		return $return_id;
	}
	
	

	function finish($data, $return_id, $user_id)
	{

    
		if($data['details']['Is this a cancellation?'] == "yes"){
		  if($data['details']['Invoice to Bruce?'] == "yes"){
        foreach($data['items'] as $item){
          $r = $this->db->query("select 1 from invoicing.sales ".
            "where ebay_item_number = '".$item['item']['ebay_item_number']."' AND ebay_email!='mail@emovieposter.com'");
            
          if($r->num_rows)
          {
            throw new exception("Item \"".$item['item']['ebay_title']."\" has not yet been deleted from Invoicing.");
          }
        }
      }else{
      
  			foreach($data['items'] as $item){
  				$r = $this->db->query("select 1 from invoicing.sales ".
  					"where ebay_item_number = '".$item['item']['ebay_item_number']."'");
  					
  				if($r->num_rows)
  				{
  					throw new exception("Item \"".$item['item']['ebay_title']."\" has not yet been deleted from Invoicing.");
  				}
  			}
      }
		}
		
		$this->db->query("update listing_system.`returns` set `status` = 'finished' ".
			"where return_id = '$return_id'");
		
		return true;
	}
	
	
	
	function start($data, $customer_id, $user_id)
	{
		$this->db->query(assemble_insert_query3(
			array(
				"who_started" => $user_id,
				"customer_id" => $customer_id,
				"started" => date("Y-m-d H:i:s"),
				"status" => "started",
				"data" => json_format(json_encode2($data)),
				"customers_id" => $data['customers_id'],
			),
			"listing_system.`returns`",
			$this->db,
			false
		));
		
		$return_id = $this->db->insert_id;
		
		$data['return_id'] = $return_id;
		
		$this->update_consignments_table($data);
		
		$this->update_blocked_bidders($data, $user_id);
		
		return $return_id;
	}
	
	
	
	function update_blocked_bidders($data, $user_id, $text = null)
	{
		if(is_null($text))
			$text = $this->blocked_bidder_text($data, $user_id);
		
		list($first_name) = explode(" ", $this->user_list[$user_id]['full_name']);
		
		$r = $this->db->query("select coalesce(Username, t1.email), t1.email ".
			"from invoicing.customers t1 ".
			"left join invoicing.aa_customers using(email) ".
			"where customers_id = '$data[customers_id]'");
			
		list($username, $email) = $r->fetch_row();
		
		$r = $this->db->query("select ebay_id, reason ".
			"from invoicing.tbl_blocked_bidders ".
			"where ebay_id = '".$this->db->escape_string($username)."'");
		
		if($r->num_rows)
		{
			list($ebay_id, $reason) = $r->fetch_row();
			
			$reason = rtrim($reason)."; ".$text;
			
			$this->db->query("update invoicing.tbl_blocked_bidders ".
				"set reason = '".$this->db->escape_string($reason)."', ".
				"date_blocked = now(), ".
				($data['details']['Is the customer being blocked over this?'] == "yes" ? "how_blocked = \"Blocked\", " : "") . 
				"who_last_changed = '".$this->db->escape_string($first_name)."' ".
				"where ebay_id = '".$this->db->escape_string($username)."'");
		}
		else
		{
			$reason = $text;
			
			$this->db->query(assemble_insert_query3(
				array(
					"ebay_id" => $username,
					"email" => $email,
					"who_last_changed" => $first_name,
					"how_blocked" => ($data['details']['Is the customer being blocked over this?'] == "yes" ? "Blocked" : "Unblocked"),
					"reason" => $reason,
					"date_blocked" => date("Y-m-d H:i:s"),
				),
				"invoicing.tbl_blocked_bidders",
				$this->db,
				false
			));
		}
	}
	
	function add_blocked_bidder_delete_note($data, $user_id, $delete_note)
	{
		$text = date("m/d/Y")." Return was canceled because \"".$delete_note."\" (";
		
		foreach($data['items'] as $item)
		{
			$text .= $item['item']['ebay_item_number']."=$".number_format($item['item']['consignment_price']).", ";
		}
		$text = substr($text, 0, -2);
		
		$text .= "). ";
		$text .= $this->user_list[$user_id]['full_name'];
		
		$this->update_blocked_bidders($data, $user_id, $text);
	}
	
	private function blocked_bidder_item_summary($data)
	{
		$text = "";
		
		foreach($data['items'] as $item)
		{
			$text .= $item['item']['ebay_item_number'].", ";
		}
		$text = substr($text, 0, -2);
		
		
		/*
			The below is what Phil asked for. See Re: AARON 2ND TWEAK TO THIS ONE - ANGIE Return - harold.amberg@yahoo.com.
			2017-06-07 AK
		*/
		$text .= " (";
		
		if(!empty($data['details']['Reduce customer\'s price to']))
		{
			$text .= " new price \$".$data['details']['Reduce customer\'s price to']."; ";
		}
		
		if(!empty($data['details']['Amount to refund or credit']))
		{
			switch($data['details']['Credit, Refund, or None?'])
			{
				case "refund":
					$text .= "refunded \$".floatval($data['details']['Amount to refund or credit']).")";
					break;
				
				case "credit":
					$text .= "wants credit of \$".floatval($data['details']['Amount to refund or credit']).")";
					break;
					
				case "unknown":
					$text .= "Pending customer input on Credit or Refund \$".floatval($data['details']['Amount to refund or credit']).")";
					break;
				
				default:
					throw new exception("'Amount to refund or credit' set, but 'Credit, Refund, or None?' not set to 'credit' or 'refund' or 'unknown'");
					break;
			}
		}
		else
		{
			foreach($data['items'] as $item)
			{
			  /*
          Subject: "STEVEN Fwd: PHIL : P.S. SUPER URGENT FOR PHIL Fwd: ANGIE Cancellation - adam.roberts98@gmail.com - Return #349" 
          From: "eMoviePoster.com - Bruce and/or Phil" <phillip@emovieposter.com>
          Date: Tue, 11 Jun 2019 13:03:29 -0500


          I believe Angie is saying this:
          We set "Is this a cancellation?" to Yes, and since the customer didn't pay anything, we rightfully set "Amount to refund or credit" to zero. However, for the Bidder Account Admin note, it output:
          "Canceled Purchase(s) 5393100 ($0,)It was their fault. "

          But it should have output:
          "Canceled Purchase(s) 5393100 ($256,)It was their fault. "

          Therefore, I'm assuming that the value entered "Amount to refund or credit"was used for the Bidder Account Admin note, but instead, it should use the price of the item.
         */
         
				//$text .= "$".number_format($item['item']['consignment_price']).", ";
				
				$text .= "$".number_format($item['item']['sales_price']).", ";
			}
      $text = substr($text, 0, -2);
		}
		
		$text = trim($text, "; ").")";
		
		return $text;
	}
	
	function blocked_bidder_text($data, $user_id)
	{
		$text = date("Y-m-d").": ";
		
		if($data['details']['Is the customer keeping the item?'] == "yes")
		{
			$text .= "Customer is keeping ";
			
			if(is_numeric($data['details']['Reduce customer\'s price to']))
			{
				$text .= "at a reduced price ";
			}
		}
		elseif($data['details']['Is this a cancellation?'] == "yes")
		{
			$text .= "Canceled Purchase(s) ";
		}
		else
		{
			$text .= "Returning ";
		}
		
		$text .= $this->blocked_bidder_item_summary($data);
		
		switch($data['details']['Who made the mistake?'])
		{
			case "us":
				$text .= "It was our fault. ";
				break;
			
			case "customer":
				$text .= "It was their fault. ";
				break;
			
			case "n/a":
				$text .= "Fault N/A. ";
				break;
			
			default:
				$text .= "Fault unspecified. ";
				break;
		}
		
		$text .= "\"".$data['details']['Reason for return']."\". ";
		
		if($data['details']['Is the customer being blocked over this?'] == "yes")
		{
		  
			$text .= "NEVER EVER UNBLOCK PER BRUCE! "; //I've heard that one before. Never say never.
		}
		
		$text .= $this->user_list[$user_id]['full_name'];
		
		return $text;
	}
	
	
	function update_consignments_table($data, $edit = false)
	{
		foreach($data['items'] as $item)
		{
			if(empty($item['item']['ebay_item_number']))
				continue;
			
			if($edit)
			{
				$row = $this->get_consignments_item($item['item']['ebay_item_number']);
			
				$original_row = $this->extract_undo_info($row['notes']);
			}
			else
			{
				$original = $this->get_consignments_item($item['item']['ebay_item_number']);
				$original['when'] = date("Y-m-d H:i:s");
				$original['return_id'] = $data['return_id'];
				$original_notes = $original['notes'];
				unset($original['notes']);
			}
			
			//if((empty($item['item']['payout_id']) && $data['details']['past_buyers'] !== "yes") || 
			//$data['details']['consignor_money'] == "yes")
			
			if($data['details']['Invoice to Bruce?'] == "yes")
			{
				$new = array(
					"High Bidder ID" => "bruce",
					"High Bidder email" => "mail@emovieposter.com",
				);
				
				if(is_numeric($data['details']['Reduce consignment price to']))
				{
					$new['Price'] = $data['details']['Reduce consignment price to'];
				}
				elseif($edit)
				{
					$new['Price'] = $original_row['Price'];
				}
				
				if($edit)
				{
					$new['notes'] = trim(date("m/d/Y ").
						$data['details']['Reason for return']."\n\n".
						$data['details']['Reason for invoicing to Bruce']."\n\n".
						trim($row['notes']));
				}
				else
				{
					$new['notes'] = trim(date("m/d/Y ").
						$data['details']['Reason for return']."\n\n".
						$data['details']['Reason for invoicing to Bruce']."\n\n".
						trim($original_notes)."\n\n".
						"RETURN:".json_encode($original));
				}
				
				$this->update_consignments_item($item['item']['ebay_item_number'], $new);
			}
			elseif($data['details']['Invoice to Bruce?'] != "yes" && $data['details']['Is the customer keeping the item?'] == "no")
			{
				//Item is not on a payout, or we are getting the money back. Change to RETURN/RETURN/0
				
				$new = array(
					"Price" => "0",
				);
				if($data['details']['Is the item being relisted?'] == "yes")
        {
          $new['High Bidder ID'] = "RELIST";
          $new['High Bidder email'] = "RELIST";
        }else if($data['details']['Is this a cancellation?'] == "yes")
				{
					$new['High Bidder ID'] = "CANCELED";
					$new['High Bidder email'] = "CANCELED";
				}
				else
				{
					$new['High Bidder ID'] = "RETURN";
					$new['High Bidder email'] = "RETURN";
				}
					
				if($edit)
				{
					$new['notes'] = trim(date("m/d/Y ").
						$data['details']['Reason for return']."\n\n".
						trim($row['notes']));
				}
				else
				{
					$new['notes'] = trim(date("m/d/Y ").
						$data['details']['Reason for return']."\n\n".
						
						trim($original_notes)."\n\n".
						"RETURN:".json_encode($original));
				}
				
				$this->update_consignments_item($item['item']['ebay_item_number'], $new);
			}
			elseif(is_numeric($data['details']['Reduce consignment price to']))
			{
				$new = array(
					"Price" => $data['details']['Reduce consignment price to'],
				);
				
				if($edit)
				{
					$new['notes'] = trim(date("m/d/Y ").
						$data['details']['Reason for return']."\n\n".
						trim($row['notes']));
				}
				else
				{
					$new['notes'] = trim(date("m/d/Y ").
						$data['details']['Reason for return']."\n\n".
						trim($original_notes)."\n\n".
						"RETURN:".json_encode($original));
				}
				
				$this->update_consignments_item($item['item']['ebay_item_number'], $new);
			}
			else
			{
				//In all cases, update return reason.
				
				$new = array();
				
				if($edit)
				{
					$new['notes'] = trim(date("m/d/Y ").
						$data['details']['Reason for return']."\n\n".
						trim($row['notes']));
				}
				else
				{
					$new['notes'] = trim(date("m/d/Y ").
						$data['details']['Reason for return']."\n\n".
						trim($original_notes)."\n\n".
						"RETURN:".json_encode($original));
				}
				
				$this->update_consignments_item($item['item']['ebay_item_number'], $new);
			}
		}
	}
	
	
	private function update_consignments_item($item_number, $new)
	{
		$this->db->query(assemble_update_query(
			"listing_system.tbl_Current_Consignments", 
			$new, 
			"eBay_Item_Num = '".$this->db->escape_string($item_number)."'",
			$this->db));
	}
	
	
	function undo_consignments_table($data)
	{
		foreach($data['items'] as $item)
		{
			$r = $this->db->query("select `High Bidder ID`, `High Bidder email`, Price, notes, payout_id ".
				"from listing_system.tbl_Current_Consignments ".
				"where eBay_Item_Num = '".$this->db->escape_string($item['item']['ebay_item_number'])."'");
			
			if($r->num_rows != 1)
			{
				throw new exception("Item #".$item['item']['ebay_item_number']." ".
					"has more than one row in tbl_Current_Consignments. I can't handle this situation.");
			}
			
			$row = $r->fetch_assoc();
			
			$original_row = $this->extract_undo_info($row['notes']);
			
			$this->db->query(assemble_update_query(
					"listing_system.tbl_Current_Consignments", 
					array(
						"High Bidder ID" => $original_row['High Bidder ID'],
						"High Bidder email" => $original_row['High Bidder email'],
						"Price" => $original_row['Price'],
						
					),
					"eBay_Item_Num = '".$this->db->escape_string($item['item']['ebay_item_number'])."'",
					$this->db));
		}
	}
	
	
	function extract_undo_info($notes)
	{
		$lines = array_filter(array_map("trim", explode("\n", $notes)), "strlen");
		
		foreach($lines as $line)
		{
			if(substr($line, 0, 7) == "RETURN:")
			{
				return json_decode(substr($line, 7), true);
			}
		}
		
		throw new exception("No undo information found");
	}
	
	
	function generate_emails($data, $return_id)
	{
		$emails = array();
		
		if(!empty($this->user_id))
		{
			$data['submitted_by'] = "Submitted by ".$this->user_list[$this->user_id]['full_name']." on ".date("n/j/Y g:i A");
		}
		
		if(!empty($data['details']['Customer tracking number']))
		{
			require_once("/webroot/invoicing/backend/tracking.inc.php");
			list($data['details']['tracking_link']) = Tracking::url($data['details']['Customer tracking number']);
		}

		 $data['process_now_note'] = "";
		
		if($data['details']['Is the item being returned to us?'] == "yes")
		{
			$return = "Return";
			$recipients = "Clark <clark@emovieposter.com>, Angie <mail@emovieposter.com>";
			$data['greeting'] = "CLARK/ANGIE";
			$data['return_or_keep'] = "Customer is returning:";
			$data['refund_text'] = "<b>Upon receipt:</b><br />";
		}
		elseif($data['details']['Is the customer keeping the item?'] == "yes")
		{
			switch($data['details']['Credit, Refund, or None?'])
			{
				case "credit":
					$return = "CREDIT NOW";
					break;
				
				case "refund":
					$return = "REFUND NOW";
					break;
					
				case "unknown":
					$return = "Pending customer input on Credit or Refund";
					break;
				
				default:
					$return = "Return";
			}
			
			$recipients = "Angie <mail@emovieposter.com>";
			$data['greeting'] = "ANGIE";
			$data['return_or_keep'] = "Customer will be keeping:";
			$data['refund_text'] = "";
			$data['process_now_note'] = "<b style='color: red'>Customer is not returning so process this now.</b><br><br>";
		}
		elseif($data['details']['Is this a cancellation?'] == "yes")
		{
			$return = "Cancellation";
			$recipients = "Angie <mail@emovieposter.com>";
			$data['greeting'] = "ANGIE";
			$data['return_or_keep'] = "Item is being canceled. Be sure to delete the item from the customer account.  Shipping has been notified to give the item(s) to Phil.";
			$data['refund_text'] = "";
		}
		elseif($data['details']['Invoice to Bruce?'] == "yes")
		{
			$return = "Return";
			$recipients = "Angie <mail@emovieposter.com>";
			$data['greeting'] = "ANGIE";
			$data['return_or_keep'] = "Invoice to Bruce:";
			$data['refund_text'] = "";
		}
		elseif($data['details']['Is the customer being blocked over this?'] == "yes")
		{
			$return = "Return";
			$recipients = "Angie <mail@emovieposter.com>";
			$data['greeting'] = "ANGIE";
			$data['return_or_keep'] = "";
			$data['refund_text'] = "";
		}
		else
		{
			$return = "Return";
			$recipients = "Angie <mail@emovieposter.com>";
			$data['greeting'] = "ANGIE";
			$data['return_or_keep'] = "ERROR: Not being returned, ".
				"customer not keeping, not a cancellation, ".
				"not invoice to Bruce, and customer not being blocked over this:";
			$data['refund_text'] = "";
		}
		
		if($data['item'][0]['payment_method'] == "Paid & Hold")
		{
			$data['warning'] = "<b style='color: red'>BE SURE IT IS REMOVED FROM CUSTOMER'S ".
				"<a href='http://poster-server/invoicing/single_invoice.php".
				"?invoice_number=".$data['item'][0]['payment_method']."'>Pay and Hold</a>.</b>";
		}
		
		if($data['details']['Are we getting money back from the consignor?'] == "yes")
		{
			
			$data['consignor_money'] = "";
			foreach($data['items'] as $item)
			{
				$commissionRate = CommissionRate::fromConsignor($item['item']['consignor'], $this->db);
				
				if(empty($item['item']['commission']))
					$item['item']['commission'] = $commissionRate->commission($item['item']['sales_price']);
				
				$amount = number_format(bcsub($item['item']['sales_price'], $item['item']['commission'], 2), 2);
				$data['consignor_money'] .= "<b style='color: red'>Get \$$amount back from ".$item['item']['consignor']." for ".$item['item']['title_45'];
				if($data['details']['Are we returning it to the consignor?'] == "yes"){
				  $data['consignor_money'] .= "<br />Give the item to Clark to be returned to the consignor.";
				}
				$data['consignor_money'] .= "</b><br /><br />";
			}
		}
		elseif($data['details']['Is this a cancellation?'] == "no")
		{
			//$data['consignor_money'] = "We canceled sale so do not get money back from the consignor.<br />";
		}
		
		if($data['details']['Invoice to Bruce?'] == "yes")
		{
			$data['invoice_to_bruce'] = "Invoice to Bruce. ".$data['details']["Reason for invoicing to Bruce"]."<br />";
		}
		elseif($data['details']['Are we getting money back from the consignor?'] == "no")
		{
			//AK Added this block 20170802. See email "Re: AARON URGENT Re: CLARK/ANGIE Return - krsv@plazatheatre.com.au - Return #134"
			$data['invoice_to_bruce'] = "Do not invoice to Bruce. ";
			
			if($data['details']['Is the item being relisted?'] == "yes")
			{
				$data['invoice_to_bruce'] = "It will be re-auctioned for the original consignor. ";
			}
			
			$data['invoice_to_bruce'] = "<br />";
		}
		
		
		if(is_numeric($data['details']['Amount to office expense']))
		{
			$data['office_expense'] = "Make it an office expense in the amount of $".$data['details']['Amount to office expense'].". <br />";
		}
		
		if(is_numeric($data['details']['Reduce customer\'s price to']))
		{
			//Changed from "Reduce customer's price" to "Customer's final price" per Phil "AARON/ANGIE Re: ANGIE CREDIT NOW - harold.amberg@yahoo.com" 20170616 AK
			$data['reduce_customers_price'] = "Customer's final price to $".$data['details']['Reduce customer\'s price to'].". <br />";
		}
		
		if(is_numeric($data['details']['Reduce consignment price to']))
		{
			$data['reduce_consignment_price'] = "Reduce price in tbl_Current_Consignments to $".$data['details']['Reduce consignment price to'].".<br />";
		}
		
		
		
		if($data['details']['Credit, Refund, or None?'] == "refund")
		{
			$action = "<b>Refund: ".$data['details']['Refund method']."</b>";
		}
		elseif($data['details']['Credit, Refund, or None?'] == "credit")
		{
			$action = "<b>Credit</b>";
		}
		elseif($data['details']['Credit, Refund, or None?'] == "none")
		{
			$action = "<b>No refund or credit required.</b>";
		}
		else
		{
			$action = "<b>Ask about refund or credit</b>";
		}
		
		if(!is_numeric($data['details']['Amount to refund or credit']))
			$data['details']['Amount to refund or credit'] = 0;
		
			$data['refund_text'] .= $action." $".number_format($data['details']['Amount to refund or credit'], 2)." for item(s).<br />";
		
		if($data['details']['Amount of original shipping to refund'] > 0)
		{
			$data['refund_text'] .= $action." $".number_format($data['details']['Amount of original shipping to refund'], 2)." for original shipping.<br />";
		}
		else
		{
			$data['refund_text'] .= " Refund original shipping? No<br />";
		}
		
		if($data['details']['Refunding return shipping?'] == "yes")
		{
			$data['refund_text'] .= $action." cost of return shipping";
			
			if(!empty($data['package']['package_postage_cost']))
			{
				$data['refund_text'] .= "(".$data['package']['package_postage_cost'].")";
			}
			
			$data['refund_text'] .= ".<br />";
		}
		elseif($data['details']['Refunding return shipping?'] == "no")
		{
			$data['refund_text'] .= " Refund return shipping? No<br />";
		}
		else
		{
			$data['refund_text'] .= " Refund return shipping? Ask<br />";
		}
		
		$data['refund_text'] = trim($data['refund_text'])."<br />";
		
		if($data['details']['Is the item being relisted?'] == "yes")
		{
			$data['relist'] = "<a href='http://poster-server/wiki/index.php/Relist' style='font-weight: bold'>Yes.</a>";
		}
		elseif($data['details']['Is the item being relisted?'] == "no")
		{
			$data['relist'] = "No.";
		}
		else
		{
			$data['relist'] = "Ask.";
		}
		
		if($data['details']['Show item to Bruce before processing?'] == "yes")
		{
			$data['top'] = "<b style='color:red'>SHOW ITEM TO BRUCE BEFORE PROCESSING</b><br />";
		}
		
		
		
		$rcpts = "aaron@emovieposter.com, phillip@emovieposter.com";
		//$rcpts = "aaron@emovieposter.com";

		//$rctps = $recipients . ", " . $rctps;
		
		$emails[0] = array(
			"recipients" => implode(', ',array($recipients,$rcpts)),
			"subject" => $data['greeting']." ".$return." - ".$data['customer']['email']." - Return #$return_id",
			"data" => $data,
			"params" => array("Content-Type" => "text/html"),
		);
		
		
		
		if($data['details']['Does a claim need to be filed?'] == "yes")
		{
			$data['greeting'] = "LANA";
			
			$data['top'] = "<b>FILE A CLAIM ON THIS RIGHT AWAY!</b><br />";
			
			$emails[1] = array(
				"recipients" => 'Lana <lana@emovieposter.com>, ' . $rcpts,
				"subject" => "LANA File a Claim - ".$data['customer']['email']." - Return #$return_id",
				"data" => $data,
				"params" => array("Content-Type" => "text/html"),
			);
		}
		
		
		if($data['details']['Is this from a Contact Past Buyers?'] == "yes")
		{
			$data['greeting'] = "LANA";
			
			$data['top'] = "<b>THIS WAS FOR A 'CONTACT PAST BUYERS' EMAIL.</b> ".
				"Be sure to note the TXT file accordingly. ";
			
			$emails[2] = array(
				"recipients" => 'Lana <lana@emovieposter.com>, ' . $rcpts,
				"subject" => "Return (Lana) - ".$data['customer']['email']." - Return #$return_id",
				"data" => $data,
				"params" => array("Content-Type" => "text/html"),
			);
		}
		
		
		$data['item_list'] = array();
		foreach($data['items'] as $i)
		{
			$data['item_list'][] = $i['item']['title_45'];
		}
		$data['item_list'] = json_encode2($data['item_list']);
		
		if(!empty($data['refund']['refund_date'])) //Has been filled out by Angie
		{
			if($data['details']['Is the item being relisted?'] == "yes")
			{
				$subject = "RELIST NOW Return Refunded - ".$data['customer']['email'] ;
				$data['relist_link'] = "<a href=\"http://poster-server/relist/?items=" . urlencode($data['item_list']) . "\">Relist item(s) now</a>";
			}
			else
			{
				$data['relist_link'] = "";
				$subject = "Return Refunded - ".$data['customer']['email'];
			}
			
			$emails[4] = array(
				"recipients" => $rcpts,
				"subject" => $subject." - Return #$return_id",
				"body" => $this->mustache->render(
					file_get_contents("templates/return_completed.html"),
					$data
				), 
				"params" => array("Content-Type" => "text/html")
			);
		}
		elseif(!empty($data['package']['package_location']) || !empty($data['package']['package_type'])) //Has been filled out by Clark
		{
			$emails[5] = array(
				"recipients" => 'Angie <mail@emovieposter.com>, ' . $rcpts,
				"subject" => "ANGIE Return Received - ".$data['customer']['email']." - Return #$return_id", //20170208 "ANGIE" added per Phil , "AARON REVISIONS Fwd: Return Received - Ktc1@aol.com"
				"body" => $this->mustache->render(
					file_get_contents("templates/return_received.html"),
					$data
				),
				"params" => array("Content-Type" => "text/html")
			);
		}
		
		if($data['details']['Are we returning it to the consignor?'] == "yes")
		{
			//Added per Phil. See email "AARON Fwd: REVISED CLARK/ANGIE Return - Boundbybooks@live.com - Return #135" AK 20170807
			//STEVEN PLEASE COMPLETE WITHIN 1 WEEK Fwd: Shipping - Create Updated Pullsheet - Return #226
			//REVISED! - STEVEN PLEASE COMPLETE WITHIN 1 WEEK Fwd: Shipping - Create Updated Pullsheet - Return #226
			
			$data['top'] = "<p style='font-size: 110%'>This item is <strong style=\"color:red\">READY</strong> to be returned to the consignor. Refer to the notes below further instruction. If no further instruction is given, ask Bruce your usual questions.</p>";
			
			$emails[6] = array(
        "recipients" => 'Clark <clark@emovieposter.com>, ' . $rcpts,
				"subject" => "CLARK return item to consignor - Return #$return_id",
				"body" => $this->mustache->render(
					file_get_contents("templates/clark_return_to_consignor.html"),
					$data
				),
				"params" => array("Content-Type" => "text/html")
			);
		}
		
		if($data['details']['Is this a cancellation?'] == "yes")
		{
			//Added per Phil. See email "AARON Fwd: ANGIE Cancellation - ragnaroc9292@aol.com - Return #144" AK 20170809
			$data['greeting'] = "SHIPPING";
			
			$data['top'] = "<p style='font-size: 110%'><a href='http://poster-server/pull_sheets/?customer_id=".$data['customer']['customer_id']."'>".
				"Recreate Pullsheet</a> for this customer now.</p>";
			
			$emails[7] = array(
        "recipients" => 'Shipping <shippingdept@emovieposter.com>, ' . $rcpts,
				"subject" => "Shipping - Create Updated Pullsheet - Return #$return_id",
				"body" => $this->mustache->render(
					file_get_contents("templates/shipping_email.html"),
					$data
				),
				"params" => array("Content-Type" => "text/html")
			);
		}

    if(!empty($data['details']['status']) && $data['details']['status']=='finished')
    {
      //Added per Phil. See email "STEVEN REQUEST Fwd: PHIL Re: ANGIE Cancellation - thatse2@ptd.net - Return #287" AK 20181228
      $emails[8] = array(
        "recipients" => 'steven@emovieposter.com',
        "subject" => $data['greeting']." FINISHED ".$return." - ".$data['customer']['email']." - Return #$return_id",
        "data" => $data,
        "params" => array("Content-Type" => "text/html"),
      );
    }
		
		return $emails;
	}
	
	function send_emails($emails, $old_emails = array(), $only_if_changed = false)
	{
		foreach($emails as $k => $email)
		{
			if(!empty($email['body']))
			{
				Mailer::mail($email['recipients'], $email['subject'], $email['body'], $email['params']);
			}
			elseif(!empty($old_emails[$k]))
			{
				$change = 0;
				
				$old_email = $old_emails[$k];
				
				foreach($email['data'] as $k => $v)
				{
					if(!is_string($v) || $k == "return_id")
						continue;
				
					if($v != $old_email['data'][$k])
					{
						$change++;
						
						if($k != "return_id")
							$email['data'][$k] = "<b style='color: red'>".$email['data'][$k]."</b>";
					}
				}
				
				if($email['data']['details']['Reason for return'] != $old_email['data']['details']['Reason for return'])
				{
					$change++;
					$email['data']['details']['Reason for return'] = "<b style='color: red'>".$email['data']['details']['Reason for return']."</b>";
				}
				
				if($email['data']['details']['Extra notes about return'] != $old_email['data']['details']['Extra notes about return'])
				{
					$change++;
					$email['data']['details']['Extra notes about return'] = "<b style='color: red'>".$email['data']['details']['Extra notes about return']."</b>";
				}
					
				//foreach($email['data']['items'] != $old_email['data']['items'])
			
			
				if($change)
				{
					$email['subject'] = "REVISED ".$email['subject'];
					$email['data']['top'] .= "<p><b>Changes have been highlighted in red.</b></p>";
				}
				
				$email['body'] = $this->mustache->render(
					file_get_contents("templates/email.html"),
					$email['data']
				);				
				
				if($only_if_changed ? $change : true) //2017-02-08 Readded per Phil : "AARON REVISIONS Fwd: Return Received - Ktc1@aol.com"
					Mailer::mail($email['recipients'], $email['subject'], $email['body'], $email['params']);
			}
			else
			{
				$email['body'] = $this->mustache->render(
					file_get_contents("templates/email.html"),
					$email['data']
				);
				
				//if($only_if_changed ? $change : true)
				Mailer::mail($email['recipients'], $email['subject'], $email['body'], $email['params']);
			}
		}
	}
	
	
	function process($return)
	{
		$return['who_started'] = $this->user_list[$return['who_started']]['full_name'];
		
		$return['data'] = json_decode($return['data'], true);
		
		$return['data'] = $this->fix_old_versions($return['data']);
		
		if(isset($return['data']['details']['return_reason']))
			$return['version'] = 0;
		elseif(isset($return['data']['details']['Reason for return']))
			$return['version'] = 1;
		elseif(isset($return['data']['details']['How shall we refund?']))
			$return['version'] = 2;
		
		
		$r = $this->db->query("select customer_id, name, email ".
			"from invoicing.customers ".
			"where customers_id = '".$this->db->escape_string($return['data']['customers_id'])."'");
			
		if($r->num_rows)
		{
			$return['data']['customer'] = $r->fetch_assoc();
		}
		
		$return['data']['invoices'] = array();
		
		foreach($return['data']['items'] as $item)
		{
			if(!empty($item['invoice_number']) && !in_array($item['invoice_number'], $return['data']['invoices']))
				$return['data']['invoices'][] = $item['invoice_number'];
		}
		
		return $return;
	}
	
	function fix_old_versions($data)
	{
		if(isset($data['details']['credit']))
		{
			$data['details']['Credit, Refund, or None?'] = $data['details']['credit'];
			unset($data['details']['credit']);
		}
		
		if(isset($data['details']['refund_items']))
		{
			$data['details']['Amount to refund or credit'] = $data['details']['refund_items'];
			unset($data['details']['refund_items']);
		}
		
		return $data;
	}
	
	
	
	function get($return_id)
	{
		$r = $this->db->query("select * ".
			"from listing_system.`returns` ".
			"where return_id = '$return_id'");
		
		$return = $this->process($r->fetch_assoc());		
		
		return $return;
	}
	
	
	function list_returns($all = false)
	{
		$r = $this->db->query("select returns.*, name  ".
			"from listing_system.`returns` ".
			"left join invoicing.customers using(customer_id) ".
			($all ? "where 1 " : "where status not in ('finished', 'deleted') ").
			"order by started desc");
			
		$data = array();
		
		while($row = $r->fetch_assoc())
		{
			$row['started'] = date("m/d h:i a", strtotime($row['started']));
			$row['data'] = json_decode($row['data']);
			$data[] = $row;
		}
		
		return $data;
	}
	
	
	private function get_consignments_item($item_number)
	{
		$r = $this->db->query("select `High Bidder ID`, `High Bidder email`, ".
			"Price, notes, payout_id ".
			"from listing_system.tbl_Current_Consignments ".
			"where eBay_Item_Num = '".$this->db->escape_string($item_number)."'");
		
		if($r->num_rows != 1)
		{
			throw new exception("Item #".$item['item']['ebay_item_number']." ".
				"has more than one row in tbl_Current_Consignments. I can't handle ".
				"this situation.");
		}

		return $r->fetch_assoc();
	}
	
	function add_delete_note($data, $delete_note)
	{
		foreach($data['items'] as $item)
		{
			if(empty($item['item']['ebay_item_number']))
				continue;
			
			$row = $this->get_consignments_item($item['item']['ebay_item_number']);
			
			$new = array(
				"notes" => trim(date("m/d/Y ").
					trim($delete_note)."\n\n".
					trim($row['notes']))
			);
			
			$this->update_consignments_item($item['item']['ebay_item_number'], $new);
		}
	}
}


?>
