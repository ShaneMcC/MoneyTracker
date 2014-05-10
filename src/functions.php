<?php

	/**
	 * Get a PDO instance using the given db data.
	 *
	 * @param $dbdata Connection data
	 * @return a new PDO instance
	 */
	function getPDO($dbdata = null) {
		if (!is_array($dbdata)) { $dbdata = array(); }

		$type = isset($dbdata['type']) ? $dbdata['type'] : 'type';
		$server = isset($dbdata['server']) ? $dbdata['server'] : 'localhost';
		$port = isset($dbdata['port']) ? $dbdata['port'] : '3306';
		$db = isset($dbdata['db']) ? $dbdata['db'] : 'bankinfo';
		$user = isset($dbdata['user']) ? $dbdata['user'] : 'bankinfo';
		$pass = isset($dbdata['pass']) ? $dbdata['pass'] : 'bankinfo';

		return new PDO($type . ':host=' . $server . ';port=' . $port . ';dbname=' . $db, $user, $pass);
	}

	/**
	 * Ask user for information from the CLI if appropriate.
	 *
	 * @param $prompt Prompt to show.
	 */
	function getUserInput($prompt) {
		if (!defined('STDIN') || !posix_isatty(STDIN)) { return ''; }

		echo $prompt;

		$handle = fopen('php://stdin', 'r');
		$line = fgets($handle);

		return trim($line);
	}

	function startsWith($haystack, $needle) {
		return $needle === "" || strpos($haystack, $needle) === 0;
	}

	function endsWith($haystack, $needle) {
	    return $needle === "" || substr($haystack, -strlen($needle)) === $needle;
	}

	date_default_timezone_set(@date_default_timezone_get());
?>
