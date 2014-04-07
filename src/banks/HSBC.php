<?php
	require_once(dirname(__FILE__) . '/../Bank.php');
	require_once(dirname(__FILE__) . '/../Account.php');
	require_once(dirname(__FILE__) . '/../Transaction.php');

	require_once(dirname(__FILE__) . '/../3rdparty/simpletest/browser.php');
	require_once(dirname(__FILE__) . '/../3rdparty/phpquery/phpQuery/phpQuery.php');

	/**
	 * Code to scrape HSBC to get Account and Transaction objects.
	 */
	class HSBC extends WebBank {
		private $account = '';
		private $securekey = '';
		private $secretword = '';

		private $accounts = null;

		/**
		 * Create a HSBC.
		 *
		 * @param $account Account number (IB...)
		 * @param $secretword Secret Word
		 * @param $securekey Secure Key Code
		 */
		public function __construct($account, $secretword, $securekey) {
			$this->account = $account;
			$this->secretword = $secretword;
			$this->securekey = $securekey;
		}

		/**
		 * String representation of this bank.
		 */
		public function __toString() {
			return 'HSBC/' . $this->account;
		}

		/**
		 * Force a fresh login.
		 *
		 * @return true if login was successful, else false.
		 */
		public function login($fresh = false) {
			if ($fresh) {
				$this->newBrowser(false);
				$page = $this->browser->get('https://www.hsbc.co.uk/');
				$page = $this->getDocument($page);

				// Move to login page
				$element = $page->find('a[title="Log on to Personal Internet Banking"');
				$loginurl = $element->eq(0)->attr('href');
				$page = $this->browser->get('https://www.hsbc.co.uk/' . $loginurl);
			} else {
				$this->newBrowser(true);
				$page = $this->browser->get('https://www.hsbc.co.uk/1/2/personal/internet-banking?BlitzToken=blitz');
				if ($this->isLoggedIn($page)) {
					return true;
				}
			}

			// Fill out the form and submit it.
			$this->browser->setFieldById('intbankingID', $this->account);
			// $this->browser->setMaximumRedirects(1);
			$page = $this->browser->submitFormById('logonForm');

			if ($this->securekey == '##') {
				$this->securekey = getUserInput('Please enter the securekey code for '.$this->account.': ');
				if ($this->securekey === false) {
					return false;
				}
			}

			// Set the fields
			$this->browser->setFieldById('passwd', $this->secretword);
			$this->browser->setFieldById('secNumberInput', $this->securekey);

			$page = $this->browser->clickSubmit('Continue');

			// And now, "click to continue" (why ?!)
			$page = $this->browser->get('https://www.hsbc.co.uk/1/2/personal/internet-banking?BlitzToken=blitz');

			// And done.
			$this->saveCookies();
			return $this->isLoggedIn($page);
		}

		public function isLoggedIn($page) {
			return (strpos($page, 'View My accounts') !== FALSE);
		}

		/**
		 * Take a Balance as exported by HSBC, and return it as a standard balance.
		 *
		 * @param $balance Balance input (eg: " £ 1.00 D " or " &163; 1.00 D ")
		 * @return Correct balance (eg: "-1.00")
		 */
		private function parseBalance($balance) {
			// Check for negative
			$prefix = '';
			if (strpos($balance, 'D') !== false) { $prefix = '-' . $prefix; }
			$balance = explode(' ', trim($balance));

			return trim($prefix . $balance[1]);
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

			$page = $this->getPage('https://www.hsbc.co.uk/1/2/personal/internet-banking?BlitzToken=blitz');

			if (!$this->isLoggedIn($page)) { return $this->accounts; }

			$page = $this->getDocument($page);

			$accounts = array();

			$accountdetails = $page->find('#jsAccountDetails');
			$items = $page->find('span.hsbcDivletBoxRowText', $accountdetails);
			for ($i = 0; $i < (count($items) / 4); $i++) {
				$pos = ($i * 4);

				// Get the values
				$type = $this->cleanElement($items->eq($pos + 0)->find('strong'));
				$owner = $this->cleanElement($items->eq($pos + 1));
				$number = $this->cleanElement($items->eq($pos + 2));
				$balance = $this->cleanElement($items->eq($pos + 3)->find('strong'));

				// Sanitise Values
				$number = explode(' ', $number);
				$balance = $this->parseBalance($balance);

				// Finally, create an account object.
				$account = new Account();
				$account->setSource($this->__toString());
				$account->setType($type);
				$account->setOwner($owner);
				if (!isset($number[1])) {
					$account->setSortCode('');
					$account->setAccountNumber($number[0]);
				} else {
					$account->setSortCode($number[0]);
					$account->setAccountNumber($number[1]);
				}
				$account->setBalance($balance);

				if ($transactions) {
					$this->updateTransactions($account, $historical, $historicalVerbose);
				}

				$this->accounts[] = $account;
			}

			return $this->accounts;
		}

		/**
		 * Convert a pair of balance information into a float.
		 *
		 * @param $balance Balance as given.
		 * @param $balanceType Balance Type as given.
		 * @return float of balance.
		 */
		private function cleanBalance($balance, $balanceType) {
			// Correct the balance.
			$result = preg_replace('#[^0-9.]#', '', $balance);
			if ($balanceType == 'D') {
				$result = 0 - $result;
			}
			return $result;
		}

		/**
		 * Take transaction data, and clean it up a bit.
		 *
		 * @param $transaction Input transaction
		 * @param $baseYear (Default: this year) Base Year for calculating date.
		 * @param $maxDate (Default: today) Maximum a date can be before it should
		 *                 be in the past.
		 * @return cleaned up transaction
		 */
		private function cleanTransaction($transaction, $baseYear = '', $maxDate = '') {
			// Get a better date
			if ($baseYear == '') {
				// If we have not been given a year, work it out based on the now.
				$current = strtotime($transaction['date']);
				$last = strtotime('-1 year', $current);
				$transaction['date'] = ($current > time()) ? $last : $current;
			} else {
				// If we have been given a year, then use it.
				$current = strtotime($transaction['date']. ' ' . $baseYear);
				$last = strtotime('-1 year', $current);
				$transaction['date'] = ($current > strtotime($maxDate)) ? $last : $current;
			}

			// Descriptions can be multiline, put them on one nicely.
			$transaction['description'] = str_replace('<br />', ' // ', $transaction['description']);
			$transaction['description'] = str_replace('<br>', ' // ', $transaction['description']);
			$transaction['description'] = preg_replace('#[\n\s]+#ims', ' ', $transaction['description']);
			$transaction['description'] = html_entity_decode($transaction['description']);
			$transaction['description'] = preg_replace('#//[\s]+$#', '', $transaction['description']);
			$transaction['description'] = trim($transaction['description']);

			// Some transactions are bold.
			$transaction['out'] = trim(str_replace('<b>', '', str_replace('</b>', '', $transaction['out'])));
			$transaction['in'] = trim(str_replace('<b>', '', str_replace('</b>', '', $transaction['in'])));

			// Rather than separate in/out, lets just have a +/- amount
			if (!empty($transaction['out'])) {
				$transaction['amount'] = 0 - $transaction['out'];
			} else if (!empty($transaction['in'])) {
				$transaction['amount'] = $transaction['in'];
			}

			// Correct the balance.
			$transaction['balance'] = $this->cleanBalance($transaction['balance'], $transaction['balance_type']);

			$transaction['type'] = preg_replace('#[^A-Z0-9)]#', '', $transaction['type']);

			// Unset any unneeded values
			unset($transaction['out']);
			unset($transaction['in']);
			unset($transaction['balance_type']);

			return $transaction;
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
			$page = $this->getPage('https://www.hsbc.co.uk/1/2/personal/internet-banking/recent-transaction?ActiveAccountKey=' . $accountKey . '&BlitzToken=blitz');
			if (!$this->isLoggedIn($page)) { return false; }
			$page = $this->getDocument($page);

			// Get the first bit of useful data.
			$items = $page->find('table.extPibTable')->eq(0)->find('td');
			$details = array();
			for ($i = 0; $i < (count($items) / 2); $i++) {
				$pos = ($i * 2);

				$key = $this->cleanElement($items->eq($pos + 0)->find('strong'));
				$value = $this->cleanElement($items->eq($pos + 1));

				$details[strtolower($key)] = $value;
			}

			if (isset($details['balance'])) {
				$account->setBalance($this->parseBalance($details['balance']));
			}
			if (isset($details['overdraft limit'])) {
				$account->setLimits('Overdraft: '.$this->parseBalance($details['overdraft limit']));
			}
			if (isset($details['available balance'])) {
				$account->setAvailable($this->parseBalance($details['available balance']));
			}

			// Now get the transactions.
			$items = $page->find('table.extPibTable tbody tr');
			$transactions = array();
			foreach ($items as $row) {
				$columns = pq($row, $page)->find('td');

				// Pull out the data
				$transaction['date'] = $this->cleanElement($columns->eq(0));
				$transaction['typecode'] = $this->cleanElement($columns->eq(1));
				$transaction['type'] = $this->getType($transaction['typecode']);

				$transaction['description'] = $this->cleanElement($columns->eq(2));
				$transaction['out'] = $this->cleanElement($columns->eq(3));
				$transaction['in'] = $this->cleanElement($columns->eq(4));

				$bal = explode(' ', $this->cleanElement($columns->eq(5)));
				$transaction['balance'] = $bal[0];
				$transaction['balance_type'] = (isset($bal[1]) ? $bal[1] : '');

				// Sanitise the above.
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

			// Now try the historical ones.
			if ($historical) {
				// Get the first page of the list of historical statements.
				$page = $this->getPage('https://www.hsbc.co.uk/1/2/personal/internet-banking/previous-statements');
				$page = $this->getDocument($page);
				$nextLink = '';
				$prevLink = '';

				$pastStatements = array();

				// How much balance was brought forward in the last statement
				$lastForward = 0.00;
				// How much balance was brought forward in this statement
				$thisForward = 0.00;
				// $cancontinue = false;
				// Keep going until we can't go any more.
				while (true) {
					// Get all the links on this page.
					$links = $page->find('table.hsbcRowSeparator tbody a');

					// We have reached the end, now go backwards and catch any stragglers.
					if (count($links) == 0) {
						if ($prevLink != '') {
							$nextLink = $prevLink;
							$page = $this->getPage($nextLink);
							$page = $this->getDocument($page);
							$prevLink = '';
							continue;
						} else {
							// If the prevlink has served it's purpose, and we have no more
							// links, then abort.
							break;
						}
					}

					// Open each statement.
					foreach ($links as $link) {
						$title = $link->getAttribute('title');
						echo 'Statement: ', $title, "\n";
						if (in_array($title, $pastStatements)) {
							echo "\t", 'Duplicate, ignored.', "\n";
							continue;
						}
						$pastStatements[] = $title;
						// if ($cancontinue == false) { continue; }
						$url = 'https://www.hsbc.co.uk'.$link->getAttribute('href');
						// Bloody HTML Tidy...
						$url = str_replace('&amp;', '&', $url);
						$rpage = $this->getPage($url);
						$rpage = $this->getDocument($rpage);

						preg_match('#^([0-9]+ [a-zA-Z]+ ([0-9]+)) statement$#', $title, $matches);
						$year = $matches[2];
						$date = $matches[1];

						// Now get the transactions.
						$items = $rpage->find('table tbody tr');
						// The last balance we calculated
						$lastBalance = 0.00;
						// The last balance we were given
						$lastGivenBalance = 0.00;
						// How much money have we calculated between the last balance and now?
						$thisDay = 0.00;
						// is this the first given balance of the statement?
						$firstBalance = true;
						// To make ordering the transactions easier, rather than having
						// all the days transactions having the same time, we add a second
						// each time. (so the first transaction of the day happened at
						// 00:00:00 the second at 00:00:01 and so on.
						$dayCount = 0;
						$lastDate = 0;
						foreach ($items as $row) {
							echo 'Got Item', "\n";
							$columns = pq($row, $items)->find('td');

							// Pull out the data
							$transaction = array();
							$transaction['date'] = $this->cleanElement($columns->eq(0)->find('p'));
							$transaction['type'] = $this->cleanElement($columns->eq(1)->find('p'));
							$fullDesc = $columns->eq(2)->find('p');
							$transaction['description'] = $this->cleanElement($fullDesc);
							$transaction['out'] = $this->cleanElement($columns->eq(3)->find('p'));
							$transaction['in'] = $this->cleanElement($columns->eq(4)->find('p'));
							$transaction['balance'] = $this->cleanElement($columns->eq(5)->find('p'));
							$transaction['balance_type'] = $this->cleanElement($columns->eq(6)->find('p'));

							if (preg_match('#<strong>(Balance (carried|brought) forward)</strong>#', $transaction['description'], $m)) {
								$lastBalance = $lastGivenBalance = $this->cleanBalance($transaction['balance'], $transaction['balance_type']);
								if ($m[1] == 'Balance brought forward') {
									$lastForward = $thisForward;
									$thisForward = $lastGivenBalance;
								}
								continue;
							}

							// Does the description have a URL?
							$urls = pq($fullDesc, $columns)->find('a');
							if (count($urls) > 0) {
								// Open the URL to get the full description if desired
								if ($historicalVerbose) {
									$dlink = $urls->eq(0);
									// HTML Tidy...
									$url = 'https://www.hsbc.co.uk'.$dlink->attr('href');
									$url = str_replace('&amp;', '&', $url);
									$dpage = $this->getPage($url);
									$dpage = $this->getDocument($dpage);
									$transaction['description'] = $this->cleanElement($dpage->find('table tbody tr')->eq(5)->find('td')->eq(1)->find('p'));
								} else {
									// Otherwise, the first line will do.
									$transaction['description'] = $this->cleanElement($urls->eq(0));
								}
							}

							// Sanitise the above.
							$transaction = $this->cleanTransaction($transaction, $year, $date);

							$thisDay += $transaction['amount'];

							// HSBC only gives a final balance for each day, not a separate
							// one for each transaction.
							// Lets fix that.
							if ($transaction['balance'] == '') {
								$lastBalance += $transaction['amount'];
								$transaction['balance'] = $lastBalance;
							} else {
								$given = $transaction['balance'];
								$calculated = sprintf('%0.2f', ($lastBalance + $transaction['amount']));

								if ($given != $calculated) {
									// HSBC has a horrible bug with the first ever statement.
									// - The starting balance will show the same as the previous
									//   statement starting balance.
									// - We can check if we are running into this bug by checking
									//   if the 2 starting balances match, and the given balance
									//   we are getting now is the same as the total for the day.
									//
									// So try to work around this bug first before aborting.
									if (!$firstBalance || $transaction['balance'] != $thisDay || $thisForward != $lastForward) {
										echo 'ERROR WITH CALCULATED BALANCES:', "\n";
										echo '    Expected: ', $transaction['balance'], "\n";
										echo '    Calculated: ', ($lastBalance + $transaction['amount']), "\n";
										echo '    lastBalance: ', $lastBalance, "\n";
										echo "\n";
										var_dump($transaction);
										echo "\n";
										die();
									}
								}
								$lastBalance = $lastGivenBalance = $given;
								$thisDay = 0.00;
								$firstBalance = false;
							}

							// Update the time of the transaction.
							if ($lastDate == $transaction['date']) {
								$dayCount++;
								$transaction['date'] += $dayCount;
							} else {
								$lastDate = $transaction['date'];
								$dayCount = 0;
							}

							if ($transaction['type'] != '') {
								$account->addTransaction(new Transaction($this->__toString(), $account->getAccountKey(), $transaction['date'], $transaction['type'], $transaction['description'], $transaction['amount'], $transaction['balance']));
							}
						}
					}

					// Look for a "next" link.
					// Only do this once, then keep using it, this will allow us to go
					// further back than HSBC would otherwise allow.
					if ($nextLink == '') {
						$links = $page->find('div.extButtons div.hsbcButtonCenter a');
						if (count($links) > 0) {
							foreach ($links as $link) {
								if ($link->getAttribute('title') == 'Next set of statements') {
									$nextLink = 'https://www.hsbc.co.uk'.$link->getAttribute('href');
								}
							}
						}
					}

					// Also look for a previous link.
					if ($prevLink == '') {
						$links = $page->find('div.extButtons div.hsbcButtonCenter a');
						if (count($links) > 0) {
							foreach ($links as $link) {
								if ($link->getAttribute('title') == 'Previous set of statements') {
									$prevLink = 'https://www.hsbc.co.uk'.$link->getAttribute('href');
								}
							}
						}
					}

					if ($nextLink != '') {
						$page = $this->getPage($nextLink);
						$page = $this->getDocument($page);
					}
				}
			}
		}
	}
?>
