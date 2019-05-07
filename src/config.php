<?php
	require_once(dirname(__FILE__) . '/3rdparty/notorm/NotORM.php');
	require_once(dirname(__FILE__) . '/functions.php');
	require_once(dirname(__FILE__) . '/SortedLinkedList.php');
	require_once(dirname(__FILE__) . '/Bank.php');
	require_once(dirname(__FILE__) . '/Account.php');
	require_once(dirname(__FILE__) . '/Transaction.php');
	require_once(dirname(__FILE__) . '/banks/HSBC.php');
	require_once(dirname(__FILE__) . '/banks/HSBCMobile.php');
	require_once(dirname(__FILE__) . '/banks/HSBCMerge.php');
	require_once(dirname(__FILE__) . '/banks/Halifax.php');
	require_once(dirname(__FILE__) . '/banks/TescoBank.php');
	require_once(dirname(__FILE__) . '/banks/TescoBankMobile.php');
	require_once(dirname(__FILE__) . '/banks/Monzo.php');

	// Database info
	$config['database']['type'] = getEnvOrDefault('DB_TYPE', 'mysql');
	$config['database']['server'] = getEnvOrDefault('DB_SERVER', 'localhost');
	$config['database']['port'] = getEnvOrDefault('DB_PORT', '3306');
	$config['database']['db'] = getEnvOrDefault('DB_DB', 'bankinfo');
	$config['database']['user'] = getEnvOrDefault('DB_USER', 'bankinfo');
	$config['database']['pass'] = getEnvOrDefault('DB_PASS', 'bankinfo');

	// Base URL (Required by Monzo among other things.)
	$config['baseurl'] = 'http://localhost/moneytracker/';

	// Debug mode on importer?
	$config['importdebug'] = false;

	// Email address to send cron errors to, or false not to send any mail.
	$config['erroraddress']['to'] = false;

	// Email address to send cron errors from.
	$config['erroraddress']['from'] = 'MoneyTracker Cron <MoneyTracker@' . getHostname() . '>';

	// Templates directory.
	$config['web']['templates'] = 'templates';
	// Template theme.
	$config['web']['theme'] = 'SomeTheme';
	setlocale(LC_MONETARY, 'en_GB.UTF-8');

	// Temporary guessed tags regexes until I add a UI for it.
	$config['guessedtags'] = array();
	$config['guessedtags'][] = ['regex' => '#Test.*#i', 'tag' => 1];

	// Bank object(s) used for updates.
	$config['bank'] = array();
	$config['bank'][] = new HSBC('IB123456789', '010101', '##');

	if (file_exists(dirname(__FILE__) . '/config.user.php')) {
		require_once(dirname(__FILE__) . '/config.user.php');
	}

	if (!function_exists('onIntegrityError')) {
		function onIntegrityError($error) { }
	}
	if (!function_exists('onBankError')) {
		function onBankError($bank, $buffer, $ex) { }
	}
