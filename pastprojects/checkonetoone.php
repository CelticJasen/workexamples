<?PHP
	//Take some variables and check the database photography_folders table for a 1/1 match of those variables combined or otherwise
	
	$servername = "poster-server";
	$username = "listing";
	$password = "system";
	$setQuery = false;
	
	$consignor = $_REQUEST[consignor];
	$photoFolder = $_REQUEST[photoFolder];
	$auctionCode = $_REQUEST[auctionCode];
	$typeCode = $_REQUEST[typeCode];
  
	$dbConnection = new mysqli($servername, $username, $password);
	
	if ($dbConnection->connect_error)
	{
		die("Connection failed: " . $dbConnection->connect_error);
	}
	
	//These if things could probably be written better but they serve to put together the query statement
	if($consignor != "")
	{
		$query = "SELECT * FROM listing_system.photography_folders WHERE consignors LIKE '[%$consignor%]'";
		$setQuery = true;
	}
	
	if($photoFolder != "")
	{
		if($setQuery)
		{
			$query .= " AND photography_code LIKE '$photoFolder%'";
		}
		else
		{
			$setQuery = true;
			$query = "SELECT * FROM listing_system.photography_folders WHERE photography_code LIKE '$photoFolder%'";
		}
	}
	
	if($auctionCode != "")
	{
		if($setQuery)
		{
			$query .= " AND auction_code LIKE '$auctionCode%'";
		}
		else
		{
			$setQuery = true;
			$query = "SELECT * FROM listing_system.photography_folders WHERE auction_code LIKE '$auctionCode%'";
		}
	}
	
	if($typeCode != "")
	{
		if($setQuery)
		{
			$query .= " AND type_code LIKE '$typeCode%'";
		}
		else
		{
			$query = "SELECT * FROM listing_system.photography_folders WHERE type_code LIKE '$typeCode%'";
		}
		
	}
	
	switch ($_REQUEST['name'])
	{
			case "consignor2":
				
				$query .= " LIMIT 5000;";
				
				$result = $dbConnection->query($query);
				
				while($row = $result->fetch_row())
				{
					$temp[0] = str_replace(array( '[', ']', '"', '"'), '', $row[3]);
					
					foreach($temp as $t)
					{
						$lucky = explode(",", $t);
						foreach($lucky as $n)
						{
							$helpme[] = $n;
						}
					}
				}
				
				foreach($helpme as $j)
				{
					$arrayResult[] = $j;
				}
				
				$finally = implode(", ", $arrayResult);
				$finally = str_replace(", ", "', '", $finally);
				
				$r = $dbConnection->query("SELECT * FROM listing_system.tbl_consignorlist ".
					"WHERE ConsignorName IN ('" . $finally . "') AND ConsignorName LIKE '$consignor%' ORDER BY ConsignorName LIMIT 50;");
				
				$i = 0;
				$data = array();
				
				while($secondrow = $r->fetch_assoc())
				{
					$data[$i]['ConsignorName'] = $secondrow['ConsignorName'];
					$data[$i]['SameAs'] = $secondrow['SameAs'];
					$data[$i]['value'] = $secondrow['ConsignorName'];
					$i++;
				}
				
				echo(json_encode($data));
				
				break;
				
			case "photography_folder2":
				
				$query .= " LIMIT 50;";
				
				$result = $dbConnection->query($query);
					
				$i = 0;
				
				while($row = $result->fetch_row())
				{
					$arrayResult[$i]['value'] = $row[0];
					$arrayResult[$i]['auction_code'] = $row[1];
					$arrayResult[$i]['type_code'] = $row[2];
					$arrayResult[$i]['photography_code'] = $row[0];
					$i++;
				}
				
				echo json_encode2($arrayResult);
				
				break;
				
			case "auction_code":
				
				$query .= " LIMIT 50;";
				
				$result = $dbConnection->query($query);
				
				$i = 0;
				
				while($row = $result->fetch_row())
				{
					$arrayResult[$i]['label'] = $row[1];
					$arrayResult[$i]['auction_code'] = $row[1];
					$arrayResult[$i]['table_name'] = $row[1];
					$i++;
				}
				
				echo json_encode2($arrayResult);
				
				break;
				
			case "type_code":
				
				$query .= " LIMIT 50;";
				
				$result = $dbConnection->query($query);
				
				while($row = $result->fetch_row())
				{
					$arrayResult[] = $row[2];
				}
				
				$arrayResult = array_unique($arrayResult);
				
				echo json_encode2($arrayResult);
				
				break;
	}
?>