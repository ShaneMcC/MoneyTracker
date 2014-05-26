<?php
	require_once(dirname(__FILE__) . '/../Bank.php');
	require_once(dirname(__FILE__) . '/../Account.php');
	require_once(dirname(__FILE__) . '/../Transaction.php');

	require_once(dirname(__FILE__) . '/../3rdparty/simpletest/browser.php');
	require_once(dirname(__FILE__) . '/../3rdparty/phpquery/phpQuery/phpQuery.php');

	/**
	 * Code to pretend to be the HSBC Mobile app, this will allow us to scrape HSBC
	 * for recent transactions without needing the securekey.
	 */
	class HSBCMobile extends WebBank {
		private $account = '';
		private $password = '';
		private $memorableinfo = '';

		private $accounts = null;
		private $accountLinks = array();

		/**
		 * Create a HSBCMobile.
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
			return 'HSBCMobile/' . $this->account;
		}

		private function hsbcPost($url, $data, $trylogin = true) {
			if ($this->browser == null) { $this->newBrowser(false); }

			$data['locale'] = 'en';
			$data['devtype'] = 'M';
			$data['platform'] = 'A';
			$data['ver'] = '1.1';
			$data['json'] = '';

			$page = $this->browser->post($url, $data);
			$decoded = @json_decode($page);

			if ($trylogin && ($decoded === null || $decoded->header->statusCode != '0000')) {
				$this->login();

				$page = $this->browser->post($url, $data);
				$decoded = @json_decode($page);
			}

			return $decoded;
		}

		/**
		 * Force a fresh login.
		 *
		 * @return true if login was successful, else false.
		 */
		public function login($fresh = false) {
			$this->newBrowser(false);
			$page = $this->browser->get('https://www.hsbc.co.uk/1/2/mobile-1-5/pre-logon');

			$url = 'https://www.hsbc.co.uk/1/2/?idv_cmd=idv.Authentication&locale=en&devtype=M&platform=A&initialAccess=true&nextPage=ukpib.mobile.1.5.cam30.response&userid=' . $this->account . '&cookieuserid=false&CSA_DynamicBrandKey=MOBILE15&ver=11&json=&__locale=en&LANGTAG=en&COUNTRYTAG=US&platform=A&devtype=M';
			$page = $this->browser->get($url);
			$decoded = @json_decode($page);

			$wanted = explode(',', $decoded->body->rccDigits);
			$digits = array();
			foreach ($wanted as $d) {
				if ($d == '8') { $d = strlen($this->memorableinfo) - 1; }
				if ($d == '7') { $d = strlen($this->memorableinfo) - 2; }

				$digits[] = $this->memorableinfo[$d];
			}


			$data = array('CSA_DynamicBrandKey' => 'MOBILE15',
			              'memorableAnswer' => $this->password,
			              'password' => implode('', $digits),
			              '__locale' => 'en',
			              );


			$decoded = $this->hsbcPost('https://www.hsbc.co.uk/1/2/?idv_cmd=idv.Authentication', $data, false);

			return $decoded !== NULL;
		}

		public function isLoggedIn($page) {
			// Always return true as there is no GET pages to test against.
			return true;
		}

		private function getType($typecode) {
			$typecodes[')))'] = 'Contactless debit card payment';
			$typecodes['ATM'] = 'Cash machine';
			$typecodes['BP'] = 'Bill payment';
			$typecodes['CHQ'] = 'Cheque';
			$typecodes['CR'] = 'Credit';
			$typecodes['DD'] = 'Direct Debit or other BACS debit';
			$typecodes['DIV'] = 'Dividend';
			$typecodes['DR'] = 'Debit';
			$typecodes['SO'] = 'Standing order';
			$typecodes['TFR'] = 'Internal Transfer';
			$typecodes['VIS'] = 'Visa Card Payment';
			$typecodes['SOL'] = 'Solo Card Payment';
			$typecodes['MAE'] = 'Maestro Card Payment';

			return isset($typecodes[$typecode]) ? $typecodes[$typecode] : $typecode;
		}

		/**
		 * Take transaction data, and clean it up a bit.
		 *
		 * @param $transaction Input transaction
		 * @return cleaned up transaction
		 */
		private function cleanTransaction($transaction) {
			// Get a better date
			$bits = explode('/', $transaction['date']);
			$transaction['date'] = $bits[1].'/'.$bits[0].'/'.$bits[2];
			$transaction['date'] = strtotime($transaction['date'] . ' Europe/London');

			// Rather than separate in/out, lets just have a +/- amount
			if (!empty($transaction['out'])) {
				$transaction['amount'] = $transaction['out'];
			} else if (!empty($transaction['in'])) {
				$transaction['amount'] = $transaction['in'];
			}

			// Unset any unneeded values
			unset($transaction['out']);
			unset($transaction['in']);

			return $transaction;
		}

		/**
		 * Take a Balance as exported by HSBCMobile, and return it as a standard balance.
		 *
		 * @param $balance Balance input (eg: "£1.00" or "-£1.00")
		 * @return Correct balance (eg: "1.00" or "-1.00")
		 */
		private function parseBalance($balance) {
			if (empty($balance)) { return ''; }
			$negative = strpos($balance, '-') !== FALSE;
			$balance = str_replace(',', '', $balance);
			preg_match('@([0-9]+.[0-9]+)$@', $balance, $matches);
			return $negative ? 0 - $matches[1] : $matches[1];
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
			$data = array('requestName' => 'ac_summary',
			              '__cmd-All_MenuRefresh' => '__cmd-All_MenuRefresh',
			              );
			$decoded = $this->hsbcPost('https://www.hsbc.co.uk/1/3/mobile-1-5/accounts?CSA_DynamicBrandKey=MOBILE15', $data);
			if ($decoded == null) { return false; }

			$accounts = array();
			foreach ($decoded->body->entities[0]->accountGroups[0]->accounts as $acc) {
				$account = new Account();
				$account->setSource($this->__toString());
				$account->setType($acc->desc);
				$account->setOwner($this->account);

				$number = explode(' ', $acc->accountNum);

				if (!isset($number[1]) || isset($number[2])) {
					$account->setSortCode('00-00-02');
					$account->setAccountNumber($acc->accountNum);
				} else {
					$account->setSortCode($number[0]);
					$account->setAccountNumber($number[1]);
				}

				$account->setBalance($this->parseBalance($acc->balance));

				$accountKey = preg_replace('#[^0-9]#', '', $account->getSortCode().$account->getAccountNumber());
				$this->accountLinks[$accountKey] = array('id' => $acc->id, 'type' => $acc->type);

				if ($transactions) {
					$this->updateTransactions($account, $historical, $historicalVerbose);
				}

				$accounts[] = $account;
			}

			return $accounts;
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

			$accData = $this->accountLinks[$accountKey];
			$isCC = ($accData['type'] == 'PCC');

			$data = array('requestName' => $isCC ? 'ac_cc_history' : 'ac_history',
			              'account_index' => $accData['id'],
			              'account_type' => $accData['type'],
			              'cmd-All_in' => 'cmd-All_in',
			              'statement' => '0',
			              );

			$decoded = $this->hsbcPost('https://www.hsbc.co.uk/1/3/mobile-1-5/accounts?CSA_DynamicBrandKey=MOBILE15', $data);
			if ($decoded == null) { return false; }

			$transactions = array();
			$items = $isCC ? $decoded->body->histories[0]->ccTxns : $decoded->body->histories;

			$lastBalance = $isCC ? $this->parseBalance($decoded->body->balance) : 0;

			foreach ($items as $trans) {
				echo 'Got Item', "\n";

				// Pull out the data
				$transaction = array();
				$transaction['date'] = ($isCC) ? $trans->txnDate : $trans->date;
				$transaction['typecode'] = ($isCC) ? ($trans->ccTxnAmtDrCr ? 'DR' : 'CR') : $trans->type;
				$transaction['type'] = $this->getType($transaction['typecode']);
				$transaction['description'] = preg_replace('#\s+#', ' ', ($isCC) ? $trans->ccTxnMerchant : implode(' // ', $trans->details));

				// Fix some known brokenness...
				if ($transaction['description'] == 'ADDED NET INT') { $transaction['description'] = 'ADDED NET INTEREST'; }

				if ($isCC) {
					$bal = $this->parseBalance($trans->ccTxnAmt);
					$transaction['out'] = $trans->ccTxnAmtDrCr ? $bal : '';
					$transaction['in'] = $trans->ccTxnAmtDrCr ? '' : $bal;
					$transaction['balance'] = $lastBalance;
					$lastBalance -= $bal;

					// Hack, the above doesn't really handle being "0" very well...
					if ($lastBalance < 0.01 && $lastBalance > -0.01) { $lastBalance = 0; }
				} else {
					$transaction['out'] = $this->parseBalance($trans->debitAmt);
					$transaction['in'] = $this->parseBalance($trans->creditAmt);
					$transaction['balance'] = $this->parseBalance($trans->balance);
				}
				$transaction = $this->cleanTransaction($transaction);

				$transactions[] = $transaction;
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
					// if ($transaction['date'] == $firstDate) { continue; }

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
