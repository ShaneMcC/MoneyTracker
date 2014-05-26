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
		$type = ($transaction->getAmount() < 0) ? 'primary' : 'success';
		foreach ($transaction->getTags() as $t) {
			echo '<span class="transactionTag label label-', $type, '" data-tagid="', $t[0], '">';
			echo $tags[$t[0]], ' (', money_format('%.2n', $t[1]), ')';
			echo '</span> ';
		}

		$remaining = abs($transaction->getAmount()) - $transaction->getTagValue();
		if ($remaining > 0) {
			echo '<span class="untaggedTag label label-danger" data-remaining="', $remaining, '">';
			echo 'Untagged (', money_format('%.2n', $remaining), ')';
			echo '</span> ';
		}
	}

	function getValidPeriods() {
		global $__validPeriods;
		if (!isset($__validPeriods)) {
			$__validPeriods = array('last7days' => array('name' => 'Last 7 days',
			                                             'start' => strtotime('-7 days 00:00:00'),
			                                             'end' => time()
			                                             ),
			                        'this' => array('name' => 'This Month',
			                                        'start' => mktime(0, 0, 0, date("m"), 1, date("Y")),
			                                        'end' => time(),
			                                        ),
			                        'last' => array('name' => 'Previous Month',
			                                        'start' => mktime(0, 0, 0, date("m")-1, 1, date("Y")),
			                                        'end' => mktime(0, 0, 0, date("m"), 1, date("Y")),
			                                        ),
			                        '2last' => array('name' => '2 months ago',
			                                         'start' => mktime(0, 0, 0, date("m")-2, 1, date("Y")),
			                                         'end' => mktime(0, 0, 0, date("m")-1, 1, date("Y")),
			                                         ),
			                        '3last' => array('name' => '3 months ago',
			                                         'start' => mktime(0, 0, 0, date("m")-3, 1, date("Y")),
			                                         'end' => mktime(0, 0, 0, date("m")-2, 1, date("Y")),
			                                         ),
			                        'last2' => array('name' => 'Last 2 months',
			                                         'start' => mktime(0, 0, 0, date("m")-2, 1, date("Y")),
			                                         'end' => mktime(0, 0, 0, date("m"), 1, date("Y")),
			                                         ),
			                        'last3' => array('name' => 'Last 3 months',
			                                         'start' => mktime(0, 0, 0, date("m")-3, 1, date("Y")),
			                                         'end' => mktime(0, 0, 0, date("m"), 1, date("Y")),
			                                         ),
			                        'thisyear' => array('name' => 'This year',
			                                            'start' => mktime(0, 0, 0, 1, 1, date("Y")),
			                                            'end' => time(),
			                                            ),
			                        );
		}

		return $__validPeriods;
	}

	function getPeriod($name = '') {
		$periods = getValidPeriods();

		if (empty($name) || !isset($periods[$name])) {
			$name = 'last7days';
		}

		return array($periods[$name]['name'], $periods[$name]['start'], $periods[$name]['end']);
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
