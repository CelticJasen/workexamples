<?php
/*
File: style_info_tidy.inc.php

Contains a function for tidying up the style_info field
for public display.

Written by Aaron Kennedy, kennedy@postpro.net, December/January 2011
*/

function array_iunique($array)
{
	/*
	Function: array_unique
	
	Works like array_unique, but is case insensitive.
	Only works on a numerically-indexed array
	
	Aaron Kennedy
	*/
	
	$new_array = Array();
	
	foreach($array as $k => $v)
	{
		if(false === in_iarray($v, $new_array))
		{
			$new_array[] = $v;
		}
	}
	
	return $new_array;
}

function in_iarray($needle, $haystack)
{
	/*
	Function in_iarray
	
	Works like in_array, only case insensitive.
	
	Aaron Kennedy
	*/
	
	foreach($haystack as $v)
	{
		if(0 === strcasecmp($needle, $v))
			return true;
	}
	
	return false;
}

function remove_text_inside_parentheses($string, &$mismatched_parenthesis = false)
{
	/*
	Function: remove_text_inside_parenthesis
	
	Takes two arguments:
	1 - the string to be modified
 	2 - optional variable passed by reference which will be set to True
		if mismatched parenthesis were found or False otherwise.
	*/
	
	$mismatched_parenthesis = false;
	$chars = str_split($string);
	$new_string = "";
	$parenth_state = 0;
	$skip_spaces = false;
	
	for($i = 0; $i < count($chars); $i++)
	{
		$char = $chars[$i];

		switch($char)
		{
			case "(":
				if($parenth_state < 0)
					$parenth_state = 1;
				else
					$parenth_state++;
				break;
				
			case ")":
				$parenth_state--;
				
				if($parenth_state < 1)
					$skip_spaces = true;
				
				if($parenth_state < 0)
					$mismatched_parenthesis = true;
				break;
				
			default:
				if($skip_spaces === true)
				{
					if($char != " " && $char != "\t")
					{
						$skip_spaces = false;
						$new_string .= $char;
					}
				}
				elseif($parenth_state < 1)
					$new_string .= $char;
				break;
		};
	}
	
	if($mismatched_parenthesis !== true)
		$mismatched_parenthesis = ($parenth_state != 0);
	
	return $new_string;
}

function style_info_tidy($item, $style_info_old)
{
	/*
	Function: style_info_tidy
	
	Pass it a record from the auction history and
	its style info separately
	and it will return the cleaned-up style info.
	*/
	
	$tokens_new = Array();
	
	$style_info = remove_text_inside_parentheses($style_info_old, $mismatched_parenthesis);
	
	$tokens = style_info_tokenize($style_info);	
	
	if(!empty($item['how_rolled']))
	{
		/*
		Phil says:
		-----
		*the how_rolled field should be included with the style_info_new field, but it doesn't appear to be doing that for newly added records. Can you either:
		1) make it so it is included for new ones AND retroactively
 		or
		2) make it so the how_rolled field is displayed with the style_info in  the Auction History results and then remove all how_rolled info from the style_info_new?
		-----
		This implements #1.
		*/
		
		$tokens_new[] = $item['how_rolled'];
	}
	
	$patterns_to_exclude = Array(
		//Remove DUPE # and NUM #
		"/^(dupe|num) *[0-9]+/i",
		
		//Phil says it's OK to remove number of pieces everywhere
		"/^[0-9]+ *(pc|piece)/i",
				
		"/^no cut/i",
		
		//Phil says it's OK to remove number of pages everywhere
		"/^[0-9]+ *(pg|page)/i",
		
		"/^[0-9]+ *(herald|sup|stills)/i",
		"/^(w\/|with|) *(coa|c\.o\.a|bag|box)/i",
		"/^mini *lc/i",
		"/^hand[ -]*numbered/i",
		
		//Phil asked Bruce, and Bruce says REPRO should display in the galleries. 2012-05-10
		//"/^repro/i",
		"/^kraftback/i",
		
		//this goes along with hand-numbered
		"/^#?[0-9]+\/[0-9]+/",
		
		//Phil says: "arthouse" should be removed during auto cleanup. 2012-10
		"/^arthouse$/i", 
		
	);
	
	foreach($tokens as $k => $v)
	{
		
		foreach($patterns_to_exclude as $pattern)
		{
			if(preg_match($pattern, $v))
				continue(2);
		}
		
		/*
		 * Phil says: strip out "w/ supplement" (even on Danish programs and 
		 * anywhere else it comes up). In fact, I'm pretty sure you can 
		 * strip out anything that reads "w/ ???????????" EXCEPT the word 
		 * "poster". 2012-10
		 */
		if(preg_match("/^w\//i", $v) && !preg_match("/poster/i", $v))
			continue;
		
		if(!empty($item['film_title']))
		{
			//If it says, for example, "signed: George Clooney" and the
			//item's film_title is "GEORGE CLOONEY", just change it to "signed".
			if(preg_match("/^signed: *".preg_quote($item['film_title'], "/")."$/i", $v))
				$v = "signed";
		}
		
		if(preg_match("/^artwork$/i", $v))
			$v = "art";
			
		//Gary says:
		//words like style, video, int'l, military, and reviews are capitalized. 
		//lowercase them for consistency
		$v = str_ireplace(
				Array("style", "video", "int'l", "military", "reviews", "advance", "teaser", "publicity", "foreign", "domestic"),
				Array("style", "video", "int'l", "military", "reviews", "advance", "teaser", "publicity", "foreign", "domestic"),
				$v
			);
		
		$v = str_ireplace(
				Array("hardcover book", "softcover book", "paperback book"),
				Array("hardcover", "softcover", "paperback"),
				$v
			);
		
		$v = str_ireplace(Array("3-d", "3d"), "3d", $v);
		
		$v = preg_replace("/^(2 sided|two-sided|two sided)$/", "2-sided", $v);
		
		$tokens_new[] = $v;
	}	
	
	return implode("; ", array_iunique($tokens_new));
}

function style_info_tokenize($style_info)
{
	return array_filter(array_map("trim", explode(";", $style_info)), "strlen");
}

function style_info_order($element)
{
        $element = strtolower($element);

        if(preg_match("/^r[0-9]{2}/i", $element))
                return 1;

        if($element == "teaser")
                return 2;

        if($element == "advance")
                return 3;

        if(preg_match("/^style [a-z]/i", $element))
                return 4;

        if(preg_match("/^.* style/i", $element))
                return 5;

        if($element == "dom" || $element == "domestic")
                return 6;

        if($element == "for" || $element == "foreign")
                return 7;

        if($element == "int'l")
                return 8;

        if($element == "video")
                return 9;

        if(preg_match("/^chapter/", $element))
                return 10;

        if(preg_match("/^#([0-9])$/", $element, $match))
                return 11+$match[1];

        //Next index: 20

        if(preg_match("/^(UF|FF|TF|linen|pbacked|laminated|1f|WTF|mounted|lenticular|gelbacked)$/i", $element))
                return 100;

        return 50;
}


function style_info_order_compare($first, $second)
{
        $first = style_info_order($first);
        $second = style_info_order($second);

        if($first == $second)
                return 0;

        return ($first < $second) ? -1 : 1;
}




?>
