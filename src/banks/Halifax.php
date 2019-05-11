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
			if (!$fresh) {
				$this->newBrowser(true);
				$page = $this->browser->get('https://secure.halifax-online.co.uk/personal/a/account_overview_personal/');
				if ($this->isLoggedIn($page)) {
					return true;
				} if ($this->signedOut($page)) {
					$fresh = true;
				}
			}
			if ($fresh) {
				$this->newBrowser(false);
				$page = $this->browser->get('https://www.halifax-online.co.uk/');
				$page = $this->getDocument($page);
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

		private function signedOut($page) {
			return (strpos($page, 'Sorry, we\'ve had to sign you out') !== FALSE);
		}

		public function isLoggedIn($page) {
			return  (strpos($page, 'Securely signed in') !== FALSE) || (strpos($page, 'Last signed in') !== FALSE);
		}

		private function is2015style($page) {
			return (strpos($page, 'Last signed in') !== FALSE);
		}

		/**
		 * Take a Balance as exported by Halifax, and return it as a standard balance.
		 *
		 * @param $balance Balance input (eg: "£1.00" or "-£1.00")
		 * @return Correct balance (eg: "1.00" or "-1.00")
		 */
		private function parseBalance($balance) {
			$negative = strpos($balance, '-') !== FALSE;
			$balance = str_replace(',', '', $balance);
			if (preg_match('@([0-9]+.[0-9]+)$@', $balance, $matches)) {
				return $negative ? 0 - $matches[1] : $matches[1];
			} else {
				return '';
			}
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
		public function getAccounts($useCached = true, $transactions = false, $historical = false, $historicalVerbose = false, $historicalLimit = 0) {
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
			$is2015 = $this->is2015style($page);
			$page = $this->getDocument($page);

			$accounts = array();

			if ($is2015) {
				$items = $page->find('div.des-m-sat-xx-account-information');
				$owner = $this->cleanElement($page->find('span.m-hf-02-name'));
			} else {
				$accountdetails = $page->find('#lstAccLst');
				$items = $page->find('li.clearfix', $accountdetails);
				$owner = $this->cleanElement($page->find('p.user span.name'));
			}
			for ($i = 0; $i < count($items); $i++) {
				// Get the values
				if ($is2015) {
					$type = $page->find('dd.account-name a', $items->eq($i))->attr("data-wt-ac");

					$numbers  = $this->cleanElement($page->find('div.section', $items->eq($i)));
					preg_match('@([0-9]{2}-[0-9]{2}-[0-9]{2}).*([0-9]{8})@ims', strip_tags($numbers), $matches);
				} else {
					$type = $page->find('h2 a img', $items->eq($i))->attr("alt");
					$numbers  = $this->cleanElement($page->find('p.numbers', $items->eq($i)));
					preg_match('@Sort Code</span>([0-9]{2}-[0-9]{2}-[0-9]{2})[^,]+, <span class="[^"]+">Account Number</span> ([0-9]+)@', $numbers, $matches);
				}
				if (!isset($matches[1])) { continue; }
				$sortcode = $matches[1];
				$number = $matches[2];

				if ($is2015) {
					$balance = $this->cleanElement($page->find('p.balance span', $items->eq($i)));
				} else {
					$balance = $this->cleanElement($page->find('div.accountBalance p.balance', $items->eq($i)));
				}
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
				if ($is2015) {
					$this->accountLinks[$accountKey] = $page->find('a#lnkAccName_des-m-sat-xx-1', $items->eq($i))->attr("href");
				} else {
					$this->accountLinks[$accountKey] = $page->find('h2 a', $items->eq($i))->attr("href");
				}

				if ($is2015) {
					$acctvalues = $page->find('table.account-values', $items->eq($i));
					$text = strip_tags($acctvalues->html());
					if (preg_match('@Money available including your[^0-9]+([0-9]+)[^0-9]+?overdraft@ims', $text, $matches)) {
						$account->setLimits('Overdraft: ' . $matches[1]);
					}

					$available = $page->find('th.available-balance', $acctvalues);
					$account->setAvailable($this->parseBalance($this->cleanElement($available)));
				} else {
					$overdraft = $this->parseBalance($this->cleanElement($page->find('div.accountBalance p.accountMsg', $items->eq($i))));
					$account->setLimits('Overdraft: ' . $overdraft);
				}

				if ($transactions) {
					$this->updateTransactions($account, $historical, $historicalVerbose, $historicalLimit);
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
			$transaction['date'] = strtotime($transaction['date'] . ' Europe/London');

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

				$transaction['in'] = $this->parseBalance($this->cleanElement($columns->eq(2)));
				$transaction['out'] = $this->parseBalance($this->cleanElement($columns->eq(3)));
				$transaction['balance'] = $this->parseBalance($this->cleanElement($columns->eq(4)));
				$transaction = $this->cleanTransaction($transaction);

				$transactions[] = $transaction;
			}

			return $transactions;
		}

		private function extractTransactions2015($page) {
			$transactions = array();

			// Now get the transactions.
			$items = $page->find('table#statement-table tbody tr.rt-row');
			foreach ($items as $row) {
				echo 'Got Item', "\n";
				$columns = pq($row, $page)->find('td');

				// Pull out the data
				$transaction['date'] = $this->cleanElement($columns->eq(0));
				$desc = $columns->eq(1);
				$transaction['description'] = $this->cleanElement($columns->eq(1));
				$transaction['typecode'] = $this->cleanElement($columns->eq(2));
				$transaction['type'] = $this->getType($transaction['typecode']);

				$transaction['in'] = $this->parseBalance($this->cleanElement($columns->eq(3)));
				$transaction['out'] = $this->parseBalance($this->cleanElement($columns->eq(4)));
				$transaction['balance'] = $this->parseBalance($this->cleanElement($columns->eq(5)));
				$transaction = $this->cleanTransaction($transaction);

				$transactions[] = $transaction;
			}

			return $transactions;
		}

		private function getType($typecode) {
			$typecodes['BGC'] = 'Bank Giro Credit';
			$typecodes['BNS'] = 'Bonus';
			$typecodes['BP '] = 'Bill Payment';
			$typecodes['CHG'] = 'Charge';
			$typecodes['CHQ'] = 'Cheque';
			$typecodes['COM'] = 'Commission';
			$typecodes['COR'] = 'Correction';
			$typecodes['CPT'] = 'Cashpoint';
			$typecodes['CSH'] = 'Cash';
			$typecodes['CSQ'] = 'Cash/Cheque';
			$typecodes['DD'] = 'Direct Debit';
			$typecodes['DEB'] = 'Debit Card';
			$typecodes['DEP'] = 'Deposit';
			$typecodes['EFT'] = 'EFTPOS (electronic funds transfer at point of sale)';
			$typecodes['EUR'] = 'Euro Cheque';
			$typecodes['FE'] = 'Foreign Exchange';
			$typecodes['FEE'] = 'Fixed Service Charge';
			$typecodes['FPC'] = 'Faster Payment charge';
			$typecodes['FPI'] = 'Faster Payments Incoming';
			$typecodes['FPO'] = 'Faster Payments Outgoing';
			$typecodes['IB'] = 'Internet Banking';
			$typecodes['INT'] = 'Interest';
			$typecodes['MPI'] = 'Mobile Payment incoming';
			$typecodes['MPO'] = 'Mobile Payment outgoing';
			$typecodes['MTG'] = 'Mortgage';
			$typecodes['NS'] = 'National Savings Dividend';
			$typecodes['NSC'] = 'National Savings Certificates';
			$typecodes['OTH'] = 'Other';
			$typecodes['PAY'] = 'Payment';
			$typecodes['PSB'] = 'Premium Savings Bonds';
			$typecodes['PSV'] = 'Paysave';
			$typecodes['SAL'] = 'Salary';
			$typecodes['SPB'] = 'Cashpoint';
			$typecodes['SO'] = 'Standing Order';
			$typecodes['STK'] = 'Stocks/Shares';
			$typecodes['TD'] = 'Dep Term Dec';
			$typecodes['TDG'] = 'Term Deposit Gross Interest';
			$typecodes['TDI'] = 'Dep Term Inc';
			$typecodes['TDN'] = 'Term Deposit Net Interest';
			$typecodes['TFR'] = 'Transfer';
			$typecodes['UT'] = 'Unit Trust';
			$typecodes['SUR'] = 'Excess Reject';

			return isset($typecodes[$typecode]) ? $typecodes[$typecode] : $typecode;
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
		 * @param $historicalLimit (Default: 0) How far back in time to go.
		 */
		public function updateTransactions($account, $historical = false, $historicalVerbose = true, $historicalLimit = 0) {
			$account->clearTransactions();
			$accountKey = preg_replace('#[^0-9]#', '', $account->getSortCode().$account->getAccountNumber());
			$page = $this->getPage('https://secure.halifax-online.co.uk' . $this->accountLinks[$accountKey]);
			if (!$this->isLoggedIn($page)) { return false; }
			$is2015 = $this->is2015style($page);
			$page = $this->getDocument($page);

			if ($is2015) {
				$transactions = $this->extractTransactions2015($page);
			} else {
				$available = strip_tags($this->cleanElement($page->find('span.manageMyAccountsFaShowMeAnchor')->parent()));
				if (preg_match('@Money[\s]available:[^0-9]*([0-9]+.[0-9]+)@', $available, $matches)) {
					$account->setAvailable($matches[1]);
				}
				$transactions = $this->extractTransactions($page);
			}

			// Now try the historical ones.
			if ($historical && !$is2015) {
				// Keep going until we can't go any more.
				while (true) {
					if ($is2015) {
						$page = $this->getPage('https://secure.halifax-online.co.uk/personal/link/lp_statement_ajax?viewstatement=previous');
					} else {
						$page = $this->browser->submitFormById('pnlgrpStatement:conS1:frmVSPUpper', array('pnlgrpStatement:conS1:frmVSPUpper:btnViewPreviousStatement' => 'null'));
					}
					$page = $this->getDocument($page);

					if ($is2015) {
						$olderTransactions = $this->extractTransactions2015($page);
					} else {
						$olderTransactions = $this->extractTransactions($page);
					}
					if (count($olderTransactions) == 0) { break; }
					$transactions = array_merge($transactions, $olderTransactions);
					if ($olderTransactions[count($olderTransactions) - 1]['date'] <= $historicalLimit) { break; }
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

			// Ignore transactions on the last date, as there may be more that we don't see
			if (count($transactions) > 0) {
				$skipDate = $transactions[0]['date'];
				foreach ($transactions as $transaction) {
					// Skip the last day, cos we can't be sure we have all the
					// transactions for it.
					if ($transaction['date'] == $skipDate) { continue; }

					if ($lastDate == $transaction['date']) {
						$dayCount++;
						$transaction['date'] += $dayCount;
					} else {
						$lastDate = $transaction['date'];
						$dayCount = 0;
					}
					$tr = new Transaction($this->__toString(), $account->getAccountKey(), $transaction['date'], $transaction['type'], $transaction['typecode'], $transaction['description'], $transaction['amount'], $transaction['balance']);
					// var_dump($tr);
					// var_dump($tr->getHash());
					$account->addTransaction($tr);
				}
			}
		}
	}
?>
