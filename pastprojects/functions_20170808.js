$(window)
	.load(function(){
		reset_changed()
	})
	.keydown(function(event){
		if(!event.ctrlKey && !event.altKey && !event.shiftKey)
		{
			
		}
	})
	.change(window_onchange)
	.bind("beforeunload", function(){
		
		return changed_warning()
	})

function window_onchange(event)
{
	if($(event.target).parents("#items, #return, #refund, #package").get().length)
	{
		window.changed_fields.push(event.target)
	}
}

function changed_warning()
{
	if(window.changed_fields.length)
	{
		return changed_fields_txt()
	}
}

function changed_fields_txt()
{
	var txt = ""
	for(x in changed_fields)
	{
		var name = $(changed_fields[x]).attr("name")
		
		if(!name)
			name = $(changed_fields[x]).attr("class")
		
		
		txt += name+", "
	}
	txt = txt.substring(0, txt.length-2)
	
	return txt
}

function changed_warning_prompt()
{
	var txt = changed_warning()
	
	if(txt)
	{
		return confirm("The following fields were changed. Continue without saving? "+txt)
	}
}

function reset_changed()
{
	window.changed_fields = []
}

$(window).bind("hashchange", function(event){
	if(window.location.hash == "#"+window.expected_hash)
		return
	
	var data = $.deparam.fragment()
	
	if(window.last_hashdata)
	{
		if(window.last_hashdata['return_id'] != data.return_id)
		{
			if(!data.return_id)
				return false
			
			open_return(data.return_id)
		}
		
		window.last_hashdata = data
	}
	else
	{
		if(!data.return_id)
			return false
		
		open_return(data.return_id)
	}
})


$(document)
	.ready(function(){
		
		$("#tabs li a")
			.click(function(event){
				if(event.button == 0)
				{
					focus_tab($(this).parents("li:first"))
					event.preventDefault()
				}
			})
			
		$(window).trigger("hashchange")
	})
	.keyup(function(event){
		if(event.keyCode == 13 && event.ctrlKey)
		{
			
		}
	})

function ajax_result_handler(data)
{
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


function ajax_error_handler(jqXHR, textStatus)
{
	if(textStatus != "abort")
		alert("Error! Error! Abort! Abort!")
}

function status(text) {
	clear_status()
	$("#status_bar").append(text)
}

function clear_status() {
	elements = $("#status_bar").find(".do_not_erase")
	$("#status_bar").empty().append(elements)
}

function reset_status() {
	$("#status_bar").empty()
}

function hsv_to_rgb(h, s, v) {
    var r, g, b, i, f, p, q, t;
    if (h && s === undefined && v === undefined) {
        s = h.s, v = h.v, h = h.h;
    }
    i = Math.floor(h * 6);
    f = h * 6 - i;
    p = v * (1 - s);
    q = v * (1 - f * s);
    t = v * (1 - (1 - f) * s);
    switch (i % 6) {
        case 0: r = v, g = t, b = p; break;
        case 1: r = q, g = v, b = p; break;
        case 2: r = p, g = v, b = t; break;
        case 3: r = p, g = q, b = v; break;
        case 4: r = t, g = p, b = v; break;
        case 5: r = v, g = p, b = q; break;
    }
	
	colors = {
        r: Math.floor(r * 255),
        g: Math.floor(g * 255),
        b: Math.floor(b * 255)
    }
	
	return "rgb("+colors.r+","+colors.g+","+colors.b+")"
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
					marginTop: "-50px",
					marginLeft: "-50px",
					zIndex: 1000,
				})
		)
}

function unthrob()
{
	$("#indicator").remove()
}

indicator = $("<img />")
	.attr("src", "/includes/graphics/indicator_big_white.gif")
	.attr("id", "indicator")
	.css({
		
	})


jQuery.fn.view = function(){
	/*
		Scroll element into view.
		If element is already visible, do nothing.
	*/
	
	if(this.length)
	{
		if($(window).scrollTop() > $(this[0]).position().top)
			$(this[0]).get(0).scrollIntoView()
	}
	
	return this
}

function reason(message)
{
	do
	{
		var reason = prompt(message)	

		if(typeof reason != "string")
			return false
		
		if(reason.trim().indexOf(" ") == -1 || reason.trim().length <= 5)
		{
			alert("We need a little more explanation than that.")
		}
		else
		{
			return reason
		}
		
	} while(true)
}

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



function alert_box(alert, type)
{
	div = $("<div />")
		.addClass("alert")
		.append(
			$("<div />")
				.css({
					textAlign: "center",
				})
				.html(alert)
		)
		.append(
			$("<img src='/includes/graphics/black_x.png' />")
				.css({
					cursor: "pointer",
					position: "absolute",
					right: "5px",
					top: "2px",
				})
				.click(function(){
					$(this)
						.parents("div.alert")
						.hide()
				})
				
		)
		
	if(type)
	{
		$(div)
			.attr("type", type)
	}
		
	
	return div
}


function generate_date(iso)
{
	d = new Date()
	
	if(iso)
	{
		return d.getFullYear() + "-" + ("0" + (d.getMonth()+1)).slice(-2) + "-" + 
		("0" + d.getDate()).slice(-2) + " " + ("0" + d.getHours()).slice(-2) + ":" + 
		("0" + d.getMinutes()).slice(-2) + ":" + ("0" + d.getSeconds()).slice(-2);
	}
	else
		return ("0" + (d.getMonth()+1)).slice(-2) + "/" + ("0" + d.getDate()).slice(-2) + "/" + d.getFullYear()
}


function focus_tab(tab)
{
	$("#tabs li").removeClass("focused")
	$(tab).addClass("focused")
	
	//window.expected_hash = jQuery.param({customer_id: data.customer.customer.address.customer_id, tab: $(tab).attr("id")})
	//window.location.hash = window.expected_hash
	
	activate_pane($(tab).attr("tab"))
}

function activate_pane(pane)
{	
	panes[pane].show()
}




function search(term, callback)
{
 	if(false == changed_warning_prompt())
	{
		return false
	}
	
	throb()
	
	$.ajaxq.abort("fetch")
	
	$.ajaxq("fetch", {
		url: "/invoicing/search.php",
		type: "post",
		data: {
			term: term,
			user_id: window.user_id,
		},
		dataType: "json",
		success: [
			function(data){
				
				if("customer" in data)
				{
					$("#customer").remove()
					
					$("#search").typeahead("close").typeahead("val", "")
					
					//TODO: clear_status() function
					clear_status()
					
					//TODO: parse_customer() function
					parse_customer(data)
					
					if(callback)
					{
						callback()
					}
				}
				
				if("record_lock" in data)
				{
					//TODO: alert_box() function
					$("#root").prepend(alert_box(data.record_lock + " is using this account.\n" + 
						"Window will automatically reload when the account becomes available.", "lock"))
					
					window.record_lock = data.record_lock
					
					//readonly()
				}
				else
				{
					delete window.record_lock
				}
				
				if("message" in data)
				{
					//TODO: status() function
					if("customer" in data)
					{
						status(data.message)
					}
					else
					{
						status("<img src='/includes/graphics/alert16.png' /> "+data.message)
					}
				}
				
			}, ajax_result_handler],
		complete: unthrob,
		error: [ajax_error_handler],
	})
}


function check_item2(textbox)
{
	check_item(textbox)
	
	var item = $(textbox).data("item")
	
	
	
	if(item['item']['payout_id'] || item['item']['date_paid'])
	{
		$("#return tbody.payout").show()
		
		$("#return [name='Does the item appear on a payout?'][value=yes]").click()
	}
	else
	{
		$("#return [name='Does the item appear on a payout?'][value=no]").click()
		
		if(item['item']['invoice_number'])
		{
			$("#return [name='Is this a cancellation?'][value=no]").click()
		}
		else
		{
			$("#return [name='Is this a cancellation?'][value=yes]").click()
		}
	}
	
	console.debug(item['item'])
	
	var total = 0
	$("#items :input.search")
		.each(function(){
			if(i = $(this).data("item"))
			{
				total += parseInt(i.item.ebay_price)
			}
		})
	
	$("#return [name='Amount to refund']")
		.val(total)
		.parents("tr")
		.addClass("highlighted")
}


function relist()
{
	var list = $("#items input.search")
		.map(function(){
			if($(this).data("item"))
				return $(this).data("item").item.title_45
		})
	list = $.makeArray(list)
	
	window.open("/relist/?items="+encodeURIComponent(JSON.stringify(list)))
	
}

function parse_customer(data)
{
	reset_changed()
	
	$("#main").empty()
	
	pane = create_customer_pane(data)
	
	$(pane)
		.prependTo("#main")
	
	$(item_search_box(check_item2))
		.appendTo("#main")
	
	details = $(templates.details)
	
	$(details)
		.appendTo("#main")	
	
	$(":input", details)
		.change(function(event){
			validate(event.target)
		})
		.tooltip()
	
	$(templates.submit_return)
		.appendTo("#main")
	
	$("#items input:first").focus()
}






function create_customer_pane(data)
{
	var pane = $("<div />")
		.attr("id", "customer")
	
	if(data.customer.returns.length)
	{
		data.customer.customer.message = "Other returns: "
		for(x in data.customer.returns)
		{
			data.customer.customer.message += 
				"<a href='#return_id="+data.customer.returns[x].return_id+"'>"+
				data.customer.returns[x].started.substring(0, 10)+" ("+data.customer.returns[x].status+")</a> "
		}
	}
	
	var address = $(Mustache.render(templates.customer, data.customer.customer))
	
	//TODO: Only allow bill to and credit cards (maybe other things too) for non-new customers
	//var bill_to = Mustache.render($("#billing_address").html(), data.customer.billing_address)
	
	//var block_records = parse_block_records(data.customer)
	
	//var notes = notes_box(data.customer)
	
	//var cards = credit_cards(data.customer.cards)
	
	$(address)
		.find("[name=pay_and_hold]")
		.prop("checked", data.customer.customer.address.pay_and_hold != "0")
	
	$(pane)
		.append(address)
	
	if(data.customer.stats)
	{
		/*
				Create the Name-mo-stat
		*/
		try
		{
			var custo = data.customer.customer.address.name.split(" ")[0].toLowerCase().substr(0,5)
			
			if(vowels.indexOf(custo[custo.length-1]) !== -1)
				custo += "-mo"
			else
				custo += "-o"
				
			custo = custo[0].toUpperCase() + custo.substr(1)
		}
		catch(e)
		{
			var custo = "Custo"
		}
		
				
		data.customer.stats['custo'] = custo
		
		custostat = $(Mustache.render(templates.stats, data.customer.stats))
			.css({fontSize: "9pt"})
		
		$(pane)
			.append(custostat)
	}
	
	return pane
}

function notes_box(data)
{
	return $("<div />")
		.append("<caption>Customer Notes</caption>")
		.addClass("roundbox")
		.css({
			padding: "4px",
			width: "540px",
			display: "inline-block"
		})
		.append("<label>notes_for_invoice</label>")
		.append(
			$("<textarea />")
				.css({
					height: "150px",
					background: "white",
					fontSize: "9pt",
					borderRadius: "5px",
					border: "1px solid #7f9db9",
				})
				.attr("name", "notes_for_invoice")
				.addClass("customers")
				.val(data.customer.address.notes_for_invoice)
		)
		.append("<label>notes_for_office_use</label>")
		.append(
			$("<input />")
				.css({
					display: "block",
					width: "100%",
					fontSize: "9pt",
					border: "1px solid #7f9db9",
					borderRadius: "5px",
				})
				.attr("name", "notes_for_office_use")
				.addClass("customers")
				.val(data.customer.address.notes_for_office_use)
		)
		
}



function serialize_return()
{
	var data = {
		"details" :	$("#return :input:visible").createValueArray(),
		"customers_id" : $("#customer [name=customers_id]").val(),
		"items" : []
	}
	
	if($("#package").length)
	{
		data['package'] = $("#package :input:visible").createValueArray()
	}
	else
	{
		data['package'] = {}
	}
	
	if($("#refund").length)
	{
		data['refund'] = $("#refund :input:visible").createValueArray()
	}
	else
	{
		data['refund'] = {}
	}
	
	$("#items :input.search")
		.each(function(){
			if(item = $(this).data("item"))
			{
				data['items'].push(item)
			}
		})
		
	return data
}


function submit_return()
{
	throb()
	
	var data = serialize_return()
	
	$.ajaxq("update", {
		url: "backend.php",
		type: "post",
		dataType: "json",
		data: {
			"return": data,
			"user_id": window.user_id,
			"customer_id" : $("#customer [name=customer_id]").val(),
		},
		success: [ajax_result_handler, function(data){
			if("return" in data)
			{
				reset_changed()
				window.expected_hash = jQuery.param({return_id:data.return.return_id})
				window.location.hash = window.expected_hash
				window.location.reload()
			}
		}],
		error: [ajax_error_handler],
	})
	
}


function delete_return()
{
	$("<div />")
		.append("<p>You are about to delete this entire return. "+
			"Records in Consignments table will be restored, but "+
			"records in blocked bidders will not. You must edit them yourself.</p>")
		.append(
			$("<textarea name='reason' placeholder='Type reason for deleting' />")
				.on("input", function(){
					dialog = $(this).parents("div.ui-dialog-content")[0]
					var reason = $(this).val().trim()
					if(reason.length >= 5)
					{
						if($(dialog).dialog("option", "buttons").length == 0)
						{
							$(dialog)
								.dialog("option", "buttons", [{
									text: "Delete",
									click: function(){
										var reason = $(dialog).find(":input[name=reason]").val().trim()
										
										if(reason.length < 5)
										{
											return false
										}
										
										$(dialog).dialog("close")
										real_delete_return(reason)
									}
								}])
						}
					}
					else if($(dialog).dialog("option", "buttons"))
					{
						$(dialog).dialog("option", "buttons", [])
					}
				})
		)
		.dialog({
			title: "Delete this return",
		})
}

function real_delete_return(reason)
{	
	$.ajaxq("update", {
		url: "backend.php",
		type: "post",
		dataType: "json",
		data: {
			"reason" : reason,
			"delete_return" : $("#return [name=return_id]").val(),
			"user_id": window.user_id,
		},
		success: [ajax_result_handler, function(data){
			if("ok" in data)
			{
				reset_changed()
				window.expected_hash = ""
				window.location.hash = ""
				window.location.reload()
			}
		}],
		error: [ajax_error_handler],
	})
}




function save_return(finish)
{
	
	
	var data = serialize_return()
	
	var ajaxData = {
			"save_return" : $("#return [name=return_id]").val(),
			"return": data,
			"user_id": window.user_id,
			"customer_id" : $("#customer [name=customer_id]").val(),
		}
		
	if(finish)
	{
		if(confirm("Confirm that this return has been received, refunded, and "+
			"otherwise fully processed?"))
		{
			ajaxData['finish'] = 1
		}
		else
		{
			return false;
		}
	}
	
	throb()
	
	$.ajaxq("update", {
		url: "backend.php",
		type: "post",
		dataType: "json",
		complete: unthrob,
		data: ajaxData,
		success: [ajax_result_handler, function(data){
			if("return" in data)
			{
				reset_changed()
				
				if(ajaxData['finish'])
				{
					alert("Finished.")
					window.location.hash = ""
					window.location.reload()
				}
				else
				{
					Cookies.set("/returns/warning", 
						"You revised this. Check <a href='/website_tools/user_account_admin.php?user="+
						data.customer.customer.address.email+
						"'>Bidder Account Admin</a> "+
						"for any needed manual changes.");
					alert("Changes saved.")
					window.location.reload()
				}
			}
		}],
		error: [ajax_error_handler],
	})
}

function load_package(data)
{
	package = $(Mustache.render(templates.package, data))
	
	$.getJSON("autocomplete.php",{type:"package_type"}, function(json){
      $(package)
      .find("[name=package_type]")
      .autocomplete({
        source: json,
        minLength: 0,
      })
      .focus(function(){
        $(this).autocomplete("search")
      })
    })
	
	$.getJSON("autocomplete.php",{type:"package_location"}, function(json){
      $(package)
      .find("[name=package_location]")
      .autocomplete({
        source: json,
        minLength: 0,
      })
      .focus(function(){
        $(this).autocomplete("search")
      })
    })
	
	return package
}

function load_refund(data)
{
	refund = $(Mustache.render(templates.refund, data))
	
	$(refund)
		.find("[name=refund_date]")
		.datepicker({
			constrainInput: true,
		})
	
	$(refund)
		.find("[name=consignor_money]")
		.autocomplete({
			source: './autocomplete_data/consignor_money.json',
			minLength: 0,
		})
		.focus(function(){
			$(this).autocomplete("search")
		})
		
	$(refund)
		.find("[name=after_refund_action]")
		.autocomplete({
			source: './autocomplete_data/after_refund_action.json',
			minLength: 0,
		})
		.focus(function(){
			$(this).autocomplete("search")
		})
	
	return refund
}

function validate(input, async)
{	
	var data = {
		"interact": 1,
		"return" : serialize_return(),
		"user_id": window.user_id
	}
	
	if(input)
	{
		data["interaction"] = $(input).attr("name")
	}
	
	throb()
	
	$.ajaxq("update", {
		async: async,
		url: "backend.php",
		type: "post",
		dataType: "json",
		data: data,
		complete: unthrob,
		success: [ajax_result_handler, function(data){	
			
			if(data.num_changed)
			{
				$("#return tr.highlighted")
					.removeClass("highlighted")
					
				for(x in data.changed)
				{
					elements = $("#return :input")
						.filter(function(){
							return $(this).attr("name") == x
						})
						.get()
					
					if(elements.length > 1)
					{
						element = $(elements)
							.filter(function(){
								return $(this).val() == data.changed[x]
							})
							
						$(element)
							.prop("checked", true)
							
						$(element)
							.parents("tr")
							.addClass("highlighted")
					}
					else
					{
						element = elements[0]
						
						$(element)
							.val(data.changed[x])
						
						$(element)
							.parents("tr")
							.addClass("highlighted")
					}
				}
			}
			
			if("unlocked" in data)
			{
				for(x in data.unlocked)
				{
					$("#return :input")
						.filter(function(){
							return $(this).attr("name") == data.unlocked[x]
						})
						.prop("disabled", false)
						.parents("tr")
						.removeClass("locked")
						.prop("title", data.unlocked[x])
						.find(":input:not(:checked)")
						.css("opacity", "1")
				}
			}
			
			if("locked" in data)
			{
				for(x in data.locked)
				{
					$("#return :input")
						.filter(function(){
							return $(this).attr("name") == data.locked[x][0]
						})
						.prop("disabled", true)
						.parents("tr")
						.addClass("locked")
						.prop("title", data.locked[x][1])
						.find(":input:not(:checked)")
						.css("opacity", "0")
				}
			}
			
			
		}],
		error: [ajax_error_handler],
	})
}

function load_details(data)
{
	details = $(templates.details)
		.appendTo("#main")

	$(":input", details)
		.change(function(event){
			validate(event.target)
		})
	
	$("[name=return_id]", details).val(data.return_id)
	
	/*if("consignor_money" in data['data']['details'])
	{
		$("tbody.payout", details).show()
	}
	else
	{
		for(x in data['data']['items'])
		{
			if("payout_id" in data['data']['items'][x]['item'])
			{
				$("tbody.payout", details).show()
				break;
			}
		}
	}*/
	
	
	data = data['data']['details']
	
	for(x in data)
	{
		elements = $(details)
			.find(":input")
			.filter(function(){
				return ($(this).attr("name") == x)
			})
		
		if(elements.length > 1)
		{
			$(elements)
				.filter(function(){
					return ($(this).val() == data[x])
				})
				.prop("checked", true)
		}
		else
		{
			$(elements).val(data[x])
		}
	}
	
	
	
	return details
}


function open_return(return_id)
{
	window.expected_hash = jQuery.param({return_id:return_id})
	window.location.hash = window.expected_hash
	
	$("#main > *:not(#customer_search)").remove()
	
	throb()
	
	$.ajaxq("update", {
		url: "backend.php",
		type: "post",
		async: false,
		dataType: "json",
		data: {
			"return_id" : return_id,
			"user_id": window.user_id,
		},
		complete: unthrob,
		success: [ajax_result_handler, function(data){
				
			if(data.return.version == 0)
			{
				window.location = "/returns_old/#return_id="+return_id
				return false
			}
		
			if(warning = Cookies.get("/returns/warning"))
			{
				$("<p />")
					.css({
						padding: "100px",
						fontSize: "110%",
						fontWeight: "bold",
						color: "red"
					})
					.html(warning)
					.appendTo("#main")
					
				Cookies.remove("/returns/warning")
			}
		
			$(templates.save_return)
					.appendTo("#main")
					
			if("return" in data)
      {       
        for(x in data['return'].data.items)
        {
          $(Mustache.render(window.templates.item, data['return'].data.items[x]))
              .appendTo("#main")
        }
      }
			
			if("package" in data.return.data)
			{
				$(load_package(data.return.data.package))
					.appendTo("#main")
			}
			else
			{
				$(load_package({}))
					.appendTo("#main")
			}
			
			if("refund" in data.return.data)
			{				
				$(load_refund(data.return.data.refund))
					.appendTo("#main")
			}
			else
			{
				$(load_refund({}))
					.appendTo("#main")
			}
		
			
		
			if("customer" in data)
			{
				pane = create_customer_pane(data)
				
				$(pane)
					.appendTo("#main")
			}
			
			
			
			/*
				$(item_search_box(check_item))
					.appendTo("#main")
				
				$(templates.details)
					.appendTo("#main")
				
				$(templates.submit_return)
					.appendTo("#main")
				
				$("#items input:first").focus()
			*/
		
			if("return" in data)
			{
				$("<h3 />")
					.css({
						width: "600px",
					})
					.text("The below greyed out info was filled out by "+data['return']['who_started']+
					". Please only edit it if you know for sure what you are doing!")
					.appendTo("#main")
				
				$(item_search_box(check_item2, data['return'].data.items))
					.appendTo("#main")
				
				
				for(x in data['return'].data.items)
				{
					$(Mustache.render(window.templates.item, data['return'].data.items[x]))
							.appendTo("#main")
				}
				
				details = $(load_details(data['return']))
					.appendTo("#main")
				
				//To run all onclick event handlers for radio boxes
				$(details)
					.find(":input:checked")
					.click()
					
				$(details)
					.find(":input")
					.tooltip()
				
				//validate()
				
				$(templates.save_return)
					.appendTo("#main")
			}
			
			if("refund" in data.return.data && data.return.data.refund.refund_date)
			{
				$("#main button.finish").show()
			}
			
			if("details" in data.return.data && data.return.data.details['Is this a cancellation?'] == "yes")
			{
				$("#main button.finish").show()
				$("#package, #refund").hide()
			}
			
			if("details" in data.return.data && data.return.data.details['Is the customer keeping the item?'] == "yes")
      {
        $("#package").hide()
      }
			
			$("#return")
				.find(":input")
				.prop("disabled", true)
				
			$("#items :input")
				.prop("readonly", true)
		}],
		error: [ajax_error_handler],
	})
}

function list(all)
{
	throb()

	$.ajaxq("update", {
		url: "backend.php",
		type: "post",
		dataType: "json",
		data: {
			list: 1,
			all: all
		},
		complete: unthrob,
		success: [ajax_result_handler, function(data){
			if("returns" in data)
			{
				$("div.incomplete").remove()
				
				element = $(Mustache.render(templates.list, data.returns))
					.css({
						maxHeight: "200px",
						overflow: "auto",
					})
					.prependTo("#top")
					.click(function(event){
						if($(event.target).is("td") || $(event.target).parents("td").length)
						{
							open_return($(event.target).parents("tr:first").data("return_id"))
							return false;
						}
					})
					
				if(all)
				{
					$("button", element)
						.html("Hide Complete")
						.unbind("click")
						.click(function(){
							list(0)
						})
				}
				else
				{
					$("button", element)
						.html("Show All")
						.unbind("click")
						.click(function(){
							list(1)
						})
				}
			}
		}],
		error: [ajax_error_handler],
	})
}

function edit_return()
{
	$("#return :input")
		.prop("disabled", false)
		
	$("#items :input")
		.prop("readonly", false)
		
		
	$("div.return-buttons button.edit").remove()
	
	//validate(null, true)
}