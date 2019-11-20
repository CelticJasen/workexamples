<?php
header('Content-Type:text/plain');

function checkReplaceAuctions($fileDate, $lotNumbers)
{
	$ssh = ssh2_connect("emovieposter.com");
	ssh2_auth_pubkey_file($ssh, "emoviepo", "/webroot/includes/ssh/id_rsa.pub", "/webroot/includes/ssh/id_rsa");
	$sftp = ssh2_sftp($ssh);
	
	foreach(glob("/webroot/export_gallery/files/AA" . $fileDate . "_" . $lotNumbers . "*") as $filePathName)
	{
		$filesizeLocal = filesize($filePathName);
		
		$fileName = str_replace("/webroot/export_gallery/files/", "", $filePathName);
		
		$stats = ssh2_sftp_stat($sftp, "/data/www/emovieposter.com/html/bulklots/" . $fileName);
		
		if(!$stats)
		{
			ssh2_scp_send($ssh, $filePathName, "/data/www/emovieposter.com/html/bulklots/" . $fileName);
			echo("Transferring " . $filePathName . " to /data/www/emovieposter.com/html/bulklots/" . $fileName);
		}
		else
		{
			$filesizeWeb = $stats[size];
			
			if($filesizeLocal != $filesizeWeb)
			{
				ssh2_sftp_unlink($sftp, "/data/www/emovieposter.com/html/bulklots/" . $fileName);
				echo("Deleting /data/www/emovieposter.com/html/bulklots/" . $fileName);
				ssh2_scp_send($ssh, $filePathName, "/data/www/emovieposter.com/html/bulklots/" . $fileName);
				echo("Transferring " . $filePathName . " to /data/www/emovieposter.com/html/bulklots/" . $fileName);
			}
		}
	}
}
?>