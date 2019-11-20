/*
 * Advanced Search jQuery plugin
 * 
 * Aaron Kennedy, kennedy@postpro.net, 2015-07-27
 */

;(function( $ ) {
	
	/*
	 * jQuery.aSearch
	 */
	$.fn.aSearch = function( options ) {
		
		/*
		 * String was passed, so call one of the public methods.
		 */
		if(typeof options == "string")
		{
			switch(options)
			{
				case "add_row":
					$.fn.aSearch.add_row(this, $(this).prop("aSearch-settings"))
					break;
					
				case "remove_row":
					$.fn.aSearch.remove_row(this, $(this).prop("aSearch-settings"))
					break;
					
				case "get":
					return $.fn.aSearch.get_values(this)
					break;
			}
		}
		/*
		 * String was not passed, so add aSearch to this element.
		 */
		else
		{
			
			//Default settings
			var settings = $.extend({
				start_rows: 4, //Number of empty filter rows to start out with
			}, options )
			
			$(this)
				.prop("aSearch-settings", settings)
				.addClass("aSearch")
			
			for(var x = $(this).find(".aSearch-row").length; x < settings.start_rows; x++)
			{
				$.fn.aSearch.add_row(this, settings)
			}
			
			$.fn.aSearch.init_autocompletes(this, settings)
			$.fn.aSearch.init_events(this, settings)
		}
		
		return this
	}

	
	/*
	 * Add another filter
	*/
	$.fn.aSearch.add_row = function( table, settings ) {
		
		var index = $(table)
				.find("tr.aSearch-row")
				.length
		
		var data = {
			fields: settings.fields,
			index: index,
		}
		
		var row = $(Mustache.render(window.templates.filter_row, data))
		
		$(row)
			.find(".aSearch-field option:eq("+(index % settings.fields.length)+")")
			.prop("selected", true)
		
		$(table).append(row)
	}
	
	
	/*
	 * Remove bottom-most filter
	 */
	$.fn.aSearch.remove_row = function( table ) {
		$(table).find("tr.aSearch-row:last").remove()
	}
	
	
	/*
	 * Executed when user chooses a field from the drop-down
	 */
	$.fn.aSearch.change_field = function( event ) {
		$.fn.aSearch.init_autocomplete(
			$(this).parents(".aSearch-row"), 
			$(this).parents("table.aSearch").prop("aSearch-settings"))
	}
	
	
	/*
	 * Executed when user changes the comparator
	 */
	$.fn.aSearch.change_comp = function( event ) {
		console.debug(this)
		console.debug(event)
	}
	
	
	/*
	 * Returns an array of values
	 */
	$.fn.aSearch.get_values = function ( table ) {
		var data = []
		
		$(table)
			.find("tr.aSearch-row")
			.each(function(){
				if($(this).find(":input.aSearch-value").val().trim() == "")
					return true
				
				data.push({
					field: $(this).find(":input.aSearch-field").val(),
					comparator: $(this).find(":input.aSearch-comp").val(),
					value: $(this).find(":input.aSearch-value").val().trim()
				})
			})
			 
		return data
	}
	
	
	/*
	 * Sets up autocomplete for all fields.
	 */
	$.fn.aSearch.init_autocompletes = function ( table, settings ) {		
		if(!("autocompletes" in settings))
			return false
		
		$(table)
			.find("tr.aSearch-row")
			.each(function(){
				$.fn.aSearch.init_autocomplete(this, settings)
			})
	}
	
	
	/*
	 * Set up autocomplete for a single field
	 */
	$.fn.aSearch.init_autocomplete = function ( row, settings ) {
		var field = $(row).find(".aSearch-field").val()
		var input = $(row).find(".aSearch-value")
		
		if($(input).hasClass("ui-autocomplete-input"))
		{
			$(input).autocomplete("destroy")
		}
		
		if(settings.autocompletes[field])
		{
			settings.autocompletes[field](input)
		}
	}
	
	
	/*
	 * Sets up event handlers
	 */
	$.fn.aSearch.init_events = function ( table, settings ) {
		$(table)
			.find(".aSearch-field")
			.change($.fn.aSearch.change_field)
			
		$(table)
			.find(".aSearch-comp")
			.change($.fn.aSearch.change_comp)
	}
	
}( jQuery ));

