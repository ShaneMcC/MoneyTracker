<?php

	require_once(dirname(__FILE__) . '/../../src/banks/HSBC.php');

	class TestHSBC extends HSBC {
		private $accounts = array();
		private $datafile = '';

		public function __construct($account, $dob, $secret) {
			parent::__construct($account, $dob, $secret);

			$this->datafile = dirname(__FILE__) . '/../data/hsbc.testdata.txt';

			if (!file_exists($this->datafile)) {
				require_once(dirname(__FILE__) . '/../createHSBCTestData.php');
				new createHSBCTestData($account, $dob, $secret, $this->datafile);
			}

			if (file_exists($this->datafile)) {
				$s = file_get_contents($this->datafile);
				$this->accounts = unserialize($s);
			} else {
				throw new exception('Test Datafile not found.');
			}
		}

		public function login() {
			return true;
		}

		public function getAccounts($useCached = true, $transactions = false, $historical = false, $historicalVerbose = false) {
			return $this->accounts;
		}

		public function updateTransactions($account, $historical = false, $historicalVerbose = true) {
			return true;
		}
	}

?>
