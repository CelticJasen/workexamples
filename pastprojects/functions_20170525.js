$.session = {};

$.backgrounds = ["bg0.png","bg1.png","bg2.png","bg3.png","bg4.png","bg5.png","bg6.png","bg7.png","marmolata_faded.jpg"];
$.fieldLocks = {"book_cover" : {},"bag":{after_last:true},"pieces":{after:true},"minilc":{},"numbered":{},
				"color":{},"papertype":{},"quantity":{}, "how_rolled":{}, "cut":{}, 
				"countryoforigin":{}, "doublesided":{}, "pages":{}, "spanus":{after:true}, 
				"canadianus":{}, "engus":{}, "measurements":{}, "additionaldetails":{}, "type_of_record" : {immediately_after: true},
				"film_title" : {immediately_after: true}, "language" : {immediately_after:true}, "nationality" : {}, 
				"xrated":{immediately_after: true}, "advertising":{}, "listing_notes":{}, "benton":{after:true}, "glued":{after:true}, "taped":{after:true},
				"keybook":{}, "newsstill":{}};
for(x in $.fieldLocks)
	$.fieldLocks[x].locked = false;
$.scrollShots = true;
$.reverseScroll = false;

if($.cookie("shotsPerItem"))
{
	$.shotsPerItem = $.cookie("shotsPerItem");
}
else
{
	$.cookie("shotsPerItem", 1);
	$.shotsPerItem = 1;
}

jQuery.fn.createValueArray = function(){
	$.values = {};
	this.each(function(){
		switch($(this).attr("type"))
		{
			case "radio":
				if($(this).prop("checked"))
					$.values[$(this).attr("name")] = $(this).val();
				break;
			case "checkbox":
				$.values[$(this).attr("name")] = (($(this).prop("checked")) ? 1 : 0);
				break;
			default:
				$.values[$(this).attr("name")] = $(this).val();
				break;
		};
	});
	return $.values;
}


function film_description_keyup()
{
	size = $("[name='eBay Description']").val().replace(/<\/?([a-z][a-z0-9]*)\b[^>]*>?/gi, '').length
	
	if(size > 4200)
	{
		if($("#messages .sizewarning1").length)
			$("#messages .sizewarning1").remove()
			
		if($("#messages .sizewarning2").length)
			$("#messages .sizewarning2").remove()
		
		if($("#messages .sizewarning3").length == 0)
			$("#messages").append($("<span class='sizewarning3'>Shrink that gigantic description!</span>")).show()
	}
	else if(size > 3800)
	{
		if($("#messages .sizewarning2").length)
			$("#messages .sizewarning2").remove()
			
		if($("#messages .sizewarning3").length)
			$("#messages .sizewarning3").remove()
		
		if($("#messages .sizewarning1").length == 0)
			$("#messages").append($("<span class='sizewarning1'>Description too much big. Add smallness.</span>")).show()
	}
	else if(size > 3400)
	{
		if($("#messages .sizewarning1").length)
			$("#messages .sizewarning1").remove()
			
		if($("#messages .sizewarning3").length)
			$("#messages .sizewarning3").remove()
		
		if($("#messages .sizewarning2").length == 0)
		{
			$("#messages").append($("<span class='sizewarning2'>Mighty big description you have there. Shrink it maybe?</span>")).show()
		}
	}
	else
	{
		if($("#messages .sizewarning1, #messages .sizewarning2, #messages .sizewarning3").length)
			$("#messages .sizewarning1, #messages .sizewarning2, #messages .sizewarning3").remove()
	}
}

$(document)
	.keyup(function(){
		document.lastkeypress = new Date().getTime()
	})
	.mousemove(function(){
		document.lastkeypress = new Date().getTime()
	})

$(document).ready(function(){
	setInterval("updateLocks()", 5000);
	
	window.onbeforeunload = function() {
    if($("#consignor_warning").length){
      return 'You have unsaved work. Are you sure?'
    }
    return;
  };
	
	if(!$.cookie("bg"))
		$.cookie("bg", 0, {expires: 30});
		
	$.info_notes_box = new Info_Notes_Box($("#info_notes_widget"));
	$.info_notes_box.set_height_range(50, 202, 202);
	
	$(document.body).css("backgroundImage", "url('graphics/"+$.backgrounds[$.cookie("bg")]+"')");
	
	$("#navButtons img").mouseover(function(){$(this).css("border", "thin solid black");}).mouseout(function(){$(this).css("border", "");});
	$(".yellowHover").mouseover(function(){$(this).css("border", "thin solid yellow");}).mouseout(function(){$(this).css("border", "");});
	
	set_box_autocompletes()
	
	$("#topPane [name='photography_folder']")
		.blur(function(){
			this.value = this.value.toUpperCase()
		})
		.autocomplete2({
			delay: 100,
			source: "/includes/suggest2.php?name=photography_folder2",
		})
	
	$("#topPane [name='photography_folder']")
		.data("ui-autocomplete2")._renderItem = photography_folder_suggestion_renderer
	
	$("[name='auction_code']")
		.autocomplete2({
			delay: 100,
			source: "/includes/suggest2.php?name=auction_code",
			focus: function(event, ui) {
				get_auction_code_info(ui.item.value)
			},
			change: function (event, ui) {
				if($(this).data("list")[$(this).val().toLowerCase()])
				{
					if($(this).data("list")[$(this).val().toLowerCase()] != $(this).val())
						$(this).val($(this).data("list")[$(this).val().toLowerCase()])
				}
                else if(!ui.item)
				{
                    $(this).val("");
                }
            },
			response: function (event, ui){
				var list = []
				
				for(x in ui.content)
				{
					list[ui.content[x].value.toLowerCase()] = ui.content[x].value
				}
				
				$(this).data("list", list)
			}
		})
		.blur(function(){
			get_auction_code_info(this.value)
		})
	
	
	$("#topPane [name='type_code']")
		.autocomplete2({
			delay: 100,
			source: "/includes/suggest2.php?name=type_code2",
			focus: function(event, ui){
				get_search_field(ui.item.value, $("#extraInfo").get());
			},
			change: function (event, ui) {
				
				if($(this).data("list")[$(this).val().toLowerCase()])
				{
					if($(this).data("list")[$(this).val().toLowerCase()] != $(this).val())
						$(this).val($(this).data("list")[$(this).val().toLowerCase()])
				}
                else if(!ui.item)
				{
                    $(this).val("");
                }
            },
			response: function (event, ui){
				var list = []
				
				for(x in ui.content)
				{
					list[ui.content[x].value.toLowerCase()] = ui.content[x].value
				}
				
				$(this).data("list", list)
			}
		})
		.blur(function(){
				$("#extraInfo").hide(350)
				$.session.demonym = get_demonym(this.value);
				get_style_info_form();
		})
		.focus(function(){
			get_search_field(this.value, $("#extraInfo").get());
			});
		
	$("#topPane [name='consignor']")
		.autocomplete2({
			delay: 100,
			source: "/includes/suggest2.php?name=consignor",
			change: function (event, ui) {
				
				if($(this).data("list")[$(this).val().toLowerCase()])
				{
					if($(this).data("list")[$(this).val().toLowerCase()] != $(this).val())
						$(this).val($(this).data("list")[$(this).val().toLowerCase()])
				}
                else if(!ui.item)
				{
                    $(this).val("");
                }
            },
			response: function (event, ui){
				var list = []
				
				for(x in ui.content)
				{
					list[ui.content[x].value.toLowerCase()] = ui.content[x].value
				}
				
				$(this).data("list", list)
			}
		})
	
	$("#topPane [name='consignor']").data("ui-autocomplete2")._renderItem = consignor_suggestion_renderer
	
	$("[name=film_title]")
		.blur(function(){
			getDesc()
		})
		.change(function(){
			getDesc()
		})
	
	$("[name=who_did]").val($.userId);
	
	$("[name='Year/NSS']").keyup(function(){
		if(document.check_year_field_timeout)
			clearTimeout(document.check_year_field_timeout)
		
		document.check_year_field_timeout = setTimeout("check_year_field()", 400)
	})
	
	$("[name=Studio]").keyup(function(){
		if(document.check_studio_field_timeout)
			clearTimeout(document.check_studio_field_timeout)
		
		document.check_studio_field_timeout = setTimeout("check_studio_field()", 400)
	})
	
	$("[name=artist].descriptions")
		.keyup(function(){
			if(document.check_artist_field_timeout)
				clearTimeout(document.check_artist_field_timeout)
			
			document.check_artist_field_timeout = setTimeout("check_artist_field()", 400)
		})
		
   $("[name=PhotographyFolderDoD]").click(function(){
      if($("[name=setPhotographyFolder]").val().indexOf(' DO OR DIE') === -1){
        $("[name=setPhotographyFolder]").val($("[name=setPhotographyFolder]").val() + " DO OR DIE")
    }else{
      $("[name=setPhotographyFolder]").val($("[name=setPhotographyFolder]").val().replace(' DO OR DIE',''))
    }
  })
	
	$("#setBox [type=button]").unbind('click').click(function(){
		start_set();
	});	
	
	$("[name='eBay Description']")
		.tinymce({
			script_url : 'tiny_mce/tiny_mce.js',
			theme : "notheme",
			skin: "default",
			content_css : "mce.css",
			forced_root_block: "",
			gecko_spellcheck: true,
			oninit: function(){
				$.tinymce_is_loaded = true;
				iframe = $("[name='eBay Description']").next().find("iframe")[0];
				$(iframe).attr("tabindex", 600).css("border", "1px solid rgb(127,157,185)");
				
				iframe.contentWindow.document.myIframe = iframe;
				
				$(iframe.contentWindow.document)
					.focus(function(){
						field = this.myIframe;
						textLen = $("[name='eBay Description']").val().length;
						height = Math.min(field.offsetHeight-2+120, Math.max(field.offsetHeight-2, ((textLen * 6.8) / field.offsetWidth * 10)));
						$(field).attr("expanded", "true").attr("origHeight", field.offsetHeight-2);
						$(field).css("height", height).css("border", "1px solid rgb(255,125,125)");
						
					})
					.blur(function(){
						film_description_keyup()
						
						field = this.myIframe;
						$(field).css("height", $(field).attr("origHeight"));
						$(field).css("border", "1px solid rgba(127,157,185)");
						$(field).attr("expanded", "false").css("border", "1px solid rgb(127,157,185)");
						
						reset_sexploitation_checkbox();
					})
				
			},
			setup: function(ed) {
				ed.onClick.add(function(ed, event){
					if(event.target.onclick)
						event.target.onclick();
				});
				
				ed.onKeyDown.add(
					function(ed, event){
						film_description_keyup()
						
						if(event.altKey)
						{
							switch(event.which)
							{
								case 65: //A
									event.preventDefault();
									$("[name=additionaldetails]").focus();
									$($("[name='eBay Description']").next().find("iframe")[0].contentWindow.document).blur();
									break;
								case 68: //D
									event.preventDefault();
									$("[name='eBay Description']").next().find("iframe").focus();
									break;
								case 69: //E
									event.preventDefault();
									$("[name='reissueyear']").focus();
									$($("[name='eBay Description']").next().find("iframe")[0].contentWindow.document).blur();
									break;
								case 76: //L
									event.preventDefault();
									$("[name=litho (US ONLY)]").focus();
									$($("[name='eBay Description']").next().find("iframe")[0].contentWindow.document).blur();
									break;
								case 77: //M
									event.preventDefault();
									$("[name=measurements]").focus();
									$($("[name='eBay Description']").next().find("iframe")[0].contentWindow.document).blur();
									break;
								case 78: //N
									event.preventDefault();
									$("[name='number']").focus()
									$($("[name='eBay Description']").next().find("iframe")[0].contentWindow.document).blur();
									break;
								case 84: //T
									event.preventDefault();
									$("[name=film_title]").focus();
									$($("[name='eBay Description']").next().find("iframe")[0].contentWindow.document).blur();
									break;
								case 80: //P
									event.preventDefault();
									$("#pastebox").focus();
									$($("[name='eBay Description']").next().find("iframe")[0].contentWindow.document).blur();
									break;
								case 81: //Q
									event.preventDefault();
									$("[name=quantity]").focus();
									$($("[name='eBay Description']").next().find("iframe")[0].contentWindow.document).blur();
									break;
								case 82: //R
									event.preventDefault();
									$("[name=Reissued]").focus();
									$($("[name='eBay Description']").next().find("iframe")[0].contentWindow.document).blur();
									break;
								case 83: //S
									event.preventDefault();
									$("#styleBoxHeader").click();
									break;
								case 49: //1
									event.preventDefault();
									$("[name=firstrelease]").focus()
									break;
								case 73: //I
									event.preventDefault()
									open_see_titles2()
									break;
								case 71: //G
									event.preventDefault()
									click_link_in_description(0)
									break;
								case 79: //O
									event.preventDefault()
									open_auction_history()
									break;
								case 89: //Y
									event.preventDefault()
									$("[name=alternatestyleinput]").focus()
									$($("[name='eBay Description']").next().find("iframe")[0].contentWindow.document).blur();
									break;
							};
						}
						else
						{
							switch(event.which)
							{
								case 27: //Escape
									if($.seetitle)
										$.seetitle.unload();
									break;
								case 34: //Page down
									if(!event.ctrlKey)
									{
									  $("#log_memo_icon").attr('data-content','').removeClass('log_memo_count');
										insert_update("next");
										event.preventDefault();
									}
									break;
								case 33: //Page up
									if(!event.ctrlKey)
									{
									  $("#log_memo_icon").attr('data-content','').removeClass('log_memo_count');
										insert_update("previous");
										event.preventDefault();
									}
									break;
								case 114: //F3
									event.preventDefault();
									finish_consignor_set()
									break;
								case 115: //F4
									event.preventDefault();
									window.open($('#titleDisplay').attr('href'));
									break;
								case 113: //F2
									event.preventDefault();
									window.open($('#searchimdb').attr('href'));
									break;
								case 118: //F7
									event.preventDefault();
									break;
							};
						}
					}
				);
			},
		});
	
	session = JSON.parse($.cookie("descriptions-" + $.userId));
	if(session && session.consignor && session.photography_folder && session.auction_code && session.set_id && session.pset_id)
	{
		$.session = session;
		$.session.demonym = get_demonym($.session.type_code);
		load_session();
		
		if(session.finishing_entire_set === true && !$.cheat)
		{
			$.setPopup = $("#setBox")
				.css({
					overflow: "auto",
				})
				.dialog({
					maxHeight: 500,
					width: 740,
					modal: true,
					title: "Start a New Set",
					open: function(event, ui){
						$("#extraInfoSet").show().load("get_auction_schedule.php")
						//set_box_autocompletes()
					}
				})
			
		}
	}
	else if(!$.cheat)
	{
		$.setPopup = $("#setBox")
			.css({
					overflow: "auto",
				})
			.dialog({
				maxHeight: 500,
				width: 740,
				modal: true,
				title: "Start a New Set",
				open: function(event, ui){
					$("#extraInfoSet").show().load("get_auction_schedule.php")
					//set_box_autocompletes()
				}
			})
	}
	
	get_style_info_form();
	get_photos();
	
	//Define our keys
	$(window).keydown(function(event) {
		if(event.altKey)
		{
			switch(event.which)
			{
				case 65: //A
					event.preventDefault();
					$("[name=additionaldetails]").focus();
					$(event.target).blur();
					break;
				case 68: //D
					event.preventDefault();
					$("[name='eBay Description']").next().find("iframe").focus();
					field = $("[name='eBay Description']").next().find("iframe")[0];
					textLen = $("[name='eBay Description']").val().length;
					height = Math.min(field.offsetHeight-2+120, Math.max(field.offsetHeight-2, ((textLen * 6.8) / field.offsetWidth * 10)));
					$(field).attr("expanded", "true").attr("origHeight", field.offsetHeight-2);
					$(field).css("height", height).css("border", "1px solid rgb(255,125,125)");
					$(event.target).blur();
					break;
				case 69: //E
					event.preventDefault();
					$("[name='reissueyear']").focus();
					$(event.target).blur();
					break;
				case 76: //L
					event.preventDefault();
					$("[name='litho (US ONLY)']").focus();
					$(event.target).blur();
					break;
				case 77: //M
					event.preventDefault();
					$("[name=measurements]").focus();
					$(event.target).blur();
					break;
				case 78: //N
					event.preventDefault();
					$("[name='number']").focus()
					$(event.target).blur()
					break;
				case 80: //P
					event.preventDefault();
					$("#pastebox").focus();
					$(event.target).blur();
					break;
				case 84: //T
					event.preventDefault();
					$("[name=film_title]").focus();
					$(event.target).blur();
					break;
				case 81: //Q
					event.preventDefault();
					$("[name=quantity]").focus();
					$(event.target).blur();
					break;
				case 82: //R
					event.preventDefault();
					$("[name=Reissued]").focus();
					$(event.target).blur();
					break;
				case 83: //S
					event.preventDefault();
					$("#styleBoxHeader").click();
					break;
				case 49: //1
					event.preventDefault();
					$("[name=firstrelease]").focus()
					break;
				case 73: //I
					event.preventDefault()
					open_see_titles2()
					break;
				case 71: //G
					event.preventDefault()
					click_link_in_description(0)
					break;
				case 79: //O
					event.preventDefault()
					open_auction_history()
					break;
				case 89: //Y
					event.preventDefault()
					$("[name=alternatestyleinput]").focus()
					break;
			};
		}
		else
		{
			switch(event.which)
			{
				case 27: //Escape
					if($.seetitle)
						$.seetitle.unload();
					break;
				case 34: //Page down
					if(!event.ctrlKey && !$(event.originalEvent.target).is("select"))
					{
						event.preventDefault();
						insert_update("next");
					}
					break;
				case 33: //Page up
					if(!event.ctrlKey && !$(event.originalEvent.target).is("select"))
					{
						event.preventDefault();
						insert_update("previous");
					}
					break;
				case 114: //F3
					event.preventDefault();
					finish_consignor_set()
					break;
				case 115: //F4
					event.preventDefault();
					window.open($('#titleDisplay').attr('href'));
					break;
				case 113: //F2
					event.preventDefault();
					window.open($('#searchimdb').attr('href'));
					break;
				case 118: //F7
					event.preventDefault();
					if(event.target.tagName === "TEXTAREA")
					{
						field = event.target;
						if($(field).attr("expanded") === "true")
						{
							$(field).css("height", $(field).attr("origHeight"));
							$(field).attr("expanded", "false");
						}
						else
						{
							height = (($(field).val().length * 6.5) / $(field).css("width")) * 20;
							$(field).attr("expanded", "true").attr("origHeight", $(field).css("height"));
							$(field).css("height", height);
						}
					}
					break;
			};
		}
	});
	
	$("textarea:not([name='eBay Description']):not(#pastebox)").focus(function(){
		field = this;
		$(field).css("border", "1px solid rgb(255,125,125)");
		if($(field).attr("expanded") !== "true")
		{
			height = Math.min(field.offsetHeight+120, Math.max(parseInt(field.offsetHeight), (($(field).val().length * 6.8) / parseInt(field.offsetWidth) * 18)));
			$(field).attr("expanded", "true").attr("origHeight", field.offsetHeight);
			$(field).css("height", height);
		}
	}).blur(function(){
		field = this;
		$(field).css("border", "1px solid rgb(127,157,185)");
		if($(field).attr("expanded") === "true")
		{
			$(field).css("height", $(field).attr("origHeight"));
			$(field).attr("expanded", "false");
		}		
	});
	
	$("#pastebox").focus(function(){
		field = this;
		$(field).css("border", "1px solid rgb(255,125,125)");
		if($(field).attr("expanded") !== "true")
		{
			height = Math.min(field.offsetHeight+120, Math.max(parseInt(field.offsetHeight), (($(field).val().length * 6.8) / parseInt(field.offsetWidth) * 18)));
			height = 100;
			$(field).attr("expanded", "true").attr("origHeight", field.offsetHeight);
			$(field).css("height", height);
		}
	}).blur(function(){
		field = this;
		$(field).css("border", "1px solid rgb(127,157,185)");
		if($(field).attr("expanded") === "true")
		{
			$(field).css("height", $(field).attr("origHeight"));
			$(field).attr("expanded", "false");
		}		
	});
	
	$("[name=pieces]").keyup(function(){
		if(this.value !== "")
			$("[name=incomplete], [name=glued], [name=taped]").next().css({"background" : "rgb(255,255,100)"});
		else
			$("[name=incomplete], [name=glued], [name=taped]").next().css({"background" : ""});
	});
	
	//Get set total
	$.ajax({
		url:"set_total.php",
		async: false,
		type: "post",
		data: "set_id=" + $.session.set_id + "&who=" + $.userId + "&photography_folder=" + encodeURIComponent($("[name=photography_folder]").val()),
		dataType: "json",
		success: function(data){
			if('total' in data)
				$("#setTotal").html(data.total + " records in set").show();
			
				$.recordNumber = data.recordNumber;
		},
		error: function() {
			alert("unknown error getting set total!");
		},
	});
	
	/*$("[name=MrLTitle]").keyup(function(){
		update_mrlister_total();
	});*/
	
	init_fields();
	
	//$.cookie("firstRun", null);
	if(!$.cookie("firstRun"))
	{
		$($.firstRun)
			.css({
				overflow: "auto",
			})
			.dialog({
				maxHeight: 500,
				width: 700,
				title: "First Run Instructions",
				modal: true,
				buttons: {
					Ok: function() {
						$( this ).dialog( "close" );
					}
				}
			});
		
		$.cookie("firstRun", "true", {expires: 30});
	}
});

function updateLocks()
{
	var postData = {}
	
	if($.descLocked)
		postData = {"film_title": $("[name=film_title]").val()}
		
	if(document.lastkeypress)
		postData['keypress'] = document.lastkeypress
		
	$.ajax({
		url:"update_locks.php",
		type: "post",
		data: postData,
		
		dataType: "json",
		success: function(data){
			if('locked' in data && $("[name='film_title']").val() == data.locked)
			{
				$.descLocked = true;
				$("#lockDisplay").html("Locked by " + data.lockedWho);
			}
			else
			{
				if($.descLocked == true)
					getDesc($("[name=film_title]").val());
				
				$.descLocked = false;
				$("#lockDisplay").empty();
			}
		},
		error: function(){
			alert("unknown error updating locks!");
		},
	});
}

function get_auction_code_info(auction_code)
{
	$("#photo_folders_remaining").empty()
	
	if($.get_auction_code_info_ajax)
		$.get_auction_code_info_ajax.abort()
	
	$.get_auction_code_info_ajax = $.ajax({
		url: "get_auction_code_info.php",
		type: "post",
		dataType: "json",
		data: "auction_code="+encodeURIComponent(auction_code),
		success: function(data){
			
			if("photography_codes" in data)
			{
				total_remaining = data.photography_codes.length					
				
				$("#photo_folders_remaining")
					.append(
						$("<a />")
							.html(total_remaining)
							.attr("title", data.photography_codes.join(", "))
							.css({
								fontSize: "9pt",
								cursor: "default",
								color: data.color,
							})
					)
			}
			
			if("auction_note" in data)
			{
				$("#extraInfoSet").empty()
					.append(
						$("<div />")
						.css({
							"white-space": "pre-wrap",
						})
						.html(data.auction_note)
					)
					.show()
			}
			else
			{
				$("#extraInfoSet").empty().hide()
			}
		}
	})
}

function get_search_field(type_code, field)
{
	$.temp = field;
	if(type_code == "")
	{
		$($.temp).html('').hide();
		return false;
	}
	
	$.ajax({
		url: 'getsearchfield.php',
		type: 'post',
		dataType: "json",
		data: "type_code=" + encodeURIComponent(type_code),
		success: function(data){
			if('error' in data)
			{
				alert(data.error);
				return false;
			}
			
			if('Search' in data)
				$($.temp).html("<label>Default measurement: </label>" + data.measurements + "<hr />" + data.Search + "<hr />" + data.type_about + "<hr />" + data.type_long_code).show(0);
			
			if('noresults' in data)
				$($.temp).html('').hide();
		},
	});
}

function get_search_field_popup(type_code)
{
	$.ajax({
		url: 'getsearchfield.php',
		type: 'post',
		dataType: "json",
		data: "type_code=" + encodeURIComponent(type_code),
		success: function(data){
			if('error' in data)
			{
				alert(data.error);
				return false;
			}
			
			if('Search' in data)
			{
				$("<div style='max-width: 700px; max-height: 500px; overflow: auto'>"+
					"<label>Default measurement: </label>" + data.measurements + "<hr />" + data.Search + "<hr />" + data.type_about + 
					"<hr />" + data.type_long_code + "</div>")
					.css({
						overflow: "auto",
					})
					.dialog({
						maxHeight: 500,
						width: 700,
						title: type_code
					})
					.scrollTop(0)
			}
			
			if("noresults" in data)
				alert("No documentation for this type code.")
		},
	});
}

function get_consignor_notes(consignor, field)
{
	if(consignor == "")
	{
		$(field).html("").hide()
		return false
	}
	
	
	
	$(field).empty().append(
		$("<span />").css("color", "red").load("get_consignor_notes.php", "consignor="+encodeURIComponent(consignor),	
	function(text){
		if(text == "")
			$(this).hide()
		else
			$(this).show()
		}))
}

function get_style_info_form(type_code, auction_code, force, callback)
{
	if(!type_code)
		type_code = $("[name=type_code]").val();
		
	if(type_code == $.prevStyleInfoForm && auction_code == $.prevStyleInfoFormAuctionCode && !force)
	{
		if(callback)
			callback();
		return true;
	}
		
	$.prevStyleInfoForm = type_code;
	$.prevStyleInfoFormAuctionCode = auction_code
	
	$.film_title = $("[name=film_title]").val();
	
	if(!auction_code)
		auction_code = $(".auction[name=auction_code]").val()
	
	$("#dated_table_total").empty()
	
	$.ajax({
		url: "get_style_info_box.php",
		async: false,
		dataType: "json",
		data: "type_code=" + encodeURIComponent(type_code) + 
			"&auction_code=" + encodeURIComponent(auction_code),
		success: function(data){
			/*
				Added new "signed by" behavior. When the auction code is
				SIGNED, the "signed by" box is moved to a more visible
				spot.
				
				Request by Matt, I think?
				
				2013-12-31 AK
			*/
			var values = $("#styleInfoPane :input").createValueArray()
			
			$("#styleInfoPane").empty().html(data.main);
			
			if('javascript' in data)
				eval(data.javascript);
				
			dated_table_total(data)
			
			
			if($("#signedbytop").length)
			{
				$("#signedbybottom")
					.append(
						$("#signedbytop").children()
					)
			}
			
			$("#extra_functions").empty().hide()
			
			if('extra' in data)
				$("#extra_functions").html(data.extra).show();
			
			if(auction_code == "SIGNED" || type_code == "art print") //$(".auction[name=auction_code]").val().toUpperCase() == "SIGNED")
			{
				$("#extra_functions")
					.append(
						$("<div />")
							.attr("id", "signedbytop")
							.append(
								$("#signedbybottom").children()
							)
					)
					.show()
			}
			
			console.debug(values)
			for(x in values)
			{
				var elements = $("#styleInfoPane :input")
					.filter(function(){
						return $(this).attr("name") == x
					})
				
				if($(elements).is("[type=radio]"))
				{
					$(elements)
						.filter(function(){
							return $(this).val() == values[x]
						})
						.prop("checked", true)
						.click()
				}
				else
				{
					$(elements).val(values[x])
				}
			}
		},
	});
	
	init_fields();
	
	$("[name='film_title']")
		.val($.film_title)
		.autocomplete2({
			delay: 100,
			source: "/includes/suggest2.php?name=film_title",
			focus: function(event, ui){
				
				if(event.key)
				{
					if(this.getDescTimeout)
						clearTimeout(this.getDescTimeout)
					
					this.getDescTimeout = setTimeout(function(){getDesc(ui.item.value)}, 200)
				}
			},
		})
		
				
	$("[name=film_title]")
		.data("ui-autocomplete2")._renderItem = film_title_suggestion_renderer
		
	$("[name=film_title]")
		.data("ui-autocomplete2")._renderMenu = function( ul, items ) {
			var that = this;
			$.each( items, function( index, item ) {
				that._renderItemData( ul, item );
			});
			$( ul ).find( "li:odd" ).addClass( "odd" );
		}
		
		
	$("[name=nationality]")
		.autocomplete2({
			delay: 100,
			source: "/includes/suggest2.php?name=nationality",
		})
	
	$("[name=film_title]")
		.blur(function(){
		getDesc();
		})
		.change(function(){
		getDesc();
	});
	
	$("[name=artist].auction")
		.autocomplete2({
			delay: 100,
			source: "/includes/artist_table.inc.php",
			delete: function(event, item){
				
				$.ajax({
					url: "/includes/artist_table.inc.php",
					async: false,
					data: {
						term: item.item.value,
						deleteRecord: "true",
					},
					success: function(data){
						
					},
					error: function(){
						alert("There was a problem deleting that.")
					}
				})
				
			}
		})
		
	$("[name=artist].auction")
		.keypress(function(event){
			
		})
	
	$("[name='how_stored_tip']").attr("title","Click here for notes").click(function(){
		$("<div style='height: 300; width:800; overflow:auto'>Indicate how the items are stored. "+
			"Are they in a box, stored between cardboard, or rolled? This helps with automatically "+
			"listing the item, and with more quickly locating the item in the future. This field "+
			"is optional.<table border=\"1\"><tr>  <td bgcolor=\"#C0C0C0\"><b>how_stored</b></td>  "+
			"<td bgcolor=\"#C0C0C0\"><b>type</b></td>  <td bgcolor=\"#C0C0C0\"><b>measurement</b></td>  "+
			"<td bgcolor=\"#C0C0C0\"><b>board size</b></td>  <td bgcolor=\"#C0C0C0\"><b>auction_tables</b></td></tr>"+
			"<tr>  <td>1/2sh board</td>  <td>board</td>  <td>24 x 30</td>  <td>24 x 30</td>  <td>ins_half (1/2sh, Japan B2)</td></tr>"+
			"<tr>  <td>1sh box</td>  <td>box</td>  <td>15 x 12 x 10</td>  <td>11 x 14</td>  "+
			"<td>ff1sh, ffnon, lcsing/multi, misc11x14, some bins, some bulklots</td></tr><tr>  <td>3sh box</td>  <td>box</td>  "+
			"<td>17 7/8 x 12 3/4 x 11 3/4 or 18 x 13 x 12</td>  <td>11 x 14</td>  <td>36shbqar, some bins, some bulklots</td></tr>"+
			"<tr>  <td>drawer size board</td>  <td>board</td>  <td>28 1/2 x 41 1/2</td>  <td>28 1/2 x 41 1/2</td>  "+
			"<td>uf_non, uf_spec, uf27x41/40, some bins, some ship_flat</td></tr><tr>  <td>insert board</td>  <td>board</td>  "+
			"<td>16 x 38</td>  <td>16 x 38</td>  <td>ins_half (inserts, It. locs), ship_flat (tri-fold 3/6/24sh)</td></tr><tr>  "+
			"<td>literally rolled</td>  <td>shelf</td>  <td>&nbsp;</td>  <td>&nbsp;</td>  <td>some linen, lgrolled</td></tr><tr>  "+
			"<td>oversize board</td>  <td>board</td>  <td>32 x 48</td>  <td>32 x 48</td>  "+
			"<td>Linen, uflarge, some ship_flat, some bins, some bulklots</td></tr><tr>  <td>still box</td>  <td>box</td>  "+
			"<td>11 1/2 x 9 1/2 x 5 or 11 x 9 x 5</td>  <td>8 x 10</td>  <td>8x10 sing/multi, some bulklots</td></tr><tr>  "+
			"<td>wc board</td>  <td>board</td>  <td>16 x 22 3/4</td>  <td>16 x 22 3/4</td>  <td>wcpanel, pbmex, some bins, some "+
			"bulklots</td></tr></table></div>")
			.css({
				overflow: "auto",
			})
			.dialog({
				maxHeight: 500,
				width: 700,
				title: "How Stored Notes",
			})
	});
	
	
	$("img[src='graphics/unlock.png'], img[src='graphics/lock.png']").remove();
	//Add field locks
	for(fieldName in $.fieldLocks)
	{
		img = $("<img src='graphics/unlock.png' lockId='"+fieldName+"' style='margin-left: -15;' class='clickable' />")
			.attr("src", ($.fieldLocks[fieldName].locked ? "graphics/lock.png" : "graphics/unlock.png"))
			.click(function(){
				lockId = $(this).attr("lockId");
				if($.fieldLocks[lockId].locked == false)
				{
					this.src = "graphics/lock.png";
					$.fieldLocks[lockId].locked = true;
					
					switch($("[name='"+lockId+"']").attr("type"))
					{
						case "checkbox":
							value = ($("[name='"+lockId+"']").prop("checked")) ? 1 : 0;
							break;
						case "radio":		
							$("[name='"+lockId+"']")
								.each(function(){
									if(this.checked)
									{
										$.temp = this.value;
										return false;
									}
								});
							
							value = $.temp;
							break;
						default:
							value = $("[name='"+lockId+"']").val()
							break;
					};
					
					$.fieldLocks[lockId].value = value
				}
				else
				{
					this.src = "graphics/unlock.png";
					$.fieldLocks[lockId].locked = false;
					$.fieldLocks[lockId].value = false;
				}
			});
		
		if($("select[name='"+fieldName+"']:first,input[name='"+fieldName+"']:first").css("display") == "none")
			continue;
		
		if($.fieldLocks[fieldName].after === true)
			$("select[name='"+fieldName+"']:first,input[name='"+fieldName+"']:first").next().after($(img).css("margin", ""));
		else if($.fieldLocks[fieldName].immediately_after === true)
			$("select[name='"+fieldName+"']:first,input[name='"+fieldName+"']:first").after($(img).css("margin", ""));
		else if($.fieldLocks[fieldName].after_last === true)
			$("select[name='"+fieldName+"']:first,input[name='"+fieldName+"']:last").next().after($(img).css("margin", ""));
		else
			$("select[name='"+fieldName+"']:not([disabled]):first,input[name='"+fieldName+"']:not([disabled]):first").before(img);
	}
	
	if(callback)
		callback()
}

function get_consignor_info(consignor)
{
	$.ajax({
		url: "get_consignor_info.php",
		type: "post",
		dataType: "json",
		data: "consignor=" + encodeURIComponent(consignor),
		success: function(data){
			if('error' in data)
			{
				alert(data.error);
				return false;
			}
			
			$("#extraInfo").show(300).html(data.SameAs);
		},
	});
}

function reset_sexploitation_checkbox()
{
	/*
	Function: reset_sexploitation_checkbox
	
	If the description contains the word sexploitation, check the box.
	If not, uncheck the box
	*/
	
	if($("[name='eBay Description']").val().indexOf("sexploitation") != -1)
	{
		$("#sexploitation").prop("checked", true)
		
		//We are disabling this per Matt and Todd 20170131 "Re: Nudity - can the describers handle this?"
		//if(!$.fieldLocks.xrated.locked)
		//	$("#xrated").prop("checked", true)
	}
}

function get_repro_notes()
{
	$("#messages .repro_note_link").remove();

	$.ajax({
		url: "/includes/get_repro_notes.php",
		type: "post",
		dataType: "json",
		data: "film_title="+encodeURIComponent($("[name=film_title]").val())+
			"&type_code="+encodeURIComponent($("[name=type_code]").val()),
		success: function(data){
			if(data.repro_notes.length > 0)
			{
				for(x in data.repro_notes)
				{
					words = data.repro_notes[x]['comment'].split(" ").splice(0,12).join(" ")
					$("#messages")
						.append($("<div><a class='repro_note_link' style='text-decoration: underline' href='javascript:void(0)'>"+
							"View Repro Note</a> <small>"+words+"&hellip;</small></div>")
							.click(function(){
								repro_note_popup($(this).attr("repro_note_id"), $(this).attr("repro_note_link"), $(this).attr("repro_note_comment"))
							}).attr({
								"repro_note_id": data.repro_notes[x]['id'],
								"repro_note_link": data.repro_notes[x]['link'],
								"repro_note_comment": data.repro_notes[x]['comment'],
							}))
						.show()
				}
			}
		}
	})
}

function insert_update(direction, move_to_old_stuff)
{
	//Added this to prevent a known issue. AK, 2012-08-23
	if($("#titleDisplay").attr("title") && $("[name=film_title]").val() && 
		$("[name=film_title]").val() != $("#titleDisplay").attr("title"))
	{
		alert("Whoa there, Barbara Blackburn, your title doesn't match the description. "+
			"Instead of blindly overwriting \""+$("[name=film_title]").val()+"\" with "+
			"the information from \""+$("#titleDisplay").attr("title")+"\", I'm going "+
			"to stop you. Wasn't that nice of me?")
		return false
	}
	
	autocorrect_measurements_field($("[name=measurements]").get(0))

	if(move_to_old_stuff)
	{
		if($("#film_title").val().trim().length == 0)
		{
			alert("Add a title before cancelling this item.");
			return false
		}
		
		if($("#listing_notes").val().trim().length == 0)
		{
			alert("Add a note before cancelling this item.")
			return false
		}
		
		if($("#listing_notes").val().trim().length < 6)
		{
			alert("Add an ACTUAL note before cancelling this item.")
			return false
		}
		
		if($("#listing_notes").val().replace(" ", "").replace("!", "").toLowerCase() == "repro")
		{
			if(confirm("Okay, Stanley. You found out how to cheat the system by writing 'Repro!' (or similar). Good job. "+
				"If you want to actually explain how this is a repro, you can press OK now, or to continue being a spoilsport, press Cancel. "+
				"I can't stop you, I'm just a computer! *grumble*"))
				return false
		}
	
		suggested = $("#film_title").val().replace(/[^a-z 0-9]/gi, "").replace(/ /g, "_").toLowerCase()+"_"+
			$("[name=photography_folder].auction").val().replace(/[^a-z 0-9]/gi, "").replace(/ /g, "_").toLowerCase()+".jpg"
		
		type = $("[name=type_code].auction").val()
		
		if(type != "1sh")
		{
			suggested = type.replace(/[^a-z 0-9]/gi, "").replace(/ /g, "_").toLowerCase() + "_" + suggested
		}
		
		image1 = prompt("Cancel bad item? (moves record to 'Repro Returns').\n\n"+
			"Place image in \\\\images\\images\\Repro.\n\n"+
			"Rename image to: ", suggested)
		
		if(!image1)
		{
			alert("Nevermind.")
			return false
		}
	}

	if($.info_notes_box.unsaved_changes_exist())
		$.info_notes_box.saveAll();
	
	check = checkTheDescription();
	
	if(check === false)
		return false;
		
	delete $.lastDescTitle;
	
	$("#messages").empty();
	
	if($("[name=film_title]").val() !== "" && (check.warnings.length || check.reminders.length))
	{
		warnings = check.warnings;
		reminders = check.reminders;
		
		for(x in warnings)
			$("#messages").append("<span style='font-weight: bold; color: red'>" + warnings[x] + "</span><br />");
		
		for(x in reminders)
			$("#messages").append(reminders[x] + "<br />");

		$("#messages").show();
	}
	else
		$("#messages").empty().hide();
	
	descriptionsArray = $(".descriptions").createValueArray();
	descriptionsArray["TITLE"] = $("[name=film_title]").val();
	
	if($("#trimmed").prop("checked"))
	{
		if($("[name=measurements]").val().indexOf("from") == -1)
			$("[name=measurements]").val('trimmed to ' + $("[name=measurements]").val());
		else
			$("[name=measurements]").val('trimmed ' + $("[name=measurements]").val());
	}
	
	postData = {
		"descriptionsData" : JSON.stringify(descriptionsArray),
		"styleInfoData" : JSON.stringify($(".style_info").createValueArray()),
		"auctionData" : JSON.stringify($(".auction").createValueArray()),
		"go" : direction,
		"autoDupe" : $("#autodupe").val(),
		"autoNum" : $("#autonum").val(),
		"locked" : $.descLocked,
	}
				
	if($.info_notes_box.data_has_changed())
	{
		postData["info_notes"] = $.info_notes_box.fetch_data() 
		postData["info_notes_original"] = $.info_notes_box.fetch_original_data()
	} 
		
	if(move_to_old_stuff)
	{
		postData["move_to_old_stuff"] = "yes"
		postData["image1"] = image1
	}
	
	if(window.memo)
	{
		postData['memo'] = window.memo
	}
	
	postData['setnum'] = $("#setnum").val()
	postData['autosetnum'] = $("#autosetnum").val()
	
	$("#autodupe, #autonum").val(0);
	
	horror = [
		"What... have... you... done?!",
		"Uh-oh!",
		"Whoops!",
		"Oh dear.",
		"You broke it!",
		"Fix it!",
		"Ouch!"
	]	
	
	$.ajax({
		url: "insert.php",
		type: "post",
		async: false,
		dataType: "json",
		data: postData,
		success: function(data){
			
			if(false !== parseData(data))
			{
				delete window.memo
				get_repro_notes()
				$("[name=film_title]").focus()
				$($("[name='eBay Description']").next().find("iframe")[0].contentWindow.document).blur()
				$("textarea").blur()
			}
			else
			{
				$("#autodupe").val(postData['autoDupe'])
				$("#autonum").val(postData['autoNum'])
			}
		},
		error: function () {
			alert("Unknown error inserting record!");
		},
	});
}

function get_gallery(film_title, type_code)
{
	if($("#gallery").css("display") == "block" && $.lastGalleryTitle == film_title)
		return true;
	
	if($.galleryAjax)
	{
		if($.lastGalleryTitle == film_title && $.galleryAjax.readyState !== 0)
			return true;
		else
			$.galleryAjax.abort();
	}
	
	$.lastGalleryTitle = film_title;
	
	$("#gallery").empty().hide();
	
	$.galleryAjax = $.ajax({
		type: "POST",
		url: "./get_images.php",
		dataType: "json",
		data: "film_title=" + encodeURIComponent(film_title) + "&type_code=" + encodeURIComponent(type_code),
		success: function(data){			
			parseGallery(data);
		},
		error: function(XMLHttpRequest, textStatus, errorThrown){
			//ajaxError(XMLHttpRequest, textStatus, errorThrown);
		}
	});
}

function parseGallery(data)
{
	for(x in data.type_results)
	{
		div = $('<div />').css({
				'height': '12em',
				'max-width': '7em',
				'float': 'left',
				'margin': '5px',
		});
		a = $('<a />').attr('href', data.type_results[x].href).attr('target', '_blank');
		img = $('<img />').css('max-width', '6em').css('max-height', '9em').css("display", "none").attr('src', data.type_results[x].image).load(function(){this.style.display = "block"});
		$(img).attr('title', 
		((data.type_results[x].condition) ? data.type_results[x].condition : "") +
		((data.type_results[x].price) ? "  Sold For: $" + data.type_results[x].price : "")
		);
		// //$(img).tooltip({delay: 700});
		$('<div />').html(
			"<span style='font-size: x-small; max-height: 40px; overflow: auto; display: inline-block'>" +
			((data.type_results[x].extra_info) ? data.type_results[x].extra_info : "&nbsp;") +
			((data.type_results[x].type) ? " &lt;" + data.type_results[x].type + "&gt;" : "") +
			"</span>"
		).appendTo(div);
		$(img).appendTo(a);
		$(a).appendTo(div);
		b = $("<div />").css({"font-size": "x-small", "text-align": "center"}).html(data.type_results[x].formatted_date);
		$(b).appendTo(div);
		$(div).appendTo("#gallery");
	}
	
	if(data.no_type_results)
	{
		for(x in data.no_type_results)
		{
			for(y in data.no_type_results[x])
			{
				div = $('<div />').css({
						'height': '12em',
						'max-width': '7em',
						'float': 'left',
						'margin': '5px',
				});
				
				a = $('<a />').attr('href', data.no_type_results[x][y].href).attr('target', '_blank');
				img = $('<img />').css('max-width', '6em').css('max-height', '9em').css("display", "none").attr('src', data.no_type_results[x][y].image).load(function(){this.style.display = "block"});
				
				$(img).attr('title', 
				((data.no_type_results[x][y].condition) ? data.no_type_results[x][y].condition : "") +
				((data.no_type_results[x][y].price) ? "  Sold For: $" + data.no_type_results[x][y].price : "")
				);
				
				//$(img).tooltip({delay: 700});
				$('<div />').html(
					"<span style='font-size: x-small; max-height: 40px; overflow: auto; display: inline-block'>" +
					((data.no_type_results[x][y].extra_info) ? data.no_type_results[x][y].extra_info : "&nbsp;") +
					((data.no_type_results[x][y].type) ? " &lt;" + data.no_type_results[x][y].type + "&gt;" : "") +
					"</span>"
				).appendTo(div);
				$(img).appendTo(a);
				$(a).appendTo(div);
				
				b = $("<div />").css({"font-size": "x-small", "text-align": "center"}).html(data.no_type_results[x][y].formatted_date);
				$(b).appendTo(div);
				$(div).appendTo("#gallery");
			}
		}
	}
	
	$("#gallery").show();
}

function dated_table_total(data)
{
	$("#dated_table_total")
		.empty()
		.append(
			$("<span />")
				.css({marginRight: "10px", marginLeft: "10px", cursor: "default"})
				.attr("title", data.dated_table_country_total + " items described for this auction (with the same country)")
				.html(data.dated_table_country_total + "c")
		)
		.append(
			$("<span />")
				.css({cursor: "default"})
				.attr("title", data.dated_table_country_total + " items described for this auction (with the same type code)")
				.html(data.dated_table_type_total + "t")
				
		)
}

function parseData(data)
{
	if('error' in data)
	{
		alert(data.error);
		return false;
	}
	
	if("syntax" in data)
	{
		$("<div />")
			.css({
				whiteSpace: "pre-wrap",
			})
			.html(data.syntax)
			.css({
				overflow: "auto",
			})
			.dialog({
				title: horror[Math.round(Math.random() * (horror.length-1))],
				maxHeight: 500,
				width: 700,
				modal: true,
				buttons: {
					Ok: function() {
						$( this ).dialog( "close" );
					}
				},
			});
		return false
	}
	
	if('lock' in data)
	{
		$.descLocked = true;
		$("#lockDisplay").html("Locked by " + data.lockWho);
	}
	else
	{
		$.descLocked = false;
		$("#lockDisplay").empty();
	}
	
	
	
	if('message' in data)
		alert(data.message);
	
	init_fields();
	
	 if('memoCount' in data){
    if(data.memoCount == 0){
      $("#log_memo_icon").attr('data-content','').removeClass('log_memo_count');
    }else{
      $("#log_memo_icon").attr('data-content',data.memoCount).addClass('log_memo_count');
    }
  }
	
	if('reminders' in data)
	{
		for(x in data.reminders)
		{
			$("#messages").append(data.reminders[x] + "<br />");
		}
		
		$("#messages").show()
	}
	
	//Scroll the image previews
	if(data.recordNumber)
		$("#photos img").css("border-style", "none");
	if(data.recordNumber && $("#photos img").length && $.scrollShots)
	{
		index = ($.reverseScroll) ? $("#photos img").length - data.recordNumber*$.shotsPerItem - 1 : data.recordNumber*$.shotsPerItem - 1;
		if($("#photos img:eq("+index+")").length)
		{
			for(x = index - $.shotsPerItem + 1; x <= index; x++)
				$("#photos img:eq("+x+")").css("border", "1px solid red");
			
			$("#photos img:eq("+(index-$.shotsPerItem+1)+")")[0].scrollIntoView(false);
			
			if((index-$.shotsPerItem+1) != index)
				$("#photos img:eq("+index+")")[0].scrollIntoView(false);
		}
	}
	
	if(data.norecord == 1)
	{
		$("#gallery").fadeOut(600);
		get_style_info_form($.session.type_code, $.session.auction_code, true);
		$.lastDescTitle = "";
		load_session();
	}
	
	if('setidcount' in data )
		$("#setTotal").html(data.setidcount + " records in set").show();
	else if(!("getDesc" in data))
		$("#setTotal").hide();
	
	if('auctionRow' in data)
	{
		get_style_info_form(data.auctionRow.type_code, data.auctionRow.auction_code);
		
		for(x in data.auctionRow)
		{
			var field = x;
			var value = data.auctionRow[x];
			if(value == null) value = ""; //Fix for Opera
			$("[name='" + field + "'].auction").val(value);
		}
		
		if(data.auctionRow.xrated == 1)
			$("#xrated").prop("checked", true)
		else
			$("#xrated").prop("checked", false)
		
		if(data.auctionRow.measurements && data.auctionRow.measurements.indexOf("trimmed ") !== -1)
			$("#trimmed").attr("disabled", true);
	}
	else if(!("getDesc" in data))
	{
		if('descriptionsRow' in data)
			$(".auction:not([name=film_title])").val('');
		else
			$(".auction").val('');
			
		load_session();
	}
	
	if('descriptionsRow' in data)
	{
		$.lastDescTitle = data.descriptionsRow['TITLE'];
		//Perform some checks
		if(data.descriptionsRow['Year/NSS'] && 
			parseInt(data.descriptionsRow['Year/NSS'].slice(0, 4)) <= 1927 && 
			data.descriptionsRow['Year/NSS'].slice(4,5) !== "-" && 
			data.descriptionsRow['eBay Description'].indexOf("silent") === -1 && 
			data.descriptionsRow['eBay Description'].search("biograph") === -1)
		{
			if($("#messages [name='silent']").length === 0) $("#messages").append("<span name='silent'><span style='text-decoration: blink; color: red;'>Important! </span>This film was made before 1927, and needs the word \"silent\" put into the description.</span>").show();
		}
		else
			$("#messages [name=silent]").remove();
		
		$("#messages [name=nazi]").remove();
		
		if(
			data.descriptionsRow['Year/NSS'] &&
			data.descriptionsRow['Year/NSS'].match(/^19(3[8-9]|4[0-5])/) &&
			$("#topPane [name=type_code]").val().match(/Austrian|Belgian|Czech|Danish|French|Greek|Dutch|Norwegian|Polish|Russian|Yugoslavian|Turkish|Hungarian|Romanian|Ital/)
		)
		{
			if(data.descriptionsRow['eBay Description'].match(/Austrian|Belgian|Czech|Danish|French|Greek|Dutch|Norwegian|Polish|Russian|Yugoslavian|Turkish|Hungarian|Romanian|Ital/))
			{
				$("#messages")
					.append("<span name='nazi' style='font-weight: bold'>"+
					"<span style='text-decoration: blink; color: red;'>Important!</span> "+
					"This film was created inside a Germany-occupied country during World War II. "+
					"Since the poster is also from a Germany-occupied country, the date of first release "+
					"is likely the same as the date of the film, but you should research it to be sure."+
					"</span>")
					.show();
			}
			else
			{			
				$("#messages")
					.append("<span name='nazi' style='font-weight: bold'>"+
					"<span style='text-decoration: blink; color: red;'>Important!</span> "+
					"You are describing a poster whose country was occupied by Germany during World War II. "+
					"This item is likely a first release between 1945 and the late 1950s because this film "+
					"was produced in a non-occupied country during this time period.</span>")
					.show();
			}
		}
		
		
		/*
		If the film is 1928 or 1929, and the info notes does not contain
		a record that says in the heading "SILENT" or "TALKING",
		tell them to insert such a record
		*/
		$("#messages [name=1928]").remove();
		
		if(data.descriptionsRow['Year/NSS'] && 
			data.descriptionsRow['Year/NSS'] != "" && 
				parseInt(data.descriptionsRow['Year/NSS'].slice(0, 4)) >= 1928 && 
				parseInt(data.descriptionsRow['Year/NSS'].slice(0, 4)) <= 1929 && 
				data.descriptionsRow['Year/NSS'].slice(4,5) !== "-")
		{
			info_notes_data = data.info_notes
			display = true
			for(x in info_notes_data)
			{
				heading = info_notes_data[x]['heading'];
				type = info_notes_data[x]['type_code'];
				sort_order = info_notes_data[x]['sort_order'];
				
				if(type == "" && sort_order == -1 && 
					(heading.indexOf("SILENT") == 0 
					|| heading.indexOf("TALKING") == 0
					|| heading.indexOf("SILENT/TALKING") == 0
					|| heading.indexOf("TALKING/SILENT") == 0))
				{
					display = false
					break;
				}
			}
			
			if(display == true)
			{
				$("#messages").append("<span name='1928' style='font-weight: bold'>"+
					"<span style='text-decoration: blink; color: red;'>Important!</span> "+
					"This film is from 1928 or 1929. Check IMDb to see if this "+
					"film is silent, talking, or released as both. Add an entry to the info notes "+
					"where the <u>heading</u> contains SILENT, TALKING, or SILENT/TALKING. "+
					"<u>Type</u> must be blank. <u>sort order</u> must be -1.</span>").show();
			}
		}
		
		
		$("#messages [name=alert]").remove()
		
		for(x in data.info_notes)
		{		
			if(data.info_notes[x]['heading'].toLowerCase().indexOf("!") !== -1 && 
				$(".auction[name=type_code]").val().toLowerCase().indexOf(data.info_notes[x]['type_code'].toLowerCase()) !== -1)
			{
				font_percent = 100 + data.info_notes[x]['heading'].toLowerCase().match(/!/g).length*10
				
				$("#messages").append(
					$("<div name='alert' style='font-weight: bold' />")
						.append("<span style='font-weight: normal'>important info note: </span>")
						.append(
							$("<span />")
								.html(data.info_notes[x]['heading'])
								.css({
									fontSize: font_percent + "%"
								})
						)
				).show()
				break;
			}
		}
		
		//End of checks
		$("#messages [name=automatically_generated_description]").remove()
		
		if('norecord' in data.descriptionsRow)
		{
			$("#gallery").fadeOut(600);
			$(".descriptions").val('');
			
			if($.fieldLocks['type_of_record'].locked)
				$("[name=type_of_record].descriptions").val($.fieldLocks['type_of_record'].value)
			
			$("#sexploitation").prop("checked", false)
			if(!$.fieldLocks.xrated.locked)
				$("#xrated").prop("checked", false)
			
			$("[name=film_title]").val($("[name=film_title]").val().toUpperCase());
			$("#titleDisplay")
				.attr("href", null)
				.removeAttr("title")
				.html('');
			$("#logview").attr("href", null).hide();
			$("#searchimdb").attr("href", "http://www.imdb.com/find?s=all&q=" + encodeURIComponent($("[name=film_title]").val())).parents(":eq(0)").show();
		}
		else
		{
			
			if(data.descriptionsRow.automatically_generated_flag == 1)
				$("#messages").append("<div name='automatically_generated_description'>Description generated automatically.</div>").show()
		
			if($.session.demonym)
				data.descriptionsRow['eBay Description'] = data.descriptionsRow['eBay Description'].replace(new RegExp($.session.demonym, "g"), "<span style='background: rgb(255,255,0); font-weight: bold;'>"+$.session.demonym+"</span>");
			if($.session.demonym == "Slovak")
				data.descriptionsRow['eBay Description'] = data.descriptionsRow['eBay Description'].replace(new RegExp("Czechoslovakian", "g"), "<span style='background: rgb(255,255,0); font-weight: bold;'>Czechoslovakian</span>");
			
			for(x in data.descriptionsRow)
			{
				var field = x;
				var value = data.descriptionsRow[x];
				if(value == null) value = ""; //Fix for Opera
				$("[name='" + field + "'].descriptions").val(value);
			}
			
			if(data.descriptionsRow.sexploitation != 0)
				$("#sexploitation").prop("checked", true);
			else
				$("#sexploitation").prop("checked", false)
			
			if("getDesc" in data)
			{
				/*if(!$.fieldLocks.xrated.locked)
				{
					if(data.descriptionsRow.sexploitation != 0)
						$("#xrated").prop("checked", true);
					else
						$("#xrated").prop("checked", false)
				}*/
			}
			
			$("[name=film_title]").val(data.descriptionsRow.TITLE);
			$("#titleDisplay")
				.html(data.descriptionsRow.TITLE)
				.attr("href", data.descriptionsRow['IMDb URL'])
				.attr("title", data.descriptionsRow.TITLE);
			$("#logview").attr({
				href: "http://poster-server/descriptions_log/?film_title=" + encodeURIComponent(data.descriptionsRow['TITLE']),
			}).show();
			$("#searchimdb").attr("href", "http://www.imdb.com/find?s=all&q=" + encodeURIComponent($("[name=film_title]").val())).parents(":eq(0)").show();
			
			get_gallery(data.descriptionsRow.TITLE, $("[name=type_code]").val());
		}		
	}
	else
	{
		$(".descriptions").val('');
		$("#sexploitation, #xrated").prop("checked", false)
		$("#titleDisplay")
			.attr("href", null)
			.removeAttr("title")
			.html('')
		$("#logview").attr("href", null).hide();
		$("#searchimdb").parents(":eq(0)").hide();
	}
	
	if(data.info_notes && data.info_notes.length > 0)
	{
		$.info_notes_box.load_data(data.info_notes);
		$.info_notes_box.show(((data.auctionRow) ? data.auctionRow.type_code : $("[name=type_code]").val()), false);
	}
	else
	{
		$.info_notes_box.load_data([]);
		$.info_notes_box.show();
	}
	
//	update_mrlister_total();
	
	if('styleInfoRow' in data)
	{
		$(".style_info:input[type=radio]")
			.each(function(){
					$(this).prop("checked", false)
			})
		
		for(x in data.styleInfoRow)
		{
			var field = x;
			var value = data.styleInfoRow[x];
			if(value == null) value = ""; //Fix for Opera
			$.value = value;
			
			if(field == "advancetagline")
			{
				switch(data.styleInfoRow.advance)
				{
					case "no":
						break;
						
					case "advance":
						$("[name=advance][value=advance]").click();
						$("[name=advancetagline]").val($.value);
						break;
						
					case "teaser":
						$("[name=advance][value=teaser]").click();
						switch($.value)
						{
							case "no credits":
								$("[name='advancetagline'][value='no credits']").prop("checked", true);
								break;
							case "Advance/Teaser":
								$("[name='advancetagline'][value='Advance/Teaser']").prop("checked", true);
								break;
							default:
								$("#advancetaglineother").prop("checked", true).val($.value);
								$("#advancetaglinecustom").val($.value);
								break;
						};
						break;
				};
			}
			else
			{
				$("[name='" + field + "'].style_info")
					.each(function(){
						switch($(this).attr("type"))
						{
							case "checkbox":
								if($.value !== "0")
									$(this).prop("checked", true);
								else
									$(this).prop("checked", false)
								break;
							
							case "radio":
								if($(this).val() == $.value)
								{
									//console.debug(this)
									$(this).click()
								}
								else
								{
									$(this).prop("checked", false)
								}
								break;
							
							default:
								if($.value == "0")
									$(this).val('');
								else
									$(this).val($.value);
								break;
						};
					});
			}
			delete $.value;
		}
	}
	else if(!("getDesc" in data))
	{
		$(".style_info:input").each(function(){
			switch($(this).attr("type"))
			{
				case "text":
					$(this).val('');
					break;
				case "checkbox":
					$(this).prop("checked", false)
					break;
				case "radio":
						
						if($(this).attr("default") === "true")
						{
							//$(this).attr("checked", true);
							$(this).click();
						}
						else
						{
							$(this).prop("checked", false)
						}
					break;
				default:
					$(this).val("")
					break;
			};
		});
		
		$("[name=language]").val("N/A")
	}
	
	if(data.norecord == 1)
		fill_in_locked_fields();
		
	if("getDesc" in data)
	{
		if("first_release_year" in data && $("input.auction[name=type_code]").val() != "script")
		{
			$(".style_info[name=firstrelease]").val(data.first_release_year)
			$("#messages").append("<div class='auto_first_release'>added 1<sup>st</sup> release year "+data.first_release_year+"</div>");
			$("#messages").show()
		}
		else
		{
			
			$(".style_info[name=firstrelease]").val("")
			$("#messages .auto_first_release").remove()
		}
	}
	
	if($("#id").val() != "")
		$("[name='autodupe'], [name='autonum']").attr("disabled", true);
	else
		$("[name='autodupe'], [name='autonum']").attr("disabled", false);
		
	film_description_keyup()
}

function clear_all_fields()
{
	$(".style_info:input[type=radio]")
		.each(function(){
				$(this).prop("checked", false)
		})
	
	$(".style_info:input").each(function(){
		switch($(this).attr("type"))
		{
			case "text":
				$(this).val('');
				break;
			case "checkbox":
				$(this).prop("checked", false)
				break;
			case "radio":
					if($(this).attr("default"))
					{
						$(this).click();
					}
				break;
			default:
				$(this).val("")
				break;
		};
	});
		
	$("[name=language]").val("N/A")
	$(".descriptions").val('');
	$("#sexploitation").prop("checked", false)
	if(!$.fieldLocks.xrated.locked)
		$("#xrated").prop("checked", false)
	
	$(".auction").val('');
}

function start_set()
{
	reset_field_locks();
	
	$.valid = true;
	$(".set:not([name=setHowRolled])").each(function(){
		if($(this).val() == "")
			$.valid = false;
	});
	if(!$.valid)
	{
		alert("You did not fill in all required fields!");
		return false;
	}
	
	get_style_info_form($("[name=setTypeCode]").val(), $("[name=setAuctionCode]").val(), true, function(){$("[name=film_title]").focus();});
	
	$.session = {};
	
	$.session.consignor = $("[name=setConsignor]").val();
	$.session.photography_folder = $("[name=setPhotographyFolder]").val();
	$.session.auction_code = $("[name=setAuctionCode]").val();
	$.session.type_code = $("[name=setTypeCode]").val();
	$.session.how_stored = $("[name=setHowStored]").val();
	$.session.how_rolled = $("[name=setHowRolled]").val();
	
	$.session.demonym = get_demonym($.session.type_code);
	
	$.ajax({
		url: "get_set_ids.php",
		async: false,
		dataType: "json",
		data: {
			"photography_folder" : $.session.photography_folder,
			"action" : "set-start",
		},
		success: function(data){
			$.session.pset_id = data.pset_id;
			$.session.set_id = data.set_id;
		},
	});
	
	load_session();
	get_photos();
	
	$.cookie("descriptions-" + $.userId, JSON.stringify($.session), {expires: 20});
	
	$.setPopup.dialog("close")
	
	$("#film_title").focus()
	
	return true;
}

function start_consignor_set()
{
	reset_field_locks();
	
	$.valid = true;
	$(".set:not([name=setHowRolled])").each(function(){
		if($(this).val() == "")
			$.valid = false;
	});
	if(!$.valid)
	{
		alert("You did not fill in all required fields!");
		return false;
	}
	
	get_style_info_form($("[name=setTypeCode]").val(), $("[name=setAuctionCode]").val(), true, function(){$("[name=film_title]").focus()});
	
	$(".style_info:input").each(function(){
			switch($(this).attr("type"))
			{
				case "radio":
						if($(this).attr("default"))
						{
							$(this).click();
							//$(this).attr("checked", true)
						}
						else
						{
							//$(this).removeAttr("checked")
							$(this).prop("checked", false)
						}
					break;
			};
		});	
	
	$.session.consignor = $("[name=setConsignor]").val();
	$.session.photography_folder = $("[name=setPhotographyFolder]").val();
	$.session.auction_code = $("[name=setAuctionCode]").val();
	$.session.type_code = $("[name=setTypeCode]").val();
	$.session.how_stored = $("[name=setHowStored]").val();
	$.session.how_rolled = $("[name=setHowRolled]").val();
	
	$.ajax({
		url: "get_set_ids.php",
		async: false,
		dataType: "json",
		data: {
			"photography_folder" : $.session.photography_folder,
			"action" : "set-new-consignor",
		},
		success: function(data){
			$.session.set_id = data.set_id;
		},
	});
	
	load_session();
	
	$.cookie("descriptions-" + $.userId, JSON.stringify($.session), {expires: 20});
	
	$.setPopup.dialog("close")
	
	$("#film_title").focus()
}

function load_session()
{
	$("[name=consignor]").val($.session.consignor);
	$("[name=photography_folder]").val($.session.photography_folder);
	$("[name=auction_code]").val($.session.auction_code);
	$("[name=type_code]").val($.session.type_code);
	$("[name=how_stored]").val($.session.how_stored);
	$("[name=how_rolled]").val($.session.how_rolled);
	//$("[name=style_id]").val($.session.style_id);
	$("[name=pset_id]").val($.session.pset_id);
	$("[name=set_id]").val($.session.set_id);
	$("[name=who_did]").val($.userId);
}

function load_session_edit_set()
{
	$(".editSet[name=consignor]").val($.session.consignor);
	$(".editSet[name=photography_folder]").val($.session.photography_folder);
	$(".editSet[name=auction_code]").val($.session.auction_code);
	$(".editSet[name=type_code]").val($.session.type_code);
	$(".editSet[name=how_stored]").val($.session.how_stored);
	$(".editSet[name=how_rolled]").val($.session.how_rolled);
	//$(".editSet[name=style_id]").val($.session.style_id);
	$(".editSet[name=pset_id]").val($.session.pset_id);
	$(".editSet[name=set_id]").val($.session.set_id);
	$(".editSet[name=who_did]").val($.userId);
}

function getDesc(film_title, base64encoded)
{
	if(!film_title)
		film_title = $("[name=film_title]").val();
	else if(base64encoded)
		film_title = Base64.decode(film_title);
	
	if($.lastDescTitle === film_title)
	{
		return false;
	}
	
	if($.artist_ajax)
		$.artist_ajax.abort()
		
	$.lastDescTitle = film_title;
	
	$.ajaxq("get_desc", {
		url: "getDesc.php",
		type: "post",
		async: false,
		dataType: "json",
		//We need the type code for the "1st release year" field auto-describer behavior 
		data: "film_title=" + encodeURIComponent(film_title)+"&type_code="+encodeURIComponent($(".auction[name=type_code]").val()),
		success: function(data)
		{
			parseData(data);
			get_repro_notes()
			
			if($("#signed_by_star").get(0).enabled)
			{
				if($(".auction[name=auction_code]").val() !== "SIGNED" && $(".auction[name=type_code]").val() !== "art print")
					$("#styleBox table").show(0)
				$("[name=signedby].style_info").val(data.descriptionsRow['TITLE'].titleCase())
			}
			
			suggest_artists()
		},
		error: function() {
			alert("Unknown error getting description!");
		},
	});
}

function getDescTitle(film_title)
{	
	if($.lastDescTitle === film_title)
	{
		return false;
	}
	
	$.lastDescTitle = film_title;
	
	$.ajax({
		type: "POST",
		async: false,
		url: "./getDesc.php",
		dataType: "json",
		data: "film_title=" + encodeURIComponent(film_title),
		success: function(data){
			parseData(data);
		},
		error: function(XMLHttpRequest, textStatus, errorThrown){
			ajaxError(XMLHttpRequest, textStatus, errorThrown);
		}
	});
}

function delete_description()
{		
	if(confirm("Are you sure you want to delete this film description?"))
	{
		var film_title = encodeURIComponent($("[name=film_title]").val());
		
		$.ajax({
			type: "POST",
			async: false,
			url: "./deleteDesc.php",
			dataType: "json",
			data: "film_title=" + film_title,
			success: function(data){
				if('error' in data)
					alert(data.error);
				else
				{		
					$(".descriptions, [name=film_title]").val('');
				}
			},
			error: function(XMLHttpRequest, textStatus, errorThrown){
				alert(XMLHttpRequest.responseText);
			}
		});
	}
}

function rename_description()
{
	if($("[name=film_title]").val() == "")
	{
		alert("There is no description open!");
		return false;
	}
		
	$.new_title = prompt("What do you want to rename '" + $("[name=film_title]").val() + "' to?", $("[name=film_title]").val());
	
	if(!$.new_title)
		return false;
	
	var old_title = $("[name=film_title]").val();
	
	$.ajax({
		type: "POST",
		async: false,
		url: "./rename.php",
		dataType: "json",
		data: "old_title=" + encodeURIComponent(old_title) + "&new_title=" + encodeURIComponent($.new_title),
		success: function(data){
			if('error' in data)
				alert(data.error);
			else
			{
				$("[name=film_title]").val($.new_title);
				getDesc();				
			}
		},
		error: function(XMLHttpRequest, textStatus, errorThrown){
			alert(XMLHttpRequest.responseText);
		}
	});
}

function delete_record()
{
	if($("#id").val() == "")
	{
		$(".auction, .descriptions").val('');
		$("#sexploitation").prop("checked", false)
			if(!$.fieldLocks.xrated.locked)
				$("#xrated").prop("checked", false)
		
		$("#titleDisplay")
			.attr("href", null)
			.removeAttr("title")
			.html('');
		$("#searchimdb").parents(":eq(0)").hide();
		
		$(".style_info:input").each(function(){
			switch($(this).attr("type"))
			{
				case "text":
					$(this).val('');
					break;
				case "checkbox":
					$(this).prop("checked", false)
					break;
				case "radio":
						if($(this).attr("default"))
						{
							$(this).click();
							//$(this).attr("checked", true)
						}
						else
						{
							//$(this).removeAttr("checked")
							$(this).prop("checked", false)
						}
					break;
				default:
					$(this).val("")
					break;
			};
		});
		
		fill_in_locked_fields();
		load_session();
		
		return false;
	}
		
	if(!confirm("Are you sure you want to delete this 1 record?"))
		return false;
		
	var id = $("[name='id']").val();
	
	var ajaxData = "id=" + encodeURIComponent(id) + "&photography_folder=" + encodeURIComponent($("[name=photography_folder]").val());
	
	$.ajax({
		type: "POST",
		async: false,
		url: "./delete.php",
		dataType: "json",
		data: ajaxData,
		success: function(data){
			parseData(data);
			
			//Get set total
			$.ajax({
				url:"set_total.php",
				type: "post",
				data: "set_id=" + $.session.set_id + "&who=" + $.userId + "&photography_folder=" + encodeURIComponent($("[name=photography_folder]").val()),
				dataType: "json",
				success: function(data){
					
					if('total' in data)
						$("#setTotal").html(data.total + " records in set").show()
					
					$("#messages").empty().hide()
				},
				error: function() {
					alert("unknown error getting set total!");
				},
			});
			
		},
		error: function(XMLHttpRequest, textStatus, errorThrown){
			alert(XMLHttpRequest.responseText);
		}
	});
}

function editing_set()
{
	load_session_edit_set();
	
	$.ajax({
		url:"set_total.php",
		async: false,
		type: "post",
		data: "set_id=" + $.session.set_id + "&who=" + $.userId,
		dataType: "json",
		success: function(data){
			if('error' in data)
			{
				alert(data.error);
				return false;
			}
			
			$.total = data.total;
		},
		error: function() {
			alert("unknown error getting set total!");
		},
	});
	
	$.setPopup = $("#editSetBox")
		.css({
			overflow: "auto",
		})
		.dialog({
			maxHeight: 500,
			width: 740,
			title: "Edit current set (" + $.total + " records in total)",
			modal: true,
			open: function(){
				//set_box_autocompletes()
				console.debug("set")
			}
		})
	
	delete $.total;
}

function edit_set()
{
	postData = "setData=" + encodeURIComponent(JSON.stringify($(".editSet:not([disabled])").createValueArray())) + "&set_id=" + $.session.set_id + "&who=" + $.userId;
	$.ajax({
		url: "edit_set.php",
		async: false,
		type: "post",
		dataType: "json",
		data: postData,
		success: function(data) {
			if('error' in data)
			{
				alert(data.error);
				return false;
			}
			
			if($.session.type_code != $("[name=type_code].editSet").val())
				get_style_info_form(false, false, false);
			
			$.session.consignor = $("[name=consignor].editSet").val();
			$.session.photography_folder = $("[name=photography_folder].editSet").val();
			$.session.auction_code = $("[name=auction_code].editSet").val();
			$.session.type_code = $("[name=type_code].editSet").val();
			$.session.how_stored = $("[name=how_stored].editSet").val();
			$.session.how_rolled = $("[name=how_rolled].editSet").val();			
			$.session.demonym = get_demonym($.session.type_code);
			
			if($("[name=set_id]").val() == $.session.set_id)
			{
				load_session();
				get_photos();
			}
			
			$.cookie("descriptions-" + $.userId, JSON.stringify($.session), {expires: 20});
			
			alert("Changed set information for " + data.affected_rows + " rows.");
			
			$.setPopup.dialog("close");
			
			$(".editSet").attr("disabled", true);
			$("#editSetBox [type=checkbox]").prop("checked", false)
		},
		error: function() {
			alert("unknown error editing set!");
		},
	});
}

function finish_consignor_set()
{
	reset_field_locks()
	insert_update("last");
	
	$(".set[name=setConsignor]").val('');
	$("[name=setPhotographyFolder]").val($.session.photography_folder);
	$("[name=setAuctionCode]").val($.session.auction_code);
	$("[name=setTypeCode]").val($.session.type_code);
	$("[name=setHowStored]").val($.session.how_stored);
	$("[name=setHowRolled]").val($.session.how_rolled);
			
	$("#setBox [type=button]").unbind('click').click(function(){
		start_consignor_set();
	});
	
	$.setPopup = $("#setBox")
		.css({
			overflow: "auto",
		})
		.dialog({
			maxHeight: 500,
			width: 740,
			modal: true,
			title: "Start a New Consignor Set",
			open: function(event, ui){
				$("#extraInfoSet").show().load("get_auction_schedule.php")
				//set_box_autocompletes()
			}
		})
	
	
	$($.setPopup).find("input:first").focus()
}

function finish_entire_set()
{
	reset_field_locks();
	insert_update("last");
	
	$.ajax({
		url: "get_set_summary.php",
		async: false,
		dataType: "text",
		type: "post",
		data: "pset_id=" + $.session.pset_id + "&photography_folder=" + encodeURIComponent($.session.photography_folder),
		success: function(data){
			if(data === "norows")
			{
				finishing_entire_set();
			}
			else
			{
				var lockStuff = "";
				for(x in $.fieldLocks)
				{
					if($.fieldLocks[x].locked === true)
						lockStuff += x + ", ";
				}
				if(lockStuff.length > 0)
				{
					lockStuff = lockStuff.slice(0, -2);
					lockStuff = "<br />You had these fields locked:<br /><small>" + lockStuff + "</small><br /><br />";
				}
				
				var allowClose = ($("<div>" + data + "</div>").find("#consignor_warning").length == 0)
				
				$.summaryBox = $("<div style='max-height: 600px; overflow: auto'>" + data + lockStuff + 
					"If this information looks correct, click 'Continue'<br />"+
					"<input type='button' value='Continue' onclick='if(hide_summary_box()){finishing_entire_set()}' /></div>")
					.css({
						overflow: "auto",
					})
					.dialog({
						maxHeight: 500,
						width: 700,
						modal: true,
						title: "Set Summary",
						dialogClass: (allowClose ? "" : "no-close"),
						closeOnEscape: allowClose,
					})
			}
		},
		error: function(){
			alert("Unknown error getting set summary!");
		},
	});
}

function hide_summary_box()
{
	if($("#consignor_warning").length)
	{
		if($.summaryBox.find("#set_notes").val().trim().length == 0)
		{
			$("<div />")
				.append("The Notes field is required when consignors do not match!")
				.css({
					overflow: "auto",
				})
				.dialog({
					maxHeight: 500,
					width: 700,
					title: "Error!",
					modal: true,
					buttons: {
						Ok: function() {
							$(this).dialog( "close" );
						}
					}
				})
			return false
		}
		else if($.summaryBox.find("#set_notes").val().trim().length < 5)
		{
			alert("Note does not meet minimum character requirement.")
			return false
		}
	}
	
	
	$.summaryBox.dialog("destroy").remove()
	return true
}

function update_set_comment(input, photography_code)
{
	$(input).css("background", "")
	if($.update_set_comment_ajax)
			$.update_set_comment_ajax.abort()
			
	$.update_set_comment_timeout = setTimeout(get_update_set_comment_function(input,photography_code), 750)
}

function get_update_set_comment_function(input, photography_code)
{
	return function update_set_comment2()
	{
		$.update_set_comment_ajax = $.ajax({
			url: "update_set_comment.php",
			type: "post",
			data: "describer_comments="+encodeURIComponent($(input).val())+
				"&photography_code="+encodeURIComponent(photography_code),
			dataType: "text",
			success: function(data){
				if(data != "OK")
					alert(data)
				else
					$(input).css("background", "rgb(200,255,200)")
			}
			
		})
	}
}


function finishing_entire_set()
{
	$.session.finishing_entire_set = true;
	$.cookie("descriptions-" + $.userId, JSON.stringify($.session), {expires: 20});
	$("#signed_by_star").attr("src", "graphics/star_grey.png").get(0).enabled = false
	
	$.ajax({
		url: "mark_set_finished.php",
		type: "post",
		dataType: "json",
		data: "pset_id=" + $.session.pset_id,
		success: function(data){
			if('error' in data)
			{
				alert(data.error);
				return false;
			}
		},
		error: function(){
			alert("Unknown error marking set as finished");
		},
	});
	
	$(".set:input").val('');
	
	$("#setBox [type=button]").unbind('click').click(function(){
		if(start_set())
		{
			$.session.finishing_entire_set = false
			$.cookie("descriptions-" + $.userId, JSON.stringify($.session), {expires: 20});
		}
	});
	
	$.setPopup = $("#setBox")
		.css({
			overflow: "auto",
		})
		.dialog({
			maxHeight: 500,
			width: 740,
			title: "Start a New Set",
			modal: true,
			open: function(event, ui){
				$("#extraInfoSet").show().load("get_auction_schedule.php")
				//set_box_autocompletes()
			}
		})
	
	$($.setPopup).find("input:first").focus()
}

function nw(url,w,h)
{
	if(!w)
		w = 400;
	if(!h)
		h = 400;
	window.open(url,"newwin","left=20,top=20,width="+w+",height="+h+",toolbar=0,status=0,resizable=0,location=0,menubar=0,scrollbars=1");
}

function change_background()
{
	next = parseInt($.cookie("bg"))+1;
	if(next > $.backgrounds.length-1)
		next = 0;
	$.cookie("bg", next, {expires: 30});
	$(document.body).css("backgroundImage", "url('graphics/"+$.backgrounds[$.cookie("bg")]+"')");
}

function get_demonym(type_code)
{
	$.ajax({
		url: "get_demonym.php",
		async: false,
		type: "post",
		data: "type_code=" + encodeURIComponent(type_code),
		dataType: "json",
		success: function(data){
			if('demonym' in data)
				$.demonym = data.demonym;
			else
				$.demonym = false;
				
			if('links' in data && data.links){
			  /*var parsed = $(data.links).each(function(){
			    console.log($(this))
          if ($(this).is("a") && (this).attr("target") !== undefined && (this).attr("target") !== false){
            $(this).attr("target","_blank")
          }
			  })*/
			 var parsed = "";
			 htmlstr = $.parseHTML(data.links);
			 $.each(htmlstr,function(){
			   if ($(this).is("a") && ($(this).attr("target") === undefined || $(this).attr("target") === false)){
            $(this).attr("target","_blank")
          }
			 })
			 console.log(htmlstr)
				$("#links").html(htmlstr).show();
			}else
				$("#links").html("").hide();
		}
	});
	
	return $.demonym;
}

function edit_tooltip(id)
{
	$("#editBox textarea").val(tooltips[id][0]);
	$("#editBox input[value='Save']").unbind("click");
	$("#editBox td:eq(0)").html("Notes for: <i>"+ id +"</i>");
	$("#editBox input[value='Save']").click(function(){
		save_tooltip(id);
	});
	$("#editBox").show(200);
}

function save_tooltip(id)
{
	note = $("#editBox textarea").val();
	
	$.ajax({
		type: "POST",
		url: "save_tooltip.php",
		dataType: "json",
		data: "&note=" + encodeURIComponent(note) + "&field=" + encodeURIComponent(id),
		success: function(data){
			if('success' in data)
				alert('Saved.');
			else if('error' in data)
				alert(data.error);
			else
				alert("A really weird error occurred. Your error code is 796F75207375636B.\n Have a nice day.");
		},
		error: function(XMLHttpRequest, textStatus, errorThrown){
			ajaxError(XMLHttpRequest, textStatus, errorThrown);
		}
	});
}

function reset_field_locks()
{
	for(x in $.fieldLocks)
	{
		$.fieldLocks[x].locked = false;
		$.fieldLocks[x].value = false;		
	}
}

function fill_in_locked_fields()
{
	for(lockId in $.fieldLocks)
	{
		if($.fieldLocks[lockId].locked)
		{
			value = $.fieldLocks[lockId].value;
			
			switch($("[name='"+lockId+"']").attr("type"))
			{
				case "checkbox":
					if(value !== "0")
						$("[name='"+lockId+"']").prop("checked", true);
					else
						$("[name='"+lockId+"']").prop("checked", false)
						
					break;
				case "radio":
					$.temp = value;
					$("[name='"+lockId+"']").each(function(){
						if(this.value == $.temp)
						{
							$(this).click();
							//$(this).attr("checked", true);
						}
						else
						{
							//$(this).removeAttr("checked")
							$(this).prop("checked", false)
						}
					});
					break;
				default:
					if(value == "0")
						$("[name='"+lockId+"']").val('');
					else
						$("[name='"+lockId+"']").val(value);
					break; 
			};
		}
	}
}

function get_photos()
{
	$("#photos").empty().hide();
	
	$.ajax({
		url: "get_crop_images.php",
		type: "post",
		data: {photography_code: $("[name=photography_folder]").val()},
		dataType: "json",
		success: function(data){
			if(!data.images || data.images.length == 0)
			{
				return false;
			}
			else
			{
				$("#photos")
					.append("<div style='margin-top: -5px'>"+
						"<input type='radio' name='scroll' onclick='$.scrollShots=false' />"+
						"<label style='display: inline'>No Scroll <input type='radio' name='scroll' checked='checked' onclick='$.scrollShots = true; $.reverseScroll = false;'/>"+
						"<label style='display: inline'>Scroll</label> <input type='radio' name='scroll' onclick='$.scrollShots = true; $.reverseScroll = true;' />"+
						"<label style='display: inline'>Reverse Scroll</label> "+
						"<select style='width: 35px;'  onchange='$.cookie(\"shotsPerItem\", parseInt(this.value)); $.shotsPerItem = parseInt(this.value);'>"+
						"<option selected='selected'>"+$.cookie("shotsPerItem")+"</option>"+
						"<option>1</option><option>2</option><option>3</option><option>4</option><option>5</option><option>6</option><option>7</option><option>8</option></select><label style='display: inline'>Shots per item</label></div>");
				
				num = 0;
				for(x in data.images)
				{
					num++;
					img = $("<img style='vertical-align: top; border-style: none; margin: 0 15 0 2; max-width: 200px;' src='http://images/"+data.images[x][0]+"' />");
					a = $("<a target='_blank' href='http://images/"+data.images[x][1]+"' />").append(img);
					$("#photos").append("<small>"+num+"</small>").append(a);
				}
				
				$("#photos").show(1, function(){
					if($.recordNumber)
						$("#photos img").css("border-style", "none");
					if($.recordNumber && $.scrollShots)
					{
						index = ($.reverseScroll) ? $("#photos img").count - $.recordNumber*$.shotsPerItem - 1 : $.recordNumber*$.shotsPerItem - 1;
						if($("#photos img:eq("+index+")").length)
						{
							for(x = index - $.shotsPerItem + 1; x <= index; x++)
								$("#photos img:eq("+x+")").css("border", "1px solid red");
			
							$("#photos img:eq("+(index-$.shotsPerItem+1)+")")[0].scrollIntoView(false);
			
							if((index-$.shotsPerItem+1) != index)
								$("#photos img:eq("+index+")")[0].scrollIntoView(false);
						
						}
					}
				});
			}
		},
		error: function(){
			alert("Unknown error getting image ids!");
		},
	});
}

function get_photos_old()
{
	$("#photos").empty().hide();
	$.ajax({
		url: "get_image_ids.php",
		type: "post",
		data: "photography_code=" + encodeURIComponent($("[name=photography_folder]").val()),
		dataType: "json",
		success: function(data){
			if("norecord" in data)
				return false;
			else
			{
				$("#photos").append("<div style='margin-top: -5px'><input type='radio' name='scroll' onclick='$.scrollShots=false' /><label style='display: inline'>No Scroll <input type='radio' name='scroll' checked='checked' onclick='$.scrollShots = true; $.reverseScroll = false;'/><label style='display: inline'>Scroll</label> <input type='radio' name='scroll' onclick='$.scrollShots = true; $.reverseScroll = true;' /><label style='display: inline'>Reverse Scroll</label> <select style='width: 35px;' value='1' onchange='$.cookie(\"shotsPerItem\", parseInt(this.value)); $.shotsPerItem = parseInt(this.value);'><option>1</option><option>"+$.cookie("shotsPerItem")+"</option><option>2</option><option>3</option><option>4</option><option>5</option><option>6</option><option>7</option><option>8</option></select><label style='display: inline'>Shots per item</label></div>");
				num = 0;
				for(x in data.images)
				{
					num++;
					img = $("<img style='vertical-align: top; border-style: none; margin: 0 15 0 2; max-width: 200px;' src='http://poster-server/descriptions/get_image.php?path=" + encodeURIComponent(data.path) + "&filename=" + encodeURIComponent(data.images[x]) + "' />");
					a = $("<a target='_blank' href='http://poster-server/descriptions/get_image.php?fullSize=1&path=" + encodeURIComponent(data.path) + "&filename=" + encodeURIComponent(data.images[x]) + "' />").append(img);
					$("#photos").append("<small>"+num+"</small>").append(a);
				}
				
				$("#photos").show(1, function(){
					if($.recordNumber)
						$("#photos img").css("border-style", "none");
					if($.recordNumber && $.scrollShots)
					{
						index = ($.reverseScroll) ? $("#photos img").count - $.recordNumber*$.shotsPerItem - 1 : $.recordNumber*$.shotsPerItem - 1;
						if($("#photos img:eq("+index+")").length)
						{
							for(x = index - $.shotsPerItem + 1; x <= index; x++)
								$("#photos img:eq("+x+")").css("border", "1px solid red");
			
							$("#photos img:eq("+(index-$.shotsPerItem+1)+")")[0].scrollIntoView(false);
			
							if((index-$.shotsPerItem+1) != index)
								$("#photos img:eq("+index+")")[0].scrollIntoView(false);
						
						}
					}
				});
			}
		},
		error: function(){
			alert("Unknown error getting image ids!");
		},
	});
}

$.field_labels_with_underlines = {
	"Additional Details" : "<u>A</u>dditional Details",
	"Film Description" : "Film <u>D</u>escription",
	"Reissue Year" : "R<u>e</u>issue Year",
	"Litho" : "<u>L</u>itho",
	"Measurements" : "<b>M</b>easurements",
	"Pastebox" : "<u>P</u>astebox",
	"Title" : "<u>T</u>itle",
	"Quantity" : "<u>Q</u>uantity",
	"Reissued" : "<u>R</u>eissue",
	"Style Box" : "<u>S</u>tyle Box",
	"Reissue" : "R<u>e</u>issue",
	"1st Release Year" : "<u>1</u>st Release Year",
}

function autocorrect_measurements_field(field)
{
	/*
	Function: autocorrect_measurements_field
	
	Tuns this: '11 1/2 17' to this: '11 1/2" x 17"'
	*/
	matches = field.value.trim().match(/^([\d]{1,3}( [\d]\/[\d]|))(x| x | )([\d]{1,3}( [\d]\/[\d]|))(x| x | )([\d]{1,3}( [\d]\/[\d]|))$/);
	if(matches == null){
	  matches = field.value.trim().match(/^([\d]{1,3}( [\d]\/[\d]|))(x| x | )([\d]{1,3}( [\d]\/[\d]|))$/);  
	}
	console.log(matches)
	if(matches && matches.length === 6)
	{
		newVal = matches[1] + "\" x " + matches[4] + "\"";
		field.value = newVal;
	}else if(matches && matches.length === 9)
  {
    newVal = matches[1] + "\" x " + matches[4] + "\" x " + matches[7] + "\"";
    field.value = newVal;
  }
	field.value = field.value.replace("X", "x");
}


function init_fields()
{
	$(".clearme").val('');
	$(".emptyme").empty();

	$("[name=glued], [name=taped], [name=incomplete]").next().css("background", "");

	$("#trimmed").prop("checked", false).removeAttr("disabled")
	
	$("[name=signedby]").removeAttr("disabled")
	
	$("[name=measurements]").blur(function(){
	  console.log($("[name=type_code]").val())
	  if($("[name=type_code]").val()!='t-shirt'){
		  autocorrect_measurements_field(this);
		}
	});
	
	$("[name=firstrelease]").change(function(){
		if(this.value.length)
	  		$("[name=reissueyear]").val("")
	})
	
	$("[name=reissueyear]").change(function(){
		if(this.value.length)
			$("[name=firstrelease]").val("")	
	})
	
	$("#imdbYear").attr("href", "javascript:void(0)").unbind("click").click(function(){
		if($("[name='IMDb URL']").val().indexOf("imdb.com/title/") != -1)
		{
			url = $("[name='IMDb URL']").val().replace("/combined", "").replace("/reference","").replace(/\/+$/, "") + "/releaseinfo"
			window.open(url)
		}
		else
			alert("Usable URL not found in URL field")
	})
	
	$("[name=film_title]").focus(function(){
		$("textarea:not([name='eBay Description'])").css("border", "1px solid rgb(127,157,185)");
		iframe = $("[name='eBay Description']").next().find("iframe")[0];
		$(iframe).attr("tabindex", 600).css("border", "1px solid rgb(127,157,185)");
	});
	
	$("[name=measurements]").parents(":eq(0)").prev().find("label").css({"text-decoration": "underline", cursor: "pointer"})
		.unbind("click")
		.click(function(){
			$("<div style='width: 400px'>You can type measurements into this field more quickly by leaving out quotes, spaces, and 'x'. "+
				"They will be added automatically. Examples:<br />"+
				"<ul style='margin-left: 20px'><li>27 40 &rarr; 27\" x 40\"</li><li>23 1/2 33 3/4 &rarr; 23 1/2\" x 33 3/4\"</li><li>8 1/2 10 &rarr; 8 1/2\" x 10\"</li></ul></div>")
				.css({
					overflow: "auto",
				})
				.dialog({
					maxHeight: 500,
					width: 700,
					title: "Notes for measurement field"
				})
		});
	
	$("[name='coproduction']").click(function(){
		if(this.checked)
			$("[name='countryoforigin']").prop("checked", true);
	});
	
	$("[name='countryoforigin']").click(function(){
		if(!this.checked)
			$("[name='coproduction']").prop("checked", false)
	});
	
	$("[name=how_rolled]").val($.session.how_rolled);
	
	if($("#id").val() != "")
		$("[name='autodupe'], [name='autonum']").attr("disabled", true);
	else
		$("[name='autodupe'], [name='autonum']").removeAttr("disabled")
		
	$("label").each(function(){
		for(old in $.field_labels_with_underlines)
		{
			new_ = $.field_labels_with_underlines[old];
			if($(this).html() == old)
			{
				$(this).html(new_);
				break;
			}
			
		}	
	});
}

// OLD CODE ALERT
// Checks description for taglines, stars, etc. and Reports if they don't meet criteria
function checkTheDescription()
{
	reminders = [];	
	warnings = [];
	
	if($("#imdburl").val().toLowerCase().trim() == "not in imdb" && 
		$("[name='eBay Description']").val().toLowerCase().indexOf("if anyone knows more") == -1 &&
		$("[name=type_of_record]").val() != "redirect")
	{
		reminders.push("Did you forget to put an IAK in the description?")
	}
	
	if($("[name=type_of_record]").val() == "redirect" && $("[name='eBay Description']").val().indexOf("see") !== 0)
	{
		reminders.push("Was that a see title? It's marked as a see title.")
	}
	
	var a = $("[name='eBay Description']").val().match(/(?:starring )(.*)/);
	if(a)
	{
		a = a[1];
		a = a.replace(/ \([^\(\)]+\)|\"[^\"]+\"/g, "");
		a = a.replace(/, and /, ", ");
		a = a.split(/Note that|If anyone|If you have|\<|Apparently/);
		a = a[0];
		a = a.split(/(?:,[\s]*|[\s]+and[\s]+)|[\s]+&[\s]+/);
	}

	var myDescription = $("[name='eBay Description']").val().stripHTML();
	var myTitle = document.getElementsByName("film_title")[0].value;
	var myType = $("#topPane [name=type_code]").val();
	var myImdb = document.getElementsByName("IMDb URL")[0].value;
	
	if(document.getElementsByName("Year/NSS")[0].value.length == 0 && 
		$("[name=type_of_record]").val() == "movie")
		reminders.push("Your Year/NSS field was blank.");
	
	if($("[name=Studio]").val().length == 0 && 
		$("[name=type_of_record]").val() == "movie")
		reminders.push("Your Studio field was blank.");
	
	if(myDescription.search("\\([^\\)\\(]*\\) (starring|featuring)") == -1 && myType.search('^(1sh|1/2sh|insert|3sh|6sh)') != -1 && $("[name=type_of_record]").val() == "movie")
	{
		reminders.push("That description did not appear to have any taglines.");
	}
	
	/*if((($("[name=auction_code]").val() == "LCMULTI" && $("[name=type_code]").val() == "LC") || 
			$("[name='auction_code']").val() == "8x10MULTI" && $("[name=type_code]").val() == "8x10"
		) && $("[name='quantity']").val() == "")
		warnings.push("Did you set the quantity correctly?");*/
		
	if(a && a.length < 5)
		reminders.push("That description did not appear to have 5 stars listed");
		
	if(myImdb == "" && document.getElementsByName("film_title")[0].value != "")
		reminders.push("You did not enter an IMDb URL for that description.");
		
	if(document.getElementsByName("reissueyear").length > 0)
	{
		var reissueyear = document.getElementsByName("reissueyear")[0].value;
		if(reissueyear != "" && document.getElementsByName("Reissued")[0].value.indexOf(reissueyear) == -1)
			reminders.push("Did you forget to add '"+reissueyear+"' to the Reissue field?");
	}
		
	
	/*
		If the film is 1928 or 1929, and the info notes does not contain
		a record that says in the heading "SILENT" or "TALKING",
		tell them to insert such a record
	*/
	if($("[name='Year/NSS']").val() != "" && 
			parseInt($("[name='Year/NSS']").val().slice(0, 4)) >= 1928 && 
			parseInt($("[name='Year/NSS']").val().slice(0, 4)) <= 1929 && 
			$("[name='Year/NSS']").val().slice(4,5) !== "-")
	{
		info_notes_data = $.info_notes_box.modified_data
		display = true
		for(x in info_notes_data)
		{
			heading = info_notes_data[x]['heading'];
			type = info_notes_data[x]['type_code'];
			sort_order = info_notes_data[x]['sort_order'];
			
			if(type == "" && sort_order == -1 && 
				(heading.indexOf("SILENT") == 0 
				|| heading.indexOf("TALKING") == 0
				|| heading.indexOf("SILENT/TALKING") == 0
				|| heading.indexOf("TALKING/SILENT") == 0))
			{
				display = false
				break;
			}
		}
		
		if(display == true)
			warnings.push("Go back! You did not add a correct "+
				" SILENT, TALKING, or SILENT/TALKING entry to the info notes. (case sensitive)");
	}
	
	if(($("[name=type_code]").val() == "German program" ||
		$("[name=type_code]").val() == "pb" ||
		$("[name=type_code]").val() == "book" ||
		$("[name=type_code]").val() == "Campaign book" ||
		$("[name=type_code]").val() == "program" ||
		$("[name=type_code]").val() == "program book" || 
		$("[name=type_code]").val() == "promo book" ||
		$("[name=type_code]").val() == "promo brochure" ||
		$("[name=type_code]").val() == "sheet music" ||
		$("[name=type_code]").val() == "magazine, exhibitor") && 
		$("[name=pages]").val() == "")
		{
			warnings.push("# of pages is required.")
			reminders.push("If you know of an exception to this rule, tell Aaron.")
		}
	
	// If window card & FF
	if($("[name=type_code]").val().indexOf("WC") == 0 && $("[name='how_rolled']").val() == "FF")
		warnings.push("Window cards are not supposed to be 'FF'. "+
			"Ask someone if you don't know what to do here.");
	
	
	if($("[name='Year/NSS']").val() != "" && 
			parseInt($("[name='Year/NSS']").val().slice(0, 4)) <= 1927 && 
			$("[name='Year/NSS']").val().slice(4,5) !== "-" && 
			myDescription.indexOf("silent") === -1 && 
			myDescription.search("biograph") === -1)
		reminders.push("Did you forget to add 'silent' to the description?");
	
	
	if($("[name=type_of_record]").val() == "")
		reminders.push("You forgot to choose a type of record (see bottom of descriptions box)")
	
	if(document.getElementsByName("film_title")[0].value != "" && $("#topPane [name=consignor]").val() == "")
	{
		alert("You must enter a consignor!");
		return false;
	}
	
	if($("[name=additionaldetails]").val().indexOf("cinerama") != -1)
		reminders.push('IMPORTANT! "Cinerama" is a film process on which people collect.  "Cinerama Releasing" is a '+
						'crappy studio that no one cares about.  Do NOT mark it "Cinerama style"!  Here are visual '+
						'examples showing the differences:<hr /><table><tr><td>Cinerama Releasing (Studio)</td><td>'+
						'<img src="/pref/cinerama_releasing.png" /></td></tr><tr><td>Cinerama (film process)</td><td>'+
						'<img src="/pref/cinerama_film_process.png" /></td></tr></table>');
	
	if($("[name='eBay Description']").val().match(/(3D|3-D|3-dimension)/) && $("[name='eBay Description']").val().indexOf("3-D (3D; 3-Dimension)") === -1)
	{
		if(confirm("If this is a 3-D movie, the description should have \"3-D (3D; 3-Dimension)\" after the director but before the genre. Press OK to fix this or Cancel to continue."))
			return false;
	}
	
	if(($("[name='eBay Description']").val() == "" && document.getElementsByName("id")[0].value != "") || ($("[name='eBay Description']").val() == "" && document.getElementsByName("film_title")[0].value != ""))
	{
		alert("You cannot insert a blank description!");
		return false;
	}
	
	if($("[name='IMDb URL']").val() == "" && $("[name=film_title]").val() != "")
	{
		alert("You need to put something in the IMDb URL!");
		return false;
	}
	
	if($("[name=advance][value=advance]").prop("checked") && $("[name=advancetagline]").val() == "")
  {
    if(confirm("Advance tagline is missing! Press OK to fix this or Cancel to continue.")){
      return false;
    }
  }
	
	if($("[name=type_code]").val()!="t-shirt" && $("[name=measurements]").val() != "" &&
		!$("[name=measurements]").val().match(/^(trimmed (to )?|)([\d]{1,3}( [\d]\/[\d]|)("|')) x ([\d]{1,3}( [\d]\/[\d]|)("|'))$/))
		warnings.push("Check your measurements. Is '"+$("[name=measurements]").val()+"' a proper measurement?")
	
	//Check if multiple LCs with quantity of 1
	if(document.getElementsByName("type_code")[0].value == "LCs, multiples" && document.getElementsByName("quantity")[0].value == 1)
		warnings.push("Did you set the quantity correctly?");
		
	output = {"reminders":reminders, "warnings":warnings}
	return output;
		
}

String.prototype.stripHTML = function()
{
        // What a tag looks like
        var matchTag = /<(?:.|\s)*?>/g;
        // Replace the tag
        return this.replace(matchTag, "");
};

String.prototype.titleCase = function(){
	words = this.split(" ");
	out = ""
	for(x in words)
	{
		if(words[x][0] == "(" ) //Stop processing at open parenthesis
			break;
		else if(words[x].match(/^([a-z]\.)+$/i)) //If we are handling initials
			out += words[x].toUpperCase() + " "
		else if(words[x].match(/^[a-z]+'[a-z]+$/i)) //If we are handling names with apostrophies like O'Brien
		{
			sections = words[x].split("'")
			
			out += sections[0].substr(0,1).toUpperCase() + sections[0].substr(1, sections[0].length-1).toLowerCase() + "'"
			out += sections[1].substr(0,1).toUpperCase() + sections[1].substr(1, sections[1].length-1).toLowerCase() + " "
		}
		else
			out += words[x].substr(0,1).toUpperCase() + words[x].substr(1,words[x].length-1).toLowerCase() + " "
	}
	return out.substring(0,out.length-1)
}

/*function update_mrlister_total()
{
	$("#mrlistercount").html($("[name=MrLTitle]").val().length + $("[name=film_title]").val().length);
}*/

function toggle_star(image)
{
	if(image.src.indexOf("star_grey.png") !== -1)
	{
		image.src = "graphics/star.png"
		image.enabled = true
	}
	else
	{
		image.src = "graphics/star_grey.png"
		image.enabled = false
	}
}

function open_auction_history()
{
	url = "http://emovieposter.com/agallery/searchfield/title/"
	
	if($("[name=film_title]").val() != "")
	{
		url += "search/"+encodeURIComponent(encodeURIComponent($("[name='film_title']").val().replace("\\", "\\\\").replace("/", "\\/"))) +
			"/type/"+encodeURIComponent(encodeURIComponent($("[name=type_code]").val().replace("\\", "\\\\").replace("/", "\\/")))
	}
	url += "/archive.html"
	window.open(url)
}

function click_link_in_description(index)
{
	iframe_document = $("[name='eBay Description']").next().find("iframe").get(0).contentDocument
	if(iframe_document)
		$(iframe_document).find("span[onclick]:eq("+index+")").click()
}


function open_see_titles2()
{
	SeeTitles.get($.lastDescTitle, function(data){
			widget = $(SeeTitles.widget(data))
			
			if(data.object && data.subject)
			{
				var title = "Redirects From/To "+$.lastDescTitle
				
				$("<h5 />")
					.text("Redirects To "+$.lastDescTitle)
					.insertBefore($(widget).find("ul.redirect-to"))
				
				$("<h5 />")
					.text("Redirects From "+$.lastDescTitle)
					.insertBefore($(widget).find("ul.redirect-from"))
			}
			else if(data.object)
			{
				var title = "Redirects To "+$.lastDescTitle
			}
			else
			{
				var title = "Redirects From "+$.lastDescTitle
			}
  			
			$(widget)
				.dialog({
					title: title,
					open: function(event, ui){
						$(this).find("input").focus()
					},
					width: 400,
					maxHeight: 640,
				})
	})
}


function open_see_titles()
{
	
	$.seetitle = $("<div />")
		.append("<iframe style='height: 450px; width: 450px' src=\"seetitle.php?title="+encodeURIComponent($("[name=film_title]").val())+"\"></iframe>")
		.css({
			overflow: "auto",
		})
		.dialog({
			height: 520,
			width: 500,
			title: "Edit Redirect Titles"
		});
}

function showStarsBox()
{
	if($("[name=film_title]").val() != "")
		return open_stars_box($("[name=film_title]").val(), $("[name='eBay Description']").text())
}

function check_year_field()
{
	yearnss = $("[name='Year/NSS']").val()
	
	if(yearnss.trim() == "")
		return true
	
	if($.year_field_ajax)
		$.year_field_ajax.abort()
	
	$.year_field_ajax = $.ajax({
		url: "check_year_field.php",
		type: "post",
		dataType: "json",
		data: "yearnss="+encodeURIComponent(yearnss),
		success: function(data){
			if("invalid" in data)
			{
				if($("#messages .invalidyear").length > 0)
					$("#messages .invalidyear").remove();
					
				$("#messages")
					.append("<span class='invalidyear'>The year field parser doesn't like what you put into "+
						"the year field. It says that it found <b>'"+data.found+"'</b>, but expected <b>"+
						data.expected+"</b> at position "+data.index+".</span>")
					.show()
			}
			else
			{
				$("#messages .invalidyear").remove()
				if($("#messages span").length == 0)
					$("#messages").hide()
			}
		}
	})
}

function check_artist_field()
{
	artist = $("[name='artist'].descriptions").val()
	
	if(artist.trim() == "")
		return true
	
	if($.year_field_ajax)
		$.year_field_ajax.abort()
	
	$.year_field_ajax = $.ajax({
		url: "check_artist_field.php",
		type: "post",
		dataType: "json",
		data: {artist: artist},
		success: function(data){
			if("invalid" in data)
			{
				if($("#messages .invalidyear").length > 0)
					$("#messages .invalidyear").remove();
					
				$("#messages")
					.append("<span class='invalidyear'>The artist field parser doesn't like what you put into "+
						"the artist field. It says that it found <b>'"+data.found+"'</b>, but expected <b>"+
						data.expected+"</b> at position "+data.index+".</span>")
					.show()
			}
			else
			{
				$("#messages .invalidyear").remove()
				if($("#messages span").length == 0)
					$("#messages").hide()
			}
		}
	})
}

function check_studio_field()
{
	studio = $("[name=Studio]").val()
	
	if(studio.trim() == "")
		return true
	
	if($.studio_field_ajax)
		$.studio_field_ajax.abort()
	
	$.studio_field_ajax = $.ajax({
		url: "check_studio_field.php",
		type: "post",
		dataType: "json",
		data: "studio="+encodeURIComponent(studio),
		success: function(data){
			if("invalid" in data)
			{
				if($("#messages .invalidstudio").length > 0)
					$("#messages .invalidstudio").remove()
				
				$("#messages")
					.append("<span class='invalidstudio'>The studio field parser doesn't like what you put into "+
						"the studio field. It says that it found <b>'"+data.found+"'</b>, but expected <b>"+
						data.expected+"</b> at position "+data.index+".</span>")
					.show()
			}
			else
			{
				$("#messages .invalidstudio").remove()
				if($("#messages span").length == 0)
					$("#messages").hide()
			}
		}
	})
}


function suggest_artists()
{
	if($(".descriptions[name=artist]").val() == "")
	{
		$("#messages div.artist_suggestions").remove()
		return false
	}
	
	$.artist_ajax = $.ajax({
		url: "/includes/artist_table.inc.php",
		type: "post",
		dataType: "json",
		data: "film_title="+encodeURIComponent($("[name=film_title]").val())+"&type_code="+encodeURIComponent($("[name=type_code]").val()),
		success: function(data){
			$("#messages div.artist_suggestions").remove()
			if(data.length)
			{				
				div = $("<div class='artist_suggestions' />").html("Artists: ")
				
				for(x in data)
				{
					if(data[x][2].indexOf("R") === 0)
						data[x][2] = "<b>"+data[x][2]+"</b>"
					
					$(div)
						.css({"marginTop":"10px", "marginBottom":"10px"})
						.append(
							$("<span />")
								.css({"background": "rgba(128,128,128, 0.2)", "padding": "3px"})
								.attr("name", data[x][1])
								.attr("class", "yellowHover clicky button")
								.click(function(){
									$("input.auction[name=artist]").val($(this).attr("name"))
								})
								.html(data[x][1])
								
								.append(" <small>("+(data[x][0]+" "+data[x][2]).trim()+")</small>")
						)
						.append(" ")
				}
				
				$("#messages").append(div).show()
			}
		}
	})
	
}


function get_reshoots()
{
	if($("#id").val() != "")
		Reshoots.get(Reshoots.create_get_handler({auction_table : "00-00-00 main (MAIN)", id : $("#id").val()}), "0")
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
			$("#reshoots").css("border", "")
		}
	})
}

function set_box_autocompletes()
{
	$("[name='setAuctionCode']")
		
		.autocomplete2({
			delay: 100,
			source: function(request, response) {
				$.getJSON("checkonetoone.php?name=auction_code", {term: request.term, consignor: $("#consignor").val(), photoFolder: $("#photoFolder").val(), auctionCode: $("#auctionCode").val(), typeCode: $("#typeCode").val() },
							response);
			},
			focus: function(event, ui) {
				get_auction_code_info(ui.item.value)
			},
			change: function (event, ui) {
				
				if($(this).data("list")[$(this).val().toLowerCase()])
				{
					if($(this).data("list")[$(this).val().toLowerCase()] != $(this).val())
						$(this).val($(this).data("list")[$(this).val().toLowerCase()])
				}
                else if(!ui.item)
				{
                    $(this).val("");
                }
            },
			response: function (event, ui){
				var list = []
				
				for(x in ui.content)
				{
					list[ui.content[x].value.toLowerCase()] = ui.content[x].value
				}
				
				$(this).data("list", list)
			}
		})
		.blur(function(){get_auction_code_info(this.value)});
		
		
	$("[name='setPhotographyFolder'], [name=photography_folder].editSet")
		.blur(function(){
			this.value = this.value.toUpperCase()
		})
		
		.autocomplete2({
			delay: 100,
			source: function(request, response) {
				$.getJSON("checkonetoone.php?name=photography_folder2", {term: request.term, consignor: $("#consignor").val(), photoFolder: $("#photoFolder").val(), auctionCode: $("#auctionCode").val(), typeCode: $("#typeCode").val() },
							response);
			}
		})
	
	$("[name='setPhotographyFolder']")
		.data("ui-autocomplete2")._renderItem = photography_folder_suggestion_renderer
		
	$("[name=photography_folder].editSet")
		.data("ui-autocomplete2")._renderItem = photography_folder_suggestion_renderer
	
	$("[name='setTypeCode']")
		
		.autocomplete2({
			delay: 100,
			source: function(request, response) {
				$.getJSON("checkonetoone.php?name=type_code", {term: request.term, consignor: $("#consignor").val(), photoFolder: $("#photoFolder").val(), auctionCode: $("#auctionCode").val(), typeCode: $("#typeCode").val() },
							response);
			},
			focus: function(event, ui){
				get_search_field(ui.item.value, $("#extraInfoSet").get());
			},
			change: function (event, ui) {
				
				if($(this).data("list")[$(this).val().toLowerCase()])
				{
					if($(this).data("list")[$(this).val().toLowerCase()] != $(this).val())
						$(this).val($(this).data("list")[$(this).val().toLowerCase()])
				}
                else if(!ui.item)
				{
                    $(this).val("");
                }
            },
			response: function (event, ui){
				var list = []
				
				for(x in ui.content)
				{
					list[ui.content[x].value.toLowerCase()] = ui.content[x].value
				}
				
				$(this).data("list", list)
			}
		})
		.focus(function(){
			get_search_field(this.value, $("#extraInfoSet").get())
		})
		.blur(function(){
			get_search_field(this.value, $("#extraInfoSet").get());
			$.session.demonym = get_demonym(this.value);
		});
			
	$("#editSetBox [name=type_code]")
		
		.autocomplete2({
			delay: 100,
			//source: "/includes/suggest2.php?name=type_code",
			source: function(request, response) {
				$.getJSON("checkonetoone.php?name=type_code", {term: request.term, consignor: $("#consignor").val(), photoFolder: $("#photoFolder").val(), auctionCode: $("#auctionCode").val(), typeCode: $("#typeCode").val() },
							response);
			},
			focus: function(event, ui){
				get_search_field(ui.item.value, $("#extraInfoEditSet").get());
			},
			change: function (event, ui) {
				
				if($(this).data("list")[$(this).val().toLowerCase()])
				{
					if($(this).data("list")[$(this).val().toLowerCase()] != $(this).val())
						$(this).val($(this).data("list")[$(this).val().toLowerCase()])
				}
                else if(!ui.item)
				{
                    $(this).val("");
                }
            },
			response: function (event, ui){
				var list = []
				
				for(x in ui.content)
				{
					list[ui.content[x].value.toLowerCase()] = ui.content[x].value
				}
				
				$(this).data("list", list)
			}
		})
		.focus(function(){
			get_search_field(this.value, $("#extraInfoEditSet").get())
		})
		.blur(function(){
			get_search_field(this.value, $("#extraInfoEditSet").get());
			$.session.demonym = get_demonym(this.value);
		});
		
	$("[name='setConsignor']")
		.autocomplete2({
			delay: 100,
			source: function(request, response) {
				$.getJSON("checkonetoone.php?name=consignor2", {term: request.term, consignor: $("#consignor").val(), photoFolder: $("#photoFolder").val(), auctionCode: $("#auctionCode").val(), typeCode: $("#typeCode").val() },
							response);
			},
			focus: function(event, ui){
				get_consignor_notes(ui.item.ConsignorName, $("#extraInfoSet").get())
			},
			change: function (event, ui) {
				
				if($(this).data("list")[$(this).val().toLowerCase()])
				{
					if($(this).data("list")[$(this).val().toLowerCase()] != $(this).val())
						$(this).val($(this).data("list")[$(this).val().toLowerCase()])
				}
                else if(!ui.item)
				{
                    $(this).val("");
                }
            },
			response: function (event, ui){
				var list = []
				
				for(x in ui.content)
				{
					list[ui.content[x].value.toLowerCase()] = ui.content[x].value
				}
				
				$(this).data("list", list)
			}
		})
		.focus(function(){
			if(this.value != "")
				get_consignor_notes(this.value, $("#extraInfoSet").get())
		})
		.blur(function(){
			if(this.value != "")
				get_consignor_notes(this.value, $("#extraInfoSet").get())
		})
		
	$("[name='setConsignor']").data("ui-autocomplete2")._renderItem = consignor_suggestion_renderer
	
	$("#editSetBox [name='consignor']")
		.autocomplete2({
			delay: 100,
			//source: "/includes/suggest2.php?name=consignor",
			source: function(request, response) {
				$.getJSON("checkonetoone.php?name=consignor2", {term: request.term, consignor: $("#consignor").val(), photoFolder: $("#photoFolder").val(), auctionCode: $("#auctionCode").val(), typeCode: $("#typeCode").val() },
							response);
			},
			focus: function(event, ui){
				get_consignor_notes(ui.item.ConsignorName, $("#extraInfoEditSet").get());
			},
			change: function (event, ui) {
				
				if($(this).data("list")[$(this).val().toLowerCase()])
				{
					if($(this).data("list")[$(this).val().toLowerCase()] != $(this).val())
						$(this).val($(this).data("list")[$(this).val().toLowerCase()])
				}
                else if(!ui.item)
				{
                    $(this).val("");
                }
            },
			response: function (event, ui){
				var list = []
				
				for(x in ui.content)
				{
					list[ui.content[x].value.toLowerCase()] = ui.content[x].value
				}
				
				$(this).data("list", list)
			}
		})
		.focus(function(){get_consignor_notes(this.value, $("#extraInfoEditSet").get())})
		.blur(function(){get_consignor_notes(this.value, $("#extraInfoEditSet").get())});
	
	$("#editSetBox [name='consignor']").data("ui-autocomplete2")._renderItem = consignor_suggestion_renderer
	
}

function consignor_suggestion_renderer(ul, data)
{
	li = $("<li />")
		.text(data.ConsignorName)
	
	if(data.SameAs && data.SameAs.indexOf("use") === 0)
		$(li).append("<span style='float:right; color:red; font-weight: bold'>(" + data.SameAs + ")</span>")
	else if(data.SameAs)
		$(li).append("<span style='float:right;'>(" + data.SameAs + ")</span>")
				
	return $(li).appendTo(ul)
}


function photography_folder_suggestion_renderer(ul, item)
{
	li = $("<li />")
		.text(item.label)
	
	if(item.auction_code && item.type_code)
	{
		$(li)
			.append("<span style='float:right; font-size: 80%;'>("+item.auction_code+" "+item.type_code+")</span>")
	}
	
	return $(li).appendTo(ul)
}

function film_title_suggestion_renderer(ul, item)
{
	if("see" in item)
	{
		return $("<li />")
			.css({
				paddingLeft: "20px",
				fontSize: "85%"
			})
			.attr("data-value", item.value )
			.append(item.see == -1 ? "&larr; " : "&rarr; ")
			.append(item.label)
			.appendTo(ul)
	}
	else
	{
		return $("<li />")
			.attr("data-value", item.value )
			.append(item.label)
			.appendTo(ul)
	}
}

function log_memo(){
  var film_title = $("[name=film_title]").val()
  
  if(film_title!=""){
    var win = window.open('/memos/?filter[0][field]=title&filter[0][comp]=%3D&filter[0][value]=' + encodeURIComponent(film_title), '_blank');
    if (win) {
        win.focus();
    } else {
        alert('Popups are disabled');
    }
  }else{
    alert("Film title must not be blank")
  }
}

function edit_memo()
{
	var memo = $(window.templates.memo)
	
	for(x in window.tabbers)
	{
		$(memo)
			.find("select")
			.append(
				$("<option />")
					.attr("value", x)
					.text(window.tabbers[x])
			)
	}
	
	if(window.memo)
	{
		$(memo)
			.find("[name=text]")
			.val(window.memo.text)
			
		$(memo)
			.find("[name=tabber] option[value="+window.memo.tabber+"]").prop("selected", true)
		
		$(memo)
			.find("[name=type][value="+window.memo.type+"]").prop("checked", true)
			
		$(memo)
			.dialog({
				title: "Memo for Tabber",
				modal: true,
				open: function(event, ui){
					$(this).find(":input:first").focus()
				},
				buttons: {
					Save: function() {
						var data = $(this).find(":input").createValueArray()
						
						window.memo = data
						
						$(this).dialog("close")
					},
					Delete: function(){
						delete window.memo
						$(this).dialog("close")
					},
				},
			})
	}
	else
	{
	
		$(memo)
			.dialog({
				title: "Memo for Tabber",
				modal: true,
				open: function(event, ui){
					$(this).find(":input:first").focus()
				},
				buttons: {
					Save: function() {
						var data = $(this).find(":input").createValueArray()
						
						window.memo = data
						
						$(this).dialog("close")
					},
				}
			})
	}
		
		
}
