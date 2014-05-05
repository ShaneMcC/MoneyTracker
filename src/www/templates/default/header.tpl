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
	<link href="{[getWebLocation]}bootstrap/css/bootstrap-theme.min.css" rel="stylesheet">

	<link href="{[getWebLocation('style.css')]}" rel="stylesheet">
	<link href="{[getWebLocation('style-local.css')]}" rel="stylesheet">

	<script src="{[getWebLocation]}bootstrap/js/jquery.js"></script>
	<script src="{[getWebLocation]}bootstrap/js/bootstrap.min.js"></script>

	<script src="{[getWebLocation]}js/bootbox.min.js"></script>

    <!-- HTML5 shim and Respond.js IE8 support of HTML5 elements and media queries -->
    <!--[if lt IE 9]>
      <script src="https://oss.maxcdn.com/libs/html5shiv/3.7.0/html5shiv.js"></script>
      <script src="https://oss.maxcdn.com/libs/respond.js/1.4.2/respond.min.js"></script>
    <![endif]-->

	</head>
	<body role="document">

		<!-- Fixed navbar -->
		<div class="navbar navbar-default navbar-fixed-top" role="navigation">
			<div class="{{$fluid ? 'container-fluid' : 'container'}}">
				<div class="navbar-header">
					<button type="button" class="navbar-toggle" data-toggle="collapse" data-target=".navbar-collapse">
						<span class="sr-only">Toggle navigation</span>
						<span class="icon-bar"></span>
						<span class="icon-bar"></span>
						<span class="icon-bar"></span>
					</button>
					<a class="navbar-brand" href="#">Money Tracker</a>
				</div>
				<div class="navbar-collapse collapse">
					<ul class="nav navbar-nav">
						<li {[ca('page', 'home')]}><a href="{[getWebLocation]}">Home</a></li>
						<li {[ca('page', 'transactions')]}><a href="{[getWebLocation]}transactions">Transactions</a></li>
						<li {[ca('page', 'tags')]}><a href="{[getWebLocation]}tags">Tags</a></li>
					</ul>
				</div>
			</div>
		</div>

		<div class="{{$fluid ? 'container-fluid' : 'container'}}" role="main">
			<div class="row">