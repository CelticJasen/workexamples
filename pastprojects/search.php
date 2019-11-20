<?php
try
{
	proc_nice(17);
	
	function restore_nice()
	{
		proc_nice(0);
	}
	
	register_shutdown_function("restore_nice");
	
	//die(print_r($_POST, true));
	header("Content-Type: text/json", true);
	require_once("includes.inc.php");
	require_once("/webroot/includes/archive_images.inc.php");
	require_once("procedures.inc.php");
	require_once("archiver.php");
	
	function status($status)
	{
		$f = fopen("status.log", "w");
		fwrite($f, $status);
		fclose($f);
	}
	
	if(!isset($_POST['action']))
		$_POST['action'] = "";
	
	if($_POST['action'] == 1)
	{
		//Action 1: Send list of dates available, using customer list.
		
		status("getting list of dates");
		
		assemble_emails_list(true, $pdb);
		
		$r = $pdb->query("select distinct date(date_end_calculated) as `date` ".
			"from invoicing.sales ".
			"join thumbnails.archive_gallery_tbl on ebay_item_number = ebay_num ".
			"join invoicing.emails on ebay_email = email ".
			"order by date desc");
		
		$dates_html = "<select id='dates_list'>\n<option />\n";
		while(list($date) = $r->fetch_row())
		{
			$dates_html .= "<option value='$date'>".date("m/d/Y", strtotime($date))."</option>\n";
		}
	
		$dates_html .= "</select>\n";
		
		unlink("status.log");
	
		die(json_encode(array("dates_html" => $dates_html)));
	}
	elseif($_POST['action'] == 2)
	{
		Event::log(array("event_type" => "cropping-compile-customer-images", "request" => $_REQUEST, "time" => date("Y-m-d H:i:s"), "user_id" => $user->getId()));
		//Action 2: Create image folders using customer list and chosen start date.
		
		$dirs = array();
		
		$emails = assemble_emails_list(false, $pdb);
		
		$output = array("messages" => array(), "missing" => array());
		
		foreach($emails as $email)
		{
			list($email, $mailto, $who) = email_mailto_who($email);
			
			status("getting filenames for $who");
			
			$r = run_item_query($pdb, $email, $_POST['start_date'], !empty($_REQUEST['one_day']));
			
			$num_rows = $r->num_rows;
			
			if($num_rows == 0)
			{
				$output['messages'][] = "No items for $who";
				continue;
			}
			
			try
			{
				$result = assemble_image_request($r, $email, true, !empty($_POST['resize']), date("Y-m-d", strtotime($_POST['start_date']))."_".$mailto);
				$dirs[] = $result['path'];
				$output['missing'] = array_merge($output['missing'], $result['missing']);
				$output['messages'][] = $num_rows." item".($num_rows > 1 ? "s" : "")." for $who";
			}
			catch(Exception $e)
			{
				if($e->getCode() == 10041)
				{
					$output['messages'][] = $e->getMessage();
				}
				else
					throw $e;
			}
		}
		
		if(empty($_POST['split_archive']))
		{
			foreach($dirs as $dir)
			{
				chdir($dir);
				
				status("Zipping $dir");
				
				//status(system("zip --quiet -0 -r --exclude=*.txt ".escapeshellarg(basename($dir)).".zip ."));
				if(stripos(basename($dir), 'adam.kennedy@hotmail.com') !== false)
				{
					status(system("zip --quiet -0 -r -x [!info]\*.txt \*.zip @ ".escapeshellarg(basename($dir)).".zip ."));  
				}
				else
				{
					status(system("zip --quiet -0 -r --exclude=*.txt ".escapeshellarg(basename($dir)).".zip ."));
				}
			}
		}
		else
		{
			foreach($dirs as $dir)
			{
				chdir($dir);
				status("Zipping $dir");
				$exclusions = array(".txt", ".zip", "status.log");
				$files = array_diff(scandir($dir), array('.', '..'));
				$limit = 700000000;
				zipPerLimit($files, $dir, $exclusions, $limit);
			}
		}
	}
	else
	{
		Event::log(array("event_type" => "cropping-compile-customer-images", "request" => $_REQUEST, "time" => date("Y-m-d H:i:s"), "user_id" => $user->getId()));
		//No "action" specified, default behavior of getting a single customer's images
		
		$_POST['email'] = trim($_POST['email']);
		$_POST['consignor'] = trim($_POST['consignor']);
		
		if($_POST['consignor'] == "")
		{
			if(!preg_match("/^[^@]+@[^@]+$/", $_POST['email']))
				throw new Exception("Invalid email '$_POST[email]'", 10040);
			
			status("getting filenames");
			
			$query = "select coalesce(ebay_num, sales.ebay_item_number) as ebay_num, ebay_title, ".
				"coalesce(image, original_image, t3.image1, t4.image1) as image, ".
				"coalesce(t2.image2, t3.image2, t4.image2) as image2, ".
				"sortfield, ".
				"date_format(coalesce(date_end_calculated, sales.`date`), '%m/%d/%Y') as `date`, ".
				"year(coalesce(date_end_calculated, sales.`date`)) as `year`, t2.bulk_lot_id, sales.price, imagesLocation, ".
				"coalesce(t3.photography_folder, t4.photography_folder) as photography_folder ".
				"from invoicing.sales ".
				"left join thumbnails.archive_gallery_tbl t2 on ebay_item_number = ebay_num ".
				"left join `listing_system`.`00-00-00 BINs (BINs)` t3 on (shipping_notes = 'BIN' and substr(ebay_title, 1, 4) = substr(t3.lot_number, 1, 4)) ".
				"left join `listing_system`.`00-00-00 BINs Sold Out (BINs)` t4 on (shipping_notes = 'BIN' and substr(ebay_title, 1, 4) = substr(t4.lot_number, 1, 4)) ".
				"left join listing_system.bulk_lots on t2.bulk_lot_id = bulk_lots.id ".
				"where sales.ebay_email = '".$pdb->escape_string($_POST['email'])."' and (sales.price is null or sales.price > 0) and sales.ebay_item_number != '' ";
			
			if(!empty($_POST['start_date']))
			{
				$query .= "and date(sales.`date`) ".
				
				(!empty($_REQUEST['one_day']) ? "=" : ">= ").
				" '".date("Y-m-d", strtotime($_POST['start_date']))."' ";
			}
			
			if(empty($_REQUEST['one_day']) && !empty($_POST['end_date']))
			{
				$query .= "and date(sales.`date`) <= '".date("Y-m-d", strtotime($_POST['end_date']))."' ";
			}
			
			$query .= "order by date_end_calculated";
			
			$r = $pdb->query($query);
			
			$name = $_POST['email'];
		}
		else
		{
			status("getting filenames");
			
			create_consignor_table($_POST['consignor'], $pdb, true);
			
			$query = "select aaron_images.*, bulk_lots.imagesLocation from aaron_images ".
				"left join listing_system.bulk_lots on bulk_lot_id = bulk_lots.id where (1 ";
			
			if(!empty($_POST['start_date']))
				$query .= "and `date_ymd` >= '".date("Y-m-d 00:00:00", strtotime($_POST['start_date']))."' ";
			
			if(!empty($_POST['end_date']))
			{
				$query .= "and `date_ymd` <= '".date("Y-m-d", strtotime($_POST['end_date']))."' ";
			}
			
			if(empty($_POST['unsold']))
				$query .= "and `date` != 'Not Yet Sold')";
			else
				$query .= ") or `date` = 'Not Yet Sold'";
			
			$r = $pdb->query($query);				
			
			$name = $_POST['consignor'];
		}
		
		$output = assemble_image_request($r, $name, false, !empty($_POST['resize']), null);
		
		if(!empty($output['path']))
		{
			$dir = $output['path'];
			status("Zipping $dir");
			chdir($dir);
			
			if(empty($_POST['split_archive']))
			{
				//status(system("zip --quiet -0 -r --exclude=*.txt ".escapeshellarg(basename($dir)).".zip ."));
				if(stripos(basename($dir), 'adam.kennedy@hotmail.com') !== false)
				{
					status(system("zip --quiet -0 -r -x [!info]\*.txt \*.zip @ ".escapeshellarg(basename($dir)).".zip ."));  
				}
				else
				{
					status(system("zip --quiet -0 -r --exclude=*.txt ".escapeshellarg(basename($dir)).".zip ."));
				}
			}
			else
			{
				chdir($dir);
				status("Zipping $dir");
				$exclusions = array(".txt", ".zip", "status.log");
				$files = array_diff(scandir($dir), array('.', '..'));
				$limit = 700000000;
				zipPerLimit($files, $dir, $exclusions, $limit);
			}
		}
	}
	
	unlink("status.log");
	
	echo json_encode($output);
}
catch(Exception $e)
{
	status("Error");	
	if($e->getCode() == 10040)
	{
		echo json_encode(Array("error" => $e->getMessage()));
	}
	else
	{
		echo json_encode(Array("error" => "There was an error. I will tell an admin about this problem.\n".$e->__toString()));
		email_error($e->__toString());
	}
}

function create_consignor_table($consignor, $pdb, $sam_sarowitz)
{
	$pdb->query("create temporary table listing_system.aaron_images (ebay_num varchar(255), ebay_title varchar(255), image varchar(255), ".
				"image2 text, sortfield varchar(255), `date` varchar(255), date_ymd datetime, `year` varchar(255), bulk_lot_id varchar(255), ".
				"price varchar(255), `table` varchar(255), `photography_folder` varchar(255))");
	
	$query = "insert into aaron_images select eBay_Item_Num, lot_title, coalesce(image, original_image), ".
		"image2, sortfield, date_format(date_end_calculated, '%m/%d/%Y'), date_end_calculated, ".
		"year(date_end_calculated), bulk_lot_id, tbl_Current_Consignments.price, 'tbl_Current_Consignments', null ".
		"from tbl_Current_Consignments ".
		"join thumbnails.archive_gallery_tbl ".
		"on (eBay_Item_Num = ebay_num and Consignee = '".$pdb->escape_string($consignor)."') ";
	
	if(!empty($sam_sarowitz))
	{
		//$query .= "where (about_code like 'polish%' or about_code like 'czech%')";
	}
  
  /*if(!empty($_POST['end_date']) || !empty($_POST['start_date'])){
    $query .= 'WHERE (';
    
    $dates = [];
    
  if(!empty($_POST['end_date'])){
        $query .= "`date` <= '".date("Y-m-d", strtotime($_POST['end_date']))."' ";
      }
      
      $query .= ') ';
      
  }*/
	
	$pdb->query($query);
	
	$r = $pdb->query("show tables from listing_system");
	while(list($table) = $r->fetch_row())
	{
		if(preg_match("/^[0-9]{2}-[0-9]{2}-[0-9]{2} /", $table))
		{
			$query = "insert into aaron_images select null, concat(film_title, ' ', type_code), image1, ".
				"image2, null, date_format(date_added, '%m/%d/%Y'), date_added, year(date_added), bulk_lot_id, null, '$table', photography_folder ".
				"from listing_system.`$table` ".
				"where consignor = '".$pdb->escape_string($consignor)."' and coalesce(ebay_item_number, '') = ''";
				
			if(!empty($sam_sarowitz))
			{
				//$query .= "and (type_code like 'polish%' or type_code like 'czech%')";
			}
			$pdb->query($query);
		}
	}
}
