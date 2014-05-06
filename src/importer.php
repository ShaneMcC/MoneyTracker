<?php
	require_once(dirname(__FILE__) . '/functions.php');
	require_once(dirname(__FILE__) . '/Bank.php');
	require_once(dirname(__FILE__) . '/Account.php');
	require_once(dirname(__FILE__) . '/Transaction.php');
	require_once(dirname(__FILE__) . '/3rdparty/notorm/NotORM.php');

	/**
	 * This class will take a Bank object and import all its data into the mySQL
	 * database.
	 *
	 * If the account is not known in the database then all historical transaction
	 * data will be requested and imported.
	 * If the account is already known, then only the current transactions will be
	 * requested and imported.
	 */
	class Importer {
		private $db = null;
		private $pdo = null;

		public function __construct($dbdata) {
			$this->pdo = getPDO($dbdata);
			$this->db = new NotORM($this->pdo);
		}

		public function import($bank) {
			$accounts = $bank->getAccounts(true);

			foreach ($accounts as $a) {
				$r = $this->db->accounts->select('accountkey')->where('accountkey', $a->getAccountKey());
				$row = $r->fetch();
				$key = $row['accountkey'];
				$accData = $a->toArray();

				if ($key == NULL) {
					$key = $accData['accountkey'];
					$result = $this->db->accounts->insert($accData);

					echo 'ALL transactions', "\n";
					$bank->updateTransactions($a, true, true);
				} else {
					echo 'RECENT transactions', "\n";
					$bank->updateTransactions($a, false, false);
				}

				// The first set of transactions we import are current.
				// After this, we will start finding historical ones, which probably
				// overlap.
				$isHistoricalOverlap = false;
				$hasHistoricalOverlap = false;
				$lastDate = time();
				$a->sortTransactions(false);
				foreach ($a->getTransactions() as $t) {
					echo '.';
					$transData = $t->toArray();
					// var_dump($transData);

					// Is the date of the current transaction above the date of the last
					// one we processed, and is this the first time it has happened?
					// If so, we have moved into the historical overlap.
					if (!$hasHistoricalOverlap && $transData['time'] > $lastDate) {
						$isHistoricalOverlap = true;
						$hasHistoricalOverlap = true;
					} else if ($isHistoricalOverlap && $transData['time'] < $lastDate) {
						// Have we got back to a lesser date?
						$isHistoricalOverlap = false;
					} else {
						// Accounting.
						$lastDate = $transData['time'];
					}

					// I forget the purpose of the "Historical Overlap" stuff...
					// if (!$isHistoricalOverlap) {
						// $result = $this->db->transactions->insert($transData);
						// Data for update.
						$key = $transData['hash'];
						unset($transData['hash']);
						$updateData = $transData;

						$genericType = ($transData['typecode'][0] == '*');
						if ($genericType) {
							$transData['typecode'] = ltrim($transData['typecode'], '*');
							unset($updateData['typecode']);
							unset($updateData['type']);
						}

						$result = $this->db->transactions->insert_update(array('hash' => $key), $transData, $updateData);
						if ($result == false) {
							echo 'Failed to update: ', $transData['description'], ' [', $transData['typecode'], '] => ', $transData['amount'], ' (', date("Y-m-d H:i:s", $transData['time']),')', "\n";
						} else {
							echo 'Inserted/Updated: ', $transData['description'], ' [', $transData['typecode'], '] => ', $transData['amount'], ' (', date("Y-m-d H:i:s", $transData['time']),')', "\n";
						}
					// }

					$first = false;
				}
			}

			// Update accounts table with data gleamed from looking at transactions.
			foreach ($accounts as $a) {
				$accData = $a->toArray();
				$key = $accData['accountkey'];
				unset($accData['accountkey']);
				$result = $this->db->accounts->insert_update(array('accountkey' => $key), $accData, $accData);
			}
		}
	}

?>
