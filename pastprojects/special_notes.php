<?php
$perpage = 20;

function getNextUnusedAuctionStartDay($offsetTimestamp, $dayMask = 0x15) {
  $query = 'SELECT dateid,day FROM gallery.special_notes WHERE dateid>=DATE_FORMAT(CURDATE(),"%Y%m%d")';

  if (($result = mysql_query($query)) !== false && mysql_num_rows($result) > 0) {
    while ($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
      $specialNotesDaysInUse[] = $row['dateid'];
    }
  }
  return getNextClosestAuctionStartDay($specialNotesDaysInUse, $offsetTimestamp, $dayMask);
}

function getNextClosestAuctionStartDay($filterDays = array(), $offsetTimestamp, $dayMask = 0x7F) {
  if (empty($offsetTimestamp)) {
    $offsetTimestamp = strtotime("today");
  }

  $yesterday_timestamp = strtotime("yesterday", $offsetTimestamp);
  if ($dayMask & 0x01)
    $closestDays[] = strtotime("next Sunday", $yesterday_timestamp);

  if ($dayMask & 0x02)
    $closestDays[] = strtotime("next Monday", $yesterday_timestamp);

  if ($dayMask & 0x04)
    $closestDays[] = strtotime("next Tuesday", $yesterday_timestamp);

  if ($dayMask & 0x08)
    $closestDays[] = strtotime("next Wednesday", $yesterday_timestamp);

  if ($dayMask & 0x10)
    $closestDays[] = strtotime("next Thursday", $yesterday_timestamp);

  if ($dayMask & 0x20)
    $closestDays[] = strtotime("next Friday", $yesterday_timestamp);

  if ($dayMask & 0x40)
    $closestDays[] = strtotime("next Saturday", $yesterday_timestamp);

  sort($closestDays);

  while (!empty($closestDays)) {
    if (in_array(date("Ymd", $closestDays[0]), $filterDays)) {
      printf("<div style=\"font-size:75%%;\">%s is in use!</div>\n", date("Ymd (D)", $closestDays[0]));
      $removedDate = array_shift($closestDays);
    } else {
      return $closestDays[0];
    }
  }

  return getNextClosestAuctionStartDay($filterDays, $removedDate + 86400, $dayMask);
}

?>
<!doctype html>
<html>
<head>
<TITLE>Special Notes</TITLE>
<style type='text/css'>
#charleft
{
	font-style: italic;
}
td{padding:0.25em 1em;}

</style>
<script type='text/javascript' src='jscal/calendar3.js'></script>
<script type='text/javascript' src='/includes/jquery/jquery-1.9.1.min.js'></script>
<script type="text/javascript">

/*changed = document.getElementById("previewdiv");

window.onbeforeunload = function()
{
  console.log("leave")
  if(changed != document.getElementById("previewdiv"))
  {
    return 'Are you sure you want to navigate away from this page?';
  }
}*/

function leaveAlert()
{
	var answer = confirm("Are you sure you want to leave?")
	if(answer)
	{
		window.location="landing.php";
	}
}

</script>
<?

function genNewValues($auctday){
//$auctday = ($thisday>0&&$thisday<=2 ? 2 : ($thisday>2&&$thisday<=4 ? 4 : 7));
$today = date("w");
$diff = ($auctday<$today ? $auctday+7 : $auctday) - $today;
$curtime = time();
$output[0] = date("Ymd",$curtime+($diff*86400));
$output[1] = date("Y-m-d",$curtime+($diff*86400)+604800);
$output[2] = date("n/j/Y",$curtime+($diff*86400)+604800);
return $output;
}
$tueValues = genNewValues(2);
$thuValues = genNewValues(4);
$sunValues = genNewValues(0);
?>
<script type='text/javascript'>

function check_for_mistakes()
{
	start = document.getElementById("indateid").value //20120816
	end = document.getElementById("dateend").value //2012-08-16
	stime = document.getElementById("stime").value //2012-08-16
	
	start_date = new Date(start.slice(0,4), start.slice(4,6)-1, start.slice(6,8), stime.slice(0,2), stime.slice(3,2))
	end_date = new Date(end.slice(0,4), end.slice(5,7)-1, end.slice(8,10), stime.slice(0,2), stime.slice(3,2))
	days = ((end_date-start_date)/1000/60/60/24)
	
	/*if(document.getElementById("day_select").value == 0 && days != 14)
	{
		return confirm("Did you mean to set this auction to "+days+" days?")
	}
	else */if(days != 7)
	{
		return confirm("Did you mean to set this auction to "+days+" days?")
	}
	
	return true
}

var old_dateval = "";

function updatespdateend(val)
{
	if(val != old_dateval)
	{
		var year = parseInt(val.split("-")[0]);
		var mon = parseFloat(val.split("-")[1]);
		var day = parseFloat(val.split("-")[2]);
		
		document.getElementById("spdateend").innerHTML = mon + "/" + day + "/" + year;
	}
}

function autoupdatetime(id,dodateid){
if(dodateid=="undefined"){
dodateid = false;
}
	switch(id)
	{
	case '2':
		document.getElementById('stime').value="19:00";
		break;
	case '4':
		document.getElementById('stime').value="19:00";
		break;
	case '0':
		document.getElementById('stime').value="15:00";
		break;
	}
	if(dodateid==true){
	autoupdateid(id);
	}
}

function autoupdateid(id){
	switch(id)
	{
	case '2':
		document.getElementById('indateid').value="<?=$tueValues[0]?>";
		document.getElementById('spdateid').innerHTML="<?=$tueValues[0]?>";
		document.getElementById('spdateend').innerHTML="<?=$tueValues[2]?>";
		document.getElementById('dateend').value="<?=$tueValues[1]?>";
		break;
	case '4':
		document.getElementById('indateid').value="<?=$thuValues[0]?>";
		document.getElementById('spdateid').innerHTML="<?=$thuValues[0]?>";
		document.getElementById('spdateend').innerHTML="<?=$thuValues[2]?>";
		document.getElementById('dateend').value="<?=$thuValues[1]?>";
		break;
	case '0':
		document.getElementById('indateid').value="<?=$sunValues[0]?>";
		document.getElementById('spdateid').innerHTML="<?=$sunValues[0]?>";
		document.getElementById('spdateend').innerHTML="<?=$sunValues[2]?>";
		document.getElementById('dateend').value="<?=$sunValues[1]?>";
		break;
	}
}

</script>
</head>
<body>
<center><h1>Calendar / Special Notes</h1> <a href='http://poster-server/wiki/index.php/Special_Notes'>About this page</a></center>
<a href='javascript:leaveAlert();' style='color:#0000FF;text-decoration:none;font-size:10px;'>&lt;-- Back to Landing</a>
<br /><br />
<?
mysql_connect2(MYSQL_HOST,MYSQL_USER,MYSQL_PASS);
mysql_select_db2(DBNAME_GALLERY);

$query = 'SELECT * FROM `new_specialnotes_paragraph` ORDER BY `snp_title`;';
$result = mysql_query($query);
if(mysql_num_rows($result)>0){
	while($row = mysql_fetch_array($result,MYSQL_ASSOC)){
		$snp_array[] = $row;
	}
}

$query = 'SELECT * FROM `new_specialnotes_shiptype` where `hide` = \'0\' ORDER BY `snst_type`, `snst_amount`;';
$result = mysql_query($query);
if(mysql_num_rows($result)>0){
	while($row = mysql_fetch_array($result,MYSQL_ASSOC)){
		$snst_array[] = $row;
	}
}

if(!empty($_GET['a']) && $_GET['a'] == "new")
{
	
	// Add new note
	if(!empty($_POST['dateid']) && isset($_POST['day']) && !empty($_POST['title']) && !empty($_POST['content']))
	{
		/*
		$starty = substr($_POST['dateid'],0,4);
		$startm = substr($_POST['dateid'],4,2);
		$startd = substr($_POST['dateid'],6);
		$starttime = strtotime("$starty-$startm-$startd 19:00");
		$endtime = strtotime($_POST['dateend'] . " 20:30");
		*/
		
		$starty = substr($_POST['dateid'],0,4);
		$startm = substr($_POST['dateid'],4,2);
		$startd = substr($_POST['dateid'],6);
		$starth = substr($_POST['stime'],0,2);
		$startmin = substr($_POST['stime'],3,2);
		$endh = $starth + 1;
		$endm = $startmin;
		$starttime = strtotime("$starty-$startm-$startd $starth:$startmin");
		$endtime = strtotime($_POST['dateend'] . " $endh:$endm");

		
		$query = 'INSERT INTO `new_specialnotes_paragraph_map` (`sn_dateid`,`snp_id`,`snst_id`) VALUES (\''.$_POST['dateid'].'\',\''.$_POST['shippingparagraph'].'\',\''.$_POST['combinetype'].'\') ON DUPLICATE KEY UPDATE `snp_id`=\''.$_POST['shippingparagraph'].'\',`snst_id`=\''.$_POST['combinetype'].'\';';
		mysql_query($query);
		if(mysql_error()!==""){
			echo "ERROR: could not insert shipping paragraph record " . mysql_error();
		}
		
		if(!empty($_POST['free_item_link'])){
			$query = 'INSERT INTO `new_specialnotes_bonusitems` (`sn_dateid`,`snb_link`,`snb_title`) VALUES (\''.$_POST['dateid'].'\',\''.$_POST['free_item_link'].'\',\''.$_POST['free_item'].'\') ON DUPLICATE KEY UPDATE `snb_link`=\''.$_POST['free_item_link'].'\',`snb_title`=\''.$_POST['free_item'].'\';';
			mysql_query($query);
			if(mysql_error()!==""){
				echo "ERROR: could not insert bonus item record " . mysql_error();
			}
		}
		
		$query = "INSERT INTO special_notes (dateid,day,title,content,dateend,lot_num,starttime,endtime,short_title,global_content,auction_homepage,free_item,logo_url) VALUES ('".$_POST['dateid']."','".intval($_POST['day'])."','".mysql_real_escape_string($_POST['title'])."','".mysql_real_escape_string($_POST['content'])."','".$_POST['dateend']."','".$_POST['lot_num']."','$starttime','$endtime','".mysql_real_escape_string($_POST['short_title'])."','".mysql_real_escape_string($_POST['global_content'])."','".mysql_real_escape_string($_POST['auction_homepage'])."','".mysql_real_escape_string($_POST['free_item'])."', '".mysql_real_escape_string($_POST['logo_url'])."')";
//echo $query;

		if(mysql_query($query))
		{
			echo "Added new note successfully!";
			echo "<br /><br /><a href='special_notes.php'>Back to Special Notes</a><br />";
		}
		else
		{
			echo "<span style='color:red;font-weight:bold;'>An Error Occured!</span><br />MySQL Said: " . mysql_error();
		}

	}
	else
	{
		?>
		<script type='text/javascript'>
			
			function changeDate(vel)
			{
				// change the date value by setting date, and adding 86400000(# of milliseconds in 1 day)*vel to the time in milliseconds then getting new output
				date = $('indateid').value;
				d1 = new Date();
				d1.setFullYear(date.slice(0,4));
				d1.setMonth(parseFloat(date.slice(4,6)) - 1);
				d1.setDate(date.slice(6));
				
				d1.setTime(d1.getTime() + (86400000*vel));

				newdate = d1.getFullYear() + (d1.getMonth() < 9 ? "0" : "") + (d1.getMonth()+1) + (d1.getDate() < 10 ? "0" : "") + d1.getDate() + "";
				$('indateid').value = newdate;
				$('spdateid').innerHTML = newdate;
			}
			
			function showpreview()
			{
				// show preview of content
				$("previewdiv").innerHTML = $("content").value;
				
				$("content").style.display = "none";
				$("previewdiv").style.display = "";
				$("prevbtn").style.display = "none";
				$("editbtn").style.display = "";
			}
			
			function showedit()
			{
				$("content").style.display = "";
				$("previewdiv").style.display = "none";
				$("prevbtn").style.display = "";
				$("editbtn").style.display = "none";
			}
			function updateCharsLeft(elementName,maxlen,container)
			{
				if(maxlen - document.getElementsByName(elementName)[0].value.length < 0)
				{
					$(container).innerHTML = "(<font color=\"#c32200\"><b>" + (maxlen - document.getElementsByName(elementName)[0].value.length) + " chars left</b></font>)";
				}
				else
				{
					$(container).innerHTML = "(" + (maxlen - document.getElementsByName(elementName)[0].value.length) + " chars left)";
				}
			}
			function checkMajorAuction(elementName, container, elementSender)
			{
				if(document.getElementsByName(elementSender)[0].value.indexOf("Major ") > -1)
				{
					document.getElementsByName(elementName)[0].required = true;
					$(container).innerHTML = "(<font color=\"#c32200\"><b>REQUIRED NOW</b></font>)";
				}
				else
				{
					document.getElementsByName(elementName)[0].required = false;
					$(container).innerHTML = "(<font color=\"#228B22\"><b>only required for major auctions</b></font>)";
				}
			}
			function $(id)
			{	return document.getElementById(id);	}
			setInterval("updatespdateend(document.forms[0].dateend.value)",100);
		</script>
		<form action="special_notes.php?a=new" method="POST" onsubmit="return check_for_mistakes()">
		<table>
		<?
		$today = date('w');
	
/*$auctday = ($today>0&&$today<=2 ? 2 : ($today>2&&$today<=4 ? 4 : 7));
$diff = ($auctday<$today ? $auctday+7 : $auctday) - $today;
$nextauctid = date("Ymd",time()+($diff*86400));
$nextauctend = date("Y-m-d",time()+($diff*86400)+604800);
$nextauctendhuman = date("n/j/Y",time()+($diff*86400)+604800);




$auctiondays = array(2,4,7);
$auctionindex = array_search($auctday,$auctiondays);
if($auctionindex===false){$auctionindex=0;}

$dayCount=1;

for($i=0;$i<6;$i++){
  $query = sprintf('SELECT * FROM gallery.special_notes WHERE dateid=\'%s\';',$nextauctid);
  if(($result = mysql_query($query))!==false && mysql_num_rows($result)>0){
    echo "<div>A note exists for ${nextauctid}, using next valid day</div>";
    $weekMult = (604800*floor(($i+$dayCount)/count($auctiondays)));
    $today = ((date('w') + (2 * ($dayCount++)))%7);
    $auctday = ($today>0&&$today<=2 ? 2 : ($today>2&&$today<=4 ? 4 : 7));
    $diff = ($auctday<$today ? $auctday+7 : $auctday) - date('w');
    $nextauctid = date("Ymd",time()+($diff*(86400)) + $weekMult);
    $nextauctend = date("Y-m-d",time()+($diff*86400) + $weekMult+604800);
    $nextauctendhuman = date("n/j/Y",time()+($diff*86400)+ $weekMult+604800);
    //var_dump($today,$auctday,$diff,$nextauctid,$nextauctend,$nextauctendhuman,$weekMult);
  }else{
    break;
  }
}*/

if(!empty($_GET['dayMask']) && intval($_GET['dayMask'])>0){
  $dayMask = intval($_GET['dayMask']);
}else{
  $dayMask = 0x15;
}

$nextauctTimestamp = getNextUnusedAuctionStartDay(0,$dayMask);
$nextauctStartDay = date("w",$nextauctTimestamp);
$nextauctid = date("Ymd",$nextauctTimestamp);
$nextauctend = date("Y-m-d",strtotime("+1 week",$nextauctTimestamp));
$nextauctendhuman = date("n/j/Y",strtotime("+1 week",$nextauctTimestamp));

echo "<tr><td>Quick Select</td><td><ul style=\"margin:0;padding:0;list-style: none\">
        <li style=\"display:inline;\"><a href=\"?a=new&dayMask=1\">Sunday</a></li>
        <li style=\"display:inline;\"><a href=\"?a=new&dayMask=4\">Tuesday</a></li>
        <li style=\"display:inline;\"><a href=\"?a=new&dayMask=16\">Thursday</a></li>
      </ul></td></tr>\n";

    echo "<tr><td>dateid: </td><td><a style='text-decoration:none;' href='javascript:changeDate(-1)'>&lt;&lt;</a><input type='hidden' id='indateid' name='dateid' value='".$nextauctid."' /><span id='spdateid'>".$nextauctid."</span><a style='text-decoration:none;' href='javascript:changeDate(1)'>&gt;&gt;</a></td></tr>";
    echo "<tr><td>Day: </td><td><select name='day' id='day_select' onchange=\"autoupdatetime(this.options[this.selectedIndex].value,true);\">";
    echo "<option value='0' ".($nextauctStartDay == 0 ? "selected=\"selected\"" : "" ).">Sun</option>";
    echo "<option value='2' ".($nextauctStartDay == 2 ? "selected=\"selected\"" : "" ).">Tue</option>";
    echo "<option value='4'  ".($nextauctStartDay == 4 ? "selected=\"selected\"" : "" ).">Thu</option>";
    echo "</select></td></tr>";
    echo "<tr><td>Title: </td><td><input type='text' name='title' size='80' onblur='checkMajorAuction(\"logo_url\",\"requireShow\",\"title\");' onkeyup='updateCharsLeft(\"title\",\"255\",\"charleft\");' /> <span id='charleft'></span></td></tr>";
    echo "<tr><td>Short Title: </td><td><input type='text' name='short_title' size='60' onkeyup='updateCharsLeft(\"short_title\",\"50\",\"charleft2\");' /> <span id='charleft2'></span></td></tr>";
    echo "<tr><td>Date End: </td><td><input type='hidden' name='dateend' id='dateend' value='".$nextauctend."' onchange='updatespdateend(this.value)' /><span id='spdateend'>".$nextauctendhuman."</span> <a href='javascript:cal1.popup();'><img src='jscal/img/cal.gif' width='16' height='16' border='0' alt='Click Here to Pick a Date' title='Click Here to Pick a Date'></a></td></tr>";
    echo "<tr><td>Time End: </td><td><input type=\"text\" name=\"stime\" value=\"".($nextauctStartDay == 0 ? "15" : "19" ).":00\" id=\"stime\"> HH:MM 24HR - <span style=\"font-weight:bold;\">15:00 for Sunday Auction! 19:00 for Tuesday & Thursday Auction!</span></td></tr>";
    echo "<tr><td>Lot Nums: </td><td><input type='text' name='lot_num' value='".$row['lot_num']."' size='2' /><br /></td></tr>";


/*
		echo "<tr><td>dateid: </td><td><a style='text-decoration:none;' href='javascript:changeDate(-1)'>&lt;&lt;</a><input type='hidden' id='indateid' name='dateid' value='".$nextauctid."' /><span id='spdateid'>".$nextauctid."</span><a style='text-decoration:none;' href='javascript:changeDate(1)'>&gt;&gt;</a></td></tr>";
		echo "<tr><td>Day: </td><td><select name='day' id='day_select' onchange=\"autoupdatetime(this.options[this.selectedIndex].value,true);\">";
		echo "<option value='0' ".($today > 4 ? "selected=\"selected\"" : "" ).">Sun</option>";
		echo "<option value='2' ".($today > 0 && $today <= 2 ? "selected=\"selected\"" : "" ).">Tue</option>";
		echo "<option value='4'  ".($today > 2 && $today <= 4 ? "selected=\"selected\"" : "" ).">Thu</option>";
		echo "</select></td></tr>";
		echo "<tr><td>Title: </td><td><input type='text' name='title' size='80' maxlength='255' onkeyup='updateCharsLeft(\"title\",\"255\",\"charleft\");' /> <span id='charleft'></span></td></tr>";
		echo "<tr><td>Short Title: </td><td><input type='text' name='short_title' size='60' maxlength='50' onkeyup='updateCharsLeft(\"short_title\",\"50\",\"charleft2\");' /> <span id='charleft2'></span></td></tr>";
		echo "<tr><td>Date End: </td><td><input type='hidden' name='dateend' id='dateend' value='".$nextauctend."' onchange='updatespdateend(this.value)' /><span id='spdateend'>".$nextauctendhuman."</span> <a href='javascript:cal1.popup();'><img src='jscal/img/cal.gif' width='16' height='16' border='0' alt='Click Here to Pick a Date' title='Click Here to Pick a Date'></a></td></tr>";
		echo "<tr><td>Time End: </td><td><input type=\"text\" name=\"stime\" value=\"".($today > 4 ? "15" : "19" ).":00\" id=\"stime\"> HH:MM 24HR - <span style=\"font-weight:bold;\">15:00 for Sunday Auction!</span></td></tr>";
		echo "<tr><td>Lot Nums: </td><td><input type='text' name='lot_num' value='".$row['lot_num']."' size='2' /><br /></td></tr>";
*/		
		echo "<tr><td>Shipping: </td><td><select name=\"shippingparagraph\"><option value=\"0\"></option>";
		foreach($snp_array as $value){
			echo "<option value=\"".$value['snp_id']."\"".($row['snp_id']==$value['snp_id']?" selected=\"selected\"":"").">".$value['snp_title']."</option>";
		}
		echo "</select></td></tr>";
		
		echo "<tr><td>Combine Type: </td><td><select name=\"combinetype\"><option value=\"0\"></option>";
		foreach($snst_array as $value){
			echo "<option value=\"".$value['snst_id']."\"".($row['snst_id']==$value['snst_id']?" selected=\"selected\"":"").">".$value['snst_text']."</option>";
		}
		echo "</select></td></tr>";
		
				echo "<tr><td>Free Item: </td><td><input type=\"text\" name=\"free_item\" value=\"NOTHING FREE\" style=\"width:20em;\"></td></tr>";
				
		echo "<tr><td>Free Item Link:</td><td><input type=\"text\" name=\"free_item_link\" value=\"\" style=\"width:40em\"></td></tr>";
		echo "<tr><td>Logo URL:</td><td><input type='text' name='logo_url' style='width: 100%' /><span id='requireShow'></span></td></tr>";
		
		echo "<tr><td colspan='2'>HTML Content:<br /><div id='previewdiv' style='display:none;width:320;'></div><textarea id='content' name='content' rows=20 cols=80></textarea></td></tr>";
		
		//echo "<tr><td colspan='2'>Global Content:<br /><div id='previewdiv' style='display:none;width:320;'></div><textarea id='global_content' name='global_content' rows=5 cols=80></textarea></td></tr>";
		
		echo "<tr><td colspan='2'>Auction Homepage:<br /><div id='previewdiv' style='display:none;width:320;'></div><textarea id='auction_homepage' name='auction_homepage' rows=5 cols=80></textarea></td></tr>";
		
		echo "<tr><td colspan='2'><input type='button' id='editbtn' style='display:none;' value='Edit' onclick='showedit()' />";
		echo "<input type='button' id='prevbtn' value='Preview' onclick='showpreview()'>&nbsp;&nbsp;<input type='submit' value='Add Note' />";
		echo "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<input type='button' value='Cancel' onclick='history.back();'/></td></tr>";
		?>
		</table>
		</form>
		<?
	}
}
elseif(!empty($_GET['a']) && $_GET['a'] == "edit")
{
	// Edit an existing note
			
	if(!empty($_POST['title']) && !empty($_POST['content']))
	{
		$starty = substr($_POST['dateid'],0,4);
		$startm = substr($_POST['dateid'],4,2);
		$startd = substr($_POST['dateid'],6);
		$starth = substr($_POST['stime'],0,2);
		$startmin = substr($_POST['stime'],3,2);
		$endh = $starth + 1;
		$endm = $startmin;
		$starttime = strtotime("$starty-$startm-$startd $starth:$startmin");
		$endtime = strtotime($_POST['dateend'] . " $endh:$endm");
		
		$query = 'INSERT INTO `new_specialnotes_paragraph_map` (`sn_dateid`,`snp_id`,`snst_id`) VALUES (\''.$_POST['dateid'].'\',\''.$_POST['shippingparagraph'].'\',\''.$_POST['combinetype'].'\') ON DUPLICATE KEY UPDATE `snp_id`=\''.$_POST['shippingparagraph'].'\',`snst_id`=\''.$_POST['combinetype'].'\';';
		mysql_query($query);
		if(mysql_error()!==""){
			echo "ERROR: could not insert shipping paragraph record " . mysql_error();
		}
		
		if(!empty($_POST['free_item_link'])){
			$query = 'INSERT INTO `new_specialnotes_bonusitems` (`sn_dateid`,`snb_link`,`snb_title`) VALUES (\''.$_POST['dateid'].'\',\''.$_POST['free_item_link'].'\',\''.$_POST['free_item'].'\') ON DUPLICATE KEY UPDATE `snb_link`=\''.$_POST['free_item_link'].'\',`snb_title`=\''.$_POST['free_item'].'\';';
			mysql_query($query);
			if(mysql_error()!==""){
				echo "ERROR: could not insert bonus item record " . mysql_error();
			}
		}
		
		$query = "UPDATE special_notes SET dateid = '".$_POST['dateid']."', day = '".$_POST['day']."', title = '".mysql_real_escape_string($_POST['title'])."',content = '".mysql_real_escape_string($_POST['content'])."', dateend = '".$_POST['dateend']."', lot_num = '".$_POST['lot_num']."', starttime = '$starttime', endtime = '$endtime', short_title = '".mysql_real_escape_string($_POST['short_title'])."',global_content = '".mysql_real_escape_string($_POST['global_content'])."',auction_homepage = '".mysql_real_escape_string($_POST['auction_homepage'])."',free_item = '".mysql_real_escape_string($_POST['free_item'])."',logo_url='".mysql_real_escape_string($_POST['logo_url'])."' WHERE dateid = '".$_GET['edit']."'";
		if(mysql_query($query))
		{
			echo "Changed note successfully! You should now double-check the Special Notes and &quot;Preview&quot; it to make sure it looks like you intended.";
			echo "<br /><br /><a href='special_notes.php?edit=".$_GET['edit']."&p=".$_GET['p']."'>Back to Special Notes</a><br />";
		}
		else
		{
			echo "<span style='color:red;font-weight:bold;'>An Error Occured!</span><br />MySQL Said: " . mysql_error();
		}
	}
	else
	{
		?>
		<script type='text/javascript'>
		
		function changeDate(vel)
		{
			// change the date value by setting date, and adding 86400000(# of milliseconds in 1 day)*vel to the time in milliseconds then getting new output
			date = $('indateid').value;
			d1 = new Date();
			d1.setFullYear(date.slice(0,4));
			d1.setMonth(parseFloat(date.slice(4,6)) - 1);
			d1.setDate(date.slice(6));

			d1.setTime(d1.getTime() + (86400000*vel));
			
			newdate = d1.getFullYear() + (d1.getMonth() < 10 ? "0" : "") + (d1.getMonth()+1) + (d1.getDate() < 9 ? "0" : "") + d1.getDate() + "";
			$('indateid').value = newdate;
			$('spdateid').innerHTML = newdate;
		}
		
		function showpreview()
		{
			// show preview of content
			$("previewdiv").innerHTML = $("content").value;
			$("content").style.display = "none";
			$("previewdiv").style.display = "";
			$("prevbtn").style.display = "none";
			$("editbtn").style.display = "";
		}
	
		function showedit()
		{
			$("content").style.display = "";
			$("previewdiv").style.display = "none";
			$("prevbtn").style.display = "";
			$("editbtn").style.display = "none";
		}
		function updateCharsLeft(elementName,maxlen,container)
		{
			if(maxlen - document.getElementsByName(elementName)[0].value.length < 0)
			{
				$(container).innerHTML = "(<font color=\"#c32200\"><b>" + (maxlen - document.getElementsByName(elementName)[0].value.length) + " chars left</b></font>)";
			}
			else
			{
				$(container).innerHTML = "(" + (maxlen - document.getElementsByName(elementName)[0].value.length) + " chars left)";
			}
		}
		function checkMajorAuction(elementName, container, elementSender)
		{
			if(document.getElementsByName(elementSender)[0].value.indexOf("Major ") > -1)
			{
				document.getElementsByName(elementName)[0].required = true;
				$(container).innerHTML = "(<font color=\"#c32200\"><b>REQUIRED NOW</b></font>)";
			}
			else
			{
				document.getElementsByName(elementName)[0].required = false;
				$(container).innerHTML = "(<font color=\"#228B22\"><b>only required for major auctions</b></font>)";
			}
		}
		/*function checkMajorAuction() 
		{
			var isMajor = jQuery('input[name=title]').val().indexOf('Major') != -1
			jQuery('input[name=logo_url]').attr('require',isMajor)
			jQuery('#requireShow').html(isMajor ? '(<b style="color:#c32200">REQUIRED NOW</b>)' : '(<b style="color:#c32200">only required for major auctions</b>)')
		}*/
		function $(id)
		{	return document.getElementById(id);	}
		
		setInterval("updatespdateend(document.forms[0].dateend.value)",100);
		</script>
		<form action="special_notes.php?a=edit&edit=<?=$_GET['edit'];?>&p=<?=$_GET['p'];?>" method="POST">
		<table>
		<?
		//$query = "SELECT * FROM special_notes WHERE dateid = '".$_GET['edit']."'";
		//$query = "SELECT * FROM special_notes LEFT JOIN `new_specialnotes_paragraph_map` ON (`dateid`=`sn_dateid`) LEFT JOIN `new_specialnotes_paragraph` USING (`snp_id`) LEFT JOIN `new_specialnotes_shiptype_map` ON (`dateid`=`sn_dateid`) LEFT JOIN `new_specialnotes_shiptype` USING (`snst_id`) WHERE dateid = '".$_GET['edit']."'";
		//$query = "SELECT * FROM special_notes LEFT JOIN `new_specialnotes_paragraph_map` ON (`dateid`=`sn_dateid`) LEFT JOIN `new_specialnotes_paragraph` USING (`snp_id`) LEFT JOIN `new_specialnotes_shiptype` USING (`snst_id`) WHERE dateid = '".$_GET['edit']."'";
		$query = "SELECT * FROM special_notes LEFT JOIN `new_specialnotes_paragraph_map` ON (`dateid`=`new_specialnotes_paragraph_map`.`sn_dateid`) LEFT JOIN `new_specialnotes_paragraph` USING (`snp_id`) LEFT JOIN `new_specialnotes_shiptype` USING (`snst_id`) LEFT JOIN new_specialnotes_bonusitems ON (`dateid`=`new_specialnotes_bonusitems`.`sn_dateid`) WHERE dateid = '".$_GET['edit']."'";
//		sn_dateid
		$result = mysql_query($query);
		//echo mysql_error();
		//echo mysql_error($result);
		if(!$result)
		{
			echo "There was an error on the page.<br /><br />Please go <a href='javascript:history.back()'>back</a> and try again.";
			die();
		}
		$row = mysql_fetch_array($result);
		echo "<tr><td>dateid: </td><td><a href='javascript:changeDate(-1)'>&lt;&lt;</a><input type='hidden' id='indateid' name='dateid' value='".$row['dateid']."' /><span id='spdateid'>".$row['dateid']."</span><a href='javascript:changeDate(1)'>&gt;&gt;</a></td></tr>";
		echo "<tr><td>Day: </td><td><select name='day' id='day_select' onchange=\"autoupdatetime(this.options[this.selectedIndex].value);\"><option value='0'".($row['day'] == "0" ? " selected='selected'" : "").">Sun</option><option value='2'".($row['day'] == "2" ? " selected='selected'" : "").">Tue</option><option value='4'".($row['day'] == "4" ? " selected='selected'" : "").">Thu</option></select></td></tr>";
		echo "<tr><td>Title: </td><td><input type='text' name='title' value='".str_replace("'","&apos;",$row['title'])."' size='80' maxlength='3000' onblur='checkMajorAuction(\"logo_url\",\"requireShow\",\"title\");' onkeyup='updateCharsLeft(\"title\",\"255\",\"charleft\");' /> <span id='charleft'>(".(255 - strlen($row['title']))." chars left)</span></td></tr>";
		echo "<tr><td>Short Title: </td><td><input type='text' name='short_title' value='".str_replace("'","&apos;",$row['short_title'])."' size='60' maxlength='100' onkeyup='updateCharsLeft(\"short_title\",\"50\",\"charleft2\");' /> <span id='charleft2'>(".(50 - strlen($row['short_title']))." chars left)</span></td></tr>";
		echo "<tr><td>Date End: </td><td><input type='hidden' name='dateend' id='dateend' value='".date("Y-m-d",strtotime($row['dateend']))."' onchange='javascript:updatespdateend(this.value)' /><span id='spdateend'>".date("n/j/Y",strtotime($row['dateend']))."</span> <a href='javascript:cal1.popup();'><img src='jscal/img/cal.gif' width='16' height='16' border='0' alt='Click Here to Pick a Date' title='Click Here to Pick a Date'></a></td></tr>";
		echo "<tr><td>Time End: </td><td><input type=\"text\" name=\"stime\" id=\"stime\" value=\"".date("H:i",$row['starttime'])."\"> HH:MM 24HR - <span style=\"font-weight:bold;\">15:00 for Sunday Auction! 19:00 for Tuesday & Thursday Auction!</span></td></tr>";
		echo "<tr><td>Lot Nums: </td><td><input type='text' name='lot_num' value='".$row['lot_num']."' size='2' /><br /></td></tr>";
		
		echo "<tr><td>Shipping: </td><td><select name=\"shippingparagraph\"><option value=\"0\"></option>";
		foreach($snp_array as $value){
			echo "<option value=\"".$value['snp_id']."\"".($row['snp_id']==$value['snp_id']?" selected=\"selected\"":"").">".$value['snp_title']."</option>";
		}
		echo "</select></td></tr>";
		
		echo "<tr><td>Combine Type: </td><td><select name=\"combinetype\"><option value=\"0\"></option>";
		foreach($snst_array as $value){
			echo "<option value=\"".$value['snst_id']."\"".($row['snst_id']==$value['snst_id']?" selected=\"selected\"":"").">".$value['snst_text']."</option>";
		}
		
		echo "<tr><td>Free Item: </td><td><input type=\"text\" name=\"free_item\" value=\"".htmlentities((isset($row['free_item'])?$row['free_item']:""))."\" style=\"width:20em;\"></td></tr>";
		
		echo "<tr><td>Free Item Link:</td><td><input type=\"text\" name=\"free_item_link\" value=\"".htmlentities((isset($row['snb_link'])?$row['snb_link']:""))."\" style=\"width:40em\"></td></tr>";
		
		echo "<tr><td>Logo URL:</td><td><input type='text' name='logo_url' value=\"".htmlspecialchars($row['logo_url'])."\" style='width: 100%' /><span id='requireShow'></span></td></tr>";
		
		echo "<tr><td colspan='2'>HTML Content:<br /><div id='previewdiv' style='display:none;width:320;'></div><textarea id='content' name='content' rows=20 cols=80>".$row['content']."</textarea></td></tr>";
		
		//echo "<tr><td colspan='2'>Global Content:<br /><div style='display:none;width:320;'></div><textarea id='global_content' name='global_content' rows=5 cols=80>".htmlentities($row['global_content'])."</textarea></td></tr>";
		
		echo "<tr><td colspan='2'>Auction Homepage:<br /><div style='display:none;width:320;'></div><textarea id='auction_homepage' name='auction_homepage' rows=5 cols=80>".htmlentities($row['auction_homepage'])."</textarea></td></tr>";
		
		echo "<tr><td colspan='2'><input type='button' id='editbtn' style='display:none;' value='Edit' onclick='showedit()' /><input type='button' id='prevbtn' value='Preview' onclick='showpreview()'>&nbsp;&nbsp;<input type='submit' value='Save Note' /> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<input type='button' value='Cancel' onclick='history.back();'/></td></tr>";
		?>
		</table>
		</form>
		<?
	}
}
else
{
	?>
	<a href="special_notes.php?a=new">Add New Note</a>
	<br /><br />
	<center>
	<?
	$pagenum = !empty($_GET['p']) ? intval($_GET['p']) : 0;
	
	$query = "SELECT count(*) FROM special_notes";
	$result = mysql_query($query);
	$row = mysql_fetch_row($result);
	$count = $row[0];
	$pages = ceil($count/$perpage);
	
	if(($pagenum+1) > $pages)
		$pagenum = 0;
	
	for($i=0;$i<$pages;$i++)
	{
		if($i != $pagenum)
		{
			echo " <a href='special_notes.php?p=$i'>".($i+1)."</a> ";
		}
		else
		{
			echo " <b>".($i+1)."</b> ";
		}
	}
	?>
	</center>
	<br /><br />
	<center>
	<table cellspacing="0" width="90%">
	<tr style='background:#E2E2E2;'><th width="160">Date</th><th>Lot Num</th><th>Title</th></tr>
	<?
	$color = 0;
	$query = "SELECT dateid,day,lot_num,title,starttime,endtime FROM special_notes ORDER BY dateid DESC LIMIT ".($pagenum*$perpage).",$perpage";
	$result = mysql_query($query);
	
	while($row = mysql_fetch_array($result))
	{
		echo "<tr";
		if($color == 0)
			$color = 1;
		else
		{
			$color = 0;
			echo " style='background:#CCDDFF;'";
		}
		echo "><td><span style='font-family:fixed'>".$row['dateid'];
		switch($row['day'])
		{
			case 0:
				echo "(<strong>Sun</strong>)";
				break;
			case 2:
				echo "(<strong>Tue</strong>)";
				break;
			case 4:
				echo "(<strong>Thu</strong>)";
				break;
			default:
				echo "(<strong>???</strong>)";
		}
		echo "</span></td><td style='text-align:center;'>" . $row['lot_num'] . "</td><td><a href='special_notes.php?a=edit&edit=".$row['dateid']."&p=$pagenum'>".$row['title']."</a>";
        if(empty($row['starttime']) || empty($row['endtime'])){
            echo "<div style=\"background-color:#FFCCCC;display:inline;font-weight:bold;float:right;padding:0 0.5em\">ERROR: Invalid starttime or endtime</div>";
        }
		/*
		if(empty($row['logo_url']) && in_array("major", $row('title')))
		{
			echo "<div style=\"background-color:#FFCCCC;display:inline;font-weight:bold;float:right;padding:0 0.5em\">ERROR: Major auctions require a logo</div>";
		}
		*/
		
        echo "</td></tr>";
	}
}
?>
</table></center>
<br /><br /><br />
<a href='javascript:leaveAlert();' style='color:#0000FF;text-decoration:none;font-size:10px;'>&lt;-- Back to Landing</a>
<script type='text/javascript'>
			var cal1 = new calendar3(document.forms[0].elements['dateend']);
			cal1.year_scroll = true;
			cal1.time_comp = false;
</script>
</body>
</html>