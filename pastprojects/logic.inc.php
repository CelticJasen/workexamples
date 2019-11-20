<?php
ini_set("display_errors", "On");


class ReturnLogic
{
	function get_answers()
	{
		return array_merge($this->all_answers, $this->answers);
	}
	
	function get_actions()
	{
		$answers = $this->get_answers();
		$actions = array();
		
		
		if(!empty($answers["Invoice to Bruce?"]) && $answers["Invoice to Bruce?"] == "yes")
		{
			$actions[] = "Change consignments to Bruce";
		}
		elseif($answers['Invoice to Bruce?'] == "no" && $answers['Is the customer keeping the item?'] == "no")
		{
			$actions[] = "Change consignments to RETURN";
			$actions[] = "Change consignments to Price = 0";
		}
		
		if(is_numeric($answers['Reduce consignment price to']))
		{
			$actions[] = "Reduce consignment price to $".($answers['Reduce consignment price to']);
		}
		
		if(!empty($answers["Is the item being returned to us?"]) && $answers["Is the item being returned to us?"] == "no")
		{
			$actions[] = "Leave buyer the same";
		}

		
		return $actions;
	}
	
	function __construct($all_answers, $items, $interaction = false)
	{
		$this->all_answers = $all_answers;
		$this->answers = array();
		
		if($interaction)
			$this->answers['interaction'] = $all_answers[$interaction];
		
		$this->questions = array();
		$this->locked = array();
		$this->unlocked = array();
		
		$this->items = $items;
		
		$this->total = 0;
		
		foreach($items as $item)
		{
			if(isset($item['item']['sales_price']))
				$this->total += $item['item']['sales_price'];
		}
		
		$this->questions["Is the customer being blocked over this?"] = 
			function($answer){
				$this->answers["Is the customer being blocked over this?"] = $answer;
			};
		
		$this->questions["Does the item appear on a payout?"] =
			function($answer){				
				$this->answers["Does the item appear on a payout?"] = $answer;
				
				if($answer == "yes")
				{
					$this->questions["Is this a cancellation?"]("no");
					
					
					if(isset($this->items[0]['item']['consignment_price']) && $this->items[0]['item']['consignment_price'] == 0)
					{
						/*
						I added this behavior because Phil said to.
						See email "Re: Returns Form - account for on payout but at zero dollars".
						I don't think he understands what he's telling me to do, and he
						doesn't want to explain anything. I'm putting this comment here
						for when this breaks so he can realize why this isn't my fault, and
						realize why he needs to explain himself better when he asks for something.
						
						AK 2017-06-02
						*/
						$this->questions['Are we getting money back from the consignor?']("no");
					}
					
					$this->unlocked[] = "Are we getting money back from the consignor?";
					
					$this->locked[] = array("Is this a cancellation?", "Item cannot be a cancellation if it appears on a payout.");
					
				}
				else
				{
					$this->questions["Are we getting money back from the consignor?"]("no");
					$this->locked[] = array("Are we getting money back from the consignor?", "Item does not appear on a payout.");
					$this->unlocked[] = "Is this a cancellation?";
					
				}
			};
		
		/*
			Just for recordkeeping purposes and does not affect the business logic.
		*/
		$this->questions["Reason for return"] = 
			function ($answer){
				$this->answers["Reason for return"] = $answer;
			};
		
		$this->questions["Extra notes about return"] = 
			function ($answer){
				$this->answers["Extra notes about return"] = $answer;
			};
		
		$this->questions["Customer tracking number"] = 
			function ($answer){
				$this->answers["Customer tracking number"] = $answer;
			};
		
		$this->questions["Reason for invoicing to Bruce"] = 
			function ($answer){
				$answer = trim($answer);
				$this->answers["Reason for invoicing to Bruce"] = $answer;
				
				if(!empty($answer))
				{
					$this->unlocked[] = "Invoice to Bruce?";
					$this->questions["Invoice to Bruce?"]("yes");
				}
			};
		
		
		$this->questions["Who made the mistake?"] = 
			function($answer){
				$this->answers["Who made the mistake?"] = $answer;
				
				if($answer == "us")
				{
					$this->questions["Is the customer being blocked over this?"]("no");
				}
			};


		/*
			
		*/
		$this->questions["Is this a cancellation?"] = 
			function($answer){
				$this->answers["Is this a cancellation?"] = $answer;
				
				if($answer == "yes")
				{
					$this->questions["Are we getting money back from the consignor?"]("no");
					$this->questions["Does a claim need to be filed?"]("no");
					$this->questions["Is the item being returned to us?"]("no");
					$this->questions["Amount of original shipping to refund"]("");
					$this->questions["Refunding return shipping?"]("no");
					$this->questions["Amount of item(s) to refund"]("");
					
					$this->locked[] = array("Are we getting money back from the consignor?", "N/A; This is a cancellation.");
					$this->locked[] = array("Does a claim need to be filed?", "N/A; This is a cancellation.");
					$this->locked[] = array("Is the item being returned to us?", "N/A; This is a cancellation.");
					$this->locked[] = array("Amount of original shipping to refund", "N/A; This is a cancellation.");
					$this->locked[] = array("Refunding return shipping?", "N/A; This is a cancellation.");
					$this->locked[] = array("Amount of item(s) to refund", "N/A; This is a cancellation.");
				}
				else
				{
					$this->unlocked[] = "Are we getting money back from the consignor?";
					$this->unlocked[] = "Does a claim need to be filed?";
					$this->unlocked[] = "Is the item being returned to us?";
					$this->unlocked[] = "Amount of original shipping to refund";
					$this->unlocked[] = "Refunding return shipping?";
					$this->unlocked[] = "Amount of item(s) to refund";
				}
			};

		
		$this->questions["Is the customer keeping the item?"] = 
			function($answer){
				$this->answers["Is the customer keeping the item?"] = $answer;
				if($answer == "yes")
				{
					$this->questions["Is the item being returned to us?"]("no");
					$this->locked[] = array("Is the item being returned to us?", "Customer is keeping the item.");
					
					$this->questions["Is the item being relisted?"]("no");
					$this->locked[] = array("Is the item being relisted?", "Customer is keeping the item.");
					
					$this->questions["Are we returning it to the consignor?"]("no");
					$this->locked[] = array("Are we returning it to the consignor?", "Customer is keeping the item.");
					
					$this->questions["Show item to Bruce before processing?"]("no");
					$this->locked[] = array("Show item to Bruce before processing?", "Customer is keeping the item.");
					
					$this->questions["Invoice to Bruce?"]("no");
					$this->locked[] = array("Invoice to Bruce?", "Customer is keeping the item.");
				}
				else
				{
					$this->unlocked[] = "Does a claim need to be filed?";
					$this->unlocked[] = "Is the item being returned to us?";
					$this->unlocked[] = "Is the item being relisted?";
					$this->unlocked[] = "Are we returning it to the consignor?";
					$this->unlocked[] = "Show item to Bruce before processing?";
					$this->unlocked[] = "Invoice to Bruce?";
				}
			};
		
		/*
			
		*/
		$this->questions["Does a claim need to be filed?"] = 
			function($answer){
				$this->answers["Does a claim need to be filed?"] = $answer;
				
				if($answer == "yes")
				{
					$this->questions["Is this a cancellation?"]("no");
					$this->locked[] = array("Is this a cancellation?", "Cannot be a cancellation if claim is being filed.");
				}
				else
				{
					$this->unlocked[] = "Is this a cancellation?";
				}
			};


		$this->questions["Refund method"] = 
			function($answer){
				$answer = trim($answer);
				$this->answers["Refund method"] = $answer;
			};

		/*
		*/
		$this->questions["Is the item being returned to us?"] = 
			function($answer){
				$this->answers["Is the item being returned to us?"] = $answer;
				
				if($answer == "yes")
				{
					$this->questions["Is this a cancellation?"]("no");
					//$this->questions["Show item to Bruce before processing?"]("no");
					$this->questions["Is the customer keeping the item?"]("no");
					//$this->questions["Invoice to Bruce?"]("no");
					
					$this->locked[] = array("Is this a cancellation?", "Item is being returned to us.");
					//$this->locked[] = "Show item to Bruce before processing?";
					$this->locked[] = array("Is the customer keeping the item?", "Item is being returned to us.");
					
					$this->unlocked[] = "Refunding return shipping?";
					
					//$this->unlocked[] = "Does a claim need to be filed?";
				}
				elseif($answer == "no")
				{
					$this->questions["Refunding return shipping?"]("no");
					$this->locked[] = array("Refunding return shipping?", "Item is not being returned.");
					
					//$this->questions["Does a claim need to be filed?"]("no");
					//$this->locked[] = array("Does a claim need to be filed?", "Item is not being returned to us.");
					
					//$this->unlocked[] = "Does a claim need to be filed?";
					$this->unlocked[] = "Invoice to Bruce?";
					$this->unlocked[] = "Is this a cancellation?";
					//$this->unlocked[] = "Show item to Bruce before processing?";
					$this->unlocked[] = "Is the customer keeping the item?";
				}
				else
				{
					//$this->unlocked[] = "Does a claim need to be filed?";
					$this->unlocked[] = "Invoice to Bruce?";
					$this->unlocked[] = "Is this a cancellation?";
					//$this->unlocked[] = "Show item to Bruce before processing?";
					$this->unlocked[] = "Is the customer keeping the item?";
					$this->unlocked[] = "Refunding return shipping?";
				}
			};

		/*
		*/
		$this->questions["Credit, Refund, or None?"] = 
			function($answer){
				$this->answers["Credit, Refund, or None?"] = $answer;
				
				if($answer == "none")
				{
					$this->questions["Amount to refund or credit"]("0");
					$this->questions["Refund method"]("");
					$this->locked[] = array("Refund method", "You have not selected 'Refund'");
				}
				elseif($answer == "credit" or $answer == "refund")
				{
					if(empty($this->all_answers["Amount to refund or credit"]))
						$this->questions["Amount to refund or credit"]($this->total);
					
					if($answer == "credit")
					{
						$this->locked[] = array("Refund method", "You have not selected 'Refund'");
					}
					else
					{
						$this->unlocked[] = "Refund method";
					}
				}
				elseif($answer == "unknown")
				{
					$this->questions["Refund method"]("");
					$this->locked[] = array("Refund method", "You have not selected 'Refund'");
				}
				else
				{
					$this->questions["Amount to refund or credit"]("");
					$this->questions["Refund method"]("");
					$this->locked[] = array("Refund method", "You have not selected 'Refund'");
				}
			};


		/*
		 * Phil asked for "Refunding return shipping?" to unlock an optional value
		 * but the following question covers exactly that.
		 * I just made it so "Refunding return shipping?" unlocks/locks the question -jj
		*/
		$this->questions["Amount of original shipping to refund"] = 
			function($answer){
				$this->answers["Amount of original shipping to refund"] = $answer;
				
				if($answer > 0)
				{
					$this->questions["Is this a cancellation?"]("no");
					$this->locked[] = array("Is this a cancellation?", "Cannot be a cancellation if shipping is being refunded.");
				}
				else
				{
					$this->unlocked[] = "Is this a cancellation?";
				}
			};

		/*
		*/
		$this->questions["Amount of item(s) to refund"] = 
			function($answer){
				$this->answers["Amount of item(s) to refund"] = $answer;
			};

		/*
		*/
		$this->questions["Refunding return shipping?"] = 
			function($answer){
				$this->answers["Refunding return shipping?"] = $answer;
				
				if($answer == "yes")
				{
					$this->questions["Is the item being returned to us?"]("yes");
					$this->locked[] = array("Is the item being returned to us?", "You selected 'refunding return shipping'.");
					$this->unlocked[] = "Amount of original shipping to refund";
				}
				else
				{
					$this->unlocked[] = "Is the item being returned to us?";
					$this->locked[] = array("Amount of original shipping to refund");
				}
			};

		/*
		*/
		$this->questions["Is the item being relisted?"] = 
			function($answer){
				$this->answers["Is the item being relisted?"] = $answer;
				
				if($answer == "yes")
				{
					$this->questions["Is the customer keeping the item?"]("no");
					$this->questions["Are we returning it to the consignor?"]("no");
					$this->locked[] = array("Is the customer keeping the item?", "Item is being relisted.");
					$this->locked[] = array("Are we returning it to the consignor?", "Item is being relisted.");
				}
				else
				{
					$this->unlocked[] = "Is the customer keeping the item?";
					$this->unlocked[] = "Are we returning it to the consignor?";
				}
			};

		/*
		*/
		$this->questions["Are we returning it to the consignor?"] = 
			function($answer){
				$this->answers["Are we returning it to the consignor?"] = $answer;
				
				if($answer == "yes")
				{
					$this->questions["Is the customer keeping the item?"]("no");
					$this->questions["Is the item being relisted?"]("no");
					$this->locked[] = array("Is the customer keeping the item?", "We are returning it to the consignor.");
					$this->locked[] = array("Is the item being relisted?", "We are returning it to the consignor.");
				}
				else
				{
					$this->unlocked[] = "Is the customer keeping the item?";
					$this->unlocked[] = "Is the item being relisted?";
				}
			};

		/*
		*/
		$this->questions["Show item to Bruce before processing?"] = 
			function($answer){
				$this->answers["Show item to Bruce before processing?"] = $answer;
				
				if($answer == "yes")
				{
					$this->questions["Is the customer keeping the item?"]("no");
					$this->locked[] = array("Is the customer keeping the item?", "You selected 'Show item to Bruce before processing'.");
				}
				else
				{
					$this->unlocked[] = "Is the customer keeping the item?";
				}
			};

		/*
		*/
		$this->questions["Are we getting money back from the consignor?"] = 
			function($answer){
				$this->answers["Are we getting money back from the consignor?"] = $answer;
				
				if($answer == "yes")
				{
					$this->questions["Is this a cancellation?"]("no");
					$this->questions["Invoice to Bruce?"]("no");
					
					$this->locked[] = array("Is this a cancellation?", "We are getting the money back from the consignor.");
					$this->locked[] = array("Invoice to Bruce?", "We are getting the money back from the consignor");
				}
				else
				{
					$this->unlocked[] = "Is this a cancellation?";
					$this->unlocked[] = "Invoice to Bruce?";
				}
			};
			

		/*
		*/
		$this->questions["Reduce customer's price to"] = 
			function($answer){
				$answer = trim($answer);
				
				if(count($this->items) > 1)
				{
					$answer = "";
					$this->locked[] = array("Reduce customer's price to", "Does not support greater than one item.");
				}
				else
				{
					if(is_numeric($answer))
					{
						//Amount to refund is old price minus new price.
						$this->questions["Amount to refund or credit"]($this->total - $answer);
						/*
						if(empty($this->all_answers['Reduce consignment price to']))
						{
							if(!empty($this->all_answers['Amount to office expense']))
							{
								//$this->questions['Reduce consignment price to']
							}
						}
						elseif(empty($this->all_answers['Amount to office expense']))
						{
							
						}*/
					}
					elseif(isset($answer))
					{
						$answer = "";
					}
				}
				
				$this->answers["Reduce customer's price to"] = $answer;
			};

		/*
		*/
		$this->questions["Reduce consignment price to"] = 
			function($answer){
				$answer = trim($answer);
				
				if(count($this->items) > 1)
				{
					$answer = "";
					$this->locked[] = array("Reduce consignment price to", "Does not support greater than one item.");
				}
				else
				{
					/*
					if(is_numeric($answer))
					{
						//if(isset($this->all_answers['']) && is_numeric($this->
					}
					elseif(isset($answer))
					{
						$answer = "";
					}*/
				}
				
				$this->answers["Reduce consignor's price to"] = $answer;
			};

		/*
		*/
		$this->questions["Amount to refund or credit"] = 
			function($answer){
				$answer = trim($answer);
			
				$this->answers["Amount to refund or credit"] = $answer;
				
				
			};
		
		
		/*
		*/
		$this->questions["Amount to office expense"] = 
			function($answer){		
				$answer = trim($answer);
				
				/*
				if(count($this->items) > 1)
				{
					$answer = "";
					$this->locked[] = array("Amount to office expense", "Does not support greater than one item.");
				}
				else
				{
					
					if(is_numeric($answer))
					{
						//if(isset($this->all_answers['']) && is_numeric($this->
					}
					elseif(isset($answer))
					{
						$answer = "";
					}
				}
				*/
						
				$this->answers["Amount to office expense"] = $answer;
			};
		
		
		/*
		*/
		$this->questions["Invoice to Bruce?"] = 
			function($answer){
				
				$this->answers["Invoice to Bruce?"] = $answer;
				
				if($answer == "yes")
				{
					
					$this->questions["Are we getting money back from the consignor?"]("no");
					
					$this->locked[] = array("Are we getting money back from the consignor?", "We are invoicing to Bruce.");
					
					if($this->answers["Is this from a Contact Past Buyers?"] == "yes" &&
						$this->answers["Reason for invoicing to Bruce"] != "because Contact Past Buyers")
					{
						$this->questions["Reason for invoicing to Bruce"]("because Contact Past Buyers");
					}
					
					//$this->unlocked[] = "Reason for invoicing to Bruce";
				}
				else
				{
					
					$this->questions["Reason for invoicing to Bruce"]("");
					//$this->locked[] = array("Reason for invoicing to Bruce", "You have not selected 'Invoice to Bruce'.");
					
					$this->unlocked[] = "Are we getting money back from the consignor?";
				}
			};
			
		
		/*
		*/
		$this->questions["Is this from a Contact Past Buyers?"] = 
			function($answer){
				$this->answers["Is this from a Contact Past Buyers?"] = $answer;
				
				if($answer == "yes" && $this->all_answers["Invoice to Bruce?"] == "yes")
				{
					$this->questions["Reason for invoicing to Bruce"]("because Contact Past Buyers");
				}
				
			};
		
		foreach($all_answers as $question => $answer)
		{
			if(isset($this->questions[$question]))
			{
				if(!isset($this->answers[$question]))
				{
					$this->questions[$question]($answer);
				}
			}
			else
			{
				throw new exception("No function for question '$question'");
			}
		}
	}
}



?>