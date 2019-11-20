/*
	This file is used in more than one location. Rename wisely. AK 2016-05-31
*/

function initialize_bloodhound()
{
	/*
		Suggest customers
	*/
	customers = new Bloodhound({
		sufficient: Infinity,
		name: "customers",
		datumTokenizer: function(datum){
			return [datum['name'], datum['customer_id'], datum['ship_city']]
		},
		queryTokenizer: Bloodhound.tokenizers.whitespace,
		prefetch: {
			url: "/invoicing/typeahead_prefetch.php?name=customer",
		},
		remote: {
			url: "suggest.php?name=customer&q=%QUERY",
			wildcard: "%QUERY",
			rateLimitWait: 500,
			prepare: function(query, settings){
				settings.url = settings.url.replace("%QUERY", encodeURIComponent(query))
				throb()
				return settings
			},
			transform: function(response){
				unthrob()
				return response
			},
		},
		dupDetector: function(remote, local) {
			return (remote.customer_id == local.customer_id)
		}
	})

	customers.clearPrefetchCache()

	customers.initialize()


	/*
		Suggest customers (close name match)
	*/
	customer2 = new Bloodhound({
		sufficient: Infinity,
		name: "customers",
		datumTokenizer: function(datum){
			return [datum['name'], datum['customer_id'], datum['ship_city']]
		},
		queryTokenizer: Bloodhound.tokenizers.whitespace,
		remote: {
			url: "suggest.php?name=customer_close_match&q=%QUERY",
			wildcard: "%QUERY",
			rateLimitWait: 500,
			prepare: function(query, settings){
				settings.url = settings.url.replace("%QUERY", encodeURIComponent(query))
				throb()
				return settings
			},
			transform: function(response){
				unthrob()
				return response
			},
		},
		dupDetector: function(remote, local) {
			return (remote.customer_id == local.customer_id)
		}
	})

	customer2.clearPrefetchCache()

	customer2.initialize()


	/*
		Suggest items
	*/
	items = new Bloodhound({
		sufficient: 10,
		name: "items",
		datumTokenizer: function(datum){
			return [datum['ebay_title']]
		},
		queryTokenizer: Bloodhound.tokenizers.whitespace,
		remote: {
			url: "suggest.php?name=item&q=%QUERY",
			wildcard: "%QUERY",
			rateLimitWait: 500,
			prepare: function(query, settings){
				settings.url = settings.url.replace("%QUERY", encodeURIComponent(query))
				throb()
				return settings
			},
			transform: function(response){
				unthrob()
				return response
			},
		},
	})

	items.clearPrefetchCache()

	items.initialize()

	/*
		Suggest PayPal payment
	*/
	payments = new Bloodhound({
		sufficient: 1,
		name: "payment",
		datumTokenizer: function(datum){
			return [datum['txn_id'], datum['customer_id']]
		},
		queryTokenizer: Bloodhound.tokenizers.whitespace,
		remote: {
			url: "suggest.php?name=payment&q=%QUERY",
			wildcard: "%QUERY",
			rateLimitWait: 500,
			prepare: function(query, settings){
				settings.url = settings.url.replace("%QUERY", encodeURIComponent(query))
				throb()
				return settings
			},
			transform: function(response){
				unthrob()
				return response
			},
		},
	})

	payments.clearPrefetchCache()

	payments.initialize()
	
	return {
		"customers" : customers,
		"customer2" : customer2,
		"items" : items,
		"payments" : payments,
	}
}


function initialize_typeahead(element, hounds)
{
	/*
		Set up Twitter typeahead
	*/
	$(element)
		.on("paste", function(event){
			this.timeout = setTimeout(function(){search($(event.target).val())}.bind(this), 0)
		})
		.on("input", function(event){
			var value = $(this).val().trim()
			
			if(value.indexOf("@") !== -1)
				search(value)
		})
		.bind("typeahead:selected", function(event, suggestion){
			if("customer_id" in suggestion)
			{
				search(suggestion.customer_id)
			}
		})
		.focus(function(){
			$(this).select()
		})
		.keydown(search_onkeydown)
		.typeahead({
			hint: true,
			highlight: true,
			minLength: 3,
		},
		{
			limit: Infinity,
			name: "customers",
			displayKey: "customer_id",
			source: hounds.customers.ttAdapter(),
			templates: {
				notFound: "<p style='padding-left: 15px'>No Results</p>",
				pending: "<p style='padding-left: 15px'><img src='/includes/graphics/indicator.gif' /></p>",
				header: "<h4>Customers</h4>",
				suggestion: function(suggestion){
					p = $("<p />")
					
					$("<span />")
						.css({
							width: "100px",
							display: "inline-block",
						})
						.text(suggestion.customer_id)
						.appendTo(p)
					
					$("<span />")
						.css({
							width: "200px",
							display: "inline-block",
						})
						.text(suggestion.name)
						.appendTo(p)
				   
						
					$("<span />")
						.css({
							width: "200px",
							display: "inline-block",
							overflow: "hidden",
							textOverflow: "ellipsis",
							whiteSpace: "nowrap",
							marginRight: "5px",
						})
						.text(suggestion.email)
						.appendTo(p)
					
					$("<span />")
						.css({
							width: "200px",
							display: "inline-block",
							overflow: "hidden",
							textOverflow: "ellipsis",
							whiteSpace: "nowrap",
						})
						.text(suggestion.location)
						.appendTo(p)	
						
					if(suggestion.consignor > 0)
					{
						$("<span />")
							.text("C")
							.appendTo(p)
					}
					
						
					/*$("<a />")
						.attr("href", "http://mail-server/persinfo.php?custid="+suggestion.customer_id)
						.text("Go")
						.appendTo(p)*/
						
						
					return p
				}
			},
		},
		{
			limit: 15,
			name: "items",
			displayKey: "ebay_title",
			source: hounds.items.ttAdapter(),
			templates: {
				header: "<h4>Items</h4>",
			},
		},
		{
			limit: 15,
			name: "payments",
			displayKey: "customer_id",
			source: hounds.payments.ttAdapter(),
			templates: {
				header: "<h4>PayPal</h4>",
				suggestion: function(suggestion){
					p = $("<p />")
					
					$("<span />")
						.css({
							width: "200px",
							display: "inline-block",
						})
						.text(suggestion.txn_id)
						.appendTo(p)
					
					$("<span />")
						.css({
							width: "200px",
							display: "inline-block",
						})
						.text(suggestion.payer_email)
						.appendTo(p)								
						
					return p
				}
			},
		});
}

function search_onkeydown(event)
{
	if(event.keyCode == 13)
	{
		search($(event.target).val())
		event.stopImmediatePropagation()
	}
}