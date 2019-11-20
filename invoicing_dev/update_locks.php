<?PHP
try
{
	$login_not_required = true;
	require_once("/webroot/auth/auth.php");
	
	if(empty($user))
		die("{\"logout\":1}");
	
	set_error_handler("exception_error_handler", E_ALL);
	mysqli_report(MYSQLI_REPORT_STRICT | MYSQLI_REPORT_ERROR);
	$db = new mysqli("localhost", "root", "k8!05Er-05", "invoicing");
	$db->set_charset("utf8");
	
	if(empty($_POST['id']))
	{
		$db->query(sprintf("update locks set `when` = UNIX_TIMESTAMP() where form = '%s' and who = '%s'",
			$db->escape_string($_POST['form']), $user->getId()));
	}
	else
	{
		$db->query("update locks set `when` = UNIX_TIMESTAMP() ".
			"where form = '".$db->escape_string($_POST['form'])."' and who = '".$user->getId()."' ".
			"and `table` = '".$db->escape_string($_POST['table'])."' and `table_id` = '".$db->escape_string($_POST['id'])."'");
	}
	
	$r = $db->query("select * from locks ".
		"where `form` = '".$db->escape_string($_POST['form'])."' and who = '".$user->getId()."'");
	
	/*$wants = array();
		
	while($row = $r->fetch_assoc())
	{
		$r = $db->query("select group_concat(name), locks_faults.id as id ".
			"from invoicing.locks_faults ".
			"join `poster-server`.users on who = users.id ".
			"where `when` > unix_timestamp()-10 and `table` = '".$db->escape_string($row['table'])."' and ".
			"table_id = '".$db->escape_string($row['table_id'])."' ".
			"group by table_id");
		
		if($r->num_rows)
			$wants[$row['table_id']] = $r->fetch_assoc();
	}*/
	
	
	if(!empty($_REQUEST['lock_waiting']))
	{
		require_once("/webroot/includes/record_locking_mysqli.inc.php");
		
		try
		{
			RecordLock::get($_REQUEST['lock_waiting']['table'], $_REQUEST['lock_waiting']['id'], "invoicing", $db, $user->getId());
			die(json_encode(array("ok" => 1, "got_lock" => $_REQUEST['lock_waiting'])));
		}
		catch(exception $e)
		{
			if($e->getCode() != 10022)
				throw $e;
		}
	}
	
	echo json_encode(array("ok" => 1));
}
catch(exception $e)
{
	echo json_encode(array("error" => $e->__toString()));
}

?>