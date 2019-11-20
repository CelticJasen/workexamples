<?PHP
/*
	This rather large file replaces much of the listing job by using available data
	from the descriptions, AHEAD_Style_info, tbl_types, and auction table to generate
	text for the most commonly used paragraphs and fields.
	
	This file provides the function <auto_list>.
	
	Written by Aaron Kennedy, kennedy@postpro.net
	
	Oh man, dude, you're going to have a fun time debugging this heap of spaghetti. Sorry about that :/ AK 2015-08-24
*/
//require_once("includes.inc.php");
require_once("parse_pressbook_extra_info.inc.php");
require_once("/webroot/includes/string.inc.php");
require_once("/webroot/includes/style_info_tidy.inc.php");
require_once("Templates.inc.php");

function in_multiarray($elem, $array)
{
	foreach($array as $val)
	{
		if($val === $elem)
			return true;
		elseif(is_array($val) && in_multiarray($elem, $val))
			return true;
	}
	
    return false;
}

function get_size($measurements)
{
	//Remove quotes
	$measurements = str_replace(Array("\"", "'"), "", $measurements);
	
	//Convert fractions
	$fractions = Array(" 1/2", " 1/4", " 3/4", " 1/8", " 3/8", " 5/8", " 7/8");
	$decimals  = Array(".5",  ".25", ".75", ".125", ".375",".625",".875");
	$measurements = str_replace($fractions, $decimals, $measurements);
	
	//Remove spaces
	//$measurements = str_replace(" ", "", $measurements);
	
	//Round numbers
	preg_match_all("/[\d]+(\.[\d]+|)/", $measurements, $matches);
	
	if(count($matches[0]) == 2)
		return Array($matches[0][0], $matches[0][1]);
	else
		return false;
}

function max_size($measurements, $x, $y)
{
	if(false === $size = get_size($measurements))
		return false;
	
	return ($size[0] <= $x && $size[1] <= $y or $size[0] <= $y && $size[1] <= $x);
}

$how_rolled_type_long = Array(
	"UF" => "Unfolded",
	"FF" =>  "Folded",
	"TF" =>"Tri-Folded",
	"linen" => "Linenbacked",
	"pbacked" => "Paperbacked",
	"laminated" => "Laminated",
	"1F" => "Folded",
	"encap" => "Encapsulated",
	"WTF" => "Unfolded",
);

$how_rolled_after_description = Array(
"UF" => "", //"that this poster was never folded! An unfolded poster is almost always far more difficult to find than a folded poster of the same title, and finding unfolded posters in excellent condition is even more difficult!",
"TF" => "that this poster was tri-folded only (which means there is no vertical machine fold) but because it would be more expensive to send flat, it will be sent rolled in a tube.",
"pbacked" => "", //Removed because Phil said it shouldn't be there. AK 2016-02-11 //"that this poster has been paperbacked. What is paperbacking? This means the poster was backed onto a light paper backing (acid-free), that is similar in feel to that of the original poster (it means that the poster must be handled carefully, as the backing does not give it much added strength, but it is similar to having an unrestored poster, and yet it has been properly preserved). It is a similar process to linenbacking, except that most collectors use linenbacking for one-sheets and paperbacking for half-sheets, inserts, window cards.",
"1F" => "that this poster was folded across the middle at one time but has been laying flat for a long time and will be sent rolled in a tube.",
"WTF" => "that this poster was somewhat tri-folded. What does that mean? Some posters were stored in a tri-folded fashion, but where someone took a group of posters and then tri-folded the entire group. This means that many of the posters have only a slight \"waviness\" one third of the way from the top and the bottom of the poster, but they are not actual folds. They are normally not very noticeable at all from the front of the poster, but they can be seen from the back of the poster, and they mean that the poster does not lay 100% flat. These \"waves\" greatly diminish if the poster is put under weight for a time, and become almost completely invisible. Most collectors consider them a very minor defect, much less of a defect than actual foldlines. Also note that this poster will be shipped rolled in a tube.",
"lenticular" =>  "that this is a lenticular poster. First made on rare occasions in the 1960s, lenticular posters are posters that have a \"3-dimensional\" look to them, and when you look at them from a slight angle, they look one way, and then when you look at them from a different angle, they look entirely different! It is said that not that many of each lenticular poster was made, likely due to the added cost of production. They can be easily damaged, and cannot be rolled tightly, as can a regular printed poster. We have provided two images of this poster, both shot at angles, to allow you to see what \"both\" images look like, but please remember that you are bidding on a single poster, NOT two posters!"
);

$how_rolled_after_description_multiple = Array(
"UF" => "", //"that these posters were never folded! An unfolded poster is almost always far more difficult to find than a folded poster of the same title, and finding unfolded posters in excellent condition is even more difficult!",
"TF" => "that these posters were tri-folded only (which means there is no vertical machine fold) but because it would be more expensive to send them flat, they will be sent rolled in a tube.",
"pbacked" => "", //Removed because Phil said it shouldn't be there. AK 2016-02-11 //"that these posters have been paperbacked. What is paperbacking? This means the poster was backed onto a light paper backing (acid-free), that is similar in feel to that of the original poster (it means that the poster must be handled carefully, as the backing does not give it much added strength, but it is similar to having an unrestored poster, and yet it has been properly preserved). It is a similar process to linenbacking, except that most collectors use linenbacking for one-sheets and paperbacking for half-sheets, inserts, window cards.",
"1F" => "that these posters were folded across the middle at one time but have been laying flat for a long time and will be sent rolled in a tube.",
"WTF" => "that these posters were somewhat tri-folded. What does that mean? Some posters were stored in a tri-folded fashion, but where someone took a group of posters and then tri-folded the entire group.  This means that many of the posters have only a slight \"waviness\" one third of the way from the top and the bottom of the poster, but they are not actual folds. They are normally not very noticeable at all from the front of the poster, but they can be seen from the back of the poster, and they mean that the poster does not lay 100% flat. These \"waves\" greatly diminish if the poster is put under weight for a time, and become almost completely invisible. Most collectors consider them a very minor defect, much less of a defect than actual foldlines. Also note that these posters will be shipped rolled in a tube.",
"lenticular" => "****adapt lenticular paragraph for multiple items****"
);

$rolled_auctions = Array(
"LGROLLED", "UF_NON", "UF_SPEC", "UF27x40", "UF27x41", "UFLARGE", "INS_HALF", "UF1SH", 
);

$type_long_codes = Array(
"[quantity]", "[Original]", "[Theatrical]", "[how_rolled]", "[style_info]", "[1sh]", "[measurements]", "[pages]", "[presskit]", "[number]",
"[quantityAn]", "[s]", "[plobbies]", "[notS]", "[numbered]", "[TitleCard]", "[11x14_LC]", "[color]", "[Movie]", "[Vintage]", "[quantityA]", 
"[FirstReleaseYear]", "[Soundtrack]", "[quantity1]", "[bookCover]", "[nationality]", "[export]", "[MuseumArt]", "[War]", "[optionalPages]",
"[artPrintReissue]", "[yies]", "[how_rolled_backed_only]", "[additionaldetails]",
);

//Really long line, sorry. It's not like you want to read this anyway.																																																																																																																																																																																																																	 Why are you still reading this?																																																						It's not all that useful to read anyway, it's just blank information.																																						Hey, man, this behavior is getting out of line. There's not even any more info to see!																																																																																	a link, just for you, since you've come this far: http://i-am-bored.com/																																																						*beyond here there be dragons*
$blank_style = '{"bag":"no","bag1":"","numbered":"","num":"0","number":"0","numbered1":"number","num1":null,"number1":null,"international":"0","intlreason":"","intlotherreason":"","domestic":"0","domreason":"","stamped":"0","stampedreason":"","alternatestyle":"0","alternatestyleinput":"","awards":"0","benton":"0","reviews":"0","military":"0","italus":"0","otherco":"0","spanus":"0","stockposter":"0","stockposterinput":"","video":"0","styleothercheck":"0","styleotherinput":"","stills":"0","supplements":"0","slides":"0","extra":"0","pages":"0","heralds":"0","other":null,"colorcover":"0","style":"N\/A","styleother":"","doublesided":"ss","printedinitaly":"0","printer":"","heavystock":"0","reissueyear":"","advance":"no","advancetagline":"","incomplete":"0","pieces":"0","countryoforigin":"0","coproduction":"0","firstrelease":"0","chapternumber":"0","chaptertitle":"","retitled":"","unreadable":"0","signedby":"","snipetype":"","candid":"0","color":"","colortype":"regular","colorother":"","papertype":"regular","otherpaper":"","additionaldetails":"","dupe":"0","quantity":"0","quantity1":"1","quantity2":"1","stillmeasurements":"","printedinitaly2":"0","signedmessage":null,"backed":null,"otherbacked":null,"militaryreason":null, "cut" : null}';

/*
Function: auto_list
Takes the database rows from the auction table, tbl_types, descriptions, and info_notes tables
and returns listing paragraphs and fields suitable for the review form.
*/
function auto_list($auctionRow, $tblTypesRow, $descriptionsRow, $infoNotesRow)
{
	global $user, $how_rolled_type_long, $how_rolled_after_description, $how_rolled_after_description_multiple, $rolled_auctions, $type_long_codes, $blank_style, $table;
	
	$output = Array();
	$type_long = $tblTypesRow['type_long_code'];
	$type_as_whole_word = $tblTypesRow['type_as_whole_word'];
	$poster_name = $tblTypesRow['poster'];
	$poster_singular = $tblTypesRow['poster_singular'];
	$poster_plural = $tblTypesRow['poster_plural'];
	$poster_simple_name = $tblTypesRow['poster_simple'];
	$standard_measurement = $tblTypesRow['measurements'];	
	$how_rolled = $how_rolled_type_long[$auctionRow['how_rolled']];
	$nonStandardMeasurement = false;
	$messages = "";
	
	//Get our style info record. If it doesn't exist, make up a blank no-info record
	if($auctionRow['style_id'] != "")
	{
		$result = mysql_query3("SELECT AHEAD_Style_Info.*, nationalities.country FROM AHEAD_Style_Info ".
				"left join nationalities on nationality = adjective ".
				"WHERE id = $auctionRow[style_id] ".
				"group by id");
		
		if(mysql_num_rows($result) != 1)
			$styleInfoRow = json_decode($blank_style, true);
		else
			$styleInfoRow = mysql_fetch_assoc($result);
	}
	else
		$styleInfoRow = json_decode($blank_style, true);
	
	if(preg_match("/^[* ]*$/", $tblTypesRow['demonym']) || $tblTypesRow['demonym'] == "U.S.")
	{
		//If a U.S. type, we allow the describers to enter a custom nationality.
		//When unset it defauts to U.S.
		
		$nationality = $styleInfoRow['nationality'];
		$country = $styleInfoRow['country'];
	}
	else
	{
		//If not U.S. type, describers are not allowed to use a custom nationality.
		//Grab the default nationality and country from the types table.
		
		$nationality = $tblTypesRow['demonym'];
		$country = $tblTypesRow['country'];
	}
	
	if(empty($country))
		$country = "the U.S.";
	
	
	//Modifications to standard_measurement
	if($styleInfoRow['keybook'] == 1 && $standard_measurement == '8" x 10"')
		$standard_measurement = '8" x 11"';
		
	if($styleInfoRow['newsstill'] == 1 && $poster_simple_name == "still")
		$poster_singular = "news photo";
	
	
	
	$trimmed = (stristr($auctionRow['measurements'], 'trimmed'));
  
  if(($auctionRow['type_code'] == "movie promo item" || $auctionRow['type_code'] == "Miscellaneous" || $auctionRow['type_code'] == "Action Figure") && strpos($auctionRow['measurements'], " to ")===false){
    preg_match_all("/[\d\/ ]+\" x? ?[\d\/ ]+\" x? ?[\d\/ ]+\"/", $auctionRow['measurements'], $matches);
    if(empty($matches[0])){
      preg_match_all("/[\d\/ ]+\" x [\d\/ ]+\"/", $auctionRow['measurements'], $matches);
    }
  }else{
    preg_match_all("/[\d\/ ]+\" x [\d\/ ]+\"/", $auctionRow['measurements'], $matches);
  }
	if(count($matches[0]) == 1)
	{
		$measurements = trim($matches[0][0]);
		$specifiedMeasurement = true;
		if($matches[0][0] != $standard_measurement);
			$nonStandardMeasurement = true;
	}
	elseif(count($matches[0]) > 1)
	{
		$measurements = $auctionRow['measurements']; // Changed from "*" 20150714 Aaron
	}
	elseif($auctionRow['auction_code'] == "UF27x40")
	{
		$measurements = '27" x 40"';
	}
	else
	{
		$measurements = $standard_measurement;
	}
	
	//Get our centimeter value for measurements
	list($measurementsWithMetric, $measurementsWithMetricForLong) = add_metric2($measurements);
	
	//Type Long
	//Special case for 8x10s
	if($auctionRow['type_code'] == "8x10" || $auctionRow['type_code'] == "11x14")
	{
		if($styleInfoRow['minilc'] == 1)
		{
			$type_long = "[quantityAn] [Original] Vintage [Theatrical] [style_info] ".
				"Mini Lobby Card[s] [number] (8x10 LC[s]; measures [measurements])";
		}
		elseif($styleInfoRow['keybook'] == 1)
		{
			//2012-07-11: Gary asked me to remove Deluxe from keybook stills because it only happens 50% of the time
			$type_long = "[quantityAn] [Original] Vintage [Theatrical] [style_info] Movie [measurements] Key Book Still[s]";
		}
		elseif($styleInfoRow['newsstill'] == 1)
		{
			$type_long = "[quantityAn] [Original] Vintage [style_info] [measurements] News Photo[s]";
		}
	}
	elseif(stristr($auctionRow['style_info'], "kilian") !== false)
	{
		$type_long = str_replace(array("[Vintage]", "[Theatrical]"), "", $type_long);
		$type_long = str_replace("[nationality]", "Kilian Enterprises", $type_long);
	}
	
	
	if($auctionRow['type_code'] == "index card, signed")
	{
		//Gary asked me to add this "repro" behavior. AK, 2013-01-31
		if(stristr($auctionRow['style_info'], "repro"))
		{
			switch($styleInfoRow['included_still'])
			{
				case "b/w":
					$type_long .= ' with REPRODUCTION 8" x 10" Movie Still';
					break;
				case "color":
					$type_long .= ' with REPRODUCTION color 8" x 10" Movie Still';
					break;
			};
		}
		else
		{
			switch($styleInfoRow['included_still'])
			{
				case "b/w":
					$type_long .= ' with 8" x 10" Movie Still';
					break;
				case "color":
					$type_long .= ' with color 8" x 10" Movie Still';
					break;
			};
		}
	}
	
	//Is this item a signed repro 8x10?
	$item_is_signed_repro_8x10 = (stripos($styleInfoRow['additionaldetails'], "repro") !== false 
							&& strpos($auctionRow['type_code'], "8x10") !== false
							&& $styleInfoRow['signedby'] != "");
	
	//Is this item a repro?
	$item_is_repro = stripos($styleInfoRow['additionaldetails'], "repro") !== false;
	
	//[Original]
	if($auctionRow['type_code'] == "special poster" && $descriptionsRow['type_of_record'] != "movie")
	{
		$original = "";
	}
	elseif($item_is_signed_repro_8x10 || $styleInfoRow['s2_recreation'] > 0)
	{
		$original = "";
	}
	elseif(preg_match("/^(\d){4}$/", $styleInfoRow['reissueyear'])) 		//If the reissue is just a year
	{
		$reissue_is_a_year = true;
		$original = "$styleInfoRow[reissueyear] Re-Release";
	}
	elseif(preg_match("/^[\d]{4}s$/", $styleInfoRow['reissueyear'])) 	//If the reissue is a year with an "s" (1960s)
	{
		$original = "Undated (probably $styleInfoRow[reissueyear]) Re-Release";
	}
	elseif($styleInfoRow['firstrelease'] != 0)
	{
		$original = "Original $styleInfoRow[firstrelease]";
		
		if($tblTypesRow['country'] != "the U.S.")
		 $original .= " (from the first release of this movie in $tblTypesRow[country])";
	}
	elseif(stristr($auctionRow['style_info'], "kilian") !== false)
	{
		$original = "";
	}
	else
	{
		$original = "Original";
	}
	
	
	
	$original .= ($styleInfoRow['retitled'] != "" && $styleInfoRow['reissueyear'] != "") ? " (re-titled \"$styleInfoRow[retitled]\" for this re-release) " : "";
	
	//[Theatrical]
	$theater_used = (!$styleInfoRow['video'] && 
		!stristr($styleInfoRow['additionaldetails'], "T.V.") &&
		!stristr($styleInfoRow['additionaldetails'], "TV") &&
		!$item_is_repro && 
		!stristr($styleInfoRow['additionaldetails'], "publicity") &&
		!$styleInfoRow['s2_recreation'] > 0 && 
		$descriptionsRow['type_of_record'] != "other") ? "Theatrical" : "";
	
	$style_info = "";
	
	if(preg_match("/\b((19|20)[0-9]{2}s?)\b/i", $auctionRow['listing_notes'], $match) && 
		$auctionRow['type_code'] == "commercial poster")
	{
		$style_info .= $match[1]." ";
	}
	
	//[how_hinfo .= ($styleInfoRow['otherco'] == 1) ? "Other Company " : "";
	$style_info .= ($styleInfoRow['s2_recreation'] ? "S2 Re-Creation " : "");
	$style_info .= ($styleInfoRow['domestic'] == 1) ? "Domestic " : "";
	$style_info .= ($styleInfoRow['international'] == 1) ? "International " : "";
	$style_info .= ($styleInfoRow['military'] == 1) ? "Military " : "";
	switch($styleInfoRow['doublesided'])
	{
		case "ss":
			if(intval(substr($descriptionsRow["Year/NSS"], 0, 4)) > 1989 && stristr("1sh", $auctionRow['type_code']))
				$style_info .= "Single-Sided ";
			break;
		case "ds":
			$style_info .= "Double-Sided ";
			break;
	};
	
	$style_info .= ($styleInfoRow['chapternumber'] != 0)
					? ("Chapter #$styleInfoRow[chapternumber] " . (($styleInfoRow['chaptertitle'] != "") ? "($styleInfoRow[chaptertitle]) " : "")) : "";
	
	// If it's a signed repro 8x10, Autographed goes at the beginning of type_long
	#if(!$item_is_signed_repro_8x10)
	
	if(stristr($type_long, "autographed") === false)
		$style_info .= ($styleInfoRow['signedby'] != "") 	? "Autographed " : "";
	
		
	/*
	//2012-10-10: I removed this behavior because Dan & Gary said they have to undo this behavior 90% of the time. AK
	if($styleInfoRow['alternatestyleinput'] != "")
	{
		$style_info .= $styleInfoRow['alternatestyleinput']." ";
		
		if($styleInfoRow['style'] == "N/A")
			$style_info .= "Style ";
	}
	*/
	
	if($styleInfoRow['style'] != "N/A")
	{
		if($styleInfoRow['style'] == "other")
			$style_info .= "Style " . $styleInfoRow['styleother'] . " ";
		else{
		  if(stristr("1sh", $auctionRow['type_code']) && strcasecmp($styleInfoRow['style'],"Style A")==0){
		    //2019-01-03 Don't add Style A to 1sh of any kind - see email: STEVEN BRUCE REQUEST Fwd: Re: Review Form
		    //Mailer::mail('steven@emovieposter.com','1sh with Style A caught',print_r($styleInfoRow,true)."\n\n".$style_info."\n\n".$auctionRow);
		  }else{
        $style_info .= strtoupper(substr($styleInfoRow['style'],0,1)) . substr($styleInfoRow['style'],1) . " ";
      }
    }
	}
	
	$style_info .= ($styleInfoRow['reviews'] == 1)		? "Reviews " : "";
	$style_info .= ($styleInfoRow['benton'] == 1)		? "Benton " : "";
	$style_info .= ($styleInfoRow['papertype'] == "deluxe" || ($auctionRow['type_code'] == "jumbo stills" && $styleInfoRow['color'] == "color")) ? "Deluxe " : "";
	$style_info .= ($styleInfoRow['color'] == "color" && in_array($auctionRow['type_code'], Array("8x10", "11x14", "jumbo stills")) && $styleInfoRow['minilc'] != 1) ? "Color " : "";
	switch($styleInfoRow['advance'])
	{
		case "teaser":
			$style_info .= "Teaser ";
			break;
		case "advance":
		case "advance/teaser":
			$style_info .= "Advance ";
			break;
		default:
			break;
	};
	$style_info .= ($styleInfoRow['video'] == 1)			? "Video Rental Store " : "";
	$style_info .= ($auctionRow['how_rolled'] == "lenticular" ? "Lenticular " : "");

	//Change made per Gary "Re: AH Fix: SpanUS" 2016-09-07
	if(false) //$styleInfoRow['language'] != "" && $tblTypesRow['demonym'] == "U.S.")
	{
		$style_info .= $styleInfoRow['language']."/US ";
	}

	//[1sh]
	$onesheet = "1sh";
	
	if($styleInfoRow['international'] && in_array($styleInfoRow['intlreason'], array("Spanish", "French")))
	{
		$onesheet = "1sh; printed for use in $styleInfoRow[intlreason]-speaking countries";
	}
	elseif($styleInfoRow['spanus'] == 1) // || $styleInfoRow['language'] == "Spanish")
	{
		$onesheet = "Spanish/US 1sh; printed in the U.S. for use with Spanish speaking audiences";
	}
	else if($styleInfoRow['italus'] == 1)
		$style_Info .= "Italian/US ";
	
	//Eng/Ital
	if((stristr($styleInfoRow['additionaldetails'], "eng/ital") && $auctionRow['type_code'] != "Italian export Eng") || 
		($styleInfoRow['language'] == "English" && $styleInfoRow['country'] == "Italy"))
	{
		$split = explode(";", $type_long);
		$type_long = implode(";", array_merge(Array($split[0]), Array(" printed in English in Italy for use in English-speaking countries"), array_diff($split, Array($split[0]))));
	}
	
	//TV
	if(stripos($styleInfoRow['additionaldetails'], "repro") === false &&
		(stristr($styleInfoRow['additionaldetails'], "TV") || stristr($styleInfoRow['additionaldetails'], "T.V.") || stripos($auctionRow['film_title'], "(TV)") !== false))
		$type_long = str_replace(Array("[Movie]", "Movie"), "Television", $type_long);
	
	//Publicity still
	if($poster_simple_name == "still")
	{
		if(stristr($auctionRow['style_info'], "music"))
		{
			$type_long = str_replace(Array("[Movie]", "Movie"), "Music Publicity", $type_long);
			$type_long = str_replace(array("[Original]", "[Vintage]", "[Theatrical]", "Original", "Vintage", "Theatrical"), "", $type_long);
		}
		elseif(stristr($auctionRow['style_info'], "radio"))
		{
			$type_long = str_replace(Array("[Movie]", "Movie"), "Radio Publicity", $type_long);
			$type_long = str_replace(array("[Original]", "[Vintage]", "[Theatrical]", "Original", "Vintage", "Theatrical"), "", $type_long);
		}
		elseif(stristr($auctionRow['style_info'], "publicity"))
		{
			$type_long = str_replace(Array("[Movie]", "Movie"), "Publicity", $type_long);
			$type_long = str_replace(array("[Original]", "[Vintage]", "[Theatrical]", "Original", "Vintage", "Theatrical"), "", $type_long);
		}
	}
	
	if(preg_match("/(^| |;) *stage( |;|$)/", $styleInfoRow['additionaldetails']) || 
	 	preg_match("/(^| |;) *stage( |;|$)/", $auctionRow['style_info']))
		$type_long = str_replace(Array("[Movie]", "Movie"), "Stage Play", $type_long);
	
	if($auctionRow['type_code'] == "special poster")
	{
		$type_long = str_replace(array("[Theatrical]", "Theatrical"), "", $type_long);
		
		if($descriptionsRow['type_of_record'] != "movie")
			$type_long = str_replace(array("[Movie]", "Movie"), "", $type_long);
	}
	elseif(preg_match("/(^|;) *museum/", $auctionRow['style_info']))
	{
		$type_long = str_replace(array("[Theatrical]", "Theatrical"), "", $type_long);
		$type_long = str_replace(array("[Movie]", "Movie"), "Museum", $type_long);
	}
	elseif(preg_match("/(^|;) *commercial/", $auctionRow['style_info']))
	{
		$type_long = str_replace(array("[Theatrical]", "Theatrical"), "", $type_long);
		$type_long = str_replace(array("[Movie]", "Movie"), "Commercial", $type_long);
	}
	elseif(preg_match("/(^|;) *(art )?exhibition/", $auctionRow['style_info']))
	{
		$type_long = str_replace(array("[Theatrical]", "Theatrical"), "", $type_long);
		$type_long = str_replace(array("[Movie]", "Movie"), "Art Exhibition", $type_long);
	}
	
	if(stristr($auctionRow['type_code'], "polish") !== false || stristr($auctionRow['type_code'], "japan") !== false)
	{
		if(preg_match("/(^|;) *special/", $auctionRow['style_info']))
		{
			$type_long = str_replace(array("[Theatrical]", "Theatrical"), "", $type_long);
			$type_long = str_replace(array("[Movie]", "Movie"), "Special", $type_long);
		}
	}
	
	if(stristr($auctionRow['type_code'], "polish") !== false)
	{
		if(stristr($auctionRow['style_info'], "circus") !== false)
		{
			$type_long = str_replace(array("[Movie]", "Movie"), "Circus", $type_long);
			$type_long = str_replace(array("[Theatrical]", "Theatrical"), "", $type_long);
		}
		elseif(stristr($auctionRow['style_info'], "music") !== false)
		{
			$type_long = str_replace(array("[Movie]", "Movie"), "Music", $type_long);
			$type_long = str_replace(array("[Theatrical]", "Theatrical"), "", $type_long);
		}
		elseif(stristr($auctionRow['style_info'], "museum") !== false)
		{
			$type_long = str_replace(array("[Movie]", "Movie"), "Museum Exhibition", $type_long);
			$type_long = str_replace(array("[Theatrical]", "Theatrical"), "", $type_long);
		}
		elseif(stristr($auctionRow['style_info'], "advertising") !== false)
		{
			$type_long = str_replace(array("[Movie]", "Movie"), "Advertising", $type_long);
			$type_long = str_replace(array("[Theatrical]", "Theatrical"), "", $type_long);
		}
		elseif(stristr($auctionRow['style_info'], "film festival") !== false)
		{
			//Just here to avoid the "festival" behavior below.
		}
		elseif(stristr($auctionRow['style_info'], "festival") !== false)
		{
			$type_long = str_replace(array("[Movie]", "Movie"), "Festival", $type_long);
			$type_long = str_replace(array("[Theatrical]", "Theatrical"), "", $type_long);
		}
	}
		
	if($styleInfoRow['advertising'] == 1)
		$type_long = str_replace(Array("[Movie]", "Movie"), "Advertising", $type_long);
		
	if($descriptionsRow['type_of_record'] == "other")
		$type_long = str_replace(Array("[Movie]", "Movie"), "", $type_long);
	
	//Kraftback
	if(stristr($styleInfoRow['additionaldetails'], "kraftback") ||
		$styleInfoRow['kraftbacked'] == 1)
	{
		$pos = strpos($type_long, "[Theatrical]");
		if($pos !== false)
		{
			$beginning = substr($type_long, 0, $pos+12);
			$end = substr($type_long, $pos+12);
			$type_long = "$beginning Kraftbacked$end";
		}
	}
	
	//Two sided
	if($styleInfoRow['doublesided'] == "twosided" && !stristr($tblTypesRow['type_long_code'], "two-sided"))
	{
		$pos = strpos($type_long, "[how_rolled]");
		if($pos !== false)
		{
			$beginning = substr($type_long, 0, $pos+12);
			$end = substr($type_long, $pos+12);
			$type_long = "$beginning Two-Sided$end";
		}
	}
	
	# This is legacy code for old LC type codes only
	if($auctionRow['type_code'] != "LC" && ($styleInfoRow['numbered'] == "TC" || $styleInfoRow['numbered1'] == "TC") && $auctionRow['type_code'] != "LC, TC")
	{
		$pos = strpos($type_long, "[style_info] Movie");
		if($pos !== false)
		{
			$beginning = substr($type_long, 0, $pos+18);
			$end = substr($type_long, $pos+18);
			$type_long = "$beginning Title$end";
		}
		
		$pos = strpos($type_long, "LC");
		if($pos !== false)
		{
			$beginning = substr($type_long, 0, $pos+2);
			$end = substr($type_long, $pos+2);
			$type_long = "$beginning TC$end";
		}
	}
	
	//Art print reissue per Todd 2016-12-13 AK
	if($auctionRow['type_code'] == "art print" and !empty($styleInfoRow['reissueyear']))
	{
		$type_long = str_replace("[artPrintReissue]", "made for the $styleInfoRow[reissueyear] re-release of this movie; ", $type_long);	
	}

	//Replace type_long codes with their correct values
	foreach($type_long_codes as $code)
	{
		switch($code)
		{
		  case "[additionaldetails]":
        $type_long = str_replace($code, ucwords(strtolower($styleInfoRow['additionaldetails'])), $type_long);
        break;
			case "[quantity]":
				$type_long = str_replace("[quantity]", (($styleInfoRow['quantity'] != 0) ? $styleInfoRow['quantity'] : "*"), $type_long);
				break;
			case "[quantity1]":
				$type_long = str_replace("[quantity1]", (($styleInfoRow['quantity'] != 0) ? $styleInfoRow['quantity'] : "1"), $type_long);
				break;
			case "[Original]":
				$type_long = str_replace("[Original]", $original, $type_long);
				break;
			case "[Theatrical]":
				$type_long = str_replace("[Theatrical]", $theater_used, $type_long);
				break;
			case "[how_rolled]":
				$type_long = str_replace("[how_rolled]", $how_rolled, $type_long);
				break;
      case "[how_rolled_backed_only]":
        $type_long = str_replace("[how_rolled_backed_only]", (in_array($auctionRow['how_rolled'], Array("linen", "pbacked")) ? $how_rolled : ""), $type_long);
        break;
			case "[style_info]":
				$type_long = str_replace("[style_info]", $style_info, $type_long);
				break;
			case "[1sh]":
				$type_long = str_replace("[1sh]", $onesheet, $type_long);
				break;
			case "[measurements]":
				if(stristr($auctionRow['type_code'], "herald")!== false && in_array($auctionRow['how_rolled'], Array("1F", "FF", "TF")))
					$type_long = str_replace("[measurements]", $measurementsWithMetricForLong." when folded", $type_long);
        elseif(stristr($auctionRow['type_code'], "t-shirt")!== false )
          $type_long = str_replace("[measurements]", $auctionRow['measurements'], $type_long);
				else		
					$type_long = str_replace("[measurements]", $measurementsWithMetricForLong, $type_long);
				break;
			case "[color]":
				if($styleInfoRow['minilc'] == 1)
				{
					if($styleInfoRow['color'] == "color")
						$type_long = str_replace("[color]", "Color", $type_long);
					else
						$type_long = str_replace("[color]", "", $type_long);
				}
				break;
			case "[pages]":
				if($auctionRow['type_code'] == "German program" && $styleInfoRow['pages'] == 0)
					$styleInfoRow['pages'] = 4;
				elseif($auctionRow['type_code'] == "Austrian program" && $styleInfoRow['pages'] == 0)
					$styleInfoRow['pages'] = 8;
				
				
				$type_long = str_replace("[pages]", 
					(($styleInfoRow['pages'] != 0) ? 
						(($styleInfoRow['pages_approximate'] == 1 || 
						preg_match("/app[a-z]*\.? [0-9]+/i", 
						$auctionRow['style_info'])) ? "approximately " : "") . 
						$styleInfoRow['pages'] : "*"), 
					$type_long);
					
				//Fix English fail. AK 2013-03-20
				if($styleInfoRow['pages'] == 1)
					$type_long = str_replace("1 pages", "1 page", $type_long);
				
				break;
			
			case "[optionalPages]":
				if($styleInfoRow['pages'])
				{
					if($styleInfoRow['pages_approximate'] == 1 || preg_match("/app[a-z]*\.? [0-9]+/i", $auctionRow['style_info']))
					{
						$pages = "approximately ".$styleInfoRow['pages']." ";
					}
					else
					{
						$pages = $styleInfoRow['pages']." ";
					}
					
					if($styleInfoRow['pages'] == 1)
					{
						$pages .= "page";
					}
					else
					{
						$pages .= "pages";
					}
					
					$type_long = str_replace("[optionalPages]", $pages, $type_long);
				}
				else
				{
					$type_long = str_replace("[optionalPages]", "", $type_long);
				}
			
				break;

			case "[presskit]":
				$presskit_items = Array();
				
				if($styleInfoRow['supplements'] > 0)
					$presskit_items[] = "$styleInfoRow[supplements] supplement".($styleInfoRow['supplements'] > 1 ? "s" : "");
				if($styleInfoRow['stills'] > 0)
					$presskit_items[] = "$styleInfoRow[stills] still".($styleInfoRow['stills'] > 1 ? "s" : "");
				if($styleInfoRow['slides'] > 0)
					$presskit_items[] = "$styleInfoRow[slides] 35mm slide".($styleInfoRow['slides'] > 1 ? "s" : "");
				if(preg_match("/([1-9][0-9]*) *CDs?/", $styleInfoRow['additionaldetails'] . " ". $auctionRow['style_info'], $matches))
					$presskit_items[] = "$matches[1] CD-ROM".($matches[1] > 1 ? "s" : "");
				
				$pk = "contains ";
				for($x = 0; $x < count($presskit_items)-1; $x++)
					$pk .= "$presskit_items[$x], ";
				$pk .= ((count($presskit_items) > 1) ? " and " : "").end($presskit_items);
				
				
				/*
				if($u)
				{
					$pk = "contains $u " . (($u==1) ? "supplement" : "supplements");
					if($t && $l)
						$pk .= ", $t ".(($t==1) ? "still" : "stills").", and $l 35mm ".(($l==1) ? "slide" : "slides");
					else if($t)
						$pk .= ", and $t ".(($t==1) ? "still" : "stills");
					else if($l)
						$k .= ", and $l 35mm ".(($l==1) ? "slide" : "slides");
				}
				else if($t)
				{
					$pk = "contains $t ".(($t==1) ? "still" : "stills");
					if($l)
						$pk .= ", and $l 35mm ".(($l==1) ? "slide" : "slides");
				}
				else if($l)
					$pk = "contains $l 35mm ".(($l==1) ? "slide" : "slides");
				*/
				
				$type_long = str_replace("[presskit]", $pk, $type_long);
				break;
			
			case "[number]":
				$matches = Array();
				if($styleInfoRow['number'] != "" && $styleInfoRow['number'] != 0 && $styleInfoRow['numbered'] != "TC")
					$type_long = str_replace("[number]", "#" . $styleInfoRow['number'], $type_long);
				elseif(preg_match("/#[\d]{1}/", $auctionRow['style_info'], $matches) && $styleInfoRow['numbered'] != "TC")
					$type_long = str_replace("[number]", "#" . $matches[0][1], $type_long);
				else
					$type_long = str_replace("[number]", "", $type_long);
				break;
			case "[quantityA]":
				if($styleInfoRow['quantity'] > 1)
					$type_long = str_replace($code, $styleInfoRow['quantity'], $type_long);
				else
					$type_long = str_replace($code, "A", $type_long);
				break;
				break;
			case "[s]":
				if($styleInfoRow['quantity'] > 1)
					$type_long = str_replace($code, "s", $type_long);
				else
					$type_long = str_replace($code, "", $type_long);
				break;
			case "[notS]":
				if($styleInfoRow['quantity'] > 1)
					$type_long = str_replace($code, "", $type_long);
				else
					$type_long = str_replace($code, "s", $type_long);
				break;
			case "[plobbies]":
					if($styleInfoRow['quantity'] > 1)
						$type_long = str_replace($code, "Photolobbies", $type_long);
					else
						$type_long = str_replace($code, "Photolobby", $type_long);
				break;
			case "[numbered]":
				if($styleInfoRow['number'] != 0 || $styleInfoRow['numbered'] == "number")
					$type_long = str_replace($code, "Numbered", $type_long);
				else
					$type_long = str_replace($code, "", $type_long);
				break;
			case "[TitleCard]":
				if($styleInfoRow['numbered'] == "TC")
					$type_long = str_replace($code, "Title", $type_long);
				else
					$type_long = str_replace($code, "", $type_long);
				break;
			case "[11x14_LC]":
				if($styleInfoRow['numbered'] == "TC")
					$type_long = str_replace($code, "LC TC", $type_long);
				else
					$type_long = str_replace($code, "LC", $type_long);
				break;
			case "[Movie]":
				if($item_is_signed_repro_8x10)
					$type_long = str_replace($code, "REPRODUCTION", $type_long);
				else
					$type_long = str_replace($code, "Movie", $type_long);
				break;
			case "[Vintage]":
				if($item_is_signed_repro_8x10 || $styleInfoRow['s2_recreation'] > 0 || 
					($auctionRow['type_code'] == "special poster" && $descriptionsRow['type_of_record'] != "movie") || 
					!empty($styleInfoRow['reissueyear']))
					$type_long = str_replace($code, "", $type_long);
				else
					$type_long = str_replace($code, "Vintage", $type_long);
				break;
			case "[FirstReleaseYear]":
				$first_release_year = ($styleInfoRow['firstrelease'] == "") ? substr($descriptionsRow['Year/NSS'], 0, 4) : $styleInfoRow['firstrelease'];
				$type_long = str_replace($code, $first_release_year, $type_long);	
				break;
			case "[Soundtrack]":
				$type_long = str_replace($code, (($styleInfoRow['cd_type'] == "compilation") ? "Compilation" : "Soundtrack"), $type_long);
				break;
			case "[bookCover]":
				$type_long = str_replace($code, (($styleInfoRow['book_cover'] != "n/a") ? ucwords($styleInfoRow['book_cover']) : ""), $type_long);
				break;
			case "[nationality]":
				$type_long = str_replace($code, $styleInfoRow['nationality'], $type_long);
				break;
			case "[export]":
				if($styleInfoRow['language'] != "")
					$type_long = str_replace($code, "Export (printed for use in $styleInfoRow[language] speaking countries)", $type_long);
				else
					$type_long = str_replace($code, "", $type_long); 
				break;
			case "[MuseumArt]":
				$type_long = str_replace($code, (!empty($styleInfoRow['museum']) ? "Museum" : "Art"), $type_long);
				break;
			case "[War]":
				if(stripos($auctionRow['style_info'], "wwiii") !== false) //The act of a pessimistic programmer
					$type_long = str_replace($code, "World War III", $type_long);
				elseif(stripos($auctionRow['style_info'], "wwii") !== false)
					$type_long = str_replace($code, "World War II", $type_long);
				elseif(stripos($auctionRow['style_info'], "wwi") !== false)
					$type_long = str_replace($code, "World War I", $type_long);
				break;
			case "[yies]":
				if($styleInfoRow['quantity'] > 1)
					$type_long = str_replace($code, "ies", $type_long);
				else
					$type_long = str_replace($code, "y", $type_long);
				break;
			case "[quantityAn]":
				//Do nothing because we do this later
				break;
			default:
				$type_long = str_replace($code, "", $type_long);
				break;
		};
	}
	
	$type_long = String::xtrim($type_long);
	
	$type_long_words = explode(" ", $type_long);
	
	
	if($styleInfoRow['quantity'] > 1)
	{
		if(in_array($auctionRow['auction_code'],array("INS_HALF", "LGROLLED", "UF1SH", "UFLARGE", "UF_NON", "UF_SPEC")) ||
      in_array($auctionRow['type_code'], array("transparency"))
      )
		{
			$type_long = str_replace("[quantityAn]", "A Group Of ".$styleInfoRow['quantity'], $type_long);
		}
		else
		{
			$type_long = str_replace("[quantityAn]", $styleInfoRow['quantity'], $type_long);
		}
	}
	elseif($type_long_words[0] == "[quantityAn]" && 
		in_array(strtolower(substr($type_long_words[1], 0, 1)), array("a", "e", "i", "o", "u")))
	{
		$type_long = str_replace("[quantityAn]", "An", $type_long);
	}
	else
	{
		$type_long = str_replace("[quantityAn]", "A", $type_long);
	}
	
	$pressbook_type_codes = Array("pb", "English pb", "French pb");
	
	//Pressbook with supplement
	$pressbook_info = parse_pressbook_extra_info($auctionRow['style_info']);
	
	//email_error(print_r($pressbook_info, true)."\n\n$auctionRow[style_info]");
	
	if(!empty($pressbook_info['supplement'][0]))
	{
		$type_long .= ". Also included ";
		
		if($pressbook_info['supplement'][0] > 1)
			$type_long .= sprintf("are %s ad supplements that have ", $pressbook_info['supplement'][0]);
		else
			$type_long .= "is an ad supplement that has ";
		
		
		$type_long .= format_pages_for_display($pressbook_info['supplement'][1]);
	}
	
	if(!empty($pressbook_info['herald'][0]))
	{
		if(!empty($pressbook_info['supplement'][0]))
		{
			$type_long .= ", and also ";
			
			if($pressbook_info['herald'][0] > 1)
				$type_long .= sprintf("%s heralds that have ", $pressbook_info['herald'][0]);
			else
				$type_long .= "a herald that has ";
		}
		else
		{
			$type_long .= ". Also included ";
			
			if($pressbook_info['herald'][0] > 1)
				$type_long .= sprintf("are %s heralds that have ", $pressbook_info['herald'][0]);
			else
				$type_long .= "is a herald that has ";
		}
		
		$type_long .= format_pages_for_display($pressbook_info['herald'][1]);
	}

	if(!empty($pressbook_info['herald'][0]) || !empty($pressbook_info['supplement'][0]))
	{
		$type_long .= ".";
	}
	
	/*
	if(in_array($auctionRow['type_code'], $pressbook_type_codes) and preg_match("/[\d]+(?= sup)/i", $auctionRow['style_info'], $matches) and $matches[0][0] > 0)
		$type_long .= ". Also included " . (($matches[0][0] > 1) ? "are " . $matches[0][0] . " ad supplements that have ***" : "is an ad supplement that has ***");
	
	//Pressbook with heralds
	if(in_array($auctionRow['type_code'], $pressbook_type_codes) and preg_match("/[\d]+(?= herald)/i", $auctionRow['style_info'], $matches) and $matches[0][0] > 0)
		$type_long .= ". Also included " . (($matches[0][0] > 1) ? "are " . $matches[0][0] . " heralds that have ***" : "is a herald that has ***");
	*/
	
	//MrLister
	//Gary requested that we disable title autolisting for LCSINGLE and 8x10SINGLE, 
	//now that we have the shift+click minigallery behavior. AK 2017-05-08
	if(!in_array($auctionRow['auction_code'], array("LCSINGLE", "8x10SING")))
	{
		$style_criteria = "";
		
		if($styleInfoRow['number'] > 0)
			$style_criteria .= "#" . $styleInfoRow['number'] . " ";
		
		if($styleInfoRow['reissueyear'] != "")
			$style_criteria .= "R".substr($styleInfoRow['reissueyear'], 2);
	    
	    
		$style_info = style_info_tokenize(style_info_tidy(array(), $auctionRow['style_info']));
		foreach($style_info as $k => $v)
		{
			if(preg_match("/^signed /", $v))
				unset($style_info[$k]);
		}
		usort($style_info, "style_info_order_compare");
		$style_info = implode("; ", $style_info);
	    
		$query = "SELECT MrLister FROM tbl_mrlistertitles ".
			"WHERE film_title = '".mysql_real_escape_string($auctionRow['film_title'])."' AND flag!='128' ";
			
		if($auctionRow['type_code'] != "commercial poster")
			$query .= "and type_code != 'commercial poster' ";
		
		if(!preg_match("/(^|;) *R[0-9]{2}/", $auctionRow['style_info']))
			$query .= "and coalesce(`style`, '') not regexp '(^|;) *R[0-9]{2}' and coalesce(MrLister, '') not regexp '^R(18|19|20)[0-9]{2}' ";
		
		$query .= "order by ";
		
		if(preg_match("/(^|;) *(R[0-9]{2}s?)/", $auctionRow['style_info'], $match))
			$query .= "style = '$match[2]' desc, ";
		
		$query .= "type_code = '".mysql_real_escape_string($auctionRow['type_code'])."' desc, style = '".mysql_real_escape_string($style_info)."' desc, ".
			"type_code = '1sh' desc, ".
			"style = '".mysql_real_escape_string($style_info)."' desc ".
			"limit 1";
		
		$r = mysql_query2($query);
		
		if(@mysql_num_rows($r) > 0)
		{
			$mrLister = mysql_result($r, 0, 0);
		}
		else
		{
			$mrLister = preg_replace("/[^0-9s]{1}$/","",substr($descriptionsRow['Year/NSS'], 0, 5));
		}
		
		//Copy magazine date/year to MrLister
		//
    //if(stristr($auctionRow['type_code'], "magazine") !== false && preg_match("/(January|February|March|April|May|June|July|August|September|October|November|December) ([\d]{1,2}, |)([\d]{4})/", $auctionRow['style_info'], $matchy))
    if((stristr($auctionRow['type_code'], "magazine") !== false || stristr($auctionRow['type_code'], "comic book") !== false || stristr($auctionRow['type_code'], "underground comix") !== false) && preg_match("/(January|February|March|April|May|June|July|August|September|October|November|December) ([\d]{1,2}, |)([\d]{4})/", $auctionRow['style_info'], $matchy))
			$mrLister = $matchy[0];
		
		
		
		if(preg_match("/^('?R?(?:18|19|20)[0-9]{2}s?)(?: |$)(.*)/", $mrLister, $match1)) //If the MrLister has a year at the beginning (normal case)
		{
			//Commercial poster year in listing_notes
			if(preg_match("/\b((19|20)[0-9]{2}s?)\b/i", $auctionRow['listing_notes'], $match) && 
				$auctionRow['type_code'] == "commercial poster")
			{
				$mrLister = $match[1] . " " . $match1[2];
				preg_match("/^('?R?[0-9]{2}s?)(?: |$)(.*)/", $mrLister, $match1);
			}
			
			//If we have a reissue year
			else if(preg_match("/^([0-9]{2}[0-9]{2}s?)/", $styleInfoRow['reissueyear'], $match2))
			{
				$mrLister = "R" . $match2[1] . " " . trim($match1[2]);
				preg_match("/^('?R?[0-9]{2}s?)(?: |$)(.*)/", $mrLister, $match1);
			}
			
			//If we have a first release year
			else if(preg_match("/^([0-9]{2}[0-9]{2}s?)/", $styleInfoRow['firstrelease'], $match3))
			{
				$mrLister = $match3[1] . " " . $match1[2];
				preg_match("/^('?R?[0-9]{2}s?)(?: |$)(.*)/", $mrLister, $match1);
			}
			
			//If it's re-titled
			//Nah, screw this... it doesn't work right
			// if($styleInfoRow['retitled'] != "")
				// $mrLister = substr($mrLister, 0, 4) . $styleInfoRow['retitled'];
				
			//If we have an autograph
			if($styleInfoRow['signedby'] != "" && stripos($match1[2], "by $styleInfoRow[signedby]") === false)
			{
				$mrLister = $match1[1] . " by $styleInfoRow[signedby], " . trim($match1[2]);
			}
			
			
		}
		
		# soundtracks
		if($auctionRow['type_code'] == "CDs")
		{
			$mrLister = "";
			
			if(preg_match("/^(\d){4}/", $styleInfoRow['firstrelease']))
				$mrLister = $styleInfoRow['firstrelease'];
			else
				$mrLister = substr($descriptionsRow['Year/NSS'], 0, 4);
				
			if($styleInfoRow['music_by'] != "")
				$mrLister .= " original score by $styleInfoRow[music_by]";
		}
		
		# scripts
		if($auctionRow['type_code'] == "script")
		{
			$mrLister = "";
			# Split up the style info
			$split = array_map("trim", explode(";", $auctionRow['style_info']));
			
			if(count($split) >= 2)
			{
				if(preg_match("/(January|February|March|April|May|June|July|August|September|October|November|December) ([\d]{1,2}, |)([\d]{4})/", $auctionRow['style_info'], $matchy))
					$mrLister = $matchy[0] . ", ";
				
				// foreach($split as $s)
				// {
					// if(stripos($s, "draft") !== false || stripos($s, "revis") !== false || stripos($s, "script") !== false)
						// $mrLister .=  trim($s) . ", ";
				// }
				
				if(!preg_match("/(January|February|March|April|May|June|July|August|September|October|November|December) ([\d]{1,2}, |)([\d]{4})/", $split[count($split)-1]))
				$mrLister .= "screenplay by " . $split[count($split)-1];
			}
			elseif(count($split) === 1)
				$mrLister = $split[0];
		}
		
		#end scripts
	}
	else
	{
		$mrLister = "";
	}
	
	/*
	[11:04:42 AM] <Gary>
	 can you make the MrL field not allow a ' at the beginning?  this misc auction has a ton of items that we've never sold before, so they are all drawing from the old MrL titles that didn't get updated to 4 digits (or just edit/replace all those old MrL titles in the descriptions table)
	[11:05:28 AM] <Aaron>
	 I can do that.
	[11:05:53 AM] <Gary>
	 ur da bes
	*/
	$mrLister = preg_replace("/^'/", "", $mrLister);
	
	//After Description
	if(($number_of_images = count(String::split_list($auctionRow['image2'])) + 1) > 10)
	{
		$after_description_no_note[] = "Note: We have $number_of_images images of the $poster_plural in this set, but due to a space limitation, ".
			"only <b>TEN</b> of the $number_of_images images are displayed above. ".
			"However, there is a \"supersize\" link to the right of those images that lets you see the other ".($number_of_images-10).".";
	}
	
	/*if($auctionRow['type_code'] == "Spanish herald" && $number_of_images > 1)
	{
		$after_description_no_note[] = "Please note that Spanish heralds, like U.S. heralds, were printed in very large quantities, ".
			"and then sent to individual theaters in Spain, and they would sometimes have the backs of them overprinted with their ".
			"theater name and specific play dates. But because a movie might play in Spain for a period of a year or two ".
			"(traveling from theater to theater), there is no guarantee that the date overprinted on the back of the herald is the ".
			"same as the date that the herald was first printed (and the date that the movie first played in Spain). <br /><br /> ".
			"Therefore, we don't list the date overprinted on the back of a herald as the date of the herald unless we know that ".
			"was when the movie first played in Spain. If we believe the herald was printed earlier, then we use that date. If it ".
			"is important to you that the date on the herald is the date the movie first opened, then please look at our image of ".
			"the back of this herald to see if there is a different date printed on it. ";
	}*/
	
	//Signed index card
	if($auctionRow['type_code'] == "index card, signed")
	{
		$who_signed = ($styleInfoRow['signedby'] != "") ? $styleInfoRow['signedby'] : ucwords(strtolower(preg_replace("/ \(.*/", "", $auctionRow['film_title'])));
		
		$temp = "This index card has been personally autographed (signed) by $who_signed";
		
		if(preg_match("/(?:^| |;) *#?([0-9]+\/[0-9]+)(?: |;|$)/", $auctionRow['style_info'], $matches) && 
			stristr($auctionRow['style_info'], "33 1/3") === false)
		{
			$temp .= " and hand-numbered $matches[1]!";			
		}
		else
		{
			$temp .= "!";
		}
		
		//Gary asked me to add this "repro" behavior. AK, 2013-01-31
		if(stristr($auctionRow['style_info'], "repro"))
		{
			switch($styleInfoRow['included_still'])
			{
				case "b/w":
					$temp .= ' Also included is a <b>REPRODUCTION</b> 8" x 10" still that the index card could be matted and framed together with!';
					break;
				case "color":
					$temp .= ' Also included is a <b>REPRODUCTION</b> color 8" x 10" still that the index card could be matted and framed together with!';
					break;
				default:
					$temp .= ' The index card could be matted with a vintage or repro still and framed together to make a cool display!';
					break;
			};
		}
		else
		{
		 	switch($styleInfoRow['included_still'])
			{
				case "b/w":
					$temp .= ' Also included is an 8" x 10" still that the index card could be matted and framed together with!';
					break;
				case "color":
					$temp .= ' Also included is a color 8" x 10" still that the index card could be matted and framed together with!';
					break;
				default:
					$temp .= ' The index card could be matted with a vintage or repro still and framed together to make a cool display!';
					break;
			};
		}
		
	   
		
		$after_description_no_note[] = $temp;
		
		unset($temp);
	}
	else if($auctionRow['type_code'] == "cut album page")
	{
		$who_signed = ($styleInfoRow['signedby'] != "") ? $styleInfoRow['signedby'] : ucwords(strtolower(preg_replace("/ \(.*/", "", $auctionRow['film_title'])));
		
		$temp = "This cut album page has been personally autographed (signed) by $who_signed";
		
		if(preg_match("/(?:^| |;) *#?([0-9]+\/[0-9]+)(?: |;|$)/", $auctionRow['style_info'], $matches) && stristr($auctionRow['style_info'], "33 1/3") === false)
		{
			$temp .= " and hand-numbered $matches[1]!";			
		}
		else
		{
			$temp .= "!";
		}
		
		
		switch($styleInfoRow['included_still'])
		{
			case "b/w":
				$temp .= ' Also included is an 8" x 10" still that it could be matted and framed together with!';
				break;
			case "color":
				$temp .= ' Also included is a color 8" x 10" still that it could be matted and framed together with!';
				break;
			default:
				$temp .= ' It could be matted with a vintage or repro still and framed together to make a cool display!';
				break;
		};
		$after_description_no_note[] = $temp;
		
		unset($temp);
	}
	else if($styleInfoRow['signedby'] != "" || $styleInfoRow['unreadable'] != 0)
	{
		if($styleInfoRow['signedby'] == "")
			$styleInfoRow['signedby'] = "***";
		
		if($item_is_signed_repro_8x10)
			$poster_simple_name = "<b>REPRODUCTION</b> $poster_simple_name";
			
		if($tblTypesRow['poster_simple']=='item'){
      if($styleInfoRow['quantity'] > 1)
        $temp = "that these {$poster_plural} have been personally autographed (signed) by $styleInfoRow[signedby]";
      else
        $temp = "that this {$poster_singular} has been personally autographed (signed) by $styleInfoRow[signedby]";
    }else{
      if($styleInfoRow['quantity'] > 1)
        $temp = "that these {$poster_simple_name}s have been personally autographed (signed) by $styleInfoRow[signedby]";
      else
        $temp = "that this $poster_simple_name has been personally autographed (signed) by $styleInfoRow[signedby]";
    }
			
			
		if(preg_match("/(?:^| |;) *#?([0-9]+\/[0-9]+)(?: |;|$)/", $auctionRow['style_info'], $matches) && stristr($auctionRow['style_info'], "33 1/3") === false)
		{
			$temp .= " and hand-numbered $matches[1]!";
		}
		else
		{
			$temp .= "!";
		}
			
		$after_description[] = $temp;
	}
	elseif(preg_match("/(?:^| |;) *#?([0-9]+\/[0-9]+)(?: |;|$)/", $auctionRow['style_info'], $matches) && stristr($auctionRow['style_info'], "33 1/3") === false)
	{
		$after_description[] = "that this item has been hand-numbered $matches[1]!";
	}
	
	//Country of origin/coproduction
	if($styleInfoRow['countryoforigin'] == 1)
	{
		if($styleInfoRow['coproduction'] == 1)
		{
			if($styleInfoRow['quantity'] > 1)
				$after_description[] = "that these are \"country of origin\" {$poster_simple_name}s for this partially $nationality movie!";
			else
				$after_description[] = "that this is a \"country of origin\" $poster_simple_name for this partially $nationality movie!";
			
		}
		else
		{
			if($styleInfoRow['quantity'] > 1)
				$after_description[] = "that these are \"country of origin\" {$poster_simple_name}s for this $nationality movie!";
			else
				$after_description[] = "that this is a \"country of origin\" $poster_simple_name for this $nationality movie!";
		}
	}
							
	//Move scripts
	if($auctionRow['type_code'] == "script")
	{
		$temp = "that this script ";
		
		$split = array_map("trim", explode(";", $auctionRow['style_info']));
		
		$temp .= "is ";
		foreach($split as $s)
		{
			if(stripos($s, "draft") !== false || stripos($s, "revis") !== false || stripos($s, "script") !== false)
				$temp .= "the " . trim(str_replace(Array("draft", "Draft"), "", $s)) . " draft ";
			elseif(stripos($s, "continuity") !== false)
				$temp .= "the $s script ";
			elseif(stripos($s, "censorship") !== false)
				$temp .= "the censorship dialogue script ";
			elseif(stripos($s, "release dialogue") !== false)
				$temp .= "the release dialogue script ";
		}
		
		if(count($split) >= 2)
		{
			if(preg_match("/(January|February|March|April|May|June|July|August|September|October|November|December) ([\d]{1,2}, |)([\d]{4})/", $auctionRow['style_info'], $matchy))
				$temp .= "from " . $matchy[0] . " ";
				
			if(!preg_match("/(January|February|March|April|May|June|July|August|September|October|November|December) ([\d]{1,2}, |)([\d]{4})/", $split[count($split)-1]))
			$temp .= "and the screenplay was written by " . $split[count($split)-1];
		}
			
		$after_description[] = $temp;
	}

	/*
	//We switched back to doing this in the after_description_bulk, per Phil. 2011-04-22
	//Italian date disclaimer
	if(strpos($auctionRow['type_code'], "Italian") !== false)
	{
		if(in_multiarray("<--ITALIAN RELEASE DATE IS CERTAIN-->", $infoNotesRow))
		{
			$after_description[] = "that it is difficult to accurately date Italian posters, and some unmarked re-release posters "
								.   "can be extremely difficult to distinguish from first releases, but in the case of this specific"
								.   " poster, we feel 100% certain that it is from the first release.";
		}
		else
		{
			$after_description[] = "that it is difficult to accurately date Italian posters, and some unmarked re-release posters can"
			.  " be extremely difficult to distinguish from first releases, so please bear that in mind before you"
			.  " place a bid.  We used our best information to date this poster, but we can't always guarantee that"
			.  " the Italian posters we sell are not from an unmarked re-release, but this will only prove to be "
			.  "true in a very tiny number of cases.";
		}
	}
	*/
	
	//English lobby card printed in Italy
	if(($styleInfoRow['engital'] == 1 && stristr($auctionRow['type_code'], "English LC") != -1) || 
		($styleInfoRow['language'] == "English" && $auctionRow['type_code'] == "Italian LC"))
	{
		if($styleInfoRow['quantity'] > 1)
		{
			$after_description[] = ReviewFormTemplates::render("English lobby card printed in Italy multiple");
		}
		else
		{
			$after_description[] = ReviewFormTemplates::render("English lobby card printed in Italy single");
		}
	}
	
	if($auctionRow['type_code'] == "sheet music")
	{
		$after_description[] = "that this sheet music is for the song \"".$styleInfoRow['additionaldetails']."\".";
	}
	
	//Folded herald
	if(stripos($auctionRow['type_code'], "herald") !== false and in_array($auctionRow['how_rolled'], Array("1F", "FF", "TF")))
	{
		$after_description_no_note[] = "This herald was folded in half at one time as was originally intended.";
	}
	
	//Trimmed to 27" x 41"
	if($nonStandardMeasurement && $measurements == '27" x 41"' && $standard_measurement == '27" x 41"')
	{
		$after_description[] = "that this one-sheet has been slightly trimmed on all four sides and it now measures 27\" x 41\"."
					." Apparently, the poster measured larger than 27\" x 41\" in both directions, and someone trimmed it so it would fit a standard size frame.";
	}
	//Trimmed
	else if($trimmed)
	{
		if($styleInfoRow['quantity'] > 1)
			$after_description[] = "that these {$poster_simple_name}s have been trimmed and they now measure $measurementsWithMetric.";
		else
			$after_description[] = "that this $poster_simple_name has been trimmed and it now measures $measurementsWithMetric.";
	}
	//Non standard measurement
	else if($nonStandardMeasurement && !($styleInfoRow['video'] || $auctionRow['type_code'] == "video poster"))
	{
		if(
			!preg_match("/(program|misc|1p|jumbo stills)/", $auctionRow['type_code']) && 
			(preg_match("/(1sh|TC|singles|30x40|40x60|crown|quad|Japanese|German|1p|2p|4p|8p|3sh|6sh|2sh|60x80)/", $auctionRow['type_code']) ||
			in_array($tblTypesRow['poster_simple'], Array("still", "lobby card")))
		)
		{
			if($standard_measurement == '8" x 10"' || $standard_measurement == '8" x 11"')
			{
				//Remove quotes
				$roundedMeasurements = str_replace("\"", "", $measurements);
				$roundedMeasurements = str_replace("\'", "", $roundedMeasurements);

				//Convert fractions
				$fractions = Array(" 1/2", " 1/4", " 3/4", " 1/8", " 3/8", " 5/8", " 7/8");
				$decimals  = Array(".5",  ".25", ".75", ".125", ".375",".625",".875");
				$roundedMeasurements = str_replace($fractions, $decimals, $roundedMeasurements);

				//Remove spaces
				//$roundedMeasurements = str_replace(" ", "", $roundedMeasurements);

				//Round numbers
				preg_match_all("/[\d]+(\.[\d]+|)/", $roundedMeasurements, $matches);
				
				if(!in_array($auctionRow['type_code'], array("Mexican LC", "Argentinean 2p", "German LC", "French LC")) && $styleInfoRow['s2_recreation'] == 0 && 
					count($matches) == 2 && $matches[0][0] <= 8 && $matches[0][1] <= 10 && ($matches[0][0] < 8 || $matches[0][1] < 10))
				{
					if($styleInfoRow['quantity'] > 1)
						$after_description[] = "that these $poster_plural measure $measurementsWithMetric, but they have not been trimmed.";
					else
						$after_description[] = "that this $poster_singular measures $measurementsWithMetric, but it has not been trimmed.";
				}
				else
				{
				  if($measurements!='8 1/4" x 10"' && $measurements!='8" x 10 1/4"' && $measurements!='8 1/4" x 10 1/4"'){
  					if($styleInfoRow['quantity'] > 1)
  						$after_description[] = "that these $poster_plural measure $measurementsWithMetric."; //Please do not bid on it thinking that it is $standard_measurement.";
  					else
  						$after_description[] = "that this $poster_singular measures $measurementsWithMetric."; //Please do not bid on it thinking that it is $standard_measurement.";
          }
				}
			}
			else
			{
				//Remove quotes
				$decimalMeasurements = str_replace(Array("\"", "\'"), "", $measurements);
				$decimalStandardMeasurements = str_replace(Array("\"", "\'"), "", $standard_measurement);

				//Convert fractions
				$fractions = Array(" 1/2", " 1/4", " 3/4", " 1/8", " 3/8", " 5/8", " 7/8");
				$decimals  = Array(".5",  ".25", ".75", ".125", ".375",".625",".875");
				$decimalMeasurements = str_replace($fractions, $decimals, $decimalMeasurements);
				$decimalStandardMeasurements = str_replace($fractions, $decimals, $decimalStandardMeasurements);

				//Remove spaces
				//$decimalMeasurements = preg_replace("/ (\.[0-9])/", "$1", $decimalMeasurements);
				//$decimalStandardMeasurements = preg_replace("/ (\.[0-9])/", "$1", $decimalStandardMeasurements);
				
				//$decimalMeasurements = str_replace(" ", "", $decimalMeasurements);
				//$decimalStandardMeasurements = str_replace(" ", "", $decimalStandardMeasurements);
				
				//Find components
				preg_match_all("/[\d]+(\.[\d]+|)/", $decimalMeasurements, $matches);
				preg_match_all("/[\d]+(\.[\d]+|)/", $decimalStandardMeasurements, $standardMatches);
				
				//Split into width and height
				$wh = array_map("trim", explode("x", $decimalMeasurements));
				
				if($auctionRow['type_code'] === "6sh")
				{
					if($wh == Array(84, 84))
						$after_description[] = "that the poster measures 84\" x 84\", not 81\" x 81\". This was something that Disney was experimenting with at this time, and several titles were made that size. The experiment was likely abandoned due to complaints by movie theaters that put the posters into six-sheet frames, and these slightly oversized posters didn't fit!";
				}
				elseif($auctionRow['type_code'] === "3sh" && $wh == Array(41, 84))
					$after_description[] = "that the poster measures 41\" x 84\", not 41\" x 81\". This was something that Disney was experimenting with at this time, and several titles were made that size. The experiment was likely abandoned due to complaints by movie theaters that put the posters into three-sheet frames, and these slightly oversized posters didn't fit!";
				elseif($auctionRow['type_code'] != "Mexican LC" && $styleInfoRow['s2_recreation'] == 0 && count($matches) == 2 && count($standardMatches) == 2 && $matches[0][0] < $standardMatches[0][0] && $matches[0][1] < $standardMatches[0][1])
				{
					if($styleInfoRow['quantity'] > 1)
						$after_description[] = "that these $poster_plural measure $measurementsWithMetric, but have not been trimmed."; // Please do not bid on it thinking that it is $standard_measurement.";
					else
						$after_description[] = "that this $poster_singular measures $measurementsWithMetric, but it has not been trimmed."; // Please do not bid on it thinking that it is $standard_measurement.";
				}
				else
				{
					if($styleInfoRow['quantity'] > 1)
						$after_description[] = "that these $poster_plural measure $measurementsWithMetric.";
					else
						$after_description[] = "that this $poster_singular measures $measurementsWithMetric.";
				}
				
			}

		}
	}

	//International
	if($styleInfoRow['international'] == 1)
	{
		switch($styleInfoRow['intlreason'])
		{
			case "FOR":
				$after_description[] = "that this poster was stamped \"FOR\" which means it was printed for use outside the United States "
									."(there would also have been posters stamped \"DOM\" for use within the United States).  Often, the \"FOR\" "
									."posters would have a different (sometimes racier) image than that for the one used in the United States.";
				break;
			case "FOREIGN":
				$after_description[] = "that this poster was stamped \"FOREIGN\" which means it was printed for use outside the United States "
										."(there would also have been posters stamped \"DOMESTIC\" for use within the United States).  Often, "
										."the \"FOREIGN\" posters would have a different (sometimes racier) image than that for the one used in the "
										."United States.";
				break;
			case "No NSS/ratings":
				$temp = "";
				if($styleInfoRow['quantity'] > 1)
				{
					$temp = "that these are \"international\" style {$poster_singular}s (meaning they were printed in the U.S. for use in non-U.S."
						." countries). Note the lack of a ratings box";
				}
				else
				{
					$temp = "that this is an \"international\" style {$poster_singular} (meaning it was printed in the U.S. for use in non-U.S."
						." countries). Note the lack of a ratings box";
				}
				
				# If it's a still or LC we leave out "and NSS information"
				if(!in_array($poster_simple_name, Array("still", "lobby card")))
					$temp .= " and NSS information.";
				else
					$temp .= ".";

				$after_description[] = $temp;
				break;
			case "ENG. INT'L":
				$after_description[] = " that the bottom of the poster is printed \"1 SHT. ENG. INT'L\" and was intended for use by English "
										."speaking audiences (there would also have been a poster stamped either \"FOR\" or \"SPANISH\" "
										."that was intended for non-English speaking audiences).";
				break;
			case "Marked International":
				$after_description[] = "that the poster is marked \"International\" on the bottom, indicating it was printed in the U.S. for use in non-U.S. countries.";
				break;
				
			case "Int'l Title":
				$after_description[] = "that this is an \"international\" style {$poster_singular} (meaning it was printed in the U.S. for ".
					"use in non-U.S. countries). Note the use of the international title (see above).";
				break;
				
			case "Int'l Studio":
				$after_description[] = "that this is an \"international\" style {$poster_singular} that was printed in the U.S. for use in ".
					"non-U.S. countries (note the international distribution studio logo).";
				break;
			
			case "Spanish":
			case "French":
			if($styleInfoRow['quantity'] > 1)
			{
				$after_description[] =  "that these are \"international\" style {$poster_singular}s (printed in the U.S. for use in $styleInfoRow[intlreason]-speaking countries).";
			}
			else
			{
				$after_description[] =  "that this is an \"international\" style {$poster_singular} (printed in the U.S. for use in $styleInfoRow[intlreason]-speaking countries).";
			}
				break;
			
			case "otherreason":
				$messages .= "This was described as international for this reason: '$styleInfoRow[intlotherreason]'.  Please describe how you see fit and tell Phillip so he can have Aaron account for it.";
				break;
		};
	}

	//Domestic
	if($styleInfoRow['domestic'] == 1)
	{
		switch($styleInfoRow['domreason'])
		{
			case "DOM":
				$after_description[] = "that this poster was stamped \"DOM\" which means it was printed for use within the United States "
										."(there would also have been posters stamped \"FOR\" for use outside the United States).";
				break;
			case "DOMESTIC":
				$after_description[] = "that this poster was stamped \"DOMESTIC\" which means it was printed for use within the United States "
										."(there would also have been posters stamped \"FOREIGN\" for use outside the United States).";
				break;
		};
	}

	//Stamped
	if($styleInfoRow['stamped'] == 1)
	{
		switch($styleInfoRow['stampedreason'])
		{
			case "Stamped ENG":
				$after_description[] = "that the back of the poster is stamped \"ENG\" and was intended for use by English speaking audiences in countries"
										." outside the U.S. (there would also have been a poster stamped \"SPANISH\" that was intended for non-English"
										." speaking audiences in countries outside the U.S.).";
				break;
			case "Stamped ENGLISH":
				$after_description[] = "that the back of the poster is stamped \"ENGLISH\" and was intended for use by English speaking audiences in countries"
										." outside the U.S. (there would also have been a poster stamped \"SPANISH\" that was intended for non-English "
										."speaking audiences in countries outside the U.S.).";
				break;
			case "Printed ENG":
				$after_description[] = "that the back of the poster is printed \"ENG\" and was intended for use by English speaking audiences in countries"
										." outside the U.S. (there would also have been a poster printed \"SPANISH\" that was intended for non-English"
										." speaking audiences in countries outside the U.S.).";
				break;
			case "Printed ENGLISH":
				$after_description[] = "that the back of the poster is printed \"ENGLISH\" and was intended for use by English speaking audiences in countries"
										." outside the U.S. (there would also have been a poster printed \"SPANISH\" that was intended for non-English "
										."speaking audiences in countries outside the U.S.).";
				break;
			/*
			case "Stamped SPANISH":
				$after_description[] = "that the back of the poster is stamped \"SPANISH\" and was intended for use by Spanish speaking audiences "
										."(there would also have been a poster stamped \"ENGLISH\" that was intended for English speaking "
										."audiences in countries outside the U.S.).";
				break;
			*/
		}
	}

	//Advance
	if($styleInfoRow['advance'] != "no")
	{
	  
		switch($styleInfoRow['advance'])
		{
			case "advance":
        //SS 2019-09-24 making everything plural
        if($styleInfoRow['quantity'] > 1)
        {
				  $after_description[] = "that these are advance $poster_plural (note the \"".trim($styleInfoRow['advancetagline'], '"')."\" at the bottom of the posters).";
        }else{
          $after_description[] = "that this is an advance $poster_singular (note the \"".trim($styleInfoRow['advancetagline'], '"')."\" at the bottom of the poster).";
        }
				break;
			case "teaser":
        if($styleInfoRow['quantity'] > 1)
        {
				  $after_description[] = "that these are teaser $poster_plural (note the complete lack of credits "
	 				  .((stristr($styleInfoRow['advancetagline'], "no credits")) ? "on the posters)." : "as well as having \"".trim($styleInfoRow['advancetagline'], '"')."\" printed at the bottom of the posters.");
        }else{
          $after_description[] = "that this is a teaser $poster_singular (note the complete lack of credits "
            .((stristr($styleInfoRow['advancetagline'], "no credits")) ? "on the poster)." : "as well as having \"".trim($styleInfoRow['advancetagline'], '"')."\" printed at the bottom of the poster.");
        }
				break;
			case "advance/teaser":
        if($styleInfoRow['quantity'] > 1)
        {
				  $after_description[] = "that these are advance/teaser $poster_plural (note the \"".trim($styleInfoRow['advancetagline'], '"')."\" at the bottom of the posters).";
        }else{
          $after_description[] = "that this is an advance/teaser $poster_singular (note the \"".trim($styleInfoRow['advancetagline'], '"')."\" at the bottom of the poster).";
        }
				break;
		};
	}

	//SpanUS
	if($styleInfoRow['spanus'] == 1) // || ($styleInfoRow['language'] == "Spanish" && $tblTypesRow['country'] == "the U.S."))
	{		
			if($styleInfoRow['quantity'] > 1)
			{
				$after_description[] = "that these {$poster_singular}s were printed in the United States for use".
					" at theaters with Spanish speaking audiences (this was done most".
					" by MGM, starting in the 1930s, but it was done by the other ".
					"major studios as well, and often the posters would have the exact".
					" same image as the English language poster, except the writing ".
					"would be in Spanish, and on posters from the 1930s and 1940s ".
					"there would be an added \"Toda en Espanol!\", meaning ".
					"\"Entirely in Spanish!\", printed within the image). ".
					"Sometimes posters from the 1960s or later will have the ".
					"word \"SPANISH\" printed in the bottom border (or sometimes ".
					"stamped on the back of the poster).";
			}
			else
			{
				$after_description[] = "that this $poster_singular was printed in the United States for use".
					" at theaters with Spanish speaking audiences (this was done most".
					" by MGM, starting in the 1930s, but it was done by the other ".
					"major studios as well, and often the posters would have the exact".
					" same image as the English language poster, except the writing ".
					"would be in Spanish, and on posters from the 1930s and 1940s ".
					"there would be an added \"Toda en Espanol!\", meaning ".
					"\"Entirely in Spanish!\", printed within the image). ".
					"Sometimes posters from the 1960s or later will have the ".
					"word \"SPANISH\" printed in the bottom border (or sometimes ".
					"stamped on the back of the poster).";
			}
	}
	elseif($styleInfoRow['language'] != "")
	{
		if($styleInfoRow['quantity'] > 1)
		{
			$after_description[] = "that these {$poster_singular}s were printed in $country, ".
				"but were intended to be used when the movie was shown in $styleInfoRow[language] speaking countries.";
		}
		else
		{
			$after_description[] = "that this {$poster_singular} was printed in $country, ".
				"but was intended to be used when the movie was shown in $styleInfoRow[language] speaking countries.";
		}
	}

	//Video
	if($styleInfoRow['video'] == 1 || $auctionRow['type_code'] == "video poster")
	{
		$after_description_no_note[] = "This $poster_singular measures $measurementsWithMetric and was ".
			"for the video release of this movie, not the theatrical release.";
	}

	//Double Sided DS
	if($styleInfoRow['doublesided'] == "ds")
	{
	  //SS 2019-09-24 making everything plural
	  if($styleInfoRow['quantity'] > 1)
    {
		  $after_description[] = "that these are double-sided posters. From ".
				"the 1990s on, movie posters were often made in double-sided ".
				"versions (for use in light boxes). If we do not note that a ".
				"poster is double-sided (as this is), then it is single-sided.".
				" Note that the image on the reverse is a mirror image of the ".
				"image on the front!";
		}else{
		  $after_description[] = "that this is a double-sided poster. From ".
        "the 1990s on, movie posters were often made in double-sided ".
        "versions (for use in light boxes). If we do not note that a ".
        "poster is double-sided (as this is), then it is single-sided.".
        " Note that the image on the reverse is a mirror image of the ".
        "image on the front!";
		}
		/*
		//UPDATE 2011-10-14 This is bad behavior and we are removing it - AK
		if($auctionRow['type_code'] == "1sh")
		{
			$after_description[] = "that this is a double-sided poster. From ".
				"the 1990s on, movie posters were often made in double-sided ".
				"versions (for use in light boxes). If we do not note that a ".
				"poster is double-sided (as this is), then it is single-sided.".
				" Note that the image on the reverse is a mirror image of the ".
				"image on the front!";
		}
		else
		{
			$after_description[] = "that this is a double-sided poster. To the ".
				"best of our knowledge, this poster was only created in a ".
				"double-sided version, so it could be displayed in a light box.";
			
		}
		*/
	}

	//Military
	//2012-08-17: Gary asked for this to be changed. AK
	if($styleInfoRow['military'] == 1)
	{
		$after_description_no_note[] = "This is a military $poster_singular. Military $poster_plural were printed at the same time ".
			"as the regular U.S. $poster_plural when the movie was first released. They were made to be used on U.S. ".
			"military installations. Sometimes the poster has small writing at the bottom that says \"For Distribution ".
			"and Use at U.S. Military Establishments Only\", and sometimes they just have \"Litho in U.S.A.\" or ".
			"\"Printed in U.S.A.\" in small letters in the lower right or lower left (most often, the ones that were used in the ".
			"1950s in the Korean War have \"Litho in U.S.A.\", and the ones that were used in the 1960s in the ".
			"Vietnam War have \"Printed in U.S.A.). This is one of the posters that says \"$styleInfoRow[militaryreason]\".";
	}
	/*
	$after_description[] = ($styleInfoRow['military'] == 1) ? "that this is a military one-sheet.  Military one-sheets were printed at the same time as the "
							."regular U.S. one-sheets. They were made to be used on U.S. military installations. Sometimes the "
							."poster has small writing at the bottom that says \"For Distribution and Use at U.S. Military Establishments Only\", and sometimes "
							."they just have \"Litho in U.S.A.\" in small letters in the lower right. This is one of the poster that says \"$styleInfoRow[militaryreason]\"."
							: "";
	*/

	//Deluxe
	if($styleInfoRow['papertype'] == "deluxe")
	{
		if($styleInfoRow['quantity'] > 1)
			$after_description[] = "that these are deluxe {$poster_singular}s printed on double weight paper stock.";
		else
			$after_description[] = "that this is a deluxe {$poster_singular} printed on double weight paper stock.";
	}
	
	//Repro still
	if(strpos($styleInfoRow['additionaldetails'], "repro") !== false && strpos($auctionRow['type_code'], "8x10") !== false)
		$after_description[] = "that many celebrities kept multiple 8\" x 10\" copies of good publicity "
							.  "images of themselves to sign for fans, and also many memorabilia shops sold such "
							.  "copy stills themselves. While they are not original stills, they are usually "
							.  "excellent clear copies, with a great shot of that celebrity in their heyday, "
							.  "which, when signed by the celebrity, makes for a wonderful framed picture on any fan's wall!";

	//Pieces
	if($auctionRow['type_code'] == "Japanese 38x62")
	{
		$after_description_no_note[] = "This poster is printed on $styleInfoRow[pieces] sheet".($styleInfoRow['pieces'] > 1 ? "s" : "")." and measures ".
			"$measurementsWithMetric. It measures quite differently from Japanese two-panel posters, which ".
			"are exactly twice the size of a Japanese \"B2\", which we refer to as \"Japanese two-panel ".
			"posters\". We do not know if there is an official Japanese term for this size poster, ".
			"which varies slightly in measurements, and which sometimes ".
			"can be found in one section or in two sections. If someone e-mails us further ".
			"information about this size, we will post it here.";
	}
	elseif($auctionRow['how_rolled'] != "linen" || in_array($auctionRow['type_code'], array("Japanese 2p")))
	{
		if($styleInfoRow['pieces'] == 0 && $tblTypesRow['pieces'] > 1)
		{
			//2012-01-03 Phil and Gary have decided to show this paragraph after all. 
			//It used to be that we didn't display this paragraph for the default number of pieces.
			$after_description[] = "that this $poster_singular was printed in $tblTypesRow[pieces] sections designed to overlap.";
		}
		elseif($styleInfoRow['pieces'] > 0)
		{
			$after_description[] = "that this $poster_singular was printed in $styleInfoRow[pieces] ".
				(($styleInfoRow['pieces'] == 1) ? "section." : "sections designed to overlap.");
		}
	}

	//Kodak
	$after_description[] = (($styleInfoRow['papertype'] == "Kodak") ? "that ".(($styleInfoRow['quantity'] > 1) ? "these {$poster_singular}s were " : "this {$poster_singular} was").
		" printed on Kodak paper, but we believe ".(($styleInfoRow['quantity'] > 1) ? "them" : "it")." to surely be ".(($styleInfoRow['quantity'] > 1) ? "original stills.": "an original still.").
		"  Sometimes, studios would run out of stills and have extras printed on Kodak stock.": "");
	
	//Heavy stock
	$after_description[] = (($styleInfoRow['heavystock'] == 1) ? "that this poster is printed on a much heavier than normal paper stock (we don't know why, but it is nice!).": "");

	//Additional details crap
	$a = $styleInfoRow['additionaldetails'];

	//2D
	if(preg_match("/(2-D|2D)/i", $a))
		$after_description[] = "that this movie was originally in 3-D. The studios created both 3D and 2D posters ".
			"and lobby cards (the 2-D posters and lobby cards were for theaters that lacked the 3-D equipment). ".
			"This $poster_simple_name is from the \"2-D\" release.";
	//3D
	if(preg_match("/(3-D|3D)/i", $a))
		$after_description[] = "that this movie was originally in 3-D. The studios created both 3D and 2D ".
			"posters and lobby cards (the 2-D posters and lobby cards were for theaters that lacked the 3-D ".
			"equipment). This $poster_simple_name is from the \"3-D\" release.";

	//Kilian
	if(stristr($a, "kilian"))
		$after_description[] = 'that this poster was created by Kilian Enterprises.  Kilian Enterprises posters are special posters '
							.  '<b>NOT</b> intended for theater use.  They were sold directly to fans.  They have far better printing '
							.  'than regular theater posters, and have better images, and are sometimes signed and numbered!';

	if($auctionRow['type_code'] == "trade ad" and $styleInfoRow['nationality'] == "" || $styleInfoRow['nationality'] == "U.S.")
	{
		$after_description_no_note[] = "From the 1920s on, studios would create elaborate trade ads, often in full color, ".
			"and often using the finest artists of the day. They would run these ads in their studio yearbooks and exhibitor ".
			"magazines, and they would also print those trade ads separately and mail them individually to theater owners, ".
			"trying to get them to book that specific movie. Sometimes those books and magazines are separated and the ads, ".
			"which now greatly resemble the individually printed trade ads, are sold individually. The trade ad offered ".
			"here was *****CHOOSE: removed from a yearbook or magazine OR printed individually*******. It can be framed ".
			"and displayed (but many trade ads have different images on each side, so one must choose which side to display ".
			"if it is framed!).";
	}

	//Stock/Whole Serial
	// if($styleInfoRow['stockposter'] == 1 || stristr($a, "whole serial"))
	// {
		// $after_description[] = 'that this is a "stock" poster created by ** for use by theaters showing ****.  Theaters would order '
							// .  'this poster, which had a large blank area at **.  Sometimes the studio would overprint the stock poster '
							// .  'with the title of a specific movie, and sometimes they were sent a series of large paper snipes with the '
							// .  'titles of specific entries of the series, so the theater could paste in each snipe as needed, thus getting '
							// .  'many posters for the price of one.  Sometimes stock posters are found with blank snipe areas.  Most '
							// .  'collectors consider them more valuable with a printed snipe attached, or with an overprinted snipe area '
							// .  '(this poster was overprinted with the title "**").';
		// $messages .= 'Please fill out the areas with **** in the After Description field.';
	// }

	//Trifolded 30sh
	if($auctionRow['type_code'] == "30sh" && $auctionRow['how_rolled'] == "TF")
	{
		$after_description[] = "This thirty-sheet poster was tri-folded only (which means there".
			" is one less vertical machine fold than usual).  This means the poster has a ".
			"significantly lesser number of folds than with thirty-sheets folded in the ".
			"\"regular\" way, which is nice!  Tri-folded thirty-sheets need to be sent in an".
			" oversized flat package, which measures roughly 13\" x 30\", so they cost more ".
			"to send than regularly folded thirty-sheets, especially if they are sent to ".
			"non-U.S. addresses.";
	}

	//Keybook
	if(($auctionRow['type_code'] === "8x10" || $auctionRow['type_code'] == "11x14") && $styleInfoRow['keybook'] == 1)
	{
		if($auctionRow['how_rolled'] === "linen")
		{
			if($styleInfoRow['quantity'] > 1)
			{
				$after_description_no_note[] = "These {$poster_plural} have been mounted to a linen type ".
					"material and have an extra 1\" to the left, ".
					"along with two punch holes in that extra area (typical of many key book stills).";
			}
			else
			{
				$after_description_no_note[] = "This {$poster_singular} has been mounted to a linen type ".
					"material and has an extra 1\" to the left of the still, ".
					"along with two punch holes in that extra area (typical of many key book stills).";
			}
		}
		else
		{
			//2012-07-11: Gary asked me to remove Deluxe from keybook stills because it only happens 50% of the time
			/*
			if($styleInfoRow['quantity'] > 1)
			{
				$after_description_no_note[] = "These key book {$poster_plural} are on a deluxe heavyweight ".
					"paper stock and have an extra 1\" on the left or top, ".
					"with four punch holes (where the stills were kept in a binder).";
			}
			else
			{
				$after_description_no_note[] = "This key book {$poster_singular} is on a deluxe heavyweight ".
					"paper stock and has an extra 1\" on the left or top ".
					"of the still with four punch holes (where the still was kept in a binder).";	
			}*/
		}
	}

	//Eng/US stills
	if(in_array($auctionRow['type_code'], Array("8x10", "11x14")) && $styleInfoRow['engus'] == 1)
	{
		if($styleInfoRow['quantity'] > 1)
		{
			$after_description[] = "that although these stills were \"printed in Great Britain\", ".
				"they have full NSS information. We have been told by an expert that Colombia and ".
				"MGM had a number of their color stills printed in Great Britain in the late 1950s ".
				"and the early 1960s (the years vary between the two studios), no doubt because the ".
				"English color printing was better than the U.S. color printing at this time. ".
				"However, they were printed in Great Britain to be used in the U.S. (we have heard ".
				"from many collectors who saw these color stills in U.S. theaters at the time these ".
				"movies were released, but we have not heard from any English collectors who saw ".
				"them used in England at that time). In the late 1960s and 1970s some studios ".
				"started printing color stills like these in Italy, surely for the same reason ".
				"(because Italian printers had better color printing during those years). ".
				"But those stills printed in Italy were for use in the U.S., as these stills ".
				"printed in Great Britain were for use in the U.S.";
		}
		else
		{
			$after_description[] = "that although this still was \"printed in Great Britain\", ".
				"it has full NSS information. We have been told by an expert that Colombia and ".
				"MGM had a number of their color stills printed in Great Britain in the late 1950s ".
				"and the early 1960s (the years vary between the two studios), no doubt because the ".
				"English color printing was better than the U.S. color printing at this time. ".
				"However, they were printed in Great Britain to be used in the U.S. (we have heard ".
				"from many collectors who saw these color stills in U.S. theaters at the time these ".
				"movies were released, but we have not heard from any English collectors who saw ".
				"them used in England at that time). In the late 1960s and 1970s some studios ".
				"started printing color stills like these in Italy, surely for the same reason ".
				"(because Italian printers had better color printing during those years). ".
				"But those stills printed in Italy were for use in the U.S., as these stills ".
				"printed in Great Britain were for use in the U.S.";
		}
	}
	
	//Canadian/US stills
	if(in_array($auctionRow['type_code'], Array("8x10", "11x14")) && $styleInfoRow['canadianus'] == 1)
	{
		if($styleInfoRow['quantity'] > 1)
		{
			$paragraph = "that these {$poster_singular}s appear in every way to be original U.S. release EXCEPT that there is one very tiny "
						."line of French type in the lower right of each still. It reads, \"IMPRIME AUX ETATS-UNIS D'AMERIQUE\". A collector "
						."has told us that he believes this means the stills were printed for use in areas that included French-speaking parts ".
						"of Canada, but that they WERE printed in the U.S., and that makes complete sense to us (and we have seen this on ".
						"stills from the 1940s through the early 2000s, so it has certainly been done for an extremely long time)! ".
						"So they are original stills from ".substr($descriptionsRow['Year/NSS'], 0, 4).", but"
						." they were printed partially for use in French-speaking Canada. If anyone knows more about this, please <a target=\"_blank\""
						." href=\"http://emovieposter.com/mail/contact.php?sel=9&sno={contactSubject}\">e-mail us</a> and we will post it here. ";
		}
		else
		{
			$paragraph = "that this {$poster_singular} appears in every way to be original U.S. release EXCEPT that there is one very tiny "
						."line of French type in the lower right. It reads, \"IMPRIME AUX ETATS-UNIS D'AMERIQUE\". A collector "
						."has told us that he believes this means the still was printed for use in areas that included French-speaking parts ".
						"of Canada, but that it WAS printed in the U.S., and that makes complete sense to us (and we have seen this on ".
						"stills from the 1940s through the early 2000s, so it has certainly been done for an extremely long time)! ".
						"So it is an original still from ".substr($descriptionsRow['Year/NSS'], 0, 4).", but".
						" it was printed partially for use in French-speaking Canada. If anyone knows more about this, please <a target=\"_blank\""
						." href=\"http://emovieposter.com/mail/contact.php?sel=9&sno={contactSubject}\">e-mail us</a> and we will post it here. ";
		}
		
		if($styleInfoRow['reissueyear'] != "")
			$paragraph .= " <---- !!!THIS IS A REISSUE, REVISE THIS PARAGRAPH TO REMOVE 'original'!!!";
		
		$after_description[] = $paragraph;
	}
	
	//Mapback
	if(stristr($a, "mapback"))
	{
		/*
			2014-08-28: Gary sent me this:
			ok, so now we'll do a permanent change on this............................................. the law has spoken.

			this poster is printed on the back of a map or part of another poster!
		*/
		
		//2012-11-27: Gary asked for the old paragraph to be changed to this. See email "mapback autolister"
		$after_description_no_note[] = "This poster is printed on the back of a map or part of another poster! ".
			"Why is this? During World War II, there were massive paper shortages in Belgium. Where ".
			"Belgian movie posters had previously been approximately 23\" x 32\", there was such a ".
			"shortage of paper that not only did they often have to print them on the back of other ".
			"posters or maps, but during World War II, the size of the posters shrank dramatically, ".
			"with some of them as small as 11\" x 15\". This situation continued even after World ".
			"War II, until around 1946 or 1947, when they began making Belgian movie posters in a ".
			"size of roughly 14\" x 22\", which became the standard size, and continued for decades! ".
			"The posters like these that are from during World War II or immediately after, and which ".
			"are printed in a small size (often on the back of other posters or maps) are INCREDIBLY ".
			"rare (surely they did not print many, and surely many of them were soon recycled themselves!";

		/*$after_description[] = 'that this poster was printed on the back of an old map!  During World War II in Belgium, there was '
							.  'a massive paper shortage, and so they used whatever paper they could find to print the movie posters, '
							.  'and in many cases they used old maps, and printed the movie posters on the reverse.  They often also '
							.  'made the posters smaller, so they could get more posters printed.  This practice continued for a couple '
							.  'of years after World War II.  Since these posters are original, and since all such posters are printed '
							.  'on the back of maps or other posters, this is not considered a defect by almost all collectors. ';*/
	}

	//Kraftbacked						
	if(stristr($a, "kraftback") || $styleInfoRow['kraftbacked'] == 1)
		$after_description[] = 'that this poster has been kraftbacked, which means it was backed onto a brown paper soon after it was printed '
							.  '(this process is called "kraft-backing", and posters with it are called "kraft-backed").  One could have '
							.  'it professionally removed from the kraft paper and linenbacked, and it would look great!';
	
	
	if(stristr($styleInfoRow['additionaldetails'].$auctionRow['style_info'], "wilding"))
	{
		$after_description[] = "that we have been informed by several collectors that they believe that this poster is a \"wilding\" poster ".
			"(one that was solely used for advance publicity, being pasted on walls around town before the movie played, just as subway ".
			"posters were used in New York City many years ago), and that this poster was never sent to theaters showing the movie. Please ".
			"do not bid on this poster unless you can accept that it is a \"wilding\" poster, and was not printed for use in specific theaters.";
	}
	
	if(stristr($styleInfoRow['additionaldetails'].$auctionRow['style_info'], "printer's proof") || 
        stristr($styleInfoRow['additionaldetails'].$auctionRow['style_info'], "printer's test"))
		$after_description_no_note[] = "This is a very rare uncut printer's test poster (often referred to as a \"printer's proof\")! ".
            "Before a poster was printed in large quantities, the printer would print a limited number of posters as \"test\" ".
            "posters. Because they needed to be shown to people at the studio, people in the art department, the stars, etc, they ".
            "would print a number of test posters and distribute them to these people. Some of them would be fully trimmed, but often".
            " they would leave the color chart on the left edge of many of the posters, which added an extra inch to the width of the".
            " poster (which explains why the poster measures $measurementsWithMetric). Once these printer's test posters were shown to the ".
            "various people in a position to \"OK\" them, the printer would print the full print run of the posters. Not only is a ".
            "printer's test poster far more rare than a regular poster, but it is also 100% genuine as there is no fear of ".
            "purchasing a reproduction!";

		
	//2pc together
	if(stristr($a, "2pc together"))
	{
		$after_description[] = 'that this poster was printed in 2 sections (designed to overlap) and when combined, '
							.  'the poster measures approximately ***';
		
		$messages .= 'Please fill out the areas with *** in the After Description field.';
	}
	
	//Envelope
	if($styleInfoRow['bag'] == "envelope" || $styleInfoRow['bag1'] == "envelope" || $styleInfoRow['bag'] == "bag" || $styleInfoRow['bag1'] == "bag")
		$after_description[] = 'that this auction also includes the printed envelope this item originally came with!';
		
	//Two Sided
	if($styleInfoRow['doublesided'] == "twosided")
	{
		if($auctionRow['type_code'] == "Japanese B3")
		{
			$after_description[] = "that the poster is double-sided, but not in the regular U.S. way! ".
				"This smaller Japanese poster has lots of black & white images on the reverse, ".
				"along with much text information (in Japanese).";
		}
		else
		{
			$after_description[] = 'that this poster is double-sided, but not in the regular way. There is a '.
				'different image on each side of the poster.  We have provided an image of each '.
				'side, and please realize that the winner of this auction will receive ONE poster!';
		}
	}
	
	//2015-11-09 Gary asked for Japanese press sheet to be separated from the regular two-sided above.
	if($auctionRow['type_code'] == "Japanese press sheet")
	{
		$after_description_no_note[] = "We have provided an image of each side of the press sheet.";
	}
	
	/*
	//ItalUS
	if($styleInfoRow['italus'] == 1 || ($styleInfoRow['language'] == "English" && $tblTypesRow['country'] == "Italy"))
	{
		if($styleInfoRow['quantity'] > 1)
			$after_description[] = 'that this poster was printed in Italy for use at theaters with English speaking audiences.';
		else
			$after_description[] = 'that these posters were printed in Italy for use at theaters with English speaking audiences';
	}*/
	
	//Campaign book pages
	if(stristr($styleInfoRow['additionaldetails'], "campaign book page") !== false)
	{
			$after_description_no_note[] = "From the 1920s on, studios would create elaborate studio yearbooks ".
				"(also known as campaign books). They were made once each year, and they were often in full ".
				"color, and often used the finest artists of the day to create many elaborate ads for their ".
				"upcoming releases. The page offered here was removed from a studio yearbook, and can be ".
				"framed and displayed (but almost all campaign book pages have different images on each side, ".
				"so one must choose which side to display if it is framed!).";
	}
	
	//Presskit
	if($auctionRow['type_code'] == "presskit")
	{
		if($styleInfoRow['stills'] == "" || $styleInfoRow['stills'] == 0)
			$after_description[] = "that normally we take a picture of the cover of the presskit and eight of the stills, BUT IN THIS CASE, THERE ARE NO STILLS, SO OF COURSE WE COULD NOT PICTURE ANY.  We have noted if the presskit contains any supplements, but we do not picture them.  All of the presskits are sealed and ready to be sent, and we cannot answer any questions about the contents.";
		else
			$after_description[] = "that we have taken a picture of the cover of the presskit and eight of the stills (less than eight if the presskit has less than eight stills, of course).  We have noted if the presskit contains any supplements, but we do not picture them.  All of the presskits are sealed and ready to be sent, and we cannot answer any questions about the contents, especially about what any stills we may have not pictured show.  REMEMBER TO LOOK AT THE TOP OF THIS AUCTION TO SEE HOW MANY STILLS ARE INCLUDED WITH THIS PRESSKIT, BECAUSE WE NEVER PICTURE MORE THAN EIGHT OF THEM (and we pretty much picked eight stills at random).";
	}
		
	//Other company
	if($styleInfoRow['otherco'] != 0)
		$after_description_no_note[] = "This was printed by Associated Displays Corporation which was a company based in New York City. Before it was discovered by noted poster dealer/historian Walter Reuben, collectors did not know the name of this company (and thought it to be located in the Midwest), and it is often referred to as the \"other company\" (and we will continue to refer to it that way, since collectors know it that way). From 1936 to roughly 1941, this company printed its own one-sheets and lobby card sets (and a very few inserts, half-sheets, three-sheets, 40x60s and stills) for three major studio movies (Warner Brothers, Paramount and United Artists, but no other studios). The items almost always feature a tiny legal disclaimer and cast credits only. The posters and lobby cards are from the original release of the movie, and almost always have completely different artwork from the regular studio release. They did this by combining elements from different film scenes onto one poster or card. Sometimes, \"other company\" posters have images that are the equal of those from the regular studio release posters.";
	
	//East Hemi
	if(stristr($auctionRow['style_info'], "west hemi"))
	{
		if($styleInfoRow['quantity'] > 1)
			$after_description[] = "that these {$poster_singular}s were marked \"WEST HEMI\" which means they were printed for use in one half of the world (there were also posters stamped \"EAST HEMI\" for use in the other half of the world!). <b>THIS WAS DUE TO THE TWO PRODUCERS (Saltzman and Broccoli) BEING UNABLE TO AGREE ON WHO SHOULD GET FIRST BILLING!</b> They resolved this by having posters for half of the world having one of their names first, and on the other half of the world, they reversed it!";
		else
			$after_description[] = "that this $poster_singular was marked \"WEST HEMI\" which means it was printed for use in one half of the world (there were also posters stamped \"EAST HEMI\" for use in the other half of the world!). <b>THIS WAS DUE TO THE TWO PRODUCERS (Saltzman and Broccoli) BEING UNABLE TO AGREE ON WHO SHOULD GET FIRST BILLING!</b> They resolved this by having posters for half of the world having one of their names first, and on the other half of the world, they reversed it!";
	}
	
	
	//Foil
	if(preg_match("/\bfoil\b/i", $auctionRow['style_info']))
	{
		//Matt told me to update this, so I did. AK 2016-02-16
		//Matt told me to update this AGAIN, so I did. AK 2016-04-27
		$after_description[] = "that portions of this item are printed on a foil ".
			"material and is very difficult to accurately photograph, but is quite ".
			"striking when viewed in person! Also note that foil material scuffs ".
			"quite easily, and is difficult to find in nice condition.";
		
		//Old version from 2016-02-16		
		/*"that this is a special \"foil\" poster, and it is ".
		"INCREDIBLY difficult to photograph the reflective portions of these ".
		"posters, so know that any unevenness in the image we provide is in the ".
		"photograph, and is NOT in the poster itself.";*/
	}
	
	//West hemi
	if(stristr($auctionRow['style_info'], "east hemi"))
	{
		if($styleInfoRow['quantity'] > 1)
			$after_description[] = "that these {$poster_singular}s were marked \"EAST HEMI\" which means they were printed for use in one half of the world (there were also posters stamped \"WEST HEMI\" for use in the other half of the world!). <b>THIS WAS DUE TO THE TWO PRODUCERS (Saltzman and Broccoli) BEING UNABLE TO AGREE ON WHO SHOULD GET FIRST BILLING!</b> They resolved this by having posters for half of the world having one of their names first, and on the other half of the world, they reversed it!";
		else
			$after_description[] = "that this $poster_singular was marked \"EAST HEMI\" which means it was printed for use in one half of the world (there were also posters stamped \"WEST HEMI\" for use in the other half of the world!). <b>THIS WAS DUE TO THE TWO PRODUCERS (Saltzman and Broccoli) BEING UNABLE TO AGREE ON WHO SHOULD GET FIRST BILLING!</b> They resolved this by having posters for half of the world having one of their names first, and on the other half of the world, they reversed it!";
	}

	if($auctionRow['type_code'] == "Danish Program")
	{
		$after_description[] = "that we have pictured the front and back covers and an interior 2-page spread from this program.";
	}
	elseif($auctionRow['type_code'] == "Japanese program")
	{
		$after_description[] = "that we have provided an image of the front cover, the back cover, ".
			"and an interior two-page spread of this $styleInfoRow[pages]-page program. ".
			"You can use these images to determine the exact condition of it from our ".
			"super-sized images, but realize that there are pages you are not seeing.";
	}
	elseif($auctionRow['type_code'] == "program book")
	{
		$after_description[] = "that we have pictured the front cover and one or more interior 2-page spreads from this program.";
	}
	elseif($auctionRow['type_code'] == "program book, souvenir") // && $styleInfoRow['pages'] > 4)
	{
		//Gary told me this was wrong and gave me another paragraph to replace it. AK 2016-02-19
		/*$after_description[] = "that we have pictured the front cover and two interior 2-page spreads from this program. ".
			"You can use these images to determine the exact condition of it from our super-sized image, but ".
			"realize that there are some other pages you are not seeing.";*/
			
		$after_description[] = "that we have pictured the front cover and an interior 2-page spread from this program. ".
			"You can use these images to determine the exact condition of it from our super-sized image, ".
			"but realize that there are some other pages you are not seeing.";
	}
	elseif(stristr($auctionRow['type_code'], "program book") && $styleInfoRow['pages'] > 4)
	{
		$after_description[] = "that we have provided an image of the cover and four of the pages of this ".$styleInfoRow['pages']."-page program. ".
			"You can use these images to determine the exact condition of it from our super-sized image, but realize that there are many other ".
			"pages you are not seeing.";
	}
	elseif(stristr($auctionRow['type_code'], "program") && $styleInfoRow['pages'] > 4)
	{
		if($auctionRow['type_code'] == "German program" || $auctionRow['type_code'] == "English program" || $auctionRow['type_code'] == "Austrian program")
			$tblTypesRow['after_description_autolist'] = "";
		
		if($styleInfoRow['pages'] >= 8)
		{
			$after_description[] = "that we have provided an image of four of the pages of this ".$styleInfoRow['pages']."-page program ".
				"(we did this by opening it and laying it flat and photographing the front and back cover together, and two of the ".
				"interior pages together). You can see the four of the ".$styleInfoRow['pages']." pages, and can well determine the exact condition of it ".
				"from our super-sized image, but realize that there are ".($styleInfoRow['pages']-4)." pages you are not seeing. But of course this means that ".
				"the front cover appears in the top right of our image, but normally, the program would be folded down the center and ".
				"you would view the cover by itself (and it will be sent folded as was originally intended).";
		}
		else
		{
			$after_description[] = "that we have pictured the front cover and an interior 2-page spread from this program.";
		}
	}
	
	if($auctionRow['type_code'] == "LC" and $styleInfoRow['quantity'] < 2)
	{
		$after_description[] = "that we have a scan of both the front and the back of this lobby card, which should greatly help you see what defects it has.";
	}
	
	//Taped or glued
	if($styleInfoRow['taped'] == 1 || $styleInfoRow['glued'] == 1)
	{
		$temp = "that the theater that used this poster ";
		
		if($styleInfoRow['taped'] == 1 && $styleInfoRow['glued'] == 1)
			$temp .= "taped and glued";
		elseif($styleInfoRow['taped'] == 1)
			$temp .= "taped";
		elseif($styleInfoRow['glued'] == 1)
			$temp .= "glued";
			
		$temp .=" the pieces together before putting them on display (this is typical of many actual theater-used posters in multiple pieces).";
				
		$after_description[] = $temp;
	}
	
	if($auctionRow['date_added'] > "2017-02-14 15:30:00" && $auctionRow['xrated'] != 0)
	{
		$after_description[] = "that this item contains some nudity, so we have placed a ".
			"white bar over those areas for those who are bothered by such images. Of ".
			"course, the actual item does not have the white bars.";
	}
	
	/*
	Gary/Bruce and I just caught something - you know that blurb we use many many times over in 8x10Multi that says :

	<b>SUPER IMPORTANT! THOUGH ONLY EIGHT OF THE STILLS ARE PICTURED, THE HIGH BIDDER ON THIS LOT WILL RECEIVE 15 STILLS FROM THIS MOVIE</b> (but realize that on some of these lots with 9 or more stills there may be a few duplicates, mostly on post-1960 lots)! We realize there is an element of gambling to this, but we made only a small effort to find the best stills in these lots, and, if you have any willingness to gamble at all, you may find that you get some great stills that were not pictured! Please do <b>NOT</b> bid on this lot unless you can accept that you are only seeing eight of the stills.

	Well, just noticed that it is doing that for ALL of the FFNON auction - problem is... we picture all stills for that auction....doh!

	Can you turn that off for the FFNON auction only or is this something that can't be avoided?

	Todd
	
	2016-03-08

	*/
	
	//sets of B&W stills with 5 or more
	if(($auctionRow['type_code'] == "8x10, B&W, multiples" || 
		$auctionRow['type_code'] == "English 8x10" || 
		($auctionRow['type_code'] == "8x10" && 
		$styleInfoRow['color'] !== "color" && $styleInfoRow['minilc'] != 1)) && 
		$styleInfoRow['quantity'] > 8 && $auctionRow['auction_code'] != "FFNON")
	{
		/*
		$after_description_no_note[] = "SUPER IMPORTANT! THIS LOT CONTAINS ".
			"$styleInfoRow[quantity] STILLS, BUT ONLY EIGHT OF THE STILLS ARE PICTURED! ".
			"THE HIGH BIDDER ON THIS LOT WILL RECEIVE $styleInfoRow[quantity] STILLS ".
			"FROM THIS MOVIE, and NOT just the eight pictured! We realize there is an ".
			"element of gambling to this, but we made only a small effort to find the ".
			"best eight stills in these lots, and, if you have any willingness to ".
			"gamble at all, you may find that you get some great stills that were not ".
			"pictured! Please do NOT bid on this lot unless you can accept that you ".
			"are only seeing eight of the stills.";
		
		$after_descritpion_no_note[] = "Note that in addition to not all of the stills ".
			"in this lot being pictured, there is a <b>SLIGHT</b> possibility that ".
			"there will be duplicate images within the lot (some of the stills not ".
			"pictured may have the same image as one or more of the ones pictured, ".
			"or the same image as each other). This is only true on a small number of ".
			"lots, <b>BUT PLEASE DO NOT BID ON THIS LOT UNLESS YOU CAN ACCEPT THE ".
			"SMALL POSSIBILITY THAT THERE MAY BE SOME DUPLICATES CONTAINED IN IT</b> ".
			"(and it is truly only on a small number of the larger lots).";*/
			
		/*$after_description_no_note[] = "<b>SUPER IMPORTANT! THOUGH ONLY EIGHT OF THE STILLS ARE PICTURED, THE ".
			"HIGH BIDDER ON THIS LOT WILL RECEIVE $styleInfoRow[quantity] STILLS FROM THIS MOVIE</b> (but realize that on some ".
			"of these lots with 9 or more stills there may be a few duplicates, mostly on post-1960 lots)! We ".
			"realize there is an element of gambling to this, but we made only a small effort to find the ".
			"best stills in these lots, and, if you have any willingness to gamble at all, you may find that ".
			"you get some great stills that were not pictured! Please do <b>NOT</b> bid on this lot unless ".
			"you can accept that you are only seeing eight of the stills.";*/
	}
			
	if(in_array($auctionRow['type_code'], $pressbook_type_codes))
	{
		$supplements = (preg_match("/[\d]+(?= sup)/i", $auctionRow['style_info'], $matches) && $matches[0] > 0);
		$heralds = (preg_match("/[\d]+(?= herald)/i", $auctionRow['style_info'], $matches) && $matches[0] > 0);
		
		if($supplements && $heralds)
		{
			$after_description[] = "that when studios would prepare pressbooks they would often include a sample of ".
									"the herald. Most often, the herald is no longer still with the pressbook. ".
									"Sometimes, the herald by itself will sell for almost as much as the rest of the ".
									"entire pressbook! This {$nationality} pressbook contains the original herald which is pictured ".
									"next to the pressbook below. Also note that pressbooks were prepared prior to a ".
									"movie being released. Often, changes would be made in a movie advertising campaign ".
									"(billing of actors, different images, etc.), and the theaters would print up special ".
									"supplements that they would send out with the pressbooks that had already been ".
									"printed. These supplements are very rare, far more rare than the pressbooks ".
									"themselves! Some pressbooks would have no supplements, some would have one, and ".
									"some would have several. Almost every Academy Award winning movie would have a ".
									"special \"Academy Award winner\" supplement. Also note that we have provided ".
									"an image of the front and back covers of this pressbook, and of course, the ".
									"winner of this auction will receive the entire single pressbook we are ".
									"auctioning (plus any supplements or heralds described above)!";

		}
		elseif($supplements)
		{
			$after_description[] = "that pressbooks were prepared prior to a movie being released. Often, changes ".
									"would be made in a movie advertising campaign (billing of actors, different images, ".
									"etc.), and the theaters would print up special supplements that they would send out ".
									"with the pressbooks that had already been printed. These supplements are very rare, ".
									"far more rare than the pressbooks themselves! Some pressbooks would have no supplements, ".
									"some would have one, and some would have several. Also note that we have provided an ".
									"image of the front and back covers of this pressbook, and of course, the winner of ".
									"this auction will receive the entire single pressbook we are auctioning (plus any ".
									"supplements or heralds described above)!";
		}
		elseif($heralds)
		{
			$after_description[] = "that when studios would prepare pressbooks they would often include a sample ".
									"of the herald.  Most often, the herald is no longer still with the pressbook. ".
									"Sometimes, the herald by itself will sell for almost as much as the rest of the ".
									"entire pressbook! This {$nationality} pressbook contains the original herald that was included ".
									"with it, and it is pictured next to the pressbook below. Also note that we have ".
									"provided an image of the front and back covers of this pressbook, and of course, ".
									"the winner of this auction will receive the entire single pressbook we are ".
									"auctioning (plus any supplements or heralds described above)!";

		}
		else
		{
			//Add the after_description from tbl_types
			$after_description[] = str_replace("[measurements]", $measurementsWithMetric, $tblTypesRow['after_description_autolist']);
		}
		
		//Uncut pressbooks
		if($styleInfoRow['cut'] == "0")
		{
			$after_description[] = "that this {$nationality} pressbook is complete and uncut! Given that theater owners received "
								.  "pressbooks partly in order to create their newspaper advertising, and quite "
								.  "frequently cut them up for that purpose, it is rare to find a pressbook that "
								.  "IS complete and uncut!";
		}
	}
	else
	{
		//Add the after_description from tbl_types
		$after_description[] = str_replace("[measurements]", $measurementsWithMetric, $tblTypesRow['after_description_autolist']);
	}
	
	//How rolled
	if($auctionRow['auction_code'] !== "SHIP_FLAT" && 
		((in_array($auctionRow['auction_code'], $rolled_auctions) && ($auctionRow['how_rolled'] == "FF" || $auctionRow['how_rolled'] == "1F")) || 
		(
			($auctionRow['how_rolled'] == "FF" && 
			$auctionRow['type_code'] == "1/2sh") || 
			(
				(
					$auctionRow['type_code'] == "insert" || 
					$auctionRow['type_code'] == "Italian locandina"
				) && 
				(
					$auctionRow['how_rolled'] == "FF" || 
					$auctionRow['how_rolled'] == "TF" || 
					$auctionRow['how_rolled'] == "1F"
				)
			)
		) ||
		(
			$auctionRow['how_rolled'] == "FF" && 
			(
				$auctionRow['how_stored'] == "drawer size board" || 
				$auctionRow['how_stored'] == "oversize board" || 
				$auctionRow['how_stored'] == "1/2sh board" || 
				$auctionRow['how_stored'] == "insert board" ||
				($auctionRow['how_stored'] == "wc board" && max_size($measurements, 14, 22))
			)
		))
	)
	{
		if($auctionRow['how_rolled'] == "1F")
		{
			if($styleInfoRow['quantity'] == 1 || $styleInfoRow['quantity'] == 0)
				$after_description[] = "that this poster was folded across the middle at one time but has been laying flat for a long time and will be sent rolled in a tube.";
			else
				$after_description[] = "that these posters were folded across the middle at one time but have been laying flat for a long time and they will be sent rolled in a tube.";
		}
		elseif($styleInfoRow['quantity'] == 1 || $styleInfoRow['quantity'] == 0)
			$after_description[] = "that this poster was folded at one time but has been laying flat for a long time and will be sent rolled in a tube.";
		else
			$after_description[] = "that these posters were folded at one time but have been laying flat for a long time and they will be sent rolled in a tube.";
	}
	/*else if(in_array($auctionRow['how_rolled'], Array("laminated", "encap")))
		$messages .= "This was described as $auctionRow[how_rolled]. Please describe how you see fit and tell Phillip so he can have Aaron accont for it.";*/ //Commented out 2013-11-27 because never used. AK
	else if(in_array($auctionRow['type_code'], array("WC, regular", "WC, jumbo")))
	{
		if($styleInfoRow['quantity'] > 1)
		{
			switch($auctionRow['how_rolled'])
			{
				case "UF":
					$after_description[] = "that these window cards were never folded. Often window cards would be folded across the middle, because that would make them "
						."11\" x 14\", and they could then be sent with standard folded posters. Most collectors put an added value on a window card that has never been folded.";
					break;
				case "1F":
					$after_description[] = "*** PLEASE ENTER APPROPRIATE AFTER DESCRIPTION FOR 1F ***";
					break;
				case "pbacked":
					$after_description[] = $how_rolled_after_description_multiple["pbacked"];
					break;
				default:
					break;
			};
		}
		else
		{
			switch($auctionRow['how_rolled'])
			{
				case "UF":
					$after_description[] = "that this window card was never folded. Often window cards would be folded across the middle, because that would make them "
						."11\" x 14\", and they could then be sent with standard folded posters. Most collectors put an added value on a window card that has never been folded.";
					break;
				case "1F":
					$after_description[] = "*** PLEASE ENTER APPROPRIATE AFTER DESCRIPTION FOR 1F ***";
					break;
				case "pbacked":
					$after_description[] = $how_rolled_after_description["pbacked"];
					break;
				default:
					break;
			};
		}
	}
	else if($auctionRow['how_rolled'] == "pbacked" && $tblTypesRow['poster_simple'] == "lobby card")
	{
		$after_description[] = "that this lobby card has been paperbacked. What is paperbacking? ".
			"This means the lobby card was backed onto a very light paper backing (acid-free), ".
			"that keeps the \"feel\" of the lobby card similar to that of the original lobby ".
			"card. Some restorers prefer to paperback lobby cards on to a heavy backing, while ".
			"others prefer to use a very lightweight backing. The advantage of a lightweight ".
			"backing is that the card still \"feels\" like a regular lobby card, but a heavier".
			" backing makes restoration easier to do and less noticeable.";
			
		$condition_major_defects[] = "Prior to paperbacking, the card had ***. ".
			"Overall, the lobby card was in very good condition prior to ".
			"paperbacking. The card was nicely backed, and displays well!";
	}
	else if($auctionRow['how_rolled'] == "linen")
	{
		$condition_major_defects[] = "The poster had ***. Overall, the poster was in very ".
			"good condition prior to linenbacking. The poster was nicely backed, and ".
			"displays well!";
	}
	else if($auctionRow['how_rolled'] == "pbacked")
	{
		$condition_major_defects[] = "Prior to paperbacking, the poster had ***. Overall, ".
			"the poster was in very good condition prior to paperbacking. The poster was ".
			"nicely backed, and displays well!";
			
		//Added because Gary said the pbacked after description wasn't being inserted. AK 2014-03-27
		//Removed because Phil said it shouldn't be there. AK 2016-02-11
		/*if($styleInfoRow['quantity'] == 1 || $styleInfoRow['quantity'] == 0 || $styleInfoRow['quantity'] == "")
			$after_description[] = $how_rolled_after_description[$auctionRow['how_rolled']];
		else
			$after_description[] = $how_rolled_after_description_multiple[$auctionRow['how_rolled']];*/
	}
	else if($auctionRow['how_rolled'] == "UF" && $auctionRow['type_code'] == "Italian locandina")
	{
	  //2019-04-16 Added machine folded per bruce email "There is an autolister change I want (from Bruce)" SS
		//2017-02-06: Bruce/Phil say that this paragraph still applies to locandinas AK
		$after_description[] = "that this poster has never been machine folded! Most pre-1970 $poster_singular ".
			"posters were machine folded twice horizontally right off of the printing press. ".
			"It is very difficult to find an unfolded example of most pre-1970 $poster_singular posters ".
			"(note that most post-1970 $poster_plural were NOT machine folded, so they are pretty much only found ".
			"unfolded, and therefore this does not apply to those $poster_plural).";
	}
	else if($auctionRow['how_rolled'] == "UF" && $auctionRow['type_code'] == "insert")
	{
	  //2019-04-16 Added machine folded per bruce email "There is an autolister change I want (from Bruce)" SS
		//2017-02-06 Changed paragraph per Bruce "URGENT CONFIRM TO BRUCE IT HAPPENED changes to inserts and half-sheets for autolister and Auction History" AK
		$after_description[] = "that this poster has never been machine folded! Some pre-1970 insert posters ".
			"were machine folded twice horizontally at the poster exchange, while some were not. It can be ".
			"difficult to find an unfolded example of many pre-1970 insert posters (note that most ".
			"post-1970 inserts were NOT machine folded, so they are pretty much only found unfolded, and ".
			"therefore this does not apply to those inserts).";
	}
	else if($auctionRow['how_rolled'] == "UF" && ($auctionRow['type_code'] == "1/2sh"))
	{
	  //2019-04-16 Added machine folded per bruce email "There is an autolister change I want (from Bruce)" SS
		//2017-02-06 Changed paragraph per Bruce "URGENT CONFIRM TO BRUCE IT HAPPENED changes to inserts and half-sheets for autolister and Auction History" AK
		$after_description[] = "that this poster has never been machine folded! Some pre-1970 half-sheet posters were ".
			"machine folded twice horizontally at the poster exchange, while some were not. It can be difficult to ".
			"find an unfolded example of many pre-1970 half-sheet posters (note that most post-1970 ".
			"half-sheets were NOT machine folded, so they are pretty much only found unfolded, and therefore this ".
			"does not apply to those half-sheets).";
			
		/*
		$after_description[] = "that this poster has never been machine folded! Most pre-1970 $poster_singular ".
			"posters were folded once horizontally and once vertically right off of the printing press. ".
			"It is very difficult to find an unfolded example of most pre-1970 $poster_singular posters ".
			"(note that most post-1970 $poster_plural were NOT folded, so they are pretty much only found ".
			"unfolded, and therefore this does not apply to those $poster_plural).";
		*/
	}
	else if($auctionRow['how_rolled'] == "TF")
	{
		switch($auctionRow['type_code'])
		{
			case "24sh":
				$after_description[] = 'this 24-sheet poster was folded in a way that makes it resemble a tri-folded six-sheet '
									.  '(only much larger, of course, as there are more sections).  But the length and width of '
									.  'the folded 24-sheet is just about the same as that of a folded six-sheet or three-sheet.  '
									.  'This means that this 24-sheet needs to be sent in an oversized flat package, which will '
									.  'measure roughly 16" x 38", and approximately 2" high, and it will cost more to send than '
									.  'a regular sized package.';
				break;
			case "English 6sh":
			case "6sh":
				$after_description[] = 'this six-sheet poster was tri-folded only (which means there is one less vertical machine'
									.  ' fold than usual).  This means the poster has a significantly lesser number of folds than '
									.  'with six-sheets folded in the "regular" way, which is nice!  Tri-folded six-sheets need to'
									.  ' be sent in an oversized flat package, which measures roughly 13" x 30", so they cost more '
									.  'to send than regularly folded six-sheets, especially if they are sent to non-U.S. addresses.';
				break;
			case "English 3sh":
			case "3sh":
				$after_description[] = 'this three-sheet poster was tri-folded only (which means there is one less vertical machine'
									.  ' fold than usual).  This means the poster has a significantly lesser number of folds than with three-sheets folded'
									.  ' in the "regular" way, which is nice!  Tri-folded three-sheets need to be sent in an oversized flat package, which '
									.  'measures roughly 13" x 30", so they cost more to send than regularly folded three-sheets, especially if they are '
									.  'sent to non-U.S. addresses.';
				break;
			case "40x60":
				$after_description[] = 'this 40x60 was tri-folded only (which means there is one less vertical machine fold than usual).  '
									.  'This means the poster has a significantly lesser number of folds than with 40x60s folded in '
									.  'the "regular" way, which is nice!  This tri-folded 40x60 needs to be sent in an oversized flat '
									.  'package, which measures roughly 13" x 32", so they cost more to send than regularly folded 40x60s, '
									.  'especially if they are sent to non-U.S. addresses.';
				break;
			case "subway poster":
			case "subway, half":
				$after_description[] = 'this subway poster was tri-folded only (which means there is one less vertical machine fold than usual).'
									.  '  This means the poster has a significantly lesser number of folds than with subway posters folded in the "regular" way, '
									.  'which is nice!  Tri-folded subway posters need to be sent in an oversized flat package, which measures roughly 13" x 32", '
									.  'so they cost more to send than regularly folded subway posters, especially if they are sent to non-U.S. addresses.';
				break;
			case "1-stop poster":
				$after_description[] = 'this one-stop poster was tri-folded only (which means there is one less vertical machine fold than usual).  '
									.  'This means the poster has a significantly lesser number of folds than with one-stop posters folded in '
									.  'the "regular" way, which is nice!  Tri-folded one-stop posters need to be sent in an oversized flat package, '
									.  'which measures roughly 13" x 28", so they cost more to send than regularly folded one-stop posters, especially'
									.  ' if they are sent to non-U.S. addresses.';
				break;
			default: 
				if(stristr($tblTypesRow['poster_simple'], "poster"))
				{
					if($styleInfoRow['quantity'] == 1 || $styleInfoRow['quantity'] == 0 || $styleInfoRow['quantity'] == "")
						$after_description[] = $how_rolled_after_description[ $auctionRow['how_rolled'] ];
					else
						$after_description[] = $how_rolled_after_description_multiple[ $auctionRow['how_rolled'] ];
				}
				break;
		};
	}
	elseif(stristr($tblTypesRow['poster_simple'], "poster") || stristr($tblTypesRow['poster_simple'], "window card"))
	{
		//2011-10-20 Gary asked for the folded/unfolded after description for
		//S2 recreation posters to be removed.
		
		//2011-11-09 Dan said that Wavy Trifold was not showing up but should.
		//added WTF to this list. - AK
		
		//Removed this behavior for S2 Recreations per Gary - AK, 2012-10-30
		//Removed this behavior for lenticular per Gary - AK, 2012-10-31
		//Added lenticular paragraph per Dan, since we now have a how_rolled for lenticular. Wow, EXACTLY a year since the last change! - AK, 2013-10-31
		if($styleInfoRow['s2_recreation'] <= 0 && 
			(($auctionRow['how_rolled'] == "UF") ? ($tblTypesRow['never_folded'] == 0) : true))
		{
			if($styleInfoRow['quantity'] == 1 || $styleInfoRow['quantity'] == 0 || $styleInfoRow['quantity'] == "")
			{
				$after_description[] = $how_rolled_after_description[$auctionRow['how_rolled']];
				//mail("aaron@emovieposter.com", "test", print_r($auctionRow, true)."\n\n\n".print_r($styleInfoRow, true)."\n\n\n".print_r($tblTypesRow, true));
			}
			else
				$after_description[] = $how_rolled_after_description_multiple[$auctionRow['how_rolled']];
		}
	}
	elseif(in_array($auctionRow['type_code'], $pressbook_type_codes) || $auctionRow['type_code'] == "pb supplement" and $auctionRow['how_rolled'] == "1F")
	{
		$after_description[] = "that this pressbook was folded across the center at one time. Because of this, "
								."this pressbook can be sent in one of our standard size packages (if it is purchased "
								."together with pressbooks that can NOT be folded, then it will of course have to be "
								."sent in one of our oversized packages).  But if this pressbook is purchased by itself "
								."(or solely with other pressbooks that can be folded), then the shipping will be "
								."much lower, because it fits in a standard package. ";
	}
	
	//S2 recreation posters - AK, 2011-11-01
	if($styleInfoRow['s2_recreation'] > 0)
	{
		//Changed to new wording per Bruce/Gary. - AK, 2012-10-30
		//Changed to new wording per Phil. - AK, 2012-11-02
		//Changed to new 3sh/1sh wordings per Gary - AK, 2017-03-02

		if($auctionRow['type_code'] == "3sh")
		{
			$after_description_no_note[] = "<b style='color:red'>This poster is one of the only multi-piece posters from the ".
				"\"S2 Art Group\", printed in 2 sections, because of its very large size. This company bought 100-year-old ".
				"lithograph presses, completely refurbished them, and created stone slabs that allowed them to make nearly ".
				"perfect replicas of classic movie posters (and in some cases making a stone litho version of a poster that was ".
				"not originally a stone litho!). They were printed in exactly the same way the posters were originally printed ".
				"(re-creating them down to the finest detail within the art, and even including the tiny writing from the bottom ".
				"of the poster). These posters were printed between 1997 and 2003, and were NOT printed when the movies were ".
				"originally released. It is super high quality and measures $measurementsWithMetric ".
				"<a href='http://www.emovieposter.com/learnmore/?page=s2_recreation'>(Learn More)</a>.</b> Note that, in addition ".
				"to the one-sheets that they recreated, they also did recreation three-sheets of King Kong, Metropolis, and ".
				"Casablanca. Each of those was printed on three one-sheet sized pieces of the exact same paper used for the ".
				"one-sheets, designed to overlap (just as three-sheets were originally printed many decades ago). Those three ".
				"three-sheet recreations are really remarkable and are incredible to view in person (I have seen them all ".
				"linenbacked and framed).";
		}
		else
		{
			$after_description_no_note[] = "<b style='color:red'>This poster is from the \"S2 Art Group\". This company bought ".
				"100-year-old lithograph presses, completely refurbished them, and created stone slabs that allowed them to ".
				"make nearly perfect replicas of classic movie posters (and in some cases making a stone litho version of a ".
				"poster that was not originally a stone litho!). They were printed in exactly the same way the posters were ".
				"originally printed (re-creating them down to the finest detail within the art, and even including the tiny ".
				"writing from the bottom of the poster). These posters were printed between 1997 and 2003, and were NOT ".
				"printed when the movies were originally released. It is super high quality and measures ".
				"$measurementsWithMetric <a href='http://www.emovieposter.com/learnmore/?page=s2_recreation'>(Learn More)</a>.".
				"</b>  <b>IMPORTANT!</b> Note that the S2 Art Group also had a lower quality 24\" x 36\" series of one-sheet ".
				"recreations that retailed for $49 each, but are <b>NOT</b> stone lithographs. The recreation offered here one ".
				"of the high quality recreations this company did, and is a true stone lithograph. It will look incredible on ".
				"the new owner's wall, and it will sell for a fraction of the cost of the first release poster with the same ".
				"image, and will sell for a fraction of the price of that first release poster!";
		}	
		
		
		if(stristr($auctionRow['style_info'], "w/ C.O.A.") !== false || stristr($auctionRow['style_info'], "w/ COA") !== false)
		{
			$after_description_no_note[] = "This S2 re-creation comes with a \"certificate of authenticity\" from \"The RE Society Ltd.\", ".
				"stating when the poster was produced, and detailing the methods used.";
		}
		
		/*
		$after_description_no_note[] = "<b style='color:red'>This $poster_simple_name is from the \"S2 Art Group\". ".
			"This company bought 100 year-old lithograph presses, completely refurbished them, and recreated stone ".
			"litho printing plates for classic movie posters (and in some cases making a stone litho version of a ".
			"poster that was not originally a stone litho!). They were printed in exactly the same way the posters ".
			"were originally printed (re-creating them down to the finest detail within the art, and even including ".
			"the tiny writing from the bottom of the poster). These posters were printed between 1997 and 2003, and ".
			"were NOT printed when the movies were originally released. It is super high quality and measures ".
			"$measurementsWithMetric <a href='http://www.emovieposter.com/learnmore/?page=s2_recreation'>(Learn More)</a>.</b>";*/
		
		/*
		$after_description_no_note[] = "<b style='color:red'>This poster is ".
			"from the \"S2 Art Group\" and is a modern stone litho printed using ".
			"the original printing plates (these were printed between 1997 and 2003, ".
			"and were NOT printed when the movies were originally released). It is ".
			"super high quality and measures $measurementsWithMetric ".
			"<a href='http://www.emovieposter.com/learnmore/?page=s2_recreation'>(Learn More)</a>.</b>";
		*/
	}
	
	//Slovak before 1993
	if($auctionRow['type_code'] == "Slovak" && intVal(substr($descriptionsRow['Year/NSS'], 0, 4)) < 1993)
		$after_description[] = "that in 1993, Czechoslovakia split into two countries: The Czech Republic and Slovakia. "
							.  "However, prior to that time, there were posters printed for each group of people within "
							.  "Czechoslovakia, meaning that there were both \"Czech\" posters and \"Slovak\" posters "
							.  "printed for many movies. The poster offered here is the Slovak poster for this movie "
							.  "and it is slightly different than the Czech poster for the same movie.";
	
	//Benton WC
	if(stristr($auctionRow['type_code'], "WC") && $styleInfoRow['benton'] == 1)
		$after_description[] = "that this card is made by the Benton Card Company which is a different company than the one that printed "
							.  "most of the window cards at that time.  The Benton Card Company made mostly two-color window cards "
							.  "that used blown up newspaper ads.  They sold their window cards cheaper than the regular ones, so "
							.  "many theaters used them.  In the 1970s, someone bought the company and made reprints of earlier titles, "
							.  "but those are clearly marked.  The window card offered here is from the original release of this movie!";
		
	//Photolobbies
	if($auctionRow['type_code'] == "LC, photolobbies other")
	{
		if($styleInfoRow['quantity'] > 1)
		{
			$after_description[] = "that on this movie, and on several other releases of the 1930s and 1940s, lesser studios "
								.  "sometimes issued a set of very different looking lobby cards!  These were a \"photolobby\" set with "
								.  "glossy color scenes from the movie, and were printed on the type of paper used for 11x14 stills, not "
								.  "the type used for lobby cards.  It is possible that some of these movies also had a regular lobby "
								.  "card set, but I believe in most cases there was only a photolobby set.";
		}
		else
		{
			$after_description[] = "that on this movie, and on several other releases of the 1930s and 1940s, lesser studios "
								.  "sometimes issued a set of very different looking lobby cards!  This was part of a \"photolobby\" set with a "
								.  "glossy color scene from the movie, and was printed on the type of paper used for 11x14 stills, not "
								.  "the type used for lobby cards.  It is possible that some of these movies also had a regular lobby "
								.  "card set, but I believe in most cases there was only a photolobby set.";
		}
	}
	
	//video/theatrical
	if(stristr($auctionRow['style_info'], "video/theatrical") !== false)
	{
		$after_description[] = "that this poster is from the video release of this movie, and we ".
			"think that it also served as the theatrical release poster as well. In the 1980s, ".
			"most of the \"adult\" cinemas were closing up, and the people who made such movies ".
			"only printed a single poster in most cases for use for both the small theatrical ".
			"release and the release on video. If anyone knows of a different poster for a ".
			"theatrical release of this movie, please <a target=\"_blank\" ".
			"href=\"http://emovieposter.com/mail/contact.php?sel=9&sno=\">e-mail us</a> and ".
			"we will post it here.";
	}
	
	//Publicity still
	if($poster_simple_name == "still" && stristr($auctionRow['style_info'], "publicity"))
	{
		$after_description[] = "that many celebrities kept multiple 8\" x 10\" (or smaller) ".
			"copies of good publicity images of themselves to sign for fans, and also many ".
			"memorabilia shops sold such copy stills themselves. While they are not original ".
			"theater-used stills, they are usually excellent clear copies, with a great ".
			"shot of that celebrity in their heyday, which, when signed by the celebrity, ".
			"makes for a wonderful framed picture on any fan's wall!";
	}
	
	//CDs
	if($auctionRow['type_code'] == "CDs" && $styleInfoRow['additional_titles'] != "")
	{
		$titles = array_map("trim", explode(";", $styleInfoRow['additional_titles']));
		if(count($titles) > 0)
		{
			$txt = "that ";
			if($styleInfoRow['quantity'] > 1)
				$txt .= "these CDs also include music from ";
			else
				$txt .= "this CD also includes music from ";
			
			if(count($titles) > 1)
				$txt .= "the following titles: " . implode(", ", $titles) . ".";
			else
				$txt .= $titles[0] . ".";
			
			$after_description[] = $txt;
		}				
	}
	
	
	
	if(preg_match("/(^|;) *AP *($|;)/i", $auctionRow['style_info']))
	{
		$after_description[] = "that this print is an \"artist's proof\" that was printed in a ".
			"much lesser amount for the artist by the printer (note that \"AP\" written on the ".
			"print). Since AP's are \"closer to the artist's hand\" they tend to be much more ".
			"valuable than the prints of a signed and numbered limited edition! ";
	}
	
	
	$after_description = array_unique($after_description);
	//Glue them together, ignoring blank entries
	$after_description = array_values(array_filter($after_description, 'strlen'));
	
	if(count($after_description) > 0){
	    if(substr($after_description[0],0,3)=="***"){
	      $after_description[0] = substr($after_description[0],3);
	    }else{
	       $after_description[0] = "Note " . $after_description[0];  
	    }
  }
	
	if(count($after_description) == 2){
	  if(substr($after_description[1],0,3)=="***"){
        $after_description[1] = substr($after_description[1],3);
      }else{
		    $after_description[1] = "Also note " . $after_description[1];
      }
  }
	
	if(count($after_description) > 2)
	{
		for($x = 1; $x < count($after_description)-1; $x++){
		  if(substr($after_description[$x],0,3)=="***"){
		    $after_description[$x] = substr($after_description[$x],3);
      }else{
        $after_description[$x] = "Also note " . $after_description[$x];
      }
			
    }
		
    if(substr($after_description[count($after_description)-1],0,3)=="***"){
      $after_description[count($after_description)-1] = substr($after_description[count($after_description)-1],3);
    }else{
		  $after_description[count($after_description)-1] = "Finally, note " . $after_description[count($after_description)-1];
    }
	}
	
	if(!empty($after_description_no_note))
		$after_description = array_merge($after_description_no_note, $after_description);
	
	if(!empty($after_description))
		$after_description = implode(" ", $after_description);
	else
		$after_description = "";
	
	//Do the measurements code
	$after_description = str_replace("[measurements]", $measurements, $after_description);
	
	//type_pre_short
	
	if(stristr($auctionRow['type_code'], "t-shirt") !== false){
	  $tshirtSizeMap = array(
    'Extra Small'=>'extra small',
    'Small'=>'small',
    'Medium'=>'medium',
    'Large'=>'large',
    'Extra Large'=>'extra large',
    'Extra Extra Large'=>'extra extra large',
    '3X Large'=>'3X large',
    '4X Large'=>'4X large',
    
    );
    $type_pre_short[] = "size: " . $tshirtSizeMap[$auctionRow['measurements']];
  }
	
	if(stristr($tblTypesRow['type_long_code'], "autographed") === false)
		$type_pre_short[] = ($styleInfoRow['signedby'] != "") ? "signed" : "";
	
	if(preg_match("/(?:^| |;) *#?([0-9]+\/[0-9]+)(?: |;|$)/", $auctionRow['style_info'], $matches) && 
		stristr($auctionRow['style_info'], "33 1/3") === false)
		$type_pre_short[] = "#".$matches[1];
	
	
	//if($styleInfoRow['quantity'] > 1 && !in_array($tblTypesRow['poster_simple'], array("still", "lobby card"))) //Added per Bruce 2017-06-07 AK. See "Re: changing title for sets of posters"
	//	$type_pre_short[] = "set of ".$styleInfoRow['quantity'];
	//else
	//Removed per Phil - he thought it would confuse people into thinking it's a full set.
	
	//Added per Phil and Bruce 2017-06-13 AK
	if($styleInfoRow['quantity'] > 1 && in_array($auctionRow['auction_code'], 
		array("INS_HALF", "LGROLLED", "UF1SH", "UFLARGE", "UF_NON", "UF_SPEC")))
	{
		$type_pre_short[] = "group of ".$styleInfoRow['quantity'];
	}
  else if($styleInfoRow['quantity'] > 1 && in_array($auctionRow['type_code'], 
		array("transparency")))
	{
		$type_pre_short[] = "group of ".$styleInfoRow['quantity'];
	}
	else if(stristr($auctionRow['type_code'], "busta") !== false) //Added 20130321 per Phil. AK
	{
		$type_pre_short[] = ($styleInfoRow['quantity'] > 1) ? "set of ".$styleInfoRow['quantity'] : ""; 
	}
	elseif($auctionRow['type_code'] != "CDs")
	{
		$type_pre_short[] = ($styleInfoRow['quantity'] > 1) ? $styleInfoRow['quantity'] : ""; 
	}
	 
	$type_pre_short[] = ($styleInfoRow['otherco'] == 1) ? "Other Company" : "";
	//$type_pre_short[] = (stristr($styleInfoRow['additionaldetails'], "Eng/Ital")) ? "Eng/Ital" : "";
	$type_pre_short[] = ($styleInfoRow['doublesided'] == "twosided") ? "2-sided" : "";
	$type_pre_short[] = (stristr($styleInfoRow['additionaldetails'], "TV")) ? "TV" : "";
	$type_pre_short[] = ($auctionRow['how_rolled'] == "linen") ? "linen" : "";
	$type_pre_short[] = ($styleInfoRow['spanus'] == 1 /*|| 
						 ($styleInfoRow['language'] == "Spanish" && $tblTypesRow['country'] == "the U.S.")*/) ? "Spanish/US" : "";
	$type_pre_short[] = ($styleInfoRow['video'] == 1 && $auctionRow['type_code'] != "video poster") ? "video" : "";
	
	
	
	
	/*
	//2012-10-10: I removed this behavior because Dan & Gary said they have to undo this behavior 90% of the time. AK
	if($styleInfoRow['alternatestyleinput'] != "")
	{
		$type_pre_short[] = $styleInfoRow['alternatestyleinput'];
		
		if($styleInfoRow['style'] == "N/A")
			$type_pre_short[] = "style";
	}
	*/
	
	
	if(stristr("1sh", $auctionRow['type_code']) && strcasecmp($styleInfoRow['style'],"Style A")==0){
	  //2019-01-03 Don't add Style A to 1sh of any kind - see email: STEVEN BRUCE REQUEST Fwd: Re: Review Form
    //Mailer::mail('steven@emovieposter.com','1sh with Style A caught',print_r($styleInfoRow,true)."\n\n".$style_info."\n\n".$auctionRow);
  }else{
    $type_pre_short[] = (($styleInfoRow['style'] != "N/A") ? (($styleInfoRow['style'] == "other") ? "style " . $styleInfoRow['styleother'] : $styleInfoRow['style']) : "");  
  }
	  
	
	
	
	//20151111 Todd had me remove "reviews" from this list
	foreach(array("revised", "holofoil", "awards", "foil", "heavy stock", 
		"wilding", "IMAX", "Kilian", "Cinerama", "roadshow", "3D", "2D", "printer's test",
		"export", "Roadshow", "long", "exhibition", "art exhibition", "museum", "commercial", 
		"pre-war", "studio style", "NSS style", "recalled", "recalled style", 
		"first printing", "second printing", "third printing", "fourth printing", "fan club", 
		"East Hemi", "West Hemi", "video/theatrical", "mylar") as $x)
	{
		if(preg_match("/(^|;) *".preg_quote($x, "/")."/", $auctionRow['style_info']))
			$type_pre_short[] = $x;
	}
	
	if(preg_match("/(^|;) *AP *($|;)/i", $auctionRow['style_info']))
	{
		$type_pre_short[] = "artist's proof";
	}
	
	if(preg_match("/(^|;) *AA *($|;)/i", $auctionRow['style_info']))
	{
		$type_pre_short[] = "awards";
	}
	
	if($styleInfoRow['international'])
	{
		$type_pre_short[] = "int'l";
		
		if($styleInfoRow['intlreason'] == "Spanish" || $styleInfoRow['intlreason'] == "French")
		{
			$type_pre_short[] = $styleInfoRow['intlreason']." language";
		}
	}
	
	
	$type_pre_short[] = ($styleInfoRow['doublesided'] == "DS") ? "DS" : "";
	$type_pre_short[] = ($styleInfoRow['advance'] == "advance") ? "advance" : "";
	$type_pre_short[] = ($styleInfoRow['advance'] == "advance/teaser") ? "advance" : "";
	$type_pre_short[] = ($styleInfoRow['advance'] == "teaser") ? "teaser" : "";
	$type_pre_short[] = ($styleInfoRow['chapternumber'] > 0) ? "chapter " . $styleInfoRow['chapternumber'] : "";
	$type_pre_short[] = ($auctionRow['type_code'] == "1sh, S2 recreations") ? "S2 recreation" : "";
	$type_pre_short[] = ($styleInfoRow['doublesided'] == "ds") ? "DS" : "";
	$type_pre_short[] = ($styleInfoRow['military'] == 1) ? "military" : "";
	$type_pre_short[] = (($styleInfoRow['color'] == "color" && $styleInfoRow['minilc'] != 1 || in_array($auctionRow['type_code'], Array("8x10, color, numbered, multiples", "8x10, color, numbered, singles", "8x10, color, UN-numbered, multiples", "8x10, color, UN-numbered, singles",))) ? "color" : "");
	$type_pre_short[] = ($styleInfoRow['benton'] == 1 ? "Benton" : "");
	$type_pre_short[] = ($styleInfoRow['papertype'] == "deluxe" ? "deluxe" : "");
	$type_pre_short[] = ($styleInfoRow['candid'] == 1 ? "candid" : "");
	$type_pre_short[] = $styleInfoRow['cd_type'];
	$type_pre_short[] = (preg_match("/(^| |;) *stage( |;|$)/", $auctionRow['style_info']) ? "stage play" : "");
	$type_pre_short[] = ($auctionRow['how_rolled'] == "lenticular" ? "lenticular" : "");
	
	
	
	
	
	if($auctionRow['type_code'] == "script")
	{
		$split = array_map("trim", explode(";", $auctionRow['style_info']));
		
		foreach($split as $s)
		{
			if(stripos($s, "draft") !== false || 
				//stripos($s, "revis") !== false || 
				stripos($s, "continuity") !== false ||
				stripos($s, "censorship") !== false ||
				stripos($s, "release dialogue") !== false)
				$type_pre_short[] = $s;
		}
	}
	
	if((stristr($auctionRow['type_code'], "comic book") !== false || stristr($auctionRow['type_code'], "underground comix") !== false) && preg_match("/(#[0-9]+)/", $auctionRow['style_info'], $matchy))
    $type_pre_short[] = $matchy[0];
	
	
	$type_pre_short = implode(" ", array_filter($type_pre_short, 'strlen')); //Glue them together, ignoring blank entries
	
	$type_pre_short = str_replace("revised revised", "revised", $type_pre_short);

	//Condition_major_defects
	#$condition_major_defects[] = (($auctionRow['type_code'] == "presskit") ? "Note that the vast majority of the presskits we are auctioning this week were carefully stored away for many years, and the stills are usually in really excellent condition. Sometimes the folder that contains the stills has some wear on it, and you should be able to see that in our super-sized image. We give an overall condition grade to each presskit, but remember that almost all of the stills are in excellent condition." : "");
	
	if($auctionRow['type_code'] == "script")
	{
		$condition_major_defects[] = "See our multiple images to get a good sense of the exact condition of this script.";
	}
	
	if($auctionRow['type_code'] == "presskit")
	{
		$condition_major_defects[] = "Many of the presskits we are auctioning ".
			"this week were carefully stored away for many years, and the ".
			"stills are in really excellent condition. Sometimes the folder ".
			"that contains the stills has some minor wear on it, and you ".
			"should be able to see that in our super-sized image.";
	}
	
	$condition_major_defects[] = ((stristr($auctionRow['type_code'], "magazine") && $auctionRow['type_code']!='magazine page' && $auctionRow['type_code'] != "magazine ad") ? "Note that we have attempted to check to see if the magazine is complete and uncut, and we believe that it is.  However, there is a slight possibility that we might have missed a small cut or a page that was neatly removed, and if that happens, you can return the magazine.  We have solely provided an overall description of the condition of the magazine, but you can also refer to our super-sized images.  Please bid (or not bid) based on that overall grade and by viewing the super-sized images." : "");
		
	
	
	/*
		- Right, I see. Do you just want me to remove the paragraph in the condition since you already have it in the after description?
		- No, we absolutely must have it in all fields possible so that no idiot could ever miss it. If you get my drift.
		2014-10-03
	*/
	if($styleInfoRow['taped'] == 1 || $styleInfoRow['glued'] == 1)
	{
		$temp = "The theater that used this poster ";
		
		if($styleInfoRow['taped'] == 1 && $styleInfoRow['glued'] == 1)
			$temp .= "taped and glued";
		elseif($styleInfoRow['taped'] == 1)
			$temp .= "taped";
		elseif($styleInfoRow['glued'] == 1)
			$temp .= "glued";
			
		$temp .=" the pieces together before putting them on display (this is typical of many actual theater-used posters in multiple pieces).";
				
		$condition_major_defects[] = $temp;
	}
		
		
		
	$condition_major_defects = array_values(array_filter($condition_major_defects, 'strlen'));
	$condition_major_defects = implode(" ", $condition_major_defects);
	

	//If it's a bulk lot, we do things differently. We just did all that work for nothing.
	if($auctionRow['bulk_lot_id'] != "")
	{
		$type_long = "";
		$after_description = "";
		$type_pre_short = "";
		$condition_major_defects = "";
		$mrLister = "";
		
		$query = "SELECT * FROM bulk_lots WHERE id=$auctionRow[bulk_lot_id]";
		$r = mysql_query($query);
		if(mysql_num_rows($r) == 1)
		{
			$row = mysql_fetch_assoc($r);
			$ptitles = json_decode($row['titles_photographed'], true);
			$rtitles = json_decode($row['titles_unphotographed'], true);
			$titles = array_merge($ptitles, $rtitles);			
			
			$number_of_items = 0;
			
			//Get years
			$years = Array();
			
			foreach($titles as $t)
			{
				$number_of_items += $t['quantity'];
				
				if(preg_match("/R[\d]{2}/", $t['style_info'], $match))
				{
					if(substr($match[0], 1, 1) == 0)
						$years[] = 200 . substr($match[0], -1);
					else
						$years[] = 19 . substr($match[0], 1);
				}
				else
				{
					$title = mysql_real_escape_string($t['title']);
					$query = "SELECT `Year/NSS` FROM descriptions WHERE TITLE = '$title'";
					$r = mysql_query($query) or die_with_honor(__FILE__, __LINE__, $query, mysql_error());
					if(mysql_num_rows($r))
					{
						$year = substr(mysql_result($r,0,0), 0, 5);
						if($year[4] != "s")
							$year = substr($year, 0, 4);
						if(preg_match("/^[\d]{4}s?$/", $year))
							$years[] = $year;
					}
				}
			}		
			
			//Mailer::mail('steven@emovieposter.com','debug - autolister', var_export($years,true));
			
			$autoList['quantity'] = $number_of_items;
			
			if(is_array($ptitles))
			{
				foreach($ptitles as $p)
				{
					if(preg_match("/(January|February|March|April|May|June|July|August|September|October|November|December) ([\d]{4})/", $p['style_info'], $matchy))
						$years[] = $matchy[2];
				}
			}
			
			if(is_array($rtitles))
			{
				foreach($rtitles as $r)
				{
					unset($matchy);
					if(preg_match("/(January|February|March|April|May|June|July|August|September|October|November|December) ([\d]{4})/", $r['style_info'], $matchy))
						$years[] = $matchy[2];
				}
			}
			
			$years = array_unique($years);
			sort($years);
			
			$first = $years[0];
			$last = $years[count($years)-1];
			
			if($first == $last)
				$mrLister = $first;
			else
				$mrLister = "$first - $last";
				
			//Default measurements
			if($auctionRow['type_code'] == "1sh")
				$autoList['measurements'] = 'All one-sheets are 27" x 40" or 27" x 41" unless otherwise noted.';
			else
				$autoList['measurements'] = $tblTypesRow['measurements'];
			
		}
	}
	
	//Put everything into one associative array
	$autoList['type_long'] = $type_long;
	$autoList['after_description'] = String::xtrim($after_description);
	$autoList['type_pre_short'] = $type_pre_short;
	$autoList['film_MrLister'] = $mrLister;
	$autoList['condition_major_defects'] = $condition_major_defects;
	
	//Round our measurements
	//Remove quotes
	$roundedMeasurements = str_replace("\"", "", $measurements);
	$roundedMeasurements = str_replace("\'", "", $roundedMeasurements);
	
	//Convert fractions
	$fractions = Array(" 1/2", " 1/4", " 3/4", " 1/8", " 3/8", " 5/8", " 7/8");
	$decimals  = Array(".5",  ".25", ".75", ".125", ".375",".625",".875");
	$roundedMeasurements = str_replace($fractions, $decimals, $roundedMeasurements);
	
	//Remove spaces
	$roundedMeasurements = str_replace("approximately ", "", $roundedMeasurements);
	
	//Round numbers
	preg_match_all("/[\d]+(\.[\d]+|)/", $roundedMeasurements, $matches);
	if(count($matches) == 2){
  	if (!empty($matches[0][2])) {
      $roundedMeasurements = round(floatval($matches[0][0])) . "x" . round(floatval($matches[0][1])) . "x" . round(floatval($matches[0][2]));
    } else {
      $roundedMeasurements = round(floatval($matches[0][0])) . "x" . round(floatval($matches[0][1]));
    }
  } 
  
	
	//Get repro-ness
	$repro = (stripos($styleInfoRow['additionaldetails'], "repro") !== false) ? " REPRO" : "";
	
	//Get type_short
	switch($auctionRow['type_code'])
	{
		case "1sh":
			if(!$trimmed && (floatval($matches[0][0]) < 26 or floatval($matches[0][1]) < 39))
				$autoList['type_short'] = $roundedMeasurements." ".$tblTypesRow['type_short'];
			else
				$autoList['type_short'] = $tblTypesRow['type_short'];
			break;
      
    case "cigar box label":
    case "crate label":
      $autoList['type_short'] = $roundedMeasurements." ".$tblTypesRow['type_short'];
      break;
			
		case "LC":
			$matches = Array();
			if($tblStyleInfo['number'] != "")
				$autoList['type_short'] = "LC #" . $tblStyleInfo['number'];
			elseif(preg_match("/#[\d]{1}/", $auctionRow['style_info'], $matches))
				$autoList['type_short'] = "LC #" . $matches[0][1];
			else
				$autoList['type_short'] = "LC";
			break;
		
		case "LC, singles":
			$matches = Array();
			if($tblStyleInfo['number'] != "")
				$autoList['type_short'] = "LC #" . $tblStyleInfo['number'];
			elseif(preg_match("/#[\d]{1}/", $auctionRow['style_info'], $matches))
				$autoList['type_short'] = "LC #" . $matches[0][1];
			else
				$autoList['type_short'] = "LC";
			break;
		
		case "war poster":
			if(stripos($auctionRow['style_info'], "wwii") !== false)
				$war = "WWII war poster";
			elseif(stripos($auctionRow['style_info'], "wwi") !== false)
				$war = "WWI war poster";
			else
				$war = "war poster";
			
			if($styleInfoRow['nationality'] != "")
				$autoList['type_short'] = "$roundedMeasurements $styleInfoRow[nationality] $war";
			else
				$autoList['type_short'] = "$roundedMeasurements $war";
				
			break;
		
		//Added per "fun with new type codes!" email, 2011-09-02, AK
		case "advertising poster":
		case "art print":
		case "motivational poster":
		case "museum/art exhibition":
		case "music poster":
		case "political campaign":
		case "war poster":
		//Per Bruce we are adding a bunch of "special" types to have the measurement before them. 2014-10-01 AK
		case "special poster":
		case "commercial poster":
		case "music poster":
		case "museum/art exhibition":
		case "motivational poster":
		case "advertising poster":
		case "travel poster":
		case "circus poster":
		case "art print":
		case "film festival poster":
		case "political campaign":
		case "magic poster":
		case "stage poster":
		case "TV poster":
		case "video poster":
		case "static cling poster":
		case "mini-poster":
		case "circus poster":
		//2016-06-17: Added repro poster typ ecode
		case "reproduction poster":
			if($styleInfoRow['nationality'] != "")
				$autoList['type_short'] = (substr_count($roundedMeasurements, 'x')==1 ? "$roundedMeasurements " : "") . "$styleInfoRow[nationality] $tblTypesRow[type_short]";
			else
				$autoList['type_short'] = (substr_count($roundedMeasurements, 'x')==1 ? "$roundedMeasurements " : "") . "$tblTypesRow[type_short]";
			break;

		case "transparency":
		case "negative":
		//2012-11-28: Removed "Japanese still" because already used below
			$autoList['type_short'] = "$roundedMeasurements $tblTypesRow[type_short]";
			break;
		
		//2012-05-10 Phil asked for this behavior for the "Japanese press sheet" code
		case "Japanese press sheet":
			$autoList['type_short'] = "$tblTypesRow[demonym] $roundedMeasurements press sheet";
			break;
		
		case "Czech misc":
		case "East German misc":
		case "English misc":
		case "German misc":
		case "Italian misc":
		case "Japanese misc":
		case "Polish misc":
		case "Russian misc":
		case "Spanish misc":
		case "Russian":
		case "Thai":
		case "Hungarian":
		case "Yugoslavian":
		case "Swedish misc": //Per Phillip, "Re: AARON New Type Code that needs your attention" email. 2014-09-01
		
			$autoList['type_short'] = "$tblTypesRow[type_short] $roundedMeasurements";
			break;
		
		//Per Gary, "autolister change" email. 2013-10-22, AK
		case "Italian pbusta 26x38":
		
		//Per Matt & Bruce, Auction Title (type_short) Request. 2015-12-02 AK
		case "Italian photobusta large":
		case "Italian photobusta medium":
		case "Italian photobusta small":
			$autoList['type_short'] = "Italian $roundedMeasurements pbusta";
			break;
		
		case "German still":
		case "Japanese still":
			$autoList['type_short'] = "$tblTypesRow[demonym] $decimalMeasurements still";
			break; 

                case "photo, signed":
                       //gary measurements in type short 2018-10-05
                       $autoList['type_short'] = "signed $roundedMeasurements photo";
                       break;
		
		case "8x10":
			if($styleInfoRow['minilc'] == 1)
			{
				$autoList['type_short'] = "8x10$repro mini LC";
				#if($styleInfoRow['numbered'] == "number" && $styleInfoRow['number'] != 0)
					#$autoList['type_short'] .= " #" . $styleInfoRow['number'];
			}
			elseif($styleInfoRow['newsstill'] == 1)
				$autoList['type_short'] = "8x10 news photo";
			else
				$autoList['type_short'] = "8x10$repro still";
				
			if($styleInfoRow['keybook'] == 1)
				$autoList['type_short'] = ($specifiedMeasurement === true) ? "$roundedMeasurements key book {$poster_simple_name}" : "8x11 key book {$poster_simple_name}";
			
			elseif($styleInfoRow['numbered'] == "number" && $styleInfoRow['number'] != 0)
				$autoList['type_short'] .= " #" . $styleInfoRow['number'];
			
			break;
		
		case "book":
			$autoList['type_short'] = (($styleInfoRow['book_cover'] != "n/a") ? $styleInfoRow['book_cover'] . " " : "") . "book";
			break;
		
		case "presskit":
			$type_short = "presskit";
			
			if($styleInfoRow['stills'] > 0)
				$type_short .= " w/ $styleInfoRow[stills] still";
			
			if($styleInfoRow['stills'] > 1)
				$type_short .= "s";
			
			$autoList['type_short'] = $type_short;
			break;
			
		case "index card, signed":
			$autoList['type_short'] = "signed $roundedMeasurements index card";
			break;
		
		case "cut album page":
			$autoList['type_short'] = "signed $roundedMeasurements cut album page";
			break;
		
		case "5x7 fan photo":
    case "fan photo":
			$autoList['type_short'] = "$roundedMeasurements fan photo";
			break;
      
    case "Miscellaneous":
      $autoList['type_short'] = (!empty($styleInfoRow['additionaldetails']) ? $styleInfoRow['additionaldetails'] : "misc item");
      //Mailer::mail('steven@emovieposter.com','debug - ' . __FILE__ . ":" . __LINE__, var_export($autoList,true) . "\n\n" . var_export($styleInfoRow,true) . "\n\n" . var_export($tblTypesRow,true) . "\n\n" . var_export($auctionRow,true));
      break;
      
    case "movie promo item":
      $autoList['type_short'] = (!empty($styleInfoRow['additionaldetails']) ? $styleInfoRow['additionaldetails'] : "movie promo item");
      //Mailer::mail('steven@emovieposter.com','debug - ' . __FILE__ . ":" . __LINE__, var_export($autoList,true) . "\n\n" . var_export($styleInfoRow,true) . "\n\n" . var_export($tblTypesRow,true));
      break;
      
    case "t-shirt":
      $autoList['type_short'] = $auctionRow['type_code'];
      //Mailer::mail('steven@emovieposter.com','debug - ' . __FILE__ . ":" . __LINE__, var_export($autoList,true) . "\n\n" . var_export($styleInfoRow,true) . "\n\n" . var_export($tblTypesRow,true));
      break;
      
		default:
			$autoList['type_short'] = $tblTypesRow['type_short'];
			break;
	};
	
	//2014-10-10: A customer complained that a poster said the wrong measurements in the title.
	//This was because the type_short had not been using custom measurements. Phillip told me to do this. AK
	//-----
	//2016-05-05: Bruce no longer wants 30x40 or 40x60 auction titles to have the actual size.
	//See email "Exception to 30x40 and 40x60 Type_Code Auction Titles"
	if($tblTypesRow['insert_measurement'])
	{
	  //2019-04-25 I'm not sure why this uses the type_short from the DB instead of what's generated above
	  //Adding exception for miscellaneous and movie promo item since they populate type_short from [additionaldetails]. SS
	  if(in_array($auctionRow['type_code'],array("Miscellaneous","movie promo item","t-shirt"))){
		  $autoList['type_short'] = "$autoList[type_short]";
      //Mailer::mail('steven@emovieposter.com','debug - ' . __FILE__ . ":" . __LINE__, var_export($autoList,true) . "\n\n" . var_export($styleInfoRow,true) . "\n\n" . var_export($tblTypesRow,true) . "\n\n" . var_export($auctionRow,true));
    }else{
      $autoList['type_short'] = "$tblTypesRow[type_short] $roundedMeasurements";
      //Mailer::mail('steven@emovieposter.com','debug - ' . __FILE__ . ":" . __LINE__, var_export($autoList,true) . "\n\n" . var_export($styleInfoRow,true) . "\n\n" . var_export($tblTypesRow,true) . "\n\n" . var_export($auctionRow,true));
    }
	}
	
	if($styleInfoRow['advertising'] == 1)
	{
		$autoList['type_short'] = "advertising ".$autoList['type_short'];
	}
	
	if(($auctionRow['type_code'] == "oversize still") || stristr($auctionRow['type_code'], "11x14") || 
		(stristr($auctionRow['type_code'], "8x10") && $styleInfoRow['minilc'] != 1 && 
			!stristr($auctionRow['type_code'], "mini LC") && $auctionRow['type_code'] != "8x10 LC" && 
			!stristr($auctionRow['type_code'], "foh") && $styleInfoRow['keybook'] != 1 && 
			preg_match("/[\d\/ ]+\" x [\d\/ ]+\"/", $measurements))
	)
	{
		//Remove quotes
		$roundedMeasurements = str_replace(array("\" x", "\""), array("x", ""), $measurements);
		$roundedMeasurements = str_replace(array("\' x", "\'"), array("x", ""), $roundedMeasurements);
		$roundedMeasurements = str_replace("x ", "x", $roundedMeasurements);
		
		//Convert fractions
		$fractions = Array(" 1/2", " 1/4", " 3/4", " 1/8", " 3/8", " 5/8", " 7/8");
		$decimals  = Array(".5",  ".25", ".75", ".125", ".375",".625",".875");
		$roundedMeasurements = str_replace($fractions, $decimals, $roundedMeasurements);
		
		//Remove spaces
		//$roundedMeasurements = str_replace(" ", "", $roundedMeasurements);
		
		//Done
		$autoList['type_short'] = ((stristr($auctionRow['type_code'], "English")) ? "English " : "") . "$roundedMeasurements ";
		
		if(stristr($auctionRow['style_info'], "music"))
			$autoList['type_short'] .= "music publicity still";
		elseif(stristr($auctionRow['style_info'], "radio"))
			$autoList['type_short'] .= "radio publicity still";
		elseif(stristr($auctionRow['style_info'], "publicity"))
			$autoList['type_short'] .= "publicity still";
		elseif($styleInfoRow['newsstill'] == 1)
			$autoList['type_short'] .= "news photo";
		else
			$autoList['type_short'] .= "still";
		
		if($styleInfoRow['numbered'] == "number" && $styleInfoRow['number'] != 0)
			$autoList['type_short'] .= " #" . $styleInfoRow['number'];
	}
	
	if($styleInfoRow['numbered'] == "TC" || $styleInfoRow['numbered1'] == "TC")
		$autoList['type_short'] = "TC";
	
	//Repro still
	if($item_is_signed_repro_8x10)
		$autoList['type_short'] = str_replace(" still", " REPRO still", $autoList['type_short']);
	
	if($styleInfoRow['nationality'] != "" && !stristr($autoList['type_short'], $styleInfoRow['nationality']))
		$autoList['type_short'] = trim($styleInfoRow['nationality'] . " " . $autoList['type_short']);
	
	if($styleInfoRow['quantity'] > 1)
	{
		if(stristr($autoList['type_short'], "transparency"))
			$autoList['type_short'] = str_replace("transparency", "transparencies", $autoList['type_short']);
		elseif(stristr($autoList['type_short'], "photolobby"))
			$autoList['type_short'] = str_replace("photolobby", "photolobbies", $autoList['type_short']);
		else{
  		  if($tblTypesRow['type_short']!=$tblTypesRow['demonym']){
  		    $autoList['type_short'] .= "s";
  		  }
			}
	}
	
	$autoList['type_short'] = trim($autoList['type_short']);
	
	if(empty($autoList['quantity']))
	{
		if($styleInfoRow['quantity'] == 0)
		{
			//AK, 2012-08-31: Discussed this AGAIN with Phil because he thought there was a
			//"bug" because not everything had a quantity, and he wanted window cards to be added to this list.
			//He didn't think the list is going to creep towards if(true)
			
			//AK, 2012-07-26: Discussed this AGAIN with Phil because he thought there was a 
			//"bug" because not everything had a quantity, and he wanted programs to be added to this list.
			
			//AK, 2012-11-06: A while back Phil got sick of having to fill in the quantity in the check
			//auction tables step, so we decided to make the describers do it. Not long after that, 
			//the describers got sick of having to do it. Matt complained, and now we are only requiring
			//a quantity for lobby cards and stills not in the "single" auctions. What a waste of time this
			//whole thing has been. It's has creeped towards "if(true)", but I think it will stop here.
			
			//AK, 2016-04-26: "Re: quantity issues" Bruce complained about the quantity getting
			//screwed up in the AH, we re-discussed all of the above, and re-decided to force the 
			//describers to enter a quantity into MISC11x14.
			
			//I think this list will eventually creep until basically the entire if condition is equivalent to just "if(true)".
			if(($tblTypesRow['poster_simple'] != "lobby card" && $tblTypesRow['poster_simple'] != "still") || 
				in_array($auctionRow['auction_code'], array("LCSINGLE", "MISC11x14", "8x10SING"))) 
				//stristr($auctionRow['type_code'], "program") || stripos($auctionRow['type_code'], "wc") === 0 || 
				//in_array($auctionRow['type_code'], array("presskit", "pb", "French pb")))
				$autoList['quantity'] = 1;
			else
				$autoList['quantity'] = "";
		}
		else
			$autoList['quantity'] = $styleInfoRow['quantity'];
	}
	
	return Array($autoList, $messages, $measurements);
}

function add_metric($measurements)
{
	//Get our centimeter value for measurements
	$decimalMeasurements = $measurements;
	
	//Convert fractions
	$fractions = Array(" 1/2", " 1/4", " 3/4", " 1/8", " 3/8", " 5/8", " 7/8");
	$decimals  = Array(".5",  ".25", ".75", ".125", ".375",".625",".875");
	$decimalMeasurements = str_replace($fractions, $decimals, $decimalMeasurements);
	
	//Remove spaces
	//$decimalMeasurements = str_replace(" ", "", $decimalMeasurements);
	
	//Round numbers
	preg_match_all("/([\d]+(\.[\d]+)?)(['\"])/", $decimalMeasurements, $matches);
	
	$decimalMeasurements = str_replace(array("\"", "'"), "", $decimalMeasurements);
	
	//mail("aaron@emovieposter.com", "measurements", print_r($matches, true)."\n".$decimalMeasurements);
	
	# If both numbers are in INCHES
	if(count($matches[0]) == 2 && $matches[3][0] == "\"" && $matches[3][1] == "\"")
	{
		$cm = round($matches[1][0] * 2.54). " x " . round($matches[1][1] * 2.54);
		return "$measurements [$cm cm]";
	}
	# If both numbers are in FEET
	elseif(count($matches[0]) == 2 && $matches[3][0] == "\'" && $matches[3][1] == "\'")
	{
		# Does not work - need to make similar code adjustments elsewhere to
		# add support for measurements in feet
		/*
		$m = round($matches[1][0] * 0.3048, 2) . " x " . round($matches[1][1] * 0.3048, 2);
		$measurementsWithMetric = "$measurements [$m m]";
		*/
		
		return $measurements;
	}
	else
		return $measurements;
}

function add_metric2($measurements)
{	
	/*
		This regex produces results that look like this:
		
		For the string: 
			27 1/2" x 41"

		Matches:
			array(1) {
			  [0]=>
			  array(9) {
			    [0]=>
			    string(13) "27 1/2" x 41""
			    [1]=>
			    string(2) "27"
			    [2]=>
			    string(1) "1"
			    [3]=>
			    string(1) "2"
			    [4]=>
			    string(1) """
			    [5]=>
			    string(2) "41"
			    [6]=>
			    string(0) ""
			    [7]=>
			    string(0) ""
			    [8]=>
			    string(1) """
			  }
			}

	*/
	
  preg_match_all("|([0-9]+)?(?: *([0-9]+)/([0-9]+))?(['\"]) (?:x )?([0-9]+)?(?: *([0-9]+)/([0-9]+))?(['\"]) (?:x )?([0-9]+)?(?: *([0-9]+)/([0-9]+))?(['\"])|", $measurements, $matches, PREG_SET_ORDER);
	//Mailer::mail('steven@emovieposter.com','debug - ' . __FILE__ . ":" . __LINE__, var_export($matches,true));
	if(empty($matches)){
    preg_match_all("|([0-9]+)?(?: *([0-9]+)/([0-9]+))?(['\"]) x ([0-9]+)?(?: *([0-9]+)/([0-9]+))?(['\"])|", $measurements, $matches, PREG_SET_ORDER);
    //Mailer::mail('steven@emovieposter.com','debug - ' . __FILE__ . ":" . __LINE__, var_export($matches,true));
  }
  
	//var_dump($matches);
	
  	foreach($matches as $match)
  	{
  	  $tmp = $match[0];
  	  if(count($match)==9){
  		// If not inches, quit trying.
  		if($match[4] != '"' || $match[8] != '"')
  			return array($measurements, $measurements);
  		
  		  $cm = round(($match[1] + (empty($match[3]) ? 0 : ($match[2] / $match[3]))) * 2.54) . " x " . 
              round(($match[5] + (empty($match[7]) ? 0 : ($match[6] / $match[7]))) * 2.54);
      }elseif(count($match)==13){
        if($match[4] != '"' || $match[8] != '"' || $match[12] != '"')
        return array($measurements, $measurements);
        $tmp = $match[0];
        if(stripos($match[0], ' x ')===false){
          $tmp = str_replace(' ',' x ',$match[0]);
        }
        
        $cm = round(($match[1] + (empty($match[3]) ? 0 : ($match[2] / $match[3]))) * 2.54) . " x " . 
              round(($match[5] + (empty($match[7]) ? 0 : ($match[6] / $match[7]))) * 2.54) . " x " .
              round(($match[9] + (empty($match[11]) ? 0 : ($match[10] / $match[11]))) * 2.54);
      }

  		$measurements = str_replace($match[0], "$tmp [$cm cm]", $measurements);
    }
	
	$measurements = str_replace("trimmed to ", "", $measurements);
	$measurements = str_replace("trimmed ", "", $measurements);
	$measurementsForLong = str_replace("trimmed to ", "trimmed ", $measurements);
	if(count($matches) > 1)
		$measurementsForLong = "(measure ".$measurementsForLong.")";
	
	return array($measurements, $measurementsForLong, $matches[0]);
}
?>
