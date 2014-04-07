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
		echo $prompt;

		$handle = fopen('php://stdin', 'r');
		$line = fgets($handle);

		return trim($line);
	}

	class database_mapper {
		private $db;

		public function __construct($db) {
			$this->db = $db;
		}

		public function getAccounts() {
			$accounts = array();
			foreach ($this->db->accounts as $row) {
				$r = iterator_to_array($row);
				$acct = Account::fromArray($r);
				$acct->setTransactions($this->getTransactions($acct->getAccountKey()));
				$accounts[] = $acct;
			}
			return $accounts;
		}

		public function getTransactions($accountKey) {
			$transactions = array();
			foreach ($this->db->transactions->where('accountkey',  $accountKey)->order("`time` asc") as $row) {
				$r = iterator_to_array($row);
				$t = Transaction::fromArray($r);
				$transactions[] = $t;
			}
			return $transactions;
		}
	}

	date_default_timezone_set(@date_default_timezone_get());
?>