<?php

	require_once(dirname(__FILE__) . '/banks/HSBC.php');
	require_once(dirname(__FILE__) . '/../test/banks/TestHSBC.php');

	$bank = new HSBC('IB123456789', '010101', '12345678');
	$importer = new Importer();

	$importer->import($bank);
?>
