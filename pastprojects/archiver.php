<?php

/* Made by Jasen Johnston 2019-March-29
 * 
 * Script for archiving large amounts of files into separate zip files according to bytes size limit
 */

function zipPerLimit($files, $filePath, $exclusions = "", $limit = 700000000)
{
	set_time_limit(300);
	$i = 0;
	$zipNumber = 0;
	$files = array_diff($files, array('.', '..'));
	$filesArray = array(array());
	$finalFiles = array();
	$sizeCount = 0;
	
	foreach($files as $file)
	{
		foreach($exclusions as $exclude)
		{
			if(strpos($file, $exclude) !== false)
			{
				continue 2;
			}
		}
		
		if(is_dir($file))
		{
			$moreFiles = array_diff(scandir($filePath . "/" . $file), array('.', '..'));
			
			foreach($moreFiles as $moreFile)
			{
				$filesArray[$i][0] = $moreFile;
				$filesArray[$i][1] = filesize($filePath . "/" . $file . "/" . $moreFile);
				$filesArray[$i][2] = $file;
				$i++;
			}
		}
		else
		{
			$filesArray[$i][0] = $file;
			$filesArray[$i][1] = filesize($filePath . "/" . $file);
			$i++;
		}
	}
	
	foreach($filesArray as $oneFile)
	{
		if(!empty($oneFile[2]))
		{
			$finalFiles[] = $oneFile[2] . "/" . $oneFile[0];
			$sizeCount += $oneFile[1];
		}
		else
		{
			$finalFiles[] = $oneFile[0];
			$sizeCount += $oneFile[1];
		}
		
		if($sizeCount >= $limit)
		{
			foreach($finalFiles as $pushFile)
			{
				system("zip --quiet -0 -r " . escapeshellarg(basename($filePath)) . "_" . escapeshellarg($zipNumber) . ".zip " . escapeshellarg($pushFile));
			}
			
			$sizeCount = 0;
			$finalFiles = array();
			$zipNumber++;
		}
	}
	
	if(!empty($finalFiles))
	{
		foreach($finalFiles as $pushFile)
		{
			system("zip --quiet -0 -r " . escapeshellarg(basename($filePath)) . "_" . escapeshellarg($zipNumber) . ".zip " . escapeshellarg($pushFile));
		}
	}
	
	return null;
}

?>