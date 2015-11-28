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
		private $debug = false;

		public function __construct($dbdata, $debug = false) {
			$this->pdo = getPDO($dbdata);
			$this->db = new NotORM($this->pdo);
			$this->debug = $debug;
		}

		public function import($bank) {
			try {
				$accounts = $bank->getAccounts(true);
			} catch (Exception $e) {
				echo 'Error in ', $bank->__toString(), ': ', $e->getMessage(), "\n\n";
				echo $e->getTraceAsString(), "\n";
				return false;
			}
			if (!is_array($accounts)) { return false; } // No accounts.

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

				$a->sortTransactions(false);
				foreach ($a->getTransactions() as $t) {
					echo '.';
					$transData = $t->toArray();
					// var_dump($transData);

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

					if ($this->debug) {
						echo '[DEBUG MODE] Transaction found: ', $transData['description'], ' [', $transData['typecode'], '] => ', $transData['amount'], ' (', date("Y-m-d H:i:s", $transData['time']),')', "\n";
					} else {
						$result = $this->db->transactions->insert_update(array('hash' => $key), $transData, $updateData);
						if ($result == false) {
							echo 'Failed to update: ', $transData['description'], ' [', $transData['typecode'], '] => ', $transData['amount'], ' (', date("Y-m-d H:i:s", $transData['time']),')', "\n";
						} else {
							echo 'Inserted/Updated: ', $transData['description'], ' [', $transData['typecode'], '] => ', $transData['amount'], ' (', date("Y-m-d H:i:s", $transData['time']),')', "\n";
						}
					}
				}
			}

			// Update accounts table with data gleamed from looking at transactions.
			foreach ($accounts as $a) {
				$accData = $a->toArray();
				$key = $accData['accountkey'];
				unset($accData['accountkey']);

				if ($this->debug) {
					echo '[DEBUG MODE] Found Account: ', $key, "\n";
				} else {
					$result = $this->db->accounts->insert_update(array('accountkey' => $key), $accData, $accData);
					if ($result == false) {
						echo 'Failed to update account data for: ', $key, "\n";
					} else {
						echo 'Inserted/Updated account data for: ', $key, "\n";
					}
				}
			}

			return true;
		}
	}

?>
