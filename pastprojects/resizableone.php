<html>
	<head>
	<title>Resize One</title>
	<script type="text/javascript" src='/includes/misc.js'></script>
	<script type='text/javascript' src='/includes/jquery/jquery.js'></script>
	<script type='text/javascript' src='/includes/jquery/jquery.cookie.js'></script>
	<script type='text/javascript'>
		$.fn.resizeRestorer = function(number) {
			var defHeight = $(this).data("cssHeight")
			var defWidth = $(this).data("cssWidth")
		
			if ( typeof localStorage.width1 !== "undefined")
			{
				$(this).width(localStorage.width1);
			}

			if ( typeof localStorage.height1 !== "undefined")
			{
				$(this).height(localStorage.height1);
			}
			if ( typeof localStorage.width2 !== "undefined")
			{
				$(this).width(localStorage.width2);
			}

			if ( typeof localStorage.height2 !== "undefined")
			{
				$(this).height(localStorage.height2);
			}
			if ( typeof localStorage.width3 !== "undefined")
			{
				$(this).width(localStorage.width3);
			}

			if ( typeof localStorage.height3 !== "undefined")
			{
				$(this).height(localStorage.height3);
			}

			$(this).mousedown(function(event)
			{
				if(event.ctrlKey)
				{
					console.log("do move")
				}
				else
				{
					console.log("do resize")
				}
				
				var isDragging = false
				var origHeight = $(this).height()
				var origWidth = $(this).width()
				
				console.log(event)

				var handlerMouseMove = function()
				{
					if (!isDragging)
					{
						isDragging = true;
					}
				}
				
				var handlerMouseUp = function()
				{
					if (isDragging)
					{
						if (origHeight != $(this).height())
						{
							if(number == 1)
							{
								localStorage.height1 = $(this).outerHeight());
							}
							elseif(number == 2)
							{
								localStorage.height2 = $(this).outerHeight());
							}
							elseif(number == 3)
							{
								localStorage.height3 = $(this).outerHeight());
							}
						}
						
						if (origWidth != $(this).width())
						{
							if(number == 1)
							{
								localStorage.width1 = $(this).outerWidth());
							}
							elseif(number == 2)
							{
								localStorage.width2 = $(this).outerWidth());
							}
							elseif(number == 3)
							{
								localStorage.width3 = $(this).outerWidth());
							}
						}
					}
					$(this).unbind("mousemove", handlerMouseMove).unbind("mouseup", handlerMouseUp)
				}
				
				$(this).bind("mousemove", handlerMouseMove).bind("mouseup", handlerMouseUp)
			})
		}

		$(document).ready(function()
		{
			$("#test1").resizeRestorer(1)
			$("#test2").resizeRestorer(2)
			$("#test3").resizeRestorer(3)
		})


	</script>
	</head>
	<body>

		<table id="text">
		<textarea id="test1" style="width:400px;height:100px"></textarea>
		<textarea id="test2" style="width:400px;height:100px"></textarea>
		<textarea id="test3" style="width:400px;height:100px"></textarea>
	</table>
	</body>
</html>