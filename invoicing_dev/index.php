<?PHP

function get_bins_date()
{
	if(in_array(date("N"), array(2, 4, 6))) //Mon, Wed, Fri
		return date("Y-m-d", strtotime("Tomorrow"));
	else
		return date("Y-m-d");
}

session_start();

if(
	!isset($_REQUEST['username']) && //Is not currently logging in
	(empty($_SESSION['invoicing_timestamp']) || $_SESSION['invoicing_timestamp'] < strtotime("60 minutes ago")) && //Has not used the invoicing system in the last 30 minutes
	(empty($_SESSION['ccproc_timestamp']) || $_SESSION['ccproc_timestamp'] < strtotime("60 minutes ago")) //Has not used the cc processor in the last 30 minutes
)
{
	$force_logout = true;
	
	$message = "For security, please log in again.";
}

//require_once("../auth.inc.php");
require_once("/webroot/auth/auth.php");

$_SESSION['invoicing_timestamp'] = time();


require_once("/webroot/includes/string.inc.php");
require_once("/scripts/includes/blab.inc.php");
require_once("/scripts/timeclock.inc.php");
require_once("backend/invoicing.inc.php");

Auth::require_permissions(PERM_ACCESS_INVOICING, $user);

mysqli_report(MYSQLI_REPORT_STRICT | MYSQLI_REPORT_ERROR);
$db = new mysqli("localhost", "root", "k8!05Er-05", "invoicing");
$db->set_charset("utf8");
require_once("/webroot/includes/record_locking_mysqli.inc.php");
//RecordLock::release_all("invoicing", $db, $user->getId());

//Needed the old way because of the login status strip
mysql_connect("poster-server", "root", "k8!05Er-05");
mysql_select_db("listing_system");

/*
TODO:

Integrate with Wiki
*/
?>
<!DOCTYPE html>
<html>
<head>
	<title>Invoicing</title>
	<link rel='stylesheet' href='card_20160629.css' />
	<link rel='stylesheet' href='templates/css/check_table_20151204.css' />
	<link rel='stylesheet' href='typeahead_20160302.css' />
	
	<link rel='stylesheet' href='/style/style_20150813.css' />
	<link rel='stylesheet' href='style20150811.css' />
	<link rel="stylesheet" href="/includes/jquery-ui-1.10.3/css/ui-lightness/jquery-ui-1.10.3.custom.css" />
	<link rel="stylesheet" media='print' href='print.css' />
	<link href='http://fonts.googleapis.com/css?family=Open+Sans:600,400|Raleway:400,600' rel='stylesheet' type='text/css'>
	<style>
		.ui-widget-content
		{
			border: 1px solid gray;
		}

	</style>
	
	<script type='text/javascript' src='/includes/jquery-ui-1.10.3/js/jquery-1.9.1.js'></script>
	<script type='text/javascript' src='/includes/jquery-ui-1.10.3/js/jquery-ui-1.10.3.custom.aaron.js'></script>
	<!--<script type='text/javascript' src='/includes/jquery-ui-1.10.3/js/jquery-ui-position.js'></script>-->
	<!--<script type='text/javascript' src='/includes/jquery/jquery.typeahead.0.11.1.js'></script>-->
	<script type='text/javascript' src='/includes/jquery/typeahead.bundle.js'></script>
	
	<script type='text/javascript' src='/includes/jquery/nextindom.jquery.js'></script>
	<!--<script type='text/javascript' src="https://maps.googleapis.com/maps/api/js?v=3.exp"></script> //We used to use Google geocoding for address parsing but it didn't work right-->
	<script type='text/javascript' src='functions_20170811.js'></script>
	<script type='text/javascript' src='functions_more_20170802.js'></script>
	<script type='text/javascript' src='invoicing_search_20160531.js'></script>
	<script type='text/javascript' src='/includes/mustache.js'></script>
	<script type='text/javascript' src='/includes/wiki.js'></script> <!--TODO: Use this-->
	<script type='text/javascript' src='/includes/shortcut_keys.js'></script> <!--TODO: Document this-->
	<script type='text/javascript' src='/includes/jquery.ba-bbq.js'></script> <!--TODO: Document this-->
	<script type='text/javascript' src='/includes/jquery/readmore.js'></script>
	<script type='text/javascript' src='/includes/jquery/ajaxq.jquery.js'></script>
	<script type="text/javascript" src="/includes/PrinterSettings.js"></script>
	<script type='text/javascript' src='/includes/tickets/tickets_20170227.js'></script>
	<script type='text/javascript' src='/js/clientsideUpdateCheck.js'></script>
	<script type="text/javascript">
	$(window).load(function(){
	      setPrinterSettings({
	        'marginTop' : 12.7,
	        'marginBottom' : 12.7,
	        'marginLeft' : 12.7,
	        'marginRight' : 12.7,
	        'headerStrLeft' : '',
	        'headerStrCenter' : '',
	        'headerStrRight' : '',
	        'footerStrLeft' : '',
	        'footerStrCenter' : '',
	        'footerStrRight' : '',
	        'scaling' : 100,
	        'shrinkToFit' : 0,
	        'printBGColors' : 1,
	        'printBGImages' : 1
	      });
	})
	</script>
	
	<script type='text/javascript'>
	<?php
	require_once("templates.js.php");
	?>
	window.user_id = <?=$user->getId()?>;
	window.user_name = <?=json_encode($user->getName())?>;
	window.current_date = <?=json_encode(date("Y-m-d"))?>;
	window.new_item_date = <?=json_encode(get_bins_date())?>;
	window.ebay_item_number_options = <?=json_encode(array_values(array_filter(array_map("trim", array_map("String::filter_comment", file("ebay_item_number_options.txt", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES))), "strlen")))?>;
	window.search_history = <?php
	$invoicing = new Invoicing($db, null);
	echo json_encode($invoicing->history_get($user->getId()));
?>;
	window.search_history2 = <?php
	$invoicing = new Invoicing($db, null);
	echo json_encode($invoicing->history_get());
?>;
	commission_rates = <?php
	require_once("/webroot/accounting/backend/commission_rates.js.php");
?>;
	
	
	$(document)
		.ready(function(){
				<?php
				if(!empty($_GET['customer_id']) && !empty($_GET['tab']))
				{
					echo '$.ajaxq.abort("fetch");';
					echo "window.location = \"/invoicing/#customer_id=".urlencode($_GET['customer_id'])."&tab=".urlencode($_GET['tab'])."\"; ";
					//echo "search(".json_encode($_GET['customer_id']).', function(){$(\'#'.$_GET['tab'].'\').click()});';
				}
				elseif(!empty($_GET['customer_id']))
				{
					echo '$.ajaxq.abort("fetch");';
					echo "window.location = \"/invoicing/#customer_id=".urlencode($_GET['customer_id'])."\"; ";
					//echo "search(".json_encode($_GET['customer_id']).");";
				}
				?>
		})

	panes = {
		"home" : {
			"show": function(){
				show_home_pane()
			}
		},
		"customer": {
			"show": function(){
				var customer_id = $("#customer_id").html()
				
				if(!customer_id)
				{
					status("<img src='/includes/graphics/alert16.png' /> You haven't selected a customer yet.")
					return false;
				}
				
				show_customer_pane()
			}
		},
		"items": {
			"show": function(){
				var customer_id = $("#customer_id").html()
				
				if(!customer_id)
				{
					status("<img src='/includes/graphics/alert16.png' /> You haven't selected a customer yet.")
					return false;
				}
					
				show_items_pane()
			}
		},
		"packages": {
			"show": function(){
				var customer_id = $("#customer_id").html()
				
				if(!customer_id)
				{
					status("<img src='/includes/graphics/alert16.png' /> You haven't selected a customer yet.")
					return false;
				}
				
				show_packages_pane()
			}
		},
		"history" : {
			"show" : function(){
				var customer_id = $("#customer_id").html()
				
				if(!customer_id)
				{
					status("<img src='/includes/graphics/alert16.png' /> You haven't selected a customer yet.")
					return false;
				}
				
				show_history_pane()
			}
		},
		"consignor" : {
			"show" : function(){
				var customer_id = $("#customer_id").html()
				
				if(!customer_id)
				{
					status("<img src='/includes/graphics/alert16.png' /> You haven't selected a customer yet.")
					return false;
				}
				
				show_consignor_pane()
			}
		},
		"email" : {
			"show" : function(){
				var customer_id = $("#customer_id").html()
				
				if(!customer_id)
				{
					status("<img src='/includes/graphics/alert16.png' /> You haven't selected a customer yet.")
					return false;
				}
				
				show_emails_pane()
			}
		},
		"account" : {
			"show" : function(){
				var customer_id = $("#customer_id").html()
				
				if(!customer_id)
				{
					status("<img src='/includes/graphics/alert16.png' /> You haven't selected a customer yet.")
					return false;
				}
				
				show_account_pane()
			}
		},
		"checks" : {
			"show" : function(){
				var customer_id = $("#customer_id").html()
				
				if(!customer_id)
				{
					status("<img src='/includes/graphics/alert16.png' /> You haven't selected a customer yet.")
					return false;
				}
				
				show_checks_pane()
			}
		},
	}
	</script>
	
	<script type="text/javascript">
	$(document).ready(function()
	{
        $("#email_search_form").submit(
          function(event){
            show_emails_pane();
            event.preventDefault();
            }
          )
	});
	</script>
	
	<link rel='stylesheet' href='aTabs_20150911.css' />
	<link rel='stylesheet' href='invoicing_20161024.css' />
</head>
<body style='position: relative'>
<?PHP
//include ("/webroot/includes/navigation.inc.php");
//include ("/webroot/includes/subnav.inc.php");
//include ("/webroot/includes/status.html");
Auth::status_bar($user);
?>
<div class='noprint' id='lognavigation'>
	<div>Related:</div>
	<ul class="listmenu">
		<li><a target='_blank' id='bidder_account_admin' href="/website_tools/user_account_admin.php">Bidder Account Admin</a></li>
		<li><a target='_blank' id='auction_anything' style='display: none' href="http://auctions.emovieposter.com/AdminMemberProfile.taf?_function=detail">Auction Anything Profile</a></li>
		<li><a target='_blank' id='quote_request_printout' href="https://www.emovieposter.com/secure/test_tools/quote_printout.php">Quote Request Printout Tool</a></li>
		<li><a target='_blank' id='shipping_quotes' href="/shipping_quotes/">Shipping Quotes</a></li>
		<li><a target='_blank' id='print_invoices' href="/invoicing/print_invoices.php">Print Invoices</a></li>
		<li><a target='_blank' id='mo_sales_tax' href="/tools/salestax.php">MO Sales Tax Calculator</a></li>
		<li><a target="_blank" id='whos_here' href="/tools/who_is_here.php">Who's Here</a></li>
		<li><a target="_blank" href="/accounting/import_paypal_history.php">Import PayPal History</a></li>
		<li><a target="_blank" href="/invoicing/declines.php">Credit Card Declines</a></li>
		<li><a target="_blank" href="/invoicing/reminder_tool.php">Reminders</a></li>
	</ul>
</div>
<!--<div style='padding: 10px' id='searchbar'>

	<div id='status'>
	</div>
</div>-->

<div class='noprint' id='tabbar'>
	<img src='/includes/graphics/menu16.png' class='hoverbutton' onclick='open_main_menu()' title='Menu' id='menu_button'
		style='vertical-align: middle; margin: 0 0 0 5px; visibility: hidden' tabindex='0' />
	
	<img class='hoverbutton'  onclick='window.open("/wiki/index.php/Invoicing_System")' 
		title='Help with Invoicing System' src='/includes/graphics/help.png' style='vertical-align: middle; margin: 0 0 0 1px' />
	
	<img src='/includes/graphics/add_user.png' class='hoverbutton' id='new_customer' 
		onclick='window.new_customer_pastebox()' onkeyup='if(event.keyCode == 13) window.new_customer_pastebox()' title='New Customer' 
		style='vertical-align: middle; margin: 0 0 0 1px' tabindex='0' />

	<img src='/includes/graphics/search16.png' class='hoverbutton' 
		onclick='advanced_search()' onkeyup='if(event.keyCode == 13) advanced_search()' style='vertical-align: middle; margin: 0 0 0 1px' title="Advanced Search" tabindex='0' />

	<input id='search' class="typeahead" style='' type="text" placeholder="Customer Search" tabindex='0' />
	
	<a href='javascript:void(0)' onclick='help2("Searching for a customer")' style='font-weight: bold; color: tomato' title='Help with searching'>?</a>
	
	<a id='customer_id' target='_blank' style='display: inline-block; width: 75px; font-size: 9pt; text-align: center;'></a>
	
	<span id='icons' style='display: inline-block; width: 116px; font-size: 9pt; text-align: center; vertical-align: middle;'>
		<img src='/includes/graphics/refresh16.png' class='hoverbutton' id='refresh' title='Refresh' style='visibility: hidden' />
		<img src='/includes/graphics/clip16.png' class='hoverbutton' id='clip' title='Copy Address to Clipboard' style='visibility: hidden' />
		<img src='/includes/graphics/quote.png' class='hoverbutton' id='quote_request' title='Request a Quote' style='visibility: hidden' />
		
		<img src='/includes/graphics/mail24.png' class='hoverbutton' id='mailto' title='Compose Email' style='visibility: hidden' />
		
	</span>
	
	<ul id='tabs' style='display: inline;'>
		<li id='home_tab' tab='home' class='focused'><a>Home</a></li>
		<li id='customer_tab' tab='customer' style='visibility: hidden' ><a>Customer</a></li>
		<li id='items_tab' tab='items' style='visibility: hidden'><a>Items</a></li>
		<li id='packages_tab' tab='packages' style='visibility: hidden'><a>Packages</a></li>
		<li id='history_tab' tab='history' style='visibility: hidden'><a>History</a></li>
		<li id='consignor_tab' tab='consignor' style='visibility: hidden'><a>Consignor</a></li>
		<li id='email_tab' tab='email' style='visibility: hidden'><a>Email</a></li>
		<!--<li id='account_tab' tab='account' style='visibility: hidden'><a>Accounting</a></li>-->
		<li id='checks_tab' tab='checks' style='visibility: hidden'><a>Checks</a></li>
	</ul>
	
	<span id='status_bar'></span>
</div>



<div id='root'>
	<div id='main'>
	</div>
</div>

<script type='x-tmpl-mustache' id='other_address'><?=file_get_contents("templates/html/other_address.html")?></script>
<script type='x-tmpl-mustache' id='billing_address'><?=file_get_contents("templates/html/billing_address.html")?></script>
<script type='x-tmpl-mustache' id='package_row'><?=file_get_contents("templates/html/package_row.html")?></script>
<script type='x-tmpl-mustache' id='item_row'><?=file_get_contents("templates/html/item_row.html")?></script>
<script type='x-tmpl-mustache' id='item_row2'><?=file_get_contents("templates/html/item_row2.html")?></script>
<script type='x-tmpl-mustache' id='mail_row'><?=file_get_contents("templates/html/mail_row.html")?></script>
<script type='x-tmpl-mustache' id='block_record'><?=file_get_contents("templates/html/block_record.html")?></script>
<script type='x-tmpl-mustache' id='stats'><?=file_get_contents("templates/html/stats.html")?></script>
<script type='x-tmpl-mustache' id='invoice_row'><?=file_get_contents("templates/html/invoice_row.html")?></script>
<script type='x-tmpl-mustache' id='credit_card'><?=file_get_contents("templates/html/credit_card.html")?></script>
<script type='x-tmpl-mustache' id='credit_card_new'><?=file_get_contents("templates/html/credit_card_new.html")?></script>
<script type='x-tmpl-mustache' id='consignor_box'><?=file_get_contents("templates/html/consignor.html")?></script>
<script type='x-tmpl-mustache' id='expert_box'><?=file_get_contents("templates/html/expert.html")?></script>
<script type='x-tmpl-mustache' id='similar_names'><?=file_get_contents("templates/html/similar_names.html")?></script>
<script type='x-tmpl-mustache' id='shared_accounts'><?=file_get_contents("templates/html/shared_accounts.html")?></script>
<script type='x-tmpl-mustache' id='phone_orders_prompt'><?=file_get_contents("templates/html/phone_orders_prompt.html")?></script>
<script type='x-tmpl-mustache' id='printout'><?=file_get_contents("templates/html/printout.html")?></script>
<script type='x-tmpl-mustache' id='warnings'><?=file_get_contents("templates/html/warnings.html")?></script>
<script type='x-tmpl-mustache' id='unprinted_invoices'><?=file_get_contents("templates/html/unprinted_invoices.html")?></script>
<script type='x-tmpl-mustache' id='ph_due_dates'><?=file_get_contents("templates/html/ph_due_dates.html")?></script>
<script type='x-tmpl-mustache' id='printout_history'><?=file_get_contents("templates/html/printout_history.html")?></script>
<script type='x-tmpl-mustache' id='website_orders'><?=file_get_contents("templates/html/website_orders.html")?></script>
<script type='x-tmpl-mustache' id='consignor_row'><?=file_get_contents("templates/html/consignor_row.html")?></script>
<script type='x-tmpl-mustache' id='consignor_new'><?=file_get_contents("templates/html/consignor_new.html")?></script>
<script type='x-tmpl-mustache' id='untracked_invoice_row'><?=file_get_contents("templates/html/untracked_invoice_row.html")?></script>
<script type='x-tmpl-mustache' id='child_invoice_row'><?=file_get_contents("templates/html/child_invoice_row.html")?></script>
<script type='x-tmpl-mustache' id='other_addresses'><?=file_get_contents("templates/html/other_addresses.html")?></script>
<script type='x-tmpl-mustache' id='autoships'><?=file_get_contents("templates/html/autoships.html")?></script>
<script type='x-tmpl-mustache' id='credit_row'><?=file_get_contents("templates/html/credit_row.html")?></script>
<script type='x-tmpl-mustache' id='credit_card_charges'><?=file_get_contents("templates/html/credit_card_charges.html")?></script>
<script type='x-tmpl-mustache' id='invoice_info'><?=file_get_contents("templates/html/invoice_info.html")?></script>
<script type='x-tmpl-mustache' id='quick_info'><?=file_get_contents("templates/html/quick_info.html")?></script>
<script type='x-tmpl-mustache' id='mail_to'><?=file_get_contents("templates/html/mail_to.html")?></script>
<script type='x-tmpl-mustache' id='unprocessed_order'><?=file_get_contents("templates/html/unprocessed_order.html")?></script>
<script type='x-tmpl-mustache' id='check_rows'><?=file_get_contents("templates/html/check_rows.html")?></script>
<script type='x-tmpl-mustache' id='check_table'><?=file_get_contents("templates/html/check_table.html")?></script>
<script type='x-tmpl-mustache' id='debtors'><?=file_get_contents("templates/html/debtors.html")?></script>
<script type='x-tmpl-mustache' id='invoice_new'><div class='roundbox invoice newinvoice' tabindex='10' data-invoice_number='{{invoice_number}}'>
	<div>
		<input pattern='[0-9]{6,}' name='invoice_number' value='{{invoice_number}}' style='width: 60px' />
		<img src='/includes/graphics/printer.png' title='Print Order' class='print' />
		<img src='/includes/graphics/eye.png' title='View Order' class='view' />
		
		<input type='datetime' disabled='disabled' name='date_of_invoice' value='{{date_of_invoice}}' class='date' />
	</div>
	
	<div style='display: inline-block; width: 270px; vertical-align: top'>
		<div>
			<select name='shipping_method' value='{{shipping_method}}' style='margin-right: 10px; width: 181px;'>
				<option selected='selected' value="">shipping</option>
<?php
$r = mysql_query3("select * from invoicing.shipping_methods order by `order`");
while($row = mysql_fetch_assoc($r))
{
	echo "\t\t\t<option>".htmlspecialchars($row['shipping_methods'])."</option>\n";
}
?>
			</select>
			$<input id='shipping_charged' name='shipping_charged' value='{{shipping_charged}}' placeholder='0' style='width: 50px' />
		</div>
		
		
		<select name='payment_method' value='{{payment_method}}' placeholder='payment' style='width: 220px'>
			<option selected='selected' value="">payment</option>
<?php
$r = mysql_query3("select * from invoicing.payment_methods where payment_methods != 'Consignment Proceeds' order by `order`");
while($row = mysql_fetch_assoc($r))
{
	echo "\t\t\t<option>".htmlspecialchars($row['payment_methods'])."</option>\n";
}
?>
		</select>
	</div>
	
	<div style='display: inline-block; width: 120px; vertical-align: top;'>
		<select size='3' name='cc_which_one' style='width: 120px'></select>
	</div>
	
	<div id='subtotal' style='margin: 5px 0 5px 0;'>$0.00</div>
	
	<textarea name='extra_info' placeholder='notes' style='height: 36px; margin-bottom: 5px; width: 397px; display: block;'>{{extra_info}}</textarea>
	<textarea name='shipping_notes' placeholder='shipping note (for Pay & Hold)' style='height: 16px; margin-bottom: 5px; width: 397px; display: block;'></textarea>
	<!--<textarea name='alternate_address' placeholder='shipping to alternate address' style='height: 16px; margin-bottom: 5px; width: 397px; display: block;'></textarea>-->
	
	
	<div style='font-size: 8pt; text-align: center; width: 100%; margin: 0; border-bottom: 1px dashed gray;'>Ship To</div>
	<ul class='ship_to_address'>
		
	</ul>
	
	<!--<div style='font-size: 8pt; text-align: center; width: 100%; margin: 0; border-bottom: 1px dashed gray;'>Bill To</div>
	<ul class='bill_to_address'>
		
	</ul>-->
	<div id='invoice_warning'></div>
</div></script>

<script type='x-tmpl-mustache' id='editable_invoice'><div class='roundbox invoice newinvoice editinvoice' tabindex='10' data-invoice_number='{{invoice_number}}'>
	<div>
		<input pattern='[0-9]{6,}' disabled='disabled' name='invoice_number' value='{{invoice_number}}' style='width: 60px' />
		
		<input type='datetime' disabled='disabled' name='date_of_invoice' value='{{date_of_invoice}}' class='date' />
	</div>
	
	<div style='display: inline-block; width: 270px; vertical-align: top'>
		<div>
			<select name='shipping_method' style='margin-right: 10px; width: 181px;'>
<?php
$r = mysql_query3("select * from invoicing.shipping_methods order by `order`");
while($row = mysql_fetch_assoc($r))
{
	echo "\t\t\t<option>".htmlspecialchars($row['shipping_methods'])."</option>\n";
}
?>
			<option selected='selected'>{{shipping_method}}</option>
			</select>
			$<input id='shipping_charged' name='shipping_charged' value='{{shipping_charged}}' placeholder='0' style='width: 50px' />
		</div>
		
		
		<select name='payment_method' placeholder='payment'>
<?php
$r = mysql_query3("select * from invoicing.payment_methods order by `order`");
while($row = mysql_fetch_assoc($r))
{
	echo "\t\t\t<option>".htmlspecialchars($row['payment_methods'])."</option>\n";
}
?>
		<option selected='selected'>{{payment_method}}</option>
		</select>
	</div>
	
	<div style='display: inline-block; width: 120px; vertical-align: top;'>
		<select size='3' name='cc_which_one' style='width: 120px'></select>
	</div>
	
	<div id='subtotal' style='margin: 5px 0 5px 0;'>$0.00</div>
	
	<textarea name='extra_info' placeholder='notes' style='height: 36px; margin-bottom: 5px; width: 397px; display: block;'>{{extra_info}}</textarea>
	<textarea name='shipping_notes' placeholder='shipping note (for Pay & Hold)' style='height: 16px; margin-bottom: 5px; width: 397px; display: block;'>{{shipping_notes}}</textarea>
	
	<button onclick='$("#edit_invoice_addresses").show(); $(this).remove()'>Change Address</button>
	
	<div id='edit_invoice_addresses' style='display: none; width: 100%;'>
		<div style='font-size: 8pt; text-align: center; width: 100%; margin: 0; border-bottom: 1px dashed gray;'>Ship To</div>
		<ul class='ship_to_address'>
			
		</ul>
		
		<!--<div style='font-size: 8pt; text-align: center; width: 100%; margin: 0; border-bottom: 1px dashed gray;'>Bill To</div>
		<ul class='bill_to_address'>
			
		</ul>-->
	</div>
	
	<div id='invoice_warning'></div>
</div></script>



<script type='x-tmpl-mustache' id='item_new'><div class='roundbox' style='width: 1370px; padding: 1px 5px 1px 5px;' id='new_item'>
	<input name='date' placeholder='date' style='width: 130px' value='<?=get_bins_date()?>' />
	<input name='ebay_item_number' placeholder='item #' style='width: 100px' />
	<input name='shipping_notes' placeholder='shipping_notes' style='width: 360px' />
	<input name='ebay_title' placeholder='item title' style='width: 445px' />
	<input name='price' placeholder='0.00' style='width: 80px' />
	<label><input id='fixed_price_sale' name='fixed_price_sale' type='checkbox' /> Fixed Price Sale</label>
	<input id='new_ebay_id' name='ebay_id' placeholder='website/amazon' style='width: 80px; display: none' />
	<a href='javascript:void(0)' onclick='help2("New item")' style='color: red; font-weight: bold'>?</a>
</div>
</script>

<script type='x-tmpl-mustache' id='paypal_payment_search'><div style="text-align: left; width: 1000px; box-shadow: 0px 15px 20px grey; margin-bottom: 15px; margin-right: auto; margin-left: auto;">
	<div style="padding: 4px 4px 4px 4px; border-radius: 13px 13px 0 0; border: 1px solid black; background: rgba(255,255,255,0.7);">
		<small>search </small>
		<input tabindex='0' style="width: 300px" />
		<span style='margin-left: 80px; letter-spacing: 2px'>Incoming PayPal Payments </span><span>(for outgoing payments, look up customer)
	</div>
	<div style="text-align: left; height: 300px; overflow: auto; background: rgba(255,255,255,0.8);">
		<table cellspacing="0" cellpadding="0" border="0" style="font-size: 10pt; table-layout: fixed; width: 980px;" id='home_paypal_table'>
			<colgroup>
			<col style="width: 150px"><col style="width: 230px">
			<col style="width: 272px"><col style="width: 53px">
			<col style="width: 16px"><col style="width: 16px">
			<col style="width: 153px"><col style="width: 16px">
			</colgroup>
			<tr><th>When</th><th>Name</th><th>Email</th><th>Amt</th><th>St</th><th>Typ</th><th>Txn id</th><th>M</th></tr>
	</div>
</div>

</script>

<script type='x-tmpl-mustache' id='packages_header'><div style='font-size: 8pt; margin-left: 11px; font-variant: small-caps;'>
	<div style='display: inline-block; width: 38px'>Pkg</div>
	<div style='display: inline-block; width: 35px'>Inv</div>
	<div style='display: inline-block; width: 70px; margin-left: 10px;'>Date</div>
	<div style='display: inline-block; width: 35px; margin-left: 5px;'>Pmt</div>
	<div style='display: inline-block; width: 80px; margin-left: 10px;'>When</div>
	<div style='display: inline-block; width: 70px; margin-left: 10px;'>Method</div>
	<div style='display: inline-block; width: 14px'>R</div>
	<div style='display: inline-block; width: 35px; margin-left: 5px;'>Ins</div>
	<div style='display: inline-block; width: 80px; margin-left: 10px;'>Original</div>
	<div style='display: inline-block; width: 80px; margin-left: 10px;'>Declared</div>
</div>
</script>

<script type='x-tmpl-mustache' id='admin'><div style='width: 400px; height: 400px' id='admin_settings_box'>
<table cellpadding='0' cellspacing='0' style='width: 100%'>
	<tr>
		<th></th>
		<td></td>
	</tr>
	<tr>
		<th></th>
		<td></td>
	</tr>
	<tr>
		<th><label for='log_in_as'>Log In As Customer</label></th>
		<td><button id='log_in_as' onclick='log_in_as_customer()'>Log In</button></td>
	</tr>
	<tr>
		<th><label for='forgot'>Send Username and Password</label></th>
		<td><button id='forgot' onclick='forgot_password_email()'>Send</button></td>
	</tr>
</table>
<div id='admin_settings_status' style='text-align: center; font-size: 10pt;'>&nbsp;</div>
</div>
</script>

<script type='x-tmpl-mustache' id='login'><form method="post" name="login" action="https://www.emovieposter.com/members/checkout.php">
	<input type="text" id="username" name="emovieposter_username" value='{{username}}' />
	<input type="password" name="emovieposter_pass" id="pass" value='{{password}}' />
	<button type="submit">Login</button>
</form>
</script>

<script type='x-tmpl-mustache' id='combine_types'><option value="">(none)</option><?php
$r = $db->query("select snst_id, snst_text from listing_system.new_specialnotes_shiptype where hide = '0' order by snst_text");

while(list($snst_id, $snst_text) = $r->fetch_row())
{
	echo "<option value='$snst_id'>$snst_text</option>\r\n";
}

?>
</script>

<script type='x-tmpl-mustache' id='charge_card_dialog'><div>
<table cellpadding='7'>
	<tr><th></th><td class='card_cell'></td></tr>
	<tr>
		<th>Type</th>
		<td>
			<label style='font-size: 10pt'><input type='radio' name='type' checked='checked' value='credit' /> Add Credit</label><br />
			<label style='font-size: 10pt'><input type='radio' name='type' value='custom' /> No Credit <small>(careful)</small></label>
		</td>
	</tr>
	<tr><th>Title</th><td><input type='text' value="{{title}}" required='required' name='title' /></td></tr>
	<tr><th>Amount</th><td><input type='text' required='required' pattern='[0-9]+(\.[0-9]{2})?' name='amount' placeholder='$' /></td></tr>
	<!--<tr><th>Invoice</th><td><input type='text' name='invoice' placeholder='#' pattern='[0-9]+' /></td></tr>-->
</table>
<input type='hidden' name='cc_id' value='{{cc_id}}' />
</div>
</script>

<script type='x-tmpl-mustache' id='refund_dialog'><div>
<table cellpadding='7'>
	<tr><th></th><td class='card_cell'></td></tr>
	<tr><th>Memo</th><td><input type='text' name='memo' /></td></tr>
	<tr><th>Amount</th><td><input type='text' required='required' pattern='[0-9]+(\.[0-9]{2})?' name='amount' placeholder='$' /></td></tr>
	<tr><th>Invoice</th><td><input type='text' name='invoice_number' placeholder='#' pattern='[0-9]+' /></td></tr>
</table>
<input type='hidden' name='cc_id' value='{{cc_id}}' />
</div>
</script>

<iframe id='iframe' style='height: 0px; width: 100%; border-style: none'></iframe>

<ul id='main_menu' style='display: none'>
	<li onclick='change_customer_email()'><img src='/includes/graphics/email16.png' /> Change Email To&hellip;</li>
	<li onclick='change_customer_id()'><img src='/includes/graphics/rename.png' /> Change customer_id&hellip;</li>
	<li onclick='merge_customer()'><img src='/includes/graphics/merge16.png' /> Merge With email&hellip;</li>
	<li onclick='merge_customer2()'><img src='/includes/graphics/merge16.png' /> Merge With customer_id&hellip;</li>
	<li onclick='delete_customer()'><img src='/includes/graphics/close.png' /> Delete Customer&hellip;</li>
	<li id='customer_dump'><img src='/includes/graphics/page.png' title='Download customer history (XLS)' /> Download Customer History</li>
	<li onclick='customer_ticket_onclick()'>
		<img src='/includes/graphics/ticket_new.png' /> Open New Ticket
	</li>
	<?
	if(in_array($user->getId(), array(1, 3, 7, 12, 22, 18))) //Phil, Bruce, Aaron, Steven, Matt, Angie
	{
	?>
	<li><hr noshade='noshade' /></li>
	<li onclick='extend_flat_rate_shipping()'><img src='/includes/graphics/truck16.png' /> Extend Flat Rate Shipping</li>
	<li onclick='cancel_quote_request()'><img src='/includes/graphics/cancel_file.png' /> Cancel Quote Request</li>
	<li onclick='forgot_password_email()'><img src='/includes/graphics/password16.png' /> Send Forgot Password Email</li>
	<li onclick='log_in_as_customer()'><img src='/includes/graphics/login16.png' /> Log In As Customer</li>
	<li onclick='change_consignments_items_email()'><img src='/includes/graphics/merge16.png' /> Move Consignments Records&hellip;</li>
	<li onclick='user_action_log()'><img src='/includes/graphics/history.png' /> View Customer Activity</li>
	<li><label for='frequent_buyer'><input type='checkbox' id='frequent_buyer' name='frequent_buyer' /> Frequent Buyer Program </label></li>
	<li><label for='vip'><input type='checkbox' id='vip' name='vip' /> VIP</label></li>
	<?
	}
	elseif(in_array($user->getId(), array(18, 81, 92, 118))) //Angie, Terri, Ellicia, Lana
	{
	?>
	<li><hr noshade='noshade' /></li>
	<li onclick='extend_flat_rate_shipping()'><img src='/includes/graphics/truck16.png' /> Extend Flat Rate Shipping</li>
	<li onclick='cancel_quote_request()'><img src='/includes/graphics/cancel_file.png' /> Cancel Quote Request</li>
	<?
	}
	
	
	
	?>
	<li><label><input id='tax_exempt' type='checkbox' name='tax_exempt' /> MO Tax Exempt</label></li>
</ul>


<div class='noprint history_bar' id='history_bar'></div>

<div class='noprint history_bar' id='history_bar2'></div>

</body>
</html>
