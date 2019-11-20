<?php

class ReviewFormTemplates
{
	const ROOT = "/webroot/listing/templates";
	
	static function render($templateName, $data = array())
	{
		if(file_exists(self::ROOT."/$templateName.html"))
		{
			return str_replace(array("\r", "\n"), "", file_get_contents(self::ROOT."/$templateName.html"));
		}
		else
		{
			throw new exception("No such template");
		}
	}
}


?>