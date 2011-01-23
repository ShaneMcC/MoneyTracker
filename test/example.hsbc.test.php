<?php

	include(dirname(__FILE__) . '../src/banks/HSBC.php');

	$datafile = dirname(__FILE__) . '/data/hsbc.testdata.txt';

	if (!file_exists('hsbc.testdata.txt')) {
		$bank = new HSBC('IB1234567890', '010170', '1234567890');
		
		$accounts = $bank->getAccounts(false, true, true, true);
	
		$s = serialize($accounts);
		file_put_contents($datafile, $s);
	} else {
		$s = file_get_contents($datafile);
		$accounts = unserialize($s);
	}

	foreach ($accounts as $account) {
		echo $account, "\n";
	}

?>