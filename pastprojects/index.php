<?PHP
//ini_set("display_errors", "On");
require_once("/webroot/auth/auth.php");
//require_once("includes.inc.php");
$extra_status_links = "<a href='http://poster-server/wiki/index.php/Edit_Consignors' style='font-size:12px;font-weight:bold;'><div>About This Page</div></a>";

function number_pad($number,$n) {
	return str_pad((int) $number,$n,"0",STR_PAD_LEFT);
}

?>
<html>
<head>
	<title>Edit Consignors</title>
	<link rel='stylesheet' href='style.css' />
	<link rel='stylesheet' href='/style/style.css' />
	<style type='text/css'>
		br
		{
			font-size: 50%;
		}
		
		p
		{
			margin: 1ex;
		}
		input
		{
			margin: 2px;
		}
		form
		{
			font-size: 90%;
		}
		.red
		{
			color:red;
			font-weight: bold;
		}

		.green
		{
			color:green;
		}
		
		textarea
		{
			border: 1px solid grey;
			font-size: 85%;
			font-family: Verdana;
			resize: none;
		}
	</style>
	<script type='text/javascript' src='json.js'></script>
	<script type='text/javascript' src='jquery/jquery.js'></script>
	<script type='text/javascript' src='/includes/jquery/jquery.ui.js'></script>
	<script type='text/javascript' src='jquery/jquery.autocomplete.js'></script>
	<script type='text/javascript' src='jquery/jquery.tooltip.js'></script>
	<script type='text/javascript' src='/includes/shortcut_keys.js'></script>
	<link rel='stylesheet' href='jquery/jquery.autocomplete.css' />
	<link rel='stylesheet' href='jquery/jquery.tooltip.css' />
	<link rel='stylesheet' href='/includes/jquery/jquery.ui.css' />
	<script type='text/javascript'>
		<?php
		echo "document.field = ".json_encode($_GET['search_field']).";\n";
		echo "document.value = ".json_encode($_GET['search_value']).";\n";
		echo "commission_rates = ";
		require_once("/webroot/accounting/backend/commission_rates.js.php");
		echo ";\n";
		?>
		
		jQuery.fn.createValueArray = function() {
			$.values = {};
			this.each(function(){
				switch($(this).attr("type"))
				{
					case "radio":
						if($(this).attr("checked"))
							$.values[$(this).attr("name")] = $(this).val();
						break;
					case "checkbox":
						$.values[$(this).attr("name")] = (($(this).attr("checked")) ? 1 : 0);
						break;
					default:
						$.values[$(this).attr("name")] = $(this).val();
						break;
				};
			});
			return $.values;
		}
		
		function addConsignor()
		{
			if($("#add_consignor textarea[name=ConsignorName]").val() !== "")
			{
				values = $("#add_consignor").find("input, textarea").createValueArray();
				
				if(check_entry(values) === false)
					return false;
				
				$.ajax({
					url: "add_consignor.php",
					type: "post",
					data: "consignorData="+encodeURIComponent(JSON.stringify(values)),
					dataType: "text",
					success: function(data){
						if(data == "ok")
						{
							alert("Success adding consignor!");
							
							$("#add_consignor textarea").val('');
							$("#add_consignor textarea[name=no_matter_what]").val("N")
							$("#add_consignor textarea[name=CommissionRate]").val("basic")
						}
						else
						{
							alert("error adding consignor:\n" + data);
						}
					},
					error: function(){
						alert("Unknown error adding consignor!");
					}
				});
			}
			else
				alert("Consignor Name is required.");
		}
		
		function updateConsignor(autoId)
		{
			values = $("input[name=auto_id][value="+autoId+"]").parents(":eq(1)").find("input, textarea").createValueArray();
			
			$.ajax({
				url: "update_consignor.php",
				type: "post",
				data: "consignorData="+encodeURIComponent(JSON.stringify(values)),
				dataType: "text",
				success: function(data){
					if(data !== "ok")
						alert("error updating consignor:\n" + data);
				},
				error: function(){
					alert("Unknown error updating consignor!");
				}
			});
		}
		
		function get_address(cust_id, consignor_name)
		{
			if($.get_address_ajax)
				$.get_address_ajax.abort();
				
			if($.cust_id == cust_id)
				return false;

			$.cust_id = cust_id;
			$.consignorName = consignor_name;
			
			$.get_address_ajax = $.ajax({
				url: "get_address.php",
				type: "post",
				data: "cust_id="+encodeURIComponent(cust_id),
				dataType: "json",
				success: function(data){
                    if('error' in data)
                        alert(data.error)
                    
					if('address' in data)
					{
						$("#address")
							.html("Address For: <br /><b>"+$.consignorName+"</b><hr />"+
								data.address+
								(data.phone ? "<hr />" + data.phone : "")+
								(data.notes ? "<hr /><div style='width: 172px; white-space: pre-wrap'>"+data.notes+"</div>" : "")+
								(data.blocks ? "<hr /><div style='width: 172px; white-space: pre-wrap'>"+data.blocks+"</div>" : "")).show();
					}
					else
					{
						$("#address").html('No address found').show();
					}
				},
				error: function(){
					alert("Unknown error getting address");
				}
			});
		}
		
		function check_entry(values)
		{
				for(x in values)
				{
					switch(x)
					{
						case "linking_email":
							if(!values[x].match(/^[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,4}$/i) && !values[x].match(/^[\s]*$/))
							{
								alert("Check your linking_email field. It can contain only one valid email address.");
								return false;
							}
							
							if(values['email'].indexOf(values[x]) == -1)
							{
								alert("The linking_email must also be listed in the email field");
								return false;
							}
							break;	
						case "email":
							if(values[x] == "")
								break;
							list = values[x].split(",");
							for(x in list)
							{
								if(!list[x].trim().match(/^[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,4}$/i))
								{
									alert("Check your email field. It can only contain comma separated email addresses. Move any notes to the notes field.");
									return false;
									break;
								}
							}
							break;
						case "SameAs":
							if(values[x].search("use ") === 0 && !values[x].match(/^use \"[^\"]+\"$/))
							{
								alert("Check your SameAs field. When typing \"use\", the name that follows should be in double quotes.");
								return false;
							}
							break;
					};
				}
			return true;
		}
		
		function searchConsignor(search, direction)
		{
			if($.myAjax)
			{
				$.myAjax.abort();
				$("#loading").empty();
			}
			
			if($.page)
				$.page = $.page + direction;			
			
			if(direction === 0 && search != false)
				$.limit = Math.floor((window.innerHeight-195)/27);
			
			// Use last search term if none was given
			if(search === false)
				search = $.term;
			else
			{
				delete $.sortField;
				$.page = 1;
				$.term = search;
			}
			
			$.myAjax = $.ajax({
				url: "consignor_data.php",
				type: "get",
				data: "q=" + encodeURIComponent(search) + ($.page ? "&page="+$.page : "")+"&limit=" + $.limit + "&field=" + encodeURIComponent($("[name=searchField]").val()) + 
							(($.sortField) ? "&sortField="+$.sortField+"&sortDirection="+$.sortDirection : ""),
				dataType: "json",
				beforeSend: function(){
					$("#loading").append("<img src='graphics/indicator.gif' />");
				},
				success: function(data){
					$("#loading").empty();
					$.page = data.page;
					$("#results").empty();
					
					if(data.results.length > 0)
						$("#results").append("<table style='border-collapse: collapse; margin: 2px' border='1'>"+
								"<tr style='font-size: 70%;'><th class='exclude' style='font-size: 90%'>Items</th><th class='exclude'>#</th>"+
								"<th class='exclude'>Mail</th><th class='exclude'>PhO</th><th class='exclude'>Log</th><th name='ConsignorName'>Name</th>"+
								"<th name='CommissionRate'>Comm.</th><th name='SameAs'>SameAs</th><th name='Notes'>Notes</th><th name='email'>email</th>"+
								"<th name='linking_email'>linking_email</th><th name='no_matter_what'>nmw</th>"+
								"<th name='cust_id'>cust_id</th><th name='describer_notes'>describer_notes</th>"+
								"<th name='lots'>lots</th><th name='payment_preference'>pmt_pref</th><th name='payment_notes'>pmt_note</th>"+
								"<th name='dateadded'>Date Added</th><th name='real_name'>real_name</th></tr></table>");
					
					if($.sortField)
					{
						$("#results th[name="+$.sortField+"]").css({
							"color": $.sortDirection ? "black" : "white",
							"background": $.sortDirection ? "white" : "black",
						});
					}
					else
					{
						$("#results th[name=ConsignorName]").css({
							"color": "black",
							"background": "white",
						});
					}
					
					$("#results th:not(.exclude)").css("cursor", "pointer").click(function(){
						$.sortDirection = ($.sortField && $.sortField === $(this).attr("name")) ? !$.sortDirection : true;
						$.sortField = $(this).attr("name");
						searchConsignor(false, 0);
					});
					
					for(x in data.results)
					{	
						tr = $("<tr />");
						
						if(!data.results[x]['CommissionRate'])
						{
							$(tr).css("background", "rgb(255,200,128)");
						}
						
						$("<td style='text-align: center;' />").append(
							$("<a target='_blank' href='http://poster-server/consignor_search/index.php?linked_consignor="+encodeURIComponent(data.results[x]['ConsignorName'])+"'></a>")
								.append($("<img style='border	: 1px solid transparent;' src='graphics/mag.png' />")
											.mouseover(function(){this.style.border = "1px solid black";}).mouseout(function(){this.style.border = "1px solid transparent";})
										)
						)
						.appendTo(tr);
						
						$("<td />").append(data.results[x].total_items)
						.css({
							textAlign: "center",
						}).appendTo(tr);
						
						if(data.results[x]['email'] !== "")
						{
							email_link = $("<a target='_blank' "+(data.names ? 
								"href='mailto:"+
								(data.results[x]['cust_id'] && data.names[data.results[x]['cust_id']] ? 
									data.names[data.results[x]['cust_id']] : 
									data.results[x]['ConsignorName'])+" &lt;"+data.results[x]['linking_email']+"&gt;'" : "")+
								"></a>")
								.append($("<img style='border	: 1px solid transparent;' src='graphics/mail.png' />")
											.mouseover(function(){this.style.border = "1px solid black";})
											.mouseout(function(){this.style.border = "1px solid transparent";})
										)
							
							if(data.results[x]['no_matter_what'] == "X")
							{
								$(email_link).click(function(){
									return confirm("This consignor never wants to be contacted, "+
										"so only e-mail him if you want to override that rule.")
								})
							}
							
							$("<td style='text-align: center;' />").append(email_link).appendTo(tr);
						}
						else
							$("<td>&nbsp;</td>").appendTo(tr);
							
						if(data.results[x]['cust_id'])
						{
							phone_orders_link = $("<a target='_blank' href='/invoicing/#customer_id="+
								encodeURIComponent(data.results[x]['cust_id'])+"'></a>")
								.append($("<img style='border	: 1px solid transparent;' src='graphics/phone.png' />")
											.mouseover(function(){this.style.border = "1px solid black";}).mouseout(function(){this.style.border = "1px solid transparent";})
										)
						}
						else
							phone_orders_link = ""
							
						$("<td style='text-align: center;' />").append(phone_orders_link).appendTo(tr)
						
						$("<td style='text-align: center' />")
							.append(
								$("<a target='_blank' />")
									.attr("href", "/audit_log/?database=listing_system&criteria="+
										encodeURIComponent(JSON.stringify([["table", "=", "tbl_consignorlist"], ["id", "=", data.results[x]['auto_id']]])))
									.append(
										$("<img style='border: 1px solid transparent;' src='/includes/graphics/log.png' />")
											.mouseover(function(){this.style.border = "1px solid black";}).mouseout(function(){this.style.border = "1px solid transparent";})
									)
							)
							.appendTo(tr)
						
						$("<td />").append(
							$("<textarea />")
								.attr({
									value: data.results[x]['ConsignorName'],
									origValue: data.results[x]['ConsignorName'],
									name: 'ConsignorName',
									wrap: 'off',
								})
								.css({height: "1.5em", width:"150px", overflow: "hidden"})
						)
						.appendTo(tr);
						
						$("<td />").append(
								$("<textarea />").attr({
									value: data.results[x]['CommissionRate'],
									origValue: data.results[x]['CommissionRate'],
									name: 'CommissionRate',
									wrap: 'off',
								})
								.css({height: "1.5em", width: "50px", overflow: "hidden"})
								.autocomplete(commission_rates, {minChars: 0, width: 120, max: 999})).appendTo(tr);
						
						$("<td />").append($("<textarea />").attr({
							value: data.results[x]['SameAs'],
							origValue: data.results[x]['SameAs'],
							name: 'SameAs',
							wrap: 'off',
						}).css({width: "110px", height: "1.5em", overflow: "hidden"})).appendTo(tr);
						
						$("<td />").append($("<textarea />").attr({
							value: data.results[x]['Notes'],
							origValue: data.results[x]['Notes'],
							name: 'Notes',
							wrap: 'off',
						}).css({width: "150px", height: "1.5em", overflow: "hidden"})).appendTo(tr);
						
						$("<td />").append($("<textarea />").attr({
							value: data.results[x]['email'],
							origValue: data.results[x]['email'],
							name: 'email',
							wrap: 'off',
						}).css({width: "150px", height: "1.5em", overflow: "hidden"})).appendTo(tr);
						
						$("<td />").append($("<textarea />").attr({
							value: data.results[x]['linking_email'],
							origValue: data.results[x]['linking_email'],
							name: 'linking_email',
							wrap: 'off',
						}).css({width: "130px", height: "1.5em", overflow: "hidden"})).appendTo(tr);
						
						$("<td />").append($("<textarea />").attr({
							value: data.results[x]['no_matter_what'],
							origValue: data.results[x]['no_matter_what'],
							name: 'no_matter_what',
							wrap: 'off',
						}).css({width: "30px", height: "1.5em", overflow: "hidden"})).appendTo(tr);
						
						$("<td />").append($("<textarea />").attr({
							value: data.results[x]['cust_id'],
							origValue: data.results[x]['cust_id'],
							name: 'cust_id',
							wrap: 'off',
						}).css({width: "55px", height: "1.5em", overflow: "hidden"})).appendTo(tr);
						
						$("<td />").append($("<textarea />").attr({
							value: data.results[x]['describer_notes'],
							origValue: data.results[x]['describer_notes'],
							name: 'describer_notes',
							wrap: 'off',
						}).css({width: "110px", height: "1.5em", overflow: "hidden"})).appendTo(tr);
						
						$("<td />").append($("<textarea />").attr({
							value: data.results[x]['lots'],
							origValue: data.results[x]['lots'],
							name: 'lots',
							wrap: 'off',
						}).autocomplete("lots_field", {minChars: 0, cacheLength: 0}).css({width: "90px", height: "1.5em", overflow: "hidden"})).appendTo(tr);
						
						$("<td />").append($("<textarea />").attr({
							value: data.results[x]['payment_preference'],
							origValue: data.results[x]['payment_preference'],
							name: 'payment_preference',
							wrap: 'off',
						}).autocomplete("payment_preference.php", {minChars: 0, cacheLength:0, width: 300}).css({width: "55px", height: "1.5em", overflow: "hidden"})).appendTo(tr);
						
						$("<td />").append($("<textarea />").attr({
							value: data.results[x]['payment_notes'],
							origValue: data.results[x]['payment_notes'],
							name: 'payment_notes',
							wrap: 'off',
						}).css({width: "55px", height: "1.5em", overflow: "hidden"})).appendTo(tr);
						
						$("<td />").append($("<textarea />").attr({
							value: data.results[x]['dateadded'],
							origValue: data.results[x]['dateadded'],
							name: 'dateadded',
							wrap: 'off',
						}).css({width: "100px", height: "1.5em", overflow: "hidden"})).appendTo(tr);
						
						$("<td />").append($("<textarea />").attr({
							value: data.results[x]['real_name'],
							origValue: data.results[x]['real_name'],
							name: 'real_name',
							wrap: 'off',
						}).css({width: "100px", height: "1.5em", overflow: "hidden"})).appendTo(tr);
						
						$("<td style='display: none' />").append("<input type='hidden' value='"+data.results[x]['auto_id']+"' name='auto_id' />").appendTo(tr);	
							
						$("#results table:first").append(tr);
					}
					
					$("[name=no_matter_what]").tooltip({
						bodyHandler: function(){
							return "N = Only e-mail when they have something<br />"+
								"Y = Email for every single auction regardless of whether they have something<br />"+
								"X = Never ever e-mail, ever";
						},
						delay: 0
					})
					
					$("#results textarea")
					.focus(function(event){
						if($.focusedTextarea)
						{
							$.focusedTextarea.style.border = "1px solid grey";
							$.focusedRow.style.background = $.focusedRow.oldBackground;
							
							delete $.focusedTextarea;
							delete $.focusedRow;
						}
						
						this.style.border = '1px solid red';
						$.focusedTextarea = this;
						
						$.focusedRow = $(this).parents(":eq(1)")[0];
						$.focusedRow.oldBackground = $.focusedRow.style.background;
						$.focusedRow.style.background = "rgb(170,255,170)";
						
						get_address($($.focusedRow).find("[name=cust_id]").val(), $($.focusedRow).find("[name=ConsignorName]").val());
					})
					.blur(function(event){
						if($(this).val() != $(this).attr("origValue"))
						{					
	   						values = $(this).parents(":eq(1)").find("input, textarea").createValueArray();
							if(false == check_entry(values))
								return false
							
							switch($(this).attr("name"))
							{
								case "linking_email":
									if(values[x] != "" && values['email'] == "")
									{
										$(this).parents(":eq(1)").find("input[name=email], textarea[name=email]").val($(this).val());
									}
									break;
								case "email":
							  	   if(values[x] != "" && values['linking_email'] == "")
									{
										$(this).parents(":eq(1)").find("input[name=linking_email], textarea[name=linking_email]").val($(this).val());
									}
									break;
								case "ConsignorName":
									if(!confirm("Really change consignor name?"))
									{
										$(this).val($(this).attr("origValue"))
										return false
									}
									break;
							}
							
							updateConsignor($(this).parents(":eq(1)").children(":last").find("input").val());
							
							$(this).attr("origValue", $(this).val());
						}
					})
					.keydown(function(event){
						if(!event.altKey)
						{
							switch(event.which)
							{
								case 113: //F2
									if($.fullBox)
									{											
										$.fullBox.style.height = $.fullBox.oldHeight;
										$.fullBox.style.width = $.fullBox.oldWidth;
										$.fullBox.style.position = $.fullBox.oldPosition;
										$.fullBox.style.right = $.fullBox.oldRight;
										$($.fullBox)
											.attr("wrap", $.fullBox.oldWrap)
											.css("overflow", "hidden")
										
										if($.fullBox === this)
										{
											delete $.fullBox;
											break;
										}
									}
									
									this.oldHeight = this.style.height;
									this.oldWidth = this.style.width;
									this.oldPosition = this.style.position;
									this.oldRight = this.style.right;
									this.oldWrap = $(this).attr("wrap");
									
									if(this.name == "payment_notes" || this.name == "payment_preference")
										this.style.right = 0;
									
									this.style.height = "100px";
									this.style.width = "400px";
									this.style.position = "absolute";
									
									$(this)
										.attr("wrap","hard")
										.css("overflow", "auto")
									
									$.fullBox = this;
									break;
								
								case 27: //Escape
									if($.fullBox)
									{
										$.fullBox.style.height = $.fullBox.oldHeight;
										$.fullBox.style.width = $.fullBox.oldWidth;
										$.fullBox.style.position = $.fullBox.oldPosition;
										$.fullBox.style.border = $.fullBox.oldBorder
										$($.fullBox).attr("wrap", $.fullBox.oldWrap);
										
										delete $.fullBox;
									}
									break;
								case 40: //down
									if(!$(".ac_results").is(":visible"))
									{
										ret = $(this).parents(":eq(1)").next().children(":eq("+this.parentNode.cellIndex+")").children(":eq(0)");
										
										
										if(ret.length)
										{
											$(ret).focus();
											$(this).blur();
										}
									}
								break;
								case 38: //up
									if(!$(".ac_results").is(":visible"))
									{
										ret = $(this).parents(":eq(1)").prev().children(":eq("+this.parentNode.cellIndex+")").children(":eq(0)");
										
										if(ret.length)
										{
											$(ret).focus();
											$(this).blur();
										}
									}
								break;
//								case 36: //home
//									if(!event.shiftKey)
//									{
//										$(this).blur();
//										$(this).parents(":eq(1)").children(":eq(0)").children(":eq(0)").focus();
//									}
//								break;
//								case 35: //end
//									if(!event.shiftKey)
//									{
//										$(this).blur();
//										$(this).parents(":eq(1)").children(":last").prev().children(":eq(0)").focus();
//									}
//								break;
							};
						}
					});
					
					$("#results a").keydown(function(event){
						if(!event.altKey)
						{
							switch(event.which)
							{
								case 40: //down
									if(!$(".ac_results").is(":visible"))
									{
										ret = $(this).parents(":eq(1)").next().children(":eq("+this.parentNode.cellIndex+")").children(":eq(0)");
										
										
										if(ret.length)
										{
											$(ret).focus();
											$(this).blur();
										}
									}
								break;
								case 38: //up
									if(!$(".ac_results").is(":visible"))
									{
										ret = $(this).parents(":eq(1)").prev().children(":eq("+this.parentNode.cellIndex+")").children(":eq(0)");
										
										if(ret.length)
										{
											$(ret).focus();
											$(this).blur();
										}
									}
								break;
//								case 36: //home
//									$(this).blur();
//									$(this).parents(":eq(1)").children(":eq(0)").children(":eq(0)").focus();
//								break;
//								case 35: //end
//									$(this).blur();
//									$(this).parents(":eq(1)").children(":last").prev().children(":eq(0)").focus();
//								break;
							};
						}
					});
					
					$.last_item_num = data.last_item_num;
					$.first_item_num = data.first_item_num;
					
					if(data.pages > 1)
						$("#results").append("<center>Page "+data.page+" of "+data.pages+"<br />"+(($.page !== 1) ? "<img style='cursor: pointer' class='hover' onclick='searchConsignor(false, -1)' src='graphics/la.png' /> " : "<img src='graphics/blank.png' />")+(($.page !== data.pages) ? "<img class='hover' style='margin-left: 5px; cursor: pointer' onclick='searchConsignor(false, 1)' src='graphics/ra.png' />" : "<img src='graphics/blank.png' />")+"</center>");
					$("#results").append("<center>("+data.total+" consignor"+((data.total > 1)?"s":"")+"; "+data.total_active+" active)</center>")
				},
				error: function(){
					alert("Unknown error searching for consignor!");
				},
			});
		}
		
		$(document).ready(function(){		
			$.limit = Math.floor((window.innerHeight-195)/27);
			searchConsignor($("#searchTerm").val(), 0);
			$(document).keyup(function(event){
				switch(event.which)
				{
					case 34: //page down
						$("img[src='graphics/ra.png']").click();
						break;
					case 33: //page up
						$("img[src='graphics/la.png']").click();
						break;
					case 27: //Escape
						if(!$(".ac_results").is(":visible"))
							$("#add_consignor").hide();
						break;
					case 70: //F
						if(event.shiftKey && event.ctrlKey)
							$("#searchTerm").focus();
						break;						
				};
			});

			$("#add_consignor textarea[name=CommissionRate]")
				.autocomplete(commission_rates, {minChars: 0, width: 120});
			
			$("#add_consignor textarea[name=lots]").autocomplete("lots_field", {minChars: 0, cacheLength: 0});
			
			$("#add_consignor textarea[name=payment_preference]").autocomplete("payment_preference.php", {minChars: 0, cacheLength:0, width: 300});
			
			$("#searchTerm").focus();
			
			if(document.field && document.value)
			{
				$("[name=searchField]").val(document.field)
				$("#searchTerm").val(document.value)
				searchConsignor(document.value, 0)
			}
		});
	</script>
</head>
<body>
<?PHP
include("../includes/navigation.inc.php");
include("../includes/subnav.inc.php");
?>
<div id='lognavigation'>
<div>Related:</div>
<ul class='listmenu'>
<li><a href='/tools/commission.php'>Commission Calculator</a></li>
<li><a href='/consignor_search/'>Search Auction Tables</a></li>
<li><a href='/tools/old_consignors.php'>Old Consignors</a></li>
<li><a href='/consignor_search_replace/'>Consignor Search & Replace</a></li>
<li><a href='/tools/consignor_emails.php'>List</a></li>
</ul>
</div>
<?
require_once("/webroot/auth/status.php");

$query = "SHOW FIELDS FROM listing_system.tbl_consignorlist WHERE Field != 'auto_id' AND Field != 'ConsignorName'";
$r = $db->query($query);
$fields = "";
$fields .= "<option>ConsignorName</option>";
while($row = $r->fetch_row())
	$fields .= "<option>".htmlspecialchars($row[0])."</option>";

?>
<fieldset><legend>Search For Consignor</legend>
Field: <select name='searchField'><?=$fields?></select> 
<input id='searchTerm' size='35' type='text' onkeyup='if(event.which == 13) searchConsignor($("#searchTerm").val(), 0);' value="<?=isset($_GET['consignor']) ? $_GET['consignor'] : "%"?>" />
<span id='loading'></span><small>(% for wildcard)</small><input type='button' value='    Add Consignor    ' style='margin-left: 10px;' onclick='$("#add_consignor").show().draggable(); $("#add_consignor [name=ConsignorName]").focus()'/>
<span style='margin-left: 25px'><small style='border: 1px solid black; padding: 4px'>Move: Up, Down, Tab, Shift+Tab -- Expand Field: F2 -- Change pages: Page Up/Down --  Show drop down: Alt+Down</small></span>
</fieldset>
<div style='vertical-align: top'>
<div id='results' style='float:left;'>
</div>
<div id='address' style='float:left; margin-left: 10px; white-space: nowrap; overflow: auto; margin-top: 3px; display: none; border: 1px dashed black; width: 172px; padding: 5px;'>
 
</div>
<div id='add_consignor' style='display: none; position: absolute; top: 200px; left: 200px; width: 500px; border: 2px solid black; background: rgb(240,240,240); padding: 0px;'>
	<div style='background: rgb(200,200,255)'>
		<span style=''>Add a Consignor</span>
		<span style='position: absolute; right: 0; cursor: pointer; background:white; font-weight: bold;' onclick='$("#add_consignor").hide()'>X</span>
	</div>
	<div style='padding: 5px'>
		<table style='width: 100%'>
		<col style='width: 100px'/><col />
		<tr><td><label>Name </label></td><td><textarea name="ConsignorName" wrap="off" style="height: 1.5em; width: 100%;"></textarea></td></tr>
		<tr><td><label>Commission </label></td><td><textarea name='CommissionRate' wrap="off" style="height: 1.5em; width: 70px;" >basic</textarea></td></tr>
		<tr><td><label>SameAs </label></td><td><textarea name="SameAs" wrap="off" style="width: 100%; height: 1.5em;"></textarea></td></tr>
		<tr><td><label>Notes </label></td><td><textarea name="Notes" required style="width: 100%; height: 3em;"></textarea></td></tr>
		<tr><td><label>email </label></td><td><textarea name="email" wrap="off" style="width: 100%; height: 1.5em;"></textarea></td></tr>
		<tr><td><label>linking_email </label></td><td><textarea name="linking_email" wrap="off" style="width: 100%; height: 1.5em;"></textarea></td></tr>
		<tr><td><label>no_matter_what </label></td><td><textarea name="no_matter_what" wrap="off" style="width: 25px; height: 1.5em;">N</textarea></td></tr>
		<tr><td><label>cust_id </label></td><td><textarea name="cust_id" wrap="off" style="width: 61px; height: 1.5em;"></textarea></td></tr>
		<tr><td><label>describer_notes</label></td><td><textarea name="describer_notes" wrap="off" style="width: 100%; height: 1.5em;"></textarea></td></tr>
		<tr><td><label>lots </label></td><td><textarea name="lots" wrap="off" autocomplete="off" class="ac_input" style="width: 100%; height: 1.5em;"></textarea></td></tr>
		<tr><td><label>pmt_pref</label></td><td><textarea name='payment_preference' wrap='off' autocomplete='off' class='ac_input' style='width: 100%; height: 1.5em'></textarea></td></tr>
		<tr><td><label>pmt_note</label></td><td><textarea name='payment_notes' wrap='off' autocomplete='off' class='ac_input' style='width: 100%; height: 1.5em'></textarea></td></tr>
		<tr><td><label>real_name</label></td><td><textarea name='real_name' wrap='off' autocomplete='off' class='ac_input' style='width: 100%; height: 1.5em'></textarea></td></tr>
		</table>
		<input type='button' onclick='addConsignor()' value='Submit'/>
	
	</div>


</div>
</div>
</body>
</html>