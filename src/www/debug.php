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
		echo '<h1>', $account->getFullNumber(), '</h1>';

		echo '<table>';
		echo '<tr>';
		echo '<th>Date</th>';
		echo '<th>Type</th>';
		echo '<th>Description</th>';
		echo '<th>Amount</th>';
		echo '<th>Balance</th>';
		// echo '<th>Hash</th>';
		echo '<th>Tags</th>';
		echo '</tr>';

		$lastBalance = null;
		$cutoff = strtotime("01 jan 2014");
		foreach ($account->getTransactions() as $transaction) {
			// if ($transaction->getTime() < $cutoff) { continue; }
			echo '<tr>';
			echo '<td>', date("Y-m-d H:i:s", $transaction->getTime()), '</td>';
			echo '<td> <attr title="', $transaction->getType(), '">', $transaction->getTypeCode(), '</attr></td>';
			echo '<td>', $transaction->getDescription(), '</td>';
			echo '<td>', money_format('%.2n', $transaction->getAmount()), '</td>';
			echo '<td>', money_format('%.2n', $transaction->getBalance()), '</td>';
			// echo '<td>', $transaction->getHash(), '</td>';
			echo '<td class="transactiontags" data-tags="', htmlspecialchars(json_encode($transaction->getTags())), '" data-id="', $transaction->getHash(), '" id="tags-', $transaction->getHash(), '">';
			echo '<div class="tagtext">';
			$a = array();
			foreach ($transaction->getTags() as $t) {
				$a[] = $tags[$t[0]];
			}
			echo '[', implode(', ', $a), ']';
			echo '</div>';
			echo '<div class="tagselect" />';
			echo '</td>';
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

<script src="https://code.jquery.com/jquery-2.1.0.min.js"></script>
<script>
	var tags = <?=json_encode($jsontags);?>

	function createDropDown(selected) {
		var s = $("<select name=\"tag[]\" />");
		$.each(tags, function(group, grouptags) {
			var g = $('<optGroup/>').attr('label', group).appendTo(s);
			$.each(grouptags, function(tag, id) {
				var o = $('<option/>').val(id).text(tag);
				o.appendTo(g);
			});
		});

		return s;
	}

	$('td.transactiontags div.tagtext').click(function() {
		$(this).hide();
		var parent = $(this).parent();

		currentTags = jQuery.parseJSON(parent.attr('data-tags'));
		hash = parent.attr('data-id');

		var selectDiv = $('div.tagselect', parent);

		$.each(currentTags, function() {
			id = this[0];
			value = this[1];
			createDropDown(id).appendTo(selectDiv);
		});

		selectDiv.show();
	});
</script>