<?php

$files = scandir2("templates/html");

echo "window.templates = {\r\n";

foreach($files as $filename)
{
	echo "\t".json_encode(pathinfo($filename, PATHINFO_FILENAME))." : ".json_encode(file_get_contents("templates/html/$filename")).",\r\n";
}

echo "}\r\n";

?>