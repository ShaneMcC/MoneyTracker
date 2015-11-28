#!/usr/bin/php
<?php
	require_once(dirname(__FILE__) . '/config.php');
	require_once(dirname(__FILE__) . '/importer.php');
	require_once(dirname(__FILE__) . '/www/functions.php');
	require_once(dirname(__FILE__) . '/www/classes/database.php');

	$importer = new Importer($config['database'], $config['importdebug']);

	/**
	 * Handler for object buffering.
	 *
	 * @param $buffer Incoming Buffer
	 * @return $buffer to output
	 */
	function obHandler($buffer) {
		global $__ob;
		if (!isset($__ob['buffer'])) { $__ob['buffer'] = ''; }
		$__ob['buffer'] .= $buffer;
		return $buffer;
	}

	/**
	 * End object buffering, flush output, and return copy of buffer.
	 *
	 * @return Buffer from object buffering.
	 */
	function obGetEndAndFlush() {
		global $__ob;
		ob_end_flush();
		$buffer = $__ob['buffer'];
		$__ob['buffer'] = '';
		return $buffer;
	}

	// =========================================================================
	// Importer
	// =========================================================================

	foreach ($config['bank'] as $bank) {
		// We output buffer the importer as it is hstorically echo-y.
		//
		// We still want to output to the console though, so pass to obHandler
		// to capture the output, and set a chunk size of 1 so that we flush
		// immediately.
		ob_start("obHandler", 1);
		try {
			$importResult = $importer->import($bank);
		} catch (Exception $e) {
			$importResult = false;
			echo 'Import error: ', $e->getMessage(), "\n\n";
			echo $e->getTraceAsString(), "\n";
		}
		// End the output buffering and get a copy of the buffer.
		$buffer = obGetEndAndFlush();

		// If we have an error address, and there was an error, send a mail.
		if (!$importResult && isset($config['erroraddress']['to']) && $config['erroraddress']['to'] !== false) {

			$subject = '[MoneyTracker Cron] Error with import: ' . $bank->__toString();

			$message = array();
			$message[] = 'There was an Error with import for ' . $bank->__toString() . ', please see below: ';
			$message[] = '';
			$message[] = '= [Importer Output] ========================';
			$message[] = $buffer;
			$message[] = '= [/Importer Output] =======================';

			mail($config['erroraddress']['to'], $subject, implode("\n", $message), 'From: ' . $config['erroraddress']['from']);
		}
	}

	// =========================================================================
	// Data Integrity Check
	// =========================================================================
	$dbmap = new database(getPDO($config['database']));

	$dataErrors = array();
	foreach ($dbmap->getAccounts() as $account) {
		$lastBalance = null;
		foreach ($account->getTransactions() as $transaction) {
			$unexpectedBalance = false;
			if ($lastBalance !== null) {
				$newBalance = $lastBalance + $transaction->getAmount();
				$unexpectedBalance = (money_format('%.2n', $transaction->getBalance()) != money_format('%.2n', $newBalance));
				if ($unexpectedBalance) {
					$dataErrors[] = $transaction->getHash() . ' has an unexpected balance. Expected: ' . money_format('%.2n', $newBalance) . ' - Got: ' . money_format('%.2n', $transaction->getBalance());
				}
			}
			$lastBalance = $transaction->getBalance();
		}
	}

	// If we have an error address, and there was an error, send a mail.
	if (count($dataErrors) > 0 && isset($config['erroraddress']['to']) && $config['erroraddress']['to'] !== false) {
		$subject = '[MoneyTracker Cron] Data integrity error: ' . $dataErrors . ' errors found.';

		$message = array();
		$message[] = 'There was ' . count($dataErrors) . 'errors with data integrity, please see below: ';
		$message[] = '';
		$message[] = '= [Integrity Output] ========================';
		foreach ($dataErrors as $e) { $message[] = $e; }
		$message[] = '= [/Integrity Output] =======================';

		mail($config['erroraddress']['to'], $subject, implode("\n", $message), 'From: ' . $config['erroraddress']['from']);
	}
	foreach ($dataErrors as $e) { echo $e, "\n"; }

	// =========================================================================
	// End
	// =========================================================================
