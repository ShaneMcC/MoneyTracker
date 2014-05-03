<?php
	//-----------------------------------------------------------------------
	// Common functions
	//-----------------------------------------------------------------------
	require_once(dirname(__FILE__) . '/../functions.php');

	//-----------------------------------------------------------------------
	// Global Configuration
	//-----------------------------------------------------------------------
	require_once(dirname(__FILE__) . '/../config.php');

	//-----------------------------------------------------------------------
	// Templating and pages.
	//-----------------------------------------------------------------------
	require_once(dirname(__FILE__) . '/classes/template.php');
	require_once(dirname(__FILE__) . '/classes/page.php');

	// Prepare the template engine
	$templateFactory = new TemplateFactory($config['web']['templates'], $config['web']['theme']);

	//-----------------------------------------------------------------------
	// Additional Classes
	//-----------------------------------------------------------------------
	require_once(dirname(__FILE__) . '/classes/session.php');
?>