<?php
	function parseBool($input) {
		$in = strtolower($input);
		return ($in === true || $in == 'true' || $in == '1' || $in == 'on' || $in == 'yes');
	}

	/**
	 * Show an alert.
	 *
	 * @param $message A message array to display modally.
	 *                 The message array is the same as that used for
	 *                 showing messages normally. The colour paramer is
	 *                 ignored however by the modal dialog.
	 */
	function showAlert($message) {
		global $templateFactory;
		$templateFactory->get('alert')->setVar('message', $message)->display();
	}

	function getTagHTML($transaction, $tags) {
		foreach ($transaction->getTags() as $t) {
			echo '<span class="label label-success" data-tagid="', $t[0], '">';
			echo $tags[$t[0]], ' (', money_format('%.2n', $t[1]), ')';
			echo '</span>&nbsp;';
		}

		$remaining = abs($transaction->getAmount()) - $transaction->getTagValue();
		if ($remaining > 0) {
			echo '<span class="label label-danger" data-remaining="', $remaining, '">';
			echo 'Untagged (', money_format('%.2n', $remaining), ')';
			echo '</span>&nbsp;';
		}

	}

	/**
	 * Show an alert.
	 *
	 * @param $title Title of the alert
	 * @param $body Body of alert
	 * @param $raw Is body raw html?
	 */
	function alert($title, $body, $raw = false) {
		showAlert(array('', $title, $body, !$raw));
	}

	/**
	 * Wrap var_dump in a modal alert.
	 *
	 * @param $var Var to dump.
	 */
	function vardump($var) {
		ob_start();
		var_dump($var);
		$data = ob_get_contents();
		ob_end_clean();
		alert('var_dump', '<pre>' . htmlspecialchars($data) . '</pre>', true);
	}
?>