<?PHP
try
{
	require_once("/webroot/includes/photographer_ids.inc.php");	
	require_once("/webroot/includes/photo_folders.inc.php");
	require_once($_SERVER['DOCUMENT_ROOT'] . "/auth.inc.php");
	mysql_connect2("localhost", "listing", "system");
	mysql_select_db2("listing_system");
	
	$r = mysql_query3(sprintf("select photography_code from photography_folders where id = '%s'", mysql_real_escape_string($_POST['id'])));
	if(mysql_num_rows($r) == 0)
		throw new Exception("That record (id #$_POST[id]) has vanished.", 10015);
	
	list($old_photography_code) = mysql_fetch_row($r);
	
	$data = Array();
	
	$data['photography_code'] = trim($_POST['photography_code']);
	$data['shots_per_item'] = $_POST['shots_per_item'];
	$data['who'] = (($people[substr($data['photography_code'], 0, 2)] == "") ? substr($data['photography_code'], 0, 2) : $people[substr($data['photography_code'], 0, 2)]);
	
	foreach(array("consignors", "times", "auction_code", "comments", "do_or_die", 
		"extra_folder_name", "linen", "pbacked", "location", "multiple_types", "photography_code", 
		"shots", "type_code", "how_stored") as $field)
	{
		$data[$field] = $_POST[$field];
	}
	
	$data['bulk_lot'] = ($_POST['auction_code'] == "BULKLOTS") ? "true" : "false";

	$data['comments'] .= "\nChanged ".date("Y-m-d H:i:s").".\nReason: ".$_POST['reason']."\n";
  
  $data['extra_flags'] = $_POST['extra_flags'];
	

	$query = "update listing_system.photography_folders set ";
	foreach($data as $k => $v)
	{
		$query .= sprintf("`%s` = '%s', ", 
			mysql_real_escape_string($k),
			mysql_real_escape_string($v)); 
	}
	$query = substr($query, 0, -2);
	$query .= sprintf(" where id = '%s'", 
		mysql_real_escape_string($_POST['id']));
	
	mysql_query3($query);
	
	
	$data['consignors'] = json_decode($data['consignors']);
	$data['times'] = json_decode($data['times']);
	
	
	/*
		Rename the actual photograpy folder
	*/
	chdir("/netmnt/crop/Images to Crop");
	$dir = scandir2(".");
	$renamed = false;
	$dump = array();
	foreach($dir as $item)
	{
		if(preg_match("/^".preg_quote($old_photography_code, "/")."/i", $item))
		{
			$list = PhotoFolders::consignor_list($data);			
      
      $hasDone = preg_match('/ ~DONE~$/', $item);
      $hasRC = preg_match('/ ~R~ ~C~$/', $item);
      
			$folder_name = PhotoFolders::generate_name($data) . ($hasDone ? ' ~DONE~' : '') . ($hasRC ? ' ~R~ ~C~' : '');
			
			if(rename($item, $folder_name))
			{
			    $dump[] = array('$old_photography_code'=>$old_photography_code,'$list'=>$list,'$folder_name'=>$folder_name,'$item'=>$item);
			  
				if(count($data['consignors']) > 5)
					PhotoFolders::consignor_file($folder_name, $list);
			}
			else
			{
				$error = "I could not rename the actual folder in Images to Crop. You must do it manually.";
			}
			
			$renamed = true;
			break;
		}
	}
  if(!empty($dump)){
    Mailer::mail("steven@emovieposter.com",'debugging photo_folder',print_r($dump,true));
  }
	
	if($renamed === false)
		$error = "I could not find the actual folder in Images to Crop, so I could not change its name.";
	
	
	/*mail("aaron@emovieposter.com", "photo folder #$_POST[id] updated", 
		"Notes from who updated:\n-------------------------\n".
		"\"".$_POST['reason']."\"\n-------------------------\n".
		var_export($data, true)."\n-------------------------\n".
		"View the audit log here: \nhttp://poster-server/audit_log/?criteria=".
		urlencode('[["table", "=", "photography_folders"], ["id", "=", "'.$_POST['id'].'"]]'));*/

	$affected_rows = mysql_affected_rows();

	$r = mysql_query3(sprintf("select photography_code from listing_system.photography_folders where id = '%s'", 
		mysql_real_escape_string($_POST['id'])));
	
	$output = array("affected_rows" => $affected_rows, "photography_code" => mysql_result($r, 0, 0));
	
	if(!empty($error))
	{
		$output['error'] = $error;
	}
	
	$editor = $user->getName();
	Mailer::mail("matt@emovieposter.com, phillip@emovieposter.com, steven@emovieposter.com", "Edit to Photography Folder", "About: http://poster-server/wiki/index.php/Edit_to_Photography_Folder\n\n" . "User: " . $editor . "\n" . print_r($data, true));
	
	die(json_encode($output));
}
catch(Exception $e)
{
	die(json_encode(Array("error" => $e->getMessage())));
	if($e->getCode() != 10040)
		email_error($e->__toString());
}
?>
