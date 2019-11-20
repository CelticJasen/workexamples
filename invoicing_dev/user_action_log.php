<?php
try
{
	require_once("/webroot/includes/time.inc.php");
	set_error_handler("exception_error_handler", E_ALL);
	mysqli_report(MYSQLI_REPORT_STRICT | MYSQLI_REPORT_ERROR);
	$db = new mysqli("localhost", "root", "k8!05Er-05", "invoicing");
	$db->set_charset("utf8");
	
	try
	{
		$wdb = new mysqli();
		$wdb->options(MYSQLI_OPT_CONNECT_TIMEOUT, 3);
		$wdb->real_connect(WEB_MYSQL_HOST, WEB_MYSQL_USER, WEB_MYSQL_PASS);
	}
	catch(exception $e)
	{
		$wdb = false;
		$status = "Could not connect to website.";
	}
	
	$result = array();	
	
	if(!empty($_REQUEST['emails']))
	{
		$data = array();
		
		foreach($_REQUEST['emails'] as $email)
		{
		 	$data[$email] = array();
			
			$r = $wdb->query("select `t1`.`user_id` AS `user_id`, `members`.`users`.`email` AS `email`, ".
				"concat(`members`.`users`.`first_name`,' ',`members`.`users`.`last_name`) AS `name`,".
				"`members`.`users`.`username` AS `username`,`t1`.`timestamp` AS `timestamp`, unix_timestamp(`timestamp`) as epoch, ".
				"coalesce(`t1`.`path`,`t2`.`path`) AS `path`,".
				"coalesce(`t1`.`referer`,`t3`.`path`) AS `referer`,".
				"coalesce(`t1`.`agent`,`t4`.`agent`) AS `agent`,".
				"`t1`.`session_string` AS `session_string`,`t1`.`query_string` AS `query_string`,`t1`.`runtime` AS `runtime` ".
				"from ((((`members`.`user_action_log` `t1` left join `members`.`user_action_log_paths` `t2` on((`t1`.`path_id` = `t2`.`id`))) ".
				"left join `members`.`user_action_log_paths` `t3` on((`t1`.`referer_id` = `t3`.`id`))) ".
				"left join `members`.`user_action_log_agents` `t4` on((`t1`.`agent_id` = `t4`.`id`))) ".
				"left join `members`.`users` on((`t1`.`user_id` = `members`.`users`.`id`))) ".
				"where email = '".$wdb->escape_string($email)."' and `timestamp` > '2015-12-14 00:00:00' ".
				"order by `timestamp` desc");
			
		 	while($row = $r->fetch_assoc())
			{
				$row['fuzzy_time'] = Time::less_fuzzy($row['epoch']);
				$row['referer'] = urldecode(urldecode($row['referer']));
				$data[$email][] = $row;
			}
		}
		
		$result['result'] = $data;
	}
	
	die(json_encode2($result));
}
catch(exception $e)
{
	if($e->getCode() == 10040)
	{
		echo json_encode(Array("error" => $e->getMessage()));
	}
	else
	{
		echo json_encode(Array("error" => "There was an error. I will inform the admin of this problem.".$e->__toString()));
		email_error($e->__toString());
	}
}

?>