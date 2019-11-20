<?php
require_once("includes.inc.php");

if(!empty($_POST['query']))
{
	try
	{
		$result = lookup_tracking_numbers($_POST['query'], $db);
		
	}
	catch(exception $e)
	{
		unset($result);
		if($e->getCode() == "10040")
			$error = $e->getMessage();
		else
			throw $e;
	}
}
elseif(!empty($_POST['action']))
{
	if($_POST['action'] == "update_only" || $_POST['action'] == "update_with_email")
	{
		$db->query("update tbl_tracking_numbers set tracking_num = '".$db->escape_string($_POST['tracking_number'])."' ".
			"where ID = '$_POST[tracking_number_id]'");
	}
	
	if($_POST['action'] == "update_with_email")
	{
		Mailer::mail($_POST['email'], "eMoviePoster.com has shipped your order!", 
			str_replace("<tracking>", $_POST['tracking_number'], $tracking_email), 
			array("from" => "eMoviePoster.com <mail@emovieposter.com>"));
			
		Mailer::mail("mail@emovieposter.com", "eMoviePoster.com has shipped your order!", 
			str_replace("<tracking>", $_POST['tracking_number'], $tracking_email), 
			array("from" => "eMoviePoster.com <mail@emovieposter.com>"));
			
		die("Sent");
	}
	
	die("Updated");
}

?>
<html>
<head>
<title>Registered Tracking Numbers</title>
<script type='text/javascript' src='/includes/jquery/jquery.js'></script>
<script type='text/javascript'>
$(document).click(function(event){
	if($(event.target).parents("div.package:not(.selected)").length)
	{
		$("div.package").removeClass("selected")
		$(event.target).parents("div.package:not(.selected)").addClass("selected")
	}
})

function submit(update_with_email)
{
	$("#status").empty()
	$("#status").append("<img src='/includes/graphics/indicator.gif' />")
	
	$.ajax({
		type: "post",
		data: "action="+(update_with_email ? "update_with_email" : "update_only")+"&tracking_number_id="+encodeURIComponent($("div.package.selected").attr("tracking_number_id"))+
			"&tracking_number="+encodeURIComponent($("#tracking_number").val())+"&email="+encodeURIComponent($("div.package.selected").attr("customer_email")),
		success: function(data){
			if(data == "Updated" || data == "Sent")
			{
				$("#status").html("<b>"+data+"</b>")
				
				$("#query").focus()
				
				$("#submit, #submit2").hide()
			}
			else
				alert("There was an error.\n\n"+data)
		}
	})
}
</script>
<style type="text/css">
textarea
{
	width: 300px;
	height: 100px;
}

input
{
	width: 260px;
}

div.package
{	
	margin: 10px 10px 10px 10px;
	border: 1px solid black;
	background: rgb(230,230,220);
	font-size: 11pt;
	display: inline-block;
}

div.package.selected
{
	border: 1px solid yellow;
	background: rgb(255,255,230);
}

div.package:hover
{
	border: 1px solid yellow;
}

</style>
</head>
<body>
<?
echo "<p>$error</p>";
?>
<small>Invoice # or Package ID</small><br />
<form method='post' style='margin: 0 0 0 0'>
	<input type="text" id='query' name='query' /><button>Go</button>
</form>
<?
function fuzzytime2($time)
{
	$start = new DateTime(date("Y-m-d"));
	$end = new DateTime(date("Y-m-d", strtotime($time)));
	
	$diff = $end->diff($start);
	
	return $diff->format("%d");
}

function days_ago($days_ago)
{
	if($days_ago == 0)
		return "today";
	elseif($days_ago == 1)
		return "yesterday";
	elseif($days_ago < 8)
	{
		return "last ".date("l", strtotime("Today -$days_ago days"));
	}
	else
		return "$days_ago days ago";
}

if(!empty($result))
{
	$result = array_reverse($result);
	foreach($result as $r)
	{
		if(empty($first))
		{
			echo "<div class='package selected' tracking_number_id='$r[ID]' customer_email='".htmlspecialchars($r['email'])."'><table>";
			$first = true;
		}
		else
			echo "<div class='package' tracking_number_id='$r[ID]' customer_email='".htmlspecialchars($r['email'])."'><table>";
		
		echo "<tr style='text-align: center'><td colspan='2'>$r[tracking_num]</td></tr><tr>";
		
		
		echo "<td style='padding-right: 10px'>";
		echo "$r[invoice_num]<br />";
		
		if(!empty($r['package_id']))
			echo "p$r[package_id]<br />";
		else
			echo "<br />";
		
		echo "$r[ship_type]<br />";
		
		echo "Sent ".days_ago(fuzzytime2($r['ship_date']));
		
		echo "</td><td>$r[customer_id]<br />$r[name]<br />$r[email]</td></tr></table></div>";
	}
	
	?>
	<div>
	<small>Tracking Number For Selected Package</small><br />
	<input type='text' id='tracking_number' name='tracking_number' />
	</div>
	<div>
		<button onclick='submit(false)' style='display: none' id='submit'>Update</button>
		<!--<button onclick='submit(true)' style='display: none' id='submit2'>Update &amp; Send Email</button>-->
	<p id='status'></p>
	</div>
	<script type="text/javascript">
	$("#tracking_number").select()
		.keyup(function(){
			if($(this).val() == "")
				$("#submit, #submit2").hide()
			else
				$("#submit, #submit2").show()	
		})
	</script>
	<?
}
else
{
	?>
	<script type="text/javascript">
	$("#query").select()
	</script>
	<?
}
?>
</body>
</html>