<!DOCTYPE html>
<html>
<head>
  <title>Email Project</title>
  <link rel="stylesheet" href="/includes/jquery-ui-1.10.3/css/ui-lightness/jquery-ui-1.10.3.custom.css" />
  <link href='http://fonts.googleapis.com/css?family=Open+Sans:600,400|Raleway:400,600' rel='stylesheet' type='text/css'>
  <script type='text/javascript' src='/includes/jquery-ui-1.10.3/js/jquery-1.9.1.js'></script>
  
</head>
<body style='position: relative'>
  <form action="/invoicing_dev/emails.php" method="post" target="returnframe">
    <!--<input type="text" name="emails[]" value="stevemoss59@yahoo.com">-->
    <select name="emails[]" multiple="multiple">
      <option>nt@osians.com</option>
      <option>lakshmi@osians.com</option>
      <option>murtaza@osians.com</option>
      <option>prashant.gandhi@osianama.com</option>
      <option>sahil@osianama.com</option>
      <option>shreenivas@osians.com</option>
      <option>videsh@osians.com</option>
      <option>vilas@osianama.com</option>
      
      
    </select>
    <input type="text" name="emails[]">
    <input type="datetime-local" name="startdate">
    <input type="datetime-local" name="enddate">
	<input type="search" name="subjectsearch">
    <input type="hidden" name="user_id" value="12">
    <button>Submit</button>
  </form>
  
  
<iframe name="returnframe" style="width:100%;height:400px"></iframe>
  
</body>
</html>