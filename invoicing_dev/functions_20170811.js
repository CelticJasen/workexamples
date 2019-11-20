history_pane_perpage = 1000
account_pane_perpage = 30

window.onbeforeunload = function(){
	//Return false if unsaved data
	if(check_for_unsaved().length)
		return false;
}



$(window).bind("hashchange", function(event){
	if(window.location.hash == "#"+window.expected_hash)
		return
	
	var data = $.deparam.fragment()
	
	if(data.ticket_id)
	{
		load_ticket(data.ticket_id, 
			function(dialog){
				//Gets called when ticket is saved
				$(dialog).dialog("destroy")
				refresh()
			},
			function(ticket){
				//Gets called when ajax returns ticket data
				
				if(!window.data || window.data.customer.customer.address.customers_id != ticket.attributes.customers_id)
				{
					if("customers_id" in ticket.attributes)
					{
						search(ticket.attributes.customers_id, function(){
							
							if("invoice_number" in ticket.attributes)
							{
								show_items_pane()
								get_invoice(ticket.attributes.invoice_number)
							}
							else
							{
								show_customer_pane()
							}
						})
					}
				}
				else if("invoice_number" in ticket.attributes)
				{
					get_invoice(ticket.attributes.invoice_number)
				}
				
			})
	}
	
	if(!data.customer_id)
		return false
	
	if(data.tab)
	{
		search(data.customer_id, function(){focus_tab($("#"+data.tab))})
	}
	else
	{
		search(data.customer_id)
	}
})



function check_for_unsaved()
{
	var changes = []
	
	if(window.customers_original != $(window.customer_pane).find(":input.customers").serialize())
		changes.push("address")
	
	if(window.billing_original != $(window.customer_pane).find(":input.bill_to_address").serialize())
		changes.push("billing address")
	
	if(window.newcard_original != $("#newcard :input").serialize())
		changes.push("new credit card")
	
	if(window.newcard_original != $(window.items_pane).find("div.card_container:first :input").serialize())
		changes.push("new credit card")
	
	if(window.new_item_original != $("#new_item :input").serialize())
		changes.push("new item")
	
	divs = $("div.extra_address").get()
	
	for(x in divs)
	{
		if(!window.extra_addresses_original[x] || window.extra_addresses_original[x] != $(divs[x]).find(":input").serialize())
			changes.push("extra address #"+(parseInt(x)+1))
	}
	
	return changes
}

indicator = $("<img />")
	.attr("src", "/includes/graphics/throb.gif")
	.attr("id", "indicator")
	.css({
		
	})


jQuery.fn.autoHeight = function(){
	$(this)
		.on("input", function(){
			$(this).css("height", $(this).height() + $(this).get(0).scrollTopMax)
		})
		
	$(this).css("height", $(this).height() + $(this).get(0).scrollTopMax)
}

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

$(window)
	
	//I get the feeling I'm going to be uncommenting this some time in the future. AK 2014-12-15
	//Update 2015-04-08: Yep, I'm uncommenting it. Damn, past self, you're good! AK
	.dblclick(function(event){
	 	if($(event.target).is("div.card_container") || $(event.target).parents("div.card_container").length)
		{
			if($(event.target).parents("div.card_container").length)
				var card = $(event.target).parents("div.card_container")
			else
				var card = $(event.target)
			
			edit_card(card)
		}
	})
	.keydown(function(event){
		if(!event.ctrlKey && !event.altKey && !event.shiftKey)
		{
			if(event.keyCode == 33) //Page up
			{
				li = $("#tabs").find("li.focused")
				
				if($(li).prev().length)
					focus_tab($(li).prev())
				else
					focus_tab($("#tabs").find("li:last"))
				
				event.preventDefault()
			}
			else if(event.keyCode == 34) //page down
			{
				li = $("#tabs").find("li.focused")
				
				if($(li).next().length)
					focus_tab($(li).next())
				else
					focus_tab($("#tabs").find("li:first"))
					
				event.preventDefault()
			}
			else if(event.keyCode == 27) //Escape
			{
				if($("#newinvoice").is(".editinvoice"))
					refresh()
			}
		}
	
		if(($(event.target).is("#newinvoice") || $(event.target).parents("#newinvoice").length) && event.keyCode == 80 /*P*/ && event.ctrlKey)
		{
			print_phone_order(true)
			event.preventDefault()
		}
		else if(event.keyCode == $.ui.keyCode.ENTER)
		{
			if(window.record_lock)
				return false
			
			if($(event.target).is("#newcard") || $(event.target).parents("#newcard").length || 
				$(event.target).is("#newcard2") || $(event.target).parents("#newcard2").length)
			{
				if($(event.target).is("#newcard2") || $(event.target).is("#newcard"))
				{
					element = event.target
				}
				else if($(event.target).parents("#newcard2").length)
				{
					element = $(event.target).parents("#newcard2").get(0)
				}
				else if($(event.target).parents("#newcard").length)
				{
					element = $(event.target).parents("#newcard").get(0)
				}
				
				console.debug(element)
				
			 	add_card(element)
			}
			else if($(event.target).is("div.card:not(#newcard):not(#newcard2) input"))
			{
				update_card($(event.target).parents("div.card_container"))
			}
			else if($(event.target).is("div.card_container:not(#newcard):not(#newcard2) input.preference"))
			{
				update_card_order($(event.target).parents("div.card_container"))
			}
			else if($(event.target).is(".editinvoice") || $(event.target).parents(".editinvoice").length && event.ctrlKey)
			{
				update_invoice()
			}
			else if(($(event.target).is("#newinvoice") || $(event.target).parents("#newinvoice").length) && event.ctrlKey)
			{
				create_invoice()
			}
			else if(($(event.target).is("#new_item") || $(event.target).parents("#new_item").length) && event.ctrlKey)
			{
				create_item()
			}
		}
		
		if(event.keyCode == $.ui.keyCode.SPACE && $(event.target).is("table.items tr:not(.deleted)"))
		{
			$(event.target)
				.toggleClass("selected")
				
			update_new_invoice()
			
			update_customer_pane_total()
		}
		
		if(event.altKey)
		{
			if(event.keyCode == 83 /*S*/)
			{
				$("#search").focus()
				$("#search").select()
				event.preventDefault()
			}
			else if(event.keyCode == 67 /*C*/)
			{
				$("#newcard").find("input:first").focus()
			}
		}
		
		if($(event.target).is("div.card:not(#newcard)") && event.keyCode == $.ui.keyCode.DELETE)
		{
			if(window.record_lock)
				return false
			
			$(event.target).view()
			
			$(event.target)
				.css({
					border: "1px solid red"
				})
				
			if(confirm("Delete card "+$(event.target).find("div.number").text()+"?"))
			{
				delete_card($(event.target).parents("div.card_container"))
			}
			
			$(event.target)
				.css({
					border: ""
				})
		}
		
		
		if($(event.target).is("li.invoice"))
		{
			if(window.record_lock)
				return false
			
			if(event.keyCode == $.ui.keyCode.DELETE)
			{
				$(event.target).view()
				
				$(event.target)
					.css({
						border: "1px solid red"
					})
				
				if(confirm("Are you sure you want to delete this invoice?"))	
					delete_invoice(event.target, "")
				
				/*
				if(text = reason("Type your reason for deleting this invoice."))
				{
					delete_invoice(event.target, text)
				}
				*/
				
				$(event.target)
					.css({
						border: ""
					})
			}
		}
		
		if($(event.target).is("table.items tr:not(.deleted)"))
		{
			/*
			//We (Phil) don't want this. It's dangerous. AK 2015-01-14
			if(event.keyCode == $.ui.keyCode.DELETE)
			{
				if(window.record_lock)
					return false
				
				var total = total_selected_items()
				
				if(total)
				{
					if(confirm("Delete "+total+ " item"+(total > 1 ? "s" : "")+"?"))
					{
						delete_selected_items()
					}
				}
			}*/
			if(event.keyCode == $.ui.keyCode.TAB)
			{
				if(event.shiftKey)
				{
					var table = $(event.target)
						.parents("tbody.items")
						.prevInDOM("tbody.items")
						
					$(table).find("tr:first").focus()
					
					var element = $(table).prev()
					
					if(element.length)
						$(element).get(0).scrollIntoView()
						
					if(table.length) //Allow tabbing back out of the items list onto other elements
						event.preventDefault()
				}
				else
				{
					var table = $(event.target)
						.parents("tbody.items")
						.nextInDOM("tbody.items")
						
					$(table).find("tr:first").focus()
					
					var element = $(table).prev()
					if(element.length)
						$(element).get(0).scrollIntoView()
					
					//Since the items lists are the last element in the DOM, don't allow tabbing down out of it.
					event.preventDefault()
				}
			}
			else if(event.keyCode == $.ui.keyCode.UP)
			{
				$(event.target).prevInDOM("table.items tr").focus()
				event.preventDefault()
			}
			else if(event.keyCode == $.ui.keyCode.DOWN)
			{
				$(event.target).nextInDOM("table.items tr").focus()
				event.preventDefault()
			}
			else if(event.keyCode == 65 /*A*/ && event.ctrlKey)
			{
				if($(event.target).parents("tbody.items").find("tr:not(.selected)").length == 0)
				{
					$(event.target)
						.parents("tbody.items")
						.find("tr")
						.removeClass("selected")
				}
				else
				{
					$(event.target)
						.parents("tbody.items")
						.find("tr")
						.addClass("selected")
				}
				
				update_new_invoice()
				
				update_customer_pane_total()
			
				event.preventDefault()
			}
		}
	})
	
	window
		.addEventListener("keydown", function(e) {
		    // space and arrow keys
		    if([32, 37, 38, 39, 40].indexOf(e.keyCode) > -1 && 
				!$(e.target).is(":input") && 
				["auto", "scroll"].indexOf($(e.target).css("overflow")) == -1) {
		        e.preventDefault();
		    }
		}, false);



$(document)
	.click(function(event){
		if($("#main_menu").is(":visible") && $(event.target).is(":not(#main_menu *):not(#menu_button)"))
		{
			$("#main_menu").hide()
		}
	})
	.dblclick(function(event){
		if($(event.target).is("li.invoice") || $(event.target).parents("li.invoice").length)
		{
			if($(event.target).parents("li.invoice").length)
				var element = $(event.target).parents("li.invoice").get(0)
			else
				element = event.target
			
			$("ul.invoices li.invoice")
				.removeClass("editing")
			
			get_invoice($(element).data("invoice_number"))
			
			$(element)
				.addClass("editing")
		}
		else if($(event.target).is("li.package, li.untracked_invoice") || $(event.target).parents("li.package, li.untracked_invoice").length)
		{
			if($(event.target).parents("li.package, li.untracked_invoice").length)
				var element = $(event.target).parents("li.package, li.untracked_invoice").get(0)
			else
				var element = event.target
				
			focus_tab($("#items_tab"))
			get_invoice($(element).data("invoice_num"))
		}
		else if(
			$(event.target).parents("#items_pane").length && 
			($(event.target).is("span.shipping_notes, span.ebay_title, span.price") || 
			$(event.target).find("span.shipping_notes, span.ebay_title, span.price").length))
		{
			if($(event.target).find("span.shipping_notes, span.ebay_title, span.price").length)
				var element = $(event.target).find("span.shipping_notes, span.ebay_title, span.price").get(0)
			else
				var element = event.target
			
			var input = $("<input />")
				.addClass($(element).attr("class"))
				.val($(element).text().trim())
				.data("original_element", element)
				.data("autonumber", $(element).parents("tr:first").data("autonumber"))
				.keyup(function(event){
					if(event.keyCode == 13) //Enter
					{
						if(this.editing == true)
							return false
						this.editing = true
						
						var data = {}
						data[$(this).attr("class")] = $(this).val()
						
						edit_item(
							$(this).data("autonumber"), 
							data, 
							function(item){
								this.editing = false
								
								$(this)
									.parents(".items tr")
									.data($(this).attr("class"), item[$(this).attr("class")])
									
								$(this)
									.replaceWith(
										$($(this).data("original_element"))
											.text(item[$(this).attr("class")])
									)
								
								//Update total if price was changed	
								if($(this).attr("class") == "price")
									update_new_invoice()
								
							}.bind(this),
							function(){
								this.editing = false
								$(this)
									.replaceWith($(this).data("original_element"))
							}.bind(this))
					}
				})
				.blur(function(){
					
					if(this.editing == true)
						return false
					this.editing = true
					
					var data = {}
						data[$(this).attr("class")] = $(this).val()
					
					edit_item(
						$(this).data("autonumber"), 
						data, 
						function(item){
							this.editing = false
							
							$(this)
								.parents(".items tr")
								.data($(this).attr("class"), item[$(this).attr("class")])
							
							$(this)
								.replaceWith(
									$($(this).data("original_element"))
										.text(item[$(this).attr("class")])
								)
								
							//Update total if price was changed	
							if($(this).attr("class") == "price")
								update_new_invoice()
						}.bind(this),
						function(){
								this.editing = false
								$(this)
									.replaceWith($(this).data("original_element"))
							}.bind(this))
				})
			
			$(element).replaceWith(input)
			
			$(input)
				.select()
		}
	})
	.ready(function(){
			update_history_bar(window.search_history)
			update_history_bar2(window.search_history2)
		  	$("#main_menu")
				.find(":input")
				.change(function(event){
					update_customer_admin($("#main_menu :input").createValueArray())
				})
			
			/*{
				"customers" : customers,
				"customer2" : customer2,
				"items" : items,
				"payments" : payments,
			}*/
			
			hounds = initialize_bloodhound();
			
			initialize_typeahead($("#search"), hounds)	
	
		$("div.pane")
			.each(function(){
				panes[$(this).attr("id")] = $(this).contents()
				$(this).empty()
			})
			
		
		
		$("#tabs")
			.keydown(function(event){
				//TODO: Key code constants
				li = $(this).find("li.focused")
				
				if(event.keyCode == 37) //Left
				{
					if($(li).prev().length)
						focus_tab($(li).prev())
					else
						focus_tab($(this).find("li:last"))
				}
				else if(event.keyCode == 39) //Right
				{
					if($(li).next().length)
						focus_tab($(li).next())
					else
						focus_tab($(this).find("li:first"))
				}
			})
			.mouseenter(tab_mouseenter)
			.mouseleave(tab_mouseleave)
			
		$("#tabs li a")
			.click(function(event){
				if(event.button == 0)
				{
					focus_tab($(this).parents("li:first"))
					event.preventDefault()
				}
			})
			/*.tooltip({
				items: "li",
				content: "Use Page Up and Page Down to change tabs.",
			})*/ //Removed because I added the quick_info_box. AK 2015-07-29
			
		setInterval(function(){
			update_locks()
		}, 5000)
		
		//new_customer()
		
		$("#refresh")
			.click(function(){
				refresh()
			})
			
		$("#quote_request")
			.click(function(){
				request_quote()
			})
			
		$("#admin_settings")
			.click(function(){
				admin()
			})
		
		/*$("#search")
			.val("")
			.focus()
			.tooltip({
				items: "input",
				content: "search by name, email, customer id, username, item name, item number, invoice, package, quote, or paypal transaction id"
			})*/
			
		snapshot_inputs()
		
		$("#search").focus()
		
		show_home_pane()
		
		$(window).trigger("hashchange")
	})
	


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

function empty_main(jqXHR, textStatus)
{
	$("#main").empty()
}

function ajax_error_handler(jqXHR, textStatus)
{
	if(textStatus != "abort")
		alert("Error! Error! Abort! Abort!")
}






function snapshot_inputs()
{
	window.customers_original = $(window.customer_pane)
		.find(":input.customers")
		.serialize()
	
	window.billing_original = $(window.customer_pane)
		.find(":input.bill_to_address")
		.serialize()
		
	window.newcard_original = $("#newcard :input").serialize()
	
	window.new_item_original = $("#new_item :input").serialize()
	
	
	window.extra_addresses_original = []
	
	divs = $("div.extra_address").get()
	
	for(x in divs)
	{
		window.extra_addresses_original.push($(divs[x]).find(":input").serialize())
	}
}





function show_items_pane()
{
	$(".ui-tooltip").remove()
	
	$.ajaxq.abort("fetch")
	
	$(window.packages_pane)
		.css({display: "none"})
	
	$(window.customer_pane)
		.css({display: "none"})
	
	$(window.items_pane)
		.css({display: ""})
		
	$(window.consignor_pane)
		.css({display: "none"})
		
	$(window.history_pane).remove()
	
	delete window.history_pane
	
	$(window.emails_pane).remove()
	
	delete window.emails_pane
	
	$(window.home_pane).remove()
	
	delete window.home_pane
	
	$(window.account_pane).remove()
	
	delete window.account_pane
	
	$(window.checks_pane).remove()
	
	delete window.checks_pane
	
	update_new_invoice()
	
	$("#newinvoice [name=extra_info]").focus()
}



function show_packages_pane()
{
	$(".ui-tooltip").remove()
	
	$.ajaxq.abort("fetch")
	
	$(window.customer_pane)
		.css({display: "none"})
	
	$(window.items_pane)
		.css({display: "none"})
		
	$(window.consignor_pane)
		.css({display: "none"})
	
	if(!window.packages_pane)
		window.packages_pane = create_packages_pane(window.data.packages)
		
	$(window.packages_pane)	
		.appendTo(document.body)
		.css({display: ""})
		
	$(window.history_pane).remove()
	
	delete window.history_pane
	
	$(window.emails_pane).remove()
	
	delete window.emails_pane
	
	$(window.home_pane).remove()
	
	delete window.home_pane
	
	$(window.account_pane).remove()
	
	delete window.account_pane
	
	$(window.checks_pane).remove()
	
	delete window.checks_pane
	
	$("#package_search").focus()
}



function create_customer_pane(data)
{
	var pane = $("<div />")
		.attr("id", "customer_pane")
		.css({
			padding: "10px"
		})
	
	var address = $(Mustache.render(window.templates.customer, data.customer))	
	//TODO: Only allow bill to and credit cards (maybe other things too) for non-new customers
	var bill_to = Mustache.render(window.templates.billing_address, data.customer.billing_address)
	
	hidden = 0
	$("#other_emails_table", address)
		.find("tr.email")
		.each(function(){
			if($("select[name=email_type]", this).val() == "Bad" || $("select[name=email_type]", this).val() == "Old" || 
				(data.customer.customer.address.email && $("input[name=email]", this).val().toLowerCase() == data.customer.customer.address.email.toLowerCase()))
			{
				$(this)
					.css({
						display: "none"
					})
					
				hidden++
			}
		})
		
	if(hidden == 0)
	{
		$("#hidden_emails", address).empty()
		$("#show_hide_other_emails_button", address).hide()
	}
	else
	{
		$("#hidden_emails", address).text(hidden+" hidden")
		$("#show_hide_other_emails_button", address).show()
	}
		
	$("#other_emails_table", address)
		.css({
			display: "table"
		})
	
	var block_records = parse_block_records(data.customer)
	var notes = notes_box(data.customer)
	var cards = credit_cards(data.customer.cards)	
	
	$(address)
		.find("[name=pay_and_hold]")
		.prop("checked", data.customer.customer.address.pay_and_hold != "0")
		
	$(address)
		.find("[name=autoship]")
		.prop("checked", data.customer.autoship)
		
	if(!data.customer.autoship)
	{
		$(".autoship[name=date_added]")
			.val(window.current_date)
	}
	
	if(data.new_customer)
	{
		$(address)
			.find("[name=email]")
			.removeAttr("readonly")
	}
	
	if(data.customer.customer.last_printout_days < 7)
	{
		$(address).find("#last_printout")
			.css({
				fontWeight: "bold",
				color: "tomato",
			})
	}
	else if(data.customer.customer.last_printout_days < 14)
	{
		$(address).find("#last_printout")
			.css({
				fontWeight: "bold",
			})
	}
	
	/*
		Left pane. 
	*/
	var left = $("<div />")
		.css({
			display: "inline-block",
			verticalAlign: "top",
			width: "605px",
		})
		
	
	tab_list = [{element: address, tab: "Shipping", subtitle: "Primary"}]
	
	if(data.customer.other_addresses && data.customer.other_addresses.length)
	{
		//$(left)
		//	.append(other_addresses)
		for(x in data.customer.other_addresses)
		{
			tab_list
				.push({
					element: $(Mustache.render($("#other_address").html(), data.customer.other_addresses[x])),
					tab: data.customer.other_addresses[x].address_label,
					subtitle: data.customer.other_addresses[x].address_label2
				})
		}
	}
	
	tab_list
		.push({
			element: $(Mustache.render($("#other_address").html(), {
				customer_id: window.customer_id,
				name: window.data.customer.customer.address.name,
				address_label: "New Address",
			})),
			//subtitle: "New",
			tab: $("<img src='/includes/graphics/plus16.png' />")
		})
	
	tab_list
		.push({
			element: $(Mustache.render($("#other_address").html(), {
				customer_id: window.customer_id,
				name: "Mario Cueva",
				ship_attention_line: "Lumiere Poster Restoration",
				ship_address_line1: "16055 Peninsula Ct.",
				ship_city: "Moreno Valley",
				ship_state: "CA",
				ship_zip: "92551",
				ship_country: "US",
				address_label: "Mario Cueva",
			})),
			//subtitle: "Mario",
			tab: $("<img src='/includes/graphics/mario.gif' />")
		})
	
	tab_div = tabs(tab_list)
	
	$(tab_div)
		.appendTo(left)
		.tabs()	
	
	$(left)
		.append(bill_to)
	
	$(pane)
		.append(left)
	
	/*
		Right Pane
	*/
	right = $("<div />")
				.css({
					display: "inline-block",
					verticalAlign: "top",
					width: "560px",
				})
				.append(notes)
	
	if(data.customer.paypal_payments)
	{
		$(right)
			.append(
				$(paypal_payments(data.customer.paypal_payments))
					/*.tooltip({
						items: "div.roundbox",
						content: "PayPal Payments received from this person"
					})*/
			)
	}
	
	if(data.customer.paypal_payments_received)
	{
		$(right)
			.append(
				$(paypal_payments_received(data.customer.paypal_payments_received))
					/*.tooltip({
						items: "div.roundbox",
						content: "PayPal Payments sent to this person"
					})*/
			)
	}
	
	$(right).appendTo(pane)
	
	/*
		Blocked Bidder records
	*/
	if(block_records != "")
	{
		$(right)
			.append(
				$("<div />")
					.addClass("blocks")
					.css({
						display: "inline-block",
						verticalAlign: "top",
						width: "540px",
					})
					.append(block_records)
			)
	}
	
	/*
		New Credit Card
	*/	
	div = $("<div />")
		.addClass("cards")
		.css({
			display: "inline-block",
			verticalAlign: "top",
			width: "540px",
		})
		.append(newcard())
		.append(cards)
		.appendTo(right)
		
	$(pane)
		.find("div.card_container:first")
		.tooltip({
			items: "div.card_container",
			content: "Ctrl+Enter to add this card",
			position: {my: "left+15 center", at: "right center", of: $(pane).find("div.card_container")}
		})	
	
	var far_right = $("<div />")
		.css({
			display: "inline-block",
			verticalAlign: "top",
			width: "165px",
		})
	
	if(data.customer.website_orders && data.customer.website_orders.length)
	{
		$(far_right)
			.append(
				$(Mustache.render($("#website_orders").html(), data.customer))
					/*.tooltip({
						items: "div.roundbox",
						content: "Checkout Orders"
					})*/ //Phil wanted these gone
			)
	}
	
	if(data.customer.credit_card_charges && data.customer.credit_card_charges.length)
	{
		$(far_right)
			.append(
				$(Mustache.render($("#credit_card_charges").html(), data.customer.credit_card_charges))
			 		/*.tooltip({
						items: "div.roundbox",
						content: "Credit Card Charges",
					})*/	
			)
	}
	
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
		
		if(data.customer.customer.address.vip != 0)
		{
			$(Mustache.render(window.templates.star, data.customer.stats))
				.appendTo(far_right)
		}
		
		custostat = Mustache.render($("#stats").html(), data.customer.stats)
		
		
		
		$(far_right)
			.append(custostat)
	}
	
	if(data.customer.pay_and_hold_due_dates && data.customer.pay_and_hold_due_dates.length)
	{
		$(far_right)
			.append(Mustache.render($("#ph_due_dates").html(), data.customer))
	}
	
	if(data.customer.quote_requests && data.customer.quote_requests.length)
	{
		$(far_right)
			.append(Mustache.render(window.templates.quote_requests, data.customer))
	}
	
	/*
		Consignor Information
	*/
	if(data.customer.consignor.length)
	{
		$(far_right)
			.append(Mustache.render($("#consignor_box").html(), data.customer.consignor))
	}
	
	/*
		Expert Contact Information
	*/
	if(data.customer.expert.length)
	{
		$(far_right)
			.append(Mustache.render($("#expert_box").html(), data.customer.expert))
	}
	
	if(data.customer.similar_names && data.customer.similar_names.length)
	{
		$(far_right)
			.append(Mustache.render($("#similar_names").html(), data.customer.similar_names))
	}
	
	if(data.customer.shared_accounts && data.customer.shared_accounts.length)
	{
		$(far_right)
			.append(Mustache.render($("#shared_accounts").html(), data.customer.shared_accounts))
	}
	
	if(data.mail_to && data.mail_to.length)
	{
		$(far_right)
			.append(
				$(Mustache.render($("#mail_to").html(), data.mail_to))
			)
	}
	
	$(pane)
		.append(far_right)
	
	/*
		Items list
	*/
	if("unpaid" in data && data.unpaid.length)
	{
		$(pane)
			.append(
				$("<div />")
					.append(lowest_lot_numbers(data.customer.lowest_lot_numbers))
					.append(
						$("<div class='roundbox' style='display: inline-block; padding: 10px;' id='customer_pane_total' />")
					)
					.append(unselect_all(data))
					.append(items_count(data))
					
					.append(
						$(create_items_table(data))
							.attr("id", "customer_pane_items")
					)
			)
	}
	else
	{
		$(pane)
			.append(
				$("<div />")
					.attr("id", "no_outstanding_items")
					.html("No Outstanding Items")
					.css({
						marginBottom: "15px",
					})
			)
	}
	
	return pane
}


/*
function print_phone_order_form()
{
	//TODO: Validation
	
	if($("table.items tr.selected").length == 0)
	{
		$("table.items tr")
			.addClass("selected")
			
		update_new_invoice()
	}
	
	$(Mustache.render($("#phone_orders_prompt").html(), {}))
		.dialog({
			title: "Print an Order",
			height: 200,
			width: 400,
			position: { my: "left top", at: "right top", of: $("#newinvoice") },
			modal: true,
			open: function(event){
			console.debug(this)
				$(this).next().find("button").focus()
			},
			buttons: {
				"Print Order" : function(){
					print_phone_order(this)
				}
			},
		})
}
*/



function print_phone_order(save)
{
	if($("#items_table tr.selected").length == 0)
	{
		$("#items_table tr")
			.addClass("selected")
			
		update_new_invoice()
	}
	
	var invoice = $("#newinvoice")
		.find(":input")
		.createValueArray()
		
	invoice.last_four = $("#newinvoice [name=cc_which_one] option:selected").data("last_four")	
	
	total = 0
	var items = []	
	
	$("#items_table tr.selected")
		.each(function(){
			var data = $(this).data()
			delete data.uiTooltip
			items.push(data)
			total += parseFloat($(this).data("price"))
		})
		
		
	
	invoice['grand_total'] = (total + parseFloat(invoice.shipping_charged)).toFixed(2)
	invoice['subtotal'] = total.toFixed(2)
	invoice['shipping_charged'] = parseFloat(invoice.shipping_charged).toFixed(2)
		
	/*
		Split up the items by auction
	*/
	var items_grouped = [], item_groups = [], i = 0, last_prefix = false
	
	for(x in items)
	{
		var prefix = items[x]['ebay_title'].match(/^([0-9][a-z])[0-9]{3,4} /)
		
		if(prefix)
			prefix = prefix[1]
		else
			prefix = "other"
		
		if(!last_prefix || last_prefix != prefix)
		{
			if(prefix == "other")
				var index = 0
			else
				var index = ++i
			
			if(!items_grouped[index])
				items_grouped[index] = {prefix:prefix, items:[]}
		}
		
		last_prefix = prefix
		
		items_grouped[index]['items'].push(items[x])
	}
	
	items_grouped = items_grouped.filter(function(n){return n;})
	
	
	var printout = {
		"invoice" : invoice,
		"items" : items_grouped,
		"address" : window.address,
		"timestamp" : new Date().toLocaleFormat(), //TODO: change this to whatever random-ass format they want this week.
		"who_printed" : window.user_name,
		"count" : items.length
	}
	
 	if(!printout.address.address.notes_for_invoice || printout.address.address.notes_for_invoice.trim() == "")
		printout.address.address.notes_for_invoice = "<span style='color: gray'>(blank)</span>"
	
	//TODO: The extra_info doesn't get printed. Make it the same thing is payment notes?
	
	var address_id = $("#newinvoice ul.ship_to_address li.selected").attr("value")
	switch(address_id)
	{
			case "primary":
				printout.printed_address = window.data.customer.customer.formatted
				printout.jumbo_note = ""
				break;
			
			case "billing":
				printout.printed_address = window.data.customer.billing_address.formatted
				printout.jumbo_note = "Alternate\nAddress"
				break;
			
			default:
				for(x in window.data.customer.other_addresses)
				{
					if(window.data.customer.other_addresses[x].address_id == address_id)
					{
						printout.printed_address = window.data.customer.other_addresses[x].formatted
						printout.jumbo_note = "Alternate\nAddress"
						break;
					}
				}
				break;
	}
		
	var w = window.open()
	
	w.document.open()
	
	w.document.write(Mustache.render($("#printout").html(), printout))
	
	w.document.close()
	
	w.document.title = "Order Printout for "+printout.address.address.name
	
	if(save)
	{
		w.print()
		
		var printout = JSON.stringify(printout)
		
		$.ajax({
			url: "update.php",
			type: "post",
			dataType: "json",
			data: {
				printout : {
					printout: w.document.documentElement.outerHTML,
					email: window.address.address.email,
					customer_id: window.customer_id,
					who: window.user_id,
					ts: invoice.date_of_invoice,
					data: printout,
				},
				user_id: window.user_id
			},
			success: [ajax_result_handler],
			error: [ajax_error_handler]
		})
	}
}


function newinvoice(data)
{
	var cards = data.customer.cards
	
	var invoice = $(Mustache.render($("#invoice_new").html(), {
		invoice_number : data.next_invoice_number,
		date_of_invoice : generate_date(),
		shipping_charged : 0,
	}))
	
	$(invoice)
		.find("textarea[name=alternate_address], textarea[name=extra_info]")
		.on("input", function(event){
			$(this)
				.css("height", $(this).height() + $(this).get(0).scrollTopMax)
		})
	
	$(invoice)
		.find("img.print")
			.css({
				cursor: "pointer"
			})
			.click(function(){
				print_phone_order(true)
			})
			
			
	$(invoice)
		.find("img.view")
			.css({
				cursor: "pointer"
			})
			.click(function(){
				print_phone_order(false)
			})
	
	$(invoice)
		.attr("id", "newinvoice")
		.find("[name=shipping_charged]")
		.change(function(){
			update_new_invoice()
		})
	
	$(invoice)
			.find("[name=cc_which_one]")
			.change(function(event){
				$(this)
					.parents("div.invoice")
					.find("select[name=payment_method]")
					.val(
						$(this).find("option:selected").data("type_of_cc")
					)
			})
	
	if(window.data.customer.consignor)
	{
		for(x in window.data.customer.consignor)
		{
			$("select[name=payment_method]", invoice)
				.append(
					$("<option />")
						.text("Proceeds ("+window.data.customer.consignor[x].ConsignorName+")")
				)
			
		}
	}
	
		
	
	 $(invoice)
	 	.find("[name=cc_which_one]")
		.append("<option value='' _data-type_of_cc='' style='color: gray'>(none)</option>")
	
	for(x in cards)
	{
		if(cards[x].expired)
			continue;
		
		$(invoice)
			.find("[name=cc_which_one]")
			.append(card_option(cards[x]))
	}
	
	if(cards.length > 3)
	{
		$(invoice)
			.find("[name=cc_which_one]")
			.attr("size", cards.length)	
	}
	
	
	ship_to_address = $(invoice)
		.find("ul.ship_to_address")
		
	/*bill_to_address = $(invoice)
		.find("ul.bill_to_address")*/
	
	
	$(ship_to_address)
		.click(function(event){	
			if($(event.target).is("li"))
				element = event.target
			
			if($(event.target).parents("li").length)
				element = $(event.target).parents("li").get(0)
		
			$(element)
				.siblings()
				.removeClass("selected")
				
			$(element)
				.addClass("selected")
		})
	
	
	/*$(bill_to_address)
		.click(function(event){			
			if($(event.target).is("li"))
				element = event.target
			
			if($(event.target).parents("li").length)
				element = $(event.target).parents("li").get(0)
				
			$(element)
				.siblings()
				.removeClass("selected")
		
			$(element)
				.addClass("selected")
		})*/
		
	
	$(ship_to_address)
		.append(
			$("<li class='selected'>Shipping <small>Primary</small></li>")
				.attr("value", "primary")
				.attr("title", data.customer.customer.formatted)
		)
	
	if(data.customer.billing_address)
	{
		$(ship_to_address)
			.append(
				$("<li>Billing <small>&nbsp;</small></li>")
					.attr("value", "billing")
					.attr("title", data.customer.billing_address.formatted)
			)
		
		/*$(bill_to_address)
			.append(
				$("<li class='selected'>Billing <small>&nbsp;</small></li>")
					.attr("value", "primary")
					.attr("title", data.customer.billing_address.formatted)
			)*/
			
		/*$(bill_to_address)
			.append(
				$("<li>Shipping <small>&nbsp;</small></li>")
					.attr("value", "shipping")
					.attr("title", data.customer.customer.formatted)
			)*/
	}
	else
	{
		/*$(bill_to_address)
			.append(
				$("<li class='selected'>Shipping <small>&nbsp;</small></li>")
					.attr("value", "shipping")
					.attr("title", data.customer.customer.formatted)
			)*/
	}
	
	
		
		
	
	if(data.customer.other_addresses)
	{
		for(x in data.customer.other_addresses)
		{
			var li = $("<li />")
				.text(data.customer.other_addresses[x].address_label)
				.attr("value", data.customer.other_addresses[x].address_id)
				.attr("title", data.customer.other_addresses[x].formatted)
				
			if(data.customer.other_addresses[x].address_label2)
			{
				$(li)
					.append(
						$("<small />")
							.text(data.customer.other_addresses[x].address_label2)
					)
			}
			else
			{
				$(li)
					.append("<small>&nbsp;</small>")
			}
			
			$(ship_to_address)
				.append(
					$(li).clone()
				)
					
			/*$(bill_to_address)
				.append(
					$(li).clone()
				)*/
		}
	}
	
	$(ship_to_address)
		.find("li")
		.tooltip({
			content: function(){
				return $("<small />").html($(this).attr("title").replace(/\n/g, "<br />"))
			}
		})
	
	/*$(bill_to_address)
		.find("li")
		.tooltip({
			content: function(){			
				return $("<small />").html($(this).attr("title").replace(/\n/g, "<br />"))
			}
		})*/
	
	if((window.data.customer.billing_address && window.data.customer.billing_address.billing_address.bill_state == "MO") ||
			(window.data.customer.customer.address.ship_state == "MO"))
	{
		$("#invoice_warning", invoice)
			.html("<b style='color: tomato; font-size: 9pt'>Missouri Customer</b>")
	}
	
	return invoice
}


function editable_invoice(data)
{
	var cards = window.data.customer.cards
	
	var invoice = $(Mustache.render($("#editable_invoice").html(), data))
	
	$(invoice)
		.find("textarea[name=alternate_address], textarea[name=extra_info]")
		.on("input", function(event){
			$(this)
				.css("height", $(this).height() + $(this).get(0).scrollTopMax)
		})
	
	$(invoice)
		.attr("id", "newinvoice")
		.find("[name=shipping_charged]")
		.change(function(){
			//update_new_invoice()
		})
	
	$(invoice)
		.find("[name=cc_which_one]")
		.change(function(event){
			$(this)
				.parents("div.invoice")
				.find("select[name=payment_method]")
				.val(
					$(this).find("option:selected").data("type_of_cc")
				)
		})
	
	$(invoice)
	 	.find("[name=cc_which_one]")
		.append("<option value='' _data-type_of_cc='' style='color: gray'>(none)</option>")
	
	for(x in cards)
	{
		if(cards[x].expired)
			continue;
		
		var option = card_option(cards[x])
		
		if(data.cc_which_one && cards[x].cc_num == data.cc_which_one)
		{
			$(option)
				.prop("selected", true)
		}
		
		$(invoice)
			.find("[name=cc_which_one]")
			.append(option)
	}
	
	if(cards.length > 3)
	{
		$(invoice)
			.find("[name=cc_which_one]")
			.attr("size", cards.length)
	}
	
	if(window.data.customer.consignor)
	{
		for(x in window.data.customer.consignor)
		{
			$("select[name=payment_method]", invoice)
				.append(
					$("<option />")
						.text("Proceeds ("+window.data.customer.consignor[x].ConsignorName+")")
				)
			
		}
	}
	
	$(invoice)
		.tooltip({
			items: invoice,
			content: "Ctrl+Enter to update this invoice. Escape to cancel.",
			position: {my: "left+15 bottom", at: "right bottom", of: invoice},
		})
	
		
	
	ship_to_address = $(invoice)
		.find("ul.ship_to_address")
		
	/*bill_to_address = $(invoice)
		.find("ul.bill_to_address")*/


	$(ship_to_address)
		.click(function(event){	
			if($(event.target).is("li"))
				element = event.target
			
			if($(event.target).parents("li").length)
				element = $(event.target).parents("li").get(0)
		
			$(element)
				.siblings()
				.removeClass("selected")
				
			$(element)
				.addClass("selected")
		})


	/*$(bill_to_address)
		.click(function(event){			
			if($(event.target).is("li"))
				element = event.target
			
			if($(event.target).parents("li").length)
				element = $(event.target).parents("li").get(0)
				
			$(element)
				.siblings()
				.removeClass("selected")
		
			$(element)
				.addClass("selected")
		})*/
	

	$(ship_to_address)
		.append(
			$("<li class='selected'>Shipping <small>Primary</small></li>")
				.attr("value", "primary")
				.attr("title", window.data.customer.customer.formatted)
		)

	if(window.data.customer.billing_address)
	{
		$(ship_to_address)
			.append(
				$("<li>Billing <small>&nbsp;</small></li>")
					.attr("value", "billing")
					.attr("title", window.data.customer.billing_address.formatted)
			)
		
		/*$(bill_to_address)
			.append(
				$("<li class='selected'>Billing <small>&nbsp;</small></li>")
					.attr("value", "primary")
					.attr("title", window.data.customer.billing_address.formatted)
			)*/
			
		//$(bill_to_address)
		//	.append(
		//		$("<li>Shipping <small>&nbsp;</small></li>")
		//			.attr("value", "shipping")
		//			.attr("title", data.customer.customer.formatted)
		//	)
	}
	else
	{
		/*$(bill_to_address)
			.append(
				$("<li class='selected'>Shipping <small>&nbsp;</small></li>")
					.attr("value", "shipping")
					.attr("title", window.data.customer.customer.formatted)
			)*/
	}


		
		

	if(window.data.customer.other_addresses)
	{
		for(x in window.data.customer.other_addresses)
		{
			var li = $("<li />")
				.text(window.data.customer.other_addresses[x].address_label)
				.attr("value", window.data.customer.other_addresses[x].address_id)
				.attr("title", window.data.customer.other_addresses[x].formatted)
				
			if(window.data.customer.other_addresses[x].address_label2)
			{
				$(li)
					.append(
						$("<small />")
							.text(window.data.customer.other_addresses[x].address_label2)
					)
			}
			else
			{
				$(li)
					.append("<small>&nbsp;</small>")
			}
			
			$(ship_to_address)
				.append(
					$(li).clone()
				)
					
			/*$(bill_to_address)
				.append(
					$(li).clone()
				)*/
		}
	}

	$(ship_to_address)
		.find("li")
		.tooltip({
			content: function(){
				return $("<small />").html($(this).attr("title").replace(/\n/g, "<br />"))
			}
		})

	/*$(bill_to_address)
		.find("li")
		.tooltip({
			content: function(){			
				return $("<small />").html($(this).attr("title").replace(/\n/g, "<br />"))
			}
		})*/
	
	if((window.data.customer.billing_address && window.data.customer.billing_address.billing_address.bill_state == "MO") ||
			(window.data.customer.customer.address.ship_state == "MO"))
	{
		$("#invoice_warning", invoice)
			.html("<b style='color: tomato; font-size: 9pt'>Missouri Customer</b>")
	}
	
	return invoice
}


function card_option(card)
{
	if(card.cc_order_to_use)
		var order_to_use = "#"+card.cc_order_to_use
	else
		var order_to_use = "&nbsp;&nbsp;"
	
	return $("<option />")
		.data("last_four", card.last_four)
		.data("type_of_cc", card.type_of_cc)
		.val(card.cc_id)
		.html(card.last_four + " &nbsp;"+order_to_use+"<img style='height: 12px;' src='graphics/"+card.icon + "' />")
}


function generate_date()
{
	d = new Date()
	
	return d.getFullYear() + "-" + ("0" + (d.getMonth()+1)).slice(-2) + "-" + 
		("0" + d.getDate()).slice(-2) + " " + ("0" + d.getHours()).slice(-2) + ":" + 
		("0" + d.getMinutes()).slice(-2) + ":" + ("0" + d.getSeconds()).slice(-2);
}


function format_card_number(num)
{
	num = num.replace(/[^0-9]/g, "")
	
	out = ""
	
	if(num.slice(0,1) == 3) //American Express
	{
		return (num.slice(0,4)+" "+num.slice(4,10)+" "+num.slice(10)).trim()
	}
	else
	{
		return (num.slice(0,4)+" "+num.slice(4,8)+" "+num.slice(8,12)+" "+num.slice(12)).trim()
	}
}


function newcard()
{
	var newcard = $(Mustache.render($("#credit_card_new").html()))
	
	$(newcard)
		.find("input.expiration")
		.on("input", function(){
			if(!$(this).val().match(/^(0[0-9]|1[0-2])[1-9][0-9]$/) && $(this).val().length)
				$(this).css("borderColor", "tomato")
			else
				$(this).css("borderColor", "")
		})
	
	$(newcard)
		.attr("id", "newcard")
		.find("input.number")
		.keyup(function() {

		  var ss, se, obj;
		  obj = $(this);
		  ss = obj[0].selectionStart;
		  se = obj[0].selectionEnd;

		  var curr = obj.val();

		  var foo = $(this).val().split(" ").join(""); // remove hyphens
		  if (foo.length > 0) {
		     foo = format_card_number(foo)
		  }
	
          if(ss == curr.length && ss == se)
		  {
			ss = foo.length
			se = foo.length
		  }

		  if (curr != foo){
		    $(this).val(foo);
		    obj[0].selectionStart = ss;
		    obj[0].selectionEnd = se;
		  }

		})
		.on("input", function(event){
			if(this.timeout)
				clearTimeout(this.timeout)
			
			this.timeout = setTimeout(function(){
			
				var type = card_type($(this).val())
				
				if(type)
				{
					var icon = {
						"Visa" : "visa.png",
						"MasterCard" : "mastercard.png",
						"Discover Card" : "discover.png",
						"American Express" : "amex.png",
					}
					
					$(this)
						.siblings("img")
						.attr("src", "graphics/"+icon[type])
					
					$(this)
						.siblings("input[name=type_of_cc]")
						.val(type)
				}
				else
				{
					$(this).siblings("img")
						.attr("src", "graphics/empty.png")
					
					$(this)
						.siblings("input[name=type_of_cc]")
						.val("")
				}
				
				
				if(validate_luhn($(this).val()) || $(this).val().length == 0)
				{					
					$(this)
						.css({
							borderColor: "",
						})
				}
				else
				{
					$(this)
						.css({
							borderColor: "tomato",
						})
				}
			}.bind(this), 200)
		})
	
	return newcard
}



function update_card(card)
{
	var cc_data = {
		"cc_id" : $(card).data("cc_id"),
		"cc_exp" : $(card).find("input[name=cc_exp]").val(),
		"cc_name" : $(card).find("input[name=cc_name]").val()
	}
	
	$.ajaxq("update", {
		url: "update.php",
		type: "post",
		dataType: "json",
		data: {
			cc_update: 1,
			cc_data: cc_data,
			user_id: window.user_id,
		},
		success: [ajax_result_handler, function(data){
			$(card)
				.remove()
				
			if("card" in data)
			{
				var chars = data.card.cc_num.split("")
				data.card.cc_num = ""
				while(chars.length)
				{
					for(var x = 0; x < 4 && chars.length; x++)
						data.card.cc_num += chars.shift()
					
					data.card.cc_num += " "
				}
				
				data.card.cc_num = data.card.cc_num.trim()
				
				$("div.newcard:first")
					.parents(".card_container")
					.replaceWith(generate_card(data.card, false))
				
				$("div.cards:first")
					.prepend(newcard())
			}
		}],
		error: [empty_main, ajax_error_handler],
	})
}


function update_card_order(card)
{
	var cc_data = {
		"cc_id" : $(card).data("cc_id"),
		"cc_order_to_use" : $(card).find("input[name=cc_order_to_use]").val()
	}
	
	$.ajaxq("update", {
		url: "update.php",
		type: "post",
		dataType: "json",
		data: {
			cc_update: 1,
			cc_data: cc_data,
			user_id: window.user_id,
		},
		success: [ajax_result_handler, function(data){
			$(card)
				.remove()
				
			if("card" in data)
			{
				var chars = data.card.cc_num.split("")
				data.card.cc_num = ""
				while(chars.length)
				{
					for(var x = 0; x < 4 && chars.length; x++)
						data.card.cc_num += chars.shift()
					
					data.card.cc_num += " "
				}
				
				data.card.cc_num = data.card.cc_num.trim()
				
				$("div.newcard:first")
					.parents(".card_container")
					.replaceWith(generate_card(data.card, false))
				
				$("div.cards:first")
					.prepend(newcard())
			}
		}],
		error: [empty_main, ajax_error_handler],
	})
}


function add_card(object)
{
	var cc_data = $(object)
		.find(":input")
		.createValueArray()
	
	cc_data.cc_num = cc_data.cc_num.replace(/[^0-9]/g, "")
	
	cc_data["customer_id"] = window.customer_id
	
	$.ajaxq("update", {
		url: "update.php",
		type: "post",
		dataType: "json",
		data: {
			cc_data: cc_data,
			user_id: window.user_id,
		},
		success: [ajax_result_handler, update_card_result],
		error: [empty_main, ajax_error_handler],
	})
}

function update_card_result(data)
{
	if("card" in data)
	{
		var chars = data.card.cc_num.split("")
		data.card.cc_num = ""
		while(chars.length)
		{
			for(var x = 0; x < 4 && chars.length; x++)
				data.card.cc_num += chars.shift()
			
			data.card.cc_num += " "
		}
		
		data.card.cc_num = data.card.cc_num.trim()
		
		$("div.cards div.card_container")
			.filter(function(){
				return $(this).data("cc_id") == data.card.cc_id
			})
			.remove()
		
		$("div.newcard")
			.parents(".card_container")
			.replaceWith(generate_card(data.card, true))
		
		$("div.cards")
			.each(function(){
				$(this)
					.prepend(newcard())
			})
			
		//TODO: Select the option we just added
		$("#newinvoice [name=cc_which_one]")
			.append(card_option(data.card))
			.find("option:last")
			.prop("selected", true)
			.trigger("change")
	}
}


function paypal_payments(payments)
{
	if(payments.length == 0)
		return ""
	
	div = $("<div />")
		.css({
			overflow: "auto",
			maxHeight: "60px",
		})
		.attr("id", "paypal_payments")
		.addClass("roundbox")
	
	table = $("<table />")
		.append("<caption>PayPal Payments Received</caption>")
		.addClass("paypal")
		.css({
			
		})
		.appendTo(div)
	
	for(x in payments)
	{
		$(table).append(create_payment_row(payments[x]))
	}
	
	return div
}


function paypal_payments_received(payments)
{
	if(payments.length == 0)
		return ""
	
	div = $("<div />")
		.css({
			overflow: "auto",
			maxHeight: "60px",
		})
		.attr("id", "paypal_payments_received")
		.addClass("roundbox")
	
	table = $("<table />")
		.append("<caption>PayPal Payments Sent</caption>")
		.addClass("paypal")
		.css({
			
		})
		.appendTo(div)
	
	for(x in payments)
	{
		$(table).append(create_payment_row(payments[x]))
	}
	
	return div
}



function credit_cards(cards)
{
	var list = []
	for(x in cards)
	{
		list.push(generate_card(cards[x]))
	}
	return list
}


function generate_card(data, display_full_number)
{	
	data2 = jQuery.extend(true, {}, data)
	
	if(!data2.last_four)
		data2.last_four = data2.cc_num.slice(-4)
	
	if(display_full_number)
		data2.last_four = data2.cc_num
	
	var hue = (data2.cc_num.substr(data2.cc_num.length-8) % 30) / 30
		
	if(data2.cc_order_to_use)
		data2.preference = "preferred"
	
	if(data2.cc_order_to_use > 1)	
		data2.preference += " (#"+data2.cc_order_to_use+")"			
	
	data2.cc_exp_orig = data2.cc_exp
	data2.cc_exp = data2.cc_exp.substr(0,2) + "/" + data2.cc_exp.substr(2,2)
	
	card = $(Mustache.render($("#credit_card").html(), data2))
	
	$(card)
		.find("div.card")
		.css("background-color", hsv_to_rgb(hue, .15, .67))
		
	if(data2.expired)
	{
		$(card)
			.find("div.card")
			.css("background-color", "#222")
	}
	
	return card
}



function parse_block_records(data)
{
	var url = "/website_tools/user_account_admin.php?user="+encodeURIComponent(data.customer.address.email)	

	data = data.block_records
	
	if(data.length == 0)
		return ""
	
	var ul = $("<ul />")
		.append("<caption>Block Records</caption>")
		.attr("id", "block_records")
		.addClass("roundbox")
		.css({
			
		})
	
	for(x in data)
	{		
		if(data[x].reason)
		{
			var reasons = data[x].reason.split(";").reverse() //Reverse for newest first
			
			for(y in reasons)
			{
				reasons[y] = Mustache.escape(reasons[y]).replace(/[A-Z!]{2,}/g, "<b>$&</b>")
			}
			
			data[x].reasons = reasons
		}
			
		
		
		var li = $(Mustache.render($("#block_record").html(), data[x]))
		
		if(data[x].how_blocked == "Unblocked" || data[x].how_blocked == "Never Block")
		{
			$(li)
				.find(".how_blocked")
				.css({
					color: "darkgreen",
					cursor: "pointer",
				})
				.click(function(){
					window.open(url)
				})
		}
		else
		{
			$(li)
				.find(".how_blocked")
				.css({
					color: "tomato",
					cursor: "pointer",
					fontSize: "16pt",
				})
				.click(function(){
					window.open(url)
				})
		}
		
		$(ul)
			.append(li)
	}
	
	return ul
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

	
function focus_tab(tab)
{
	$("#tabs li").removeClass("focused")
	$(tab).addClass("focused")
	
	var params = {tab: $(tab).attr("id")}
	
	if(window.data && window.data.customer)
		params['customer_id'] = window.data.customer.customer.address.customer_id
	
	window.expected_hash = jQuery.param(params)
	window.location.hash = window.expected_hash
	
	activate_pane($(tab).attr("tab"))
}

function activate_pane(pane)
{	
	panes[pane].show()
}


function parse_address(address, callback)
{
	callback(
		dumb_parse_address(address)
	)
}


function dumb_parse_address(text)
{
	var customer = {ship_country: "US"}
	
	text = text.replace(/>/g, "")
	
	var lines = text.split("\n").map(String.trim).filter(function(val){return val.length})
	
	if(lines.length == 0)
		return customer
	
	
	//Look for phone number
	for(x in lines)
	{
		if(matches = lines[x].match(/^phone:(.*)$/i))
		{
			customer['phone_number_1'] = matches[1].trim()
			lines.splice(x, 1)
		}
		else if(matches = lines[x].match(/^([^a-z]{7,})$/i))
		{
			customer['phone_number_1'] = matches[1].trim()
			lines.splice(x, 1)
		}
	}
	
	if(lines.length == 0)
		return customer
		
	
	//Look for ATTN or C/O
	for(x in lines)
	{
		if(lines[x].match(/^(ATTN|ATTENTION|C\/O)/i))
		{
			customer['ship_attention_line'] = lines[x]
			lines.splice(x, 1)
			break;
		}
	}
	
	if(lines.length == 0)
		return customer
	
	
	//Look for country
	var countries = {"AFGHANISTAN":"AF","ALAND ISLANDS":"AX","ALBANIA":"AL","ALGERIA":"DZ","AMERICAN SAMOA":"AS","ANDORRA":"AD","ANGOLA":"AO","ANGUILLA":"AI","ANTARCTICA":"AQ","ANTIGUA AND BARBUDA":"AG","ARGENTINA":"AR","ARMENIA":"AM","ARUBA":"AW","AUSTRALIA":"AU","AUSTRIA":"AT","AZERBAIJAN":"AZ","BAHAMAS":"BS","BAHRAIN":"BH","BANGLADESH":"BD","BARBADOS":"BB","BELARUS":"BY","BELGIUM":"BE","BELIZE":"BZ","BENIN":"BJ","BERMUDA":"BM","BHUTAN":"BT","BOLIVIA":"BO","BOSNIA AND HERZEGOVINA":"BA","BOTSWANA":"BW","BOUVET ISLAND":"BV","BRAZIL":"BR","BRITISH INDIAN OCEAN TERRITORY":"IO","BRUNEI DARUSSALAM":"BN","BULGARIA":"BG","BURKINA FASO":"BF","BURUNDI":"BI","CAMBODIA":"KH","CAMEROON":"CM","CANADA":"CA","CAPE VERDE":"CV","CAYMAN ISLANDS":"KY","CENTRAL AFRICAN REPUBLIC":"CF","CHAD":"TD","CHILE":"CL","CHINA":"CN","CHRISTMAS ISLAND":"CX","COCOS ISLANDS":"CC","COLOMBIA":"CO","COMOROS":"KM","COOK ISLANDS":"CK","COSTA RICA":"CR","COTE D'IVOIRE":"CI","CROATIA":"HR","CUBA":"CU","CURACAO":"CW","CYPRUS":"CY","CZECH REPUBLIC":"CZ","DENMARK":"DK","DJIBOUTI":"DJ","DOMINICA":"DM","DOMINICAN REPUBLIC":"DO","EAST TIMOR":"TP","ECUADOR":"EC","EGYPT":"EG","EL SALVADOR":"SV","ENGLAND	ENGL":"ND","EQUATORIAL GUINEA":"GQ","ERITREA":"ER","ESTONIA":"EE","ETHIOPIA":"ET","FALKLAND ISLANDS":"FK","FAROE ISLANDS":"FO","FIJI":"FJ","FINLAND":"FI","FRANCE":"FR","FRANCE, METROPOLITAN":"FX","FRENCH GUIANA":"GF","FRENCH POLYNESIA":"PF","FRENCH SOUTHERN TERRITORIES":"TF","GABON":"GA","GAMBIA":"GM","GEORGIA":"GE","GERMANY":"DE","GHANA":"GH","GIBRALTAR":"GI","GREAT BRITAIN AND NORTHERN IRELAND":"GB","GREECE":"GR","GREENLAND":"GL","GRENADA":"GD","GUADELOUPE":"GP","GUAM":"GU","GUATEMALA":"GT","GUERNSEY":"GG","GUINEA":"GN","GUINEA-BISSAU":"GW","GUYANA":"GY","HAITI":"HT","HEARD AND MCDONALD ISLANDS":"HM","HONDURAS":"HN","HONG KONG":"HK","HUNGARY":"HU","ICELAND":"IS","INDIA":"IN","INDONESIA":"ID","IRAN":"IR","IRAQ":"IQ","IRELAND":"IE","ISLE OF MAN":"IM","ISRAEL":"IL","ITALY":"IT","JAMAICA":"JM","JAPAN":"JP","JERSEY":"JE","JORDAN":"JO","KAZAKHSTAN":"KZ","KENYA":"KE","KIRIBATI":"KI","KUWAIT":"KW","KYRGYZSTAN":"KG","LAOS":"LA","LATVIA":"LV","LEBANON":"LB","LESOTHO":"LS","LIBERIA":"LR","LIBYA":"LY","LIECHTENSTEIN":"LI","LITHUANIA":"LT","LUXEMBOURG":"LU","MACAO":"MO","MACEDONIA":"MK","MADAGASCAR":"MG","MALAWI":"MW","MALAYSIA":"MY","MALDIVES":"MV","MALI":"ML","MALTA":"MT","MARSHALL ISLANDS":"MH","MARTINIQUE":"MQ","MAURITANIA":"MR","MAURITIUS":"MU","MAYOTTE":"YT","MEXICO":"MX","MICRONESIA":"FM","MOLDOVA":"MD","MONACO":"MC","MONGOLIA":"MN","MONTENEGRO":"ME","MONTSERRAT":"MS","MOROCCO":"MA","MOZAMBIQUE":"MZ","MYANMAR":"MM","NAMIBIA":"NA","NAURU":"NR","NEPAL":"NP","NETHERLANDS":"NL","NEUTRAL ZONE":"NT","NEW CALEDONIA":"NC","NEW ZEALAND":"NZ","NICARAGUA":"NI","NIGER":"NE","NIGERIA":"NG","NIUE":"NU","NORFOLK ISLAND":"NF","NORTHERN IRELAND":"ND","NORTHERN MARIANA ISLANDS":"MP","NORWAY":"NO","OMAN":"OM","PAKISTAN":"PK","PALAU":"PW","PALESTINIAN TERRITORY, OCCUPIED":"PS","PANAMA":"PA","PAPUA NEW GUINEA":"PG","PARAGUAY":"PY","PERU":"PE","PHILIPPINES":"PH","PITCAIRN":"PN","POLAND":"PL","PORTUGAL":"PT","PUERTO RICO":"PR","QATAR":"QA","REUNION":"RE","ROMANIA":"RO","RUSSIAN FEDERATION":"RU","RWANDA":"RW","S. GEORGIA AND S. SANDWICH ISLANDS":"GS","SAINT BARTHELEMY":"BL","SAINT KITTS AND NEVIS":"KN","SAINT LUCIA":"LC","SAINT MARTIN":"MF","SAINT VINCENT AND THE GRENADINES":"VC","SAMOA":"WS","SAN MARINO":"SM","SAO TOME AND PRINCIPE":"ST","SAUDI ARABIA":"SA","SCOTLAND	SCOTL":"ND","SENEGAL":"SN","SERBIA, REPUBLIC OF":"RS","SEYCHELLES":"SC","SIERRA LEONE":"SL","SINGAPORE":"SG","SLOVAK REPUBLIC":"SK","SLOVENIA":"SI","SOLOMON ISLANDS":"SB","SOMALIA":"SO","SOUTH AFRICA":"ZA","SPAIN":"ES","SRI LANKA":"LK","ST. HELENA":"SH","ST. PIERRE AND MIQUELON":"PM","SUDAN":"SD","SURINAME":"SR","SVALBARD AND JAN MAYEN ISLANDS":"SJ","SWAZILAND":"SZ","SWEDEN":"SE","SWITZERLAND":"CH","SYRIA":"SY","TAIWAN":"TW","TAJIKISTAN":"TJ","TANZANIA":"TZ","THAILAND":"TH","TIMOR-LESTE":"TL","TOGO":"TG","TOKELAU":"TK","TONGA":"TO","TRINIDAD AND TOBAGO":"TT","TUNISIA":"TN","TURKEY":"TR","TURKMENISTAN":"TM","TURKS AND CAICOS ISLANDS":"TC","TUVALU":"TV","UGANDA":"UG","UKRAINE":"UA","UNITED ARAB EMIRATES":"AE","UNITED STATES":"US","URUGUAY":"UY","US MINOR OUTLYING ISLANDS":"UM","UZBEKISTAN":"UZ","VANUATU":"VU","VATICAN CITY STATE":"VA","VENEZUELA":"VE","VIET NAM":"VN","WALES":"ES","WALLIS AND FUTUNA ISLANDS":"WF","WESTERN SAHARA":"EH","YEMEN":"YE","YUGOSLAVIA":"YU","ZAIRE":"ZR","ZAMBIA":"ZM","ZIMBABWE":"ZW"}
	for(x in lines)
	{
		if(countries[lines[x].toUpperCase()])
		{
			customer['ship_country'] = countries[lines[x].toUpperCase()]
			lines.splice(x, 1)
			break;
		}
	}
	
	if(lines.length == 0)
		return customer
	
	
	
	//Look for city, state, zip
	if(matches = lines[lines.length-1].match(/^(.+), +(.+) +([0-9-]{5,})$/))
	{
		customer['ship_city'] = matches[1]
		customer['ship_state'] = matches[2].toUpperCase() 
		
		var states = {"ALBERTA": "AB","APO": "AE","ALASKA": "AK","ALABAMA": "AL","FPO": "AP","ARMED FORCES": "PO","ARKANSAS": "AR","ARIZONA": "AZ","BRITISH COLUMBIA": "BC","CALIFORNIA": "CA","COLORADO": "CO","CONNECTICUT": "CT","DISTRICT OF COLUMBIA": "DC","DELAWARE": "DE","FLORIDA": "FL","ARMED FORCES PACIFIC	": "PO","GEORGIA": "GA","GUAM": "GU","HAWAII": "HI","IOWA": "IA","IDAHO": "ID","ILLINOIS": "IL","INDIANA": "IN","KANSAS": "KS","KENTUCKY": "KY","LOUISIANA": "LA","MASSACHUSETTS": "MA","MANITOBA": "MB","MARYLAND": "MD","MAINE": "ME","MICHIGAN": "MI","MINNESOTA": "MN","MISSOURI": "MO","MISSISSIPPI": "MS","MONTANA": "MT","NEW BRUNSWICK": "NB","NORTH CAROLINA": "NC","NORTH DAKOTA": "ND","NEBRASKA": "NE","NEWFOUNDLAND": "NF","NEW HAMPSHIRE": "NH","NEW JERSEY": "NJ","NEW MEXICO": "NM","NOVA SCOTIA": "NS","NORTHWEST TERRITORIES": "NT","NUNAVUT": "NU","NEVADA": "NV","NEW YORK": "NY","OHIO": "OH","OKLAHOMA": "OK","ONTARIO": "ON","OREGON": "OR","PENNSYLVANIA": "PA","PRINCE EDWARD ISLAND": "PE","PUERTO RICO": "PR","QUEBEC": "QC","RHODE ISLAND": "RI","SOUTH CAROLINA": "SC","SOUTH DAKOTA": "SD","SASKATCHEWAN": "SK","TENNESSEE": "TN","TEXAS": "TX","UTAH": "UT","VIRGINIA": "VA","VIRGIN ISLANDS": "VI","VERMONT": "VT","WASHINGTON": "WA","WISCONSIN": "WI","WEST VIRGINIA": "WV","WYOMING": "WY","YUKON TERRITORY": "YT"}
		
		if(states[customer['ship_state']])
			customer['ship_state'] = states[customer['ship_state']]
		
		customer['ship_zip'] = matches[3]
		
		lines.splice(lines.length-1, 1)
	}
	
	if(lines.length == 0)
		return customer
	
	
	//Look for name
	if(lines[0].match(/^[^0-9]+$/))
		customer['name'] = lines.shift()
	
	if(lines.length == 0)
		return customer
	
	
	//Get address lines
	customer['ship_address_line1'] = lines.shift()
	
	if(lines.length == 0)
		return customer
	
	customer['ship_address_line2'] = lines.shift()
	
	if(lines.length == 0)
		return customer
		
	
	//Put extra lines in notes
	customer['notes_for_invoice'] = "extra lines in address:\r\n"+lines.join("\r\n")
	
	return customer
}

function parse_google_address_components(components, phone, address)
{
	var types = {
		"street_number" : ["ship_address_line1", "long_name"],
		"route" : ["ship_address_line1", "long_name"],
		"neighborhood" : ["ship_city", "long_name"],
		"locality" : ["ship_city", "long_name"],
		"administrative_area_level_1" : ["ship_state", "long_name"],
		"country" : ["ship_country", "short_name"],
		"postal_code" : ["ship_zip", "long_name"],
		"subpremise" : ["ship_address_line2", "long_name"],		
	}
	
	var result = {
		ship_address_line1: "",
		ship_address_line2: "",
		ship_city: "",
		ship_state: "",
		ship_country: "",
		ship_zip: "",
		phone_number_1: phone
	}
	
	result.name = address.split("\n")[0].trim()
	
	for(x in components)
	{
		for(type in types)
		{
			if(components[x].types.indexOf(type) != -1)
			{
				if(types[type][0] == "ship_city")
					result[types[type][0]] += components[x][types[type][1]] + ", "
				else
					result[types[type][0]] += components[x][types[type][1]] + " "
				
				continue;
			}
		}
	}
	
	for(x in result)
	{
		result[x] = result[x].trim().replace(/,$/, "")
	}
	
	return result
}


function new_customer_pastebox()
{
	var unsaved = check_for_unsaved()
	if(unsaved.length && !confirm("You have unsaved changes ("+unsaved.join(",")+"). Continue without saving?"))
		return false;	
	
	new_customer()
	
	$("<div style='width: 200px'><label>Address</label><textarea name='address_pastebox' style='width: 100%; height: 150px;'></textarea></div>")
		.dialog({
			title: "New Customer",
			modal: true,
			resizable: false,
			buttons: {
				"Add Customer" : function(){
						parse_address($(this).find("textarea").val(), 
							function(address){
								new_customer(address)
							})
							
						$(this).dialog("close")
					},
			},
			position: {my: "left top", at: "left bottom", of: "#search"},
			open: function(event, ui){
				$(this).find("textarea").focus()
			},
			close: function(){
				$(".customers[name=name]").focus()
			}
		})
}

function new_customer(address, new_data)
{
	$("#tabs li").removeClass("focused")
	$("#customer_tab").addClass("focused")
	$("#search").typeahead("close").typeahead("val", "")
	
	clear_status()
	
	var data = {
		new_customer: true,
		customer: {
			aa_accounts: [],
			autoship: false,
			billing_address: false,
			block_records: [],
			cards: [],
			consignor: false,
			customer: {
				address: {
					customer_id: "",
					name: "",
					pay_and_hold: "0",
				},
				formatted: "",
			},
			customer_id: "new",
			expert: [],
			other_emails: [],
			pay_and_hold_due_dates: [],
			paypal_payments: [],
			pitascore: 0,
		},
		invoices: [],
		packages: [],
		unpaid: [],
	}
	
	if(new_data)
	{
		if(new_data.unpaid && new_data.unpaid.length)
			data.unpaid = new_data.unpaid
			
		if(new_data.similar_names)
			data.similar_names = new_data.similar_names
			
		if(new_data.aa_account)
			data.customer.aa_accounts.push(new_data.aa_account)
			
		if(new_data.block_records)
			data.customer.block_records = new_data.block_records
			
	}
	
	if(address)
	{
		jQuery.extend(data.customer.customer.address, address)
	}
	
	
	
	parse_customer(data)
	
	show_customer_pane()
	
	$(".customers[name=name]").focus()
}

function readwrite()
{
	search(window.customer_id)
}

function readonly()
{
	$(window.customer_pane)
		.find(":input:not([readonly])")
		.attr("readonly", true)
		.addClass("tempreadonly")
		
	$(window.items_pane)
		.find(":input:not([readonly])")
		.attr("readonly", true)
		.addClass("tempreadonly")
	
	/*$(window.packages_pane)
		.find(":input:not([readonly])")
		.attr("readonly", true)
		.addClass("tempreadonly")*/
}

var silly_messages = [
	"Be patient!",
	"I'm loading as fast as I can!",
	"Stop that!",
	"Stop pressing that dang button!",
	"Are you doing that on purpose?",
	"That's getting on my nerves.",
	"Ouch!",
	"I'm disappointed in you.",
	"You're mean.",
	"I'm going home.",
	"Sigh.",
]

function search(term, callback)
{
	if(term.toLowerCase() == "i want to go home")
	{
		$(document.body)
			.css({"background": "white", "textAlign" : "center"})
			.html("<h1 style='color: red; '>VERY ERROR!</h1><h2>Uh-oh.</h2><br /><h3>Looks like this is going to take, ehhhh let's see... about "+(18 - new Date().getHours())+" hours to fix.</h3>")
	}
	
	if($.ajaxq.isRunning("fetch2"))
	{
		$("#main").prepend(alert_box($("<span style='float: right; padding-right: 20px' />").text(silly_messages[$("#main div.alert").length])))
		return false
	}
	
	var unsaved = check_for_unsaved()
	if(unsaved.length && !confirm("You have unsaved changes ("+unsaved.join(",")+"). Continue?"))
		return false;
	
	$("#admin_settings_box").dialog("destroy")
		
	$(window.customer_pane).remove()
	$(window.packages_pane).remove()
	$(window.items_pane).remove()
	$(window.consignor_pane).remove()
	
	delete window.customer_pane
	delete window.packages_pane
	delete window.items_pane
	delete window.consignor_pane
	
	snapshot_inputs()
	
	throb()
	
	$.ajaxq.abort("fetch")
	
	$.ajaxq("fetch", {
		url: "search.php",
		type: "post",
		data: {
			term: term,
			user_id: window.user_id,
		},
		dataType: "json",
		success: [
			function(data){
				$("#root div.alert").remove()
				$("#close").css("visibility", "")
				
				if("customer" in data)
				{
					$("#tabs li").removeClass("focused")
					$("#customer_tab").addClass("focused")
					$("#search").typeahead("close").typeahead("val", "")
					$("#tabs").focus()
					
					clear_status()
					parse_customer(data)
					
					if(data.consignor)
					{
						focus_tab($("#consignor_tab"))
					}
					else if(data.tracking_id)
					{
						//show_packages_pane()/
						focus_tab($("#packages_tab"))
						
						$("#packages li#tracking"+data.tracking_id)
							.addClass("selected")
							
						$("#package_search").val(data.tracking_number)
							
						$("#packages li:not(.selected)").addClass("hidden")
						
						deuglify_tracking_numbers()
						
						get_package_contents(data.tracking_id)
					}
					else if(callback)
					{
						callback()
					}
					else
					{
						show_customer_pane()
					}
				}
				else if("new_account" in data)
				{
					new_customer(data.new_account, data)
					
					$("#root")
						.prepend(alert_box("No account found in invoicing, but we can create one now."))
				}
				
				if("record_lock" in data)
				{
					$("#root").prepend(alert_box(data.record_lock + " is using this account.\n" + 
						"Window will automatically reload when the account becomes available.", "lock"))
					
					window.record_lock = data.record_lock
					
					readonly()
				}
				else
				{
					delete window.record_lock
				}
				
				if("message" in data)
				{
					if("customer" in data)
					{
						status(data.message)
					}
					else
					{
						status("<img src='/includes/graphics/alert16.png' /> "+data.message)
					}
				}
				
				if("history" in data)
				{
					update_history_bar(data.history)
				}
				
				if("history2" in data)
				{
					update_history_bar2(data.history2)
				}
			}, ajax_result_handler],
		complete: unthrob,
		error: [empty_main, ajax_error_handler],
	})
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


function create_payment_row(data, color)
{
	used_fields = {
		"payment_date" 	: "When",
		"name" 			: "Name",
		"payer_email" 	: "Email",
		"mc_gross" 		: "Amt",
		"payment_status": "St",
		"txn_type"		: "Typ",
		"txn_id"		: "Txn id",
		"memo"			: "M",
	}
	
	tr = $("<tr />")
		.addClass("pp_payment")
		//.css("background", (color ? "rgb(230,230,230)" : "rgb(255,255,255)"))
		.attr("pp_id", data["id"])
		.attr("timestamp", data['timestamp'])
	
	/*$(tr).append(
		$("<td />").append(
			$("<img src='./paypal_notify/del.png' />")
				.css({"cursor": "pointer", "padding": "4px"})
				.attr("pp_id", data["id"])
				.click(function(){
					hide_paypal_payment($(this).attr("pp_id"))
					$(this).hide()
				})
		)
	)*/
	
	for(field in used_fields)
	{
		if(!(field in data))
		{
			$(tr).append($("<td>&nbsp;</td>"))
			continue;
		}
		
		if(field == "txn_type")
		{
			if(data['ebay'])
			{
				$(tr).append($("<td><img src='./paypal_notify/ebay.ico' title='Send Money' /></td>").css("padding", "0 10px 5px 0"))
			}
			else
			{
				switch(data[field])
				{
					case "send_money":
						$(tr).append($("<td><img src='./paypal_notify/cash.png' title='Send Money' /></td>").css("padding", "0 10px 5px 0"))
						break;
					case "cart":
						$(tr).append($("<td><img src='./paypal_notify/cart.png' title='Cart' /></td>").css("padding", "0 10px 5px 0"))
						break;
					case "virtual_terminal":
						$(tr).append($("<td><img src='./paypal_notify/cc.png' title='Credit Card Processor' /></td>").css("padding", "0 10px 5px 0"))
						break;
					case "new_case":
						$(tr).append($("<td><img src='./paypal_notify/gavel.png' title='New Case' /></td>").css("padding", "0 10px 5px 0"))
						break;
					default:
						if(data[field])
						{
							$(tr).append(
								$("<td></td>").html(
									data[field].replace("\n", "<br />")
								).css("padding", "0 10px 5px 0")
							)
						}
						else
						{
							$(tr)
								.append("<td />")
						}
						break;
				}
			}
		}
		else if(field == "payment_status")
		{
			switch(data[field])
			{
				case "Completed":
					$(tr).append($("<td><img src='./paypal_notify/check.png' title='Completed' /></td>").css("padding", "0 10px 5px 0"))
					break;
				case "Reversed":
					$(tr).append($("<td><img src='./paypal_notify/undo.png' title='Reversed' /></td>").css("padding", "0 10px 5px 0"))
					break;
				case "Pending":
					$(tr).append($("<td><img src='./paypal_notify/clock.png' title='Pending' /></td>").css("padding", "0 10px 5px 0"))
					break;
				default:
					$(tr).append($("<td></td>").html(
						data[field]
					).css("padding", "0 10px 5px 0"))
					break;
			}
		}
		else if(field == "memo")
		{
			$(tr).append(
				$("<td />").append(
					$("<img src='./paypal_notify/memo.png' />")
						.attr("title", data[field])
				)
			)
		}
		else if(field == "txn_id")
		{
			$(tr).append(
				$("<td></td>").html(
					"<a class='pretty_link' target='_blank' href='https://www.paypal.com/vst/id="+data[field]+"'>"+data[field]+"</a>"
				).css("padding", "0 10px 5px 0")
			)
		}
		else if(field == "payer_email")
		{
			var text = data[field].split("@").join("@<wbr />")
			$(tr)
				.append(
					$("<td></td>")
						.html("<a class='pretty_link' href='mailto:"+data[field]+"'>"+text+"</a>")
						.css({padding: "0 10px 5px 0"})
				)
		}
		else if(field == "name")
		{
			$(tr)
				.append(
					$("<td></td>")
						.append(
							$("<a />")
								.html(data[field])
								.css({
									padding: "0 10px 5px 0",
									cursor: "pointer"
								})
								.attr("href", "?customer_id="+encodeURIComponent(data['payer_email']))
								.click(function(event){
									if(event.button == 0)
									{
										search(data['payer_email'])
										event.preventDefault()
									}
								})
						)
						
				)
		}
		else if(field == "mc_gross")
		{
			$(tr).append(
				$("<td></td>").html(
					data[field]
				).css({padding: "0 10px 5px 0", textAlign: "right"})
			)
		}
		else
		{
			$(tr).append(
				$("<td></td>").html(
					data[field]
				).css("padding", "0 10px 5px 0")
			)
		}
	}
	
	return tr
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

function delete_item(row)
{
	//TODO: Maybe make this undoable
	
	if(!confirm("Delete '"+$(row).data("ebay_title")+"'?"))
		return false
	
	$.ajaxq("update", {
		url: "update.php",
		type: "post",
		dataType: "json",
		data: {
			delete_items: [$(row).data("autonumber")],
			user_id: window.user_id,
		},
		success: [ajax_result_handler, function(data){
			if("warning" in data)
			{
				alert(data.warning)
			}
			
			if("deleted_items" in data)
			{
				$(row)
					.addClass("deleted")
					.removeClass("selected")
					
				update_new_invoice()
				update_customer_pane_total()
			}
		}],
		error: [empty_main, ajax_error_handler],
	})
}

function delete_all_items()
{
	var items = $.makeArray($("#customer_pane_items img.delete_icon").parents("tr").map(function(){return $(this).data("autonumber")}))
	
	if(!confirm("You are about to delete all items with a red \"X\" ("+items.length+" items)"))
		return false
	
	$.ajaxq("update", {
		url: "update.php",
		type: "post",
		dataType: "json",
		data: {
			delete_items: items,
			user_id: window.user_id,
		},
		success: [ajax_result_handler, function(data){
			if("warning" in data)
			{
				alert(data.warning)
			}
			
			if("deleted_items" in data)
			{
				refresh()
			}
		}],
		error: [empty_main, ajax_error_handler],
	})
}

function delete_invoice(object, reason)
{
	$.ajaxq("update", {
		url: "update.php",
		type: "post",
		dataType: "json",
		data: {
			delete_invoice: $(object).data("invoice_number"),
			reason: reason,
			user_id: window.user_id,
		},
		success: [ajax_result_handler, function(data){
			if("warning" in data)
			{
				alert(data.warning)
			}
			
			$(object)
				.addClass("deleted")
		}],
		error: [empty_main, ajax_error_handler],
	})
}

function delete_card(object)
{
	$.ajaxq("update", {
		url: "update.php",
		type: "post",
		dataType: "json",
		data: {
			delete_card: $(object).data("cc_id"),
			user_id: window.user_id,
		},
		success: [ajax_result_handler, function(data){
			if("done" in data)
			{
				$(object)
					.find("div.card")
					.addClass("deleted")
			}
		}],
		error: [empty_main, ajax_error_handler],
	})
}

function total_selected_items()
{
	return $("#items_table tr.selected").length
}

function update_new_invoice()
{
	$("#sales_tax_button")
		.html("Add Sales Tax ($"+sales_tax()+")")
	
	items = $("#items_table tr.selected").get()
	
	if(items.length || $("#shipping_charged").val() > 0)
	{
		var total = 0
		
		for(x in items)
		{
			total += parseFloat($(items[x]).data("price")) * parseInt($(items[x]).data("quantity"))
		}
		
		var data = {
			"subtotal" : total,
			"shipping_charged" : parseFloat($("#shipping_charged").val()),
			"grand_total" : total+parseFloat($("#shipping_charged").val())
		}
		
		//$("#newinvoice input[name=subtotal]").val(data.subtotal.toFixed(2))
		//$("#newinvoice input[name=grand_total]").val(data.grand_total.toFixed(2))
		
		
		$("#subtotal")
			.html("$"+data.subtotal.toFixed(2)+" + "+data.shipping_charged.toFixed(2)+
				"  = $"+data.grand_total.toFixed(2))
	}
	else
	{
		//$("#newinvoice input[name=subtotal]").val(0)
		//$("#newinvoice input[name=grand_total]").val(0)
		$("#subtotal").html("&nbsp;")
	}
}

function update_customer_pane_total()
{
	items = $("#customer_pane_items tr.selected").get()
	
	var total = 0
	
	if(items.length)
	{
		for(x in items)
		{
			total += parseFloat($(items[x]).data("price"))
		}
	}
	
	$("#customer_pane_total")
		.text("Total: $"+total.toFixed(2))
}

function update_locks() {
	if(!window.customer_email)
		return false
	
	var data = {
		form: "invoicing",
		table: "customer", 
		id: window.customer_email,
	}	
	
	if(window.record_lock)
	{
		data["lock_waiting"] = {
			table : "customer",
			id : window.customer_email
		}
	}

	$.ajaxq("fetch", {
		url : "update_locks.php",
		dataType: "json",
		type : "post",
		data : data,
		success : [ajax_result_handler, function(data) {
			if("logout" in data)
			{
				location.reload()
			}
			
			if(!("ok" in data))
				status("Couldn't update record lock." + data)
				
			if("got_lock" in data)
			{
				readwrite()
			}
			
			/*if("wants" in data)
			{
				locks_wanted(data.wants)
			}*/
		}],
		error: [ajax_error_handler],
	})
	
}

function locks_wanted(wants)
{
	var ids = []
	for(x in wants)
		ids.push(wants[x].id)
		
	$("div.alert.lockfault")
		.each(function(){
			if(ids.indexOf($(this).data("lockfault_id")) == -1)
				$(this).remove()
		})
	
	for(x in wants)
	{
		if($("#lockfault"+wants[x].id).length)
			continue;
		
		var box = alert_box(wants[x].name+" is trying to open this account.")
		
		$(box)
			.addClass("lockfault")
			.data("lockfault_id", wants[x].id)
			.attr("id", "lockfault"+wants[x].id)
			.appendTo($("#root"))
	}
}

function validate_luhn(luhn) {
	luhn = luhn.replace(/[^0-9]/g, "")
	
	var len = luhn.length,
	mul = 0,
	prodArr = [[0, 1, 2, 3, 4, 5, 6, 7, 8, 9], [0, 2, 4, 6, 8, 1, 3, 5, 7, 9]],
	sum = 0;
	 
	while (len--) {
	sum += prodArr[mul][parseInt(luhn.charAt(len), 10)];
	mul ^= 1;
	}
	 
	return sum % 10 === 0 && sum > 0;
}; 


function card_type(number) {
	number = number.replace(/[^0-9]/g, '')
	
	// Visa
	if (number.match(/^4/)) {
		return "Visa";
	}
	
	// American Express
	if (number.match(/^(34|37)/)) {
		return "American Express";
	}
	
	// Discover
	if (number.match(/^(6011|622(12[6-9]|1[3-9][0-9]|[2-8][0-9]{2}|9[0-1][0-9]|92[0-5]|64[4-9])|65)/)) {
		return "Discover Card";
	}
	
	// MasterCard
	if (number.match(/^5[1-5]/)) {
		return "MasterCard";
	}
	
	return 0;
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


function item_table_head(left, total)
{
	return $("<div />")
		.css({
			margin: "10px 5px 10px 5px",
			width: "1045px",
			position: "relative",
		})
		.append("&nbsp;")
		.append(
			$("<div />")
				.css({
					position: "absolute",
					left: "0px",
					top: "0px",
				})
				.html(left)
		)
		.append(
			$("<div />")
				.css({
					position: "absolute",
					right: "0px",
					top: "0px",
				})
				.html((total ? "<sup>$</sup>"+total : ""))
		)
}


function create_item_result(data)
{	
	if(data.next_bins_number)
		window.data.next_bins_number = data.next_bins_number
	
	if(data.item)
	{
		//TODO: This should take the data and create the item row
		var table = $("<table />")
			.addClass("roundbox")
			.addClass("items")
			.css({
				width: "1000px",
				marginTop: "15px",
				marginBottom: "15px",
			})
		
		data.item.delete_icon = "<img class='delete_icon' src='/includes/graphics/delete_x16.png' />"
		
		var row = $(Mustache.render($("#item_row2").html(), data.item))
							.attr("tabindex", "0")
							.addClass("selected")
							.data("invoice", false)	
		
		$(row)
			.find("img.delete_icon")
			.css({
				cursor: "pointer",
			})
			.click(function(event){
				delete_item($(event.target).parents("tr:first"))
			})
		
		$("#items_table")
			.prepend(
				$("<tbody />")
					.append(row)
			)
		
		$("#new_item")
			.find(":input")
			.val("")
			
		$("#fixed_price_sale")
			.attr("checked", false)
			
		$("#new_item [name=date]")
			.val(window.new_item_date)
			
		update_new_invoice()
		
		snapshot_inputs()
	}
	
	$("#no_outstanding_items").remove()
	
	if($("#unselect_all_button").length == 0)
	{
		$("#new_item")
			.after(
				$(unselect_all())
					.attr("id", "unselect_all_button")
			)
		
		if((window.data.customer.billing_address && window.data.customer.billing_address.billing_address.bill_state == "MO") ||
			(window.data.customer.customer.address.ship_state == "MO"))
		{
			$("#unselect_all_button")
				.after(sales_tax_button())
		}
	}
}

function validate_new_item()
{
	if(item['ebay_title'].trim() == "")
	{
		$("#new_item [name=ebay_title]")
			.css({
				border: "2px dotted tomato",
			})
			
		return false
	}
	else
	{
		$("#new_item [name=ebay_title]")
			.css({
				border: "",
			})
			
		return true
	}
}


function create_item()
{
	throb()
	
	var item = $("#new_item")
		.find("input")
		.createValueArray()
	
	item['customer_id'] = window.customer_id
	item['ebay_email'] = window.customer_email
	
	if(window.data.customer.customer.address.AA_ID)
		item['AA_ID'] = window.data.customer.customer.address.AA_ID
	
	if(item.ebay_id.match(/amazon/i) && item.ebay_item_number.match(/^F/i))
	{
		alert("You must put in the Amazon number")
		return false
	}
	
	$.ajaxq("update", {
		url: "update.php",
		type: "post",
		dataType: "json",
		data: {
			item: item,
			user_id: window.user_id,
		},
		complete: unthrob,
		success: [ajax_result_handler, create_item_result],
		error: [empty_main, ajax_error_handler],
	})
}


function edit_item(autonumber, data, callback, cancel_callback)
{
	if(!confirm("Are you sure you want to change this item?"))
	{
		cancel_callback()
		return false
	}
	
	var item = $("#new_item")
		.find("input")
		.createValueArray()
	
	$.ajaxq("update", {
		url: "update.php",
		type: "post",
		dataType: "json",
		data: {
			edit_item: autonumber,
			item_data: data,
			user_id: window.user_id,
		},
		success: [ajax_result_handler, function(data){
			if("item" in data)
			{
				callback(data.item)
			}
		}],
		error: [empty_main, ajax_error_handler],
	})
}

function edit_reminder_note(callback, cancel_callback)
{
	if($("#items_table tr.selected").length == 0)
	{
		alert("No items selected.")
		return false
	}
	
	var autonumbers = $.makeArray($("#items_table tr.selected").map(function(){return $(this).data("autonumber")}))
	
	var notes = prompt("Enter reminder notes for selected item(s) ("+autonumbers.length+")", $("#items_table tr.selected").data("reminder_notes"))
	
	if(typeof notes != "string")
	{
		if(cancel_callback)
			cancel_callback()
		return false
	}
	
	$.ajaxq("update", {
		url: "update.php",
		type: "post",
		dataType: "json",
		data: {
			autonumbers: autonumbers,
			reminder_note: notes,
			user_id: window.user_id,
		},
		success: [ajax_result_handler, function(data){
			if("ok" in data)
			{
				if(callback)
				{
					callback()
				}
			}
		}],
		error: [empty_main, ajax_error_handler],
	})
}


function create_invoice(ignore)
{
	//TODO: pay_and_hold, shipping_notes, 
	var invoice = $("#newinvoice :input").createValueArray()
	
	invoice['customer_id'] = window.customer_id
	invoice['customers_id'] = window.customers_id
	invoice['who_did']  = window.user_name
	
	/*&if(invoice['alternate_address'].trim().length)
	{
		invoice['extra_info'] += "\r\nSHIPPING TO ALTERNATE ADDRESS\r\n"+invoice['alternate_address'].trim()
	}
	
	delete invoice['alternate_address']*/
	
	var items = []
	
	$("#items_table tr.selected")
		.each(function(){
			items.push($(this).data("autonumber"))
		})
		
	if($("#newinvoice ul.ship_to_address:visible li.selected").length)
		invoice['shipto'] = $("#newinvoice ul.ship_to_address li.selected").attr("value")
		
	if(invoice['shipto'] && invoice['shipto'] != "primary")
	{
		if(!confirm("Are you sure you are shipping to an alternate address?"))
			return false
		
		invoice['extra_info'] += "\r\nSHIPPING TO ALTERNATE ADDRESS"
	}
	
	if(invoice['payment_method'] == "Invoice to Bruce")
	{
		var reason = prompt("Why are these items being invoiced to Bruce?")
		
		if(typeof reason != "string")
			return false
		
		while(reason.trim().indexOf(" ") != -1 && reason.trim().length <= 5)
		{
			if(typeof reason != "string")
				return false
			
			alert("We need a little more explanation than that.")
			
			reason = prompt(text)						
		}
		
		invoice['reason_for_invoicing_to_bruce'] = reason
	}
	
	/*if($("#newinvoice ul.bill_to_address:visible li.selected").length)
		invoice['billto'] = $("#newinvoice ul.bill_to_address li.selected").attr("value")*/
	
	//TODO: Warn if no items
	//TODO: Check for payment_method / cc_which_one mismatch
	
	$.ajaxq("update", {
		url: "update.php",
		type: "post",
		dataType: "json",
		data: {
			invoice: invoice,
			items: items,
			ignore: ignore,
			user_id: window.user_id,
		},
		success: [ajax_result_handler, create_invoice_result],
		error: [empty_main, ajax_error_handler],
	})
}

function create_invoice_result(data)
{
	if(data.warnings)
	{
		$(Mustache.render($("#warnings").html(), data))
			.dialog({
				height: 350,
				width: 650,
				position: { my: "left top", at: "right top", of: $("#newinvoice") },
				modal: true,
				title: "Uh-oh!",
				buttons: {
					"Cancel" : function(){
						$(this).dialog("close")
					},
					"Create Anyway" : function(){
						$(this).dialog("close")
						create_invoice(1)
					},
				}
			})
	}
	else if(!data.error)
	{
		search(window.customer_id, function(){
			show_items_pane()
		})
	}
}


//TODO: Adapt this to work
//TODO: Remember only US and CA (and maybe some others?)
function add_ship_state_autocomplete()
{
		$("[name=ship_state]").autocomplete("./includes/suggest.php",
			{extraParams:{field:"ship_state"}, mustMatch: true, width: 200, cacheLength: 0, delay: 0,
			formatItem: function(data){
				data = data[0].split("\t")
				return data[0] + "<span style='float:right'>" + data[1] + "</span>";
			}, formatResult: function(data){
				data = data[0].split("\t");
				return data[0]
	   		}});
}


function mark_invoice_shipped(invoice_number)
{
	if(confirm("Mark invoice #"+invoice_number+" as shipped? It will be hidden from this screen."))
	{
		$.ajaxq("update", {
			url: "update.php",
			type: "post",
			dataType: "json",
			data: {
				mark_invoice_shipped: invoice_number,
				user_id: window.user_id,
			},
			success: [ajax_result_handler, function(data){
				if(!("ok" in data))
					alert("I couldn't mark that invoice shipped for some reason.")
				else
					refresh()
			}],
			error: [empty_main, ajax_error_handler],
		})
	}
}



function update_customer(ignore)
{
	var data = $(":input.customers").createValueArray()
	
	var autoship = $(":input.autoship").createValueArray()

	autoship['customer_id'] = data.customer_id
	autoship['email_address'] = data.email
	
	var other_emails = $("#other_emails_table tr.email")
		.map(function(){
			if($("input[name=email]", this).val().trim())
			{
				return $(this).find(":input").createValueArray()
			}
		}).get()
	
	if(other_emails.length == 0)
		other_emails = ""
	
	/*if(data['email'].trim() == "")
	{
		alert("Email cannot be blank.")
		return false
	}*/
	
	if(data['name'].trim() == "")
	{
		alert("Name cannot be blank.")
		return false
	}
	
	if(data['ship_country'].trim() == "")
	{
		alert("Country cannot be blank.")
		return false
	}
	
	$.ajaxq("update", {
		url: "update.php",
		type: "post",
		dataType: "json",
		data: {
			other_emails: other_emails,
			autoship: autoship,
			data: data,
			table: "customers",
			ignore: ignore,
			user_id: window.user_id,
		},
		success: [ajax_result_handler, update_customer_result],
		error: [empty_main, ajax_error_handler],
	})
}


function delete_customer()
{
	//TODO: Checking for items, other records that must be deleted first.
	if(!confirm("You can only delete a customer with no items. Continue?"))
		return true
		
	throb()
	
	$.ajaxq("update", {
		url: "update.php",
		type: "post",
		dataType: "json",
		complete: unthrob,
		data: {
			delete_customer: window.customer_id,
			user_id: window.user_id,
		},
		success: [ajax_result_handler, function(data){
			
			if("result" in data)
			{
				show_home_pane()
				status("<img src='/includes/graphics/alert16.png' /> Deleted "+window.customer_id)
			}
		}],
		error: [empty_main, ajax_error_handler],
	})
}



function merge_customer2()
{
	var unsaved = check_for_unsaved()
	if(unsaved.length && !confirm("You have unsaved changes ("+unsaved.join(",")+"). Continue without saving?"))
		return false;	
	
	var from_id = prompt("Move items to '"+window.customer_id+"' from:")
	
	if(!from_id)
		return false
	
	throb()
	
	$.ajaxq("update", {
		url: "update.php",
		type: "post",
		dataType: "json",
		complete: unthrob,
		data: {
			move_items: 1,
			from_id: from_id,
			to_id: window.customer_id,
			user_id: window.user_id,
		},
		success: [ajax_result_handler, function(data){
			if("result" in data)
			{
				var t = $("<table cellpadding='5' cellspacing='0' />")
					.css({fontSize: "10pt"})
				
				if(data.error2)
				{
					$(t)
						.append(
							$("<caption />")
								.css({
									fontSize: "18pt",
									fontWeight: "bold",
									color: "tomato"
								})
								.text(data.error2)
						)
				}
				
				for(x in data.result)
				{
					td = $("<td />").text(data.result[x].message)
					
					if(data.result[x].error)
					{
						$(td)
							.css({
								fontSize: "110%",
								fontWeight: "bold",
								color: "tomato",
							})
					}
					
					$(t)
						.append(
							$("<tr />")
								.append(
									$("<td />")
										.text(x)
								)
								.append(
									
								)
						)
				}				
					
				var div = $("<div />")
					.append(t)
						
				if(!data.error2)
				{
					$(div)
						.append(
							$("<p />")
								.text("Old customer has been deleted. All old customer info was moved to the notes box. "+
									"Review that RIGHT NOW, delete any unneeded info, and "+
									"copy any needed info to the appropriate place.")
						)
				}
					
				if(window.data.customer.consignor.length)
				{
					$(div)
						.append(
							$("<p />")
								.text("Be sure to review the Consignor tab to check "+
									"their \"email\" field jives with their Consignor "+
									"\"Notes\". Tell Phil if it does not jive (turkey).")
						)
				}
					
				$(div)
					.dialog({
						title: "Results",
						width: 400
					})
			}
			
			if(!("error" in data))
				search(window.customer_id)
		}],
		error: [empty_main, ajax_error_handler],
	})
}



function merge_customer()
{
	var unsaved = check_for_unsaved()
	if(unsaved.length && !confirm("You have unsaved changes ("+unsaved.join(",")+"). Continue without saving?"))
		return false;	
	
	var from_email = prompt("Move items to "+window.customer_email+" from:")
	
	if(!from_email)
		return false
	
	throb()
	
	$.ajaxq("update", {
		url: "update.php",
		type: "post",
		dataType: "json",
		complete: unthrob,
		data: {
			move_items: 1,
			from_email: from_email,
			to_email: window.customer_email,
			user_id: window.user_id,
		},
		success: [ajax_result_handler, function(data){
			if("result" in data)
			{
				var t = $("<table cellpadding='5' cellspacing='0' />")
					.css({fontSize: "10pt"})
				
				if(data.error2)
				{
					$(t)
						.append(
							$("<caption />")
								.text(data.error2)
						)
				}
				
				for(x in data.result)
				{
					$(t)
						.append(
							$("<tr />")
								.append(
									$("<td />")
										.text(x)
								)
								.append(
									$("<td />")
										.text(data.result[x].message)
								)
						)
				}				
					
				var div = $("<div />")
					.append(t)
						
				if(!data.error2)
				{
					$(div)
						.append(
							$("<p />")
								.text("Old customer has been deleted. All old customer info was moved to the notes box. "+
									"Review that RIGHT NOW, delete any unneeded info, and "+
									"copy any needed info to the appropriate place.")
						)
				}
					
				if(window.data.customer.consignor.length)
				{
					$(div)
						.append(
							$("<p />")
								.text("Be sure to review the Consignor tab to check "+
									"their \"email\" field jives with their Consignor "+
									"\"Notes\". Tell Phil if it does not jive (turkey).")
						)
				}
					
				$(div)
					.dialog({
						title: "Results",
						width: 400
					})
			}
			
			if(!("error" in data))
				search(window.customer_id)
		}],
		error: [empty_main, ajax_error_handler],
	})
}


function change_customer_email()
{
	var unsaved = check_for_unsaved()
	if(unsaved.length && !confirm("You have unsaved changes ("+unsaved.join(",")+"). Continue without saving?"))
		return false;	
	
	var to_email = prompt("Change customer's email address from '"+window.customer_email+"' to:")
	
	if(!to_email)
		return false
	
	throb()
	
	$.ajaxq("update", {
		url: "update.php",
		type: "post",
		dataType: "json",
		complete: unthrob,
		data: {
			move_customer: 1,
			from_email: window.customer_email,
			to_email: to_email,
			user_id: window.user_id,
		},
		success: [ajax_result_handler, function(){
			
			if(window.data.customer.consignor.length)
			{
				$("<div />")
					.append(
						$("<p />")
							.text("Be sure to review the Consignor tab to make sure "+
								"their \"email\" field jives with their Consignor "+
								"\"Notes\". Tell Phil if it does not jive (turkey).")
					)
					.dialog({
						title: "Hey!",
						modal: true,
					})
			}
			
			search(window.customer_id)
		}],
		error: [empty_main, ajax_error_handler],
	})
}



function change_customer_id()
{
	var unsaved = check_for_unsaved()
	if(unsaved.length && !confirm("You have unsaved changes ("+unsaved.join(",")+"). Continue without saving?"))
		return false;	
	
	var to_id = prompt("Change customer_id from '"+window.customer_id+"' to:")
	
	if(!to_id)
		return false
	
	throb()
	
	$.ajaxq("update", {
		url: "update.php",
		type: "post",
		dataType: "json",
		complete: unthrob,
		data: {
			from_customers_id: window.customers_id,
			to_customer_id: to_id,
			user_id: window.user_id,
		},
		success: [ajax_result_handler, function(data){			
			if("customer_id" in data)
			{
				search(data.customer_id)
			}
		}],
		error: [empty_main, ajax_error_handler],
	})
}



function update_extra_address(button)
{
	//var unsaved = check_for_unsaved()
	//if(unsaved.length && !confirm("You have unsaved changes ("+unsaved.join(",")+"). Continue without saving?"))
		//return false;
		
	var data = $(button).parents("div.extra_address")
		.find(":input.extra_address").createValueArray()
	
	if(data['name'].trim() == "")
	{
		alert("Name cannot be blank.")
		return false
	}
	
	if(data['ship_country'].trim() == "")
	{
		alert("Country cannot be blank.")
		return false
	}
	
	$.ajaxq("update", {
		url: "update.php",
		type: "post",
		dataType: "json",
		data: {
			data: data,
			notes_for_invoice: $(":input.customers[name=notes_for_invoice]").val(),
			table: "customers_addresses",
			customers_id: window.customers_id,
			//ignore: ignore,
			user_id: window.user_id,
		},
		success: [ajax_result_handler, update_customer_result],
		error: [empty_main, ajax_error_handler],
	})
}


function delete_extra_address(button)
{
	if(window.record_lock)
		return false
				
	var unsaved = check_for_unsaved()
	if(unsaved.length && !confirm("You have unsaved changes ("+unsaved.join(",")+"). Continue without saving?"))
		return false;
	
	if($(button).parents("div.extra_address").find(":input[name=account_number]").val().trim() != "")
	{
		var message = "This address has an account number, which will be deleted. Are you sure you want to delete this address?"
	}
	else
	{
		var message = "Are you sure you want to delete this address?"
	}
	
	if(!confirm(message))
		return false
	
	$.ajaxq("update", {
		url: "update.php",
		type: "post",
		dataType: "json",
		data: {
			address_id: $(button)
				.parents("div.extra_address")
				.find(":input[name=address_id]").val(),
			delete_extra_address: 1,
			user_id: window.user_id,
		},
		success: [ajax_result_handler, function(data){
			snapshot_inputs()
			search(window.customer_id)
		}],
		error: [empty_main, ajax_error_handler],
	})
}


function set_primary_address(button)
{
	if(window.record_lock)
		return false
				
	var unsaved = check_for_unsaved()
	if(unsaved.length && !confirm("You have unsaved changes ("+unsaved.join(",")+"). Continue without saving?"))
		return false;
	
	if(!confirm("You are about to set this address as the primary address. Continue?"))
		return false
	
	$.ajaxq("update", {
		url: "update.php",
		type: "post",
		dataType: "json",
		data: {
			address_id: $(button)
				.parents("div.extra_address")
				.find(":input[name=address_id]").val(),
			set_primary_address: 1,
			user_id: window.user_id,
			customer_id: window.customer_id
		},
		success: [ajax_result_handler, function(data){
			snapshot_inputs()
			search(window.customer_id)
		}],
		error: [empty_main, ajax_error_handler],
	})
}



function update_customer_result(data)
{
	if(data.similar)
	{
		$(Mustache.render($("#similar_names").html(), data.similar))
			.dialog({
				width: 500,
				height: 400,
				buttons: {
					"Create Anyway": function(){
						$(this).dialog("close")
						update_customer(1)
					},
					"Cancel": function(){
						$(this).dialog("close")
					}
				}
			})
	}
	else
	{
		if(data.new_customer_id)
		{
			snapshot_inputs()
			search(data.new_customer_id)
		}
		
		var indicator = $("<small style='color: green; font-weight: bold'>OK!</small>")
		$("#update_customer").before(indicator)
		$(indicator).fadeOut(800)
		
		window.customers_original = $(window.customer_pane)
			.find(":input.customers")
			.serialize()
	}
	
	if(data.customer)
	{
		update_clip(data.customer)
	}
}


function update_bill_to_address()
{
	var data = $(":input.bill_to_address").createValueArray()
	
	data['customer_id'] = $("input[name=customer_id]").val()
	
	$.ajaxq("update", {
		url: "update.php",
		type: "post",
		dataType: "json",
		data: {
			data: data,
			table: "bill_to_address",
			user_id: window.user_id,
		},
		success: [ajax_result_handler, update_bill_to_address_result],
		error: [empty_main, ajax_error_handler],
	})
}


function update_bill_to_address_result(data)
{
	var indicator = $("<small style='color: green; font-weight: bold'>OK!</small>")
	$("#update_bill_to_address").before(indicator)
	$(indicator).fadeOut(800)
	
	window.billing_original = $(window.customer_pane)
		.find(":input.bill_to_address")
		.serialize()
}


function other_emails_onchange(event)
{
	if( $(event.target).is(":input") )
	{				
		table = $(event.target).parents("table.other_emails")
		
		var elements = $("tr.email", table).get()
		
		for(x in elements)
		{
			if($("input[name=email]", elements[x]).val().trim() == "" && $("input[name=notes]", elements[x]).val().trim() == "")
				return false;
		}
		
		$(table)
			.append(
				$(Mustache.render(window.templates.other_emails_row, {
					"customers_id" : window.data.customer.customer.address.customers_id
				}))
			)
	}
}

function add_autocompletes(customer_pane)
{
	//TODO: State autocomplete. Use the cities database, add back the so-called "useless" fields you removed 
	//in order to use the state names. For the city dropdown, filter out states and counties and stuff.
	
	//Cities come from /data/aaron/cities.sql.gz
	//https://code.google.com/p/worlddb/downloads/detail?name=lokasyon.sql.gz&can=2&q=
	var autocomplete = $(customer_pane)
		.find("input[name=ship_city]")
		.autocomplete({
			source: function(request, response){
				$.ajaxq("autocomplete", {
					url: "suggest.php",
					type: "post",
					data: {
						name: "city",
						state: $("input[name=ship_state]").val(),
						country: $("input[name=ship_country]").val(),
						term: request.term
					},
					dataType: "json",
					success: [ajax_result_handler, function(data){response(data)}],
					error: [empty_main, ajax_error_handler, function(){response()}],
				})
			},
			minLength: 1,
		})
	
	var autocomplete = $(customer_pane)
		.find("input[name=ship_country]")
		.autocomplete({
			source: "suggest.php?name=country",
			minLength: 1,	
		})
		.data("ui-autocomplete")

		autocomplete._resizeMenu = function(){
			this.menu.element.outerWidth(300)
		}
		
		autocomplete._renderItem = function(ul, item){
			return $("<li />")
				.data("item.autocomplete", item)
				.append(
					$("<a />")
						.text(item.label)
						.css({position: "relative"})
						.append(
							$("<span />")
								.css({position: "absolute", right: "0px"})
								.text(item.countries)
						)
				)
				.appendTo(ul)
		}
		
	
	var autocomplete = $(customer_pane)
		.find("input[name=ship_state]")
		.autocomplete({
			source: function(request, response){
				$.ajaxq("autocomplete", {
					url: "suggest.php",
					type: "post",
					data: {
						name: "state",
						country: $("input[name=ship_country]").val(),
						term: request.term
					},
					dataType: "json",
					success: [ajax_result_handler, function(data){response(data)}],
					error: [empty_main, ajax_error_handler, function(){response()}],
				})
			},
			minLength: 0,
		})
		.data("ui-autocomplete")
		
		autocomplete._resizeMenu = function(){
			this.menu.element.outerWidth(300)
		}
		
		autocomplete._renderItem = function(ul, item){
			if(item.local_name == item.label)
			{
				return $("<li />")
				.data("item.autocomplete", item)
				.append(
					$("<a />")
						.text(item.label)
				)
				.appendTo(ul)
			}
			else
			{
				return $("<li />")
					.data("item.autocomplete", item)
					.append(
						$("<a />")
							.text(item.label)
							.css({position: "relative"})
							.append(
								$("<span />")
									.css({position: "absolute", right: "0px"})
									.text(item.local_name)
							)
					)
					.appendTo(ul)
			}
		}
	
	
	var autocomplete = $(customer_pane)
		.find("input[name=bill_country]")
		.autocomplete({
			source: "suggest.php?name=country",
			minLength: 1,	
		})
		.data("ui-autocomplete")

		autocomplete._resizeMenu = function(){
			this.menu.element.outerWidth(300)
		}
		
		autocomplete._renderItem = function(ul, item){
			return $("<li />")
				.data("item.autocomplete", item)
				.append(
					$("<a />")
						.text(item.label)
						.css({position: "relative"})
						.append(
							$("<span />")
								.css({position: "absolute", right: "0px"})
								.text(item.countries)
						)
				)
				.appendTo(ul)
		}
		
	$(customer_pane)
		.find("table.other_emails")
		.change(function(event){return other_emails_onchange(event)})
}







function usort(inputArr, sorter) {
  //  discuss at: http://phpjs.org/functions/usort/
  // original by: Brett Zamir (http://brett-zamir.me)
  // improved by: Brett Zamir (http://brett-zamir.me)
  //        note: This function deviates from PHP in returning a copy of the array instead
  //        note: of acting by reference and returning true; this was necessary because
  //        note: IE does not allow deleting and re-adding of properties without caching
  //        note: of property position; you can set the ini of "phpjs.strictForIn" to true to
  //        note: get the PHP behavior, but use this only if you are in an environment
  //        note: such as Firefox extensions where for-in iteration order is fixed and true
  //        note: property deletion is supported. Note that we intend to implement the PHP
  //        note: behavior by default if IE ever does allow it; only gives shallow copy since
  //        note: is by reference in PHP anyways
  //   example 1: stuff = {d: '3', a: '1', b: '11', c: '4'};
  //   example 1: stuff = usort(stuff, function (a, b) {return(a-b);});
  //   example 1: $result = stuff;
  //   returns 1: {0: '1', 1: '3', 2: '4', 3: '11'};

  var valArr = [],
    k = '',
    i = 0,
    strictForIn = false,
    populateArr = {};

  if (typeof sorter === 'string') {
    sorter = this[sorter];
  } else if (Object.prototype.toString.call(sorter) === '[object Array]') {
    sorter = this[sorter[0]][sorter[1]];
  }

  // BEGIN REDUNDANT
  this.php_js = this.php_js || {};
  this.php_js.ini = this.php_js.ini || {};
  // END REDUNDANT
  strictForIn = this.php_js.ini['phpjs.strictForIn'] && this.php_js.ini['phpjs.strictForIn'].local_value && this.php_js
    .ini['phpjs.strictForIn'].local_value !== 'off';
  populateArr = strictForIn ? inputArr : populateArr;

  for (k in inputArr) { // Get key and value arrays
    if (inputArr.hasOwnProperty(k)) {
      valArr.push(inputArr[k]);
      if (strictForIn) {
        delete inputArr[k];
      }
    }
  }
  try {
    valArr.sort(sorter);
  } catch (e) {
    return false;
  }
  for (i = 0; i < valArr.length; i++) { // Repopulate the old array
    populateArr[i] = valArr[i];
  }

  return strictForIn || populateArr;
}



// Ideally you should use feature detection but can't think of a better way
if ( true ) { // Check for firefox like $.browser.mozilla
  (function(window){
    var _confirm = window.confirm;
    window.confirm = function(msg){
      var keyupCanceler = function(ev){
          ev.stopPropagation();
          return false;
      };
      document.addEventListener("keyup", keyupCanceler, true);
      var retVal = _confirm(msg);
      setTimeout(function(){
        document.removeEventListener("keyup", keyupCanceler, true);
      }, 150); // Giving enough time to fire event
      return retVal;
    };
  })(window);
}

function refresh(callback)
{
	var focused_tab = $("#tabs li.focused")
	
	search(window.customer_id, function(){
		focus_tab(focused_tab)
		
		if(typeof callback == "function")
			callback()
	})
}

function show_printout_history()
{
	$(Mustache.render($("#printout_history").html(), window.data.customer))
		.dialog({
			title: "Printout History for "+window.customer_id,
			height: 300,
			position: {my: "center", at: "center", of: window}
		})
}

function request_quote()
{
	throb()

	var data = $(":input.customers").createValueArray()
	
	delete data.pay_and_hold_days
	delete data.other_emails
	delete data.preferred_package_value
	delete data.account_number
	delete data.customer_since

	$.ajax({
		url: "update.php",
		type: "post",
		dataType: "json",
		data: {
			customer_website: 1,
			data: data,
			user_id: window.user_id,
		},
		success: [ajax_result_handler, function(data){
			if("ok" in data)
			{
				open_quote_request()
			}
		}],
		complete: unthrob,
		error: [empty_main, ajax_error_handler],
	})
}

function open_quote_request()
{
	$("<form></form>")
		.attr("action", "https://www.emovieposter.com/secure/test_tools/quote_admin.php"+
			"?email="+encodeURIComponent(window.customer_email))
		.attr("method", "post")
		.attr("target", "_blank")
		.css("display", "none")
		/*.append(
			$("<input />")
			.attr({
				type: "hidden",
				name: "alternate_address"
			}).val($("textarea[name=alternate_address]").val())
		)*/
		.appendTo(document.body).get(0).submit()
}



function close()
{
	var unsaved = check_for_unsaved()
	if(unsaved.length && !confirm("You have unsaved changes ("+unsaved.join(",")+"). Continue?"))
		return false;
	
	clear_status()
	
	$("#admin_settings_box").dialog("destroy")
	
	
	$(window.customer_pane).remove()
	$(window.packages_pane).remove()
	$(window.items_pane).remove()
	$(window.consignor_pane).remove()
	
	delete window.customer_pane
	delete window.packages_pane
	delete window.items_pane
	delete window.record_lock
	delete window.consignor_pane
	
	$("#auction_anything")
		.attr("href", "#")
		.text("Auction Anything Profile")
		.hide()
	
	document.title = "Invoicing"
	
	$("#root div.alert").remove()
	
	snapshot_inputs()
	
	$("#refresh, #admin_settings, #menu_button, #clip").css("visibility", "hidden")
	
	if(window.customer_email)
	{
		$.ajax({
			url: "close.php",
			type: "post",
			data: {
				email: window.customer_email,
				user_id: window.user_id,
			},
			dataType: "json",
			success: [
				function(data){
					$("#customer_id").html("").removeAttr("href")
					delete window.address
					delete window.data
					delete window.customers_id
					delete window.customer_id
					delete window.customer_email
				}, ajax_result_handler],
			complete: unthrob,
			error: [empty_main, ajax_error_handler],
		})
	}
}


function create_consignor_pane(data)
{
	var pane = $("<div />")
		.attr("id", "consignor_pane")
		.css({
			padding: "10px"
		})
	
	for(x in data.customer.consignor)
	{
		var consignor = $(Mustache.render($("#consignor_row").html(), data.customer.consignor[x]))
		
		$(".tooltip", consignor).tooltip()
		
		$(consignor)
			.appendTo(pane)
		
		$(consignor)
			.find("textarea")
			.on("input", function(event){
				$(this)
					.css("height", $(this).height() + $(this).get(0).scrollTopMax)
			})
			
		$("[name=no_matter_what] option[value="+data.customer.consignor[x].no_matter_what+"]", consignor).prop("selected", true)
	}
	
	var consignor = $(Mustache.render($("#consignor_new").html(), {
		real_name: window.data.customer.customer.address.name,
		email: window.customer_email,
		linking_email: window.customer_email
	}))
	
	$(consignor)
		.appendTo(pane)
		
	$(consignor)
		.find("textarea")
		.on("input", function(event){
			$(this)
				.css("height", $(this).height() + $(this).get(0).scrollTopMax)
		})
		
	$(pane)
		.find("div.consignor")
		.find("[name=CommissionRate]")
		.autocomplete({
			autoFocus: true,
			minLength: 0,
			source: commission_rates
		})
		
		$(pane)
			.find("div.consignor")
			.find("[name=lots]")
			.autocomplete({
				autoFocus: true,
				minLength: 0,
				source: "suggest.php?name=lots",
			})
			
		$(pane)
			.find("div.consignor")
			.find("[name=payment_preference]")
			.autocomplete({
				autoFocus: true,
				minLength: 0,
				source: "suggest.php?name=payment_preference",
			})
	
	
	return pane
}


function show_consignor_pane()
{
	$(".ui-tooltip").remove()
	
	$.ajaxq.abort("fetch")
	
	$(window.customer_pane)
		.css({display: "none"})
	
	$(window.items_pane)
		.css({display: "none"})
		
	$(window.packages_pane)
		.css({display: "none"})
		
	$(window.consignor_pane)
		.css({display: ""})
		
	$(window.history_pane).remove()
	
	delete window.history_pane
	
	$(window.home_pane).remove()
	
	delete window.home_pane
	
	$(window.account_pane).remove()
	
	delete window.account_pane
	
	$(window.checks_pane).remove()
	
	delete window.checks_pane
	
	//$("#package_search").focus()
	
	$("div.consignor textarea")
		.trigger("input")
}


function save_consignor(button)
{
	var consignor = $(button)
		.parents("div.consignor")
		.find(":input:not(button)")
		.createValueArray()
	
	if(!consignor['cust_id'])
		consignor['cust_id'] = window.customer_id
	
	$.ajaxq("update", {
		url: "update.php",
		type: "post",
		dataType: "json",
		data: {
			consignor: consignor,
			user_id: window.user_id,
		},
		success: [ajax_result_handler, function(data){
			if(!("error" in data))
				refresh()
		}],
		error: [empty_main, ajax_error_handler],
	})
}


function new_consignor(button)
{
	var consignor = $(button)
		.parents("div.consignor")
		.find(":input:not(button)")
		.createValueArray()
	
	consignor['cust_id'] = window.customer_id
	
	$.ajaxq("update", {
		url: "update.php",
		type: "post",
		dataType: "json",
		data: {
			consignor: consignor,
			new_consignor: 1,
			user_id: window.user_id,
		},
		success: [ajax_result_handler, function(data){
			if(!("error" in data))
				refresh()
		}],
		error: [empty_main, ajax_error_handler],
	})
}


//TODO: This needs to log you OUT before login
function log_in_as_customer()
{
	throb()
	
	$.ajax({
		url: "/website_tools/recover_aa_password.php",
		type: "post",
		data: {
			"customer_id": window.data.customer.customer.address.AA_ID,
		},
		dataType: "json",
		complete: function(){
			unthrob()
		},
		error: [empty_main, ajax_error_handler],
		success: [ajax_result_handler, function(data){
			if("result" in data)
			{
				w = window.open()
	
				w.document.open()
				
				w.document.write(Mustache.render($("#login").html(), {
						username: window.data.customer.customer.address.username,
						password: data.result,
					}))
				
				w.document.close()
				
				w.document.title = "Logging in as "+window.data.customer.customer.address.name
				
				w.document.forms.login.submit()
			}
		}]
	})
}

function forgot_password_email(customer_id, username)
{
	if(!window.data.customer.customer.address.AA_ID)
	{
		alert("No Auction Anything account assigned to this customer.")
		return false
	}
	
	if(!confirm("You are about to send a 'forgot username/password' email to "+window.data.customer.customer.address.AA_ID+". Continue?"))
		return false;
	
	throb()
	
	$.ajax({
		url: "/website_tools/forgot_password_email.php",
		type: "post",
		data: {
			aa_customer_id: window.data.customer.customer.address.AA_ID
		},
		dataType: "json",
		complete: function(){
			unthrob()
		},
		error: [empty_main, ajax_error_handler],
		success: [ajax_result_handler, function(data){
			if("success" in data)
			{
				alert("Email sent to "+data.email+".")
				status("<b style='color: green'>Sent to "+data.email+".</b>")
			}
		}]
	})
}



function update_customer_admin(data)
{
	data['customers_id'] = window.customers_id
	data['customer_id'] = window.customer_id
	
	$.ajaxq("update", {
		url: "update.php",
		type: "post",
		dataType: "json",
		data: {
			data: data,
			table: "customers",
			user_id: window.user_id,
			
		},
		success: [ajax_result_handler, function(data){
			if(!("error" in data))
			{
				var indicator = $("<small style='color: green; font-weight: bold'>OK!</small>")
				$("#admin_settings_status").append(indicator)
				$(indicator).fadeOut(800)
			}
		}],
		error: [empty_main, ajax_error_handler],
	})
}

function open_menu()
{
	$($("#menu").html())
		.css({display: "inline-block", fontSize: "10pt"})
		.menu({
			blur: function(event){
				
			}
		})
		.blur(function(){
			
		})
		.appendTo(document.body)
		.position({my: "left top", at: "left bottom", of: $("#search")})
		.focus()
}

vowels = ["a", "e", "i", "o", "u", "y", "n", "r"]

function deuglify_tracking_numbers()
{
	$("#packages ul")
		.each(function(){
			if($(this).children(":not(.hidden)").length)
			{
				$(this)
					.css("display", "")
			}
			else
			{
				$(this)
					.css("display", "none")
			}
				
		})
}


function get_invoice(invoice_number)
{
	throb()
	
	$.ajaxq.abort("fetch")
	
	$.ajaxq("fetch", {
		url: "select.php",
		type: "post",
		data: {
			customer_id: window.customer_id,
			invoice_number: invoice_number,
			user_id: window.user_id,
		},
		dataType: "json",
		success: [ajax_result_handler, get_invoice_result],
		complete: unthrob,
		error: [empty_main, ajax_error_handler],
	})
}


function get_invoice_result(data)
{
	$("#newinvoice").replaceWith(editable_invoice(data.invoice))
	
	$("#items_table").remove()
	
	$(create_items_table({
			unpaid: data.items,
			select_invoice: data.invoice.invoice_number
		}))
		.attr("id", "items_table")
		.appendTo(window.items_pane)
	
	$(window.items_pane)
		.find("table.items")
		.tooltip({
			items: "table.items",
			content: "<ul><li>Arrows + Space to select</li><li>Ctrl+A to select/deselect all</li><li>Tab/Shift+Tab to change group</li></ul>",
			position: {my: "center bottom", at: "center top-15"},
		})
		
	update_new_invoice()
	
	$("#newinvoice [name=extra_info]").focus()
	
	$("#newinvoice [name=extra_info]")
		.css("height", $("#newinvoice [name=extra_info]").height() + $("#newinvoice [name=extra_info]").get(0).scrollTopMax)
}

function update_invoice(ignore)
{
	var invoice = $("#newinvoice :input:not(button)").createValueArray()
	
	invoice['customer_id'] = window.customer_id
	invoice['customers_id'] = window.customers_id
	invoice['who_did']  = window.user_name
	
	var items = []
	
	$("#items_table tr.selected")
		.each(function(){
			items.push($(this).data("autonumber"))
		})
		
	if($("#newinvoice ul.ship_to_address").is(":visible"))
		invoice['shipto'] = $("#newinvoice ul.ship_to_address li.selected").attr("value")
	
	//if($("#newinvoice ul.bill_to_address:visible li.selected").length)
		//invoice['billto'] = $("#newinvoice ul.bill_to_address li.selected").attr("value")
		
	//TODO: Warn if no items
	//TODO: Check for payment_method / cc_which_one mismatch
	
	$.ajaxq("update", {
		url: "update.php",
		type: "post",
		dataType: "json",
		data: {
			update: 1,
			invoice: invoice,
			items: items,
			user_id: window.user_id,
			ignore: ignore,
		},
		success: [ajax_result_handler, update_invoice_result],
		error: [empty_main, ajax_error_handler],
	})
}


function update_invoice_result(data)
{
	if(data.warnings)
	{
		$(Mustache.render($("#warnings").html(), data))
			.dialog({
				height: 350,
				width: 450,
				position: { my: "left top", at: "right top", of: $("#newinvoice") },
				modal: true,
				title: "Uh-oh!",
				buttons: {
					"Cancel" : function(){
						$(this).dialog("close")
					},
					"Create Anyway" : function(){
						$(this).dialog("close")
						update_invoice(1)
					},
				}
			})
	}
	else
	{
		search(window.customer_id, function(){
			focus_tab($("#items_tab"))
		})
	}
}

function print_selected_invoices()
{
	var invoices = []
	$("#unprinted_invoices input[type=checkbox]:checked")
		.each(function(){
			invoices.push($(this).data("invoice_number"))
		})
	
	if(invoices.length == 0)
	{
		alert("You didn't select any invoices to print.")
		return false;
	}
	
	var w = window.open("/invoicing/print_invoices.php?invoice_numbers="+invoices.join(","))
	
	$(w)
		.bind("beforeunload", function(){
			
			if(confirm("Mark "+invoices.length+" invoice"+(invoices.length > 1 ? "s" : "")+" as printed?"))
			{
				mark_invoices_printed(invoices)
			}
			else
			{
				
			}
		})
}

function mark_invoices_printed(invoices)
{
	$.ajaxq("update", {
		url: "update.php",
		type: "post",
		dataType: "json",
		data: {
			mark_invoices_printed: invoices,
			user_id: window.user_id,
			user_name: window.user_name,
		},
		success: [ajax_result_handler, function(data){
			if("ok" in data)
			{
				activate_pane("home")
			}
		}],
		error: [ajax_error_handler],
	})
}



function edit_card(card)
{
	var input = $("<input />")
		.attr("name", "cc_exp")
		.attr("size", 4)
		.val($(card).data("cc_exp")).get(0)
	
	$(card)
		.find("div.expiration")
		.empty()
		.append(input)
		
	input.select()
	
	$(card)
		.find("div.name")
		.empty()
		.append(
			$("<input />")
				.attr("name", "cc_name")
				.css({
					width: "130px",
				})
				.val($(card).data("cc_name"))
		)
}


function charge_card_dialog(card)
{
	var unsaved = check_for_unsaved()
	if(unsaved.length)
	{
		alert("You must save your changes before proceeding. ("+unsaved.join(",")+").")
		return false;
	}
	
	var box = $(Mustache.render($("#charge_card_dialog").html(), {
		title: "Charged card "+$(card).find("div.number").html().slice(-4),
		cc_id: $(card).data("cc_id")
	}))
	.dialog({
		title: "Charge this credit card",
		modal: true,
		width: 338,
		height: 350,
		resizable: false,
		position: { my: "center top", at: "center top+50", of: window },
		buttons: {
			"Charge Now" : function(){
				var values = $(this)
					.find(":input")
					.createValueArray()
					
				if(values.amount && values.title)
				{
					charge_card(values)
					$(this).dialog("close")
				}
				else
				{
					alert("Looks like you're missing something.")
				}
			},
			"Help" : function(){
				help2("Charge a credit card")
			}
		}
	})
	
	$(box)
		.find(".card_cell")
		.css({padding: 0})
		.append(
			$(card).find("div.card").clone().css({margin: "0"})
		)
	
	$(box)
		.find("[name=amount]")
		.focus()
		
	window.charge_card_dialog_box = box
}

function charge_card(charge)
{
	throb()
	
	charge['user_id'] = window.user_id
	
	if(window.data.customer.billing_address && window.data.customer.billing_address.billing_address.ts > window.data.customer.customer.address.ts)
	{
		charge['max_timestamp'] = window.data.customer.billing_address.billing_address.ts
	}
	else
	{
		charge['max_timestamp'] = window.data.customer.customer.address.ts
	}
	
	$.ajaxq.abort("fetch")
	
	$.ajaxq("fetch", {
		url: "charge.php",
		type: "post",
		data: charge,
		dataType: "json",
		success: [
			function(data){
				if(!("error" in data))
				{
					$(window.charge_card_dialog_box).dialog("close")
					refresh()
				}
			}, ajax_result_handler],
		complete: unthrob,
		error: [empty_main, ajax_error_handler],
	})
}


function extend_flat_rate_shipping()
{
	//They're surely going to ask to be able to customize the amount 
	//of time they're extended by, but that's stupid so I'm going to wait
	//until they ask and then roll my eyes at them. AK 2015-06-01
	//if(!confirm("Are you sure you want to extend flat rate shipping for this customer?"))
	//	return false
		
	//Update. Phil asked for it immediately. >:[
	var days = prompt("How many days from today would you like to extend flat rate shipping for this customer?", 7)
	
	if(days === false || isNaN(parseInt(days)))
		return false;
	
	throb()
	
	$.ajax({
		url: "update.php",
		type: "post",
		data: {
			"extend_flat_rate_shipping": days,
			"users_id": window.data.customer.customer.address.users_id,
			"user_name": window.user_name,
		},
		dataType: "json",
		success: [ajax_result_handler, function(data){
			console.debug(data)
			if("ok" in data)
			{
				alert("Success! Flat rate shipping has been extended for this customer.")
			}
		}],
		complete: function(data){
			unthrob()
		},
		error: [empty_main, ajax_error_handler],
	})
}


function cancel_quote_request()
{
	if(!confirm("This will remove \"quote requested\" status from all of this customer's items. Are you sure?"))
		return false
	
	throb()
	
	$.ajax({
		url: "update.php",
		type: "post",
		data: {
			"cancel_quote_request": 1,
			"users_id": window.data.customer.customer.address.users_id,
			"user_id": window.user_id,
		},
		dataType: "json",
		success: [ajax_result_handler, function(data){
			
			
			if("ok" in data)
			{
				if("affected_rows" in data)
				{
					alert("Done! Affected rows: "+data.affected_rows)
				}
				
				refresh()
			}
		}],
		complete: function(data){
			unthrob()
		},
		error: [empty_main, ajax_error_handler],
	})
}



function tab_mouseenter(event)
{
	//if("customer" == $(event.target).parents("li").attr("tab"))
	//{
	if(!$("#quick_info_box").length && window.data)
	{
		$(Mustache.render($("#quick_info").html(), window.data.customer.customer))
			.attr("id", "quick_info_box")
			.css("opacity", "0")
			.appendTo(document.body)
			.position({my: "center top", at: "center bottom", of: $("#tabs")})
			.animate({opacity: "1"})
	}
	//}
}

function tab_mouseleave(event)
{
	$("#quick_info_box").remove()
}

function refund_dialog(card)
{
	var unsaved = check_for_unsaved()
	if(unsaved.length)
	{
		alert("You must save your changes before proceeding. ("+unsaved.join(",")+").")
		return false;
	}
	
	var box = $(Mustache.render($("#refund_dialog").html(), {
		cc_id: $(card).data("cc_id")
	}))
	
	.dialog({
		title: "Record a refund",
		modal: true,
		width: 338,
		height: 350,
		resizable: false,
		position: { my: "center", at: "center", of: window },
		buttons: {
			"Save" : function(){
				var values = $(this)
					.find(":input")
					.createValueArray()
					
				if(values.amount)
				{
					refund(values)
					$(this).dialog("close")
				}
				else
				{
					alert("Looks like you're missing something.")
				}
			},
			"Help" : function(){
			  	help2("Record a refund")
			}
		}
	})
	
	$(box)
		.find(".card_cell")
		.css({padding: 0})
		.append(
			$(card).find("div.card").clone().css({margin: "0"})
		)
	
	$(box)
		.find("[name=amount]")
		.focus()
		
	window.refund_dialog_box = box
}

function refund(values)
{
	throb()
	
	values['user_id'] = window.user_id,
	values['max_timestamp'] = window.data.customer.customer.address.ts
	values['customers_id'] = window.data.customer.customer.address.customers_id
	
	$.ajaxq.abort("fetch")
	
	$.ajaxq("fetch", {
		url: "refund.php",
		type: "post",
		data: values,
		dataType: "json",
		success: [
			function(data){
				if(!("error" in data))
				{
					$(window.refund_dialog_box).dialog("close")
					refresh()
				}
			}, ajax_result_handler],
		complete: unthrob,
		error: [empty_main, ajax_error_handler],
	})
}















function import_text()
{
	$(".ui-tooltip").remove()
	
	throb()
	
	$.ajaxq.abort("fetch")
	
	$.ajaxq("fetch", {
		url: "account.php",
		type: "post",
		data: {
			user_id: window.user_id,
			customers_id: window.customers_id,
			import: $("#import_text").val(),
		},
		dataType: "json",
		success: [ajax_result_handler, function(){
			refresh()
		}],
		complete: unthrob,
		error: [empty_main, ajax_error_handler],
	})
}








function show_account_pane()
{
	$(".ui-tooltip").remove()
	
	throb()
	
	$.ajaxq.abort("fetch")
	
	$.ajaxq("fetch", {
		url: "account.php",
		type: "post",
		data: {
			user_id: window.user_id,
			customers_id: window.customers_id,
			page: 0,
			perpage: account_pane_perpage,
		},
		dataType: "json",
		success: [ajax_result_handler, parse_account],
		complete: unthrob,
		error: [empty_main, ajax_error_handler],
	})
}

function parse_account(data)
{
	$(window.customer_pane)
		.css({display: "none"})
	
	$(window.items_pane)
		.css({display: "none"})
		
	$(window.packages_pane)
		.css({display: "none"})
		
	$(window.consignor_pane)
		.css({display: "none"})
	
	$(window.home_pane).remove()
	
	delete window.home_pane
	
	$(window.history_pane).remove()
	
	delete window.history_pane
	
	$(window.account).remove()
	
	delete window.account
	
	$(window.emails_pane).remove()
	
	delete window.emails_pane
	
		$(window.checks_pane).remove()
	
	delete window.checks_pane
	
	window.account = create_account_pane(data)
	
	$(window.account)
		.appendTo(document.body)	
}



function create_account_pane(data)
{
	var pane = $("<div />")
		.attr("id", "account_pane")
		.css({
			padding: "10px"
		})
	
	$("<textarea />")
		.attr("id", "import_text")
		.css({
			width: "700px",
			height: "75px",
			background: "white",
			display: "block",
		})
		.appendTo(pane)
	
	$("<button>Import</button>")
		.click(function(){
			import_text()
		})
		.appendTo(pane)
		
	
	var table = $("<table border='1' />")
		.addClass("accounting")
	
	$(table)
		.html("<tr><th>Date</th><th>Invoice</th>"+
			"<th>Description</th><th>Credit</th><th>Debit</th><th>Balance</th></tr>")
	
	for(x in data.transactions)
	{		
		var row = $(Mustache.render($("#accounting_row").html(), data.transactions[x]))
		
		if(x == 0)
		{
			$("<span style='float: left'>$</span>")
				.prependTo($(row).find(".credit"))
			
			$("<span style='float: left'>$</span>")
				.prependTo($(row).find(".debit"))
			
			$("<span style='float: left'>$</span>")
				.prependTo($(row).find(".balance"))
		}
		
		$(row)
			.appendTo(table)
	}
	
	$(pane)
		.append(table)
	
	if(data.transactions.length >= account_pane_perpage)
	{
		$("<div />")
			.css({
				textAlign: "center",
				margin: "15px 0px 15px 0px",
			})
			.append(
				$("<button>Load More</button>")
					.click(function(){
						load_more_transactions(parseInt(data.page)+1)
						$(this).parents("div:first").remove()
					})
			)
			.appendTo(pane)
			.get(0).scrollIntoView()
	}
	else
	{
		$("<div />")
			.css({
				margin: "15px 0px 15px 500px",
			})
			.text("No More Items")
			.appendTo(pane)
			.get(0).scrollIntoView()
	}

	return pane
}


function load_more_transactions(page)
{
	$(".ui-tooltip").remove()
	
	throb()
	
	$.ajaxq.abort("fetch")
	
	$.ajaxq("fetch", {
		url: "account.php",
		type: "post",
		data: {
			user_id: window.user_id,
			customers_id: window.customers_id,
			page: page,
			perpage: account_pane_perpage,
		},
		dataType: "json",
		success: [ajax_result_handler, parse_more_transactions],
		complete: unthrob,
		error: [empty_main, ajax_error_handler],
	})
}

function parse_more_transactions(data)
{
	var pane = $("#account_pane")
	var table = $(pane).find("table.accounting")
	
	if(data.transactions && data.transactions.length)
	{
		$(table)
			.append("<tr><th>Date</th><th>Invoice</th>"+
				"<th>Description</th><th>Credit</th><th>Debit</th><th>Balance</th></tr>")
			
   		for(x in data.transactions)
		{		
			var row = $(Mustache.render($("#accounting_row").html(), data.transactions[x]))
						
			$(row)
				.appendTo(table)
		}	
		
		if(data.transactions.length >= account_pane_perpage)
		{
			$("<div />")
				.css({
					textAlign: "center",
					margin: "15px 0px 15px 0px",
				})
				.append(
					$("<button>Load More</button>")
						.click(function(){
							load_more_transactions(parseInt(data.page)+1)
							$(this).parents("div:first").remove()
						})
				)
				.appendTo(pane)
				.get(0).scrollIntoView()
		}
		else
		{
			$("<div />")
				.css({
					margin: "15px 0px 15px 500px",
				})
				.text("No More Items")
				.appendTo(pane)
				.get(0).scrollIntoView()
		}
	
	}
	else
	{
		$("<div />")
			.css({
				margin: "15px 0px 15px 500px",
			})
			.text("No More Items")
			.appendTo(pane)
			.get(0).scrollIntoView()
	}
		
	return pane
}



//These are global because they need to persist beyond the functions that use them
var startdate = "";
var enddate = "";
var subjectsearch = "";
var laststartdate = "";
var lastenddate = "";
var lastsubjectsearch = "";

function show_emails_pane()
{
	if(typeof $("input[name=startdate]").val() !== 'undefined' && $("input[name=startdate]").val() !== null)
	{
		startdate = $("input[name=startdate]").val();
	}
	else
	{
		startdate = "";
	}
	
	if(typeof $("input[name=enddate]").val() !== 'undefined' && $("input[name=enddate]").val() !== null)
	{
		enddate = $("input[name=enddate]").val();
	}
	else
	{
		enddate = "";
	}
	
	if(typeof $("input[name=subjectsearch]").val() !== 'undefined' && $("input[name=subjectsearch]").val() !== null)
	{
		subjectsearch = $("input[name=subjectsearch]").val();
	}
	else
	{
		subjectsearch = "";
	}
	
	if(typeof $("input[id=page]").val() !== 'undefined' && $("input[id=page]").val() !== null)
	{
		
		if(Number.isInteger(parseInt($("input[id=page]").val())))
		{
			page = parseInt($("input[id=page]").val());
			page = parseInt(page);
		}
		else
		{
			page = 1;
		}
	}
	else
	{
		page = 1;
	}
	
	//Sets the page to 1 if any of the previously submitted data has been changed.
	//It prevents things like a blank page 6 being shown when only 2 pages exist.
	if(startdate != laststartdate || enddate != lastenddate || subjectsearch != lastsubjectsearch)
	{
		page = 1;
	}
	
	$(".ui-tooltip").remove()
	
	throb()
	
	$.ajaxq.abort("fetch")
	
	emails = [window.data.customer.customer.address.email]
	
	for(x in window.data.customer.customer.other_emails)
	{
		emails.push(window.data.customer.customer.other_emails[x].email)
	}
	
	parse_emails(emails)
	
	$.ajaxq("fetch", {
		url: "emails.php",
		type: "post",
		data: {
			user_id: window.user_id,
			emails: emails,
			page: page,
			startdate: startdate,
			enddate: enddate,
			subjectsearch: subjectsearch,
		},
		dataType: "json",
		success: [ajax_result_handler, parse_emails],
		complete: unthrob,
		error: [empty_main, ajax_error_handler],
	})
}


function parse_emails(data)
{
	$(window.customer_pane)
		.css({display: "none"})
	
	$(window.items_pane)
		.css({display: "none"})
		
	$(window.packages_pane)
		.css({display: "none"})
		
	$(window.consignor_pane)
		.css({display: "none"})
	
	$(window.home_pane).remove()
	
	delete window.home_pane
	
	$(window.history_pane).remove()
	
	delete window.history_pane
	
	$(window.emails_pane).remove()
	
	delete window.emails_pane
	
	$(window.account_pane).remove()
	
	delete window.account_pane
	
	$(window.checks_pane).remove()
	
	delete window.checks_pane
	
	window.emails_pane = create_emails_pane(data)
	
	$(window.emails_pane)
		.appendTo(document.body)
	
	//These set the HTML input fields to what was previously just submitted
	//so it doesn't get wiped out every time the page loads
	document.getElementById("startdatevariable").value = startdate
	document.getElementById("enddatevariable").value = enddate
	document.getElementById("subjectvariable").value = subjectsearch
	
	laststartdate = startdate;
	lastenddate = enddate;
	lastsubjectsearch = subjectsearch;
}

function create_emails_pane(data)
{
	//HTML to be appended to the page
	var filterinputs = $("<form id=\"email_search_form\">"+
		"<div>"+
		"<input id=\"startdatevariable\" type=\"datetime-local\" name=\"startdate\" placeholder=\"Start Date m/d/y\">"+
		"<input id=\"enddatevariable\" type=\"datetime-local\" name=\"enddate\" placeholder=\"End Date m/d/y\">"+
		"<input id=\"subjectvariable\" type=\"search\" name=\"subjectsearch\" placeholder=\"Subject\">"+
		"<button id=\"get_emails_submit\" style=\"background:transparent;border:none;font-size:0;\"></button>"+
		"</div>"+
		"</form><br>"+
		
		"<small>Archiving began August 2015</small><br>").submit(function(event){
		event.preventDefault()
		show_emails_pane()
		})
  
	var pane = $("<div />")
		.attr("id", "emails_pane")
		.css({
			padding: "10px"
		})
		.append(filterinputs)
	
	//Add date picker to the page on elements with matching name
	pane.find("[name=startdate]").datepicker({dateFormat: "m/d/y", changeMonth: true, changeYear: true, showOtherMonths: true, selectOtherMonths: true, onSelect: function(){$('#get_emails_submit').submit();}})
	pane.find("[name=enddate]").datepicker({dateFormat: "m/d/y", changeMonth: true, changeYear: true, showOtherMonths: true, selectOtherMonths: true, onSelect: function(){$('#get_emails_submit').submit();}})
	
	//The following append will display paging info on the page for the user
	if(data.pageinfo)
	{
		$(pane)
			.append(data.pageinfo)
	}
	
	if(data.mail)
	{
		var template = $("#mail_row").html()
		
		for(x in data.mail)
		{
			var table = $("<table />")
				.addClass("credits")
				.addClass("items")
				.addClass("history")
				.css({
					marginTop: "0px",
					marginBottom: "0px",
					fontSize: "12px",
				})
				.append(
					"<tr><th style='width: 141px'>When</th><th style='width: 469px'>Subject</th><th style='width: 247px'>From</th><th style='width: 247px'>To</th></tr>"
				)
			
			for(y in data.mail[x])
			{
				var row = $(Mustache.render(template, data.mail[x][y]))
							.attr("tabindex", "0")
							//.addClass("selected")
				
				$(table)
					.append(row)
			}
			
			$(pane)
				.append(item_table_head(x, ""))
				.append(table)
		}
		
		if(table == null)
		{
			$(pane)
				.append("<br>No more emails")
		}
		
		$(pane)
			.append("<br><br>")
	}
	else
	{
		$(pane)
			.append("No emails")
	}
	
	
	//window.history_pane_page = 0
		
	return pane
}



function change_consignments_items_email()
{
	var unsaved = check_for_unsaved()
	
	if(unsaved.length && !confirm("You have unsaved changes ("+unsaved.join(",")+"). Continue without saving?"))
		return false;
	
	var from_email = prompt("Update tbl_Current_Consignments records to '"+window.customer_email+"' from:")
	
	if(!from_email)
		return false
	
	throb()
	
	$.ajaxq("update", {
		url: "update_email.php",
		type: "post",
		dataType: "json",
		complete: unthrob,
		data: {
			from_email: from_email,
			to_email: window.customer_email,
			user_id: window.user_id,
		},
		success: [ajax_result_handler, function(data){
			if("confirm" in data)
			{
				$('<div title="Update records?"><p style="font-size: 9pt"><span class="ui-icon ui-icon-alert" style="float:left; margin:0 7px 20px 0;"></span>'+data.confirm+'</p></div>')
					.dialog({
						resizable: false,
						height: 230,
						width: 700,
						modal: true,
						position: {my: "center top", at: "center top+30", of: window},
						buttons : {
							"Yes": function(){
								change_consignments_items_email_run(from_email)
								$(this).dialog("close")
							},
							"No": function(){
								$(this).dialog("close")
							}
						},
					})
			}
		}],
		error: [empty_main, ajax_error_handler],
	})
}

function change_consignments_items_email_run(from_email)
{	
	throb()
	
	$.ajaxq("update", {
		url: "update_email.php",
		type: "post",
		dataType: "json",
		complete: unthrob,
		data: {
			run: 1,
			from_email: from_email,
			to_email: window.customer_email,
			user_id: window.user_id,
		},
		success: [ajax_result_handler, function(data){
			if("success" in data)
			{
				alert(data.success)
				search(window.customer_id)
			}
		}],
		error: [empty_main, ajax_error_handler],
	})
}


function advanced_search()
{
   w =  window.open("advanced_search.php", "advSearch", "dialog=yes,minimizable=yes,scrollbars=yes,width=900")
   
   
}



function tabs(items)
{
	/*
		[
			{
				tab: "Tab Content",
				element: element
			},
		]
	*/
	var root = $("<div />")
		.addClass("aTabs")
		.attr("id", "aTabs")
	
	var tabs = $("<ul />")
		.appendTo(root)
	
	for(x in items)
	{
		$("<div />")
			.attr("id", "aTab-"+x)
			.css({
				display: "none",
			})
			.append(
				$(items[x].element)
					.css({
						margin: 0,
						borderTop: "none",
					})
			)
			.appendTo(root)
		
		var a = $("<a />")
					.attr("href", "#aTab-"+x)
					.append(items[x].tab)
		
		if(items[x].subtitle)
		{
			$(a)
				.append(
					$("<small />")
						.text(items[x].subtitle)
				)
		}
		else
		{
			$(a)
				.append("<small>&nbsp;</small>")
		}
		
		$("<li />")
			.append(a)
			.appendTo(tabs)
	}
	
	return root
}




function new_transaction(credit)
{
	var box = $($("#transaction").html())
	
	$(box)
		.find("input[name=type]")
		.click(function(){
			console.debug($(this).val())
		})
	
	$(box).find("input[name=amount]").parents("td").prev().text(credit ? "Credit" : "Debit")
	
	$(box)
		.dialog({
			height: 350,
			width: 650,
			//position: { my: "left top", at: "right top", of: $("#newinvoice") },
			//modal: true,
			title: "New Transaction",
			buttons: {
				"Cancel" : function(){
					$(this).dialog("close")
				},
				"Create" : function(){
					$(this).dialog("close")
					
				},
			}
		})
	
}





function update_history_bar(history)
{
	$("#history_bar").empty()
	first = true
	for(x in history)
	{
		if(first)
		{
			first = false;
		}
		else
		{
			$("#history_bar")
				.append("&larr;");
		}
		
		
		$("#history_bar")
			.append(history_link(x, history[x]))
	}
}

function update_history_bar2(history)
{
	$("#history_bar2").empty()
	first = true
	for(x in history)
	{
		if(first)
		{
			first = false;
		}
		else
		{
			$("#history_bar2")
				.append("&larr;");
		}
		
		
		$("#history_bar2")
			.append(history_link(x, history[x]))
	}
}



function history_link(customer_id, name)
{
	return $("<a class='link' href='?customer_id="+customer_id+"' />")
		.click(function(event){
			if(event.which == 1)
			{
				search(customer_id)
				event.preventDefault()
			}
		})
		.text(name)
}

function other_emails(data)
{
	var table = $(Mustache.render(window.templates.other_emails, data))
	
	$(":input", table)
		.change(function(){
			table = $(this).parents("table.other_emails")
			
			var elements = $("tr.email", table).get()
			
			for(x in elements)
			{
				if($("input[name=email]").val().trim() == "" && $("input[name=notes]").val().trim() == "")
					return false;
			}
			
			$(table)
				.append(
					Mustache.render(window.templates.other_emails_row, {
						"customers_id" : window.data.customer.customer.address.customers_id
					})
				)
		})
		
	return table
}


function autoquote(qpp_id)
{
	if(!confirm("You are about to create an automatically estimated shipping quote."))
		return false
	
	throb()
	
	$.ajaxq("update", {
		url: "update.php",
		type: "post",
		dataType: "json",
		data: {
			qpp_id: qpp_id,
			user_id: window.user_id,
		},
		success: [ajax_result_handler, function(data){
			if("package_id" in data)
			{
				window.open("http://poster-server/shipping_quotes/?customer="+data.package_id)
			}
			
			refresh()
		}],
		error: [ajax_error_handler],
		complete: [unthrob]
	})
}

