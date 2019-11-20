<?php
require_once("includes.inc.php");
Auth::require_permissions(PERM_ACCESS_INVOICING, $user);

if(empty($_POST['action']))
{
	$_POST['invoice_num'] = $_POST['tracking_num'] = $_POST['ship_to'] = $_POST['insurance_purchased'] = $_POST['declared_value'] = "";
	$_POST['ship_date'] = "Today";
	$_POST['ship_type'] = "Fedex";
}

function blank_to_null($val)
{
	return ($val == "") ? NULL : $val;
}

$result_ids = array();

if(!empty($_POST['result_ids']))
{
	$result_ids = explode(",", $_POST['result_ids']);
}

if(!empty($_POST['action']))
{	
	if($_POST['action'] == "select")
	{
		$r = $db->query("select * from invoicing.tbl_tracking_numbers where ID = '$_POST[id]'");
		
		$result = array();
		
		while($row = $r->fetch_assoc())
			$result['package'] = $row;
		
		die(json_encode($result));
	}
	
	if($_POST['action'] == "delete")
	{
		$db->query("delete from invoicing.tbl_tracking_numbers where ID='$_POST[id]'");
	}
	
	if($_POST['action'] == "insert")
	{
		try
		{
			$_POST = array_map("trim", $_POST);
			
			if(empty($_POST['invoice_num']))
				throw new exception("Invoice number was blank.", 10000);
				
			//if(empty($_POST['tracking_num']))
				//throw new exception("Tracking number was blank.", 10000);
				
			//if(empty($_POST['ship_to']))
				//throw new exception("Customer Name was blank.", 10000);
				
			if(empty($_POST['ship_type']))
				throw new exception("Ship Type was blank.", 10000);
				
			if("2013-12-15 00:00:00" > $date = date("Y-m-d H:i:s", strtotime($_POST['ship_date'])))
				throw new exception("Ship date incorrect ('$date')", 10000);
				
			if(!empty($_POST['insurance_purchased']) && !is_numeric($_POST['insurance_purchased']))
				throw new exception("Invalid entry for Insurance.", 10000);
				
			if(!empty($_POST['declared_value']) && !is_numeric($_POST['declared_value']))
				throw new exception("Invalid entry for Declared Value.", 10000);
		
			$data = array_map("blank_to_null", array(
				"invoice_num" => $_POST['invoice_num'],
				"tracking_num" => $_POST['tracking_num'],
				"ship_date" => date("Y-m-d H:i:s", strtotime($_POST['ship_date'])),
				"ship_type" => $_POST['ship_type'],
				"insurance_purchased" => $_POST['insurance_purchased'],
				"declared_value" => $_POST['declared_value'],
				"package_id" => $_POST['package_id'],
				"pull_sheet_id" => $_POST['pull_sheet_id'],
			));
			
			$data['ship_to'] = $_POST['ship_to'];
		
			//Mailer::mail("aaron@emovieposter.com", "Check Tracking Number Insert/Update", print_r($data, true));
		
			if(empty($_POST['ID']))
			{
				$db->query(assemble_insert_query3($data, "invoicing.tbl_tracking_numbers", $db));
				
				unset($_POST['invoice_num'], $_POST['tracking_num'], $_POST['ship_date'],
					$_POST['ship_type'], $_POST['ship_to'], $_POST['insurance_purchased'],
					$_POST['declared_value']);
			
			
				$result_ids[] = $db->insert_id;
			}
			else
			{
				$query = "update invoicing.tbl_tracking_numbers set ";
				foreach($data as $k => $v)
				{
					$query .= "`".$db->escape_string($k)."` = ";
					
					if(is_null($v))
						$query .= "null, ";
					else
						$query .= "'".$db->escape_string($v)."', ";
				}
				$query = substr($query, 0, -2)." where ID = '$_POST[ID]'";
				
				$db->query($query);
			}
		}
		catch(Exception $e)
		{
			if($e->getCode() == 10000)
			{
				$error = $e->getMessage();
			}
			elseif($db->errno == 1062)
			{
				$error = "There's already a record there. ".$e->getMessage();
			}
			else
			{
				email_error($e->__toString());
				$error = "Something bad happened. ".$e->__toString();
			}
		}
	}
	else
	{
		
	}
}

if(!empty($_POST['query']))
{
	try
	{		
		$result = lookup_tracking_numbers($_POST['query'], $db);
		
		$result_ids = array();
		foreach($result as $r)
			$result_ids[] = $r["ID"];
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
elseif(!empty($result_ids))
{
	foreach($result_ids as $id)
	{
		$result[] = fetch_tracking_number($id, $db);
	}
}

?>
<html>
<head>
<title>Tracking Numbers</title>
<script type='text/javascript' src='/includes/jquery/jquery.js'></script>
<script type='text/javascript'>

$(document)
	.ready(function(){
		if($("div.package").length)
		{
			select($("div.package:first").attr("tracking_number_id"))
		}
	})
	.click(function(event){
		if($(event.target).parents("div.package:not(.selected)").length)
		{
			$("div.package").removeClass("selected")
			$(event.target).parents("div.package:not(.selected)").addClass("selected")
		}
		
		if($(event.target).parents("div.package").length)
		{
			select($(event.target).parents("div.package").attr("tracking_number_id"))
		}
	})

function select(tracking_number_id)
{
	$.ajax({
		type: "post",
		async: false,
		data: "action=select&id="+tracking_number_id,
		dataType: "json",
		success: function(data){
			
			for(x in data.package)
			{
				$(":input.package").filter(function(){
					return $(this).attr("name") == x
				}).val(data.package[x])
			}
		}
	})
}

function del(tracking_id)
{
	if(!confirm("Delete this package?"))
		return false;
	
	form = $("<form />")
		.attr("method", "post")
		.append(
			$("<input />")
				.attr("type", "hidden")
				.attr("name", "action")
				.val("delete")
		)
		.append(
			$("<input />")
				.attr("type", "hidden")
				.attr("name", "id")
				.val(tracking_id)
		)
		.appendTo(document.body)
		
	form.submit()
}

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
<link rel='stylesheet' href='/style/style.css' />
<style type="text/css">
textarea
{
	width: 300px;
	height: 100px;
}


div.package
{	
	margin: 10px 10px 10px 10px;
	border: 1px solid black;
	background: rgb(230,230,220);
	font-size: 11pt;
	display: inline-block;
	position: relative;
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


label
{
	font-size: 9pt;
	margin-right: 5px;
}

.clicky:hover
{
	cursor: pointer;
	border: 1px solid red;
}

.clicky
{
	border: 1px solid transparent;
}

</style>
</head>
<body>
<div id='lognavigation'>
<div>Related:</div>
<ul class='listmenu'>
<li><a href='http://poster-server/address_lookup.php'>Address Lookup</a></li>
<li><a href='http://poster-server/shipping_quotes/'>Quotes Form</a></li>
</ul>
</div>
<?

Auth::status_bar($user);

if(!empty($error))
	echo "<p style='color: tomato'>ERROR: $error</p>";
?>
<div style='padding: 5px;'>
<div>
	<div style='display: inline-block; text-align: top; vertical-align: top; margin-right: 30px;'>
		<small>Search For Package</small><br />
		<form method='post' style='margin: 0 0 0 0' style='display: inline'>
			<table>
				<tr><td><label>Invoice # or Package ID</label></td></tr>
				<tr><td><input type="text" id='query' name='query' size='15' /><button>Go</button></td></tr>
			</table>
		</form>
	</div>

	<div style='display: inline-block; text-align: top; vertical-align: top'>
		<small>Add/Edit</small><br />
		<form method='post' style='margin: 0 0 0 0' style='display: inline' onkeydown='if(event.keyCode == 13) return false'>
			<input type="hidden" class='package' name="ID" value="" />
			<input type="hidden" name='result_ids' value="<?=implode(",", $result_ids)?>" />
			<input type="hidden" name="action" value="insert" />
			<table>
				<tr><td><label>Invoice/Package/Sheet #</label></td><td><label>Tracking #</label></td>
					<td><label>Ship Date</label></td><td><label>Ship Type</label></td>
					<td><label>Customer Name</label></td><td><label>Insurance</label></td>
					<td><label>Declared Value</label></td><td><label>Package</label></td>
					<td><label>Pull Sheet</label></td>
					<td><label for='hand_delivered' />Hand<br />Deliver</label></td>
				</tr>
				
				<tr><td><input type="text" class='package' name='invoice_num' size='15' value="<?=$_POST['invoice_num']?>" /></td>
					<td><input type="text" class='package' name='tracking_num' size='30' value="<?=$_POST['tracking_num']?>" /></td>
					<td><input type="text" class='package' name='ship_date' size='15' value="<?=$_POST['ship_date']?>" /></td>
					<td><input type="text" class='package' name='ship_type' size='15' value="<?=$_POST['ship_type']?>" /></td>
					<td><input type="text" class='package' name='ship_to' size='20' value="<?=$_POST['ship_to']?>" /></td>
					<td><input type="text" class='package' name='insurance_purchased' size='10' value="<?=$_POST['insurance_purchased']?>" /></td>
					<td><input type="text" class='package' name='declared_value' size='10' value="<?=$_POST['declared_value']?>" /></td>
					<td><input type="text" class='package' name='package_id' size='8' value="<?=$_POST['package_id']?>" /></td>
					<td><input type="text" class='package' name='pull_sheet_id' size='8' value="<?=$_POST['pull_sheet_id']?>" /></td>
					<td><input id='hand_delivered' type='checkbox' onclick='if(this.checked){$("[name=ship_type]").attr("readonly", true).val("Hand Delivered")}else{$("[name=ship_type]").removeAttr("readonly").val("")}' /></td>
					<td>
						<button onclick='$(":input.package").val(""); $("[name=ship_date]").val("Today"); $("[name=ship_type]").val("Fedex"); return false;'>Clear</button>
						<button>Go</button>
					</td>
				</tr>
			</table>		
		</form>
	</div>
</div>


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
		
		echo "</td><td>$r[customer_id]<br />$r[name]<br />$r[email]</td></tr></table>";
		
		echo "<span class='clicky' onclick='del($r[ID]);' ".
			"style='position: absolute; right: 0px; top: 0px; color: red; font-weight: bold;'>X</span>";
		
		echo "</div>";
	}
	
	?>
	
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
</div>
</body>
</html>