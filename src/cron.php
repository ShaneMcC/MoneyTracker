<?php
	require_once(dirname(__FILE__) . '/config.php');
	require_once(dirname(__FILE__) . '/importer.php');

	$importer = new Importer($config['database']);

	foreach ($config['bank'] as $bank) {
		$importer->import($bank);
	}
?>
