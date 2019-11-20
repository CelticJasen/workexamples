<?PHP
/*
File: checky.inc.php

This is shared by "Check Auction Tables" and "Create Auction Tables".
Its purpose is to sanity-check a dated auction table, and allow
the user to perform corrections before it is made a live auction.

It checks for the following:
- ebay_category == 0
- presence of bulk lot images
- certain fields that must be blank
- swear words in various fields (in case of typo)
- linebreaks in certain fields
- image2 field validity
- valid image names
- potential reproductions
- proper reissues
- unbroken strings longer than 20 characters
- valid type_code
- valid condition_overall
- proper double-sided/single-sided
- blank consignors
- proper 3D info
*/

require_once("../descriptions/assemble_style_info.inc.php");
require_once("/webroot/includes/archive_images.inc.php");

$replacehighlighted = '<span style="background-color:tomato">\\1</span>';

function create_review_form_link($table, $autonumber)
{
	/*
	 * Function: create_review_form_link
	 * 
	 * Give it the auction table and the autonumber of the record,
	 * and it will return a clickable link which leads to the
	 * review form for that record.
	 */
	return "<a style='text-decoration: underline' href='/listing/index.php?litemode=true".
		"&table=".urlencode($table)."&autonumber=$autonumber' target='_blank'>".
		"#$autonumber</a>";
}

function update_record_textarea($table, $id, $field, $value, $style = "")
{
	/*
	 * Function: update_record_textarea
	 * 
	 * Creates a textarea used to change a field of a record in the 
	 * listing_system MySQL database. The textbox turns green upon
	 * successful update. This depends on having the "update_record()"
	 * javascript function present.
	 */
	if($style == "")
	{	
		switch($field)
		{
			case "film_MrLister":
				$style = "width: 275px";
				break;
			
			case "title_45":
			case "title_long":
				$style = "width: 350px";
				break;
			
			case "type_long":
				$style = "width: 350px";
				break;
			
			case "type_short":
			case "type_pre_short":
				$style = "width: 110px";
				break;
		}
	}
	
	return sprintf("<textarea style='$style' onblur='update_record(%s, %s, %s, this)'>%s</textarea>", 
		json_encode($table), json_encode($id), json_encode($field), htmlspecialchars($value));
}

function table_header($columns)
{
	/*
	 * Function: table_header
	 * 
	 * Returns a <tr><th> row with your array of 
	 * columns. Values are not htmlspecialchars escaped!
	 */
	$output = "<tr>";
	foreach($columns as $c)
	{
		$output .= "<th>$c</th>";
	}
	
	return $output."</tr>\n";
}

function table_row($columns)
{
	/*
	 * Function: table_row
	 * 
	 * Returns a <tr><td> row with your array of 
	 * columns. Values are not htmlspecialchars escaped!
	 */
	$output = "<tr>";
	foreach($columns as $c)
	{
		$output .= "<td>$c</td>";
	}
	
	return $output."</tr>\n";
}

function output_images($image1, $image2, $sortfield)
{
	$output = "";
	list($images, $thumb) = find_images_for_dated_table_item($image1, $sortfield);
	
	if(!empty($thumb))
		$output .= "<a target='_blank' href='http://poster-server/".$images['']."'><img style='margin: 5px' src='http://poster-server/$thumb' /></a>";
	else
		return "<small>Thumbnail(s) not available.</small>";
	
	foreach(array_filter(array_map("trim", explode(";", $image2)), "strlen") as $image)
	{
		list($images, $thumb) = find_images_for_dated_table_item($image, $sortfield);
		
		if(!empty($thumb))
			$output .= "<a target='_blank' href='http://poster-server/".$images['']."'><img style='margin: 5px' src='http://poster-server/$thumb' /></a>";
		else
			return "<small>Thumbnail(s) not available.</small>";
	}
	
	return $output;
}

function find_images_for_dated_table_item($image, $sortfield)
{
	# Look for an image to display for this record, 
	# using the smallest available for the thumbnail.
	$images = search_for_image($image, $sortfield);
	
	unset($thumb);

	foreach(Array("100", "200", "550") as $size)
	{
		if(isset($images[$size]))
		{
			$thumb = $images[$size];
			break;
		}
	}
	
	if(empty($thumb))
		$thumb = false;
	
	return array($images, $thumb);
}

function checky($extra, $interactive = true, $after_the_fact = false)
{
	global $fields_to_check, $blank_fields;
	global $replacehighlighted;
	
	ob_implicit_flush();
	
	?>
	<script>
	$(document).ready(function(){
		$(".readmore").readmore({
			moreLink: "<a href='#' style='padding: 20px 20px 20px 100px; font-size: 18pt'>&darr; Read More &darr;</a>",
			lessLink: "<a href='#' style=''>Read Less</a>",
		})
	})
	</script>
	<?
	
	if(!isset($fields_to_check))
	{
		$fields_to_check = Array('lot_number', 'film_title', 'film_MrLister', 'image1', 'consignor', 'type_pre_short', 
			'type_code', 'type_short', 'type_long', 'condition_overall', 'condition_major_defects',
			'condition_common_defects', 'after_description', 'after_description_bulk', 'title_long',
			'title_45', 'style_info', 'how_rolled', 'template_date', 'desire_items', 'ebay_item_number',
			'ebay_price', 'ebay_id', 'ebay_email', 'artist', 'date_added', 'sections_code', 
			'eBay Description');
	}
	
	if($after_the_fact)
		$blank_fields = Array();
	elseif(!isset($blank_fields))
		$blank_fields = Array('after_description_bulk', 'ebay_item_number', 'ebay_price', 'ebay_id', 'ebay_email', 'sections_code');
	
	$cusswords = unserialize(file_get_contents("/webroot/includes/cusswords.php.obj"));
	$cusswords[] = "fo";
	$cusswords[] = "teh";
	
	$errors = $successes = Array();
	$table = mysql_real_escape_string($_REQUEST['table']);
	
	//Total number of records
	if($extra)
	{
		$r = q("SELECT count(*) FROM `$table`");
		$totalCount = mysql_result($r, 0, 0);
		echo "<h4>$_REQUEST[table]</h4>";
		echo "Total Records: $totalCount<br />";
	}
	
	echo "<ul>";
	if(!$after_the_fact)
	{
		//Check to make sure ebay_category is all '0'
		$r = q("SELECT id, ebay_category FROM `$table` WHERE coalesce(ebay_category, '') != 0");
		$num = mysql_num_rows($r);
		if($num && $extra)
				echo "<li><span class='green'>$num record(s) have an ebay_category different from 0</span><a target=\"_blank\" href=\"http://poster-server/wiki/index.php/Check_Auction_Tables#Check_to_make_sure_ebay_category_is_all_.270.27\"> ?wiki</a></li>";
		elseif($extra)
			echo "<li><span class='red'>Checked ebay_category = 0</span><a target=\"_blank\" href=\"http://poster-server/wiki/index.php/Check_Auction_Tables#Check_to_make_sure_ebay_category_is_all_.270.27\"> ?wiki</a></li>";
	}		
	
	
	
	
	
	/*
		For bulk lots, check to make sure an image has been chosen
	*/
	if(stristr(stripslashes($table), "sunday"))
	{
		$r = q("SELECT selected_image, imagesLocation, film_title, autonumber, `$table`.id FROM `$table` LEFT JOIN bulk_lots ON bulk_lot_id = bulk_lots.id WHERE COALESCE(bulk_lot_id, '') != ''");
		$numrows = mysql_num_rows($r);
		if($numrows)
		{
			$error = false;
			while($row = mysql_fetch_assoc($r))
			{
				if(!preg_match("/^[a-zA-Z0-9_-]+\.jpg$/", $row['selected_image']))
				{
					$error = true;
					echo "<li><span class='red'>Bulk lot image not selected for ".
						"<a style='text-decoration: underline' ".
						"href='http://poster-server/listing/index.php?table=".urlencode($table).
						"&autonumber=$row[autonumber]' target='_blank'>id #$row[id]</a> ".
						"or it does not match a 'image_name.jpg' format. ($row[selected_image])</span></li>";
				}
				else if(!file_exists("/images/Images in process/Uploading AHEAD (bulklots)/$row[imagesLocation]/$row[selected_image]"))
				{
					$error = true;
					echo "<li><span class='red'>Image for <a style='text-decoration: underline' href='http://poster-server/listing/index.php?table=".urlencode($table)."&autonumber=$row[autonumber]' target='_blank'>id #$row[id]</a> does not exist in /images/Images in process/Uploading AHEAD (bulklots)/$row[imagesLocation]/</span><a target=\"_blank\" href=\"http://poster-server/wiki/index.php/Check_Auction_Tables#Check_to_make_sure_an_image_has_been_chosen_for_bulk_lots\"> ?wiki</a></li>";
				}
			}
			
			if(!$error)
				echo "<li><span class='green'>Checked images for $numrows bulk lots</span><a target=\"_blank\" href=\"http://poster-server/wiki/index.php/Check_Auction_Tables#Check_to_make_sure_an_image_has_been_chosen_for_bulk_lots\"> ?wiki</a></li>";
		}
	}	
	
	
	
	
	
	/*
		Check to make sure titles have descriptions
	*/
	if(stristr(stripslashes($table), "sunday"))
	{
		$r = q("SELECT id, film_title, autonumber FROM `$table` ".
			"LEFT JOIN descriptions ON film_title = TITLE ".
			"WHERE COALESCE(`eBay Description`, '') = '' and bulk_lot_id is null");
	}
	else
	{
		$r = q("SELECT id, film_title, autonumber FROM `$table` ".
			"LEFT JOIN descriptions ON film_title = TITLE ".
			"WHERE COALESCE(`eBay Description`, '') = '' and bulk_lot_id is null");
	}
		
	if($num = mysql_num_rows($r))
	{
		echo "<li><span class='red'>$num record(s) have a blank or nonexistant film description</span><ul>";
		foreach(mysql_fetch_all($r) as $row)
			echo "<li><a style='text-decoration: underline' href='http://poster-server/listing/index.php?table=".urlencode($table)."&autonumber=$row[2]' target='_blank'>id #$row[0]</a>; '$row[1]'</li>";
		echo "</ul><a target=\"_blank\" href=\"http://poster-server/wiki/index.php/Check_Auction_Tables#Check_to_make_sure_titles_have_descriptions\"> ?wiki</a></li>";
	}
	elseif($extra)
		echo "<li><span class='green'>Checked for blank film descriptions</span><a target=\"_blank\" href=\"http://poster-server/wiki/index.php/Check_Auction_Tables#Check_to_make_sure_titles_have_descriptions\"> ?wiki</a></li>";
	
	
	
	
	
	/*
		Look for cuss words
	*/
	$t = microtime(true);
	
	$cuss_words_found = false;
	$cussword_fields = Array("film_MrLister", "condition_major_defects", 
		"condition_common_defects", "after_description", "after_description_bulk", 
		"title_long", "title_45", "style_info", "artist");
	$cussword_fields = Array("film_MrLister", "title_long", "title_45");
	$cusswords_exclude = Array("hell", "mick", "dyke", "lesbian", "gay", "snatch", "damn", "gringo");
	foreach($cusswords_exclude as $w)
	{
		$key = array_search($w, $cusswords);
		if($key !== false)
			unset($cusswords[$key]);
	}
	
	$cusswords_regex = implode("|", array_map("preg_quote", $cusswords));
	
	# Names that should not be matched as cuss words
	$excluded_names = Array("Dick Tracy", "Dick Van Dyke", "Dick Kleiner", "DICK TRACY");
	
	
	$query = "select autonumber, ";
	$where_part = "";
	foreach($cussword_fields as $field)
	{
		$field = mysql_real_escape_string($field);
		# Remove certain names from the matched field, like 
		# Dick Van Dyke, so that name is not matched as 
		# a cussword.
		$field_with_replacements = "`$field`";
		foreach($excluded_names as $n)
			$field_with_replacements = "replace($field_with_replacements, '$n', '')";
		$where_part .= "$field_with_replacements regexp '(^| )($cusswords_regex)($| )' or ";
		
		$query .= "`$field`, ";
	}
	$query = substr($query, 0, -2);
	$where_part = substr($where_part, 0, -4);
	$query .= " from `$table` where $where_part";
	
	$r2 = mysql_query2($query);
	ob_start();
	while($row = mysql_fetch_assoc($r2))
	{
		foreach($cussword_fields as $field)
		{
			$field_value = $row[$field];
			//$words = array_map("trim", explode(" ", $field_value));
			$bad_words_found = Array();
			
			foreach($cusswords as $c)
			{
				if(preg_match("/(^| )".preg_quote($c)."($| )/", $field_value))
					$bad_words_found[] = $c;
			}
			/*
			foreach($words as $w)
			{
				$id = array_search(strtolower($w), $cusswords);
				if($id !== false)
					$bad_words_found[] = $cusswords[$id];
			}
			*/
			
			$bad_words_found = array_unique($bad_words_found);
			
			if(count($bad_words_found) > 0)
			{
				if($cuss_words_found === false)
				{
					$cuss_words_found = true;
					echo "<li><span class='red'>Found bad word(s)</span><a target=\"_blank\" href=\"http://poster-server/wiki/index.php/Check_Auction_Tables#Check_for_cuss_words\"> ?wiki</a><ul>";
				}
				
				$highlighted = preg_replace(array_map(function($value){return "/(" .$value . ")/";},$bad_words_found), $replacehighlighted, $field_value);
				
				echo "<li><b>".implode("</b>, <b>", $bad_words_found)."</b> in \"" . $highlighted . "\" ($field). ".
					"<a style='text-decoration: underline' ".
					"href='http://poster-server/listing/index.php?".
					"table=".urlencode($table)."&autonumber=".
					"($row[autonumber]) ' target='_blank'>Edit</a></li>";
			}
		}
	}
	if($cuss_words_found)
		ob_flush();
	else
		ob_end_clean();
		
	ob_implicit_flush();
		
	unset($r2);
	
	$t = round(microtime(true) - $t, 2);
	
	if($cuss_words_found === false)
		echo "<li><span class='green'>Looked for bad words</span><a target=\"_blank\" href=\"http://poster-server/wiki/index.php/Check_Auction_Tables#Check_for_cuss_words\"> ?wiki</a></li>"; // (took $t seconds) <- took that out since it looks like it's not needed
	else
		echo "<li>It took $t seconds to check for cuss words</li></ul></li>";
	
	
	
	
	
	/*
		Look for "see" descriptions
		" AND CONCAT('%', film_title, '%') NOT LIKE `eBay Description`"
	*/
	$r = q("SELECT id, film_title, `eBay Description`, autonumber FROM `$table` LEFT JOIN descriptions ON film_title = TITLE WHERE `eBay Description` REGEXP '^[ ]*see[ :]'");
	if($num = mysql_num_rows($r))
	{
		echo "<li><span class='red'>$num record(s) appear to be redirects ('see' titles)</span><a target=\"_blank\" href=\"http://poster-server/wiki/index.php/Check_Auction_Tables#Check_for_.22see.22_descriptions\"> ?wiki</a><ul>";
		foreach(mysql_fetch_all($r) as $row)
			echo "<li><a style='text-decoration: underline' href='http://poster-server/listing/index.php?table=".urlencode($table)."&autonumber=$row[3]' target='_blank'>id #$row[0]</a>, '$row[1]', '$row[2]'</li>";
		echo "</ul></li>";
	}
	elseif($verbose)
		echo "<li><span class='green'>Checked for 'see' titles</span><a target=\"_blank\" href=\"http://poster-server/wiki/index.php/Check_Auction_Tables#Check_for_.22see.22_descriptions\"> ?wiki</a></li>";
	
	
	
	
	
	/*
		Look for linebreaks
	*/
	foreach($fields_to_check as $field)
	{
		if($field == "artist")
		{
			$r = q("SELECT id, artist, autonumber FROM `$table` ".
				"WHERE artist LIKE '%\\n%' or artist like '%\\r%'");
		}
		else
		{
			$r = q("SELECT id, `$field`, autonumber FROM `$table` LEFT JOIN descriptions ON film_title = TITLE ".
				"WHERE `$field` LIKE '%\\n%' or `$field` like '%\\r%'");
		}
		
		if($num = mysql_num_rows($r))
		{
			echo "<li><span class='red'>$num record(s) had a `$field` with a linebreak</span><a target=\"_blank\" href=\"http://poster-server/wiki/index.php/Check_Auction_Tables#Check_for_linebreaks\"> ?wiki</a><ul class='readmore'>";
			
			foreach(mysql_fetch_all($r) as $row)
			{
				$content = str_replace("\n", "<br /><b>-----LINEBREAK-----</b>", $row[1]);
				echo "<li><a style='text-decoration: underline' href='http://poster-server/listing/index.php?table=".urlencode($table).
					"&autonumber=$row[2]' target='_blank'>id #$row[0]</a>, '$content'</li>";
			}
			
			echo "</ul></li>";
		}
		
		if($field == "artist")
		{
			$r = mysql_query2("select id, `$field`, autonumber from `$table` ".
				"where `$field` like '%**%' or `$field` regexp '[^A-Za-z0-9_][x]{2,}'");
		}
		else
		{
			$r = mysql_query2("select id, `$field`, autonumber from `$table` ".
				"left join descriptions on film_title = TITLE ".
				"where `$field` like '%**%' or `$field` regexp '[^A-Za-z0-9_][x]{2,}'");
		}
		
		if($num = mysql_num_rows($r))
		{
			echo "<li><span class='red'>$num record(s) had a `$field` containing 'xx' or '**'.</span><a target=\"_blank\" href=\"http://poster-server/wiki/index.php/Check_Auction_Tables#Check_for_linebreaks\"> ?wiki</a><ul>";
			while(list($id, $content, $autonumber) = mysql_fetch_row($r))
			{
				$checkarray = '/(\*\*|xx|XX)/';
				$highlighted = preg_replace($checkarray, $replacehighlighted, $content);
				
				echo "<li><a style='text-decoration: underline' href='http://poster-server/listing/index.php?table=".urlencode($table).
					"&autonumber=$autonumber' target='_blank'>id #$id</a>, \"" . $highlighted . "\"</li>";
			}
			
			echo "</ul></li>\n";
		}
		
		if($field == "artist")
			$r = mysql_query2("select id, `$field`, autonumber from `$table` where `$field` like '%\\t%'");
		else
			$r = mysql_query2("select id, `$field`, autonumber from `$table` left join descriptions on film_title = TITLE where `$field` like '%\\t%'");
		
		if($num = mysql_num_rows($r))
		{
			echo "<li><span class='red'>$num record(s) had a `$field` containing the tab character.</span><a target=\"_blank\" href=\"http://poster-server/wiki/index.php/Check_Auction_Tables#Check_for_linebreaks\"> ?wiki</a><ul>";
			while(list($id, $content, $autonumber) = mysql_fetch_row($r))
			{
				echo "<li><a style='text-decoration: underline' href='http://poster-server/listing/index.php?table=".urlencode($table).
					"&autonumber=$autonumber' target='_blank'>id #$id</a>, '".str_replace("\t", "<b>(TAB)</b>", $content)."'</li>";
			}
			
			echo "</ul></li>\n";
		}
	}
	
	if($extra)
		echo "<li><span class='green'>Checked for linebreaks, 'xx','**', and tab in various fields</span><a target=\"_blank\" href=\"http://poster-server/wiki/index.php/Check_Auction_Tables#Check_for_linebreaks\"> ?wiki</a></li>\n";
	
	
	
	
	
	/*
		Look for double quotes because they break the CSV
	*/
	$r = mysql_query3("select id, autonumber, title_45 from `$table` where title_45 like '%\"%'");
	while(list($id, $autonumber, $title_45) = mysql_fetch_row($r))
	{
		echo "<li><span class='red'>".create_review_form_link($table, $autonumber)." has double quotes in title.</span><a target=\"_blank\" href=\"http://poster-server/wiki/index.php/Check_Auction_Tables#Check_for_double_quotes_because_they_break_the_CSV\"> ?wiki</a> ";
		
		if($interactive)
		{
			echo "<br /><input type='text' style='width: 700px;' onblur='update_record(\"$table\", \"$id\", \"title_45\", this)' ".
				"value=\"".htmlspecialchars($title_45)."\" />";
		}
		
		echo "</li>";
	}
	
	
	
	
	
	/*
		Fields required to be blank
	*/
	if($extra)
	{
		foreach($blank_fields as $field)
		{
			$r = q("SELECT id, `$field`, autonumber FROM `$table` WHERE COALESCE(`$field`, '') != '' and COALESCE(`$field`, '') NOT LIKE '%What IS %'");
			if($num = mysql_num_rows($r))
			{
				echo "<li><span class='red'>$num record(s) did not have a blank `$field`</span><a target=\"_blank\" href=\"http://poster-server/wiki/index.php/Check_Auction_Tables#Fields_required_to_be_blank_check\"> ?wiki</a><ul class='readmore'>";
				
				foreach(mysql_fetch_all($r) as $row)
				{
					if($interactive)
					{
						echo "<li>".create_review_form_link($table, $row[2]).
							"<br /><textarea style='width: 500px; height: 60px;' ".
							"onblur='update_record(\"$table\", \"$row[0]\", ".
							"\"$field\", this)'>".
							htmlentities($row[1], ENT_NOQUOTES)."</textarea></li>";
						
					}
					else
						echo "<li>id #$row[0], '$row[1]'</li>";
				}
				
				echo "</ul></li>";
			}
			elseif($extra)
				echo "<li><span class='green'>all blank: `$field`</span><a target=\"_blank\" href=\"http://poster-server/wiki/index.php/Check_Auction_Tables#Fields_required_to_be_blank_check\"> ?wiki</a></li>";
		}
	}
	
	
	
	
	
	/*
		Check style info field integrity
	*/
	ob_start();
	$quantity_check = "";
	$r = mysql_query3("select * from `$table`");
	while($auctionData = mysql_fetch_assoc($r))
	{
		if($auctionData['style_id'] == "" || $auctionData['date_added'] < "2011-01-01 00:00:00")
			continue;
		
		$r2 = mysql_query3(sprintf("select * from AHEAD_Style_Info where id = '%s'", $auctionData['style_id']));
		
		if(mysql_num_rows($r2) == 0)
			continue;
		
		$styleInfoData = mysql_fetch_assoc($r2);
		
		$style_info = assemble_style_info($auctionData, $styleInfoData);
		
		if($style_info != $auctionData['style_info'])
		{
			printf("<li style='%s'>%s: <span style='min-width: 200px; display: inline-block;'>&ldquo;%s&rdquo;</span> &rarr; ".
				"<span style='min-width: 200px; display: inline-block;'>&ldquo;%s&rdquo;</span>",
				((preg_match("/R[0-9]{2}/", $style_info.$auctionData['style_info'])) ? "background: yellow" : ""), 
				create_review_form_link($table, $auctionData['autonumber']),
				$style_info, $auctionData['style_info']);			
			
			printf("<small style='margin-left: 50px'>%s</small>, <small>%s</small>\n",
				htmlspecialchars($auctionData['type_long']), htmlspecialchars($auctionData['type_pre_short']));
			
			echo "</li>\n";
		}
		elseif($styleInfoData['quantity'] > 1)
		{
			// JJ 20190621 added strpos checks for 'roup' to make it so "Group/group of" quantity check errors don't appear anymore.
			if(!preg_match("/^((a )?(complete )?(set|lot) of )?$styleInfoData[quantity]/i", $auctionData['type_pre_short']) && strpos($auctionData['type_pre_short'], 'roup') === false)
			{
				$quantity_check .= sprintf("<li>%s; quant=%s; type_pre_short=&ldquo;%s&rdquo;'</li>\n", 
					create_review_form_link($table, $auctionData['autonumber']), 
					$styleInfoData['quantity'], $auctionData['type_pre_short']);
			}
			elseif(!preg_match("/^((a )?(complete )?(set|lot) of )?$styleInfoData[quantity] /i", $auctionData['type_long']) && strpos($auctionData['type_long'], 'roup') === false)
			{
				$quantity_check .= sprintf("<li>%s; quant=%s; type_long=&ldquo;%s&rdquo;</li>\n", 
					create_review_form_link($table, $auctionData['autonumber']),
					$styleInfoData['quantity'], $auctionData['type_long']);
			}
		}
		
		if(stristr($auctionData['image1'], "set_of") !== false)
		{
			if(!preg_match("/^((a )?(complete )?(set|lot) of )?[0-9]/i", $auctionData['type_pre_short']) && strpos($auctionData['type_pre_short'], 'roup') === false)
			{
				$quantity_check .= sprintf("<li>%s; image1=&ldquo;%s&ldquo;; type_pre_short=&ldquo;%s&rdquo;'</li>\n", 
					create_review_form_link($table, $auctionData['autonumber']), 
					$auctionData['image1'], $auctionData['type_pre_short']);
			}
			elseif(!preg_match("/^((a )?(complete )?(set|lot) of )?[0-9]+ /i", $auctionData['type_long']) && strpos($auctionData['type_long'], 'roup') === false)
			{
				$quantity_check .= sprintf("<li>%s; image1=&ldquo;%s&ldquo; type_long=&ldquo;%s&rdquo;</li>\n", 
					create_review_form_link($table, $auctionData['autonumber']),
					$auctionData['image1'], $auctionData['type_long']);
			}
		}
	}
	$contents = ob_get_clean();	
	if($contents == "")
		echo "<li><span class='green'>Checked style info integrity</span><a target=\"_blank\" href=\"http://poster-server/wiki/index.php/Check_Auction_Tables#Check_style_info_field_integrity\"> ?wiki</a></li>\n";
	else
	{
		echo "<li><span class='orange'>Style info; field & table disagree. High probability of mistakes everywhere.</span><a target=\"_blank\" href=\"http://poster-server/wiki/index.php/Check_Auction_Tables#Check_style_info_field_integrity\"> ?wiki</a>\n<ul class='readmore'>";
		echo $contents;
		echo "</ul></li>\n";
	}
	
	if($quantity_check != "")
	{
		echo "<li><span class='red'>Quantity check fails</span><a target=\"_blank\" href=\"http://poster-server/wiki/index.php/Check_Auction_Tables#Check_style_info_field_integrity\"> ?wiki</a>\n<ul>";
		echo $quantity_check;
		echo "</ul></li>\n";
	}
	else
	{
		echo "<li><span class='green'>Quantity check passes</span><a target=\"_blank\" href=\"http://poster-server/wiki/index.php/Check_Auction_Tables#Check_style_info_field_integrity\"> ?wiki</a></li>\n";
	}
	
	
	
	
	
	/*
		Check image names/files
	*/
	$r = q("SELECT id, image2, autonumber FROM `$table` WHERE COALESCE(image2, '') != ''");
	if($num = mysql_num_rows($r))
	{
		foreach(mysql_fetch_all($r) as $row)
		{
			$bad = Array();
			$extraImages = array_map("trim", explode(";", $row[1]));
			foreach($extraImages as $img)
			{
				if(!preg_match("/^[a-zA-Z0-9_-]+\.jpg\$/i", $img))
					$bad[] = $img;
			}
			if(count($bad))
				$badExtraImages[] = Array($row[0], $row[1], implode(",",$bad), $row[2]);
		}
		
		if($numBad = count($badExtraImages))
		{
			echo "<li><span class='red'>$numBad record(s) have invalid extra images</span><a target=\"_blank\" href=\"http://poster-server/wiki/index.php/Check_Auction_Tables#Check_image_names.2Ffiles\"> ?wiki</a><ul>";
			foreach($badExtraImages as $b)
				echo "<li><a style='text-decoration: underline' href='http://poster-server/listing/index.php?table=".urlencode($table)."&autonumber=$b[3]' target='_blank'>id #$b[0]</a>, '$b[1]' ($b[2])</li>";
			echo "</ul></li>";
		}
		else
			echo "<li><span class='green'>Checked image2 field</span><a target=\"_blank\" href=\"http://poster-server/wiki/index.php/Check_Auction_Tables#Check_image_names.2Ffiles\"> ?wiki</a></li>";	
	}
	elseif($extra)
		echo "<li><span class='green'>No image2 fields were used</span><a target=\"_blank\" href=\"http://poster-server/wiki/index.php/Check_Auction_Tables#Check_image_names.2Ffiles\"> ?wiki</a></li>";
	
	$r = q("SELECT id, image1, autonumber FROM `$table` WHERE COALESCE(image1, '') NOT REGEXP '^[a-z0-9_]+\.jpg$'");
	if($num = mysql_num_rows($r))
	{
		echo "<li><span class='red'>$num record(s) have something wrong with the image name</span><a target=\"_blank\" href=\"http://poster-server/wiki/index.php/Check_Auction_Tables#Check_image_names.2Ffiles\"> ?wiki</a><ul>";
		foreach(mysql_fetch_all($r) as $row)
		{
			if($row[1] === "")
				$reason = "Value is blank";
			elseif(substr($row[1], -4) !== ".jpg")
				$reason = "Value does not end in '.jpg'";
			else
			{
				$match = preg_match("/[^a-zA-Z0-9_-]/", $row[1], $matches, PREG_OFFSET_CAPTURE);
				if($match !== false)
					$reason = "Character '".$matches[0][0]."' at position ".$matches[0][1]." is not allowed.";
				else
					$reason = "Unknown reason";
			}
			
			$val = htmlspecialchars($row[1]);
			echo "<li><a style='text-decoration: underline' href='http://poster-server/listing/index.php?table=".urlencode($table)."&autonumber=$row[2]' target='_blank'>id #$row[0]</a> ";
			
			if($interactive)
				echo "<input type='text' style='width: 350px;' onblur='update_record(\"$table\", \"$row[0]\", \"image1\", this)' value=\"$val\" />";
			
			echo " Reason: ($reason)</li>";
		}
		echo "</ul></li>";
	}
	elseif($extra)
		echo "<li><span class='green'>Checked for valid image names</span><a target=\"_blank\" href=\"http://poster-server/wiki/index.php/Check_Auction_Tables#Check_image_names.2Ffiles\"> ?wiki</a></li>\n";
	
	
	
	
	
	/*
		Quantity check
	*/
	try
	{
		//throw new Exception("This check not implemented yet - Aaron.", 10000);
		?>
<script type='text/javascript'>
	function set_all_quantity_to_one()
	{
		$("input.quantity").each(function(){
			element = $(this).val("1").get(0)
			element.onblur()
		})
	}
</script>
<?
		
		$r = mysql_query3("select quantity, id, autonumber, image1, image2, template_date, type_long, style_info from `$table` where quantity is null or quantity = '0'");
		
		if(mysql_num_rows($r))
		{
			echo "<li><span class='red'>Quantity field not filled in.</span><a target=\"_blank\" href=\"http://poster-server/wiki/index.php/Check_Auction_Tables#Quantity_check\"> ?wiki</a> <input type='button' onclick='set_all_quantity_to_one()' value='Set All = 1' />\n".
				"<table style='font-size: 90%; border-collapse: collapse' cellpadding='10' border='1'>".
				"<tr><th>Link</th><th>Long</th><th>Style</th><th>Image</th><th>Qua</th></tr>\n";
			
			while($row = mysql_fetch_assoc($r))
			{
				
				if($interactive)
				{
					$quantity_field = sprintf("<input type='text' tabIndex='1' class='quantity' style='width: 30px' ".
						"onblur='update_record(\"%s\", \"%s\", \"quantity\", this)' value=\"%s\" />", 
							htmlspecialchars($table), $row['id'], $row['quantity']);
				}
				else
				{
					$quantity_field = '"'.$row['quantity'].'"';
				}
				
				printf("<tr><td>%s</td><td>%s</td><td>%s</td><td>".
					"<div style='max-width: 600px; max-height: 200px; overflow: auto'>%s</div></td><td>%s</td></tr>\n", 
					create_review_form_link($table, $row['autonumber']), 
					"<div style='max-width: 400px'>".$row['type_long']."</div>",
					"<div style='max-width: 300'>".$row['style_info']."</div>",
					output_images($row['image1'], $row['image2'], $row['template_date']),
					$quantity_field, "");
			}
			
			echo "</table></li>\n";
		}
		else
			echo "<li><span class='green'>Checked: quantity field filled in</span><a target=\"_blank\" href=\"http://poster-server/wiki/index.php/Check_Auction_Tables#Quantity_check\"> ?wiki</a></li>\n";
		
	}
	catch(Exception $e)
	{
		echo "<pre>".htmlspecialchars($e->__toString())."</pre>";
	}
	
	
	
	
	
	/*
		Look for potential reproductions
	*/
	//Removed because Phil said there is no need for it. 2017-06-30 AK
	/*
	try
	{
		$r = @mysql_query3("select r.film_title, r.type_code, r.comment, r.link, t.autonumber, t.title_long, t.image1, t.template_date ".
			"from listing_system.repro_notes as r JOIN `$table` as t using(film_title) ".
			" WHERE t.type_code like r.type_code");

		if(mysql_num_rows($r) > 0)
		{
			require_once("/webroot/includes/archive_images.inc.php");
			echo "<li><span class='orange'>Found potential reproductions</span> <small>Thumbnails only available after images are scaled</small>\n".
				"<table style='font-size: 90%' cellpadding='10' border='1' class='readmore'>\n";
			while(list($film_title, $r_type_code, $comment, $link, $autonumber, $title_long, $image, $sortfield) = mysql_fetch_row($r))
			{
				list($images, $thumb) = find_images_for_dated_table_item($image, $sortfield);
				
				echo "<tr><td>$title_long</td><td>".nl2br($comment)."</td><td style='text-align: center'>".
					"<a target='_blank' href='http://poster-server/".$images[""]."'>".
					((empty($thumb)) ? "Thumbnail not available" : "<img style='width: 100px' src='http://poster-server/$thumb' />").
					"</a><br />".
					(($link != "") ? "<a target='_blank' href='$link'>Info</a><br />" : "")."<a style='text-decoration: underline' ".
					"href='http://poster-server/listing/index.php?table=".urlencode($table)."&autonumber=$autonumber' target='_blank'>Record</a> | ".
					"<a href=\"mailto:matt@emovieposter.com?subject=".htmlspecialchars($title_long)."\" style='text-decoration: underline'>Email</a>".
					"</td>";
			}
			echo "</table>\n";
		}
		elseif($extra)
			echo "<li><span class='green'>Looked for potential reproductions</span></li>\n";
	}
	catch(Exception $e)
	{
		echo $e->getMessage();
	}
	*/
	
	
	
	
	
	/*
		Search for the word "num"
	*/
	try
	{
		$fields_to_check_for_num = array("type_pre_short", "type_short", "film_MrLister", "title_45", "title_long");
		
		$r = mysql_query3("select autonumber, id, ".implode(",", $fields_to_check_for_num).", type_code, film_title from `$table` ".
			"where type_pre_short regexp '(^| )num(\$| )' or type_short regexp '(^| )num(\$| )' or ".
			"film_MrLister regexp '(^| )num(\$| )' or title_45 regexp '(^| )num(\$| )' or title_45 regexp '(^| )num(\$| |[0-9])'");
		
		if(mysql_num_rows($r) > 0)
		{
			echo "<li><span class='red'>Found \"num\"</span><a target=\"_blank\" href=\"http://poster-server/wiki/index.php/Check_Auction_Tables#Search_for_the_word_.22num.22\"> ?wiki</a>\n".
				"<table style='font-size: 90%' cellpadding='10' border='1'>\n";
			
			echo table_header(array_merge(array("link"), $fields_to_check_for_num));
			
			while($row = mysql_fetch_assoc($r))
			{
				$table_rows = array(create_review_form_link($table, $row['autonumber']));
				$id = $row['id'];
				
				unset($row['autonumber'], $row['id']);
				
				foreach($row as $field => $value)
				{
					$table_rows[] = update_record_textarea($table, $id, $field, $value);
				}
				
				echo table_row($table_rows);
			}
			
			echo "</table></li>";
		}
		elseif($extra)
			echo "<li><span class='green'>Looked for \"num\" in ".implode(", ", $fields_to_check_for_num)."</span><a target=\"_blank\" href=\"http://poster-server/wiki/index.php/Check_Auction_Tables#Search_for_the_word_.22num.22\"> ?wiki</a></li>\n";
	}
	catch(Exception $e)
	{
		echo $e->getMessage();
	}
	
	
	
	
	
	/*
		Mismatching reissue
	*/
	// added " title_long, title_45," to the line below and fixed it all up so it shows those in the table 2019 June 19 JJ 
	$r = q("SELECT id, film_title, film_MrLister, t1.type_long, style_info, autonumber, substring(`Year/NSS`, 1, 4), type_code, `Year/NSS`, title_long, title_45 ".
		"FROM `$table` as t1 ".
		"left join descriptions on film_title = TITLE ".
		"left join tbl_types on type_code = code ".
		"WHERE ".
		"(COALESCE(film_MrLister, '') REGEXP '^R[0-9\?]{2}' AND (COALESCE(style_info, '') NOT REGEXP 'R[0-9\?]{2}' OR COALESCE(t1.type_long, '') NOT LIKE '%Re-Release%')) or ".
		"(COALESCE(t1.type_long, '') LIKE '%Re-Release%' AND (COALESCE(film_MrLister, '') NOT REGEXP '^R[0-9\?]{2}' OR COALESCE(style_info, '') NOT REGEXP 'R[0-9\?]{2}')) or ".
		"(COALESCE(style_info, '') REGEXP 'R[0-9\?]{2}' AND (COALESCE(film_MrLister, '') NOT REGEXP '^R[0-9\?]{2}' OR COALESCE(t1.type_long, '') NOT LIKE '%Re-Release%')) ".
		"order by sort_order");
	if($num = mysql_num_rows($r))
	{
		echo "<li><span class='red'>$num record(s) - reissue mismatch</span><a target=\"_blank\" href=\"http://poster-server/wiki/index.php/Check_Auction_Tables#Mismatching_reissue_check\"> ?wiki</a><br /><table cellpadding='10' border='1'>";
		echo "<th>id</th><th>film_title</th><th>Year/NSS</th><th>film_MrLister</th><th>type_long</th><th>style_info</th><th>title_long</th><th>title_45</th>";
		foreach(mysql_fetch_all($r) as $row)
		{
			if(isset($last_type) && $row[7] != $last_type)	
				echo "<tr><td colspan='6'><hr style='color:red' /></td></tr>\n";
			$last_type = $row[7];
			
			echo "<tr><td><a style='text-decoration: underline' ".
				"href='http://poster-server/listing/index.php?table=".urlencode($table)."&autonumber=$row[5]' ".
				"target='_blank'>$row[0]</a></td><td>$row[1]<br />$row[6]</td>";
			if($interactive)
			{
				echo "<td><textarea style='width: 250px' onblur='update_record(\"descriptions\", \"$row[1]\", \"Year/NSS\", this)'>$row[8]</textarea></td>";
				echo "<td><textarea style='width: 20em' ".
					"onblur='update_record(\"$table\", \"$row[0]\", \"film_MrLister\", this)'>$row[2]</textarea></td>";
				echo "<td><textarea style='width: 350px' onblur='update_record(\"$table\", \"$row[0]\", \"type_long\", this)'>$row[3]</textarea></td>";
				echo "<td><textarea onblur='update_record(\"$table\", \"$row[0]\", \"style_info\", this)'>$row[4]</textarea></td>";
				echo "<td><textarea style='width: 250px' onblur='update_record(\"$table\", \"$row[0]\", \"title_long\", this)'>$row[9]</textarea></td>";
				echo "<td><textarea style='width: 250px' onblur='update_record(\"$table\", \"$row[0]\", \"title_45\", this)'>$row[10]</textarea></td>";

				echo "</tr>";
			}
			else
			{
				echo "<td>$row[8]</td>";
				echo "<td>$row[2]</td>";
				echo "<td>$row[3]</td>";
				echo "<td>$row[4]</td>";
				echo "<td>$row[9]</td>";
				echo "<td>$row[10]</td>";
				echo "</tr>";
			}
		}
		echo "</table></li>";
	}
	elseif($extra)
		echo "<li><span class='green'>Checked Reissues</span><a target=\"_blank\" href=\"http://poster-server/wiki/index.php/Check_Auction_Tables#Mismatching_reissue_check\"> ?wiki</a></li>";
	
	
	
	
	
	/*
		Year mismatch check
	*/
	/* COMMENTING ALL THIS OUT REQUESTED BY Phil BECAUSE IT'S NOT USED AND TAKES A LONG TIME ~JJ 2019 MAY 17
	
	$r = q("SELECT t1.id, film_title, film_MrLister, t1.type_long, style_info, autonumber, ".
		"substring(`Year/NSS`, 1, 4), type_code, `Year/NSS`, ".
		"yearnss_country, substring(film_MrLister, 2, 2) as year2 ".
		"FROM `$table` as t1 ".
		"left join descriptions on film_title = TITLE ".
		"left join AHEAD_Style_Info on (style_id = AHEAD_Style_Info.id) ".
		"left join tbl_types on (if(nationality != '', nationality, type_code) = code)".
		"WHERE ".
		"(film_MrLister regexp '^[0-9]{4} ' and `Year/NSS` not regexp concat('^(18|19|20)', substring(film_MrLister, 3, 2))) ".
		//"(film_MrLister regexp '^\'[0-9]{2} ' and `Year/NSS` not regexp concat('^(18|19|20)', substring(film_MrLister, 2, 2))) ".
		"order by sort_order");
	
	if($num = mysql_num_rows($r))
	{
		require_once("/webroot/includes/parse_year_field.inc.php");
		
		$count = 0;
		
		ob_start();
		echo "<th>id</th><th>film_title</th><th>Year/NSS</th><th>film_MrLister</th><th>type_long</th><th>style_info</th>";
		//foreach(mysql_fetch_all($r) as $row)
		while($row = mysql_fetch_array($r))
		{
	 		*/
			/*
				More advanced filtering/checking here
			*/
			/*
			list($array, list($found, $expected, $index)) = Year::parse($row['Year/NSS']);
			
			if(!empty($array))
			{
				if(in_array(
					substr(Year::year_of_country($array, $row['yearnss_country']), 0, 4), 
					array(false, "18".$row['year2'], "19".$row['year2'], "20".$row['year2'])))
				{
					
					continue;
				}
			}
			
			$count++;
			
			if(isset($last_type) && $row[7] != $last_type)	
				echo "<tr><td colspan='6'><hr style='color:red' /></td></tr>\n";
			$last_type = $row[7];
			
			echo "<tr><td><a style='text-decoration: underline' ".
				"href='http://poster-server/listing/index.php?table=".urlencode($table)."&autonumber=$row[5]' ".
				"target='_blank'>$row[0]</a></td><td>$row[1]<br />$row[6]</td>";
			if($interactive)
			{
				echo "<td><textarea style='width: 250px' onblur='update_record(\"descriptions\", \"$row[1]\", \"Year/NSS\", this)'>$row[8]</textarea></td>";
				echo "<td><textarea style='width: 20em' ".
					"onblur='update_record(\"$table\", \"$row[0]\", \"film_MrLister\", this)'>$row[2]</textarea></td>";
				echo "<td><textarea style='width: 350px' onblur='update_record(\"$table\", \"$row[0]\", \"type_long\", this)'>$row[3]</textarea></td>";
				echo "<td><textarea onblur='update_record(\"$table\", \"$row[0]\", \"style_info\", this)'>$row[4]</textarea></td>";

				echo "</tr>";
			}
			else
			{
				echo "<td>$row[8]</td>";
				echo "<td>$row[2]</td>";
				echo "<td>$row[3]</td>";
				echo "<td>$row[4]</td>";
				echo "</tr>";
			}
		}
		
		echo "</table></li>";
		
		$contents = ob_get_clean();
		if($count > 0)
		{
			// added this if/else statement for better readability on the page
			echo "<li><span class='red'>$count items - year mismatch</span><a target=\"_blank\" href=\"http://poster-server/wiki/index.php/Check_Auction_Tables#Year_mismatch_check\"> ?wiki</a><br /><table cellpadding='10' border='1' class='readmore'>".$contents;
		}
		else
		{
			echo "<li><span class='green'>No year mismatches</span><a target=\"_blank\" href=\"http://poster-server/wiki/index.php/Check_Auction_Tables#Year_mismatch_check\"> ?wiki</a><br /><table cellpadding='10' border='1' class='readmore'>".$contents;
		}
	}
	elseif($extra)
		echo "<li><span class='green'>Checked Years</span><a target=\"_blank\" href=\"http://poster-server/wiki/index.php/Check_Auction_Tables#Year_mismatch_check\"> ?wiki</a></li>";
	*/
	
	
	
	
	
	/*
		Long unbroken strings
		
		Phil has a tendency to ignore this check,
		and then everyone is surprised when stuff is broken.
	*/
	ob_start();
	$r = mysql_query2("select id, title_45 from `$table`");
	$total_unbroken_strings = 0;
	$unbroken_string_length_limit = 20;
	while(list($id, $title) = mysql_fetch_row($r))
	{
		$words = explode(" ", $title);
  		foreach($words as $word)
		{
			if(strlen($word) > $unbroken_string_length_limit)
			{
				echo "<li><input type='text' size='150' ".
					"onblur='update_record(\"$table\", \"$id\", \"title_45\", this)' "
					."value=\"".htmlspecialchars($title)."\"/></li>\n";
				$total_unbroken_strings++;
				break;
			}
		}
	}
	if($total_unbroken_strings == 0)
		echo "<li><span class='green'>Checked for unbroken strings greater ".
			"than $unbroken_string_length_limit characters long.</span><a target=\"_blank\" href=\"http://poster-server/wiki/index.php/Check_Auction_Tables#Long_unbroken_strings_check\"> ?wiki</a></li>";
	else
	{
		echo "<li><span class='red'>Found $total_unbroken_strings title(s) with ".
			"unbroken strings longer than $unbroken_string_length_limit ".
			"characters. <br />These may cause too-wide columns in the gallery, ".
			"depending on the number of characters and frequency of wider letters ".
			"(like 'W', 'M', or 'S').</span><a target=\"_blank\" href=\"http://poster-server/wiki/index.php/Check_Auction_Tables#Long_unbroken_strings_check\"> ?wiki</a><ul>".
			ob_get_clean().
			"</ul></li>\n";
	}
	
	ob_implicit_flush();
	
	
	
	
	
	/*
		x-rated
	*/
	$r = mysql_query2("select id, autonumber, image1, template_date, title_45, film_title, sexploitation, bulk_lot_id ".
		"from `$table` ".
		"left join descriptions on film_title = TITLE where sexploitation = 0 and ".
		"(after_description like '% white bar%' or film_MrLister like '% x-rated%' or ".
		"film_MrLister like '% stripper%' or `eBay Description` like '% sexploitation %' ".
		"or film_title like '% sex%' or film_title like 'sex %')");
	
	if(mysql_num_rows($r))
	{
		echo "<li><span class='orange'>Check x-rated posters.</span><a target=\"_blank\" href=\"http://poster-server/wiki/index.php/Check_Auction_Tables#X-rated_check\"> ?wiki</a> ".
			"<small>Thumbnails only available after images are scaled</small> ".
			"<button onclick='toggle_xrated_items()'>White Bar</button>\n".
			"<button onclick='toggle_xrated_films()'>Adult Filter</button>\n".
			"<table id='xrated_table' style='font-size: 90%' cellpadding='10' border='1'>\n";
		
		while(list($id, $autonumber, $image, $sortfield, $lot_title, $film_title, $sexploitation, $bulk_lot_id) = mysql_fetch_row($r))
		{
			list($images, $thumb) = find_images_for_dated_table_item($image, $sortfield);
			
			echo "<tr><td>$lot_title</td><td style='text-align: center'>".
				"<a target='_blank' href='http://poster-server/".$images[""]."'>".
				((empty($thumb)) ? "Thumbnail not available" : "<img style='width: 100px' src='http://poster-server/$thumb' />").
				"</a><br /><a style='text-decoration: underline' ".
				"href='http://poster-server/listing/index.php?table=".urlencode($table)."&autonumber=$autonumber' target='_blank'>View Record</a>".
				"</td><td>";
			
			
			echo "<table><tr><td>".
				"<label for='xrateditem$id'>White Bar</label></td><td>".
				"<input id='xrateditem$id' class='xrateditem' type='checkbox' ".
				"onchange='update_record(\"$table\", \"$id\", \"xrated\", this)' />".
				"</td></tr>";
			
			if(empty($bulk_lot_id))
			{
				echo "<tr><td><label for='xratedfilm$id'>Adult Filter</label></td><td>".
					"<input id='xratedfilm$id' class='xratedfilm' type='checkbox' ".($sexploitation != 0 ? "checked='checked' " : "").
					"onchange='update_record(\"descriptions\", ".htmlspecialchars(json_encode($film_title), ENT_QUOTES).", \"sexploitation\", this); ' />".
					"</td></tr>";
			}
			
			echo "</table>";
			
			echo "</td></tr>\n";
		}
			
		echo "</table></li>\n";	
	}
	else
		echo "<li><span class='green'>Checked for x-rated items</span><a target=\"_blank\" href=\"http://poster-server/wiki/index.php/Check_Auction_Tables#X-rated_check\"> ?wiki</a></li>\n";
	
	
	
	
	
	//Duplicate posters check. AK, 2012-12-2012
	/* Phil sez: I need it to show me when there are 2 or more items from 
	 * the same film plus the style_info, pre_short, and MrListTitle. 
	 * Those fields should be update-able from Check Auction Tables.
	 */
	//Only select records from the table that are posters.
	//Clean up style_info, and put film_title, style_info, type_code, and id into temporary table.
	function style_info_filter($style_info)
	{
		$output = array();
		
		$tags = array_filter(array_map("trim", explode(";", $style_info)), "strlen");
		
		foreach($tags as $tag)
		{
			if(!preg_match("/^R[0-9]{2,}/", $tag))
				$output[] = $tag;
		}
		
		return implode("; ", $output);
	}
	require_once("/webroot/includes/style_info_tidy.inc.php");
	mysql_query3("create temporary table style_infos (id int, film_title varchar(255), style_info varchar(255), type_code varchar(255), type_pre_short varchar(255))");
	$r = mysql_query3("select id, style_info, film_title, type_code, type_pre_short from `$table` ".
		"join tbl_types on (type_code = code and poster_simple = 'poster')");
	
	while($row = mysql_fetch_assoc($r))
	{
		mysql_query3(sprintf("insert into style_infos values ('%s', '%s', '%s', '%s', '%s')",
			$row['id'], mysql_real_escape_string($row['film_title']), mysql_real_escape_string(style_info_tidy($row, $row['style_info'])),
			mysql_real_escape_string($row['type_code']), mysql_real_escape_string($row['type_pre_short'])));
	}
	mysql_free_result($r);
	
	
	//Create temporary table containing ids of only unique records.
	mysql_query3("create temporary table unique_items select id from style_infos ".
		"group by film_title, style_info, type_code having(count(id) < 2)");  
	
	//The check did not trigger on a pair of items because the style names
	//were in the style_info field but not the type_pre_short field. Phil had me
	//write some code that includes the type_pre_short in the check, but then 
	//we discovered that the reviewers never used the type_pre_short for recording
	//the unique style of poster. Phil showed Bruce and they decided to put it back
	//to how it was before rather than changing the way the reviewers did things. 2016-01-05 AK
	
	//mysql_query3("create temporary table unique_items2 select id from style_infos ".
		//"group by film_title, type_pre_short, type_code, style_info regexp 'R[0-9]{2,}' having(count(id) < 2)");  
	
	//Select all records from the table that are not in the unique list, and are posters.
	$r = mysql_query3("select * from `$table` ".
		"join tbl_types on (type_code = code and poster_simple = 'poster') ".
		"left join unique_items using(id) ".
		//"left join unique_items2 using(id) ".
		"where unique_items.id is null ". //or unique_items2.id is null ".
		"order by film_title, style_info");
	
	if(mysql_num_rows($r))
	{
		echo "<li><span class='orange'>Add styles to duplicates.</span><a target=\"_blank\" href=\"http://poster-server/wiki/index.php/Check_Auction_Tables#Duplicate_posters_check\"> ?wiki</a> <small>Thumbnails only available after images are scaled. ".
			"Duplicate checking is based on style info cleaned up for display in Auction History.</small>\n".
			"<table style='font-size: 90%' cellpadding='10' border='1'>\n";
		
		printf("<tr><th>Image</th><th>Film</th><th>Type</th><th>type_pre_short</th><th>Style</th><th>Title</th></tr>\n");		
		
		while($row = mysql_fetch_assoc($r))
		{
			list($images, $thumb) = find_images_for_dated_table_item($row['image1'], $row['template_date']);
			
			printf("<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>\n",
				"<a target='_blank' href='http://poster-server/".$images[""]."'>".
				((empty($thumb)) ? "Thumbnail not available" : "<img style='width: 100px' src='http://poster-server/$thumb' />").
				"</a><br />".create_review_form_link($table, $row['autonumber']),
				update_record_textarea($table, $row['id'], "film_title", $row['film_title']),
				update_record_textarea($table, $row['id'], "type_code", $row['type_code']),
				update_record_textarea($table, $row['id'], "type_pre_short", $row['type_pre_short']),
				update_record_textarea($table, $row['id'], "style_info", $row['style_info']),
				update_record_textarea($table, $row['id'], "film_MrLister", $row['film_MrLister']));
		}
		
		echo "</table></li>\n";	
	}
	else
		echo "<li><span class='green'>Checked for duplicate items</span><a target=\"_blank\" href=\"http://poster-server/wiki/index.php/Check_Auction_Tables#Duplicate_posters_check\"> ?wiki</a></li>\n";
	
	
	
	
	
	/*
		Valid type codes
	*/
	$r = q("SELECT id, type_code, autonumber FROM `$table` LEFT JOIN tbl_types ON tbl_types.code = `$table`.type_code ".
		"WHERE coalesce(type_code, '') NOT IN (SELECT code FROM tbl_types) ORDER BY sort_order");
	if($num = mysql_num_rows($r))
	{
		echo "<li><span class='red'>There were invalid type codes</span><a target=\"_blank\" href=\"http://poster-server/wiki/index.php/Check_Auction_Tables#Valid_type_codes_check\"> ?wiki</a><ul>";
		foreach(mysql_fetch_all($r) as $row)
		{
			echo "<li><a style='text-decoration: underline' href='http://poster-server/listing/index.php?table=".urlencode($table).
				"&autonumber=$row[2]' target='_blank'>id #$row[0]</a>, "; 
			
			if($interactive)
			{
				echo "<select onblur='update_record(\"$table\", \"$row[0]\", \"type_code\", this)'>";
				echo "<option value='$row[1]'>INVALID: ($row[1])</option>";
				$resulto = mysql_query("SELECT code FROM tbl_types ORDER BY sort_order ASC");
				while($rowo = mysql_fetch_row($resulto))
					echo "<option value='".addslashes($rowo[0])."'>$rowo[0]</option>";
				echo "</select>";
			}
			else
				echo "$row[1]";
			
			echo "</li>";
		}
		echo "</ul></li>";
	}
	elseif($extra)
		echo "<li><span class='green'>Validated type_code</span><a target=\"_blank\" href=\"http://poster-server/wiki/index.php/Check_Auction_Tables#Valid_type_codes_check\"> ?wiki</a></li>";
	
	
	
	
	
	/*
		Valid condition_overall
	*/
	$r = q("SELECT id, condition_overall, autonumber FROM `$table` WHERE condition_overall NOT IN ('good to very good','very good to fine','fair to good','poor to fair','very good','fine','good','fair','poor','good to very good, WITH CUTS','very good to fine, WITH CUTS','fair to good, WITH CUTS','poor to fair, WITH CUTS','very good, WITH CUTS','fine, WITH CUTS','good, WITH CUTS','fair, WITH CUTS','poor, WITH CUTS','good to very good, NO CUTS','very good to fine, NO CUTS','fair to good, NO CUTS','poor to fair, NO CUTS','very good, NO CUTS','fine, NO CUTS','good, NO CUTS','fair, NO CUTS','poor, NO CUTS')");
	if($num = mysql_num_rows($r))
	{
		echo "<li><span class='red'>There were invalid conditions!</span><a target=\"_blank\" href=\"http://poster-server/wiki/index.php/Check_Auction_Tables#Valid_condition_overall_check\"> ?wiki</a><ul>";
		foreach(mysql_fetch_all($r) as $row)
		{
			echo "<li><a style='text-decoration: underline' href='http://poster-server/listing/index.php?table=".urlencode($table)."&autonumber=$row[2]' target='_blank'>id #$row[0]</a>, <select onblur='update_record(\"$table\", \"$row[0]\", \"condition_overall\", this)'>";
			
			if($interactive)
			{
				echo "<option value='$row[1]'>INVALID: ($row[1])</option>";
				foreach(Array('good to very good','very good to fine','fair to good','poor to fair','very good','fine','good','fair','poor') as $opt)
					echo "<option value='".addslashes($opt)."'>$opt</option>";
				echo "</select>";
			}
			else
				echo "$row[1]";
			 
			
			echo "</li>";
		}
		echo "</ul></li>";
	}
	elseif($extra)
		echo "<li><span class='green'>Validated condition_overall</span><a target=\"_blank\" href=\"http://poster-server/wiki/index.php/Check_Auction_Tables#Valid_condition_overall_check\"> ?wiki</a></li>";
	
	
	
	
	
	/*
		single-sided
	*/
	$r = q("SELECT count(*) FROM `$table` WHERE type_code LIKE '1sh%'");
	$count_1sh = mysql_result($r,0,0);
	$r = q("SELECT `$table`.id, film_title, style_info, type_long, `Year/NSS`, autonumber ".
		"FROM `$table` ".
		"left join AHEAD_Style_Info on (style_id = AHEAD_Style_Info.id) ".
		"LEFT JOIN descriptions ON descriptions.TITLE = `$table`.film_title ".
		"WHERE type_code LIKE '1sh%' AND (CONVERT(SUBSTRING(`Year/NSS`, 1, 4), signed) >= 1990 or reissueyear >= 1990) ".
		"AND type_long NOT LIKE '%double-sided%' AND type_long NOT LIKE '%single-sided%' AND type_long NOT LIKE '%Linenback%' and type_long not like '%two-sided%'");
	
	if($num = mysql_num_rows($r))
	{
		echo "<li><span class='red'>There were $num record(s) that should have 'Double-Sided' or 'Single-Sided' but do not.</span><a target=\"_blank\" href=\"http://poster-server/wiki/index.php/Check_Auction_Tables#Single-sided_check\"> ?wiki</a> ";
		if($interactive)
			echo "<input type='button' onclick='$(\"[name=singleSidedCheck]\").each(function(){this.value = this.value.replace(\"One-Sheet\", \"Single-Sided One-Sheet\"); update_record(\"$table\", $(this).attr(\"eyeDee\"), \"type_long\", this)}); $(this).attr(\"disabled\", \"disabled\");' value='Fix All' />";
		echo "<br /><table cellpadding='10' border='1'>";
		echo "<tr><th>id</th><th>film_title</th><th>style_info</th><th>year</th><th>type_long</th></tr>";
		foreach(mysql_fetch_all($r) as $row)
		{
			$row[2] = ($row[2] == "") ? "&nbsp;" : htmlspecialchars($row[2]);
			$year = substr($row[4], 0, 4);
			echo "<tr><td><a style='text-decoration: underline' href='http://poster-server/listing/index.php?table=".urlencode($table)."&autonumber=$row[5]' target='_blank'>".$row[0]."</a></td><td>".$row[1]."</td><td>".$row[2]."</td><td>".$year."</td>"; 
			if($interactive)
				echo "<td><textarea eyeDee='$row[0]' name='singleSidedCheck' style='width: 60em' onblur='update_record(\"$table\", \"$row[0]\", \"type_long\", this)'>".$row[3]."</textarea></td>";
			else
				echo "<td>$row[3]</td>";
			
			echo "</tr>";
		}
		echo "</table></li>";
	}
	elseif($extra)
		echo "<li><span class='green'>Checked single-sided/double-sided ($count_1sh 1sheets)</span><a target=\"_blank\" href=\"http://poster-server/wiki/index.php/Check_Auction_Tables#Single-sided_check\"> ?wiki</a></li>";
	
		
	
	
	
	/*
		check for empty quantity in 8x10multi table
	*/
	if(strstr($table, "8x10MULTI"))
	{
		$r = mysql_query2("select id, film_title, type_code, type_pre_short, type_long, autonumber ".
			"from `$table` where (type_code like '%8x10%' or type_code like '%LC%') and ".
			"(type_pre_short not regexp '^[0-9]' or type_long not regexp '^[0-9]')");
		$num = mysql_num_rows($r);
		if($num > 0)
		{
			echo "<li><span class='red'>There were $num record(s) possibly missing quantities</span><a target=\"_blank\" href=\"http://poster-server/wiki/index.php/Check_Auction_Tables#Check_for_empty_quantity_in_8x10multi_table\"> ?wiki</a>".
				"<br /><table cellpadding='10' border='1'>";
			
			while($row = mysql_fetch_row($r))
			{
				echo "<tr><td><a style='text-decoration: underline' ".
					"href='http://poster-server/listing/index.php?table=".urlencode($table)."&autonumber=$row[5]' ".
					"target='_blank'>".$row[0]."</a></td><td>".$row[1]."</td></tr>\n";
			}
			
			echo "</table></li>";	
		}
		elseif($extra)
			echo "<li><span class='green'>Checked for items that should have quantity</span><a target=\"_blank\" href=\"http://poster-server/wiki/index.php/Check_Auction_Tables#Check_for_empty_quantity_in_8x10multi_table\"> ?wiki</a></li>\n";
	}
	
	
	
	
	
	/*
		blank consignor
	*/
	$r = q("SELECT id, film_title, autonumber FROM `$table` WHERE COALESCE(consignor, '') = ''");
	$num = mysql_num_rows($r);
	if($num > 0)
	{
		echo "<li><span class='red'>There were $num record(s) with a blank or null consignor.</span><a target=\"_blank\" href=\"http://poster-server/wiki/index.php/Check_Auction_Tables#Blank_consignor_check\"> ?wiki</a><br /><table cellpadding='10' border='1'>";
		echo "<tr><th>id</th><th>film_title</th></tr>";
		foreach(mysql_fetch_all($r) as $row)
			echo "<tr><td><a style='text-decoration: underline' href='http://poster-server/listing/index.php?table=".urlencode($table)."&autonumber=$row[2]' target='_blank'>".$row[0]."</a></td><td>".$row[1]."</td></tr>";
		echo "</table></li>";
	}
	elseif($extra)
		echo "<li><span class='green'>Checked for blank consignors</span?><a target=\"_blank\" href=\"http://poster-server/wiki/index.php/Check_Auction_Tables#Blank_consignor_check\"> ?wiki</a></li>";
	
	//3D vs. 3-D
	$r = q("SELECT id, film_title, autonumber FROM `$table` LEFT JOIN descriptions ON TITLE = film_title WHERE CONCAT_WS(' ', film_MrLister, type_long, condition_major_defects, condition_common_defects, after_description, title_long, title_45, style_info, `eBay Description`) LIKE '% 3d %' AND CONCAT_WS(' ', film_MrLister, type_long, condition_major_defects, condition_common_defects, after_description, title_long, title_45, style_info, `eBay Description`) NOT LIKE '%3-D (3D; 3-Dimension)%'");
	$num = mysql_num_rows($r);
	if($num > 0)
	{
		echo "<li><span class='red'>There were $num record(s) that have \"3D\" and may need to be changed to \"3-D (3D; 3-Dimension)\".</span><a target=\"_blank\" href=\"http://poster-server/wiki/index.php/Check_Auction_Tables#3D_vs._3-D_check\"> ?wiki</a><br /><table cellpadding='10' border='1'>";
		echo "<tr><th>id</th><th>film_title</th></tr>";
		foreach(mysql_fetch_all($r) as $row)
			echo "<tr><td><a style='text-decoration: underline' href='http://poster-server/listing/index.php?table=".urlencode($table)."&autonumber=$row[2]' target='_blank'>".$row[0]."</a></td><td>".$row[1]."</td></tr>";
		echo "</table></li>";
	}
	elseif($extra)
		echo "<li><span class='green'>Checked for \"3D\"</span><a target=\"_blank\" href=\"http://poster-server/wiki/index.php/Check_Auction_Tables#3D_vs._3-D_check\"> ?wiki</a></li>\n";
		
	
		
	
	
	/*
		Cut pressbooks
	*/
	$r = mysql_query3("select id, autonumber from `$table` where style_info like '%cut%' and ".
		"type_code like '%pb%' and ". //added this to only check pressbook types JJ 2019 May 15
		"style_info not like '%no cut%' and (condition_overall like 'very good%' or ".
		"condition_overall like 'fine%')");
	if(mysql_num_rows($r))
	{
		echo "<li><span class='red'>Cut pressbooks with too-high condition grade</span><a target=\"_blank\" href=\"http://poster-server/wiki/index.php/Check_Auction_Tables#Cut_press_books_check\"> ?wiki</a>";
		echo "<ul>";
		
		while(list($id, $autonumber) = mysql_fetch_row($r))
		{
			printf("<li>%s</li>", create_review_form_link($table, $autonumber));
		}
		
		echo "</ul>";
		echo "</li>";
	}
	elseif($extra)
	{
		echo "<li><span class='green'>Checked for cut pressbooks with too-high condition grade</span><a target=\"_blank\" href=\"http://poster-server/wiki/index.php/Check_Auction_Tables#Cut_press_books_check\"> ?wiki</a></li>\n";
	}
	
	
	
	
	
	/*
		check for unwanted strings. "poster-server"
	*/
	$r = mysql_query3("select id, autonumber from `$table` ".
		"left join descriptions ON film_title = TITLE ".
		"where `".str_replace("`artist`", "`$table`.artist", implode("` like '%poster-server%' or `", $fields_to_check)."` like '%poster-server%'"));
	
	if(mysql_num_rows($r))
	{
		echo "<li><span class='red'>Records with 'poster-server' in text</span><a target=\"_blank\" href=\"http://poster-server/wiki/index.php/Check_Auction_Tables#Check_for_unwanted_strings._.22poster-server.22\"> ?wiki</a>";
		echo "<ul>";
		
		while(list($id, $autonumber) = mysql_fetch_row($r))
		{
			printf("<li>%s</li>", create_review_form_link($table, $autonumber));
		}
		
		echo "</ul>";
		echo "</li>";
	}
	elseif($extra)
	{
		echo "<li><span class='green'>Checked for 'poster-server' in text</span><a target=\"_blank\" href=\"http://poster-server/wiki/index.php/Check_Auction_Tables#Check_for_unwanted_strings._.22poster-server.22\"> ?wiki</a></li>\n";
	}
	
	
	
	
	
	/*
		new check that Phil wants JJ 20190723
		Check for cuts
	*/
	
	$r = mysql_query3("SELECT condition_overall, condition_major_defects, style_info, id, autonumber, type_code FROM `$table` WHERE (condition_major_defects LIKE '% cut%' AND condition_overall NOT LIKE 'WITH CUTS') OR (condition_major_defects LIKE '% cut%' AND style_info NOT LIKE 'CUT')");
	
	if(mysql_num_rows($r))
	{
		echo "<li><span class='red'>Checked for cuts via the \"condition_major_defects\" field and found: </span><a target=\"_blank\" href=\"http://poster-server/wiki/index.php/Check_Auction_Tables#Check_for_.22cut.22_in_the_.22condition_major_defects.22_field\"> ?wiki</a>\n";
		echo "<table style='font-size: 90%' cellpadding='10' border='1'>";
		echo "<tr><th>id</th><th>condition_major_defects</th><th>condition_overall</th><th>style_info</th><th>type_code</th></tr>\n";
		
		foreach(mysql_fetch_all($r) as $row)
		{
			//echo "<tr><td><a style='text-decoration: underline' href='http://poster-server/listing/index.php?table=" . urlencode($table) . "&autonumber=$row[4]' target='_blank'>id #$row[4]</a></td><td>" . $row[1] . "</td><td>" . $row[0] . "</td><td>" . $row[2] . "</td></tr>";
			printf("<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>\n",
				"<br />".create_review_form_link($table, $row[4]),
				update_record_textarea($table, $row[3], "condition_major_defects", $row[1]),
				update_record_textarea($table, $row[3], "condition_overall", $row[0]),
				update_record_textarea($table, $row[3], "style_info", $row[2]),
				update_record_textarea($table, $row[3], "type_code", $row[5]));
		}
		echo "</table></li>\n";
	}
	else
	{
		echo "<li><span class='green'>Checked for cuts in the \"condition_major_defects\" field.</span><a target=\"_blank\" href=\"http://poster-server/wiki/index.php/Check_Auction_Tables#Check_for_.22cut.22_in_the_.22condition_major_defects.22_field\"> ?wiki</a></li>\n";
	}
	
	
	
	
	
	/*
		New check to make sure lot_number, title_long, and title_45 have correct first two digits
		JJ 20191011
	*/
	
	$lotVersion = substr($table, -2);
	 
	$r = mysql_query3("SELECT * FROM listing_system.`$table` WHERE substr(lot_number, 1, 2)!='$lotVersion' OR substr(title_long, 1, 2)!='$lotVersion' OR substr(title_45, 1, 2)!='$lotVersion'");
	
	if(mysql_num_rows($r))
	{
		echo "<li><span class='red'>WARNING: THERE ARE INCORRECT LOT NUMBERS!</span><a target=\"_blank\" href=\"http://poster-server/wiki/index.php/Check_Auction_Tables#Check_for_incorrect_lot_numbers_in_the_.22lot_number.2C_title_long.2C_and_title_45.22_fields\"> ?wiki</a>\n";
	}
	else
	{
		echo "<li><span class='green'>Checked for incorrect lot numbers.</span><a target=\"_blank\"http://poster-server/wiki/index.php/Check_Auction_Tables#Check_for_incorrect_lot_numbers_in_the_.22lot_number.2C_title_long.2C_and_title_45.22_fields\"> ?wiki</a></li>\n";
	}
	
	//end of check
	
	echo "</ul>";
}

?>