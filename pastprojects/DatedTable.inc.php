<?php



class DatedTable extends AuctionTable implements UndoEvent
{
	private $lot_number, $date, $codes, $description, $oldStatus, $tableName, $oldName;
	
	function __construct(mysqli $db, $name = null)
	{
		parent::__construct();
		
		$this->codes = array();
		
		if(!is_null($name))
		{
			$this->init($db, $name);
		}
	}
	
	function undo(mysqli $db)
	{
		$this->drop($db);
		return true;
	}
	
	function getUndoDescription()
	{
		return "create auction table ".$this->getName();
	}
	
	function create(mysqli $db, Triggers $triggers)
	{
		//require_once("/scripts/triggers/triggers2.inc.php");
		
		$trigger_settings = array(
			"log_table_name" => "auditLog2",
			"log_fields" => Array(
				"table"		=> "table",
				"key"		=> "id",
				"field"		=> "field",
				"old"		=> "old_value",
				"new"		=> "new_value",
				"user"		=> "user",
				"client"	=> "client",
				"operation"	=> "operation",
				"record"	=> "extra",
			),
		);
		
		$name = $this->getName();
		$database = $this->getDatabase();
		$auctionDate = $this->getDate();
		$sqlName = $this->getSqlName($db);
		$db->query("CREATE TABLE $sqlName (
`autonumber` int(9) NOT NULL,
`lot_number` varchar(7) DEFAULT NULL,
`film_title` varchar(96) DEFAULT NULL,
`film_MrLister` varchar(85) DEFAULT NULL,
`image1` varchar(255) DEFAULT NULL,
`image2` text,
`consignor` varchar(50) DEFAULT NULL,
`type_pre_short` varchar(255) DEFAULT NULL,
`type_code` varchar(255) DEFAULT NULL,
`type_short` varchar(255) DEFAULT NULL,
`type_long` text,
`condition_overall` varchar(50) DEFAULT NULL,
`condition_major_defects` text,
`condition_common_defects` text,
`after_description` text,
`after_description_bulk` text,
`title_long` varchar(255) DEFAULT NULL,
`title_45` varchar(255) DEFAULT NULL,
`style_info` text,
`how_rolled` varchar(10) DEFAULT NULL,
`ebay_category` int(9) DEFAULT '0',
`template_date` varchar(9) DEFAULT '20000000',
`desire_items` varchar(20) DEFAULT NULL,
`ebay_item_number` varchar(255) DEFAULT NULL,
`ebay_price` double DEFAULT NULL,
`ebay_id` varchar(255) DEFAULT NULL,
`ebay_email` varchar(255) DEFAULT NULL,
`artist` varchar(255) DEFAULT NULL,
`date_added` datetime DEFAULT NULL,
`sections_code` varchar(255) DEFAULT NULL,
`who_did` int(11) DEFAULT NULL,
`photography_folder` varchar(20) DEFAULT NULL,
`utility_field` varchar(255) DEFAULT NULL,
`dupe_query` varchar(255) DEFAULT NULL,
`listing_notes` text,
`auction_code` varchar(255) DEFAULT NULL,
`measurements` varchar(255) DEFAULT NULL,
`auction_id` int(9) DEFAULT '0',
`id` int(9) NOT NULL AUTO_INCREMENT,
`ts` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
`style_id` int(9) DEFAULT NULL,
`how_stored` varchar(50) DEFAULT NULL,
`move_order` smallint(5) unsigned DEFAULT NULL,
`selected` tinyint(1) NOT NULL DEFAULT '0',
`bulk_lot_id` int(10) unsigned DEFAULT NULL,
`date_conditioned` datetime DEFAULT NULL,
`who_conditioned` varchar(255) DEFAULT NULL,
`xrated` tinyint(3) unsigned NOT NULL,
`quantity` smallint(5) unsigned DEFAULT NULL,
`status` enum('described','image added','new') NOT NULL DEFAULT 'new',
`set_id` int(10) unsigned DEFAULT NULL,
`pset_id` int(10) unsigned DEFAULT NULL,
`ship` enum('flat','rolled') DEFAULT NULL,
`shipping_paragraph` tinyint(3) unsigned DEFAULT NULL,
`combine_type` tinyint(3) unsigned DEFAULT NULL,
PRIMARY KEY (`id`),
UNIQUE KEY `autonumber` (`autonumber`),
UNIQUE KEY `style_id` (`style_id`),
UNIQUE KEY `image1` (`image1`),
KEY `01-13-03type_short` (`type_short`),
KEY `artist` (`artist`),
KEY `auction_code` (`auction_code`),
KEY `auction_id` (`auction_id`),
KEY `consignor` (`consignor`),
KEY `ebay_id` (`ebay_id`),
KEY `film_MrLister` (`film_MrLister`),
KEY `film_title` (`film_title`),
KEY `lot_number` (`lot_number`),
KEY `sections_code` (`sections_code`),
KEY `type_code` (`type_code`),
KEY `who_did` (`who_did`),
KEY `sorting_index` (`type_short`,`film_title`),
KEY `selected` (`selected`),
KEY `photography_folder` (`photography_folder`),
KEY `move_order` (`move_order`)
) ENGINE=InnoDB");
		
		/*$db->query("create table `".$db->escape_string($database)."`.`".$db->escape_string($name)."` ".
			"LIKE listing_system.`00-00-00 main (MAIN)`");
		
		$db->query("ALTER TABLE `".$db->escape_string($database)."`.`".$db->escape_string($name)."` ".
			"ADD COLUMN shipping_paragraph tinyint unsigned, ADD COLUMN combine_type tinyint unsigned");*/
		
		$db->query($triggers->generate_insert_trigger($database, $name, true, $trigger_settings));
		$db->query($triggers->generate_update_trigger($database, $name, true, $trigger_settings));
		$db->query($triggers->generate_delete_trigger($database, $name, true, $trigger_settings));
		
		$db->query("CREATE TRIGGER set_date_added_".time()." BEFORE INSERT ON `".$db->escape_string($name)."` ".
				"FOR EACH ROW BEGIN IF NEW.date_added is null THEN SET NEW.date_added = NOW(); END IF; ".
				"if NEW.autonumber is null then ".
				"SET NEW.autonumber = coalesce((SELECT autonumber FROM `".$db->escape_string($name)."` ORDER BY autonumber DESC LIMIT 1), 0)+1; ".
				"end if; ".
				"END;");
		
		$this->folder = new AuctionImageFolder($this);
		
		
		AuctionSchedule::setStatus($db, $auctionDate, 8);
		AuctionSchedule::setTable($db, $auctionDate, $name);
		
		return true;
	}
	
	function undoTemplatesTable(mysqli $db)
	{
		$db->query("delete from listing_system.tbl_templates ".
			"where `templatedate` = '".$db->escape_string($this->getDate()->getTemplateDate())."'");
			
		return true;
	}
	
	function updateTemplatesTable(mysqli $db)
	{
		$auctionDate = $this->getDate();
		
		$db->query("insert into listing_system.tbl_templates ".
			"set `templatedate` = '".$db->escape_string($auctionDate->getTemplateDate())."', ".
			"`Day` = '".$auctionDate->getTemplateDow()."', `lot_numbers` = '".$this->getLotNumberPrefix()."', ".
			"`type` = '".implode("& ", $this->getAuctionCodes())."', ".
			"`quantity_in_auction` = '".$db->escape_string($this->getQuantities($db))."', ".
			"`offers` = '----' ".
			"on duplicate key update ".
			"`Day` = '".$auctionDate->getTemplateDow()."', `lot_numbers` = '".$this->getLotNumberPrefix()."', ".
			"`type` = '".implode("& ", $this->getAuctionCodes())."', ".
			"`quantity_in_auction` = '".$db->escape_string($this->getQuantities($db))."', ".
			"`offers` = '----'");
			
		return true;
	}
	
	
	function getQuantities(mysqli $db)
	{
		$r = $db->query("select count(*), sum(quantity) ".
			"from `".$db->escape_string($this->getDatabase())."`.`".$db->escape_string($this->getName())."`");
			
		list($total, $quantity) = $r->fetch_row();
		
		if($quantity != $total)
			return number_format($total)." (".number_format($quantity)." in all)";
		else
			return number_format($total);
	}
	
	function getCombineTypes(mysqli $db)
	{
		$r = $db->query("select count(*), combine_type ".
			"from `".$db->escape_string($this->getDatabase())."`.`".$db->escape_string($this->getName())."` ".
			"group by combine_type");
			
		$data = array();
		
		while(list($count, $combine_type) = $r->fetch_row())
		{
			$data[$combine_type] = $count;
		}
		
		return $data;
	}
	
	function drop(mysqli $db)
	{
		//TODO: Cancel scale & upload job so thumbnails don't prevent the image folder being deleted
		
		$auctionDate = $this->getDate();
		
		AuctionSchedule::setStatus($db, $auctionDate, $this->oldStatus);
		
		$r = $db->query("select count(*) from `".$db->escape_string($this->getDatabase())."`.`".$db->escape_string($this->getName())."`");
		
		list($count) = $r->fetch_row();
		
		if($count == 0)
		{
			$db->query("drop table `".$db->escape_string($this->getDatabase())."`.`".$db->escape_string($this->getName())."`");
			
			return true;
		}
		else
		{
			throw new exception("Dated table has records in it! Cowardly refusing to drop non-empty table.");
		}
	}
	
	function getDate()
	{
		return $this->date;
	}
	
	function setDate(AuctionDate $date, mysqli $db)
	{
		$this->date = $date;
		$this->oldStatus = AuctionSchedule::getStatus($db, $date);
	}
	
	function setDescription($description)
	{
		$this->description = $description;
	}
	
	function setCodes($codes)
	{
		foreach($codes as $c)
		{
			if($c instanceof AuctionCode)
			{
				$this->codes[] = $c;
			}
			else
			{
				throw new exception("Wrong parameter passed to AuctionTable->setCodes");
			}
		}
	}
	
	function setLotNumberPrefix(LotNumberPrefix $lot_number)
	{
		$this->lot_number = $lot_number;
	}
	
	
	
	function getName()
	{
		if(!isset($this->tableName))
		{
			if(empty($this->date) || empty($this->codes) || empty($this->lot_number) || empty($this->description))
				throw new exception("AuctionTable not initialized");
				
			
			$name_parts[0] = $this->date->getForAuctionTable() . " " . $this->description;
			$name_parts[1] = "";
			$name_parts[2] = " " . $this->lot_number;
			$basenameSize = strlen($name_parts[0]) + strlen($name_parts[1]) + strlen($name_parts[2]); 
			
			if($basenameSize <= 64){
				$tmp_codes = array_values($this->codes);
				for($i=0; $i<count($tmp_codes); $i++){
					if($i==0 && ($basenameSize + 3 + strlen($tmp_codes[$i]))<=64){
						$name_parts[1] = " (" . $tmp_codes[$i] . ")";
					}elseif($i>0 && ($basenameSize + 3 + strlen($tmp_codes[$i]))<=64){
						$name_parts[1] = substr($name_parts[1],0,-1) . ", " . $tmp_codes[$i] . ")";
					}
					$basenameSize = strlen($name_parts[0]) + strlen($name_parts[1]) + strlen($name_parts[2]); 
					if($basenameSize >= 64){
						break;
					}
				}
			}
			
			//$name = $this->date->getForAuctionTable()." ".$this->description . " (".implode(", ", $this->codes).") ".$this->lot_number;
				
			$name = implode("",$name_parts);
			
      //JASEN - this is the part that was throwing the error, $name_parts[1] was empty because it couldn't fit any auction codes into this string "19-05-23 THURS window cards, Italy  French 1p, 2p, 4p, etc 6k" 
			if(strlen($name) > 64 || strlen($name_parts[1])==0)
				throw new exception("Table name is too long ('$name')",0x80000000);
				
			$this->tableName = $name;
		}
		
		return $this->tableName;
	}
	
	
	
	
	
	function init(mysqli $db, $name)
	{
		$r = $db->query("show tables from `listing_system` like '".$db->escape_string($name)."'");
		
		if($r->num_rows == 0)
		{
			$r = $db->query("show tables from `listing_system_archives` like '".$db->escape_string($name)."'");
			
			if($r->num_rows == 0)
			{
				throw new exception("No such table '$name'");
			}
			else
			{
				$this->database = "listing_system_archives";
			}
		}
		else
		{
			$this->database = "listing_system";
		}
		
		if(preg_match("/^([0-9]{2}-[0-9]{2}-[0-9]{2})(?: THURS| SUN|) ([^\(\)]+) \(([^\(\)]+)\) ([0-9][a-z])$/", $name, $match))
		{
			$this->date = new AuctionDate(new DateTimeImmutable($match[1]));
			$this->description = $match[2];
			$this->codes = $this->auctionCodes($match[3], $db);
			$this->lot_number = new LotNumberPrefix($match[4], true);
		}
		else
		{
			throw new exception ("Invalid table name '$name'");
		}
		
		$this->tableName = $name;
		
		$this->folder = new AuctionImageFolder($this);
	}
	
	
	
	function getLotNumberPrefix()
	{
		return $this->lot_number;
	}
	
	function getAuctionCodes()
	{
		return $this->codes;
	}
	
	function index(mysqli $db)
	{
		$failures = array();
		$r = $db->query("select ebay_item_number, style_id ".
			"from ".$this->getSqlName($db)." ".
			"where style_id is not null and ebay_item_number is not null");
			
		while(list($item_number, $style_id) = $r->fetch_row())
		{
			try
			{
				$db->query("insert into listing_system.item_link ".
					"set item_number = '".$db->escape_string($item_number)."', ".
					"`date` = '".$db->escape_string($this->date->format("Y-m-d"))."', ".
					"`style_id` = '".$db->escape_string($style_id)."' ");
			}
			catch(exception $e)
			{
				$failures[] = $e->__toString();
			}
		}
		
		if(empty($failures))
			return true;
		else
			return $failures;
	}
	
	public function hasAuctionCode($code)
	{
		foreach($this->codes as $code)
		{
			if($code->is($code))
				return true;
		}
		
		return false;
	}
	
	private function validateAuctionCodes()
	{
		foreach($this->codes as $code)
		{
			if($code->isValid() === false)
				return false;
		}
		
		return true;
	}
	
	private function auctionCodes($codes, $db)
	{
		$codes = explode(", ", $codes);
		
		foreach($codes as $k => $code)
		{
			$codes[$k] = new AuctionCode($code, $db);
		}
		
		return $codes;
	}
	
	static function getFromDate(mysqli $db, DateTimeImmutable $date)
	{
		$r = $db->query("show tables from listing_system");
		
		while(list($table) = $r->fetch_row())
		{
			if(substr($table, 0, 8) == $date->format("y-m-d"))
				return new self($db, $table);
		}
		
		return false;
	}
	
	function getItemByItemNumber(mysqli $db, $item_number)
	{
		$r = $db->query("select * ".
			"from ".$this->getSqlName($db)." ".
			"where ebay_item_number = '".$db->escape_string($item_number)."'");
			
		if($row = $r->fetch_assoc())
		{
			return new AuctionItem($db, $this, $row);
		}
		else
		{
			return false;
		}
	}
	
}


?>