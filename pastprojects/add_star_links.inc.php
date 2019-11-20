<?php
/*
File: add_star_links.inc.php

This file contains functions for replacing actors' names
in movie descriptions with links to pages describing the
actor.

This file is copied daily from poster-server to the webserver.
It will be used by the auction export code as well as the Auction History.

Phil idea: Get IMDb's list of actors/movies they acted in and use that
to link to the proper person.

Aaron Kennedy, kennedy@postpro.net, 2012-12-20
*/
if(!defined("MYSQL_PEOPLE_TABLE"))
	define("MYSQL_PEOPLE_TABLE", "listing_system.people");

if(!defined("MYSQL_DESCRIPTIONS_TABLE"))
	define("MYSQL_DESCRIPTIONS_TABLE", "listing_system.descriptions");
	
if(!defined("MYSQL_DESCRIPTIONS_PEOPLE_EXCLUSIONS_TABLE"))
	define("MYSQL_DESCRIPTIONS_PEOPLE_EXCLUSIONS_TABLE", "listing_system.descriptions_people_exclusions");

function strlen_compare($s1, $s2)
{
	$len_s1 = strlen($s1[0]);
	$len_s2 = strlen($s2[0]);
	
	if($len_s1 < $len_s2)
		return -1;
	elseif($len_s1 > $len_s2)
		return 1;
	else
		return 0;
}

function actor_matches_position_compare($a, $b)
{
	$pos1 = $a[1];
	$pos2 = $b[1];
	
	if($pos1 < $pos2)
		return -1;
	elseif($pos1 > $pos2)
		return 1;
	else
		return 0;
}

function extract_year_from_description($row)
{
	if(!empty($row) && preg_match("/^(?:18|19|20)[0-9]{2}/", $row['Year/NSS'], $match))
		return $match[0];
	else
		return false;
}


function get_name_matches($description, $year, $names_only = false, $minimum_fame = null, $descriptions_id = null)
{
	$names = get_star_names($year, null, $descriptions_id, $minimum_fame);
	
	/*
	 * Sort names list by string length, longest first.
	 * This way we don't accidentally match part of a 
	 * longer name
	 */
	usort($names, "strlen_compare");
	$names = array_reverse($names);
	
	
	/*
	 * We want to find the position of the "title" portion of the description,
	 * so we can not match actor names that occur in that portion.
	 */
	if(preg_match("/ (18|19|20)[0-9]{2}/", $description, $match, PREG_OFFSET_CAPTURE))
		$begin_matching = $match[0][1];
	else
		$begin_matching = 0;
	
	
	/*
	 * Find names in the description and record their beginning and end indices
	 * 
	 * Store in $matches
	 */
	$matches = array();
	
	foreach($names as $row)
	{
		$case_sensitive = (stripos($row[0], " ") == false); //Case-sensitive matching for single names only, such as "Prince"
		
		if(preg_match_all("/(?<=[^a-z0-9])".preg_quote($row[0], "/")."(?=[^a-z0-9]|$)/".($case_sensitive ? "" : "i"), $description, 
			$preg_matches, PREG_OFFSET_CAPTURE | PREG_PATTERN_ORDER))
		{
			if($names_only)
			{
				$matches[$row[1]] = $row[0];
			}
			else
			{
				foreach($preg_matches[0] as $preg_match)
				{				
					$position = $preg_match[1];
					
					if($position < $begin_matching)
						continue;
					
					foreach($matches as $m)
					{					
						if($position >= $m[1] and $position+strlen($row[0]) <= $m[2]) //Changed from < to <= due to Penelope Ann Miller versus Ann Miller bug. AK 20130503
							continue(2);
					}
				
					//Positions are 0-indexed.
					$matches[] = array($row, $position, strlen($row[0])+$position);
				}
			}
		}
	}
	

	return $matches;
}

function add_star_links($description, $year, $characters_to_save = 0, $return_matches = false, $descriptions_id = null)
{
	$matches = get_name_matches($description, $year, false, null, $descriptions_id);
	
	if($return_matches)
		return $matches;
	
	//What's the point in continuing if we didn't find anything?
	if(count($matches) == 0)
		return $description;
	
	/*
	 * I have to sort the matches ascending by their numeric position 
	 * so I can keep my sanity. AK
	 */
	usort($matches, "actor_matches_position_compare");
	
	/*
	 * Assemble new description.
	 */
	$new_description = substr($description, 0, $matches[0][1]);	
	
	/*
	 * Calculate how many links to leave out based on the number of 
	 * bytes we are trying to conserve.
	 */
	$links_to_remove = ceil($characters_to_save / strlen("<a href='http://www.emovieposter.com/auctions/person.php?q=1000' ".
			"onclick='if(window.nw2){return nw2(this.href)}'></a>"));
	
	for($i = 0; $i < count($matches); $i++)
	{
		list(list($name, $id), $start, $end) = $matches[$i];
		
		/*
		 * Stop adding links when we run out of bytes available.
		 */
		if($i < count($matches)-$links_to_remove)
		{
			$new_description .= "<a href='http://www.emovieposter.com/auctions/person.php?q=$id' ".
				"onclick='if(window.nw2){return nw2(this.href)}'>".
				substr($description, $start, $end-$start)."</a>";
		}
		else
		{
			$new_description .= substr($description, $start, $end-$start);
		}
			
		if(!empty($matches[$i+1]))
			$new_description .= substr($description, $end, $matches[$i+1][1]-$end);
	}
	
	$new_description .= substr($description, $end);
	
	return $new_description;
}



function dumb_match_star_names($description, $star_names)
{
	$matches = array();
	foreach($star_names as $star)
	{
		if(preg_match("/(?<=[^a-z0-9])".preg_quote($star['printed_name'], "/")."(?=[^a-z0-9]|$)/i", $description))
			$matches[] = $star;
	}
	return $matches;
}



function get_star_names($year, $more = false, $descriptions_id = null, $minimum_fame = null)
{
	/*
	 * Get list of actors that could have acted in this film,
	 * based off their career begin and end dates.
	 */
	$query = "select * from ".MYSQL_DESCRIPTIONS_TABLE." ".
		"join ".MYSQL_PEOPLE_TABLE." on (descriptions_id = autoID and (type_of_record = 'person' or type_of_record = 'group')) ";
	
	if(is_null($year))
	{
		$query .= "where career_begin is not null ";
	}
	else
	{
		$query .= "where career_begin <= '$year' and ".
			"(career_end is null or career_end >= '$year') ";
	}
	
	if($minimum_fame)
	{
		$query .= " and famousness >= '$minimum_fame' ";
	}
	
	$r = mysql_query3($query." order by career_end is not null, career_end desc");
	
	
	
	/*
	 *	Get the list of people who should explicitly not be 
	 *	in this description.
	*/
	$exclusions = array();
	if(!is_null($descriptions_id))
	{
		$r2 = mysql_query3("select person from ".MYSQL_DESCRIPTIONS_PEOPLE_EXCLUSIONS_TABLE." where film = '$descriptions_id'");
		while(list($person) = mysql_fetch_row($r2))
		{
			$exclusions[] = $person;
		}
	}
	
	
	
	$names = $names_for_duplicate_checking = array();
	
	while($row = mysql_fetch_assoc($r))
	{
		if(in_array($row['autoID'], $exclusions))
		{
			continue; //Here is where the actual exclusion happens ~JJ
		}
		
		if(!empty($row['printed_name']))
		{
			$name = $row['printed_name'];
		}
		else
		{
			$name = preg_replace("/\([^\(\)]*\)/", "", $row['TITLE']);
		}
		
		//I'm afraid to use too-short names because of high likelihood of mistakes. AK 
		if($more || strlen($row['TITLE']) > 6 || $row['override_length_limitation'])
		{
			/*
			 * Keep the list of names unique.
			 * The first name chosen is determined by the 
			 * sort order of the result set, which is currently
			 * sorted by the career_end, newest to oldest.
			 */
			if(empty($names_for_duplicate_checking[strlen($name)]) || !in_array(crc32($name), $names_for_duplicate_checking[strlen($name)]))
			{
				if($more)
					$names[] = $row;
				else
					$names[] = array($name, $row['autoID']);
			}
			
			
			if(array_key_exists(strlen($name), $names_for_duplicate_checking))
				$names_for_duplicate_checking[strlen($name)][] = crc32($name);
			else
				$names_for_duplicate_checking[strlen($name)] = array(crc32($name));
				
		}
	}
	
	return $names;
}

if(!empty($_REQUEST['film_title']))
{
	$po = mysql_connect2("poster-server", "listing", "system");
	ob_implicit_flush();
	function go($title)
	{
		$r = mysql_query3("select `eBay Description`, `Year/NSS`, autoID from listing_system.descriptions where TITLE = \"$title\"");
		
		$row = mysql_fetch_assoc($r);
		
		if(preg_match("/^(?:18|19|20)[0-9]{2}/", $row['Year/NSS']))
		{
			echo add_star_links($row['eBay Description'], $match[0], null, null, $row['autoID']);
		}
		else
		{
			add_star_links($row['eBay Description'], null);
		}
	}

	?>
	<script type='text/javascript'>
	function nw2(u){
		w = window
		if(w.nw && !w.nw.closed)
			w.nw.location = u
		else
			w.nw = w.open(u, '', 'width=800,location=0,status=0,toolbar=0,menubar=0');
		
		return false //For onclick event, so link is not opened twice
	}
	</script>
	<?

	go($_REQUEST['film_title']);
}
?>
