<?php
function parse_pressbook_extra_info($style_info)
{
	$tokens = array_map("trim", explode(";", strtolower($style_info)));
	
	$info = array("supplement" => array(), "herald" => array());
	
	foreach($tokens as $token)
	{
		if(preg_match("/^([0-9]+) (supplement|herald)s? \(([^\(\)]+)\)$/", $token, $matches))
		{
			$split = array_map("trim", explode(",", $matches[3]));
			foreach($split as $s)
			{
				$info[$matches[2]][0] = $matches[1];
				if(preg_match("/^([0-9]+)pgs?$/", $s, $page_matches))
				{
					$info[$matches[2]][1][] = $page_matches[1];
				}
			}
		}
	}
	
	return $info;
}

function format_pages_for_display($pages)
{
	$numbers = array("zero", "one", "two", "three", "four", "five", "six", "seven", "eight", "nine");
	$numbers = array(0,1,2,3,4,5,6,7,8,9);
	
	switch(count($pages))
	{
		case 1:
			if(array_key_exists($pages[0], $numbers))
			{
				if($pages[0] == 1)
					return "one page";
				else
					return $numbers[$pages[0]]." pages";
			}
			else
				return $pages[0]." pages";
			break;
			
		case 2:			
			if(array_key_exists($pages[0], $numbers))
			{
				if($pages[0] == 1)
					$text = "one page and ";
				else
					$text = $numbers[$pages[0]]." pages and ";
			}
			else
				$text = $pages[0]." pages and";
			
			if(array_key_exists($pages[1], $numbers))
			{
				if($pages[1] == 1)
					$text .= "one page";
				else
					$text .= $numbers[$pages[1]]." pages";
			}
			else
				$text .= $pages[1]." pages";
			return $text;
			break;
			
		default:
			$text = "";
			foreach($pages as $k => $p)
			{
				if(array_key_exists($p, $numbers))
				{
					if($p == 1)
						$text .= "one page, ";
					else
						$text .= $numbers[$p]." pages, ";
					
					
				}
				else
					$text .= $p." pages, ";
				
				if($k == count($pages)-2)
					$text .= "and ";
			}
			
			return substr($text, 0, -2);
			break;
	}
}

?>