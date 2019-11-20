<?PHP
session_start();

$template = <<<A
<tr>
	<td><a target='_blank' href='/invoicing/?customer_id={{customer_id}}'>{{customer_id}}</a></td>
	<td>{{email}}</td>
	<td>{{name}}</td>
A;

$text_template = <<<A
{{customer_id}}	{{email}}	{{name}}
A;

require_once("/webroot/auth/auth.php");

require_once("/scripts/includes/blab.inc.php");
require_once("/scripts/timeclock.inc.php");
require_once("advanced_search.inc.php");

mysqli_report(MYSQLI_REPORT_STRICT | MYSQLI_REPORT_ERROR);
$db = new mysqli("localhost", "root", "k8!05Er-05", "invoicing");
$db->set_charset("iso-8859-1");

//require_once("/webroot/includes/record_locking_mysqli.inc.php");
//RecordLock::release_all("invoicing", $db, $user->getId());

Auth::require_permissions(PERM_ACCESS_INVOICING, $user);

?>
<html>
<head>
	<title>Advanced Customer Search</title>
	<link rel='stylesheet' href='typeahead.css' />
	<link rel='stylesheet' href='/style/style.css' />
	<link rel='stylesheet' href='style20150811.css' />
	<link rel="stylesheet" href="/includes/jquery-ui-1.10.3/css/ui-lightness/jquery-ui-1.10.3.custom.css" />
	<link rel="stylesheet" media='print' href='print.css' />
	<link href='http://fonts.googleapis.com/css?family=Open+Sans:600,400|Raleway:400,600' rel='stylesheet' type='text/css'>
	<script type='text/javascript' src='/includes/jquery-ui-1.10.3/js/jquery-1.9.1.js'></script>
	<script type='text/javascript' src='/includes/jquery-ui-1.10.3/js/jquery-ui-1.10.3.custom.js'></script>
	<script type='text/javascript' src='/includes/jquery/bloodhound.js'></script>
	<script type='text/javascript' src='/includes/jquery/jquery.typeahead.js'></script>
	<script type='text/javascript' src='/includes/jquery/nextindom.jquery.js'></script>
	<script type='text/javascript' src='/includes/mustache.js'></script>
	<script type='text/javascript' src='/includes/wiki.js'></script> <!--TODO: Use this-->
	<!--<script type='text/javascript' src='/includes/shortcut_keys.js'></script> <!--TODO: Document this-->
	<script type='text/javascript' src='/includes/jquery.ba-bbq.js'></script> <!--TODO: Document this-->
	<script type='text/javascript' src='/includes/jquery/ajaxq.jquery.js'></script>
	<script type='text/javascript' src='advanced_search.js'></script>
	
	
	<script type='text/javascript'>
	window.user_id = <?=$user->getId()?>;
	window.user_name = <?=json_encode($user->getName())?>;
	window.current_date = <?=json_encode(date("Y-m-d"))?>;
	window.field_map = <?=AdvancedSearch::field_map_to_js(AdvancedSearch::$customers_field_map)?>
	
	
	$(document)
		.ready(function(){
			$("#test")
				.aSearch({
					fields : window.field_map,
					autocompletes : {
						"ship_country" : function ( element ) {
							return $(element)
								.autocomplete({
									delay: 0,
									minLength: 1,
									source: "suggest.php?name=country",
								})
						},
						"ship_state" : function ( element ) {
							return $(element)
								.autocomplete({
									delay: 0,
									minLength: 1,
									source: "suggest.php?name=state",
								})
						}
					}
				})
				
			$(".aSearch-value:first").focus()
		})

	panes = {
		"history" : {
			"show": function(){
				show_history_pane()
			}
		},
		"checks": {
			"show": function(){
				show_checks_pane()
			}
		},
		"payouts": {
			"show": function(){
				show_payouts_pane()
			}
		},
		"old_payouts": {
			"show": function(){
				show_old_payouts_pane()
			}
		},
	}
	
	function reset_form()
	{
		$(".aSearch-value").val("")
		$(".aSearch-value:first").focus()
		$(".aSearch-comp").val("Contains")
	}
	
	<?php
	require_once("templates.js.php");
	?>
	</script>
	<style type='text/css'>
		
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
		
		div.result
		{
			white-space: pre-wrap;
			padding: 5px;
			background: white;
			border: 1px solid gray;
			border-radius: 3px;
			display: inline-block;
			vertical-align: top;
			min-height: 90px;
			min-width: 150px;
			margin: 5px;
		}
		
		table.results
		{
			background: white;
			margin: 5px 5px 20px 5px;
			border-collapse: collapse;
		}
		
		table.results td
		{
			padding: 2px;
			white-space: pre-wrap;
			font-size: 11pt;
		}
		
		table.results td.s
		{
			font-size: 9pt;
		}

		body
		{
			background: white;
		}

	</style>
</head>
<body>
<?PHP
//include ("/webroot/includes/navigation.inc.php");
//include ("/webroot/includes/subnav.inc.php");
//include ("/webroot/includes/status.html");


?>

<div id='root'>

	<?php
		$where = AdvancedSearch::filter_to_sql($_REQUEST['filter'], AdvancedSearch::$customers_field_map, $db);
	?>
	<div id='main'>
		
		<form method='get'>
		<div style='margin: 10px'>
			<button type='button' onclick='$("#test").aSearch("add_row")'>More</button>
			<button type='button' onclick='$("#test").aSearch("remove_row")'>Less</button>
			<button type='button' onclick='reset_form()'>Reset</button>
			<button>Search</button>
		</div>
		<table id='test' style='background: white;'><?
		
		if(!empty($_REQUEST['filter']))
		{
			echo AdvancedSearch::filters_to_html($_REQUEST['filter'], AdvancedSearch::$customers_field_map);
		}
		
		?></table>
		<input type='hidden' name='filter[100][field]' value='Status_Account' />
		<?
		if(empty($_REQUEST['filter'][100]))
		{
			?>
			<label><input type='radio' name='filter[100][value]' value='0' /> Blocked</label>
			<label><input type='radio' name='filter[100][value]' value='1' /> Active</label>
			<label><input type='radio' name='filter[100][value]' value='*' /> Has Auction Acct (any status)</label>
			<label><input type='radio' name='filter[100][value]' value='null' /> No Auction Acct</label>
			<label><input type='radio' name='filter[100][value]' value='' checked='checked' /> No Preference</label>
			<?
		}
		else
		{
			echo "<label><input type='radio' name='filter[100][value]' value='0' ".($_REQUEST['filter'][100]['value'] == "0" ? "checked='checked'" : "")." /> Blocked</label>";
			echo "<label><input type='radio' name='filter[100][value]' value='1' ".($_REQUEST['filter'][100]['value'] == "1" ? "checked='checked'" : "")." /> Active</label>";
			echo "<label><input type='radio' name='filter[100][value]' value='*' ".($_REQUEST['filter'][100]['value'] == "*" ? "checked='checked'" : "")." /> Has Auction Acct (any status)</label>";
			echo "<label><input type='radio' name='filter[100][value]' value='null'  ".($_REQUEST['filter'][100]['value'] == "null"  ? "checked='checked'" : "")." /> No Auction Acct</label>";
			echo "<label><input type='radio' name='filter[100][value]' value=''  ".($_REQUEST['filter'][100]['value'] == ""  ? "checked='checked'" : "")." /> No Preference</label>";
		}
		?>
		
		
		<div>
<?php
require_once("/webroot/invoicing/backend/address.inc.php");
if(!empty($where))
{
	require_once("/webroot/includes/Mustache/Autoloader.php");
	Mustache_Autoloader::register();
	$mustache = new Mustache_Engine;
	
	
	
	$r = $db->query(
		"select customers.*, aa_customers.Status_Account, customers_scores.rank, customers_dedupe.phones, ".
		"if(autoship.customer_id is null, '', 'autoship') as autoship, ".
		"if(pay_and_hold > 0, 'pay & hold', '') as pay_and_hold ".
		"from invoicing.customers ".
		"left join invoicing.aa_customers using(email) ".
		"left join invoicing.customers_scores using(customer_id) ".
		"left join invoicing.customers_dedupe using(customer_id) ".
		"left join invoicing.autoship as autoship using(customer_id) ".
		"left join invoicing.customers_addresses using(customer_id) ".
		"left join invoicing.customers_emails on (customers.customers_id = customers_emails.customers_id) ".
		"where $where group by customers.customers_id limit 5000");
	
	Event::log(array(
		"event_type" => "invoicing-search-advanced",
		"user_id" => $user->getId(),
		"where" => $where,
		"results" => $r->num_rows,
	));
	
	echo "<table class='results' border='1'>\r\n";
	echo "<caption>".$r->num_rows." results <img src='/includes/graphics/clip16.png' title='Copy To Clipboard' 
		class='hoverbutton' onclick='window.ffclipboard.setText(window.text)' /></caption>\r\n";
	$text = "";
	
	/*
		Create mustache template
	*/
	foreach($_REQUEST['filter'] as $f)
	{
		if("" == trim($f['value']) && $f['comp'] != "display")
			continue;
		
		switch($f['field'])
		{
			case "phones":
				$template .= "<td>{{phone_number_1}}</td>\r\n".
					"<td>{{phone_number_2}}</td>\r\n".
					"<td>{{fax_number}}</td>\r\n";
				
				$text_template .= "\t{{phone_number_1}}\t{{phone_number_2}}\t{{phone_number_3}}";
				break;
				
			case "emails":
				$template .= "<td>{{email}}</td>\r\n".
					"<td>{{other_emails}}</td>\r\n";
				
				$text_template .= "\t{{email}}\t{{other_emails}}";
				break;
			
			case "notes_for_invoice":
				$template .= "<td class='s'>{{".$f['field']."}}</td>\r\n";
				$text_template .= "\t{{".$f['field']."}}";
				break;
			
			case "Status_Account":
				$template .= "<td>{{blocked}}</td>\r\n";
				$text_template .= "\t{{blocked}}";
				break;
			
			case "customer_id":
			case "email":
			case "name":
				//These are already displayed by default
				break;
			
			default:
				$template .= "<td>{{".$f['field']."}}</td>\r\n";
				$text_template .= "\t{{".$f['field']."}}";
				break;
		};
	}
	$template .= "</tr>";
	
	while($row = $r->fetch_assoc())
	{
		$row = AdvancedSearch::process_row($row);
		$text .= $mustache->render($text_template, array_map(array("AdvancedSearch", "convert_linebreaks"), $row))."\r\n";
		
		echo $mustache->render($template, $row);flush();
	}
	echo "</table>";
	echo "<script type='text/javascript'>";
	echo "window.text = ".json_encode($text).";";
	echo "</script>";
	
	echo "<small style='white-space: pre-wrap'>$where</small>";	
}
?>
		</div>
		</form>
	</div>
</div>



</body>
</html>