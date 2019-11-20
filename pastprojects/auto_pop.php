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
	$howStored = $_REQUEST[howStored];
  
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
			$setQuery = true;
			$query = "SELECT * FROM listing_system.photography_folders WHERE type_code LIKE '$typeCode%'";
		}
	}
	
	if($howStored != "")
	{
		if($setQuery)
		{
			$query .= " AND how_stored LIKE '$howStored%'";
		}
		else
		{
			$query = "SELECT * FROM listing_system.photography_folders WHERE how_stored LIKE '$howStored%'";
		}
	}
	
	$result = $dbConnection->query($query);
	
	if(mysqli_num_rows($result) == 1)
	{
		while($r = $result->fetch_row())
		{
			$queryResult['photoCode'] = $r[0];
			$queryResult['auctionCode'] = $r[1];
			$queryResult['typeCode'] = $r[2];
			$queryResult['howStored'] = $r[29];
		}
		
		echo json_encode($queryResult);
		//Mailer::mail("jasen@emovieposter.com", "auto_pop.php", print_r(json_encode($queryResult),true));
	}
?>