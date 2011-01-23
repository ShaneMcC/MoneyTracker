<?php
	require_once(dirname(__FILE__) . '/../Bank.php');
	require_once(dirname(__FILE__) . '/../Account.php');
	require_once(dirname(__FILE__) . '/../Transaction.php');

	require_once(dirname(__FILE__) . '/../3rdparty/simpletest/browser.php');
	require_once(dirname(__FILE__) . '/../3rdparty/phpquery/phpQuery/phpQuery.php');

	/**
	 * Code to scrape HSBC to get Account and Transaction objects.
	 */
	class HSBC extends Bank {
		private $account = '';
		private $secret = '';
		private $dob = '';
		private $browser = null;

		private $accounts = null;

		/**
		 * Create a HSBC.
		 *
		 * @param $account Account number (IB...)
		 * @param $dob Date of bith (DDMMYY)
		 * @param $secret 6-10 digit secret code
		 */
		public function __construct($account, $dob, $secret) {
			$this->account = $account;
			$this->dob = $dob;
			$this->secret = $secret;
		}

		private function getCode($first, $second, $third) {
			$pass = str_split($this->secret);

			$words = array('FIRST' => 0,
			               'SECOND' => 1,
			               'THIRD' => 2,
			               'FOURTH' => 3,
			               'FIFTH' => 4,
			               'SIXTH' => 5,
			               'SEVENTH' => 6,
			               'EIGHTH' => 7,
			               'NINTH' => 8,
			               'TENTH' => 9,
			               'NEXT TO LAST' => count($pass) - 2,
			               'LAST' => count($pass) - 1,
			);

			return $pass[$words[$first]] . $pass[$words[$second]] . $pass[$words[$third]];
		}

		/**
		 * Force a fresh login.
		 *
		 * @return true if login was successful, else false.
		 */
		public function login() {
			// Initial page, to get cookies
			$this->browser = new SimpleBrowser();
			$this->browser->setParser(new SimplePHPPageBuilder());

			$this->browser->get('https://www.hsbc.co.uk/');

			// Move to login page
			$foo = $this->browser->submitFormById('loginpersonalform');

			// Fill out the form and submit it.
			$this->browser->setFieldById('intbankingID', $this->account);
			$page = $this->browser->submitFormById('IBloginForm');

			// Now fill out the next page form.
			// This needs some regexing to get what we want, firstly the requested
			// bits of the code
			if (preg_match_all('@<span class="hsbcTextHighlight">&#160;<strong>([^<]+)</strong></span>&nbsp;@', $page, $matches)) {
				// Get the code and the dob
				$code = $this->getCode(trim($matches[1][0]), trim($matches[1][1]), trim($matches[1][2]));
				$dob = $this->dob;

				// Set the fields
				$this->browser->setFieldByName('memorableAnswer', $dob);
				$this->browser->setFieldByName('password', $code);

				$page = $this->browser->clickSubmit('Continue');

				// And now, "click to continue" (why ?!)
				$page = $this->browser->get('https://www.hsbc.co.uk/1/2/personal/internet-banking?BlitzToken=blitz');

				// And done.
				return (strpos($page, 'You are logged on') !== FALSE);
			}
			return false;
		}

		/**
		 * Get the requested page, logging in if needed.
		 *
		 * @param $url URL of page to get.
		 * @param $justGet (Default: false) Just get the page, don't try to auth.
		 */
		private function getPage($url, $justGet = false) {
			if ($this->browser == null) {
				if ($justGet) {
					$this->browser = new SimpleBrowser();
					$this->browser->setParser(new SimplePHPPageBuilder());
				} else {
					$this->login();
				}
			}

			$page = $this->browser->get($url);
			if (!$justGet && (strpos($page, 'You are logged on') === FALSE)) {
				$this->login();
				$page = $this->browser->get($url);
			}
			return $page;
		}

		/**
		 * Get a nice tidied and phpQueryed version of a html page.
		 *
		 * @param $html HTML to parse
		 * @return PHPQuery document from the tidied html.
		 */
		private function getDocument($html) {
			$config = array('indent' => TRUE,
			                'wrap' => 0,
			                'output-xhtml' => true,
			                'clean' => true,
			                'numeric-entities' => true,
			                'char-encoding' => 'ascii',
			                'input-encoding' => 'ascii',
			                );
			$tidy = tidy_parse_string($html, $config);
			$tidy->cleanRepair();
			$html = $tidy->value;
			return phpQuery::newDocument($html);
		}

		/**
		 * Clean up an element.
		 *
		 * @return a clean element as a string.
		 */
		private function cleanElement($element) {
			// Fail.
			return trim(preg_replace('#[^\s\w\d-._/\\\'*()<>{}\[\]]#i', '', $element->html()));
		}

		/**
		 * Take a Balance as exported by HSBC, and return it as a standard balance.
		 *
		 * @param $balance Balance input (eg: " £ 1.00 D ")
		 * @return Correct balance (eg: "-1.00")
		 */
		private function parseBalance($balance) {
			// Check for negative
			$prefix = '';
			if (strpos($balance, 'D') !== false) { $prefix = '-' . $prefix; }
			$balance = explode(' ', $balance);

			return trim($prefix . $balance[0]);
		}

		/**
		 * Get the sub-accounts of this login.
		 * This will return cached account objects.
		 *
		 * @param $useCached (Default: true) Return cached values if possib;e?
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
				$account->setType($type);
				$account->setOwner($owner);
				$account->setSortCode($number[0]);
				$account->setAccountNumber($number[1]);
				$account->setBalance($balance);

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
			$transaction['description'] = preg_replace('#[\n\s]+#ims', ' ', $transaction['description']);
			$transaction['description'] = html_entity_decode($transaction['description']);
			$transaction['description'] = preg_replace('#//[\s]+$#', '', $transaction['description']);
			$transaction['description'] = trim($transaction['description']);

			// Rather than separate in/out, lets just have a +/- amount
			if (!empty($transaction['out'])) {
				$transaction['amount'] = 0 - $transaction['out'];
			} else if (!empty($transaction['in'])) {
				$transaction['amount'] = $transaction['in'];
			}

			// Correct the balance.
			$transaction['balance'] = preg_replace('#[^0-9.]#', '', $transaction['balance']);
			if ($transaction['balance_type'] == 'D') {
				$transaction['balance'] = 0 - $transaction['balance'];
			}

			$transaction['type'] = preg_replace('#[^A-Z0-9]#', '', $transaction['type']);

			// Unset any unneeded values
			unset($transaction['out']);
			unset($transaction['in']);
			unset($transaction['balance_type']);

			return $transaction;
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
				$columns = pq($row)->find('td');

				// Pull out the data
				$transaction['date'] = $this->cleanElement($columns->eq(0));
				$transaction['type'] = $this->cleanElement($columns->eq(1));
				$transaction['description'] = $this->cleanElement($columns->eq(2));
				$transaction['out'] = $this->cleanElement($columns->eq(3));
				$transaction['in'] = $this->cleanElement($columns->eq(4));
				$transaction['balance'] = $this->cleanElement($columns->eq(5));
				$transaction['balance_type'] = $this->cleanElement($columns->eq(6));

				// Sanitise the above.
				$transaction = $this->cleanTransaction($transaction);

				$account->addTransaction(new Transaction($transaction['date'], $transaction['type'], $transaction['description'], $transaction['amount'], $transaction['balance']));
			}

			// Now try the historical ones.
			if ($historical) {
				// Get the first page of the list of historical statements.
				$page = $this->getPage('https://www.hsbc.co.uk/1/2/personal/internet-banking/previous-statements');
				$page = $this->getDocument($page);
				$nextLink = '';

				// Keep going until we can't go any more.
				while (true) {
					// Get all the links on this page.
					$links = $page->find('table.hsbcRowSeparator tbody a');

					// Abort once we run out of links.
					if (count($links) == 0) { break; }

					// Open each statement.
					foreach ($links as $link) {
						$title = $link->getAttribute('title');

						$url = 'https://www.hsbc.co.uk'.$link->getAttribute('href');
						// Bloody HTML Tidy...
						$url = str_replace('&amp;', '&', $url);
						$rpage = $this->getPage($url);

						$rpage = $this->getDocument($rpage);

						preg_match('#^([0-9]+ [a-zA-Z]+ ([0-9]+)) statement$#', $title, $matches);
						$year = $matches[2];
						$date = $matches[1];

						echo 'Statement: ', $title, "\n";

						// Now get the transactions.
						$items = $rpage->find('table tbody tr');
						$transactions = array();
						foreach ($items as $row) {
							echo 'Got Item.', "\n";
							$columns = pq($row)->find('td');

							// Pull out the data
							$transaction = array();
							$transaction['date'] = $this->cleanElement($columns->eq(0)->find('p'));
							$transaction['type'] = $this->cleanElement($columns->eq(1)->find('p'));
							$transaction['description'] = $columns->eq(2)->find('p');
							$transaction['out'] = $this->cleanElement($columns->eq(3)->find('p'));
							$transaction['in'] = $this->cleanElement($columns->eq(4)->find('p'));
							$transaction['balance'] = $this->cleanElement($columns->eq(5)->find('p'));
							$transaction['balance_type'] = $this->cleanElement($columns->eq(6)->find('p'));

							if (preg_match('#<strong>Balance (carried|brought) forward</strong>#', $transaction['description'])) {
								continue;
							}

							// Does the description have a URL?
							$urls = pq($transaction['description'])->find('a');
							if (count($urls) > 0) {
								// Open the URL to get the full description if desired
								if ($historicalVerbose) {
									$dlink = $urls->eq(0);
									// HTML Tidy...
									$url = 'https://www.hsbc.co.uk'.$dlink->attr('href');
									$url = str_replace('&amp;', '&', $url);
									$dpage = $this->getPage($url);
									$dpage = $this->getDocument($dpage);
									$transaction['description'] = $dpage->find('table tbody tr')->eq(5)->find('td')->eq(1)->find('p');
								} else {
									// Otherwise, the first line will do.
									$transaction['description'] = $urls->eq(0);
								}
							}

							$transaction['description'] = $this->cleanElement($transaction['description']);

							// Sanitise the above.
							$transaction = $this->cleanTransaction($transaction, $year, $date);

							if ($transaction['type'] != '') {
								$account->addTransaction(new Transaction($transaction['date'], $transaction['type'], $transaction['description'], $transaction['amount'], $transaction['balance']));
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

					if ($nextLink != '') {
						$page = $this->getPage($nextLink);
						$page = $this->getDocument($page);
					}
				}
			}
		}
	}
?>
