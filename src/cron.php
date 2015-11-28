#!/usr/bin/php
<?php
	require_once(dirname(__FILE__) . '/config.php');
	require_once(dirname(__FILE__) . '/importer.php');

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

	// TODO: Check data integrity.
?>
