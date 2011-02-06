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
					$bank->updateTransactions($a, true, true);
				} else {
					$r->update($accData);
					$bank->updateTransactions($a, false, false);
				}

				// The first set of transactions we import are current.
				// After this, we will start finding historical ones, which probably
				// overlap.
				$isHistoricalOverlap = false;
				$hasHistoricalOverlap = false;
				$lastDate = time();
				foreach ($a->getTransactions() as $t) {
					$transData = $t->toArray();

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

					if (!$isHistoricalOverlap) {
						$result = $this->db->transactions->insert($transData);
						if ($result == false) {
							echo 'Failed to insert transaction: ', "\n";
							var_dump($transData);
						}
					}
				}
			}
		}
	}

?>
