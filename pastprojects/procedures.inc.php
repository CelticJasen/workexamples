<?php
function assemble_image_request($mysql_result, $name, $no_subdirectories, $resize, $dir_name = null)
{
	/*
	 * Function: assemble_image_request
	 * 
	 * Takes two arguments: a mysql result from the customer images program,
	 * and a name to display to the user and to create the folder with,
	 * and creates the image request folder(s) for that person.
	 */
	
	$idb = new mysqli(MYSQL_HOST, MYSQL_USER, MYSQL_PASS, "images");
	
	$output = Array("missing" => array(),"watermarked"=>array());
	
	$count = 0;
	$output['size'] = 0;
	$total = $mysql_result->num_rows;
	
	$info_file_data = array();
	
	if($total > 0)
	{
		if(!empty($dir_name))
			$top_directory = "/images/Requests/$dir_name";
		else
			$top_directory = sprintf("/images/Requests/%s_%s", date("Ymd"), $name);
		
		if(file_exists($top_directory))
		{
			status("You already did $name.");
			throw new Exception("You already did $name.", 10041);
		}
		
		mkdir2($top_directory);
		
		$output['directory'] = "\\\\images".str_replace("/", "\\", $top_directory);
		$output['path'] = $top_directory;
	}
	
	while($item = $mysql_result->fetch_assoc())
	{
		$count++;
		status("$count of $total for $name");
		
		$item["filenames"] = array();
		$item['missing'] = Array();
		$item['watermarked'] = Array();
		
		if($no_subdirectories)
			$subfolder = $top_directory;
		else
		{
			$subfolder = sprintf("$top_directory/%s", $item['year']);
			mkdir2($subfolder);
		}
		
		if($item['imagesLocation'] != "")
		{
			if(is_dir("/images/Images in process/Uploading AHEAD (bulklots)/$item[imagesLocation]"))
			{
				$subfolder = $subfolder."/".substr($item['imagesLocation'], 0, 7);
				
				mkdir2($subfolder);
				
				$files = scandir("/images/Images in process/Uploading AHEAD (bulklots)/$item[imagesLocation]");
				foreach($files as $f)
				{
					if(preg_match("/\.jpe?g$/i", $f) && 
						is_file("/images/Images in process/Uploading AHEAD (bulklots)/$item[imagesLocation]/$f"))
					{
						$result = search_for_image2($f, $item['sortfield'], $idb, $top_directory);
						
						if(!empty($result['']))
						{
							link_or_resize($result[''], "$subfolder/".basename($f), $resize);
							
							if($result['watermarked'])
                $item['watermarked'][] = $item['image'];
						}
						else
						{
							$item['missing'][] = $f;
						}
					}
				}
			}
			else
			{
				$item['missing'][] = $item['imagesLocation'];
			}
		}
		
		$result = search_for_image2($item["image"], $item['sortfield'], $idb, $top_directory);
		
		if(!empty($result['']))
		{
			$output['size'] += filesize($result['']);
			
			$item['filenames'][] = link_or_resize($result[''], "$subfolder/".basename($item['image']), $resize);
			
			if($result['watermarked'])
				$item['watermarked'][] = $item['image'];
		}
		else
			$item['missing'][] = $item['image'];
		
		$extra_images = array_filter(array_map("trim", explode(";", $item["image2"])), "strlen");
		foreach($extra_images as $i)
		{
			$result = search_for_image2($i, $item['sortfield'], $idb, $top_directory);
			
			if(!empty($result['']))
			{
				$output['size'] += filesize($result['']);
				
				$item['filenames'][] = link_or_resize($result[''], "$subfolder/".basename($i), $resize);
				
				if($result['watermarked'])
					$item['watermarked'][] = $i;
			}
			else
				$item['missing'][] = $i;
		}

		if(count($item['missing']) || count($item['watermarked']))
		{
			$output['missing'][] = $item;
		}

    if(count($item['watermarked']))
    {
            $output['watermarked'][] = $item;
    }

		
		$info_file_data[] = $item;
	}

	if($total > 0)
	{
		$filename = explode("_", basename($top_directory));
		$filename = $filename[1];
		$f = fopen("$top_directory/$filename.txt", "w");
		fclose($f);
	

		$info_file = fopen("$top_directory/info.txt", "w");
	

		if(count($output['missing']))
		{
		 	fwrite($info_file, "--------------------------------------------------- Items with missing or watermarked images ---------------------------------------------------\r\n\r\n");
			
			foreach($info_file_data as $k => $item)
			{
				if(!empty($item['missing']) || !empty($item['watermarked']))
				{
					fwrite($info_file, generate_info_file_line($item)."\r\n");
					unset($info_file_data[$k]);
				}
			}
			
			fwrite($info_file, "\r\n--------------------------------------------------------- Items complete with all images -------------------------------------------------------\r\n\r\n");
		}
	
	
		
		foreach($info_file_data as $item)
		{
			fwrite($info_file, generate_info_file_line($item)."\r\n");
		}
		
		
		$output['mb'] = round($output['size']/1000/1000);
		$output['dvds'] = ceil($output['size']/(4.7*1000*1000*1000));
		$output['cds'] = ceil($output['size']/(700*1000*1000));
	}
	status("finished $name");
	
	return $output;
}



function generate_info_file_line($item)
{
	$line = str_pad(substr($item['ebay_title'], 0, 60), 60)."\t".$item['date']."\t".
		($item['price'] == "" ? "" : number_format($item['price'], 2))."\t";
	
	if(!empty($item['imagesLocation']))
		return $line.$item['imagesLocation'];
	else
		return $line.implode(" ", $item['filenames']);
}

function split_space($txt)
{
	return preg_split("/\s+/", $txt);
}

function assemble_emails_list($create_table, $pdb)
{
	/*
	 * Function: assemble_emails_list
	 * 
	 * Reads the customer list, validates the emails,
	 * and outputs an array. Optionally, inserts
	 * the emails into a temporary mysql table. 
	 */
	$lines = @file("/webroot/customer_images/customer_list.txt", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
	

	$lines = array_unique(array_map("trim", array_map("strtolower", $lines)));

	if($lines === false)
		throw new Exception("customer_list.txt missing", 10020);
	
	natcasesort($lines);
	
	$list = @file("/webroot/customer_images/alternate_emails.txt", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);	
	
	if($list === false)
		throw new Exception("alternate_emails.txt missing", 10020);
	
	$alternates = array();
	
	foreach($list as $alt)
	{
		list($k, $v) = split_space(trim($alt));
		$alternates[$k] = $v;
	}
	
	
	
	$output = array();
	
	foreach($lines as $l)
	{
		if(!preg_match("/^[^@]+@[^@]+\.[^@]+$/", $l))
			throw new Exception("Invalid email address in list: ('$l'). You must change it before proceeding.", 10040);	
		
		if(!empty($alternates[$l]))
		{
			$output[] = array($l, $alternates[$l]);
		}
		else
		{
			$output[] = array($l, $l);
		}
	}
	
	if($create_table)
	{
		$pdb->query("create temporary table invoicing.emails (email varchar(255))");
		$pdb->query("insert into invoicing.emails values ('".implode("'), ('", array_map(array($pdb, "escape_string"), $lines))."')");
	}
	
	return $output;
}


function link_or_resize($from, $to, $resize)
{
	//Per Phil this is crazy and we should never have been doing this at all. AK 2014-08-21
	/*
	if($resize && filesize($from) > 1024*1024*1.5)
	{
		$to = deduplify_filename($to);
		
		exec("nice -n 18 convert ".escapeshellarg($from)." -resize '3000000@>' ".escapeshellarg($to), $output, $retval);
		
		if($retval != 0)
			throw new exception("Could not scale image.", 10000);
	}
	else
	{
		symlink2($from, $to);
	}
	*/
	
	link2($from, $to);
	
	return basename($to);
}


function search_for_image2($filename, $sortfield = null, $idb, $top_directory)
{
	/*if(!preg_match("/[a-z]{2}[0-9]{5}/i", $filename))
	{
		$result = search_for_image($filename, $sortfield);
		$result['watermarked'] = true;
		return $result;
	}*/
	
	$pinfo = pathinfo($filename);
	$filename = $pinfo['basename'];
	
	
	$r = $idb->query("select directory, filename AS dbfilename from images_index ".
		"where (directory like '/crop/Backed Up Images/%' OR directory like '/data/crop/Backed Up Images/%') and filename = '".$idb->escape_string($filename)."'");
	
  
  //$debug_file = fopen("$top_directory/debug.txt", "a");
  
  //fwrite($debug_file, "-- Searching \"".$filename."\" --\n");
  
	if($r->num_rows > 0)
	{
		while(list($directory, $dbfilename) = $r->fetch_row())
		{
      $pathinfo = pathinfo($filename);
      $pathinfo2 = pathinfo($dbfilename);
      
      //fwrite($debug_file, "\$pathinfo => ".print_r($pathinfo,true)."\n");
      //fwrite($debug_file, "\$pathinfo2 => ".print_r($pathinfo,true)."\n");
      
      //fwrite($debug_file, "file exist check 1: " .  var_export(file_exists("$directory/".$pathinfo['basename']),true)  . "\n");
			if(file_exists("$directory/".$pathinfo['basename']))
				return array("" => "$directory/".$pathinfo['basename'], "watermarked" => false);
      
      //fwrite($debug_file, "file exist check 2: " .  var_export(file_exists("$directory/".$pathinfo2['basename']),true)  . "\n");
      if(file_exists("$directory/".$pathinfo2['basename']))
        return array("" => "$directory/".$pathinfo2['basename'], "watermarked" => false);
      
      //fwrite($debug_file, "file exist loop check\n");
      foreach(['.jpg','.Jpg','.JPG'] as $extension){
        $alt_filename = preg_replace('/\.jpg$/i', $extension, $pathinfo['basename']);
        //fwrite($debug_file, $extension . " : " .  var_export("$directory/".$alt_filename,true)  . "\n");
        if(file_exists("$directory/".$alt_filename))
          return array("" => "$directory/".$alt_filename, "watermarked" => false);
      }
      
      
		}
	}
	
	//fwrite($debug_file, "MISSING\n");
  
  //fwrite($debug_file, "\n\n\n");
  //fclose($debug_file);
	

	$result = search_for_image($filename, $sortfield);
	$result['watermarked'] = true;
	return $result;
}

function email_mailto_who($array)
{
	list($email, $mailto) = $array;
	
	if($email != $mailto)
	{
		$who = "$email/$mailto";
	}
	else
	{
		$who = "$email";
	}
	
	return array($email, $mailto, $who);
}


function run_item_query($pdb, $email, $start_date, $one_day)
{
	$query = sprintf("select coalesce(ebay_num, sales.ebay_item_number) as ebay_num, ebay_title, ".
		"coalesce(image, original_image, t3.image1, t4.image1) as image, ".
		"coalesce(t2.image2, t3.image2, t4.image2) as image2, ".
		"sortfield, ".
		"date_format(coalesce(date_end_calculated, sales.`date`), '%%m/%%d/%%Y') as `date`, ".
		"year(coalesce(date_end_calculated, sales.`date`)) as `year`, t2.bulk_lot_id, sales.price, imagesLocation, ".
		"coalesce(t3.photography_folder, t4.photography_folder) as photography_folder ".
		"from invoicing.sales ".
		"left join thumbnails.archive_gallery_tbl t2 on (ebay_item_number = ebay_num and ebay_item_number != '') ".
		"left join `listing_system`.`00-00-00 BINs (BINs)` t3 on (sales.ebay_item_number regexp '^F[0-9]{4,}$' and substr(ebay_title, 1, 4) = substr(t3.lot_number, 1, 4)) ".
		"left join `listing_system`.`00-00-00 BINs Sold Out (BINs)` t4 on (sales.ebay_item_number regexp '^F[0-9]{4,}$' and substr(ebay_title, 1, 4) = substr(t4.lot_number, 1, 4)) ".
		"left join listing_system.bulk_lots on t2.bulk_lot_id = bulk_lots.id ".
		"where sales.ebay_email = '%s' and date(coalesce(date_end_calculated, sales.`date`)) ".
		($one_day ? "=" : ">= ").
		"'%s' and (t2.id is not null or t3.id is not null or t4.id is not null) ".
		"order by date_end_calculated", 
		$pdb->escape_string($email), 
		$pdb->escape_string(date("Y-m-d", strtotime($start_date))));
	
	$r = $pdb->query($query);
	
	return $r;
}



?>
