<!DOCTYPE html>
<html lang="en">
	<head>
	<title>{{(isset($title) ? $title : 'Unknown Title')}}</title>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta name="description" content="">
	<meta name="author" content="">

	{-- <link href="http://fonts.googleapis.com/css?family=Open+Sans:400italic,600italic,400,600" rel="stylesheet"> --}

	<!-- Bootstrap -  http://twitter.github.com/bootstrap/index.html -->
	<!-- Using Icons from GlyphIcons - http://glyphicons.com/ -->
	<link href="{[getWebLocation]}bootstrap/css/bootstrap.min.css" rel="stylesheet">
	<link href="{[getWebLocation]}bootstrap/css/bootstrap-responsive.min.css" rel="stylesheet">

	<link href="{[getWebLocation('style.css')]}" rel="stylesheet">
	<link href="{[getWebLocation('style-local.css')]}" rel="stylesheet">

	<script src="{[getWebLocation]}bootstrap/js/jquery.js"></script>
	<script src="{[getWebLocation]}bootstrap/js/bootstrap.min.js"></script>

	<!-- HTML5 shim, for IE6-8 support of HTML5 elements -->
	<!--[if lt IE 9]>
		<script src="http://html5shim.googlecode.com/svn/trunk/html5.js"></script>
	<![endif]-->

	</head>
	<body>

			<div class="navbar navbar-fixed-top">
				<div class="navbar-inner">
					<div class="{{$fluid ? 'container-fluid' : 'container'}}">
						<a class="btn btn-navbar" data-toggle="collapse" data-target=".nav-collapse">
							<span class="icon-bar"></span>
							<span class="icon-bar"></span>
							<span class="icon-bar"></span>
						</a>
						<a class="brand" href="#">Money Tracker</a>
						<div class="nav">
							<ul class="nav">
								<li {[ca('page', 'home')]}><a href="{[getWebLocation]}">Home</a></li>
								<li {[ca('page', 'transactions')]}><a href="{[getWebLocation]}transactions">Transactions</a></li>
							</ul>
						</div>
						<div class="nav pull-right">
							<ul class="nav">
							</ul>
						</div>
					</div>
				</div>
			</div>

			<script type="text/javascript">
				$('.dropdown-toggle').dropdown();
			</script>

@ if ($fluid) {
	<style>
		{-- Bootstrap doesn't do offsets in fluid mode, so hax it here. --}
		div.offset3 {
			margin-left: 25% !important
		}

		{-- It also fucks with form elements... --}
		.row-fluid [class*="span"] {
			display: inline-block;
		}
	</style>
@ }

	<div class="{{$fluid ? 'container-fluid' : 'container'}}">
		<div class="{{$fluid ? 'row-fluid' : 'row'}}">