<?php


class Autoquote
{
	
	function __construct($db, $wdb, $blab = null)
	{
		require_once("/webroot/shipping_estimator/shipping_estimates.inc.php");
		require_once("/webroot/shipping_quotes/endicia.inc.php");
		$this->db = $db;
		$this->wdb = $wdb;
		$this->blab = $blab;
		$this->endicia = new Endicia(false, $db);
		$this->auctionSchedule = new AuctionSchedule();
	}
	
	
	
	/*
		Take a qpp_id (quote request id) and determine whether 
		or not it is a good candidate for automatic quoting.
		
		Consider: US/International, weird items, quantity, value
		
		Actually I might just run it through the quote code, and if it throws an exception, it's bad.
	*/
	
	
	
	/*
		Take a qpp_id (quote request id) and generate a blank 
		quote record with one package that contains all the 
		items in that quote request.
	*/
	function create_quote_package($qpp_id)
	{
		$r = $this->wdb->query("select coalesce(items_winners.item_id, if(items_bin.item_id, concat('F', items_bin.item_id), null)), ".
			"coalesce(items_winners.users_email, items_bin.users_email) as users_email ".
			"from members.checkout ".
			"left join members.items_winners using (item_id) ".
			"left join members.items_bin using(item_id) ".
			"where qpp_id = '".$this->wdb->escape_string($qpp_id)."'");
			
		$items = array();
		
		while($row = $r->fetch_row())
		{
			list($item, $email) = $row;
			
			if(is_null($item))
				throw new exception("Null item");
				
			$items[] = $item;
		}
		
		$this->db->query("insert into invoicing.quotes ".
			"set customer_email = '".$this->db->escape_string($email)."', `who` = '18', ".
			"`note` = 'Automatically Created'");
			
		$quote_id = $this->db->insert_id;
		
		$this->db->query("insert into invoicing.quotes_packages ".
			"set quote_id = '$quote_id', packaging_id = '0', `who` = '18', ".
			"`note` = 'Automatically Created'");
			
		$package_id = $this->db->insert_id;
		
		$this->db->query("update invoicing.sales ".
			"set package_id = '$package_id' ".
			"where ebay_item_number in ('".implode("','", $items)."')");
			
		return $package_id;
	}
	
	
	
	/*
		Take package_id and generate list of items
	*/
	function get_package_items($package_id)
	{
		$r = $this->db->query("select `date`, ebay_item_number, ebay_title, price, combine_type, reference ".
			"from invoicing.sales ".
			"where package_id = '".$this->db->escape_string($package_id)."' ");
			
		$items = $item_numbers = array();
		
		$value = 0;
		
		while($row = $r->fetch_assoc())
		{
			if(!empty($row['reference'])) //Is a "Pay & Hold Invoice #" item
				continue;

			if(preg_match("/^[0-9]{7,}$/", $row['ebay_item_number']))
			{
				$value += $row['price'];
				$items[$row['ebay_item_number']] = $row;
				$item_numbers[] = $row['ebay_item_number'];
			}
			elseif($row['price'] >= 0)
			{
				throw new exception ("Unsupported item \"$row[ebay_title]\"");
			}
			else
			{
				//Item is a credit. Ignore.
			}
		}

		$quantity = $this->add_ah_info($item_numbers, $items);
		
		$this->add_datedtable_info($items);
		
		return array($items, $value, $quantity);
	}
	
	function add_datedtable_info(&$items)
	{
		foreach($items as $ebay_num => $item)
		{
			if(!empty($item['style_id']))
			{
				$auctionTable = $this->auctionSchedule->getTableCachedByEndDate($this->db, new DateTimeImmutable($item['date']));
				$auctionItem = $auctionTable->getItemByStyleId($this->db, $item['style_id']);
				$items[$ebay_num]['auction_code'] = $auctionItem->getAuctionCode();
			}
		}
	}
	
	/*
		Add type & dimension info to list of items
	*/
	function add_ah_info($item_numbers, &$items)
	{

		$r = $this->db->query(
			"select ebay_num, about_code as type_code, if(quantity is null or quantity = 0, 1, quantity) as quantity, ".
			"how_rolled, country, item_type as type_long, style_id, sortfield, date_end_calculated, poster_simple ".
			"from thumbnails.archive_gallery_tbl ".
			"join listing_system.tbl_types on (about_code = code) ".
			"where ebay_num in ('".implode("','", array_map(array($this->db, "escape_string"), $item_numbers))."') ".
			"union ".
			"select ebay_num, about_code as type_code, if(quantity is null or quantity = 0, 1, quantity) as quantity, ".
			"how_rolled, country, item_type as type_long, style_id, sortfield, date_end_calculated, poster_simple ".
			"from thumbnails.archive_gallery_cancelled ".
			"join listing_system.tbl_types on (about_code = code) ".
			"where ebay_num in ('".implode("','", array_map(array($this->db, "escape_string"), $item_numbers))."') "
			);

		$quantity = 0;

		while($row = $r->fetch_assoc())
		{
			$quantity += $row['quantity'];

			$ebay_num = $row['ebay_num'];
			unset($row['ebay_num']);

			$row['dimensions'] = self::extract_measurement($row['type_long']);

			$items[$ebay_num] = array_merge($items[$ebay_num], $row);
		}
		
		return $quantity;
	}
	
	
	static function add_ah_info_static(mysqli $db, $items)
	{
		foreach($items as $i)
		{
			$r = $db->query(
				"select ebay_num, about_code as type_code, if(quantity is null or quantity = 0, 1, quantity) as quantity, ".
				"how_rolled, country, item_type as type_long ".
				"from thumbnails.archive_gallery_tbl ".
				"join listing_system.tbl_types on (about_code = code) ".
				"where ebay_num = '".$i['ebay_item_number']."' ".
				"union ".
				"select ebay_num, about_code as type_code, if(quantity is null or quantity = 0, 1, quantity) as quantity, ".
				"how_rolled, country, item_type as type_long ".
				"from thumbnails.archive_gallery_cancelled ".
				"join listing_system.tbl_types on (about_code = code) ".
				"where ebay_num = '".$i['ebay_item_number']."' "
				);

			$quantity = 0;

			if($row = $r->fetch_assoc())
			{
				$i['dimensions'] = self::extract_measurement($row['type_long']);
				$i['how_rolled'] = $row['how_rolled'];
				$i['quantity'] = $row['quantity'];
			}
			
			yield ($i);
		}
	}
	
	
	
	/*
		Takes type_long and extracts measurements
	*/
	static function extract_measurement($type_long)
	{
		preg_match_all("/[\d\/ ]+\" x [\d\/ ]+\"/", $type_long, $matches);
		
		if(count($matches[0]) != 1)
			throw new exception("Could not extract measurement from \"$type_long\"");
		
		$fractions = Array(" 1/2", " 1/4", " 3/4", " 1/8", " 3/8", " 5/8", " 7/8");
		$decimals  = Array(".5",  ".25", ".75", ".125", ".375",".625",".875");
		$decimalMeasurements = str_replace($fractions, $decimals, $matches[0][0]);

		preg_match_all("/([\d]+(?:\.[\d]+)?)\" x ([\d]+(?:\.[\d]+)?)\"/", $decimalMeasurements, $matches);
			
		if(count($matches[0]) == 1)
		{
			return array($matches[1][0], $matches[2][0]);
		}
		
		throw new exception("Could not extract measurement from \"$type_long\"");
	}
	
	
	
	/*
		Take items list and determine weight of items
	*/
	function estimate_items_weight($items)
	{
		//TODO: For flat items, each group of auctions gets a piece of cardboard between them.
		Mailer::mail("jasen@emovieposter.com, steven@emovieposter.com", "autoquote.inc.php debug - ". __METHOD__ . ":" . __LINE__, print_r($items,true));
		$ounces = 0;
		
		foreach($items as $item)
		{
			
			if($item['type_code'] == "presskit" || $item['type_code'] == "pb")
			{
				$weight = 90;
			}
			elseif($item['type_code'] == "LC")
			{
				$weight = 7;
			}
			elseif($item['type_code'] == "insert" || $item['type_code'] == "1/2sh")
			{
				$weight = 4.5;
			}
			elseif(empty($item['country']))
			{
				$weight = 2.5;
			}
			elseif($item['country'] == 'the U.S.')
			{
				$weight = 2.5;
			}
			elseif($item['country'] == "Belgium" || $item['country'] == "Australia")
			{
				$weight = 2.0;
			}
			else
			{
				$weight = 2.5;
			}
			
			//TODO: Find "thin" or "thin linen" or "heavy stock" in the 
			//condition description or style info and use that to adjust weight
			
			$area = $item['dimensions'][0] * $item['dimensions'][1];
			
			$ounces += $item['quantity'] * $weight * ($area / (27*40));
			
			if($item['how_rolled'] == "linen")
			{
				$ounces += $item['quantity'] * 14 * ($area / (27*40));
			}
		}
		
		return round($ounces, 1);
	}
	
	

	/*
		Take items list, value, and quantity and determine maximum dimension for ROLLED items
	*/
	function estimate_rolled_linen_packaging($items, $value, $quantity)
	{		
		$roll_length = 0;
		foreach($items as $row)
		{
			if($roll_length < min($row['dimensions']))
				$roll_length = min($row['dimensions']);
		}
				
		if($value > 1000 || $quantity > 4)
		{
			//6 inch tube
			$width = $height = 7; 
			$std_tube_weight = 4.25;
		}
		else
		{
			//4 inch tube
			$width = $height = 5; 
			$std_tube_weight = 1.70;
		}
		
		$pack_weight = ($std_tube_weight * ($roll_length / 27) * 16) + 6; //Anomaly
		
		$pack_weight += min(16, $value/300); //Add an ounce for every $300, up to 16 ounces.
		
		$pack_weight = round($pack_weight, 1);
		
		return array($pack_weight, $width, $height, $roll_length+2+2); //+2 for linen edges, +2 for packaging room
	}
	
	
	/*
		Take items list, value, and quantity and determine maximum dimension for ROLLED NON-LINEN items
	*/
	function estimate_rolled_nonlinen_packaging($items, $value, $quantity)
	{
		$tubes = array(19, 26, 32, 34, 38, 46, 52, 62, 92);
		$packingPaper = array(17, 24, 30, 32, 36); //These are the only sizes of packing paper that shipping uses says John
		$roll_length = 0;
		
		$uflarge = 0;
		
		foreach($items as $row)
		{
			if($roll_length < min($row['dimensions']))
				$roll_length = min($row['dimensions']);
				
			if($row['auction_code'] == "UFLARGE")
			{
				//Phil requested this behavior. Upgrade to 4-inch tube automatically
				//if there is any UFLARGE item in the package.
				$uflarge++;
			}
		}
		
		$roll_length += 2;
		
		if($roll_length > 92)
			throw new exception("Item is too big");
		
		foreach($packingPaper as $paper)
		{
			if($roll_length < $paper)
			{
				$roll_length = $paper;
				break;
			}
		}
		
		foreach($tubes as $t)
		{
			if($t > $roll_length)
			{
				$roll_length = $t;
				break;
			}
		}
		
		
		if($value > 1000 || $quantity > 10 || $roll_length > 62)
		{
			//6 inch tube
			$width = $height = 7; 
			$std_tube_weight = 4.25;
			$packaging_id = 3;
		}
		elseif($quantity > 3 || $roll_length > 34 || $uflarge || $value >= 300)
		{
			//4 inch tube
			$width = $height = 5; 
			$std_tube_weight = 1.70;
			$packaging_id = 2;
		}
		else
		{
			//3 inch tube
			$width = $height = 4;
			$std_tube_weight = 1.5;
			$packaging_id = 1;
		}
		
		$pack_weight = ($std_tube_weight * ($roll_length / 27) * 16); //Anomaly
		
		$pack_weight += min(16, $value/300); //Add an ounce for every $300, up to 16 ounces.
		
		$pack_weight = round($pack_weight, 1);
		
		return array($pack_weight, $width, $height, $roll_length, $packaging_id); //+2 for packaging room
	}
	
	
	/*
		Take items list, value, and quantity and determine maximum dimension for FLAT items
	*/
	function estimate_flat_nonlinen_packaging($items, $value, $quantity)
	{
		$combine_types = array(
			29 => "", //Flat8
			9  => "", //Flat12
			3  => "", //Flat14
			28 => "", //FlatActualOversized
			30 => "", //Flat16
			33 => "", //Flat18
		);
		
		$auctions = array();
		$bag_and_boards = 0;
		
		$cardAmount = 0;
		$bigCardAmount = 0;
		$frenchCardAmount = 0;
		$length = 0;
		$width = 0;
		$smallPosterSimpleArray = array("still", "lobby card", "title card");
		$smallCardTypes = array("WC, mini");
		$bigCardTypes = array("Mexican WC", "Aust WC", "WC, regular", "Italian LC",
			"Mexican LC", "Italian oversized still", "jumbo still", "jumbo stills",
			"Middle Eastern 12x16 color stills", "Middle Eastern misc",
			"oversize still", "oversize stills",
		);
		$frenchCardTypes = array("French 1p", "French 2p", "French 4p", "French 8p", "French door panel", "French large");
		
		foreach($items as $row)
		{
			increment($combine_types, $row['combine_type']);
			increment($auctions, substr($row['ebay_title'], 0, 2));
			
			//I've been told each single lobby card gets a bag and board, so adding this. AK 2017-06-27
			if($row['type_code'] == "LC" and $row['quantity'] == 1)
			{
				$bag_and_boards++;
			}
			
			if($row['dimensions'][0] > $length)
			{
				$length = $row['dimensions'][0];
			}
			
			if($row['dimensions'][1] > $width)
			{
				$width = $row['dimensions'][1];
			}
		}
		
		foreach($items as $item)
		{
			if((in_array($item['poster_simple'], $smallPosterSimpleArray) || in_array($item['type_code'], $smallCardTypes)))
			{
				$cardAmount++;
			}
			
			if(in_array($item['type_code'], $bigCardTypes))
			{
				$bigCardAmount++;
			}
			
			if(in_array($item['type_code'], $frenchCardTypes))
			{
				$frenchCardAmount++;
			}
		}
		
		//Mailer:mail('jasen@emovieposter.com, steven@emovieposter.com',__FILE__ . ":" . __LINE__,var_export($combine_types,true));
		//$combine_types[9] > 0 && 
		
		if($combine_types[28] + $combine_types[29] + $combine_types[9] == count($items)) //Flat8, Flat12, FlatActualOversized
		{
			//TODO: Oversized items going up to a cutdown calumet, such as a 28x42 one-sheet
			
			//#4 calumet with top and bottom cardboard weighs 15oz. 15x12x4 box with top and bottom cardboard and peanuts weighs 20oz
			//If 300 or over, 15x12x4 Box, otherwise #4 Calumet
			//We revised the box to 28 oz + 8 for a piece of pressboard
			if(($value >= 300 && $bigCardAmount == 0 && $frenchCardAmount == 0) || ($cardAmount == 1 && $value > 150 && $bigCardAmount == 0 && $frenchCardAmount == 0) || ($cardAmount > 1 && $value > 250 && $bigCardAmount == 0 && $frenchCardAmount == 0))
			{
				$pack_weight = 28;
				
				$pack_weight += 8 * count($auctions); //For each auction group, add a piece of pressboard
				
				if($value >= 1000)
				{
					$pack_weight += 6;
				}
				
				$length = 16;
				$width = 13;
				$height = 5;
				$type = 5; //15x12x4 box
			}
			else if(($bigCardAmount == 1 && ($value > 150 || count($items) > 8)) || ($bigCardAmount > 0 && $bigCardAmount + $cardAmount > 1 && ($value > 250 || count($items) > 8)) || ($frenchCardAmount > 0 && ($value > 300 || count($items) > 8)))
			{
				$pack_weight = 40;
				
				$pack_weight += 8 * count($auctions); //For each auction group, add a piece of pressboard
				
				if($value >= 1000)
				{
					$pack_weight += 6;
				}
				
				$length = 25;
				$width = 17;
				$height = 15;
				$type = 38; //24x16x13 box
			}
			else if(($frenchCardAmount > 0 || $bigCardAmount > 0) || ($length < 25 && $length > 14.75 && $width < 16 && $width > 11.75) || ($width < 25 && $width > 14.75 && $length < 16 && $length > 11.75)) // Needs to check length and width variable
			{
				$pack_weight = 25;
				
				$pack_weight += 8 * count($auctions); //For each auction group, add a piece of pressboard
				
				$length = 24;
				$width = 18;
				$height = 1;
				$type = 19; //#11 calumet
			}
			else
			{
				$pack_weight = 15;
				
				$length = 15;
				$width = 13;
				$height = 1;
				$type = 17; //#4 calumet
			}
			
			//So, what about other package types? What about other logic for things that need to go in these package types?
			
			//I've been told each single lobby card gets a bag and board, so adding this. AK 2017-06-27
			$pack_weight += $bag_and_boards * 3; //Each bag and board weighs exactly 3 ounces
		}
		else
		{
			throw new exception("Not yet supported (combine type ".implode(",", array_keys(array_filter($combine_types, "strlen"))).")");
		}
		
		return array($pack_weight, $width, $height, $length, $type);
	}
	
	
	
	
	
	/*
		Take package_id and estimate packaging, dimensions, and weight for ROLLED LINEN items
	*/
	function estimate_rolled_linen($package_id, $country, $zip)
	{
		throw new exception("Rolled linen not yet supported");
		
		list($items, $value, $quantity) = $this->get_package_items($package_id);
		
		list($package_weight, $width, $height, $length) = $this->estimate_rolled_linen_packaging($items, $value, $quantity);
		
		$items_weight = $this->estimate_items_weight($items);
		
		$estimated_weight = round($items_weight + $package_weight);
		
		$this->blab->blab("Est $estimated_weight Oz");
		
		if($value >= 500 || $estimated_weight >= 30*16)
		{
			if($value <= 50) //2019 April 30 added this as requested to ensure packs under $50 never get insured
			{
				$insured_value = $value;
				$insured = false;
			}
			else
			{
				$insured = true;
				$insured_value = $value;
			}
		}
		else
		{
			$insured_value = $value;
			$insured = false;
		}
		
		$signature = ($country == "US" and $value >= 300); //changed from 500 value 2019 April 30
		
		if($country != "US" && $length > 24 && ($length <= 32 && $width == 4 or $length <= 26 && $width == 5))
			$length = 24;
		
		if($value <= 50) //2019 September 19 added this as requested to ensure packes under $50 never get insured
		{
			$insured_value = $value;
			$insured = false;
		}
		
		try
		{
			$prices = $this->quote_postage($country, $zip, $estimated_weight, $length, $width, $height, $insured_value, $insured, $signature, $package_id);
		}
		catch(exception $e)
		{
			return array(
				"items_weight" => $items_weight,
				"package_weight" => $package_weight,
				"weight" => $estimated_weight,
				"length" => $length,
				"width" => $width,
				"height" => $height,
				"error" => $e->getMessage(),
				"signature" => $signature,
				"insured" => $insured,
			);
		}
		
		return array(
			"items_weight" => $items_weight,
			"package_weight" => $package_weight,
			"weight" => $estimated_weight,
			"length" => $length,
			"width" => $width,
			"height" => $height,
			"prices" => $prices,
			"signature" => $signature,
			"insured" => $insured,
			"value" => $value,
			"estimated_weight" => $estimated_weight,
		);
	}
	
	
	static function bruce_overestimate($ounces)
	{
		if($ounces % 16 >= 14 || $ounces % 16 == 0)
		{
			return Math::ceiling($ounces, 16)+1;
		}
		
		return $ounces;
	}
	
	/*
		Take package_id and estimate packaging, dimensions, and weight for ROLLED NON-LINEN items
	*/
	function estimate_rolled_nonlinen($package_id, $country, $zip)
	{		
		list($items, $value, $quantity) = $this->get_package_items($package_id);
		
		list($package_weight, $width, $height, $length, $packaging_id) = $this->estimate_rolled_nonlinen_packaging($items, $value, $quantity);
		
		$items_weight = $this->estimate_items_weight($items);
		
		$estimated_weight = round($items_weight + $package_weight);
		
		$estimated_weight = $this::bruce_overestimate($estimated_weight);
		
		$this->blab->blab("Est $estimated_weight Oz");
		
		if($value >= 500 || $estimated_weight >= 30*16)
		{
			if($value <= 50) //2019 April 30 added this as requested to ensure packes under $50 never get insured
			{
				$insured_value = $value;
				$insured = false;
			}
			else
			{
				$insured = true;
				$insured_value = $value;
			}
			
		}
		else
		{
			$insured_value = $value;
			$insured = false;
		}
		
		$signature = ($country == "US" and $value >= 300); //changed from 500 value 2019 April 30
		
		if($country != "US" && $length > 24 && ($length <= 32 && $width == 4 or $length <= 26 && $width == 5))
			$length = 24;
		
		if($value <= 50) //2019 September 19 added this as requested to ensure packes under $50 never get insured
		{
			$insured_value = $value;
			$insured = false;
		}
		
		try
		{
			$prices = $this->quote_postage($country, $zip, $estimated_weight, $length, 
				$width, $height, $insured_value, $insured, $signature, $package_id);
			
			if($country != "US")
			{
				$prices = $prices + $this->quote_postage($country, $zip, $estimated_weight, 
					$length, $width, $height, ($insured ? 0 : $value), !$insured, $signature, $package_id);
			}
		}
		catch(exception $e)
		{
			return array(
				"items_weight" => $items_weight,
				"package_weight" => $package_weight,
				"weight" => $estimated_weight,
				"length" => $length,
				"width" => $width,
				"height" => $height,
				"error" => $e->getMessage(),
				"signature" => $signature,
				"insured" => $insured,
			);
		}
		
		return array(
			"items_weight" => $items_weight,
			"package_weight" => $package_weight,
			"weight" => $estimated_weight,
			"length" => $length,
			"width" => $width,
			"height" => $height,
			"prices" => $prices,
			"signature" => $signature,
			"insured" => $insured,
			"value" => $value,
			"estimated_weight" => $estimated_weight,
			"packaging_id" => $packaging_id,
		);
	}
	
	
	/*
		Take package_id and estimate packaging, dimensions, and weight for FLAT items
	*/
	function estimate_flat_nonlinen($package_id, $country, $zip)
	{
		list($items, $value, $quantity) = $this->get_package_items($package_id);
		
		list($package_weight, $width, $height, $length, $packaging_id) = $this->estimate_flat_nonlinen_packaging($items, $value, $quantity);
		
		/*foreach($items as $i)
		{
			echo $i['dimensions'][0]."x".$i['dimensions'][1]." $i[type_code], ";
		}
		echo "\n";*/
		
		$items_weight = $this->estimate_items_weight($items);
		
		$estimated_weight = round($items_weight + $package_weight);
		
		self::bruce_overestimate($estimated_weight);
		
		$this->blab->blab("Est $estimated_weight Oz");
		
		if($value >= 500 || $estimated_weight >= 30*16)
		{
			if($value <= 50) //2019 April 30 added this as requested to ensure packes under $50 never get insured
			{
				$insured_value = $value;
				$insured = false;
			}
			else
			{
				$insured = true;
				$insured_value = $value;
			}
			
		}
		else
		{
			$insured_value = $value;
			$insured = true; // Switched to true 2019 April 19
		}
		
		$signature = ($country == "US" and $value >= 300); //changed from 500 value 2019 April 30
		
		if($value <= 50) //2019 September 19 added this as requested to ensure packes under $50 never get insured
		{
			$insured_value = $value;
			$insured = false;
		}
		
		try
		{
			$prices = $this->quote_postage($country, $zip, $estimated_weight, $length, 
				$width, $height, ($insured ? $value : 0), $insured, $signature, $package_id);
			
			if($country != "US")
			{
				$prices = $prices + $this->quote_postage($country, $zip, $estimated_weight, 
					$length, $width, $height, ($insured ? 0 : $value), !$insured, $signature, $package_id);
			}
		}
		catch(exception $e)
		{
			throw $e;
			/*
			return array(
				"items_weight" => $items_weight,
				"package_weight" => $package_weight,
				"weight" => $estimated_weight,
				"length" => $length,
				"width" => $width,
				"height" => $height,
				"error" => $e->getMessage(),
			);
			*/
		}
		
		return array(
			"items_weight" => $items_weight,
			"package_weight" => $package_weight,
			"weight" => $estimated_weight,
			"length" => $length,
			"width" => $width,
			"height" => $height,
			"prices" => $prices,
			"signature" => $signature,
			"value" => $value,
			"estimated_weight" => $estimated_weight,
			"packaging_id" => $packaging_id,
			"insured" => $insured,
		);
	}
	
	
	/*
		Take a package id and determine which method to use (flat, rolled, rolled linen),
		then run that method to get packaging, dimensions, and weight.
	*/
	function run($package_id)
	{
		list($country, $zip) = $this->customer($package_id);
		
		$r = $this->db->query("select count(*), combine_type, snst_text, `date` ".
			"from invoicing.sales ".
			"join listing_system.new_specialnotes_shiptype on (snst_id = combine_type) ".
			"where package_id = '".$this->db->escape_string($package_id)."'  ".
			"group by combine_type");
		
		$types = array();
		
		while(list($count, $combine_type, $snst_text, $date) = $r->fetch_row())
		{
			switch($combine_type)
			{
				case 24: //RolledLinen
					increment($types, 'RolledLinen', $count);
					break;
					
				case 12: //Rolled12
				case 5:  //RolledActual
					increment($types, 'Rolled', $count);
					break;
					
				case 9:  //Flat12
				case 3:  //Flat14
				case 30: //Flat16
				case 33: //Flat18
				case 29: //Flat8
					increment($types, 'Flat', $count);
					break;
					
				case 28: //FlatActualOversized
					try
					{
						$datedTable = $this->auctionSchedule->getTableCached($this->db, new AuctionDate(new DateTimeImmutable($date)));
						
						//2017-07-12 Phil approved adding FlatActualOversized, WCPANEL items to Flat autoquote. AK.
						if($datedTable->hasAuctionCode("WCPANEL"))
						{
							increment($types, 'Flat', $count);
						}
						else
						{
							increment($types, 'FlatActualOversized', $count);
						}
					}
					catch(exception $e)
					{
						email_error($e->__toString());
						increment($types, 'FlatActualOversized', $count);
					}
					
					
					break;
					
				default:
					throw new exception("Not yet supported (no method for $snst_text)");
					break;
			}
		}
		
		
		if(isset($types['Flat']) && !isset($types['Rolled']))
			return $this->estimate_flat_nonlinen($package_id, $country, $zip);
		
		if(count($types) != 1)
			throw new exception("Not yet supported (more than one combine_type)");
		
		if(isset($types['Rolled']))
			return $this->estimate_rolled_nonlinen($package_id, $country, $zip);
			
		/*if(isset($types['RolledLinen']))
			return $this->estimate_rolled_linen($package_id, $country, $zip);*/
			
		
		
		throw new exception("Not yet supported (no method available)");
	}
	
	
	/*
		Take package_id and get customer shipping information
	*/
	function customer($package_id)
	{
		$r = $this->db->query("select ship_country, substring(ship_zip, 1, 5), notes_for_invoice like '%#packing%' ".
			"from customers ".
			"join quotes on (email = customer_email) ".
			"join quotes_packages using(quote_id) ".
			"where package_id = '".$this->db->escape_string($package_id)."'");
		
		if($r->num_rows == 1)
		{
			list($country, $zip, $custom_packing) = $r->fetch_row();
			
			if($custom_packing)
				throw new exception ("Customer has custom packing instructions");
			
			return array($country, $zip);
		}
		
		throw new exception ("Could not find customer");
	}
	
	
	
	/*
		Take packaging, dimensions, weight, and customer information and
		run quotes to determine postage and insurance
	*/
	function quote_postage($ship_country, $postal_code, $ounces, $length, $width, $height, $value, $insured, $signature = false, $package_id)
	{
		
		$request = array(
			"ToCountryCode" => $ship_country,
			"ToPostalCode" => ($ship_country == "US" || $ship_country == "CA") ? $postal_code : "",
			"WeightOz" => $ounces,
			"Length" => $length,
			"Width" => $width,
			"Height" => $height,
			"InsuredValue" => $value,
			"InsuredMail" => $insured ? "ENDICIA" : "OFF",
			"SignatureConfirmation" => $signature ? "ON" : "OFF",
		);
		
		$this->last_endicia_rates_request = $request;
		
		//print_r($request);
		
		$rates = $this->endicia->CalculatePostageRates($request);
		
		//print_r($rates);
		
		$this->last_endicia_rates_response = $rates;
		
		$prices = $this->convert_quotes_amounts($rates, $insured, $request, $package_id);
		
		return $prices;
	}
	
	
	
	/*
		Determine whether a U.S.-bound package requires carrier insurance
	*/
	function carrier_insurance_required($value)
	{
		throw new exception("Not implemented yet");
	}
	
	
	
	
	/*
		Take Endicia API result and convert to something usable
	*/
	function convert_quotes_amounts($rates, $insured, $request, $package_id)
	{
		$r = $this->db->query("select usps_name, ship_service ".
			"from invoicing.quotes_services_usps_map ".
			"where insured = '".($insured ? 1 : 0)."';");
		
		$services = array();
		while(list($name, $id) = $r->fetch_row())
			$services[$name] = $id;
		
		$prices = array();
		
		$checkFirstClass = array("First", "First-Class Mail", "FirstClassPackageInternationalService");
		$checkPriotiry = array("Priority", "PriorityExpress");
    
		Mailer::mail("jasen@emovieposter.com, steven@emovieposter.com", "autoquote.inc.php debug - ". __METHOD__ . ":" . __LINE__, print_r($rates,true));
		
		foreach($rates->PostageRatesResponse->PostagePrice as $price)
		{
			
			list($items, $value, $quantity) = $this->get_package_items($package_id);
						
			$itemCost = 0;
			
			foreach($items as $item)
			{
				$itemCost += $item['price'];
			}
			
			//We don't currently want to quote Media Mail automatically
			if(empty($services[$price->MailClass]) || $price->MailClass == "MediaMail")
			{
				continue;
			}
			
			//Don't add first class to foreign delivery over $299
			Mailer::mail("jasen@emovieposter.com", "autoquote check 300 nonUS", "info: \nMail Class=> " . print_r($price->MailClass,true) . "\nItem Value=> " . print_r($itemCost,true) . "\nCountry Code=> " . print_r($request['ToCountryCode'],true));
			
			if(in_array($price->MailClass, $checkFirstClass) && $itemCost >= 300 && $request["ToCountryCode"] != "US")
			{
				Mailer::mail("jasen@emovieposter.com", "prices autoquote caught 300", 'Caught $300 foreign first class');
				continue;
			}
			
			//Don't add registered mail to domestic packages under $700
			
			if($price->Fees->RegisteredMail > 0 && $itemCost < 700 && $request["ToCountryCode"] == "US")
			{
				Mailer::mail("jasen@emovieposter.com", "prices autoquote caught 700", 'Caught $700 domestic');
				continue;
			}
			
			//Don't add Priority mail to domestic packages $50 or less
			//Apparently this isn't what they wanted?
			/*
			if(in_array($price->MailClass, $checkPriotiry) && $itemCost <= 50 && $request["ToCountryCode"] == "US")
			{
				Mailer::mail("jasen@emovieposter.com", "prices autoquote caught 50", 'Caught <= $50 domestic Priority mail package');
				continue;
			}*/
			
			$prices[$services[$price->MailClass]] = array($price->TotalAmount, 
				$price->Fees->InsuredMail, $price->Fees->SignatureConfirmation, $price->MailClass);
		}
		
		Mailer::mail("steven@emovieposter.com, jasen@emovieposter.com", "prices autoquote data", '$prices=>' . print_r($prices,true));
		
		return $prices;
	}
	
	
	/*
		Update a quote with values from quote_postage()
	*/
	function update_quote($package_id, $autoquote)
	{
		//Mailer::mail("aaron@emovieposter.com", "Q", print_r($autoquote, true));
		$this->db->query("update invoicing.quotes_packages ".
			"set `pounds` = '".floor($autoquote['estimated_weight'] / 16)."', `ounces` = '".($autoquote['estimated_weight'] % 16)."', ".
			"`length` = '".round($autoquote['length'])."', `width` = '".round($autoquote['width'])."', `height` = '".round($autoquote['height'])."', ".
			"`value` = '".round($autoquote['value'], 2)."', ".
			"`packaging_id` = '".$autoquote['packaging_id']."', ".
			"pkg_autoquote_json = '".$this->db->escape_string(json_encode2($autoquote))."' ".
			"where package_id = '$package_id'");
		
		list($country, $zip) = $this->customer($package_id);
		
		if($country == "US")
		{
			//Unset Express for US. AK 20170814 || 25 is Parcel Select (uninsured)
			unset($autoquote['prices'][3], $autoquote['prices'][4], $autoquote['prices'][25]);
		}
		
		foreach($autoquote['prices'] as $ship_service => $amounts)
		{
			list($total, $insurance_fee, $signature_fee, $class) = $amounts;
			
			$this->db->query(assemble_insert_query3(array(
				"package_id" => $package_id,
				"ship_service" => $ship_service,
				"free_item" => 0,
				"cost" => round($total+$insurance_fee+$signature_fee+3, 2),
				"who" => 18
			), "invoicing.quotes_amounts", $this->db, false));

			$this->db->query(assemble_insert_query3(array(
				"package_id" => $package_id,
				"ship_service" => $ship_service,
				"free_item" => 0,
				"cost" => round($total+$insurance_fee+$signature_fee+3, 2),
				"who" => 18
			), "invoicing.quotes_amounts_autoquote", $this->db, false));
		}
		
		$extra_notes = "";
		
		if($autoquote['insured'])
		{
			$extra_notes .= "* Insured for $".number_format($autoquote['value'])."\r\n";
		}
		
		if($autoquote['signature'])
		{
			$extra_notes .= "* Signature Required is ON";
		}
		
		if(!empty($extra_notes))
		{
			$this->db->query("update invoicing.quotes_packages, invoicing.quotes ".
				"set quotes.note = trim(concat(coalesce(quotes.note, ''), '\r\n', '".$this->db->escape_string($extra_notes)."')) ".
				"where quotes_packages.quote_id = quotes.quote_id and package_id = '$package_id'");
		}
		
		return true;
	}
	
	
	
	/*
		Run when shipment is made to determine and notify when overcharge/undercharge occurs.
	*/
	
	
	
	/*
		Daily audit report summarizing all autoquotes generated
	*/
	
	
	
	/*
		Function for talking to Endicia
	*/
	function estimate($country_code, $ounces, $postal_code = "", $length = "20", $width = "14", $height = "7", $insured_value = 0)
	{
		$rates = $this->endicia->CalculatePostageRates(array(
			"ToPostalCode" => $postal_code,
			"ToCountryCode" => $country_code,
			"WeightOz" => $ounces,
			"Length" => $length,
			"Width" => $width,
			"Height" => $height,
			"InsuredMail" => ($insured_value > 0 ? "ENDICIA" : "OFF"),
			"InsuredValue" => $insured_value,
		));
		
		$prices = $this->e->generate_quotes_amounts($rates, ($insured_value > 0), $this->db);
		
		if(empty($prices[1])) //Priority
			throw new exception("No priority price received. <pre>".print_r($rates, true)."</pre>");
		
		return number_format($prices[1], 2);
	}
	
}

?>