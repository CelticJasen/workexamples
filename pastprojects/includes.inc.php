<?PHP
require_once("../auth.inc.php");
if(!isset($_SESSION['locks']))
	$_SESSION['locks'] = Array();
header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // Date in the past

define(MYSQL_WEBHOST,"www.emovieposter.com");
define(MYSQL_WEBUSER,"emoviepo");
define(MYSQL_WEBPASS,"Bb#u|@G5W7pn");
define(WEBDBNAME_GALLERY,"gallery");
define(WEBDBNAME_COMBINED,"combined");
define(WEBDBNAME_RESOURCES,"resources");
define(WEBDBNAME_MEMBERS,"members");

permissions_required(PERM_DESCRIPTIONS);

$dbhost = "poster-server";
$dbuser = "listing";
$dbpass = "system";
$conn = mysql_connect($dbhost, $dbuser, $dbpass) or die ("Error giblets88: " . mysql_error() );
mysql_select_db("listing_system") or die ("Error arrgh333, could not select DB: " . mysql_error() );
$username = mysql_real_escape_string($user->getName());

function remove_tab($string)
{
	return str_replace("\t", "", $string);
}

function create_auction_anything_csv()
{
	global $upload_directions, $_POST;
	
	$r = mysql_query3("SELECT id,title,description FROM auction_anything_export WHERE LENGTH(description) >= 8000 ORDER BY productid ASC");
	
	if(mysql_num_rows($r) !== 0)
	{
		echo "<form method='post' action='./index.php'>Fixing descriptions 8000 characters or longer<br />";
		$ids = Array();
		while($row = mysql_fetch_assoc($r))
		{
			$ids[] = $row['id'];
			echo "<br />$row[title] (<span style='font-size: 110%; font-weight: bold;' id='$row[id]chars'>".strlen($row['description'])."</span> chars)<br />".
				"<textarea onkeyup='document.getElementById(\"$row[id]chars\").innerHTML = this.value.length' style='width: 800px; height: 500px' name='description$row[id]'>".htmlspecialchars($row['description'])."</textarea>";
		}
		echo "<br /><input type='hidden' name='specialNotesDate' value='$_POST[specialNotesDate]' /><input type='submit' value='Submit' name='submit' /><input type='hidden' name='step' value='4' /></form>";
	}
	
	$limit = 2000;
			
	$r = mysql_query3("select title, description, category, price, productid, images, gallerytype, thumbnail from auction_anything_export order by productid");
	
	$csv_files = array("auctionCSVs/auctions-".$_POST['specialNotesDate'].".csv");
	
	$fp = fopen("auctionCSVs/auctions-".$_POST['specialNotesDate'].".csv",'w');
	
	
	//Figure out the number of extra image columns in the file
	$max_image_columns = 0;
	while($row = mysql_fetch_assoc($r))
	{
		$num = count(array_filter(array_map("trim", explode(";", $row['images'])), "strlen"));
		
		if($num > $max_image_columns)
			$max_image_columns = $num;
	}
	$max_image_columns = min($max_image_columns, 10);
	mysql_data_seek($r, 0);
	
	
	fwrite($fp,"title\tdescription\tcategory\tprice\tproductid\tthumbnail");
	fwrite($fp, str_repeat("\timage", $max_image_columns));
	fwrite($fp, "\r\n");

	$i=0;
	while($row = mysql_fetch_assoc($r))
	{
		$row = array_map("remove_tab", $row);
		$image_list = explode(";", $row['images']);
		
		fprintf($fp, "%s\t%s\t%s\t%s\t%s\t%s", 
			$row['title'], $row['description'], $row['category'], $row['price'], $row['productid'],
			$row['thumbnail']);
		
		foreach($image_list as $image)
		{
			fwrite($fp, "\t".$image);
		}
		
		fwrite($fp, "\r\n");
		
		fflush($fp);
		
		//Added new code for splitting up the CSV file into 500 items each. AK, 20130306
		$i++;
		
		if($i % 500 == 0)
		{
			fclose($fp);
			
			$fp = fopen("auctionCSVs/auctions-".$_POST['specialNotesDate']."_".($i/500).".csv",'w');
			
			$csv_files[] = "auctionCSVs/auctions-".$_POST['specialNotesDate']."_".($i/500).".csv";
	
			fwrite($fp,"title\tdescription\tcategory\tprice\tproductid\tthumbnail");
			fwrite($fp, str_repeat("\timage", $max_image_columns));
			fwrite($fp, "\r\n");
		}
	}

	fclose($fp);
	
	echo "<h2>Your upload file has been created!</h2>";
	echo "<ol>";
	foreach($csv_files as $filename)
	{
		echo "<li><a href='./$filename'>Click Here</a> to download the auction file to upload to ".
			"<a target='_blank' href='http://auctions.emovieposter.com/BulkLoader.taf?_function=dir'>auctions.emovieposter.com</a>.</li>\n";
	}
	echo "</ol>";
	echo str_replace("#total_items#", mysql_num_rows($r), $upload_directions);

	echo "<br /><br /><a href='./'>Back</a>";
	
	$f = fopen("work_log", "a");
	fwrite($f, "auction " . $_POST['table'] . "\r\n");
	fclose($f);
}


function get_description($film_title)
{	
	$result = mysql_query3(sprintf("select * from listing_system.descriptions where TITLE='%s'", 
		mysql_real_escape_string($film_title)));
	
	if(mysql_num_rows($result) == 1)
		return mysql_fetch_assoc($result);
	return array();
}

function get_tbl_types($type_code)
{
	$r = mysql_query3(sprintf("select * from listing_system.tbl_types where code = '%s'", mysql_real_escape_string($type_code)));
	
	if(mysql_num_rows($r))
		return mysql_fetch_assoc($r);
	return array();
}

function get_tbl_templates($template_date)
{
	$r = mysql_query3(sprintf("select * from listing_system.tbl_templates where templatedate = '%s'", 
		mysql_real_escape_string($template_date)));
	
	if(mysql_num_rows($r))
		return mysql_fetch_assoc($r);
	return array();
}

function get_day_code($day)
{
	switch($day)
	{
		case "Tuesday":
			$auctionType = 2;
			$galleryType = "gallery_main";
			$otherDays = "THURSDAY & SUNDAY";
			break;
			
		case "Thursday":
			$auctionType = 4;
			$galleryType = "thursday";
			$otherDays = "TUESDAY & SUNDAY";
			break;
			
		case "Sunday":
			$auctionType = 0;
			$galleryType = "sunday";
			$otherDays = "TUESDAY & THURSDAY";
			break;
	};
	
	return array($auctionType, $galleryType, $otherDays);
}



function die_with_honor($file="<unspecified>", $line="<unspecified>", $query="<unspecified>", $error="<unspecified>")
{
		die(json_encode(Array("error" => "error in file $file at line $line:\n$query:\n$error")));
}

function lock_record($table, $id)
{
	global $username;
	
	$table = mysql_real_escape_string($table);
	$id = mysql_real_escape_string($id);
	
	mysql_query("LOCK TABLES `locks` WRITE") or die_with_honor(__FILE__, __LINE__, $query, mysql_error());
		
	$query = "SELECT `who`, `when` FROM locks WHERE `table`='$table' AND table_id='$id'";
	$result = mysql_query($query) or die_with_honor(__FILE__, __LINE__, $query, mysql_error());

	if(mysql_num_rows($result))
	{
		$who = mysql_result($result,0,0);
		$then = mysql_result($result,0,1);
		$now = time();
		$when = date("m/d/Y H:i:s", $then);
		if(($now-$then) < 10)
			die(json_encode( Array("lock" => "LOCKED: The record you are trying to read (id $id) is currently locked by $who")));
	}

	foreach($_SESSION['locks'] as $k => $x)
	{
		if($x['form'] == 'review' && $x['table'] == $table)
		{
			$table_id = mysql_real_escape_string($x['table_id']);
			$query = "DELETE FROM `locks` WHERE table_id='$table_id' AND `form`='review' AND `who`='$username'";
			$result = mysql_query($query) or die_with_honor(__FILE__, __LINE__, $query, mysql_error());
			unset($_SESSION['locks'][$k]);
		}
	}

	$_SESSION['locks'][] = Array('table' => stripslashes($table), 'table_id' => stripslashes($id), 'form' => 'review');
	$time = time();
	$query = "INSERT INTO locks (`table`, table_id, `who`, `when`, `session_id`, `form`) VALUES "
			."('$table', '$id', '$username', '$time', '" . session_id() . "', 'review');";
	mysql_query($query) or die_with_honor(__FILE__, __LINE__, $query, mysql_error());

	mysql_query("UNLOCK TABLES") or die_with_honor(__FILE__, __LINE__, $query, mysql_error());
	
	//Purge locks that are no longer used (older than 60 seconds)
	mysql_query("DELETE FROM `locks` WHERE `when` < (UNIX_TIMESTAMP()-60)");
}

function purge_lock($table)
{
	$table = mysql_real_escape_string($table);
	$locks = $_SESSION['locks'];
	global $username;
	
	foreach($locks as $k => $x)
	{
		if($x['table'] == $table)
		{
			$table_id = mysql_real_escape_string($x['table_id']);
			$query = "DELETE FROM `locks` WHERE table_id='$table_id' AND `form`='review' AND `who`='$username'";
			$result = mysql_query($query) or die_with_honor(__FILE__, __LINE__, $query, mysql_error());
			unset($locks[$k]);
		}
	}
}


function dow($table)
{
	preg_match("/^([0-9]{2}-[0-9]{2}-[0-9]{2}) /", $table, $match);
	return date("l", strtotime("20".$match[1]));
}

function purge_all_locks()
{
	global $username;
	$session_id = session_id();
	$query = "DELETE FROM `locks` WHERE who='$username' AND `form`='review'";
	mysql_query($query) or die("Error purging locks:\n" . $query . "\n" . mysql_error());
	
	foreach($_SESSION['locks'] as $k => $x)
	{
		if($x['form'] == "review")
			unset($_SESSION['locks'][$k]);
	}
}

function q($query, $noDie = false)
{
	$r = mysql_query($query);
	if($r === false && $noDie === false)
		die("Error in query '$query': " . mysql_error());
	
	return $r;
}

function mysql_fetch_all($result)
{
	while($row = mysql_fetch_row($result))
		$rows[] = $row;
	return $rows;
}

function query($query)
{
	$r = mysql_query($query);
	
	if(!$r)
		die_with_honor(__FILE__, __LINE__, htmlspecialchars($query), mysql_error());
	else
		return $r;
}


function lot_number_pad($number, $pad)
{
	/*
		Pads just the first numeric portion of a string,
		ignoring the suffix.
	*/
	
	preg_match("/^([0-9]+)(.*)$/", $number, $matches);
	
	return str_pad($matches[1], $pad, "0", STR_PAD_LEFT).$matches[2];
}

function update_custom_shipping($table, $log = null)
{
	if(is_null($log))
		$log = fopen("/dev/null", "w");
	
	$r = mysql_query3("UPDATE `$table` SET shipping_paragraph = " . intVal($_POST['shipping']).
		", combine_type = " . intVal($_POST['default_combine_type']));
	
	/*******************************************************************************************
	 * Find custom values for shipping, and validate the ranges of numbers entered.
	 * The list must be a comma-separated list of single integers or ranges of integers in the form of "begin-end".
	 * Example: 1-200, 562, 563, 801-801, 890-999
	 *******************************************************************************************/
	$saved_settings = file_exists("shipping_ranges") ?
		eval('return '.file_get_contents("saved_settings").';') :
		Array();
		
	$saved_settings[$_POST['table']] = Array(
		"shipping" => $_POST['shipping'],
		"default_combine_type" => $_POST['default_combine_type'],
		"custom" => Array(),
	);
	
	$r = mysql_query3("select count(*) from `$table` where length(lot_number) = '5'");
	
	if(mysql_result($r, 0, 0) > 0)
		$pad_length = 3;
	else
		$pad_length = 4;
	
	foreach($_POST as $name => $val)
	{
		if(strpos($name, "shipping_range") === 0)
		{				
			fwrite($log, "$name\n");fflush($log);
			$number = substr($name, strlen("shipping_range_"));
			
			//Save the options chosen
			$saved_settings[$_POST['table']]['custom'][] = Array(
					$_POST["shipping_range_$number"], 
					$_POST["shipping_paragraph_$number"],
					$_POST["shipping_combine_type_$number"],
				);
			
			if(trim($val) == "")
				continue;
			
			//Split the ranges into an array, by comma
			$ranges = array_map("trim", explode(",", $val));
			
			foreach($ranges as $range)
			{
				$lotnums = array_map("trim", explode("-", $range));
				
				if(count($lotnums) == 0)
					die_invalid_value("Nothing entered");
				
				// Check for numericness
				foreach($lotnums as $num)
				{
					if(!preg_match("/^[0-9]/", $num))
						die_invalid_value("Invalid entry.");
					
					if($num < 1)
						die_invalid_value("Number has to be more than zero.");
				}
					
				if(count($lotnums) == 1)
				{
					$lotnums[0] = lot_number_pad($lotnums[0], $pad_length);
					
					$r = mysql_query3("UPDATE `$table` SET shipping_paragraph = ".$_POST['shipping_paragraph_'.$number].", ".
						"combine_type = ".$_POST['shipping_combine_type_'.$number]." WHERE SUBSTRING(lot_number, 3) = '$lotnums[0]'");
				}
				elseif(count($lotnums) == 2)
				{
					list($begin, $end) = $lotnums;
					
					$begin = lot_number_pad($begin, $pad_length);
					$end = lot_number_pad($end, $pad_length);
					
					if($end < $begin)
						die_invalid_value("End is less than begin");
					
					$r = mysql_query3("UPDATE `$table` SET shipping_paragraph = ".$_POST['shipping_paragraph_'.$number].", ".
						"combine_type = ".$_POST['shipping_combine_type_'.$number]." WHERE SUBSTRING(lot_number, 3) >= '$begin' ".
						"AND SUBSTRING(lot_number, 3) <= '$end'");
				}
				else
					die_invalid_value("More than 2?");
			}
		}
		
		$f = fopen("saved_settings", "w");
		fwrite($f, var_export($saved_settings, true));
		fclose($f);
		
		$f = fopen("last_shipping", "w");
		fwrite($f, $_POST['shipping']);
		fclose($f);
		
		$f = fopen("last_combine_type", "w");
		fwrite($f, $_POST['default_combine_type']);
		fclose($f);
	}
}

function die_invalid_value($message)
{
	mysql_query2("ROLLBACK");
	throw new Exception("Custom Shipping invalid: $message", 10010);
}


$upload_directions = <<<A
<br /><br /><a href='http://poster-server/wiki/index.php/Export_Auctions#Bulk_Upload_Wizard_on_AuctionAnything' target='_blank'>About this Page</a><br />
<span id='directions' style='display: none'>
<ol>
<li>Log into auctions.emovieposter.com as the admin</li>
<li>Go to Auctions -> Enter Items -> <a href="http://auctions.emovieposter.com/BulkLoader.taf?_function=dir" target="_blank">Bulk Upload Wizard</a></li>
<li>Click "Use the updated Bulk Upload Wizard"</li>
<li>Select "Step 3" and click "Next"</li>
<li>Click "Browse..." and select the CSV from the desktop (or wherever your web browser saved it).  Click "Next".  <i>Note: It will now upload the CSV to the auctions site which will take 2 to 7 minutes.  If there is a problem with it, clear your cache/cookies and try again from Step 3.  If it still won't load, contact Auction Anything right away (John Hotaling: 407-282-8568 x160; Chris is x210; also contact them through their Help menu).  It is also possible that the CSV file isn't formated correctly.  If so, have Aaron fix before anything else!</i></li>
<li>When successful, it will display a table of the data to be imported.  Scroll down to the bottom and look at the record number.  It should be: quantity of items + 1 (for the field names row).  You are just verifying that the correct number of records are there.  When satisfied, click "Next"</li>
<li>Select "Standard (with Limit/Proxy Bidding)" and click "Next"</li>
<li>On the "Upload: Images" screen, do not change anything.  Just click "Next"</li>
<li>Setting Start/End times.  IMPORTANT!  It is setup as EASTERN time, so if you want the auctions to end at 7, you have to set it to 8!  <br />
	<ul>
	<li>For Tuesday &amp; Thursday:
		<ul>
			<li>set it to Start at 8 and to close at 8:05.</li>
			<li>verify the correct start and end dates are selected.</li>
		</ul>
	</li>
	<li>For Sunday:
		<ul>
			<li>set it to Start at 4 and to close at 4:05.</li>
			<li>verify the correct start and end dates are selected.  You will always have to change it because it defaults to the day you are importing, NOT to the day of the actual auction.</li>
		</ul>
	</li>
	<li>For all:
		<ul><li>
		The auctions need to run approximately 90 minutes.  Use the suggested "stagger" time below UNLESS it is a major auction, then ask Bruce how long he wants the auction to run and then use this calculator to find the ideal staggering.
		</li></ul>
	</li>
	<li>Double-check all the settings as it is not easy to fix if something is wrong!  Once satisfied, click "Next"</li>
	</ul>
</li>
<li>Verify "Pending" mode is set then click "Next"</li>
<li>Click Finish.  It will now add the items to the auctions site.  This can take up to a couple of minutes.  When done, click "Close"</li>
<li>Check a couple of auctions by:
	<ul>
	<li>Go to Auctions -> <a href="http://auctions.emovieposter.com/AuctionHelp.taf" target="_blank">Search Items</a></li>
	<li>Under "Listing Status", select "Pending Admin Review"</li>
	<li>Click "Search"</li>
	<li>Open two different items and review them for accuracy.  NOTE: if doing bulk lots, verify a bulk lot and a regular item.  If they are good, then you are done.  If something is amiss, you may have to have Aaron fix the issue, delete the bad import, and then you can start over at Step 2.</li>
</li>
</ol>
</span>
<br />
<fieldset style='display: inline-block'><legend>Stagger Calculator</legend>
	Items: <input id='items' type='text' size='4' value='#total_items#' onkeyup='calculate_stagger();' /> 
	Run Time (in minutes): <input id='run' size='4' onkeyup='calculate_stagger();' type='text' value='90'/> 
	Start on minute: <input id='start_on_minute' size='4' onkeyup='calculate_stagger();' type='text' value='5' />
	Stagger Time (in seconds) <input size='4' readonly='readonly' type='text' id='stagger' />
	501st item <input size='7' readonly='readonly' type='text' id='hms' />
	
</fieldset>
<script type='text/javascript'>

function calculate_stagger()
{	
	var start_on_minute = parseInt(document.getElementById("start_on_minute").value) * 60
	var stagger = Math.max(Math.round(parseInt(document.getElementById("run").value) * 60 / parseInt(document.getElementById("items").value)), 6);
	
	document.getElementById("stagger").value = stagger
	
	
	var seconds = start_on_minute + (stagger * 501)
	var h = Math.floor(seconds / 3600).toString()
	var m = Math.floor((seconds - (h * 3600)) / 60)
	if(seconds - (h * 3600) - (m * 60))
		m++
	
	m = m.toString().padStart(2, "0")
	
	document.getElementById("hms").value = h+":"+m
}

calculate_stagger();
</script>
A;


session_write_close();
?>
