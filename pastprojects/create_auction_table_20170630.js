


function begin_undo_last_action()
{
	jQuery.ajax({
		url: "/create_auction_table/backend.php",
		type: "post",
		dataType: "json",
		data: {
			get_last_undo: 1,
			user_id: window.user_id,
		},
		success: [function(data){
			if(data.ts)
			{
				confirm_undo(data)
			}
		}, ajax_result_handler],
		error: ajax_error_handler
	})
}


function begin_undo_table(date)
{
	throb()
	jQuery.ajax({
		url: "/create_auction_table/backend.php",
		type: "post",
		dataType: "json",
		data: {
			get_undo_table: date,
			user_id: window.user_id,
		},
		success: [function(data){
			unthrob()
			if(data.ts)
			{
				confirm_undo(data)
			}
		}, ajax_result_handler],
		error: [unthrob, ajax_error_handler]
	})
}

function confirm_undo(data)
{
	if(confirm("You are about to undo:\n"+data.items))
	{
		undo_action(data.ts);
	}
}

function undo_action(ts)
{
	throb()
	
	jQuery.ajax({
		url: "/create_auction_table/backend.php",
		type: "post",
		dataType: "json",
		data: {
			undo: ts,
			user_id: window.user_id,
		},
		complete: unthrob,
		success: [function(data){
			if("ok" in data)
			{
				alert("Done.")
			}
		}, ajax_result_handler],
		error: ajax_error_handler
	})
}

function begin_create_auction(date)
{
	throb()
	
	jQuery.ajax({
		url: "/create_auction_table/backend.php",
		type: "post",
		dataType: "json",
		data: {
			get_date: date,
			user_id: window.user_id,
		},
		success: [function(data){
			unthrob()
			
			if(data.day)
			{
				confirm_create_auction(data)
			}
			
			if(data.table)
			{
				alert("There is already a table for this auction ('"+data.table+"')")
			}
		}, ajax_result_handler],
		error: [unthrob, ajax_error_handler]
	})
}


//count
function create_auction_chars_remaining(input)
{
	
	var remaining = 64 - (jQuery(input).val().length+parseInt(jQuery(input).data("auction_codes_length")) + 21) //changed +14 to +21 to account for the largest auction codes
	
	
	
	jQuery(input).siblings("[name=remaining]").val(remaining)
}


function confirm_create_auction(data)
{
	div = jQuery("<div />")
		.append(
			jQuery("<p />")
				.text("You are about to create the "+data.date+" auction "+
					"with "+data.conditioned+" "+data.day.auction_codes.join("/")+" items.")
		)
		.append(
			jQuery("<table />")
				.append(
					jQuery("<tr />")
						.append("<td>Short table description</td>")
						.append(
							jQuery("<td />")
								.append(
									jQuery("<input name='table_description' data-auction_codes_length='"+data.day.auction_codes.join("/").length+"' />")
										.keyup(function(event){
											create_auction_chars_remaining(this)
										})
										.css({
											width: "380px",
											fontSize: "10pt"
										})
										.val(data.day.plain_title)
								)
								.append(" <small>chars</small> ")
								.append(
									jQuery("<input name='remaining' readonly='readonly' />")
										.css({
											width: "30px",
											fontSize: "9pt",
										})
										.val(64-(data.day.auction_codes.join("/").length + data.day.plain_title.length + 21)) //changed +14 to +21 to account for the largest auction codes
								)
						)
				)
		)
	
	
	
	url = "/create_auction_table/typeGraph.png.php?a="+new Date().getTime()
	
	for(x in data.day.auction_codes)
	{
		url += "&auction_codes[]="+encodeURIComponent(data.day.auction_codes[x])
	}
	
	jQuery(div)
		.append(
			jQuery("<img />")
				.css({
					margin: "10px"
				})
				.attr("src", url)
		)
		
	if(data.warnings.length)
	{
		table = jQuery("<table />")
			.css({
				fontSize: "9pt",
			})
			.append("<caption>Warnings</caption>")
		
		
		for(x in data.warnings)
		{
			jQuery(table)
				.append(
					jQuery("<tr />")
						.append(
							jQuery("<td />")
								.text(data.warnings[x]['category'])
						)
						.append(
							jQuery("<td />")
								.append(
									jQuery("<a />")
										.attr("href", data.warnings[x].link)
										.attr("target", "_blank")
										.text(data.warnings[x].title)
								)
						)
				)
		}
		
		jQuery(table)
			.appendTo(div)
	}
   
	jQuery(div)
		.dialog({
			title: data.day.auction_codes.join("/"),
            width: 700,
            height: 700,
	    	position: {my: "right center", at: "center", of: window},
            //resizable: false,
            buttons: {
                "Go" : function(data){
						return function(){
                    		create_auction(data, jQuery(this).find("input[name=table_description]").val())
							jQuery(this).dialog("close")
                		}
					}(data)
            },
		})

		
	
	/*
	title = prompt("You are about to create the "+data.date+" auction "+
					"with "+data.conditioned+" "+data.day.auction_codes.join("/")+" items. "+
					"Enter short table description:", 
					data.day.plain_title)
					
	if(title !== null && title != "")
	{
		create_auction(data, title)
	}
	*/
}


function create_auction(data, table_description)
{
	throb()
	jQuery.ajax({
		url: "/create_auction_table/backend.php",
		type: "post",
		dataType: "json",
		data: {
			create_auction: 1,
			auction_date: data.date,
			auction_description: table_description,
			auction_codes: data.day.auction_codes,
			user_id: window.user_id,
		},
		complete: [unthrob],
		success: [function(data){
			if("table" in data)
			{
				window.open("/check_auction_table/?submit=Submit&table="+encodeURIComponent(data.table))
			}
			
		}, ajax_result_handler],
		error: ajax_error_handler
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

function ajax_error_handler(jqXHR, textStatus) {
  if (textStatus != "abort") {
    var errorStartPos = jqXHR.responseText.indexOf("\n\n[SHOWERROR] ")
    if (errorStartPos != -1) {
      alert("ERROR - " + jqXHR.responseText.substr(errorStartPos + 14))
    } else {
      alert("Error! Error! Abort! Abort!")
    }
  }
}

indicator = jQuery("<img />")
		.attr("src", "/includes/graphics/throb.gif")
		.attr("id", "indicator")
		.css({
			
		})
	
jQuery.fn.createValueArray = function() {
	var values = {};
	this.each(function(){
		switch(jQuery(this).attr("type"))
		{
			case "radio":
				if(jQuery(this).prop("checked"))
					values[jQuery(this).attr("name")] = jQuery(this).val();
				break;
			case "checkbox":
				values[jQuery(this).attr("name")] = ((jQuery(this).prop("checked")) ? 1 : 0);
				break;
			default:
				values[jQuery(this).attr("name")] = jQuery(this).val();
				break;
		};
	});
	
	return values;
}

function throb()
{
	jQuery(document.body)
		.append(
			jQuery(indicator)
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
	jQuery("#indicator").remove()
}