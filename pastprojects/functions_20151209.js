$(document).ready(function(){
	setInterval(function(){
		poll_reshoot()
	}, 7000)
	
	$(".auction, .gallery, .descriptions, .bulk_lot").val('');
	$("#image").empty()
	$("[name=title_45].auction")
		.keyup(function(){
			update_title_45_length();
		});
	$("#search").focus();
	
	$.info_notes_box = new Info_Notes_Box($("#info_notes_widget"));
	
	$("input[name='MrLTitle']")
		.autocomplete("suggest.php", {
			minChars: 0, 
			selectFirst: false,
			cacheLength: 0, 
			max: 110,
			delay: 10, 
			extraParams: {
				field: "film_MrLister",
				film_title: function(){
					return $('[name="TITLE"]').val();
					}, 
				type_code: function(){
					return $('#type_code').val();
					},
				},
			formatItem: function(row){
					obj = eval("(" + row + ")");
					return obj.MrLister + " <i style='position:absolute; right: 2em;'>" + obj.type_code + "</i>";
				},
			formatResult: function(row){
					obj = eval("(" + row + ")");
					return obj.MrLister;
				},
		});
	
	$("[name=film_title]").change(function(){
	   getDesc(this.value);
	});
	
	$("textarea").keydown(function(event){
		switch(event.which)
		{
			case 113: //F2
				if($.fullBox)
				{											
					$.fullBox.style.height = $.fullBox.oldHeight;
					$.fullBox.style.width = $.fullBox.oldWidth;
					$.fullBox.style.position = $.fullBox.oldPosition;
					$.fullBox.style.border = $.fullBox.oldBorder;
					$($.fullBox).attr("wrap", $.fullBox.oldWrap);
					
					if($.fullBox === this)
					{
						delete $.fullBox;
						break;
					}
				}
				
				this.oldHeight = this.style.height;
				this.oldWidth = this.style.width;
				this.oldPosition = this.style.position;
				this.oldWrap = $(this).attr("wrap");
				this.oldBorder = this.style.border;
				
				this.style.height = "300px";
				this.style.width = "500px";
				this.style.position = "absolute";
				this.style.border = "1px solid red";
				$(this).attr("wrap","hard");
				
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
		};
	
	});
	
});

function key_code_is_alphanumeric(code)
{
	return (
		(code > 47 && code < 91 || code == 32 || code > 95 && code < 111)
	);
}

/* Provided by info_notes_box.js
function htmlspecialchars(str) {
 if (typeof(str) == "string") {
  str = str.replace(/&/g, "&amp;");
  str = str.replace(/"/g, "&quot;");
  str = str.replace(/'/g, "&#039;");
  str = str.replace(/</g, "&lt;");
  str = str.replace(/>/g, "&gt;");
  }
 return str;
 }
*/

 
function nl2br(val)
{
	return val.replace("\n", "<br />");
}

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


function search(term, evt)
{
	if($.searchTimeout)
		clearTimeout($.searchTimeout);
		
	if(evt == 13)
		do_search(term);
	else if(key_code_is_alphanumeric(evt))
		$.searchTimeout = setTimeout(function(){do_search(term)}, 500);
}

function do_search(term)
{
	if($.searchAjax)
		$.searchAjax.abort();
	
	if(term.length < 2)
		return false;
	
	$.searchAjax = $.ajax({
	   url: "search.php",
	   type: "post",
	   dataType: "json",
	   beforeSend: function(){
		$("#loading").empty().append("<img src='graphics/indicator.gif' />");
	   },
	   data: {
			search: term,
			search_auction_history: $("#search_ah").attr("checked")
	   },
	   success: function(data){
	   	$("#loading").empty();
		
		if(data.length == 0)
		{
			$("#results").empty().hide();
			$("#loading").html("No results found");
			return false;
		}
		
		$("#results").html("<ul class='links' />");
		
		for(x in data)
		{
			if(data[x].dated_table)
				$("#results ul").append("<li onclick='get_item("+JSON.stringify(data[x].id)+", this, false, "+JSON.stringify(data[x].dated_table)+")'>"+data[x].lot_title+"</li>");
			else
				$("#results ul").append("<li onclick='get_item("+JSON.stringify(data[x].id)+", this, false)'>"+data[x].lot_title+"</li>");
		}
		
		$("#results").show();

		//if(data.length == 1)
		//I am removing this because Matt wants it to always get the first item. - AK, 2012-12-28
		get_item(data[0].id, $("#results li:first"), false, data[0].dated_table);
		
	   },
	   error: function(xhr, status){
	   	if(status == "abort")
			return false
		
	   	alert("Unknown error searching auctions!");
	   }
	});
}

function search_title_type(title, type_code)
{
	if($.searchAjax)
		$.searchAjax.abort();
	
	$.searchAjax = $.ajax({
	   url: "search.php",
	   type: "post",
	   dataType: "json",
	   data: "film_title="+encodeURIComponent(title)+"&type_code="+encodeURIComponent(type_code),
	   success: function(data){		
		if(data.length == 0)
		{
			//$("#results").empty().hide();
			return false;
		}
		
		$("#results").html("<ul class='links' />");
		
		for(x in data)
		{
			if(data[x].dated_table)
				$("#results ul").append("<li onclick='get_item("+JSON.stringify(data[x].id)+", this, false, "+JSON.stringify(data[x].dated_table)+")'>"+data[x].lot_title+"</li>");
			else
				$("#results ul").append("<li onclick='get_item("+JSON.stringify(data[x].id)+", this, false)'>"+data[x].lot_title+"</li>");
		}
		
		$("#results").show();
	   },
	   error: function(xhr, status){
	   	if(status == "abort")
			return false
		
	   	alert("Unknown error searching auctions!");
	   }
	});
}

function get_item(id, li, hide_results, dated_table, suppress_empty_messages)
{
	if(!suppress_empty_messages)
		$("#messages").empty()
	
	if(hide_results)
		$("#results").hide();
	
	document.last_item_id = id;
	document.last_dated_table = dated_table
	
	$(".auction, .descriptions, .gallery, .bulk_lot, #viewerslist, #bidderslist").val('');
	clearTitleList(); //Remove all elements from the bulk lots title lists
	$("#image").empty()
	$("#mrlistertitles table, #repronotes table").remove();
	$("#links").empty();
	$("#bidders").empty().hide();
	$.info_notes_box.reset();
	update_title_45_length();
	$(".links").find("img").remove();
	if($.getItemAjax)
		$.getItemAjax.abort();
	
	$(li).append("<img style='margin-top: -5px;' src='graphics/indicator.gif' />");

	$.getItemAjax = $.ajax({
		url: "select.php",
		type: "post",
		dataType: "json",
		data: "id="+encodeURIComponent(id)+(dated_table ? "&dated_table="+encodeURIComponent(dated_table) : ""),
		success: function(data){
			$(li).find("img").remove();
			
			if('error' in data)
			{
				alert(data.error);
				return false;
			}
			
			if('message' in data)
			{
				$(alert_box(data.message))
					.appendTo("#messages")
			}
			
			$.bulk_lot = data.bulk_lot;
			
			$.day = "";
			if(data.day)
				$.day = data.day;
				
			$.shipping_id = "";
			if(data.shipping_id)
				$.shipping_id = data.shipping_id;
			
			$.specialNotesDate = "";
			if(data.specialNotesDate)
				$.specialNotesDate = data.specialNotesDate;
				
			$.aa_description_id = "";
			if(data.aa_description_id)
				$.aa_description_id = data.aa_description_id;
				
			$.datedTable = "";
			if(data.datedTable)
			{
				$.datedTable = data.datedTable;
			}
				
			if(data.auction_table)
				document.auctionTable = data.auction_table
				
			$.aa_title_id = "";
			if(data.aa_title_id)
				$.aa_title_id = data.aa_title_id;
			
			$("#datedTable").html($.datedTable);
				
			$("#buttons").show();
			
			if(data.image_folder && data.localRow.image1)
			{
				$("#image")
					.append("<a target='_blank' href='"+data.image_folder + "/" + data.localRow.image1 +"'>"+
					"<img style='border-style: none; height: 200px;' src='"+data.image_folder + "/550/" + data.localRow.image1 +"' /></a>")
					.show();
				
				document.currentSupersize = data.image_folder + "/" + data.localRow.image1;
			}
			else if(data.archive_row.image)
			{
				path = data.archive_row.image.split("/")
				filename = path.pop()
				$("#image")
					.append("<a target='_blank' href='http://emovieposter.com/"+data.archive_row.image+"'>"+
					"<img style='border-style: none; height: 200px' src='http://emovieposter.com/"+path.join("/")+"/200/"+filename+"' /></a>")
					.show()
				
				document.currentSupersize = "";
			}
			else
			{
				document.currentSupersize = "";
			}
			
			if(data.localRow && data.localRow.image2)
			{
				var extra_images = data.localRow.image2.split(";").map(String.trim)
				for(x in extra_images)
				{
					$("#image")
						.append("<a style='margin-left: 10px;' target='_blank' href='"+data.image_folder + "/" + extra_images[x] +"'>"+
						"<img style='border-style: none; height: 200px;' src='"+data.image_folder + "/550/" + extra_images[x] +"' /></a>")
						.show();
				}
			}
			
			if(data.descriptionsRow)
			{
				for(x in data.descriptionsRow)
					$("[name='"+x+"'].descriptions").val(data.descriptionsRow[x]);
				$("[name=TITLE_orig]").val(data.descriptionsRow.TITLE);
				
				if(data.descriptionsRow['IMDb URL'] != "" && data.descriptionsRow['IMDb URL'].toLowerCase().indexOf("not in imdb") == -1)
				$("#links").empty()
					.append("<a target='_blank' href=\""+htmlspecialchars(data.descriptionsRow['IMDb URL'])+"\">IMDb</a> | ")
					.append("<a target='_blank' href=\"/desc_edit/?film_title="+htmlspecialchars(encodeURIComponent(data.descriptionsRow.TITLE))+"\">Edit Desc</a> | ");
			}
			else
				$(".descriptions").val('');
				
			if(data.viewers)
			{
				$("#viewers").empty().append("<table rules='groups' border='1' style='border-collapse: collapse'><colgroup></colgroup><colgroup></colgroup><caption>Viewers <textarea style='width: 30px; height: 15px;' onclick='this.selectionStart = 0; this.selectionEnd = this.value.length' id='viewerslist'></textarea></caption></table>");
				for(x in data.viewers)
				{
					$("#viewers table").append("<tr><td>"+data.viewers[x]['name']+"</td><td>"+data.viewers[x]['email']+"</td></tr>");
				}
				
				$("#viewers").css("display", "inline-block");
			}
			else
				$("#viewers").empty().append("<table><caption>No Logged In Viewers</caption></table>").css("display", "inline-block");
			
			if(data.bidders)
			{
				$("#bidders").empty().append("<table rules='groups' border='1' style='border-collapse: collapse'><colgroup></colgroup><colgroup></colgroup><caption>Bidders <textarea style='width: 30px; height: 15px;' onclick='this.selectionStart = 0; this.selectionEnd = this.value.length' id='bidderslist'></textarea></caption></table>");
				for(x in data.bidders)
				{
					$("#bidders table").append("<tr><td>"+data.bidders[x]['name']+"</td><td>"+data.bidders[x]['email']+"</td></tr>");
				}
				
				$("#bidders").css("display", "inline-block");
			}
			else
				$("#bidders").empty().append("<table><caption>No Bids</caption></table>").css("display", "inline-block");
			
			if(data.archive_row)
				$("#delete_button").hide()
			else
				$("#delete_button").show()
			
			$(".auction").val('');
			
			if(data.localRow)
			{
				//Separate the database portion of the table from the table name portion. AK 2013-02-21
				matches = data.datedTable.match(/^`([^`]+)`\.`([^`]+)`/)
				
				$("#links")
					.append("<a target='_blank' href='http://poster-server/listing/index.php"+
						"?table="+encodeURIComponent(matches[2])+
						(matches[1] == "listing_system_archives" ? "&archived=true" : "")+
						"&autonumber="+data.localRow.autonumber+"'>Review Form</a> | ");
				
				for(x in data.localRow)
				{
					$("[name='"+x+"'].auction").val(data.localRow[x]);
				}
				
				$("[name=orig_title_45]").val(data.localRow.title_45);
			}
			
			if(data.archive_row)
			{
				var fieldsMap = {
					"lot_number" : "lot_num",
					"film_title" : "film_title",
					"image1" : "image",
					"image2" : "image2",
					"type_long" : "item_type",
					"condition_overall" : "over_cond",
					"condition_major_defects" : "major_def",
					"condition_common_defects" : "common_def",
					"title_45" : "lot_title",
					"type_code" : "about_code",
					"after_description" : "after_desc",
					"quantity" : "quantity",
					"artist" : "artist",
					"style_info_clean" : "style_info_clean",
					"style_info" : "style_info",
				}
				
				for(x in fieldsMap)
				{
					$("[name='"+x+"'].auction").val(data.archive_row[fieldsMap[x]])
				}
				
				$("[name=orig_title_45]").val(data.archive_row['lot_title'])
			}
				
			if(data.bulk_lot_row)
			{
				$(".bulk_lot_fields").show()
				for(x in data.bulk_lot_row)
				{
					$("[name='"+x+"'].bulk_lot").val(data.bulk_lot_row[x])			
				}

				titles_photographed = JSON.parse(data.bulk_lot_row.titles_photographed);
				titles_unphotographed = JSON.parse(data.bulk_lot_row.titles_unphotographed);

				for(x in titles_photographed)
				{
					params = {
						"title": titles_photographed[x].title,
						"quantity" : titles_photographed[x].quantity,
						"style_info": titles_photographed[x].style_info
					};
					
					addNewPTitle(params);
				}

				addNewPTitle();

				for(x in titles_unphotographed)
				{
					params = {
						"title": titles_unphotographed[x].title,
						"quantity" : titles_unphotographed[x].quantity,
						"style_info": titles_unphotographed[x].style_info
					};
					
					addNewRTitle(params);
				}

				addNewRTitle();
			}
			else
			{
				$(".bulk_lots").val("")
				$(".bulk_lot_fields").hide()
			}
			
			if(data.galleryRow)
			{
				/*var fieldsMap = {
					"lot_number" : "lot_num",
					"film_title" : "film_title",
					"image1" : "image",
					"image2" : "image2",
					"type_long" : "item_type",
					"condition_overall" : "over_cond",
					"condition_major_defects" : "major_def",
					"condition_common_defects" : "common_def",
					"title_45" : "lot_title",
					"type_code" : "about_code",
					"after_description" : "after_desc",
					"quantity" : "quantity",
					"artist" : "artist",
					"style_info_clean" : "style_info_clean",
					"style_info" : "style_info",
				}
				
				for(x in fieldsMap)
				{
					$("[name='"+x+"'].auction").val(data.galleryRow[fieldsMap[x]])
				}
				
				$("[name=orig_title_45]").val(data.galleryRow['lot_title'])*/
				
				$(".gallery").css("background", "").attr("disabled", false);
				
				for(x in data.galleryRow)
				{
					$("[name='"+x+"'].gallery").val(data.galleryRow[x]);
				}
				
				$("#links").append("<a target='_blank' href='http://auctions.emovieposter.com/Bidding.taf?_function=detail&Auction_uid1="+data.galleryRow.ebay_num+"'>Auction Page</a>"+
										" | <a target='_blank' href='http://www.emovieposter.com/agallery/search/"+data.galleryRow.lot_num+"/all.html'>Gallery</a> | ");
				
				if(data.mailto)
					$("#links").append(" | <a href=\"mailto:?bcc="+htmlspecialchars(data.mailto)+"\">Email To Bidders</a>");
					
				if(data.mailto_viewers)
					$("#links").append(" | <a href=\"mailto:?bcc="+htmlspecialchars(data.mailto)+","+htmlspecialchars(data.mailto_viewers)+"\">Email To All</a>");
					
				$("#links").append(" | <a href=\"javascript:void(0)\" onclick='showStarsBox()'>Stars</a>");
				
				$("#links").append("<br />");
				
				if(data.viewerslist)
					$("#viewerslist").val(data.mailto_viewers.replace(/,/g, "\n"))
				else
					$("#viewerslist").val('');
					
				if(data.bidderslist)
					$("#bidderslist").val(data.bidderslist);
				else
					$("#bidderslist").val(data.bidderslist.replace(/,/g, "\n"));
			}
			else if(data.archive_row)
			{
				$(".gallery").css("background", "").attr("disabled", false);
				
				for(x in data.archive_row)
				{
					$("[name='"+x+"'].gallery").val(data.archive_row[x]);
				}
				
				$("#links").append("<a target='_blank' href='http://auctions.emovieposter.com/Bidding.taf?_function=detail&Auction_uid1="+data.archive_row['ebay_num']+"'>Auction Page</a>"+
										" | <a target='_blank' href='http://www.emovieposter.com/agallery/archiveitem/"+data.archive_row['rand_id']+".html'>Gallery</a> | ");
				
				if(data.mailto)
					$("#links").append(" | <a href=\"mailto:?bcc="+htmlspecialchars(data.mailto)+"\">Email To Bidders</a>");
					
				if(data.mailto_viewers)
					$("#links").append(" | <a href=\"mailto:?bcc="+htmlspecialchars(data.mailto)+","+htmlspecialchars(data.mailto_viewers)+"\">Email To All</a>");
					
				$("#links").append(" | <a href=\"javascript:void(0)\" onclick='showStarsBox()'>Stars</a>");
				
				$("#links").append("<br />");
				
				if(data.viewerslist)
					$("#viewerslist").val(data.mailto_viewers.replace(/,/g, "\n"))
				else
					$("#viewerslist").val('');
					
				if(data.bidderslist)
					$("#bidderslist").val(data.bidderslist.replace(/,/g, "\n"));
				else
					$("#bidderslist").val('');
			}
			else
				$(".gallery").attr("disabled", true).css("background", "grey").val('');
			
			if(data.archive_row)
			{
				$("[name=style_info_clean]").val(data.archive_row['style_info_clean']).prop("disabled", false).css("background", "tomato")
			}
			else
			{
				$("[name=style_info_clean]").val("").prop("disabled", true)
			}
			
			if(data.info_notes && data.info_notes.length > 0)
			{
				$.info_notes_box.load_data(data.info_notes);
				$.info_notes_box.show($("[name=type_code]").val(), false);
			}
			else
			{
				$.info_notes_box.load_data([]);
				$.info_notes_box.show();
			}
			
			update_title_45_length();
			
			$("#mrlistertitles table").remove();
			
			if(data.MrListerTitles)
			{
				obj = $("<table />").css("width", "100%");
				for(x in data.MrListerTitles)
				{
					$(obj).append("<tr>"+
						"<td style='min-width:14em'><input type='text' tabindex='500' class='nocontext' style='width:100%' value=\""+data.MrListerTitles[x][0]+"\" /></td>"+
						"<td style='width:9em'><input type='text' class='nocontext' tabindex='500' style='width:100%' value=\""+data.MrListerTitles[x][1]+"\" /></td>"+
						"<td style='width:9em'><input type='text' class='nocontext' tabindex='500' style='width:100%' value=\""+data.MrListerTitles[x][2]+"\" /></td>"+
						"<td><a style='color:red; font-weight: bold;' href='javascript:void(0);' onclick='$(this).parents(\"tr:first\").remove();' >x</a></td></tr>");
				}
			}
			else
				$("#mrlistertitles").append("<table />").css("width", "100%");

			$("#mrlistertitles").append(obj);
			
			function addNewField()
			{
				newField = $("<tr><td style='min-width:14em'><input type='text' tabindex='500' style='width:100%' class='nocontext' value='' /></td><td style='width:9em'><input type='text' class='nocontext' tabindex='500' style='width:100%' value='' /></td><td>&nbsp;</td></tr>");
				
				$(newField).find("input")
					.blur(function(){
						//If we have a blank row, don't make another one!
						$.cancelNewField = false;
						$("#mrlistertitles tr").each(function(){
							if($(this).find("input:eq(0)").val() == "" && $(this).find("input:eq(1)").val() == "")
								$.cancelNewField = true;
						});
						
						if(!$.cancelNewField)
							addNewField();
					});
				
				$("#mrlistertitles table").append(newField);
			}
			
			addNewField();
			
			$("#repronotes table").remove();
			
			if(data.reproNotes)
			{
				obj = $("<table><tr><td>type_code</td><td>comments</td><td>link</td></tr></table>").css("width", "100%");
				for(x in data.reproNotes)
					$(obj).append("<tr><td style='width:9em'><textarea type='text' class='nocontext type_code' tabindex='500' style='width:100%'>"+data.reproNotes[x][1]+"</textarea></td><td><textarea tabindex='500' style='width:100%'>"+data.reproNotes[x][2]+"</textarea></td><td><textarea type='text' class='nocontext' tabindex='500' style='width:100%'>"+data.reproNotes[x][3]+"</textarea></td><td><a style='color:red; font-weight: bold;' href='javascript:void(0);' onclick='$(this).parents(\"tr:first\").remove();' >x</a></td></tr>");
					$("#repronotes").append(obj);
			}
			else
				$("#repronotes").append("<table><tr><th>type_code</th><th>comments</th><th>link</th></tr></table>").css("width", "100%");
			
			function addNewReproField()
			{
				newField = $("<tr><td style='width: 33%'><textarea type='text' class='nocontext type_code' tabindex='500' style='width:100%'></textarea></td><td style='width: 33%'><textarea tabindex='500' style='width:100%'></textarea></td><td style='width: 33%'><textarea type='text' class='nocontext' tabindex='500' style='width:100%'></textarea></td></tr>");
				$(newField).find("textarea").blur(function(){
					//If we have a blank row, don't make another one!
					$.cancelNewReproField = false;
					$("#repronotes tr:not(:eq(0))").each(function(){
						if($(this).find("textarea:eq(0)").val() == "" && $(this).find("textarea:eq(1)").val() == "")
							$.cancelNewReproField = true;
					});
					
					if(!$.cancelNewReproField)
						addNewReproField();
				});
				$("#repronotes table").append(newField);
			}
			
			addNewReproField();
			
			$(".type_code")
				.autocomplete("suggest.php", {
					minChars: 0, 
					selectFirst: false,
					cacheLength: 0, 
					max: 110,
					delay: 10, 
					extraParams: {
						field: "type_code",
					},
				});
			
			if('differences' in data && data.differences.length > 0)
			{
				$("#save_button").focus()
					
				div = $("<div />")
					.css({
						overflow: "auto",
					})
					.append(
						$("<p />")
							.html("Some information differs between the local table and the website. I will be using the version on the website.")
					)
				
				for(x in data.differences)
				{
					$("<table />")
						.attr("border", "1")
						.css({
							"marginTop" : "10px",
						})
						.append(
							$("<caption />")
								.html(data.differences[x]['local_field'] + " / " + data.differences[x]['gallery_field'])
						)
						.append(
							"<tr><th>Local</th><th>Website</th></tr>"
						)
						.append(
							$("<tr />")
								.append(
									$("<td />")
										.css({
											"whiteSpace" : "pre-wrap",
										})
										.html(data.differences[x]['local'])
								)
								.append(
									$("<td />")
										.css({
											"whiteSpace" : "pre-wrap",
										})
										.html(data.differences[x]['gallery'])
								)
						)
						.appendTo(div)
				}
				
				$(div).dialog({
					title: "Differences",
					width: 900,
					height: 300,
				})
			}
			
			/*
				Get original row information
			*/
			$.origLocalRow = $(".auction").createValueArray()
			$.origGalleryRow = $(".gallery").createValueArray()
			$.origDescriptionsRow = $(":input.descriptions:not([name=TITLE_orig])").createValueArray()
			$.origBulkLotRow = $(".bulk_lot").createValueArray()
			$.origBulkLotRow['titles_photographed'] = JSON.stringify(assemble_titles_photographed())
			$.origBulkLotRow['titles_unphotographed'] = JSON.stringify(assemble_titles_unphotographed())
			$.lastMrListerTitles = get_mrlisters()
			$.lastReproNotes = get_repro_notes()
		},
		error: function(xhr, status){
			if(status == "abort")
				return false
			
		  alert("Unknown error getting item!");
		},
	});
}

function update_record()
{
	$("#messages").empty()
	
	if($.box)
		$.box.getContent().find("input").after("<img src='graphics/indicator.gif' />");
		
	if($.info_notes_box.unsaved_changes_exist())
		$.info_notes_box.saveAll();
		
	//Work on repro notes
	var reproNotes = [];
	$("#repronotes tr:not(:eq(0))").each(function(){
		//If the repro note is blank, don't add it to the array of values to insert
		if($(this).find("textarea:eq(0)").val() != "")
			reproNotes.push([$(this).find("textarea:eq(0)").val(), $(this).find("textarea:eq(1)").val(), $(this).find("textarea:eq(2)").val()]);
	});
	
	$.ajax({
		url: "reproNotes.php",
		async: false,
		type: "post",
		dataType: "json",
		data: "data=" + encodeURIComponent(JSON.stringify(reproNotes)) + "&film_title=" + encodeURIComponent($("[name='film_title']").val()),
		success: function(data){
			if('success' in data)
				return true;
			else if("error" in data)
				alert(data.error);
			else
				alert("An unknown error occurred. Your error code is CdsJJ79T5mE6UU6dO57lHL7FLD6F1hZC09fi9mllYa69d8SYF.\nHave a nice day.");
		},
		error: function(XMLHttpRequest, textStatus, errorThrown){
			alert("Unknown error editing repro notes.");
		}
	});		
	
	
	//Work on mrlister titles
	var data = [];
	$("#mrlistertitles tr").each(function(){
		//If the mrLister is blank, don't add it to the array of values to insert
		if($(this).find("input:eq(0)").val() != "")
			data.push([$(this).find("input:eq(0)").val(), $(this).find("input:eq(1)").val(), $(this).find("input:eq(2)").val()]);
	});
	
	if(JSON.stringify(data).indexOf("undefined") !== -1)
	{
		alert("Matt, get Aaron!. There is some sort of issue with the MrLister titles.");
	}
	
	$.ajax({
		url: "mrLister.php",
		async: false,
		type: "POST",
		dataType: "json",
		data: "data=" + encodeURIComponent(JSON.stringify(data)) + "&film_title=" + encodeURIComponent($("[name='film_title']").val()),
		success: function(data){
			if('success' in data)
				return true;
			else if("error" in data)
				alert(data.error);
			else
				alert("An unknown error occurred. Your error code is CdsJJ79T5mE6UU6dO57lHL7FLD6F1hZC09fi9mllYa69d8SYF.\nHave a nice day.");
		},
		error: function(XMLHttpRequest, textStatus, errorThrown){
			alert("Unknown error occurred editing mrlister titles.");
		}
	});

	//Work on the normal data
	localData = JSON.stringify($(".auction").createValueArray());
	galleryData = JSON.stringify($(".gallery").createValueArray());
	descriptionsData = JSON.stringify($(":input.descriptions").createValueArray());
	if($.bulk_lot)
	{
		
		bulkLotData = $(".bulk_lot").createValueArray()
		bulkLotData['titles_photographed'] = JSON.stringify(assemble_titles_photographed())
		bulkLotData['titles_unphotographed'] = JSON.stringify(assemble_titles_unphotographed())
	}
	
    info_notes_data = ($.info_notes_box.data_has_changed() ? 
		"&info_notes=" + encodeURIComponent($.info_notes_box.fetch_data()) + "&info_notes_original=" + encodeURIComponent($.info_notes_box.fetch_original_data())
		: "");
	
	$.ajax({
		url: "insert.php",
		type: "post",
		dataType: "json",
		data: "localData="+encodeURIComponent(localData)+(($.origGalleryRow) ? "&galleryData="+encodeURIComponent(galleryData) : "")+
				"&descriptionsData="+encodeURIComponent(descriptionsData)+"&day="+encodeURIComponent($.day)+
				"&shipping="+encodeURIComponent($.shipping_id)+"&specialNotesDate="+encodeURIComponent($.specialNotesDate)+
				"&datedTable="+encodeURIComponent($.datedTable)+"&aa_description_id="+encodeURIComponent($.aa_description_id)+
				"&aa_title_id="+encodeURIComponent($.aa_title_id)+"&bulk_lot="+$.bulk_lot+($.bulk_lot ? "&bulkLotData="+encodeURIComponent(JSON.stringify(bulkLotData)) : "")+
				((info_notes_data) ? info_notes_data : ""),
		success: function(data){
		  $.box.getContent().find("img").remove();
		  
		  if('messages' in data)
		  {
		  	for(x in data.messages)
			{
				$(alert_box(data.messages[x]))
					.appendTo("#messages")
			}
		  }
		  
		  if('success' in data)
		  {
		  	
		  }
		  else if('error' in data)
		  	alert("Something bad happened." + data.error);
		  else
		  	alert("Something strange happened in insert.php.\n"+JSON.stringify(data));
			
		  if($.box)
			$.box.hideAndUnload();
		  
		  get_item(document.last_item_id, $("#loading"), null, document.last_dated_table, 1);
		  
		  if($.origGalleryRow)
			search_title_type($.origGalleryRow['film_title'], $.origGalleryRow['about_code'])
		  
		  $("#search").focus()
		  
		},
		error: function(){
		  alert("Unknown error in insert.php");
		},
		
	
	});
}

function update_title_45_length()
{
	$("[name=title_45_length]").html($("[name=title_45].auction").val().length);
}

function save_record()
{
	if($.info_notes_box.unsaved_changes_exist())
		$.info_notes_box.saveAll();
		
	localData = $(".auction").createValueArray();
	galleryData = $(".gallery").createValueArray();
	descriptionsData = $(":input.descriptions:not([name=TITLE_orig])").createValueArray();
	bulkLotData = $(".bulk_lot").createValueArray();
	bulkLotData['titles_photographed'] = JSON.stringify(assemble_titles_photographed())
	bulkLotData['titles_unphotographed'] = JSON.stringify(assemble_titles_unphotographed());
	
	changes = {'descriptionsData':{}, 'galleryData':{}, 'localData':{}, 'bulkLotData':{}};
	
	if($.origLocalRow)
	{
		for(x in localData)
		{
			if(localData[x] != $.origLocalRow[x])
				changes['localData'][x] = {'from':$.origLocalRow[x],'to':localData[x]};
		}
	}
	
	if($.origGalleryRow)
	{
		for(x in galleryData)
		{
			if(galleryData[x] != $.origGalleryRow[x])
				changes['galleryData'][x] = {'from':$.origGalleryRow[x], 'to':galleryData[x]};
		}
	}
	
	if($.origDescriptionsRow)
	{
		for(x in descriptionsData)
		{
			if($.origDescriptionsRow && descriptionsData[x] != $.origDescriptionsRow[x])
				changes['descriptionsData'][x] = {'from':$.origDescriptionsRow[x], 'to':descriptionsData[x]};
		}
	}
	
	if($.origBulkLotRow)
	{
		for(x in bulkLotData)
		{
			if($.origBulkLotRow && bulkLotData[x] != $.origBulkLotRow[x])
			{
				changes['bulkLotData'][x] = {'from':$.origBulkLotRow[x], 'to':bulkLotData[x]}
			}
		}
	}	
	
   	mrlistersComparison = get_mrlisters()
	reproNotesComparison = get_repro_notes()
	
	if(
		JSON.stringify(changes) != JSON.stringify({'descriptionsData':{}, 'galleryData':{}, 'localData':{}, 'bulkLotData':{}}) ||
		JSON.stringify(mrlistersComparison) != JSON.stringify($.lastMrListerTitles) ||
		JSON.stringify(reproNotesComparison) != JSON.stringify($.lastReproNotes) ||
		$.info_notes_box.data_has_changed()
	)
	{
		content = $("<div style='width: 800px; max-height: 400px; overflow: auto;'></div>");
		if(JSON.stringify(changes.descriptionsData) != "{}")
		{
			table = $("<table class='changes' border='1'><caption>Descriptions Fields</caption><tr><th>Field</th><th>From</th><th>To</th></tr></table>");
			for(x in changes.descriptionsData)
			{
				$(table).append("<tr><td>"+htmlspecialchars(x)+"</td><td><div>"+nl2br(htmlspecialchars(changes.descriptionsData[x]['from']))
								+"</div></td><td><div>"+nl2br(htmlspecialchars(changes.descriptionsData[x]['to']))+"</div></td></tr>");
			}
			
			$(content).append(table);
		}
		
		if(JSON.stringify(changes.bulkLotData) != "{}")
		{
			table = $("<table class='changes' border='1'><caption>Bulk Lot Fields</caption><tr><th>Field</th><th>From</th><th>To</th></tr></table>");
			for(x in changes.bulkLotData)
			{
				$(table).append("<tr><td>"+htmlspecialchars(x)+"</td><td><div>"+nl2br(htmlspecialchars(changes.bulkLotData[x]['from']))
								+"</div></td><td><div>"+nl2br(htmlspecialchars(changes.bulkLotData[x]['to']))+"</div></td></tr>");
			}
			
			$(content).append(table);
		}
		
		if(JSON.stringify(changes.galleryData) != "{}")
		{
			table = $("<table class='changes' border='1'><caption>Online Fields</caption><tr><th>Field</th><th>From</th><th>To</th></tr></table>");
			for(x in changes.galleryData)
			{
				$(table).append("<tr><td>"+htmlspecialchars(x)+"</td><td><div>"+nl2br(htmlspecialchars(changes.galleryData[x]['from']))
								+"</div></td><td><div>"+nl2br(htmlspecialchars(changes.galleryData[x]['to'])+"</div></td></tr>"));
			}
			
			$(content).append(table);
		}
		
		if(JSON.stringify(changes.localData) != "{}")
		{
			table = $("<table class='changes' border='1'><caption>Local/Online Fields</caption><tr><th>Field</th><th>From</th><th>To</th></tr></table>");
			
			for(x in changes.localData)
			{
				$(table).append("<tr><td>"+htmlspecialchars(x)+"</td><td><div>"+nl2br(htmlspecialchars(changes.localData[x]['from']))
								+"</div></td><td><div>"+nl2br(htmlspecialchars(changes.localData[x]['to'])+"</div></td></tr>"));
			}
			
			$(content).append(table);
		}
		
		$.hi = JSON.stringify(mrlistersComparison)
		$.hi2 = JSON.stringify($.lastMrListerTitles)
		
		if(JSON.stringify(mrlistersComparison) !== JSON.stringify($.lastMrListerTitles))
			$(content).append("<p>Changes made to MrLister Titles</p>");
		
		if(JSON.stringify(reproNotesComparison) != JSON.stringify($.lastReproNotes))
			$(content).append("<p>Changes made to Repro Notes</p>");
			
		if($.info_notes_box.data_has_changed())
			$(content).append("<p>Changes made to Info Notes</p>");
		
		$(content).append("<br /><input type='button' style='width: auto' onclick='update_record();' value='   Save Record   ' />");
	}
	else
	{
		content = $("<div style='text-align: center;'></div>");
		
		$(content).append("You didn't change anything. Save anyway?"+
			"<br /><input type='button' style='width: auto' "+
			"onclick='update_record();' id='save_button2' value='Save' />");
	}
	
	$.box = new Boxy(content, {modal: true, title: "Changes to be made"});
	
	$.box.boxy.find("input:last").focus()
}

function getDesc(film_title)
{
	if(!film_title)
		film_title = $("[name=film_title]").val();
		
	if($.lastDescTitle === film_title)
	{
		return false;
	}	
		
	$.lastDescTitle = film_title;
	
	$.ajax({
		url: "getDesc.php",
		type: "post",
		async: false,
		dataType: "json",
		beforeSend: function(){
			$("#description_loading").empty().append("<img src='graphics/indicator.gif' />");
		},
		data: "film_title=" + encodeURIComponent(film_title),
		success: function(data) {
			$("#description_loading").empty();
			if('error' in data)
			{
				alert(data.error);
				return false;
			}
			
			if(data.norecord && data.norecord == 1)
			{
				$(".descriptions").val('');
				$("#mrlistertitles table").remove();
				$("#repronotes table").remove();
				return true;
			}
			
			if(data.descriptionsRow)
			{
				for(x in data.descriptionsRow)
					$("[name='"+x+"'].descriptions").val(data.descriptionsRow[x]);
				$("[name=film_title], [name=TITLE_orig]").val(data.descriptionsRow.TITLE);
			}
			else
				$(".descriptions").val('');
			
			if(data.info_notes && data.info_notes.length > 0)
			{
				$.info_notes_box.load_data(data.info_notes);
				$.info_notes_box.show($("[name=type_code]").val(), false);
			}
			else
			{
				$.info_notes_box.load_data([]);
				$.info_notes_box.show();
			}
			
			
			$("#mrlistertitles table").remove();
			if(data.MrListerTitles)
			{
				obj = $("<table />").css("width", "100%");
				for(x in data.MrListerTitles)
				{
					$(obj).append("<tr><td style='min-width:14em'><input type='text' tabindex='500' class='nocontext' style='width:100%' value='"+data.MrListerTitles[x][0]+"' /></td>"+
						"<td style='width:9em'><input type='text' class='nocontext' tabindex='500' style='width:100%' value='"+data.MrListerTitles[x][1]+"' /></td>"+
						"<td style='width:9em'><input type='text' class='nocontext' tabindex='500' style='width:100%' value=\""+data.MrListerTitles[x][2]+"\" /></td>"+
						"<td><a style='color:red; font-weight: bold;' href='javascript:void(0);' onclick='$(this).parents(\"tr:first\").remove();' >x</a></td></tr>");
				}
			}
			else
				$("#mrlistertitles").append("<table />").css("width", "100%");

			$("#mrlistertitles").append(obj);
			
			function addNewField()
			{
				newField = $("<tr><td style='min-width:14em'><input type='text' tabindex='500' style='width:100%' class='nocontext' value='' /></td><td style='width:9em'><input type='text' class='nocontext' tabindex='500' style='width:100%' value='' /></td><td>&nbsp;</td></tr>");
				$(newField).find("input").blur(function(){
					//If we have a blank row, don't make another one!
					$.cancelNewField = false;
					$("#mrlistertitles tr").each(function(){
						if($(this).find("input:eq(0)").val() == "" && $(this).find("input:eq(1)").val() == "")
							$.cancelNewField = true;
					});
					
					if(!$.cancelNewField)
						addNewField();
				});
				$("#mrlistertitles table").append(newField);
			}
			
			addNewField();
			
			$("#repronotes table").remove();
			
			if(data.reproNotes)
			{
				obj = $("<table><tr><td>type_code</td><td>comments</td><td>link</td></tr></table>").css("width", "100%");
				for(x in data.reproNotes)
					$(obj).append("<tr><td style='width:9em'><textarea type='text' class='nocontext type_code' tabindex='500' style='width:100%'>"+data.reproNotes[x][1]+"</textarea></td><td><textarea tabindex='500' style='width:100%'>"+data.reproNotes[x][2]+"</textarea></td><td><textarea type='text' class='nocontext' tabindex='500' style='width:100%'>"+data.reproNotes[x][3]+"</textarea></td><td><a style='color:red; font-weight: bold;' href='javascript:void(0);' onclick='$(this).parents(\"tr:first\").remove();' >x</a></td></tr>");
					$("#repronotes").append(obj);
			}
			else
				$("#repronotes").append("<table><tr><th>type_code</th><th>comments</th><th>link</th></tr></table>").css("width", "100%");
			
			function addNewReproField()
			{
				newField = $("<tr><td style='width: 33%'><textarea type='text' class='nocontext type_code' tabindex='500' style='width:100%'></textarea></td><td style='width: 33%'><textarea tabindex='500' style='width:100%'></textarea></td><td style='width: 33%'><textarea type='text' class='nocontext' tabindex='500' style='width:100%'></textarea></td></tr>");
				$(newField).find("textarea").blur(function(){
					//If we have a blank row, don't make another one!
					$.cancelNewReproField = false;
					$("#repronotes tr:not(:eq(0))").each(function(){
						if($(this).find("textarea:eq(0)").val() == "" && $(this).find("textarea:eq(1)").val() == "")
							$.cancelNewReproField = true;
					});
					
					if(!$.cancelNewReproField)
						addNewReproField();
				});
				$("#repronotes table").append(newField);
			}
			
			addNewReproField();
			
			$(".type_code").autocomplete("suggest.php", {
				minChars: 0, 
				selectFirst: false,
				cacheLength: 0, 
				max: 110,
				delay: 10, 
				extraParams: {
					field: "type_code",
				},
			});
			
			$.lastMrListerTitles = get_mrlisters()
			$.lastReproNotes = get_repro_notes()
			$.origDescriptionsRow = $(":input.descriptions:not([name=TITLE_orig])").createValueArray()
			
		},
		error: function(xhr, status) {
			if(status == "abort")
				return false
			
			alert("Unknown error getting description!");
		},
	});
}


function delete_record_confirm()
{
	Boxy.confirm("<div><h2>You are going to cancel:</h2><h1>"+htmlspecialchars($("[name=orig_title_45]").val())+"</h1><h2>Continue?</h2></div>", function(){cancel_item();}, {title: "Cancel an item"});
}

function cancel_item()
{
	$.cancelItemBoxy = new Boxy("<div>Which auction table do you want to move this item to?<br />"+
		$.auction_choice+"<br /><br /><label>Why was this item cancelled?</label><textarea id='cancellation_reason'></textarea><input type='button' onclick='really_cancel_item()' style='width: auto; display: inline;' value='    Continue   ' />"+
		"<span id='loading_delete' /></div>", {title: "Choose an auction table"});
}

function really_cancel_item()
{
	$.ajax({
	url: "delete.php",
	type: "post",
	dataType: "json",
	beforeSend: function(){
		$("#loading_delete").empty().append("&nbsp;<img src='graphics/indicator.gif' />&nbsp;<small>This can take up to 1 minute.</small>");
	},
	data: "ebay_num="+encodeURIComponent($("[name=ebay_num]").val())+"&title="+encodeURIComponent($("[name=title_45]").val())+
			"&to_table="+encodeURIComponent($("#tables :selected").val())+"&gallery_id="+encodeURIComponent($("[name=id].gallery").val())+
			"&cancel_reason="+encodeURIComponent($("#cancellation_reason").val())+
			"&auction_id="+encodeURIComponent($("[name=id].auction").val())+"&lot_num="+encodeURIComponent($("[name=lot_number]").val()),
	success: function(data){
		$.cancelItemBoxy.unload();
	  
	 	$(".auction, .descriptions, .gallery").val('');
		$("#mrlistertitles table, #repronotes table").remove();
		$("#links").empty();
		$("#bidders").empty().hide();
		update_title_45_length();
		$(".links").find("img").remove();

	  
	  if("error" in data)
	  {
	  	alert(data.error);
		return false;
	  }
	  
	  if("message" in data)
	  {
	  	if(data.message != "")
		{
			$(alert_box(data.message))
				.appendTo("#messages")
		}
		else
		{
			$(alert_box("Success deleting this item"))
				.appendTo("#messages")
		}
		
		if($.origGalleryRow)
			search_title_type($.origGalleryRow['film_title'], $.origGalleryRow['about_code'])
		
		return false;
	  }
	  
	  if("success" in data)
	  	alert("Successfully deleted item '"+data.item+"'");
	  else
	  	alert("There was an unknown error deleting the item!");		
	},
	error: function(req, textStatus, errorThrown){
	  alert("There was an unknown error deleting the item! \n"+req.responseText+"\n"+textStatus+"\n"+errorThrown);
	},
	
});
}

function showStarsBox()
{
	return open_stars_box($("[name=film_title]").val(), $("[name='eBay Description']").val())
}


function get_reshoots()
{
	if($.datedTable)
	{
		auction_table = $.datedTable.match(/`(.*)`\.`(.*)`/)
	}
	else
	{
		Reshoots.get(Reshoots.create_get_handler(), "0")
		return true
	}	

	if(auction_table[1] == "listing_system")
	{
		Reshoots.get(Reshoots.create_get_handler({
			auction_table : auction_table[2], 
			id : $.origLocalRow['id']
		}), "0")
	}
	else
		Reshoots.get(Reshoots.create_get_handler(), "0")
}

function poll_reshoot()
{
	Reshoots.poll(function(data){
		if(data.rows)
		{
			$("#reshoots").css("border", "1px solid red")
		}
		else
		{				
			$("#reshootst").css("border", "")
		}
	})
}

function get_mrlisters()
{
	//Mrlisters
	var mrlisters = [];
	var mrlistersComparison = [];
	$("#mrlistertitles tr").each(function(){
		//If the mrLister is blank, don't add it to the array of values to insert
		if($(this).find("input:eq(0)").val() != "")
		{
			mrlisters.push([$(this).find("input:eq(0)").val(), $(this).find("input:eq(1)").val(), $(this).find("input:eq(1)").val()]);
			mrlistersComparison.push([
				htmlspecialchars($(this).find("input:eq(0)").val()),
				htmlspecialchars($(this).find("input:eq(1)").val()),
				htmlspecialchars($(this).find("input:eq(2)").val())
			]);
		}
	});
	
	return mrlistersComparison
}


function get_repro_notes()
{
	//Repro notes
	var reproNotes = [];
	var reproNotesComparison = [];
	$("#repronotes tr:not(:eq(0))").each(function(){
		//If the repro note is blank, don't add it to the array of values to insert
		if($(this).find("textarea:eq(0)").val() != "")
		{
			reproNotes.push([$(this).find("textarea:eq(0)").val(), $(this).find("textarea:eq(1)").val(), $(this).find("textarea:eq(2)").val()]);
			reproNotesComparison.push([
				htmlspecialchars($("[name=film_title]").val()),
				htmlspecialchars($(this).find("textarea:eq(0)").val()),
				htmlspecialchars($(this).find("textarea:eq(1)").val()),
				htmlspecialchars($(this).find("textarea:eq(2)").val())
			]);
		}
	});
	
	return reproNotesComparison
}