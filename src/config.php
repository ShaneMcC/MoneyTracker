<?php
	require_once(dirname(__FILE__) . '/3rdparty/notorm/NotORM.php');
	require_once(dirname(__FILE__) . '/functions.php');
	require_once(dirname(__FILE__) . '/Bank.php');
	require_once(dirname(__FILE__) . '/Account.php');
	require_once(dirname(__FILE__) . '/Transaction.php');
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