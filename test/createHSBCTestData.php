<?php

	require_once(dirname(__FILE__) . '/../src/banks/HSBC.php');

	class createHSBCTestData {
		public function __construct($account, $dob, $secret, $datafile) {
			$bank = new HSBC($account, $dob, $secret);
			$accounts = $bank->getAccounts(false, true, true, true);
			$s = serialize($accounts);
			file_put_contents($datafile, $s);
		}
	}

?>