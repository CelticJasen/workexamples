<?php

class RecordLocking
{
	//TODO: Blab debugging
	//TODO: Make database-specific?
	const LOCK_TIMEOUT = 10;
	
	function __construct($db, $blab, $user)
	{
		$this->db = $db;
		$this->blab = $blab;
		$this->user = $user;
	}
	
	
	
	
	/** 
	 * Lock a record. Throws an exception when record is already locked.
	 * Lock will expire after LOCK_TIMEOUT seconds unless
	 * the lock gets updated.
	 * 
	 * @param string $table		Table that contains the record to be locked
	 * @param string $table_id	ID of the record to be locked
	 * @param string $form		Application that is creating the lock
	 */
	function add($table, $table_id, $form)
	{
		//TODO: Account for multiple forms open on same computer
		$this->db->query("lock tables locks write");
		
		$result = $this->db->query(sprintf("select `who`, `when` from locks where `table`='%s' and table_id='%s'",
			$this->db->escape_string($table), $this->db->escape_string($table_id)));

		if($result->num_rows)
		{
			list($who, $then) = $result->fetch_row();
			
			$who = $this->user->getName($who);
			
			$now = time();
			$when = date("m/d/Y H:i:s", $then);
			
			if(($now-$then) < self::LOCK_TIMEOUT)
			{
				$this->db->query("unlock tables");
				$e = new Exception("$who is using a record you are trying to open.\n----------\n$table\n$table_id", 10022);
				$e->who_locked = $who;
				throw $e;
			}
		}

		$this->db->query(sprintf("insert into locks (`table`, table_id, who, `when`, form) values ('%s', '%s', '%s', '%s', '%s')",
			$this->db->escape_string($table), $this->db->escape_string($table_id), $this->user->getId(),
			time(), $this->db->escape_string($form)));

		$this->db->query("UNLOCK TABLES");
		
		//Purge locks that are no longer used (older than 60 seconds)
		$this->db->query("DELETE FROM `locks` WHERE `when` < (UNIX_TIMESTAMP()-60)");
		
		return true;
	}
	
	
	
	/**
	 * Release a record lock.
	 */
	function release($table, $table_id, $form)
	{
		$this->db->query(sprintf("delete from locks where `table` = '%s' and table_id = '%s' and form = '%s' and who = '%s'",
			$this->db->escape_string($table), $this->db->escape_string($table_id), $this->db->escape_string($form), $this->user->getId()));
	}
	
	
	
	/**
	 * Release all of my locks in application $form.
	 */
	function purge($form)
	{
		$this->db->query(sprintf("delete from locks where who = '%s' and form = '%s'", 
			$this->user->getId(), $this->db->escape_string($form)));
	}
	
	
	
	/**
	 * Keep all of my locks in application $form for another LOCK_TIMEOUT seconds.
	 */
	function update($form)
	{
		$this->db->query(sprintf("update locks set `when` = UNIX_TIMESTAMP() where form = '%s' and who = '%s'",
			$this->db->escape_string($form), $this->user->getId()));
	}
}

?>