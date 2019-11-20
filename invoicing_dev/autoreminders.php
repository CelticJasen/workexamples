<?PHP
ini_set("display_errors", "on");
require_once("/webroot/auth/auth.php");

require_once("/scripts/includes/blab.inc.php");
require_once("/scripts/timeclock.inc.php");


mysqli_report(MYSQLI_REPORT_STRICT | MYSQLI_REPORT_ERROR);
$db = new mysqli("localhost", "root", "k8!05Er-05", "invoicing");
$wdb = new mysqli(WEB_MYSQL_HOST, WEB_MYSQL_USER, WEB_MYSQL_PASS);

if(!empty($_REQUEST['send']))
{
	$twoWeekDay = new DateTimeImmutable($_REQUEST['twoWeekDay']);
	
	$threeWeekDay = new DateTimeImmutable($_REQUEST['threeWeekDay']);
	
	//$twoWeekDebtors = new DebtorList($twoWeekDay, $db, $wdb);
	//$threeWeekDebtors = new DebtorList($twoWeekDay, $db, $wdb);
	
	$twoWeekEmails = $threeWeekEmails = array();
	
	foreach($_REQUEST['twoWeekDebtors'] as $email)
	{
		$twoWeekEmails[] = new ReminderEmail(1, new Debtor($email), $twoWeekDay, $threeWeekDay, $db);
	}
	
	foreach($_REQUEST['threeWeekDebtors'] as $email)
	{
		$threeWeekEmails[] = new ReminderEmail(2, new Debtor($email), $threeWeekDay, $threeWeekDay, $db);
	}
	
	foreach($twoWeekEmails as $email)
	{
		$email->previewSend();
		
		ReminderDatabase::logReminderEmail($db, $email);
	}
	
	foreach($threeWeekEmails as $email)
	{
		$email->previewSend();
		
		ReminderDatabase::logReminderEmail($db, $email);
	}
	
	echo json_encode2(array("ok" => 1));
	exit;
}
//require_once("/webroot/includes/record_locking_mysqli.inc.php");
//RecordLock::release_all("invoicing", $db, $user->getId());


$twoWeekReminderDay = ReminderDatabase::twoWeekReminderDay();
$threeWeekReminderDay = ReminderDatabase::threeWeekReminderDay();

$threeWeekDebtors = ReminderDatabase::getDebtors($threeWeekReminderDay, $db, $wdb);
$twoWeekDebtors = ReminderDatabase::getDebtors($twoWeekReminderDay, $db, $wdb);


function reminder_rows(DebtorList $list, DateTimeImmutable $day, DebtorList $exclude = null)
{
	$x = false;
	foreach($list as $debtor)
	{
		if($debtor->getsReminder($day))
		{
			if(!is_null($exclude) && $exclude->hasEmail($debtor->getEmail()))
				continue;
			
			yield "<tr class='debtor ".(($x = !$x) ? "odd " : "")."' data-email='".htmlspecialchars($debtor->getEmail())."'>".
				"<td><a target='_blank' href='/invoicing/#customer_id=".$debtor->getCustomerId()."'>".
				$debtor->getEmail()."<br />".$debtor->getCustomerId()."<br />".$debtor->getName()."</a></td>".
				"<td>$".$debtor->getTotal($day)." (".count($debtor->getItems($day)).")</td><td>".$debtor->getAllDatesFormatted()."</td>".
				"<td>".($debtor->getNotesForOfficeUseHtml())."</td>".
				"<td><label><input name='dontSend' type='checkbox' autocomplete='off' /> Don't send?</label></td>".
				"</tr>\r\n";
		}
		else
		{
			$quotes = $debtor->formatWaitingQuoteRequests($day);
			
			$notes = ($debtor->getNotesForOfficeUseHtml());
			
			if(!empty($quotes))
			{
				if(!empty($notes))
				{
					$notes .= "<br />";
				}
				
				$notes .= $quotes;
			}
			
			yield "<tr class='debtor hidden' data-email='".htmlspecialchars($debtor->getEmail())."'>".
				"<td><a target='_blank' href='/invoicing/#customer_id=".$debtor->getCustomerId()."'>".
				$debtor->getEmail()."<br />".$debtor->getCustomerId()."<br />".$debtor->getName()."</a></td>".
				"<td>$".$debtor->getTotal($day)." (".count($debtor->getItems($day)).")</td><td>".$debtor->getAllDatesFormatted()."</td>".
				"<td>".$notes."</td>".
				"<td><label><input name='dontSend' checked='checked' type='checkbox'  autocomplete='off' /> Don't send?</label>".
				($debtor->reminderSentDay($day) ? "<br /><b>Already sent</b>" : "").
				"</td>".
				"</tr>\r\n";
		}
	}
}

?>
<!DOCTYPE html>
<html>
<head>
	<title>Autoreminders</title>
	<link rel='stylesheet' href='/style/style.css' />
	<link rel='stylesheet' href='/includes/see_titles.css' />
	<link rel="stylesheet" href="/includes/jquery-ui-1.10.3/css/ui-lightness/jquery-ui-1.10.3.custom.css" />
	<link href='http://fonts.googleapis.com/css?family=Open+Sans:600,400|Raleway:400,600' rel='stylesheet' type='text/css'>
	<link rel="stylesheet" href="//code.jquery.com/ui/1.11.4/themes/smoothness/jquery-ui.css">
	<link rel='stylesheet' href='amenu.css' />
	
	<script src="//code.jquery.com/jquery-1.10.2.js"></script>
	<script src="//code.jquery.com/ui/1.11.4/jquery-ui.js"></script>
	<script type='text/javascript' src='/includes/jquery/jquery.typeahead.js'></script>
	<script type='text/javascript' src='/includes/jquery/bloodhound.js'></script>
	<script type='text/javascript' src='/includes/jquery/nextindom.jquery.js'></script>
	<script type='text/javascript' src='/includes/mustache.js'></script>
	<script type='text/javascript' src='/includes/wiki.js'></script> <!--TODO: Use this-->
	<script type='text/javascript' src='/includes/shortcut_keys.js'></script> <!--TODO: Document this-->
	<script type='text/javascript' src='/includes/jquery.ba-bbq.js'></script> <!--TODO: Document this-->
	<script type='text/javascript' src='/includes/jquery/readmore.js'></script>
	<script type='text/javascript' src='/includes/jquery/ajaxq.jquery.js'></script>
	<script type='text/javascript'>
	window.user_id = <?=$user->getId()?>;
	window.user_name = <?=json_encode($user->getName())?>;
	window.current_date = <?=json_encode(date("Y-m-d"))?>;
	
	function debtorTrMap(tr)
	{
		if(!$("input[name=dontSend]", tr).prop("checked"))
			return $(tr).data("email")
	}
	
	function send_emails()
	{
		var data = {
			send: 1,
			twoWeekDay: $("#twoWeek").data("date"),
			threeWeekDay: $("#threeWeek").data("date"),
			twoWeekDebtors: $("#twoWeek tr.debtor:not(.hidden)").map(function(){return debtorTrMap(this);}).toArray(),
			threeWeekDebtors: $("#threeWeek tr.debtor:not(.hidden)").map(function(){return debtorTrMap(this);}).toArray(),
		}
		
		var hidden = $("#twoWeek tr.debtor.hidden").length
		hidden += $("#threeWeek tr.debtor.hidden").length
		
		if(!confirm("You are about to TEST send "+(data.twoWeekDebtors.length+data.threeWeekDebtors.length)+" emails. "+
			"There are "+(hidden)+" customers excluded from reminders."))
			return false
		
		throb()
		
		$.ajax({
			url: "autoreminders.php",
			dataType: "json",
			data: data,
			success: function(data){
				if("error" in data)
					alert(data.error)
				
				if("ok" in data)
				{
					alert("Done.");
				}
			},
			complete: [unthrob],
			error: function(xhr, textStatus, errorThrown){
				alert("Error! "+textStatus)
			},
		})	
	}
	
	$(document)
		.ready(function(){
			
		})

	indicator = $("<img />")
		.attr("src", "/includes/graphics/throb.gif")
		.attr("id", "indicator")
		.css({
			
		})
	
	jQuery.fn.createValueArray = function() {
		var values = {};
		this.each(function(){
			switch($(this).attr("type"))
			{
				case "radio":
					if($(this).prop("checked"))
						values[$(this).attr("name")] = $(this).val();
					break;
				case "checkbox":
					values[$(this).attr("name")] = (($(this).prop("checked")) ? 1 : 0);
					break;
				default:
					values[$(this).attr("name")] = $(this).val();
					break;
			};
		});
		
		return values;
	}
	
	function throb()
	{
		$(document.body)
			.append(
				$(indicator)
					.css({
						position: "fixed",
						top: "50%",
						left: "50%",
						marginTop: "-100px",
						marginLeft: "-100px",
						zIndex: 1000,
					})
			)
	}
	
	function unthrob()
	{
		$("#indicator").remove()
	}
	
	function toggle_hidden()
	{
		if($("table.reminders tr.hidden").hasClass("shown"))
		{
			$("table.reminders tr.hidden")
				.each(function(){$(this).removeClass("shown")})
		}
		else
		{
			$("table.reminders tr.hidden")
				.each(function(){$(this).addClass("shown")})
		}
	}
	
	</script>
	<style type='text/css'>
		table.reminders tr.hidden
		{
			display: none;
		}
		
		
		
		table.reminders
		{
			font-size: 9pt;
		}
		
		table.reminders caption
		{
			font-size: 14pt;
			padding: 10px;
		}
		
		table.reminders tbody tr
		{
			background: white;
		}
		
		table.reminders tbody tr.odd
		{
			background: lightgray;
		}
		
		table.reminders tr.shown
		{
			display: table-row;
		}
		
		
		
		body
		{			
			background: #b6b9d2;
			background-image: url('graphics/bg.png');
			font-size: 14px;
			font-family: Verdana, Geneva, Arial, Helvetica, sans-serif;
		}

		.roundbox
		{
			margin: 3px 5px 13px 5px;
			border-radius: 10px;
			border: 1px solid #888;
			
		}

		.roundbox, 
		ul:not(.ui-autocomplete):not(#tabs):not(#main_menu) li:nth-child(2n+1),
		#tabbar, 
		#lognavigation,
		table.items
		{
			background: rgba(255,255,255,0.7)
		}

		table.items td
		{
			padding: 0px;
		}

		#tabs li.focused
		{
			background: rgba(0, 0, 128, 0.15)
		}

		table.items td, table.items td *
		{
			color: gray;
		}

		li.hidden
		{
			display: none;
		}

		li.selected
		{
			background: #fff7e1 !important;
		}

		table.items tr.selected td
		{
			background: #fff7e1;
		}

		table.items tr.selected td, table.items tr.selected td *
		{
			color: black;
		}

		table.items, table.paypal
		{
			border-collapse: collapse;
		}

		.margins
		{
			margin: 13px 5px 13px 5px;
		}

		h2
		{
			margin: 0px 5px -8px 5px;
			text-align: center;
			font-size: 13pt;
		}

		h3
		{
			font-size: 12pt;
			text-align: center;
			margin: 0px;
		}

		label
		{
			font-size: 8pt;
		}

		textarea
		{
			background: transparent;
			font-size: 14px;
			border-style: none;
			font-family: Verdana, Geneva, Arial, Helvetica, sans-serif;
		}

		.round
		{
			border-radius: 10px;
		}


		.inblock
		{
			display: inline-block;
			vertical-align: top;
		}

		.hoverbutton
		{
			border: 1px solid transparent;
			cursor: pointer;
		}

		.hoverbutton:hover
		{
			border: 1px solid black;
		}

		input:focus, textarea:focus, select:focus, div.card:focus, div.items.tr:focus, li.invoice:focus {
			outline: 1px dotted #222;
		}
		
		.ui-dialog
		{
			z-index: 101;
		}
		
		a:link
		{
			text-decoration: none;
			color: black;
		}
		
		ul
		{
			list-style: none;
			margin: 0;
			padding: 0;
			vertical-align: bottom;
			overflow: auto;
		}
		
		ul li
		{
			cursor: default;
			padding: 3px 3px 3px 5px;
			position: relative;
			white-space: nowrap;
		}		
		
		#tabs li
		{
			display: inline-block;
			padding: 2px 5px 2px 5px;
			font-variant: small-caps;
		}
		
		#tabs li.focused
		{
			border-radius: 8px 8px 0px 0px;
		}
		
		span.twitter-typeahead
		{
			vertical-align: top;
		}
		
		a:link, a:visited
		{
			color: black;
		}
		
		a:hover
		{
			text-decoration: underline;
		}
		
		
		div.alert
		{
			position: relative;
			height: 14px;
			background: #eeee77;
			font-size: 8pt;
			padding: 0px;
			margin-bottom: 1px;
			white-space: nowrap;
			text-overflow: ellipsis;
		}
		
		#status_bar
		{
			display: inline-block;
			font-size: 8pt;
			padding-left: 50px;
			padding-bottom: 3px;
			/*
			position: fixed;
			bottom: 0px;
			left: 0px;
			width: 100%;
			background: #eee;
			height: 16px;
			border-top: 1px solid #999;
			*/
		}
		
		#status_bar *
		{
			vertical-align: middle;
		}

		.ui-menu-item
		{
			font-size: 9pt;
		}
		
		
		::-moz-placeholder
		{
			color: #888;
		}
		
		.ui-tooltip
		{
			background: lightyellow;
		}


		textarea, input
		{
			border: 1px solid #7f9db9;
			background: white;
		}
		
		ul.listmenu li
		{
			background: transparent !important
		}

		#main
		{
			padding: 10px;
		}
		
		.ui-menu{
			width: 300px;
			font-size: 10pt;
		}
		
		table.reminders tr.hidden
		{
			background: rgb(240,240,240);
		}
		
		table.reminders tr.hidden, table.reminders tr.hidden a
		{
			color: rgb(150,150,150);
		}
			
	</style>
</head>
<body>
<?PHP
include ("/webroot/auth/status.php");
?>
<div class='noprint' id='lognavigation'>
	<div>Related:</div>
	<ul class="listmenu">
		<li><a target='_blank' id='bidder_account_admin' href="/website_tools/user_account_admin.php">Bidder Account Admin</a></li>
		<li><a target='_blank' href='/invoicing/'>Invoicing System</a></li>
		<li><a target='_blank' id='quote_request_printout' href="https://www.emovieposter.com/secure/test_tools/quote_printout.php">Quote Request Printout Tool</a></li>
		<li><a target='_blank' id='shipping_quotes' href="/shipping_quotes/">Shipping Quotes</a></li>
		<li><a target='_blank' id='print_invoices' href="http://poster-server/invoicing/print_invoices.php">Print Invoices</a></li>
		<li><a target='_blank' id='mo_sales_tax' href="sales_tax_report.php">MO Sales Tax Calculator</a></li>
		<li><a target="_blank" id='whos_here' href="http://poster-server/tools/who_is_here.php">Who's Here</a></li>
	</ul>
</div>


<div id='root'>
	<div id='main'>
	
	<button onclick='toggle_hidden()'>Toggle Hidden</button>
	<button onclick='send_emails()'>Send Emails</button>
	
	<table id='twoWeek' data-date="<?=$twoWeekReminderDay->format("Y-m-d")?>" class='reminders' cellspacing='0' cellpadding='5' style='table-layout: fixed'>
		<thead>
			<caption>1<sup>st</sup> Reminders (2 weeks; <?=$twoWeekReminderDay->format("m/d/Y")?>)</caption>
			<tr><th style='width: 300px'>Customer</th><th style='width: 100px'>Totals (total dollars/items)</th>
			<th style='width: 300px'>Date(s)</th>
			<th style='width: 200px'>Notes</th><th style='width: 100px'>Don't send?</th></tr>
		</thead>
		
	<tbody>
	<?
	$rows = reminder_rows($twoWeekDebtors, $twoWeekReminderDay, $threeWeekDebtors);
	
	foreach($rows as $row)
	{
		echo $row;flush();
	}
	
	?>
	</tbody>
	</table>
	
	
	<table id='threeWeek' data-date="<?=$threeWeekReminderDay->format("Y-m-d")?>" class='reminders' cellspacing='0' cellpadding='5' style='table-layout: fixed'>
		<thead>
			<caption>2<sup>nd</sup> Reminders (3 weeks; <?=$threeWeekReminderDay->format("m/d/Y")?>)</caption>
			<tr><th style='width: 300px'>Customer</th><th style='width: 100px'>Totals (total dollars/items)</th>
			<th style='width: 300px'>Date(s)</th>
			<th style='width: 200px'>NotesForOfficeUse</th><th style='width: 100px'>Don't send?</th></tr>
		</thead>
		
	<tbody>
	<?
	$rows = reminder_rows($threeWeekDebtors, $threeWeekReminderDay);
	
	foreach($rows as $row)
	{
		echo $row;flush();
	}
	
	?>
	</tbody>
	</table>
	
	</div>
</div>

</body>
</html>