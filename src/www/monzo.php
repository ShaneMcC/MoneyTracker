<!DOCTYPE html>
<html>
	<head>
		<title>Monzo Redirect</title>
		<style>
			.wordwrap {
				white-space: pre-wrap;       /* Since CSS 2.1 */
				white-space: -moz-pre-wrap;  /* Mozilla, since 1999 */
				white-space: -pre-wrap;      /* Opera 4-6 */
				white-space: -o-pre-wrap;    /* Opera 7 */
				word-wrap: break-word;       /* Internet Explorer 5.5+ */
			}

			pre.monzo {
				padding: 20px;
				margin: 10px;
			}
		</style>

		<meta charset="utf-8">
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
		<meta name="description" content="">
		<meta name="author" content="">



		<!-- jQuery - http://jquery.com/ -->
		<script src="./bootstrap/js/jquery.js"></script>

		<!-- Bootstrap -  http://getbootstrap.com/ -->
		<!-- Using Icons from GlyphIcons - http://glyphicons.com/ -->
		<link href="./bootstrap/css/bootstrap.min.css" rel="stylesheet">
		<link href="./bootstrap/css/bootstrap-theme.min.css" rel="stylesheet">
		<script src="./bootstrap/js/bootstrap.min.js"></script>
		<link href="./templates/default/style.css" rel="stylesheet">

		<!-- HTML5 shim and Respond.js IE8 support of HTML5 elements and media queries -->
		<!--[if lt IE 9]>
			<script src="https://oss.maxcdn.com/libs/html5shiv/3.7.0/html5shiv.js"></script>
			<script src="https://oss.maxcdn.com/libs/respond.js/1.4.2/respond.min.js"></script>
		<![endif]-->

	</head>
	<body role="document">
		<!-- Fixed navbar -->
		<div class="navbar navbar-default navbar-fixed-top" role="navigation">
			<div class="container-fluid">
				<div class="navbar-header">
					<a class="navbar-brand" href="#">Money Tracker - Monzo Redirect</a>
				</div>
			</div>
		</div>

		<div class="container-fluid" role="main">
			<div class="row">
				<div class="col-sm-12">
					<h1>Monzo Redirect</h1>

					Please copy/paste the following code to the CLI Prompt (this should all be on one line):
					<br><br>
					<pre class="wordwrap monzo"><?php echo htmlspecialchars($_REQUEST['code']); ?></pre>
				</div>
			</div>
		</div>
	</body>
</html>
