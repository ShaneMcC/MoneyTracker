<?php
	require_once(dirname(__FILE__) . '/../config.php');
	require_once(dirname(__FILE__) . '/functions.php');
	require_once(dirname(__FILE__) . '/classes/database.php');

	echo '<a href="index.php">Main Page</a><br><br>';

	$dbmap = new database(getPDO($config['database']));
	$accounts = $dbmap->getAccounts();

	$tags = array();
	$jsontags = array();
	echo '<pre>';
	foreach ($dbmap->getAllTags() as $t) {
		$tags[$t['tagid']] = $t['category'] . ' :: ' . $t['tag'];
		$jsontags[$t['category']][$t['tag']] = $t['tagid'];
		echo $t['tagid'], ' -> ', $tags[$t['tagid']], "\n";
	}
	echo '</pre>';

	foreach ($accounts as $account) {
		echo '<h1>', $account->getFullNumber(), '<small><br>', $account->getDescriptionOrType(). '</small></h1>';

		echo '<table>';
		echo '<tr>';
		echo '<th>Date</th>';
		echo '<th>Type</th>';
		echo '<th>Description</th>';
		echo '<th>Amount</th>';
		echo '<th>Balance</th>';
		echo '<th>Hash</th>';
		echo '<th>Tags</th>';
		echo '</tr>';

		$lastBalance = null;
		$cutoff = strtotime("01 jan 2014");
		foreach ($account->getTransactions() as $transaction) {

			$unexpectedBalance = false;
			if ($lastBalance !== null) {
				$newBalance = $lastBalance + $transaction->getAmount();
				$unexpectedBalance = (money_format('%.2n', $transaction->getBalance()) != money_format('%.2n', $newBalance));
			}

			// if ($transaction->getTime() < $cutoff) { continue; }

			echo ($unexpectedBalance) ? '<tr style="border: 1px solid black; border-bottom: none; color: #F00">' : '<tr>';
			echo '<td>', date("Y-m-d H:i:s", $transaction->getTime()), '</td>';
			echo '<td> <attr title="', $transaction->getType(), '">', $transaction->getTypeCode(), '</attr></td>';
			echo '<td>', $transaction->getDescription(), '</td>';
			echo '<td>', money_format('%.2n', $transaction->getAmount()), '</td>';
			echo '<td>', money_format('%.2n', $transaction->getBalance()), '</td>';
			echo '<td>', $transaction->getHash(), '</td>';
			echo '<td class="transactiontags" data-tags="', htmlspecialchars(json_encode($transaction->getTags())), '" data-id="', $transaction->getHash(), '" id="tags-', $transaction->getHash(), '">';
			echo '<div class="tagtext">';
			$a = array();
			foreach ($transaction->getTags() as $t) {
				$a[] = $tags[$t[0]];
			}
			echo '[', implode(', ', $a), ']';
			echo '</div>';
			echo '</td>';
			echo '</tr>';

			if ($unexpectedBalance) {
				echo '<tr>';
				echo '<td colspan=5>';
				echo '<strong><em>';
				echo 'Unexpected balance... Expected: ' . $newBalance;
				echo '</em></strong>';
				echo '</td>';
				echo '</tr>';
			}

			$lastBalance = $transaction->getBalance();
		}

		echo '</table>';
	}
?>
