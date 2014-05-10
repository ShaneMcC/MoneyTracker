<?php
	require_once(dirname(__FILE__) . '/../Bank.php');
	require_once(dirname(__FILE__) . '/../Account.php');
	require_once(dirname(__FILE__) . '/../Transaction.php');

	require_once(dirname(__FILE__) . '/../3rdparty/simpletest/browser.php');
	require_once(dirname(__FILE__) . '/../3rdparty/phpquery/phpQuery/phpQuery.php');

	/**
	 * Code to scrape Tesco Bank to get Account and Transaction objects.
	 */
	class TescoBank extends WebBank {
		private $account = '';
		private $password = '';
		private $securitynumber = '';

		private $accounts = null;
		private $accountLinks = array();

		/**
		 * Create a TescoBank.
		 *
		 * @param $account Account number (IB...)
		 * @param $password Secret Word
		 * @param $securitynumber Pass Thingy.
		 */
		public function __construct($account, $password, $securitynumber) {
			parent::__construct();
			$this->account = $account;
			$this->password = $password;
			$this->securitynumber = '.' . $securitynumber;

			if (!class_exists('v8js')) {
				die('TescoBank currently requires v8js.');
			}
			$this->loadPermData();
		}

		/**
		 * String representation of this bank.
		 */
		public function __toString() {
			return 'TescoBank/' . $this->account;
		}

		/**
		 * Force a fresh login.
		 *
		 * @return true if login was successful, else false.
		 */
		public function login($fresh = false) {
			// Index Page.
			if ($fresh) {
				$this->newBrowser(false);
				$page = $this->browser->get('https://www.tescobank.com/sss/auth');
			} else {
				$this->newBrowser(true);
				$page = $this->browser->get('https://www.tescobank.com/portal/auth/portal/sv');
				if ($this->isLoggedIn($page)) {
					return true;
				} else {
					$page = $this->browser->get('https://www.tescobank.com/sss/auth');
				}
			}

			// Fill out the login form and submit it.
			$this->browser->setFieldById('login-uid', $this->account);
			$page = $this->browser->submitFormById('login_uid_form');
			if (empty($page)) {
				die('Error getting initial page.');
			}
			$document = $this->getDocument($page);

			// Dear tesco, fuck off...
			// Tell them a bit about ourselves...
			$cookie = $this->browser->getCurrentCookieValue('ArcotAuthDid');
			$deviceID = isset($this->permdata['deviceID']) ? $this->permdata['deviceID'] : '';
			$data = array('MFP' => '{"navigator":{"doNotTrack":"unspecified","oscpu":"Linux x86_64","vendor":"","vendorSub":"","productSub":"20100101","cookieEnabled":true,"buildID":"20140402095913","appCodeName":"Mozilla","appName":"Netscape","appVersion":"5.0 (X11)","platform":"Linux x86_64","userAgent":"Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:29.0) Gecko/20100101 Firefox/29.0","product":"Gecko","language":"en-US","onLine":true},"plugins":[],"screen":{"availHeight":1200,"availWidth":1920,"colorDepth":24,"height":1200,"pixelDepth":24,"width":1920},"extra":{"timezone":-60,"sigVersion":"1.5"}}',
			              'DeviceIDType' => 'httpcookie',
			              'DeviceID' => $cookie === false ? $deviceID : $cookie,
			              'processreq' => 'true',
			              'StateDataAttrNm' => $document->find('input[name="StateDataAttrNm"]')->attr("value"),
			             );
			$page = $this->browser->post($this->browser->getURL(), $data);
			$document = $this->getDocument($page);

			// Fuck off some more.
			// More about us! Yay.
			$data = array('AUTHTOKEN_PRESENT' => (isset($this->permdata['otpdata']) ? 'true' : 'false'),
			              'ERROR_DETAILS' => '',
			              'processreq' => 'true',
			              'StateDataAttrNm' => $document->find('input[name="StateDataAttrNm"]')->attr("value"),
			              'STORAGE_TYPE' => 'Cookie',
			              'DIAGNOSTICS' => 'Localstorage is supportedCookies are supported',
			             );
			$page = $this->browser->post($this->browser->getURL(), $data);
			$document = $this->getDocument($page);

			// Finally, let's actually tell them some login data.
			$needed = $document->find('input[name^="DIGIT"]:not(:disabled)');
			for ($i = 0; $i < count($needed); $i++) {
				$item = $needed->eq($i);

				$name = $item->attr("name");
				$num = str_replace('DIGIT', '', $name);

				$this->browser->setFieldById($name, $this->securitynumber[$num]);
			}
			// Check if we need to enter a password/otp.
			$passNeeded = $document->find('input#PASSWORD');
			if (count($passNeeded) > 0) {
				$this->browser->setFieldById('AUTHTOKEN_PRESENT', 'true');
				$servertime = $document->find('input#SERVERTIME')->attr("value");
				$mytime = round(microtime(true) * 1000);
				$this->browser->setFieldById('DIFFINTIME', ($mytime - $servertime));
				$this->browser->setFieldById('PROPOSALTIME', $mytime);
				$this->browser->setFieldById('GENERATEDOTP', $this->generateOTP($this->permdata['otpdata'], $this->password, $mytime));
			}

			$page = $this->browser->clickSubmitById('NEXTBUTTON');
			$document = $this->getDocument($page);

			// At this point, we might be asked for a text-message based OTP.
			$OTPNeeded = $document->find('input#MOBILE_NR_DISPLAY');
			if (count($OTPNeeded) > 0) {
				// OTP Time!
				$page = $this->browser->clickSubmitById('SEND-OTA');

				// Input the OTP.
				$otp = getUserInput('Please enter the OTP for '.$this->account.': ');
				$this->browser->setFieldById('OTP', $otp);
				$page = $this->browser->clickSubmitById('NEXTBUTTON');

				// Please remember us :(
				$this->browser->setFieldById('DOWNLOADAID', 'Y');

				// Now, generate a client-side OTP so that we can actually log in...
				preg_match("#var provisionXML = '(.*)'#", $page, $m);
				$xml = $m[1];
				$this->permdata['otpdata'] = $xml;

				$this->browser->setFieldById('AUTHTOKEN_PRESENT', 'true');
				$servertime = $document->find('input#SERVERTIME')->attr("value");
				$mytime = round(microtime(true) * 1000);
				$this->browser->setFieldById('DIFFINTIME', ($mytime - $servertime));
				$this->browser->setFieldById('PROPOSALTIME', $mytime);
				$this->browser->setFieldById('GENERATEDOTP', $this->generateOTP($xml, $this->password, $mytime));

				$page = $this->browser->clickSubmitById('NEXTBUTTON');
			}

			// Save the DeviceID incase it changes.
			preg_match('#var deviceID = "(.*)"#', $page, $m);
			if (!isset($m[1])) {
				die('Login failed.');
			}
			$deviceID = $m[1];

			$this->permdata['deviceID'] = $deviceID;
			$this->savePermData();

			// Now progress the last bit.
			$page = $this->browser->submitFormById('returnform');

			// Never save cookies, tesco bank is flakey as fuck.

			return $this->isLoggedIn($page);
		}

		private function generateOTP($xml, $pass, $time) {
			$jsfile = file_get_contents(dirname(__FILE__) . '/TescoBank-OTP.js');

			$v8 = new V8JS();
			$v8->executeString(<<<V8JS
				var window = {"location": {"hostname": "localhost"}};
				var navigator = {};
V8JS
);

			$v8->executeString($jsfile);
			$res = $v8->executeString(<<<V8JS
				accs = otp_parseXml('$xml');
				arcotClient = new OTP(new Store());
				arcotClient.generateOTPUsingAccount(accs,'$pass', {'time':'$time'});
V8JS
);

			return $res;
		}

		public function isLoggedIn($page) {
			return (strpos($page, 'You\'re logged in to Online Banking') !== FALSE) || (strpos($page, '<a href="/Tesco_Consumer/ChooseServiceReqType.do">Manage your account</a>') !== FALSE);
		}

		/**
		 * Take a Balance as exported by TescoBank, and return it as a standard balance.
		 *
		 * @param $balance Balance input (eg: "£1.00" or "-£1.00")
		 * @return Correct balance (eg: "1.00" or "-1.00")
		 */
		private function parseBalance($balance) {
			$negative = strpos($balance, '-') !== FALSE;
			$balance = str_replace(',', '', $balance);
			$balance = str_replace('&nbsp;', '', $balance);
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
			$page = $this->getPage('https://www.tescobank.com/portal/auth/portal/sv/overview/SVInitialDataWindow?action=1&action=initialDataNoScript');

			if (!$this->isLoggedIn($page)) { return $this->accounts; }
			$page = $this->getDocument($page);

			$accounts = array();

			$accountdetails = $page->find('#sv-creditcard-product');
			$items = $page->find('div.product', $accountdetails);
			$owner = $this->account;
			for ($i = 0; $i < count($items); $i++) {
				// Get the values
				$type = $this->cleanElement($page->find('h2.product-name a', $items->eq($i)));

				// Tesco annoyingly hides the full number of the account, so we use fake sort-code to pad-out the account-key a bit.
				// 00-XX-YY is not a valid sort code. Use 00-01 for tesco credit card.
				$sortcode = '00-00-01';
				$number = $this->cleanElement($page->find('dd.card-number', $items->eq($i)));

				$balance = $this->parseBalance($this->cleanElement($page->find('dd.current-balance', $items->eq($i))));

				// Finally, create an account object.
				$account = new Account();
				$account->setSource($this->__toString());
				$account->setType($type);
				$account->setOwner($owner);
				$account->setSortCode($sortcode);
				$account->setAccountNumber($number);
				$account->setBalance(0 - $balance); // "Balance" given is how much is owed

				$accountKey = preg_replace('#[^0-9]#', '', $account->getSortCode().$account->getAccountNumber());
				$this->accountLinks[$accountKey] = $page->find('h2.product-name a', $items->eq($i))->attr("href");

				$available = $this->parseBalance($this->cleanElement($page->find('dd.available-credit', $items->eq($i))));
				$account->setAvailable($available);

				if ($transactions) {
					$this->updateTransactions($account, $historical, $historicalVerbose);
				}

				$this->accounts[] = $account;
			}

			// return array();
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
			$bits = explode('/', $transaction['date']);
			$transaction['date'] = $bits[1].'/'.$bits[0].'/'.$bits[2];
			$transaction['date'] = strtotime($transaction['date'] . ' Europe/London');

			// Rather than separate in/out, lets just have a +/- amount
			if (!empty($transaction['out'])) {
				$transaction['amount'] = 0 - $this->parseBalance($transaction['out']);
			} else if (!empty($transaction['in'])) {
				$transaction['amount'] = $this->parseBalance($transaction['in']);
			}

			// Unset any unneeded values
			unset($transaction['out']);
			unset($transaction['in']);
			unset($transaction['balance_type']);

			return $transaction;
		}

		private function extractTransactions($page, $baseBalance) {
			$transactions = array();

			// Look for errors.
			$items = $page->find('td[colspan=5].dispute');
			if (count($items) > 0) { return $transactions; }

			// Now get the transactions.
			$items = $page->find('#displayTransaction table tr');
			foreach ($items as $row) {
				echo 'Got Item', "\n";
				$columns = pq($row, $page)->find('td');
				if (count($columns) < 2) { continue; }

				// Pull out the data
				$transaction['extra'] = array();
				$transaction['extra']['transactiondate'] = $this->cleanElement($columns->eq(0));
				$transaction['date'] = $this->cleanElement($columns->eq(1));
				$transaction['description'] = $this->cleanElement($columns->eq(2)->find('a'));
				$description_url = $columns->eq(2)->find('a')->attr('href');
				if (!empty($description_url)) {
					$url = 'https://onlineservicing.creditcards.tescobank.com' . $description_url;
					$dpage = $this->getPage($url, true);
					$dpage = $this->getDocument($dpage);

					$items = $dpage->find('table tr td[colspan=2].normalText');
					$next = false;
					foreach ($items as $col) {
						$content = $this->cleanElement($col);
						if ($content == 'Merchant Information') {
							$next = true;
						} else if ($next) {
							$bits = explode("\n", trim($col->nodeValue));
							foreach ($bits as &$b) { $b = trim($b); }
							$transaction['description'] = implode(' // ', $bits);
							break;
						}
					}
				}

				$transaction['out'] = str_replace(',', '', $this->cleanElement($columns->eq(3)));
				$transaction['in'] = str_replace(',', '', $this->cleanElement($columns->eq(4)));
				$transaction['balance'] = '';

				$transaction['typecode'] = empty($transaction['out']) ? 'IN' : 'OUT';
				$transaction['type'] = empty($transaction['out']) ? 'Credit' : 'Debit';

				$transaction = $this->cleanTransaction($transaction);

				$transactions[] = $transaction;
			}

			// Now loop again, to add in the balance guesses.
			$transactions = array_reverse($transactions);
			foreach ($transactions as &$t) {
				$baseBalance += $t['amount'];
				$t['balance'] = $baseBalance;
			}
			$transactions = array_reverse($transactions);

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
		 * @param $failOnSSO (Default: false) Should we fail if SSO fails?
		 */
		public function updateTransactions($account, $historical = false, $historicalVerbose = true, $failOnSSO = false) {
			$account->clearTransactions();
			$accountKey = preg_replace('#[^0-9]#', '', $account->getSortCode().$account->getAccountNumber());
			$page = $this->getPage('https://www.tescobank.com/' . $this->accountLinks[$accountKey]);
			if (!$this->isLoggedIn($page)) { return false; }

			// The CC server doesn't get along with PHP...
			$this->browser->setStreamContext(array('ssl' => array('ciphers' => 'AES256-SHA')));

			$page = $this->browser->submitFormById('manage-creditcard-account-no-js-form');
			// Check again if we are logged in, sometimes the SSO sucks.
			if (!$this->isLoggedIn($page)) {
				// SSO Failed, do a fresh login...
				// TODO: Figure out a better way than doing this.
				echo 'SSO Fail.';
				if ($failOnSSO) { return false; }
				$this->login(true);

				return $this->updateTransactions($account, $historical, $historicalVerbose, true);
			}
			// Get last statement balance.
			preg_match('#<strong>Statement balance</strong></td>[^"]+"normalText">([^<]+)</td>#', $page, $m);
			$lastBalance = 0 - $this->parseBalance($m[1]);

			// Now get most recent transactions.
			$page = $this->getPage('https://onlineservicing.creditcards.tescobank.com/Tesco_Consumer/ViewTransactions.do', true);
			$page = $this->getDocument($page);

			$transactions = $this->extractTransactions($page, $lastBalance);

			if ($historical) {
				// Get some old shit.
				$dates = $page->find('select[name="cycleDate"] option');
				for ($i = 0; $i < count($dates); $i++) {
					$cycleDate = $dates->eq($i)->attr("value");
					if ($cycleDate == '00') { continue; }
					echo $this->cleanElement($dates->eq($i)), "\n";
					$url = 'https://onlineservicing.creditcards.tescobank.com/Tesco_Consumer/Transactions.do?cycleDate=' . $cycleDate;

					$page = $this->getPage($url, true);
					$page = $this->getDocument($page);

					$lastBalance = '';
					$items = $page->find('table tr td.normalText');
					$next = false;
					foreach ($items as $col) {
						$content = $this->cleanElement($col);
						if ($content == 'Previous balance') {
							$next = true;
						} else if ($next) {
							$lastBalance = 0 - $this->parseBalance($this->cleanElement($col));
							break;
						}
					}

					$olderTransactions = $this->extractTransactions($page, $lastBalance);
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
					// if ($transaction['date'] == $firstDate) { continue; }

					if ($lastDate == $transaction['date']) {
						$dayCount++;
						$transaction['date'] += $dayCount;
					} else {
						$lastDate = $transaction['date'];
						$dayCount = 0;
					}
					$account->addTransaction(new Transaction($this->__toString(), $account->getAccountKey(), $transaction['date'], $transaction['type'], $transaction['typecode'], $transaction['description'], $transaction['amount'], $transaction['balance'], $transaction['extra']));
				}
			}

			// Reset the stream context.
			$this->browser->setStreamContext(array());

echo "Done Transactions.\n";
		}
	}
?>
