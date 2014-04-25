<?php
	require_once(dirname(__FILE__) . '/../Bank.php');
	require_once(dirname(__FILE__) . '/../Account.php');
	require_once(dirname(__FILE__) . '/../Transaction.php');

	require_once(dirname(__FILE__) . '/../3rdparty/simpletest/browser.php');
	require_once(dirname(__FILE__) . '/../3rdparty/phpquery/phpQuery/phpQuery.php');

	/**
	 * Code to scrape Halifax to get Account and Transaction objects.
	 */
	class Halifax extends WebBank {
		private $account = '';
		private $password = '';
		private $memorableinfo = '';

		private $accounts = null;
		private $accountLinks = array();

		/**
		 * Create a Halifax.
		 *
		 * @param $account Account number (IB...)
		 * @param $password Secret Word
		 * @param $memorableinfo Secure Key Code
		 */
		public function __construct($account, $password, $memorableinfo) {
			parent::__construct();
			$this->account = $account;
			$this->password = $password;
			// The '.' is to push everything back one for login.
			$this->memorableinfo = '.'.$memorableinfo;
		}

		/**
		 * String representation of this bank.
		 */
		public function __toString() {
			return 'Halifax/' . $this->account;
		}

		/**
		 * Force a fresh login.
		 *
		 * @return true if login was successful, else false.
		 */
		public function login($fresh = false) {
			if ($fresh) {
				$this->newBrowser(false);
				$page = $this->browser->get('https://www.halifax-online.co.uk/');
				$page = $this->getDocument($page);
			} else {
				$this->newBrowser(true);
				$page = $this->browser->get('https://secure.halifax-online.co.uk/personal/a/account_overview_personal/');
				if ($this->isLoggedIn($page)) {
					return true;
				}
			}

			// Fill out the form and submit it.
			$this->browser->setFieldById('frmLogin:strCustomerLogin_userID', $this->account);
			$this->browser->setFieldById('frmLogin:strCustomerLogin_pwd', $this->password);
			$page = $this->browser->submitFormById('frmLogin');

			// Set the fields
			if (preg_match('@Please enter characters ([0-9]+), ([0-9]+) and ([0-9]+) from your memorable information@', $page, $matches)) {
				// Set the fields
				$this->browser->setFieldById('frmentermemorableinformation1:strEnterMemorableInformation_memInfo1', '&nbsp;' . strtolower($this->memorableinfo[$matches[1]]));
				$this->browser->setFieldById('frmentermemorableinformation1:strEnterMemorableInformation_memInfo2', '&nbsp;' . strtolower($this->memorableinfo[$matches[2]]));
				$this->browser->setFieldById('frmentermemorableinformation1:strEnterMemorableInformation_memInfo3', '&nbsp;' . strtolower($this->memorableinfo[$matches[3]]));
				$page = $this->browser->submitFormById('frmentermemorableinformation1', array('frmentermemorableinformation1:btnContinue' => null));

				// And done.
				$this->saveCookies();
				return $this->isLoggedIn($page);
			}

			return false;
		}

		public function isLoggedIn($page) {
			return (strpos($page, 'Securely signed in') !== FALSE);
		}

		/**
		 * Take a Balance as exported by HSBC, and return it as a standard balance.
		 *
		 * @param $balance Balance input (eg: " £ 1.00 D " or " &163; 1.00 D ")
		 * @return Correct balance (eg: "-1.00")
		 */
		private function parseBalance($balance) {
			// Check for negative
			$balance = str_replace(',', '', $balance);
			preg_match('@([0-9]+.[0-9]+)$@', $balance, $matches);
			return $matches[1];
		}

		/**
		 * Get the sub-accounts of this login.
		 * This will return cached account objects.
		 *
		 * @param $useCached (Default: true) Return cached values if possible?
		 * @param $transactions (Default: false) Also update transactions?
		 *                      (This will force a reload of the accounts only if
		 *                       none of them have any associated transactions)
		 * @param $historical (Default: false) Also try to get historical
		 *                    transactions?
		 * @param $historicalVerbose (Default: false) Should verbose data be
		 *                           collected for historical, or is a single-line
		 *                           description ok?
		 * @return accounts associated with this login.
		 */
		public function getAccounts($useCached = true, $transactions = false, $historical = false, $historicalVerbose = false) {
			// Check if we only want cached data.
			if ($useCached) {
				// Check if we have some accounts.
				if ($this->accounts != null && count($this->accounts) > 0) {
					// If we want transactions, check that at least one has some.
					if ($transactions) {
						foreach ($this->accounts as $a) {
							if (count($a->getTransactions() > 0)) {
								// Found some transactions, return the cache!
								return $this->accounts;
							}
						}
					} else {
						return $this->accounts;
					}
				}
			}

			$this->accounts = array();

			$page = $this->getPage('https://secure.halifax-online.co.uk/personal/a/account_overview_personal/');
			if (!$this->isLoggedIn($page)) { return $this->accounts; }
			$page = $this->getDocument($page);

			$accounts = array();

			$accountdetails = $page->find('#lstAccLst');
			$items = $page->find('li.clearfix', $accountdetails);
			$owner = $this->cleanElement($page->find('p.user span.name'));
			for ($i = 0; $i < count($items); $i++) {
				// Get the values
				$type = $page->find('h2 a img', $items->eq($i))->attr("alt");

				$numbers  = $this->cleanElement($page->find('p.numbers', $items->eq($i)));
				preg_match('@Sort Code</span>([0-9]{2}-[0-9]{2}-[0-9]{2}), <span class="[^"]+">Account Number</span> ([0-9]+)@', $numbers, $matches);
				if (!isset($matches[1])) { die('TEST'); }
				$sortcode = $matches[1];
				$number = $matches[2];
				$balance = $this->cleanElement($page->find('div.accountBalance span.balanceShowMeAnchor', $items->eq($i)));
				$balance = $this->parseBalance($balance);

				// Finally, create an account object.
				$account = new Account();
				$account->setSource($this->__toString());
				$account->setType($type);
				$account->setOwner($owner);
				$account->setSortCode($sortcode);
				$account->setAccountNumber($number);
				$account->setBalance($balance);

				$accountKey = preg_replace('#[^0-9]#', '', $account->getSortCode().$account->getAccountNumber());
				$this->accountLinks[$accountKey] = $page->find('h2 a', $items->eq($i))->attr("href");

				$overdraft = $this->parseBalance($this->cleanElement($page->find('div.accountBalance p.accountMsg', $items->eq($i))));
				$account->setLimits('Overdraft: ' . $overdraft);

				if ($transactions) {
					$this->updateTransactions($account, $historical, $historicalVerbose);
				}

				$this->accounts[] = $account;
			}

			return $this->accounts;
		}

		/**
		 * Take transaction data, and clean it up a bit.
		 *
		 * @param $transaction Input transaction
		 * @return cleaned up transaction
		 */
		private function cleanTransaction($transaction) {
			// Get a better date
			$transaction['date'] = strtotime($transaction['date']);

			// Rather than separate in/out, lets just have a +/- amount
			if (!empty($transaction['out'])) {
				$transaction['amount'] = 0 - $transaction['out'];
			} else if (!empty($transaction['in'])) {
				$transaction['amount'] = $transaction['in'];
			}

			// Unset any unneeded values
			unset($transaction['out']);
			unset($transaction['in']);
			unset($transaction['balance_type']);

			return $transaction;
		}

		private function extractTransactions($page) {
			$transactions = array();

			// Look for errors.
			$items = $page->find('table.statement tbody tr td.errorMsg');
			if (count($items) > 0) { return $transactions; }

			// Now get the transactions.
			$items = $page->find('table.statement tbody tr');
			foreach ($items as $row) {
				echo 'Got Item', "\n";
				$columns = pq($row, $page)->find('td');

				// Pull out the data
				$transaction['date'] = $this->cleanElement(pq($row, $page)->find('th'));
				$desc = $columns->eq(0)->find('span.splitString');
				$transaction['description'] = array();
				foreach ($desc as $d) {
					$transaction['description'][] = $this->cleanElement($d);
				}
				$transaction['description'] = implode(' // ', $transaction['description']);

				$transaction['typecode'] = $this->cleanElement($columns->eq(1)->find('abbr'));
				$transaction['type'] = $columns->eq(1)->find('abbr')->attr('title');

				$transaction['in'] = str_replace(',', '', $this->cleanElement($columns->eq(2)));
				$transaction['out'] = str_replace(',', '', $this->cleanElement($columns->eq(3)));
				$transaction['balance'] = str_replace(',', '', $this->cleanElement($columns->eq(4)));
				$transaction = $this->cleanTransaction($transaction);

				$transactions[] = $transaction;
			}

			return $transactions;
		}

		/**
		 * Update the transactions on the given account object.
		 *
		 * @param $account Account to update.
		 * @param $historical (Default: false) Also try to get historical
		 *                    transactions?
		 * @param $historicalVerbose (Default: false) Should verbose data be
		 *                           collected for historical, or is a single-line
		 *                           description ok?
		 */
		public function updateTransactions($account, $historical = false, $historicalVerbose = true) {
			$account->clearTransactions();
			$accountKey = preg_replace('#[^0-9]#', '', $account->getSortCode().$account->getAccountNumber());
			$page = $this->getPage('https://secure.halifax-online.co.uk/' . $this->accountLinks[$accountKey]);
			if (!$this->isLoggedIn($page)) { return false; }
			$page = $this->getDocument($page);

			$available = strip_tags($this->cleanElement($page->find('div.accountBalance')));
			if (preg_match('@Money available:[^0-9]*([0-9]+.[0-9]+)@', $available, $matches)) {
				$account->setAvailable($matches[1]);
			}
			$transactions = $this->extractTransactions($page);

			// Now try the historical ones.
			if ($historical) {
				// Keep going until we can't go any more.
				while (true) {
					$page = $this->browser->submitFormById('pnlgrpStatement:conS1:frmVSPUpper', array('pnlgrpStatement:conS1:frmVSPUpper:btnViewPreviousStatement' => 'null'));
					$page = $this->getDocument($page);

					$olderTransactions = $this->extractTransactions($page);
					if (count($olderTransactions) == 0) { break; }
					$transactions = array_merge($transactions, $olderTransactions);
				}
			}


			// Now go through the transactions bottom-top so that we have them in the
			// order that they occured.
			$transactions = array_reverse($transactions);

			// To make ordering the transactions easier, rather than having
			// all the days transactions having the same time, we add a second
			// each time. (so the first transaction of the day happened at
			// 00:00:00 the second at 00:00:01 and so on.
			$dayCount = 0;
			$lastDate = 0;

			// Ignore transactions on the most-recent current date, as there may be more to come.
			if (count($transactions) > 0) {
				$firstDate = $transactions[count($transactions) -1 ]['date'];
				foreach ($transactions as $transaction) {
					// Skip the first day, cos we can't be sure we have all the
					// transactions for it.
					if ($transaction['date'] == $firstDate) { continue; }

					if ($lastDate == $transaction['date']) {
						$dayCount++;
						$transaction['date'] += $dayCount;
					} else {
						$lastDate = $transaction['date'];
						$dayCount = 0;
					}
					$account->addTransaction(new Transaction($this->__toString(), $account->getAccountKey(), $transaction['date'], $transaction['type'], $transaction['typecode'], $transaction['description'], $transaction['amount'], $transaction['balance']));
				}
			}
		}
	}
?>
