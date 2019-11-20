<?PHP
require_once("includes.inc.php");
session_write_close();
$spaces = "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;";
$query = "SELECT film_title, consignor FROM `00-00-00 main (MAIN)` where ".
	"status in ('new', 'described') and who_did=" . $user->getId() . " AND substr(photography_folder, 1, 7) = substr('$_REQUEST[photography_folder]', 1, 7)";
$result = $db->query($query);
if(mysql_num_rows($result) === 0)
{
  //echo $query;
	die("norows");
}

$setString = "<h3>" . ((mysql_num_rows($result) == 1) ? "1 record" : mysql_num_rows($result) . " records" ). " for <big>$_REQUEST[photography_folder]</big></h3>";
$setString .= "<table style='max-width: 1000px;' cellpadding='8'><tr>";
$row = mysql_fetch_row($result);
$titles[] = $row[0];
$consignor = $row[1];
$count2 = 0;

while($row)
{
	$row = mysql_fetch_row($result);
	if($row[1] != $consignor)
	{		
		$consignors[] = $consignor;
		$count2++;
		if($count2 == 5)
		{
			$setString .= "</tr><tr>";
			$count2 = 1;
		}
		$lastTitle = $titles[count($titles)-1];
		$firstTitle = $titles[0];
		$count = count($titles);
		$setString .= "<td><b>$consignor</b><br />$spaces records: $count<br />$spaces titles: \"$firstTitle\" to \"$lastTitle\"<br /></td>";
		$consignor = $row[1];
		unset($titles);
	}
	$titles[] = $row[0];
}
$setString .= "</tr></table>";

if(count($consignors))
{
	$warn = array();
	foreach($consignors as $c)
	{
		$r = mysql_query3("select 1 from tbl_consignorlist where ConsignorName like '".substr($c, 0, 5)."%'");
		if(mysql_num_rows($r) > 1)
		{
			$warn[] = $c;
		}
	}
	
	if(count($warn))
	{
		$setString .= "<div style='background: #FFC; padding: 5px; margin-bottom: 5px;'>Careful with names: ".implode(", ", $warn)."</div>";
	}
	
	if(preg_match("/^[a-z]{2}[0-9]{5}/i", $_REQUEST['photography_folder'], $match))
	{
		$photo_code = $match[0];
		
		$r = $db->select("photography_folders", Array("consignors", "auction_code", "describer_comments"), 
			"photography_code='".$photo_code."'");
		
		if(mysql_num_rows($r) == 1)
		{
			$photography_consignors = json_decode(mysql_result($r, 0, 0), true);
			$auction_code = mysql_result($r, 0, 1);
			$describer_comments = mysql_result($r, 0, 2);
			
			$new_photography_consignors = Array();
			foreach($photography_consignors as $c)
			{
				if(empty($previous))
				{
					$previous = $c;
					$new_photography_consignors[] = $c;
					continue;
				}
				
				if($c == $previous)
				{
					$previous = $c;
					continue;
				}
				else
				{
					$new_photography_consignors[] = $c;
					$previous = $c;
				}
			}
			$photography_consignors = $new_photography_consignors;
			
			if($photography_consignors !== $consignors)
			{
				$setString .= "<hr /><span id='consignor_warning' style='color:red; font-weight: bold; text-decoration: blink;'>Warning: </span>".
					"The consignors you entered do not match the ones entered by photography!<br />";
				$setString .= "<b style='margin-left: 20px'>You Entered</b>: &nbsp;" . implode(", ", $consignors) . "<br />";
				$setString .= "<b style='margin-left: 20px'>Photography</b>: " . implode(", ", $photography_consignors) . "<br />";
				
				//Added this stuff for highlighting that Phil wanted JJ, 20190715
				$photoTyped = implode(", ", $photography_consignors);
				$consignorTyped = implode(", ", $consignors);
				
				$newTypedArray = array();
				$oldTypedArray = array();
				$newTyped = "";
				$oldTyped = "";
				
				$describerTyped = "";
				$photographerTyped = "";
				
				if(sizeof($photography_consignors) < sizeof($consignors))
				{
					$questionMarks = sizeof($consignors) - sizeof($photography_consignors);
					foreach($consignors as $c)
					{
						if(in_array($c, $photography_consignors))
						{
							array_push($oldTypedArray, $c);
						}
						else
						{
							array_push($newTypedArray, $c);
						}
					}
					
					$newTyped = implode(", ",$newTypedArray);
					$oldTyped = implode(", ", $oldTypedArray);
					
					$describerTyped = "Describer typed: " . $oldTyped . ", <span style=\"background-color: #FFFF00;\">" . $newTyped . "</span>";
					
					if($questionMarks > 0)
					{
						$photographerTyped = "\nPhotographer typed: " . $oldTyped . "";
						for($i = 0; $i < $questionMarks; $i++)
						{
							$photographerTyped .= ", <span style=\"background-color: #FFFF00;\">??????</span>";
						}
						$photographerTyped .= "</pre>";
					}
					else
					{
						$photographerTyped = "\nPhotographer typed: " . $oldTyped . "</pre>";
					}
				}
				else if(sizeof($photography_consignors) > sizeof($consignors))
				{
					$questionMarks = sizeof($photography_consignors) - sizeof($consignors);
					foreach($photography_consignors as $p)
					{
						if(in_array($p, $consignors))
						{
							array_push($oldTypedArray, $p);
						}
						else
						{
							array_push($newTypedArray, $p);
						}
					}
					
					$newTyped = implode(", ",$newTypedArray);
					$oldTyped = implode(", ", $oldTypedArray);
					
					$photographerTyped = "\nPhotographer typed: " . $oldTyped . ", <span style=\"background-color: #FFFF00;\">" . $newTyped . "</span></pre>";
					
					if($questionMarks > 0)
					{
						$describerTyped = "Describer typed: " . $oldTyped . "";
						for($i = 0; $i < $questionMarks; $i++)
						{
							$describerTyped .= ", <span style=\"background-color: #FFFF00;\">??????</span>";
						}
					}
					else
					{
						$describerTyped = "Describer typed: " . $oldTyped . "";
					}
				}
				else
				{
				  foreach($photography_consignors as $c)
          {
            array_push($oldTypedArray, $c);
          }
          
					foreach($consignors as $c)
					{
						array_push($newTypedArray, $c);
					}
					
					$oldTyped = implode(", ", $oldTypedArray);
          $newTyped = implode(", ", $newTypedArray);
					
					$describerTyped = "Describer typed: " . $newTyped . "";
					$photographerTyped = "\nPhotographer typed: " . $oldTyped . "</pre>";
				}
				
				//Removed, no longer wanted. AK, 2011-12-27
				//Re-added for Phil, 2012-05-07
				$rcpts ="matt@emovieposter.com, phillip@emovieposter.com, jasen@emovieposter.com, steven@emovieposter.com"; 
				if($user->getId()==12){
				  //$rcpts ="steven@emovieposter.com";
				}
				
				mail($rcpts , "Wrong consignors on $_REQUEST[photography_folder]", 
				  "<a href=\"http://poster-server/wiki/index.php/Wrong_consignors_email\">About this email</a><br><br>\n\n" . 
					"<pre>$_REQUEST[photography_folder] described by ".$user->getName()." for $auction_code\n".
					//"Describer typed: " . $oldTyped . ", <span style=\"background-color: #FFFF00;\">" . $newTyped . "</span>".
					"" . $describerTyped . "".
					//"\nPhotographer typed: ".implode(", ", $photography_consignors).", <span style=\"background-color: #FFFF00;\">??????</span></pre>".
					"" . $photographerTyped . "".
					"<iframe style='width: 500px; height: 400px;' src='http://poster-server/descriptions/display_photo_folder_info.php".
					"?photography_code=".urlencode($photo_code)."'></iframe>", "Content-Type: text/html");
			}
			
			$setString .= "<hr /><label>Notes</label> ".
					"<input id='set_notes' style='width: 100%' value=\"".htmlspecialchars($describer_comments).
					"\" onkeyup='update_set_comment(this, ".json_encode($photo_code).")' /><hr />";
					
			if($photography_consignors !== $consignors)
			{
				$setString .= "If there is a mistake in the photography folder information, go ".
					"<a href='http://poster-server/photo_folder/?photography_code=".urlencode($photo_code)."' target='_blank' style='text-decoration: underline'>here</a>,".
					" correct it, and try again.<hr />";
			}
		}
	}
	
	$consignors = array_unique($consignors);
	foreach($consignors as $consignor)
	{
		$r = mysql_query3("select describer_notes from listing_system.tbl_consignorlist where ConsignorName = '".mysql_real_escape_string($consignor)."'");
		$notes = trim(mysql_result($r, 0, 0));
		
		if($notes != "")
		{
			$setString .= "<p style='width: 300px; color: red;'><b>$consignor</b><br />$notes</p>";
		}
	}
}

echo $setString;
?>
