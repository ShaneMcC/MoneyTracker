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
				die("Tesco Suck...");
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
			$deviceID = $m[1];

			$this->permdata['deviceID'] = $deviceID;
			$this->savePermData();

			// Now progress the last bit.
			$page = $this->browser->submitFormById('returnform');
			$this->saveCookies();

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
			return (strpos($page, 'You\'re logged in to Online Banking') !== FALSE);
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
			die('getAccounts is unsupported.');
			if (!$this->isLoggedIn($page)) { return $this->accounts; }
			$page = $this->getDocument($page);


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

			die('updateTransactions is unsupported.');
		}
	}
?>
