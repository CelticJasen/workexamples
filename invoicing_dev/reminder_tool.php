<?PHP

session_start();

require_once("/webroot/auth/auth.php");

require_once("/scripts/includes/blab.inc.php");
require_once("/scripts/timeclock.inc.php");

mysqli_report(MYSQLI_REPORT_STRICT | MYSQLI_REPORT_ERROR);
$db = new mysqli("localhost", "root", "k8!05Er-05", "invoicing");
$db->set_charset("utf8");

?>
<!DOCTYPE html>
<html>
<head>
	<title>Reminders</title>
	<link rel='stylesheet' href='/style/style.css' />
	<link rel='stylesheet' href='/includes/see_titles.css' />
	<link rel="stylesheet" href="/includes/jquery-ui-1.10.3/css/ui-lightness/jquery-ui-1.10.3.custom.css" />
	<link href='http://fonts.googleapis.com/css?family=Open+Sans:600,400|Raleway:400,600' rel='stylesheet' type='text/css'>
	<link rel="stylesheet" href="//code.jquery.com/ui/1.11.4/themes/smoothness/jquery-ui.css">
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
	<script type='text/javascript' src='/includes/dropzone.js'></script>
	<script type='text/javascript'>	
	<?php
	require_once("templates.js.php");
	?>
	window.user_id = <?=$user->getId()?>;
	window.user_name = <?=json_encode($user->getName())?>;
	window.current_date = <?=json_encode(date("Y-m-d"))?>;

	window.user_id = <?=$user->getId()?>;
	window.user_name = <?=json_encode($user->getName())?>;
	window.current_date = <?=json_encode(date("Y-m-d"))?>;
	
	window.reminder_templates = <?=json_encode(scandir2("templates/email/reminders"))?>;
	
	$(document)
		.ready(function(){
			
			$("#datepicker")
				.datepicker({
					dateFormat: "yy-mm-dd",
					onSelect: function(date, datepicker){
						load_reminders(date)
					}
				})
				
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
	
	function load_reminders(date)
	{
		throb()
		
		$("#reminders").empty()
		
		$.ajaxq("fetch", {
			url: "reminders.php",
			type: "post",
			data: {
				user_id: window.user_id,
				user_name: window.user_name,
				date: date
			},
			dataType: "json",
			success: [ajax_result_handler, function(data){
				var div = $("<div />")
					.addClass("orders")
				
				data.select = ""
				for(x in window.reminder_templates)
				{
					data.select += "<option value=\""+window.reminder_templates[x]+"\">"+
						window.reminder_templates[x].replace(".html", "").replace(".txt", "")+"</option>\n"
				}
				
				$(Mustache.render(window.templates.reminders, data))
					.change(function(event){
						console.debug(event)
					})
					.appendTo(div)
				
				
				
				$(div)
					.appendTo("#reminders")
					
				$("<button id='create_drafts' />")
					.text("Create Drafts")
					.click(function(){
						$(this).prop("disabled", true)
						send()
					})
					.appendTo(div)
					.button()
					
				button = $("<button />")
					.text("Help")
					.click(function(){
						help2("Reminders")
					})
					.appendTo(div)
					.button()
			}],
			complete: unthrob,
			//error: [empty_main, ajax_error_handler],
		})
	}
	
	
	function email(customer_id, email, date)
	{
		throb()
		
		$.ajaxq("fetch", {
			url: "reminders.php",
			type: "post",
			data: {
				user_id: window.user_id,
				user_name: window.user_name,
				date: date,
				customer_id: customer_id,
				email: email,
				template: 1,
			},
			dataType: "json",
			success: [ajax_result_handler, function(data){
				if("mailto" in data)
				{
					window.location = data.mailto
				}
			}],
			complete: unthrob,
			//error: [empty_main, ajax_error_handler],
		})
	}
	
	
	function send()
	{
		var send = []
		var blocks = []
		
		$("#reminders select.email_template")
			.each(function(){
				if($(this).val())
				{
					if($(this).val() == "Block List")
					{
						blocks.push({
							"customer_id" : $(this).data("customer_id"), 
							"email": $(this).data("email"),
							"auction_date": $(this).data("auction_date")
						})
					}
					else
					{
						send.push([$(this).data("customer_id"), $(this).data("email"), $(this).data("auction_date"), $(this).val()])
					}
				}
			})
		
		throb()
		
		$.ajaxq("fetch", {
			url: "reminders.php",
			type: "post",
			data: {
				user_id: window.user_id,
				user_name: window.user_name,
				send: send,
				blocks: blocks,
			},
			dataType: "json",
			success: [ajax_result_handler, function(data){
				
			}],
			complete: [unthrob, function(){
				$("#create_drafts").prop("disabled", false)
			}],
			//error: [empty_main, ajax_error_handler],
		})
	}
	
	
	function ajax_result_handler(data)
	{
		if("logout" in data)
		{
			window.location = "/taskmgr"
		}
		
		if("status" in data)
		{
			status("<img src='/includes/graphics/alert16.png' /> "+data.status)
		}
		
		if("error" in data)
		{
			alert(data.error)
			return false
		}
	}
	
	
	</script>
	<style type='text/css'>
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
		
				
		.tt-dropdown-menu {
		  text-align: left;
		}

		.typeahead {
		  background-color: #fff;
		}

		.typeahead:focus {
		  border: 2px solid #0097cf;
		}

		.tt-query {
			box-shadow: inset 0 1px 1px rgba(0, 0, 0, 0.075);
		}


		.tt-hint {
			color: #999
		}

		.tt-dropdown-menu {
			width: 800px;
			margin-top: 6px;
			padding: 8px 0;
			background-color: #fff;
			border: 1px solid #ccc;
			border: 1px solid rgba(0, 0, 0, 0.2);
			border-radius: 8px;
			box-shadow: 0 5px 10px rgba(0,0,0,.2);
		}

		.tt-suggestion {
			padding: 3px;
			font-size: 10pt;
			cursor: default;
		}

		.tt-suggestion.tt-cursor {
			color: #fff;
			background-color: #0097cf;
		}

		.tt-suggestion p {
			margin: 0;
		}
		
		div.orders
		{
			background: rgba(255, 255, 255, 0.3);
			border-radius: 5px;
			/*width: 1000px;
			margin-left: auto;
			margin-right: auto;*/
			margin-top: 20px;
			padding: 10px;
		}

		

		div.orders table
		{
			width: 1000px;
			background: rgba(255, 255, 255, 0.3);
			margin: 5px;
			padding: 4px;
			border-radius: 2px;
			border-collapse: collapse;
		}
		
		div.orders table th
		{
			text-align: center;
		}
		
		div.orders table td.spacer
		{
			border-left: 1px solid gray;
			border-right: 1px solid gray;
			padding: 1px;
		}

		div.orders table tr td
		{
			font-size: 9pt;
			padding-right: 5px;
			padding-left: 5px;
			border-top: 1px solid lightgray;
			padding: 2px 5px 2px 5px;
			overflow: hidden; 
			text-overflow: ellipsis;
		}
		
		div.orders table caption
		{
			margin-bottom: 20px;
		}
		
		div.orders table pre
		{
			font-family: inherit;
			margin-top: 0;
		}
		
		div.orders table tr:hover
		{
			background: rgb(255,255,150);
		}

		div.orders th
		{
			font-size: 9pt;
			text-align: left;
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
	<?		
	/*$wiki = unserialize(file_get_contents("http://localhost/wiki/api.php?action=parse&format=php&page=Import%20PayPal%20History"));
	
	echo $wiki['parse']['text']['*'];*/
	?>
	<div id='datepicker'></div>
	

		
	<?
	echo "<div  id='reminders'>";
  
		
	echo "</div>";
	?>
	</div>
</div>

</body>
</html>