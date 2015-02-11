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

	if (!function_exists('http_parse_headers')) {
		function http_parse_headers($raw_headers) {
			$headers = array();
			$key = '';

			foreach(explode("\n", $raw_headers) as $i => $h) {
				$h = explode(':', $h, 2);

				if (isset($h[1])) {
					if (!isset($headers[$h[0]])) {
						$headers[$h[0]] = trim($h[1]);
					} else if (is_array($headers[$h[0]])) {
						$headers[$h[0]] = array_merge($headers[$h[0]], array(trim($h[1])));
					} else {
						$headers[$h[0]] = array_merge(array($headers[$h[0]]), array(trim($h[1])));
					}
					$key = $h[0];
				} else {
					if (substr($h[0], 0, 1) == "\t") {
						$headers[$key] .= "\r\n\t".trim($h[0]);
					} else if (!$key) {
						$headers[0] = trim($h[0]);trim($h[0]);
					}
				}
			}

			return $headers;
		}
	}
?>
