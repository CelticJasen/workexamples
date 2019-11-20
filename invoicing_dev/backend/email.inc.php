<?php

function email_date_sort($a, $b)
{
	return strcmp($b['date'], $a['date']);
}

class MailIndex
{
	function __construct()
	{
		try
		{
		  mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
			$this->pdb = new mysqli();
			$this->pdb->options(MYSQLI_OPT_CONNECT_TIMEOUT, 3);
			$this->pdb->real_connect("poster-server", "office", "never eat shredded wheat", "poster-server");
		}
		catch(exception $e)
		{
			$this->pdb = false;
		}
		
		try
		{
			$this->db = new mysqli();
			$this->db->options(MYSQLI_OPT_CONNECT_TIMEOUT, 3);
			$this->db->real_connect("images", "mail", "postal", "mail");
		}
		catch(exception $e)
		{
			$this->db = false;
		}
		
		
		try
		{
			$this->mdb = new mysqli();
			$this->mdb->options(MYSQLI_OPT_CONNECT_TIMEOUT, 3);
			$this->mdb->real_connect("mail", "mail", "mail", "email");
		}
		catch(exception $e)
		{
			$this->mdb = false;
		}
	}
	
	function subscriptions($users_id)
	{
		if($this->mdb !== false)
		{
			$ids = array();
			$r = $this->mdb->query("select customers_id ".
				"from subscriptions ".
				"where users_id = '$users_id'");
			
			while(list($customers_id) = $r->fetch_row())
				$ids[] = $customers_id;
			
			return $ids;
		}
	}
	
	function check_subscription($customers_id, $users_id)
	{
		if($this->mdb !== false)
		{
			$r = $this->mdb->query("select 1 ".
				"from subscriptions ".
				"where customers_id = '$customers_id' and users_id = '$users_id'");
				
			return $r->num_rows > 0;
		}
		
		return false;
	}
	
	function add_subscription($customers_id, $users_id)
	{
		if($this->mdb !== false)
		{
			$this->mdb->query("insert into subscriptions ".
				"set customers_id = '$customers_id', users_id = '$users_id' on duplicate key update `ts` = now()");
				
			$this->update_subscriptions();
		}
	}
	
	function remove_subscription($customers_id, $users_id)
	{
		if($this->mdb !== false)
		{
			$this->mdb->query("delete from subscriptions ".
				"where customers_id = '$customers_id' and users_id = '$users_id'");
				
			$this->update_subscriptions();
		}
	}
	
	function update_subscriptions()
	{
		if($this->mdb !== false)
		{
			/*
				create user_id => email map
			*/
			$users = array();
			$r = $this->pdb->query("select `id`, `email` from `poster-server`.users where email like '%@%'");
			while(list($users_id, $email) = $r->fetch_row())
				$users[$users_id] = $email;
			
			
			/*
				update subscription_map table
			*/
			$this->mdb->query("delete from email.subscription_map");
			
			$customers = array();			
			
			$r = $this->mdb->query("select users_id, customers_id ".
				"from email.subscriptions");
				
			while(list($users_id, $customers_id) = $r->fetch_row())
			{
				if(!isset($customers[$customers_id]))
				{
					$customers[$customers_id] = array();
					
					$r2 = $this->pdb->query("select email from invoicing.customers where customers_id = '$customers_id' and email like '%@%' ".
					"union ".
					"select email from invoicing.customers_emails where customers_id = '$customers_id' and email like '%@%'");
				
					while(list($email) = $r2->fetch_row())
					{						
						$customers[$customers_id][] = $email;
					}
				}
				
				foreach($customers[$customers_id] as $email)
				{
					$this->mdb->query("insert into email.subscription_map ".
						"set `subscriber` = '".$this->mdb->escape_string($users[$users_id])."', ".
						"`to` = '".$this->mdb->escape_string($email)."' ".
						"on duplicate key update `to` = `to`");
				}
			}
		}
	}

	function get_consignment_mail($emails = null)
	{
		$output = array();

		if(is_null($emails))
		{
			$r = $this->mdb->query("select emails.`date`, ".
				"group_concat(`To`.address separator ', ') as `to`, subject, ".
				//"`From`.address as `from`, subject, ".
				"emails.email_id ".
				"from email.emails ".
				//"join email.email_ids on (emails.email_id = email_ids.email_id) ".
				//"join email.addresses `From` on (`from` = `From`.address_id) ".
				"join email.participants on (emails.email_id = participants.email_id) ".
				"join email.addresses `To` on (participants.header = 'to' and participants.address_id = `To`.address_id) ".
				//"join email.address_ids on (participants.address_id = address_ids.address_id) ".
				"where (".
					"`subject` = 'Your Consignment(s) Arrived!' or ".
					"`subject` = 'Thank you for letting us know about your incoming consignments!' or ".
					"`subject` = 'Update from eMoviePoster.com on your recent shipment(s)!' ".
				") and ".
				"emails.`date` > date_sub(curdate(), interval 90 day) ".
				"group by emails.email_id ".
				"order by emails.`date` desc ");
		}
		else
		{
			$this->mdb->query("create temporary table email_ids ".
				"select email_id ".
				"from email.participants ".
				"join email.addresses using(address_id) ".
				"where `header` = 'To' and ".
				"address in ('".implode("','", array_map(array($this->mdb, "escape_string"), $emails))."')");

			$r = $this->mdb->query("select emails.`date`, subject, ".
				//"group_concat(`To`.address separator ', ') as `to`, `From`.address as `from`, subject, ".
				"emails.email_id ".
				"from email.emails ".
				"join email.email_ids on (emails.email_id = email_ids.email_id) ".
				//"join email.addresses `From` on (`from` = `From`.address_id) ".
				"join email.participants on (emails.email_id = participants.email_id) ".
				"join email.addresses `To` on (participants.header = 'to' and participants.address_id = `To`.address_id) ".
				//"join email.address_ids on (participants.address_id = address_ids.address_id) ".
				"where (".
					"`subject` = 'Your Consignment(s) Arrived!' or ".
					"`subject` = 'Thank you for letting us know about your incoming consignments!' or ".
					"`subject` = 'Update from eMoviePoster.com on your recent shipment(s)!' ".
				") and ".
				"emails.`date` > date_sub(curdate(), interval 90 day) ".
				"group by emails.email_id ".
				"order by emails.`date` desc ");
		}

		$mails = array();

		while($row = $r->fetch_assoc())
		{
			$mails[] = $row;
		}

		return $mails;
	}
	
	
	function get_mail_to($emails, $limit = 5, $options)
	{
		$output = array();
		$querystring = "";
		$likestring = "";
		$startdate = "";
		$enddate = "";
		$subjectsearch = "";
		
		if(!empty($options['startdate']))
		{
			$startdate = $options['startdate'];
		}
		
		if(!empty($options['enddate']))
		{
			$enddate = $options['enddate'];
		}
		
		if(!empty($options['subjectsearch']))
		{
			$subjectsearch = $options['subjectsearch'];
		}
		
		if(!empty($options['page']))
		{
			$page = $options['page'];
		}
		else
		{
			$page = "1";
		}
		
		
		/*
		 * All this was commented out because this server shouldn't have emails in it anyway
		if($this->db !== false)
		{
			$r = $this->db->query("select address_id ".
				"from mail.addresses ".
				"where `email` in ('".implode("','", array_map(array($this->db, "escape_string"), $emails))."')");
			
			$address_ids = array();
			while(list($address_id) = $r->fetch_row())
				$address_ids[] = $address_id;
			
			$this->db->query("create temporary table email_ids ".
				"select emails_id ".
				"from mail.`to` ".
				"where `to` in ('".implode("','", $address_ids)."')".
				"group by emails_id");
				
			$this->db->query("insert into email_ids ".
				"select emails_id ".
				"from mail.emails ".
				"where `from` in ('".implode("','", $address_ids)."')");
				
			if(empty($enddate) && !empty($startdate))
			{
				$startdateready = date("Y-m-d H:i:s", strtotime($startdate));
				
				$querystring = "WHERE date >= '$startdateready'";
			}
			
			if(empty($startdate) && !empty($enddate))
			{
				$enddateready = date("Y-m-d H:i:s", strtotime($enddate));
				
				$querystring = "WHERE date <= '$enddateready '";
			}
			
			if(!empty($startdate) && !empty($enddate))
			{
				$startdateready = date("Y-m-d H:i:s", strtotime($startdate));
				$enddateready = date("Y-m-d H:i:s", strtotime($enddate));
				
				$querystring = "WHERE date >= '$startdateready' AND date <= '$enddateready'";
			}
			
			if(!empty($subjectsearch) && !empty($startdate) || !empty($enddate))
			{
				$likestring = "AND subject LIKE '%$subjectsearch%'";
			}
			elseif(!empty($subjectsearch))
			{
				$likestring = "WHERE subject LIKE '%$subjectsearch%'";
			}
			
			$r = $this->db->query("select `date`, group_concat(t1.email separator ', ') as `to`, t2.email as `from`, subject, emails_id ".
				"from mail.emails ".
				"join email_ids using(emails_id) ".
				"left join mail.to using(emails_id) ".
				"left join mail.addresses as t1 on (`to` = t1.address_id) ".
				"left join mail.addresses as t2 on (`from` = t2.address_id) ".
				//"where box is not null ".
				"$querystring ".
				"$likestring ".
				"group by emails.emails_id ".
				"ORDER BY emails.emails_id DESC " .
				"limit $limit");
			
			
			while($row = $r->fetch_assoc())
			{
				$output[] = $row;
			}
		}*/
		
		if($this->mdb !== false)
		{	
			$this->mdb->query("create temporary table email_ids ".
				"select email_id ".
				"from email.participants ".
				"join email.addresses using(address_id) ".
				"where address in ('".implode("','", array_map(array($this->mdb, "escape_string"), $emails))."')");
			
			// the following 'if' statements prepare the query strings that will or will not be sent to the database
			// depending on the optional arguments sent to this function
			if(empty($enddate) && !empty($startdate))
			{
				$startdateready = date("Y-m-d H:i:s", strtotime($startdate));
				$querystring = "WHERE date >= '$startdateready'";
			}
			
			if(empty($startdate) && !empty($enddate))
			{
				$enddateready = date("Y-m-d H:i:s", strtotime($enddate));
				$querystring = "WHERE date <= '$enddateready '";
			}
			
			if(!empty($startdate) && !empty($enddate))
			{
				$startdateready = date("Y-m-d H:i:s", strtotime($startdate));
				$enddateready = date("Y-m-d H:i:s", strtotime($enddate));
				$querystring = "WHERE date >= '$startdateready' AND date <= '$enddateready'";
			}
			
			if(!empty($subjectsearch) && !empty($startdate) || !empty($enddate))
			{
				$likestring = "AND subject LIKE '%$subjectsearch%'";
			}
			elseif(!empty($subjectsearch))
			{
				$likestring = "WHERE subject LIKE '%$subjectsearch%'";
			}
			
			// Added by Jasen for pagination of emails
			// Find out how many items are in the table
			$total = $this->mdb->query("SELECT * ".
				"FROM email.emails ".
				"join email.email_ids on (emails.email_id = email_ids.email_id) ".
				"join email.addresses `From` on (`from` = `From`.address_id) ".
				"join email.participants on (emails.email_id = participants.email_id) ".
				"join email.addresses `To` on (participants.header = 'to' and participants.address_id = `To`.address_id) ".
				"$querystring ".
				"$likestring ".
				"group by emails.email_id ".
				"ORDER BY emails.email_id DESC ");
			
			$total = mysqli_num_rows($total);
			
			// How many pages will there be
			$pages = ceil($total / $limit);
			
			// Calculate the offset for the query
			$offset = ($page - 1)  * $limit;
			
			// Some information to display to the user
			$start = $offset + 1;
			$end = min(($offset + $limit), $total);
			
			// The "back" link
			$prevlink = ($page > 1) ? "<button type=\"button\" onclick=\"firstpage()\">&laquo;</button> <button type\"button\" onclick=\"pagedown()\">".
			"&lsaquo;</button>" : "<span class=\"disabled\">&laquo;</span> <span class=\"disabled\">&lsaquo;</span>";
			// The "forward" link
			$nextlink = ($page < $pages) ? "<button type=\"button\" onclick=\"pageup()\">&rsaquo;</button> <button type=\"button\" onclick=\"lastpage()\">".
			"&raquo;</button>" : "<span class=\"disabled\">&rsaquo;</span><span class=\"disabled\">&raquo;</span>";
			// Display the paging information
			$pageinfo = "<div id=\"paging\"><p>" . $prevlink . " Page " . $page . " of " . $pages . " pages, displaying " . $start . "-" . $end . " ".
			"of " . $total . " results " . $nextlink . " </p></div><input id=\"page\" type=\"hidden\" value=\"" . $page . "\">".
			"<script>function pageup(){document.getElementById(\"page\").value =" . $page . " + 1;submitbypage()}function pagedown()".
			"{document.getElementById(\"page\").value =" . $page . " - 1;submitbypage()}function firstpage(){document.getElementById(\"page\").value=1;submitbypage()}".
			"function lastpage(){document.getElementById(\"page\").value=" . $pages . ";submitbypage()}function submitbypage(){\$(\"#email_search_form\").submit(show_emails_pane());}</script>";
			
			// Run the paged query
			$r = $this->mdb->query("select emails.`date`, group_concat(`To`.address separator ', ') as `to`, `From`.address as `from`, subject, emails.email_id ".
				"from email.emails ".
				"join email.email_ids on (emails.email_id = email_ids.email_id) ".
				"join email.addresses `From` on (`from` = `From`.address_id) ".
				"join email.participants on (emails.email_id = participants.email_id) ".
				"join email.addresses `To` on (participants.header = 'to' and participants.address_id = `To`.address_id) ".
				"$querystring ".
				"$likestring ".
				"group by emails.email_id ".
				"ORDER BY emails.email_id DESC ".
				"LIMIT $limit ".
				"OFFSET $offset");
				
			while($row = $r->fetch_assoc())
			{
				$output[] = $row;
			}
		}
		
		usort($output, "email_date_sort");
		array_unshift($output, $pageinfo); //This seems a bit hacky but is necessary for delivering paging info
		return $output;
	}
	
	function last_email_from($emails)
	{
		if($this->mdb !== false)
		{
			$this->mdb->query("create temporary table email_ids ".
				"select email_id ".
				"from email.participants ".
				"join email.addresses using(address_id) ".
				"where `header` = 'from' and ".
				"address in ('".implode("','", array_map(array($this->mdb, "escape_string"), $emails))."')");
				
			$r = $this->mdb->query("select emails.`date`, group_concat(`To`.address separator ', ') as `to`, `From`.address as `from`, subject, emails.email_id ".
				"from email.emails ".
				"join email.email_ids on (emails.email_id = email_ids.email_id) ".
				"join email.addresses `From` on (`from` = `From`.address_id) ".
				"join email.participants on (emails.email_id = participants.email_id) ".
				"join email.addresses `To` on (participants.header = 'to' and participants.address_id = `To`.address_id) ".
				//"join email.address_ids on (participants.address_id = address_ids.address_id) ".
				"group by emails.email_id ".
				"order by emails.`date` desc ".
				"limit 1");

			$this->mdb->query("drop temporary table email_ids");
			
			if($r->num_rows)	
				return $r->fetch_assoc();				
			
			return false;
		}
	}
	
	function last_email_to($emails)
	{
		if($this->mdb !== false)
		{
			$this->mdb->query("create temporary table email_ids ".
				"select email_id ".
				"from email.participants ".
				"join email.addresses using(address_id) ".
				"where `header` in ('to', 'cc', 'bcc') and ".
				"address in ('".implode("','", array_map(array($this->mdb, "escape_string"), $emails))."')");
				
			$r = $this->mdb->query("select emails.`date`, group_concat(`To`.address separator ', ') as `to`, `From`.address as `from`, subject, emails.email_id ".
				"from email.emails ".
				"join email.email_ids on (emails.email_id = email_ids.email_id) ".
				"join email.addresses `From` on (`from` = `From`.address_id) ".
				"join email.participants on (emails.email_id = participants.email_id) ".
				"join email.addresses `To` on (participants.header = 'to' and participants.address_id = `To`.address_id) ".
				//"join email.address_ids on (participants.address_id = address_ids.address_id) ".
				"group by emails.email_id ".
				"order by emails.`date` desc ".
				"limit 1");
			
			$this->mdb->query("drop temporary table email_ids");
			
			if($r->num_rows)	
				return $r->fetch_assoc();				
			
			return false;
		}
	}
	
	
	function last_email_about($emails)
	{
		if($this->mdb !== false)
		{
			$this->mdb->query("create temporary table email_ids ".
				"select email_id ".
				"from email.participants ".
				"join email.addresses using(address_id) ".
				"where `header` not in ('from', 'to', 'cc', 'bcc') and ".
				"address in ('".implode("','", array_map(array($this->mdb, "escape_string"), $emails))."')");
				
			$r = $this->mdb->query("select emails.`date`, group_concat(`To`.address separator ', ') as `to`, `From`.address as `from`, subject, emails.email_id ".
				"from email.emails ".
				"join email.email_ids on (emails.email_id = email_ids.email_id) ".
				"join email.addresses `From` on (`from` = `From`.address_id) ".
				"join email.participants on (emails.email_id = participants.email_id) ".
				"join email.addresses `To` on (participants.header = 'to' and participants.address_id = `To`.address_id) ".
				//"join email.address_ids on (participants.address_id = address_ids.address_id) ".
				"group by emails.email_id ".
				"order by emails.`date` desc ".
				"limit 1");
			
			$this->mdb->query("drop temporary table email_ids");
			
			if($r->num_rows)	
				return $r->fetch_assoc();				
			
			return false;
		}
	}
	
	
	
	function process_results($emails, $group = false)
	{
		$tempstore = array_shift($emails); //This seems a bit hacky but is necessary for delivering paging info
		
		require_once("/webroot/includes/time.inc.php"); //For Time::days_ago($date) and Time::fuzzy($epoch)
		
		$groups = array();
		
		foreach($emails as $k => $v)
		{
			$emails[$k]['date'] = date("n/j/y g:i\&\\n\b\s\p\;a", strtotime($v['date']));
			
			$v['from'] = str_replace("@emovieposter.com", "", $v['from']); //Removes email ending to help indicate it's from within
			$v['to'] = str_replace("@emovieposter.com", "", $v['to']);
			
			$emails[$k]['from'] = str_replace("@", "@&shy;", $v['from']); //Makes the '@' symbol visible on webpage
			$emails[$k]['to'] = str_replace("@", "@&shy;", $v['to']); 
			$emails[$k]['to'] = implode(', ', array_unique(explode(', ', $emails[$k]['to']))); //Removes duplicate addresses that clutter the page
			
			$emails[$k]['subject'] = str_replace(
				array("@", ".", "/"), 
				array("@&shy;", ".&shy;", "/&shy;"), 
				$v['subject']);
			
			$when = Time::days_ago($v['date']);
			
			if($group)
			{
				if(empty($groups[$when]))
					$groups[$when] = array();
					
				$groups[$when][] = $emails[$k];
			}
		}
		
		if($group)
		{
			array_unshift($groups, $tempstore); //This seems a bit hacky but is necessary for delivering paging info
			return $groups;
		}
		else
		{
			array_unshift($emails, $tempstore); //This seems a bit hacky but is necessary for delivering paging info
			return $emails;
		}
	}
}


?>