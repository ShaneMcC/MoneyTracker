<?php
	require_once(dirname(__FILE__) . '/../Bank.php');
	require_once(dirname(__FILE__) . '/../Account.php');
	require_once(dirname(__FILE__) . '/../Transaction.php');

	require_once(dirname(__FILE__) . '/../www/classes/database.php');

	/**
	 * Code to use HSBCMobile, to update a regular HSBC account.
	 *
	 * This will only update accounts that have already been discovered
	 * previously by a "HSBC" Bank object, and will not include proper
	 * transaction types.
	 *
	 * A re-scrape with the regular HSBC scraper should update the
	 * transaction types if done frequently enough.
	 */
	class HSBCMerge extends Bank {
		private $account = '';
		private $password = '';
		private $memorableinfo = '';

		private $mobile;
		private $database;
		private $accountMap;

		/**
		 * Create a HSBCMerge.
		 *
		 * @param $account Account number (IB...)
		 * @param $password Secret Word
		 * @param $memorableinfo Secure Key Code
		 */
		public function __construct($account, $password, $memorableinfo) {
			parent::__construct();
			$this->account = $account;
			$this->password = $password;
			$this->memorableinfo = $memorableinfo;

			// Create an instance of HSBCMobile to do the bulk of the work.
			$this->mobile = new HSBCMobile($account, $password, $memorableinfo);
		}

		/** Get the Database object. */
		private function getDatabase() {
			if ($this->database == null) {
				global $config;
				$this->database = new database(getPDO($config['database']));
			}

			return $this->database;
		}

		/**
		 * String representation of this bank.
		 */
		public function __toString() {
			return 'HSBC/' . $this->account;
		}

		/** {@inhertiDoc} */
		public function getAccounts($useCached = true, $transactions = false, $historical = false, $historicalVerbose = false, $historicalLimit = 0) {
			$mobileAccounts = $this->mobile->getAccounts($useCached, $transactions, $historical, $historicalVerbose, $historicalLimit);
			$realAccounts = array();

			foreach ($this->getDatabase()->getDB()->accounts->where('source', $this->__toString()) as $row) {
				$r = iterator_to_array($row);
				$acct = Account::fromArray($r);
				$realAccounts[] = $acct;
			}

			$map = array();
			$endAccounts = array();
			foreach ($realAccounts as $ra) {
				// Find matching mobile account.
				$matched = FALSE;

				foreach ($mobileAccounts as $ma) {
					if ($ma->getSortCode() == $ra->getSortCode()) {
						// Possible Match.
						$racc = preg_replace('#[^0-9]#', '', $ra->getAccountNumber());
						$macc = preg_replace('#[^0-9]#', '', $ma->getAccountNumber());

						if (endsWith($racc, $macc)) {
							if ($matched === FALSE) {
								// Found a match, and we haven't had one before!
								$matched = $ma;
							} else {
								// Found a match, but we have had one before :(
								$matched = NULL;
							}
						}
					}
				}

				if ($matched !== FALSE && $matched !== null) {
					echo 'Matched: ', $ra->getAccountKey(), ' to ', $matched->getAccountKey(), "\n";
					$map[$ra->getAccountKey()] = array('key' => $matched->getAccountKey(), 'sortcode' => $matched->getSortCode(), 'number' => $matched->getAccountNumber());

					// Merge the accounts into a new account object.
					// Take values from the matched "real" account, then update
					// them with data from the donor mobile account.
					$donor = $matched->toArray();
					$merge = $ra->toArray();

					foreach (array('lastbalance', 'available', 'misc', 'extra') as $k) {
						if (isset($donor[$k])) {
							$merge[$k] = $donor[$k];
						}
					}

					$new = Account::fromArray($merge);
					$new->setTransactions($matched->getTransactions());

					$endAccounts[] = $new;
				} else if ($matched === null) {
					echo 'Multiple matches for: ', $ra->getAccountKey(), "\n";
				} else if ($matched === null) {
					echo 'No matches for: ', $ra->getAccountKey(), "\n";
				}
			}

			$this->accountMap = $map;

			return $endAccounts;
		}

		/** {@inhertiDoc} */
		public function updateTransactions($account, $historical = false, $historicalVerbose = true, $historicalLimit = 0) {
			// Details for mapping
			$original = array('key' => $account->getAccountKey(), 'sortcode' => $account->getSortCode(), 'number' => $account->getAccountNumber());
			$mapped = $this->accountMap[$original['key']];

			// Map the account.
			$account->setAccountNumber($mapped['number']);
			$account->setSortCode($mapped['sortcode']);

			// Update the transactions.
			$this->mobile->updateTransactions($account, $historical, $historicalVerbose, $historicalLimit);

			// Unmap the account.
			$account->setAccountNumber($original['number']);
			$account->setSortCode($original['sortcode']);

			// Fix the transactions.
			foreach ($account->getTransactions() as &$trans) {
				$this->updateTransaction($trans, $original['key']);
			}
		}

		private function updateTransaction(&$transaction, $key) {
			// Transactions are (with the exception of tags) immutable, so update it with reflection hacks.
			$_Transaction = new ReflectionClass("Transaction");
			$_Transaction_myAccountKey = $_Transaction->getProperty("myAccountKey");
			$_Transaction_myAccountKey->setAccessible(true);
			$_Transaction_mySource = $_Transaction->getProperty("mySource");
			$_Transaction_mySource->setAccessible(true);
			$_Transaction_myTypeCode = $_Transaction->getProperty("myTypeCode");
			$_Transaction_myTypeCode->setAccessible(true);

			// $transaction->myAccountKey = $key;
			$_Transaction_myAccountKey->setValue($transaction, $key);

			// $transaction->mySource = $this->__toString();
			$_Transaction_mySource->setValue($transaction, $this->__toString());

			// $transaction->myTypeCode = "*" . $transaction->getTypeCode();
			$_Transaction_myTypeCode->setValue($transaction, "*" . $transaction->getTypeCode());
		}

	}
?>
