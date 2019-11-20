<?php

class AdvancedSearch
{
	static $customers_field_map = array(
		"emails" => array(
			"field" => array("customers.email", "customers_emails.email"),
			"name" => "emails",
		),
		"customer_id" => array(
			"field" => array("customers.customer_id"),
			"name" => "customer_id",
		),
		"name" => array(
			"field" => array("customers.name", "customers_addresses.name"),
			"name" => "name",
		),
		"ship_attention_line" => array(
			"field" => array("customers.ship_attention_line", "customers_addresses.ship_attention_line"),
			"name" => "ship_attention_line",
		),
		"ship_address_line1" => array(
			"field" => array("customers.ship_address_line1", "customers_addresses.ship_address_line1"),
			"name" => "ship_address_line1",
		),
		"ship_address_line2" => array(
			"field" => array("customers.ship_address_line2", "customers_addresses.ship_address_line2"),
			"name" => "ship_address_line2",
		),
		"ship_city" => array(
			"field" => array("customers.ship_city", "customers_addresses.ship_city"),
			"name" => "ship_city",
		),
		"ship_state" => array(
			"field" => array("customers.ship_state", "customers_addresses.ship_state"),
			"name" => "ship_state",
		),
		"ship_country" => array(
			"field" => array("customers.ship_country", "customers_addresses.ship_country"),
			"name" => "ship_country",
		),
		"notes_for_invoice" => array(
			"field" => array("customers.notes_for_invoice"),
			"name" => "notes_for_invoice",
		),
		"phones" => array(
			"field" => array("phones"),
			"name" => "phones",
		),
		"email" => array(
			"field" => array("customers.email"),
			"name" => "email",
		),
		"Status_Account" => array(
			"field" => array("Status_Account"),
			"name" => "Status_Account",
		),
		"vip" => array(
			"field" => array("customers.vip"),
			"name" => "vip",
		),
		"pay_and_hold" => array(
			"field" => array("pay_and_hold"),
			"name" => "pay_and_hold",
		),
		"autoship" => array(
			"field" => array("(autoship.customer_id is not null)"),
			"name" => "autoship",
		),
		"rank" => array(
			"field" => array("rank"),
			"name" => "rank"
		),
		"frequent_buyer" => array(
			"field" => array("frequent_buyer"),
			"name" => "frequent_buyer",
		),
	);
	
	function __construct($db, $blab)
	{
		
	}
	
	static function filters_to_html($array, $field_map)
	{
		require_once("/webroot/includes/Mustache/Autoloader.php");
		Mustache_Autoloader::register();
		$mustache = new Mustache_Engine;

		$data = array(
			"fields" => self::field_map_to_numeric_array($field_map),
		);

		$html = "";
		
		
		foreach($array as $index => $filter)
		{
			if($filter['field'] == "Status_Account")
			{
				
			}
			else
			{
				$data['index'] = $index;
				$data['value'] = $filter['value'];
				
				$row = $mustache->render(file_get_contents("templates/html/filter_row.html"), $data);
				
				$row = str_replace(
					"<option value='".htmlspecialchars($filter['field'])."'", 
					"<option value='".htmlspecialchars($filter['field'])."' selected='selected'",
					$row);
					
				$row = str_replace(
					"<option value='".htmlspecialchars($filter['comp'])."'",
					"<option value='".htmlspecialchars($filter['comp'])."' selected='selected'",
					$row);
					
				$html .= $row;
			}
		}
		
		return $html;

	}
	
	static function filter_to_sql($array, $field_map, $db)
	{
		$fields = array();
		$where = "";
		
		foreach($array as $a)
		{
			if($a['field'] == "phones")
				$a['value'] = preg_replace("/[^0-9]/", "", $a['value']);
			
			if($a['comp'] == "display")
			{
				continue;
			}
			elseif($a['comp'] == "false")
			{
				$where .= "\r\n(".$field_map[$a['field']]['field'][0]." is null or ".
					$field_map[$a['field']]['field']." = '' or ".$field_map[$a['field']]['field']." = 0) and ";
			}
			elseif($a['comp'] == "true")
			{
				$where .= "\r\n".$field_map[$a['field']]['field'][0]." != 0 and ";
			}
			elseif("" != $a['value'] = trim($a['value']))
			{
				if($a['field'] == "Status_Account")
				{
					if($a['value'] == "null")
					{
						$where .= "\r\nStatus_Account is null and ";
					}
					elseif($a['value'] == "*")
					{
						$where .= "\r\nStatus_Account is not null and ";
					}
					else
					{
						$where .= "\r\nStatus_Account = '".$db->escape_string($a['value'])."' and ";
					}
				}
				else
				{			   	
					$where .= "\r\n(";	
					foreach($field_map[$a['field']]['field'] as $field)
					{
						$where .= $field." ";
						
						switch($a['comp'])
						{
							case "%%":
								$where .= "like '%".$db->escape_string($a['value'])."%' or ";
								break;
								
							case "!%%":
								$where .= "not like '%".$db->escape_string($a['value'])."%' or ";
								break;
								
							case "=":
								$where .= "= '".$db->escape_string($a['value'])."' or ";
								break;
								
							case "!=":
								$where .= "!= '".$db->escape_string($a['value'])."' or ";
								break;
								
							case "^";
								$where .= "like '".$db->escape_string($a['value'])."%' or ";
								break;
								
							case "$";
								$where .= "like '%".$db->escape_string($a['value'])."' or ";
								break;
								
							case ">";
								$where .= "> '".$db->escape_string($a['value'])."' or ";
								break;
								
							case "<":
								$where .= "< '".$db->escape_string($a['value'])."' or ";
								break;
							
							default:
								throw new exception("Invalid comparator", 10000);
								break;
						};
					}
					
					$where = substr($where, 0, -4).") and ";
				}
			}
		}
		
		$where = substr($where, 0, -4);
		
		return $where;
	}
	
	
	static function field_map_to_js($field_map)
	{
		$out = array();
		
		foreach($field_map as $k => $v)
		{
			$out[] = array(
				"name" => $v['name'],
				"value" => $k,
			);
		}
		
		return json_encode($out);
	}
	
	static function field_map_to_numeric_array($field_map)
	{
		$out = array();
		
		foreach($field_map as $k => $v)
		{
			if($v['name'] == "Status_Account")
				continue;
			
			$out[] = array(
				"name" => $v['name'],
				"value" => $k,
			);
		}
		
		return $out;
	}
	
	static function convert_linebreaks($text)
	{
		return str_replace(array("\r", "\n"), array("", "\\n"), $text);
	}
	
	static function process_row($row)
	{
		if(!is_null($row['Status_Account']))
		{
			if($row['Status_Account'] == "0")
				$row['blocked'] = "blocked";
			elseif($row['Status_Account'] == "1")
				$row['blocked'] = "active";
		}
		
		return $row;
	}
}

?>