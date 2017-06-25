#!/usr/bin/php
<?php
	require_once(dirname(__FILE__) . '/config.php');

	$pdo = getPDO($config['database'], false);
	$orm = new NotORM($pdo);
	$currentVersion = getDBVersion($pdo);

	echo 'Current Database Version: ', $currentVersion, "\n";
	echo 'Wanted Database Version: ', CURRENT_DB_VERSION, "\n";

	if ($currentVersion == CURRENT_DB_VERSION) { die('Nothing to do.' . "\n"); }
	echo "\n";

	try {
		// Stage the upgrade
		$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
		$pdo->beginTransaction();

		// ==================================================
		// Version upgrade -- 2015-11-27 -- Version: 1
		// ====================
		// Add meta table.
		// ==================================================
		if ($currentVersion < 1) {
			echo 'Upgrading to version: 1', "\n";

			$res = $pdo->exec('CREATE TABLE meta (`metakey` VARCHAR(50) PRIMARY KEY NOT NULL, `metavalue` VARCHAR(50) NOT NULL);');
			$orm->meta->insert(array('metakey' => 'DBVersion', 'metavalue' => '1'));
		}

		// ==================================================
		// Version upgrade -- 2015-11-27 -- Version: 2
		// ====================
		// Test increment.
		// ==================================================
		if ($currentVersion < 2) {
			echo 'Upgrading to version: 2', "\n";
			$orm->meta->update(array('metakey' => 'DBVersion', 'metavalue' => '2'));
		}

		// ==================================================
		// Version upgrade -- 2015-11-27 -- Version: 3
		// ====================
		// Clean up the transaction description crc to not include
		// new line markers or multiple spaces.
		// ==================================================
		if ($currentVersion < 3) {
			echo 'Upgrading to version: 3', "\n";

			$changes = array();
			foreach ($orm->transactions as $row) {
				$r = iterator_to_array($row);
				$t = Transaction::fromArray($r);

				if ($t->getHash() != $r['hash']) {
					$v1Hash = $t->getHash(true, false);
					$v2Hash = $t->getHash(false, false);
					$v3Hash = $t->getHash(false, true);
					$convert = false;

					echo "\t", 'Found: ', $r['hash'], ' - ', $r['description'], "\n";
					if ($r['hash'] == $v1Hash) {
						echo "\t\t", 'Converting from v1 Hash to ';
						$convert = true;
					} else if ($r['hash'] == $v2Hash) {
						echo "\t\t", 'Converting from v2 Hash to ';
						$convert = true;
					}

					if ($convert) {
						echo 'v3 Hash: ', $v3Hash, "\n";
						$orm->transactions->where('hash', $r['hash'])->update(array('hash' => $v3Hash));
					}
				}
			}

			$orm->meta->update(array('metakey' => 'DBVersion', 'metavalue' => '3'));
		}

		// ==================================================
		// Version upgrade -- 2016-10-28 -- Version: 4
		// ====================
		// Allow null descriptions.
		// ==================================================
		if ($currentVersion < 4) {
			echo 'Upgrading to version: 4', "\n";

			$res = $pdo->exec('ALTER TABLE accounts MODIFY description VARCHAR(128);');
			$orm->meta->update(array('metakey' => 'DBVersion', 'metavalue' => '4'));
		}

		// ==================================================
		// Version upgrade -- 2017-06-25 -- Version: 5
		// ====================
		// Allow longer account keys.
		// ==================================================
		if ($currentVersion < 5) {
			echo 'Upgrading to version: 5', "\n";

			$pdo->exec('ALTER TABLE `accounts` CHANGE COLUMN `accountkey` `accountkey` VARCHAR(65) NOT NULL;');
			$pdo->exec('ALTER TABLE `accounts` CHANGE COLUMN `accountnumber` `accountnumber` VARCHAR(55) NOT NULL ;');
			$pdo->exec('ALTER TABLE `transactions` CHANGE COLUMN `accountkey` `accountkey` VARCHAR(65) NOT NULL ;');

			$orm->meta->update(array('metakey' => 'DBVersion', 'metavalue' => '5'));
		}

		// ==================================================
		// Version upgrade -- 2017-06-25 -- Version: 6
		// ====================
		// Fix TescoBank extra data for transactions
		// ==================================================
		if ($currentVersion < 6) {
			$changes = array();
			foreach ($orm->transactions as $row) {
				$r = iterator_to_array($row);
				if (startsWith($r['source'], 'TescoBank/') && !startsWith($r['extra'], '{')) {
					echo 'Updating: ', $r['hash'], "\n";

					$orm->transactions->where('hash', $r['hash'])->update(array('extra' => json_encode(['transactiondate' => $r['extra']])));
				}
			}

			$orm->meta->update(array('metakey' => 'DBVersion', 'metavalue' => '6'));
		}

		// ==================================================
		// Version upgrade -- 2017-06-25 -- Version: 7
		// ====================
		// Longer HashCodes
		// ==================================================
		if ($currentVersion < 7) {
			echo 'Upgrading to version: 7', "\n";

			$pdo->exec('ALTER TABLE `transactions` CHANGE COLUMN `hash` `hash` VARCHAR(255) NOT NULL ;');
			$pdo->exec('ALTER TABLE `taggedtransaction` CHANGE COLUMN `transaction` `transaction` VARCHAR(255) NOT NULL ;');

			$orm->meta->update(array('metakey' => 'DBVersion', 'metavalue' => '7'));
		}

		// Commit the upgrade.
		$pdo->commit();
	} catch (Exception $e) {
		$pdo->rollBack();

		echo 'There was an error performing the upgrade. The upgrade has been rolled back.', "\n";
		var_dump($e);
		die();
	}

	echo "\n";
	echo 'Upgrade complete.', "\n";

