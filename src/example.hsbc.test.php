<?php

	include(dirname(__FILE__) . '/banks/HSBC.php');

	if (!file_exists('hsbc.testdata.txt')) {
		$bank = new HSBC('IB1234567890', '010170', '1234567890');
		
		$accounts = $bank->getAccounts(false, true, true, true);
	
		$s = serialize($accounts);
		file_put_contents('hsbc.testdata.txt', $s);
	} else {
		$s = file_get_contents('testdata.txt');
		$accounts = unserialize($s);
	}

	foreach ($accounts as $account) {
		echo $account, "\n";
	}

?>