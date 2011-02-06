<?php
	require_once(dirname(__FILE__) . '/../../3rdparty/notorm/NotORM.php');
	ini_set('display_errors', 'off');

	require_once(dirname(__FILE__) . '/data.rules.php');
	require_once(dirname(__FILE__) . '/../../config.php');
	require_once(dirname(__FILE__) . '/../../functions.php');

	if (!defined('SUBCATEGORIES') || !SUBCATEGORIES) {
		$oldcats = $categories;
		$categories = array();
		foreach ($oldcats as $cat => $data) {
			$bits = explode('|', $cat);
			$cat = $bits[0];
			if (isset($categories[$cat])) {
				$categories[$cat] = array_merge($categories[$cat], $data);
			} else {
				$categories[$cat] = $data;
			}
		}
	}

	// Loads statements from the specified directory
	function loadStatements() {
		global $config;
		
		$pdo = getPDO($config['database']);
		$db = new NotORM($pdo);

		$result = array();
		$result['SQL Table'] = array();

		foreach ($db->transactions() as $t) {
			$data = array();
			$data['Description'] = $t['description'];
			$data['Date'] = DateTime::createFromFormat('d/m/Y', date('d/m/Y', $t['time']))->setTime(0, 0, 0);
			$data['Amount'] = (double) $t['amount'];
			$data['Type'] = $t['changetype'];

			$result['SQL Table'][] = parseStatementLine($data);
		}

		return $result;
	}

?>