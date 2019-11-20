<?php

class String
{
	/*
	Class: Strings

	I'm using this as a namespace for random
	string functions.

	Aaron Kennedy, kennedy@postpro.net, 2013-03-15
	*/

	function xtrim($str)
	{
		/*
		Method: xtrim
		
		Remove whitespace from beginning and end, 
		plus collapse multiple spaces to a single space.
		
		This function was taken from the autolister,
		which I think was taken from someone on php.net
		who had written it. They called it TrimStr.
		*/
	    $str = trim($str);
		
		$ret_str = "";
		
	    for($i=0;$i < strlen($str);$i++)
	    {

	        if(substr($str, $i, 1) != " ")
	        {

	            $ret_str .= trim(substr($str, $i, 1));

	        }
	        else
	        {
	            while(substr($str,$i,1) == " ")
	          
	            {
	                $i++;
	            }
	            $ret_str.= " ";
	            $i--; // ***
	        }
	    }
		
	    return $ret_str;
	}
	
	
	
	function limit($str, $lim)
	{
		/*
		Method: limit
		
		If the string exceeds the length $lim, 
		substr it and add ellipses (...).
		
		Don't know where this came from.
		*/
		if(strlen($str) > $lim)
		{
		        $str = substr($str, 0, $lim);
		        $str .= "...";
		}
		return $str;
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



	function transliterate($string)
	{
		/*
		Method: transliterate
		
		Take an ISO-8859-1 string and remove accents and special
		characters, converting to ASCII-only characters.
		
		2013-04-09
		*/
		setlocale(LC_CTYPE, "en_US.utf8");
		return iconv('ISO-8859-1','ASCII//TRANSLIT//IGNORE', $string);
	}



	function split_list($list, $separator = ";")
	{
		return array_values(array_filter(array_map("trim", explode($separator, $list)), "strlen"));
	}



	function split_list2($list, $separator = ";")
	{
		$list = array_filter(array_map("strtolower", array_map("trim", explode($separator, $list))), "strlen");
		sort($list);
		return $list;
	}
	
	
	
	static function pretty_print($data, $prepend = "", $name_width = 20, $value_width = 40)
	{
		$output = "";
		
		if(is_array($data))
		{
			foreach($data as $k => $v)
			{
				$k = explode("\n", wordwrap($k, $name_width, "\n", true));
				
				if(is_array($v))
				{
					$v = explode("\n", self::pretty_print($v, $prepend, $name_width, $value_width));
				}
				else
				{
					$v = explode("\n", wordwrap($v, $value_width, "\n", true));
				}
				
				for($x = 0; $x < max(count($k), count($v)); $x++)
				{
					$output .= $prepend.
						str_pad(self::get($k, $x), $name_width, " ", STR_PAD_RIGHT)." | ".
						str_pad(self::get($v, $x), $value_width, " ", STR_PAD_RIGHT)."\n";
				}
				
				if(count($k) > 1 || count($v) > 1)
					$output .= "\n";
			}
		}
		else
		{
			$output .= $data;
		}
		
		return $output;
	}

	function get($array, $key)
	{
		if(!isset($array[$key]))
			return "";
		else
			return $array[$key];
	}
	
	function filter_comment($line)
	{
		return preg_replace("/#.*/", "", $line);
	}
}

?>
