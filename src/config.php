<?php
	require_once(dirname(__FILE__) . '/banks/HSBC.php');

	// Database info
	$config['database']['type'] = 'mysql';
	$config['database']['server'] = 'localhost';
	$config['database']['port'] = '3306';
	$config['database']['db'] = 'bankinfo';
	$config['database']['user'] = 'bankinfo';
	$config['database']['pass'] = 'bankinfo';

	// Bank object(s) used for nightly cron.
	$config['bank'] = array();
	$config['bank'][] = new HSBC('IB123456789', '010101', '12345678');

	if (file_exists(dirname(__FILE__) . '/config.user.php')) {
		require_once(dirname(__FILE__) . '/config.user.php');
	}
?>