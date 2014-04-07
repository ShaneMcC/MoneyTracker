<?php
	require_once(dirname(__FILE__) . '/../config.php');

	setlocale(LC_MONETARY, 'en_GB.UTF-8');

	$dbmap = new database_mapper(new NotORM(getPDO($config['database'])));
	$accounts = $dbmap->getAccounts();

	foreach ($accounts as $account) {
		echo '<h1>', $account->getFullNumber(), '</h1>';

		echo '<table>';
		echo '<tr>';
		echo '<th>Date</th>';
		echo '<th>Type</th>';
		echo '<th>Description</th>';
		echo '<th>Amount</th>';
		echo '<th>Balance</th>';
		echo '</tr>';

		$lastBalance = null;
		foreach ($account->getTransactions() as $transaction) {
			echo '<tr>';
			echo '<td>', date("Y-m-d H:i:s", $transaction->getTime()), '</td>';
			echo '<td>', $transaction->getType(), '</td>';
			echo '<td>', $transaction->getDescription(), '</td>';
			echo '<td>', money_format('%.2n', $transaction->getAmount()), '</td>';
			echo '<td>', money_format('%.2n', $transaction->getBalance()), '</td>';
			echo '</tr>';

			if ($lastBalance !== null) {
				$newBalance = $lastBalance + $transaction->getAmount();
				if (money_format('%.2n', $transaction->getBalance()) != money_format('%.2n', $newBalance)) {
					echo '<tr>';
					echo '<td colspan=5>';
					echo '<strong><em>';
					echo 'Unexpected balance... Expected: ' . $newBalance;
					echo '</em></strong>';
					echo '</td>';
					echo '</tr>';
				}
			}

			$lastBalance = $transaction->getBalance();
		}

		echo '</table>';
	}
?>