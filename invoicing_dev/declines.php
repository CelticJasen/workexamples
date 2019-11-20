<?PHP
session_start();

require_once("/webroot/auth/auth.php");

require_once("/scripts/includes/blab.inc.php");
require_once("/scripts/timeclock.inc.php");

mysqli_report(MYSQLI_REPORT_STRICT | MYSQLI_REPORT_ERROR);
$db = new mysqli("localhost", "root", "k8!05Er-05", "invoicing");
$db->set_charset("utf8");

//require_once("/webroot/includes/record_locking_mysqli.inc.php");
//RecordLock::release_all("invoicing", $db, $user->getId());

if(!empty($_FILES['file']))
{	
	$file = new SplFileObject($_FILES['file']['tmp_name']);
}

?>
<html>
<head>
	<title>Credit Card Declines</title>
	<link rel='stylesheet' href='/style/style.css' />
	<link rel="stylesheet" href="/includes/jquery-ui-1.10.3/css/ui-lightness/jquery-ui-1.10.3.custom.css" />
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
	window.templates = {
		declines: <?=json_encode2(file_get_contents("/webroot/credit_card_processor/templates/declines.html"))?>,
		declines_print: <?=json_encode2(file_get_contents("/webroot/credit_card_processor/templates/declines_print.html"))?>
	}
	window.user_id = <?=$user->getId()?>;
	window.user_name = <?=json_encode($user->getName())?>;
	window.current_date = <?=json_encode(date("Y-m-d"))?>;
	
	$(document)
		.ready(function(){
			declines()
		})

	indicator = $("<img />")
		.attr("src", "/includes/graphics/throb.gif")
		.attr("id", "indicator")
		.css({
			
		})
	
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
	
	function update_cc_notes(input)
	{
		throb()
		
		$.ajax({
			url: "/credit_card_processor/reports.php",
			data: {
				action: "cc_notes",
				cc_notes: $(input).val(),
				invoice_number: $(input).data("invoice_number")
			},
			type: "post",
			dataType: "json",
			success: [ajax_result_handler, function(data){
				$(input).val(data.cc_notes).css({background: "rgb(240,255,240)"})
			}],
			complete: unthrob,
			error: [ajax_error_handler],
		})
	}


	function declines()
	{
		throb()
		
		$.ajax({
			url: "/credit_card_processor/reports.php",
			data: {action: "declines"},
			type: "post",
			dataType: "json",
			success: [ajax_result_handler, function(data){
				if("declines" in data)
				{
					show_declines(data.declines)
				}
			}],
			complete: unthrob,
			error: [ajax_error_handler],
		})
	}


	function ajax_error_handler(jqXHR, textStatus)
	{
		if(textStatus != "abort")
			alert("Error! Error! Abort! Abort!")
	}


	function show_declines(declines)
	{
		$("#main")
			.append(Mustache.render(window.templates.declines, declines))
		
		$("#main")
			.append(
				$("<button />")
					.text("Print")
					.button()
					.css({
						display: "block",
						marginTop: "10px",
						marginRight: "auto",
						marginLeft: "auto",
					})
					.click(function(){
						w = window.open()
					
						w.document.open()
						
						w.document.write(Mustache.render(window.templates.declines_print, declines))
						
						w.document.close()
						
						w.print()
						
						w.close()
					})
			)
		
		$("table.declines input")
			.keyup(function(event){
				$(this).css({
					background: "rgb(255,255,230)",
				})
				
				if(event.keyCode == 13)
					update_cc_notes(this)
			})
			.change(function(){
				$(this).css({
					background: "rgb(255,255,230)",
				})
				
				update_cc_notes(this)
			})
	}
	
	function ajax_result_handler(data)
	{
		if("status" in data)
		{
			//status("<img src='/includes/graphics/alert16.png' /> "+data.status)
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
	</style>
</head>
<body>
<?PHP

Auth::status_bar($user);
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
	//$wiki = unserialize(file_get_contents("http://localhost/wiki/api.php?action=parse&format=php&page=Import%20PayPal%20History"));
	
	//echo $wiki['parse']['text']['*'];
	?>
</div>

</body>
</html>