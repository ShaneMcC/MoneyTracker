<?php
	require_once(dirname(__FILE__) . '/../Bank.php');
	require_once(dirname(__FILE__) . '/../Account.php');
	require_once(dirname(__FILE__) . '/../Transaction.php');

	require_once(dirname(__FILE__) . '/../3rdparty/simpletest/browser.php');
	require_once(dirname(__FILE__) . '/../3rdparty/phpquery/phpQuery/phpQuery.php');

	/**
	 * Code to pretend to be the TescoBank Mobile app.
	 *
	 * This is an extension of TescoBank but using mobile calls where required.
	 */
	class TescoBankMobile extends TescoBank {
		private $tbapitoken = '';
		private $loggedIn = false;
		private $myID = '';
		protected $accountReq = array();

		public function __construct($account, $password, $securitynumber) {
			parent::__construct($account, $password, $securitynumber);
		}

		/**
		 * Force a fresh login.
		 *
		 * @return true if login was successful, else false.
		 */
		public function login($fresh = false) {
			if (!$fresh && $this->loggedIn) { return true; }
			$this->newBrowser(false);

			// Get Device ID.
			$deviceID = $this->checkDeviceID();

			// Get user IDs
			$page = $this->browser->get('https://mob.tescobank.com/broker/api/users/ids/' . urlencode($this->account));
			$ids = @json_decode($page);
			$this->myID = $ids->tbId;

			// Get pam stuff that we don't care about.
			$page = $this->browser->get('https://mob.tescobank.com/broker/api/credential/pam/' . urlencode($this->myID));
			$pam = @json_decode($page);

			// Get the challenge code.
			$page = $this->browser->get('https://mob.tescobank.com/broker/api/login/challenge/' . urlencode($ids->cssoId));
			$code = @json_decode($page);

			// Post the pinDigits asked for.
			$data = array('verifyTokenVersion' => (isset($this->permdata['mobileOTPData']) ? 'true' : 'false'), 'deviceId' => $deviceID, 'pinDigits' => '');
			foreach ($code->pinDigits as $num) {
				$data['pinDigits'] .= $this->securitynumber[$num];
			}
			$page = $this->browser->post('https://mob.tescobank.com/broker/api/login/challenge/response/' . urlencode($ids->cssoId) .'/' . urlencode($code->challengeID), $data);
			$token = @json_decode($page);

			if (!property_exists($token, 'encryptedToken')) {
				// Re-Challenge - new OTP Needed.
				unset($this->permdata['mobileOTPData']);

				// Get the challenge code.
				$page = $this->browser->get('https://mob.tescobank.com/broker/api/login/challenge/' . urlencode($ids->cssoId));
				$code = @json_decode($page);

				// Post the pinDigits asked for.
				$data = array('verifyTokenVersion' => 'false', 'deviceId' => $deviceID, 'pinDigits' => '');
				foreach ($code->pinDigits as $num) {
					$data['pinDigits'] .= $this->securitynumber[$num];
				}
				$page = $this->browser->post('https://mob.tescobank.com/broker/api/login/challenge/response/' . urlencode($ids->cssoId) .'/' . urlencode($code->challengeID), $data);
				$token = @json_decode($page);
			}

			// We get a new header now, add it to all future requests
			$headers = http_parse_headers($this->browser->getHeaders());
			$this->tbapitoken = $headers['X-TB-API-TOKEN'];
			$this->browser->addHeader('Authorization: TB-API-TOKEN' . $this->tbapitoken);
			$this->browser->addHeader('credentials: auth-scheme #auth-param');

			// First time registering will need a mobile OTP first.
			if (!isset($this->permdata['mobileOTPData'])) {
				// Do Mobile OTP
				$this->browser->get('https://mob.tescobank.com/broker/api/sms/' . urlencode($ids->cssoId) .'/' . urlencode($this->myID));
				$smsDigits = getUserInput('Please enter the OTP for '.$this->account.': ');
				$data = array('smsDigits' => $smsDigits);
				$page = $this->browser->post('https://mob.tescobank.com/broker/api/sms/response/' . urlencode($ids->cssoId), $data);
			}

			$xml = trim(preg_replace('/\s+/', ' ', $token->encryptedToken));
			preg_match('#<utc>([0-9]+)</utc>#', $xml, $m);
			$time = ($m[1] * 1000);

			// Now do the password.
			$otp = $this->generateOTP($xml, $time);

			$data = array('otp' => $otp, 'deviceId' => $deviceID);
			$page = $this->browser->post('https://mob.tescobank.com/broker/api/login/authenticate/' . urlencode($this->myID), $data);
			$authed = @json_decode($page);

			if (property_exists($authed, 'authenticated') && $authed->authenticated) {
				// Our authentication level has increased.
				$headers = http_parse_headers($this->browser->getHeaders());
				$this->tbapitoken = $headers['X-TB-API-TOKEN'];
				$this->browser->addHeader('Authorization: TB-API-TOKEN' . $this->tbapitoken);

				// Save Data for future logins!
				if (!isset($this->permdata['mobileOTPData'])) {
					$this->permdata['mobileOTPData'] = $xml;

					$data = $this->getDeviceData('eth0');
					$data['deviceId'] = $deviceID;

					$page = $this->browser->post('https://mob.tescobank.com/broker/api/registered/' . urlencode($ids->cssoId) .'/'. urlencode($deviceID), $data);
					$savedDevice = @json_decode($page);

					if (property_exists($savedDevice, 'registrationComplete') && $savedDevice->registrationComplete) {
						// We're done, save it!
						$this->savePermData();
					}
				}

				$this->loggedIn = true;
				echo 'Logged In.', "\n";
				return true;
			} else {
				return false;
			}
		}

		protected function newBrowser($loadCookies = true) {
			parent::newBrowser($loadCookies);

			$this->browser->setUserAgent('Dalvik/1.6.0 (Linux; U; Android 4.4.4; Nexus 5 Build/KTU84P)');
			$this->browser->addHeader('X-Credential: MobWord');
			$this->browser->addHeader('X-Jailbroken: Y');
			$this->browser->addHeader('X-DeviceType: Nexus 5');
			$this->browser->addHeader('X-OSVersion: 4.4.4');
			$this->browser->addHeader('X-OSName: Android');

			if (!empty($this->tbapitoken)) {
				$this->browser->addHeader('Authorization: TB-API-TOKEN' . $this->tbapitoken);
				$this->browser->addHeader('credentials: auth-scheme #auth-param');
			}
		}

		private function getDeviceData($iface) {
			$data = array();

			$mac = trim(file_get_contents('/sys/class/net/'.$iface.'/address'));
			$ip = trim(exec('ip addr show dev '.$iface.' | grep "inet " | cut -d " " -f 6  | cut -f 1 -d "/"'));

			$data['deviceIsJailbroken'] = 'false';
			$data['deviceOsVersion'] = '4.4.4';
			$data['avlHeight'] = '1776';
			$data['deviceModel'] = 'Nexus 5';
			$data['mac'] = $mac;
			$data['fullWidth'] = '1080';
			$data['fullHeight'] = '1776';
			$data['avlWidth'] = '1080';
			$data['clientAppName'] = 'Tesco Bank';
			$data['deviceOsName'] = 'Android';
			$data['timezone'] = 'Greenwich Mean Time';
			$data['clientAppVersion'] = '1.1.2';
			$data['language'] = 'English';
			$data['internalIP'] = $ip;
			$data['externalIP'] = $ip;

			return $data;
		}

		private function checkDeviceID() {
			if ($this->browser == null) { $this->newBrowser(false); }

			$data = $this->getDeviceData('eth0');
			if (isset($this->permdata['mobileDeviceID'])) {
				$data['deviceId'] = $this->permdata['mobileDeviceID'];
			}

			$page = $this->browser->post('https://mob.tescobank.com/broker/api/checkDevice', $data);
			$decoded = @json_decode($page);

			$this->permdata['mobileDeviceID'] = $decoded->deviceID;

			return $decoded->deviceID;
		}

		public function isLoggedIn($page) {
			return $this->loggedIn;
		}


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
			if (empty($this->myID)) { $this->login(); }

			$decoded = @json_decode($this->getPage('https://mob.tescobank.com/broker/api/products/' . $this->myID));
			if ($decoded == null) { return false; }

			$accounts = array();
			foreach ($decoded->products as $prod) {
				$account = new Account();
				$account->setSource($this->__toString());
				$account->setType($prod->productName);
				$account->setOwner($this->account);

				// Get More Data
				$req = '[{"customerId":null,"productCategory":"' . $prod->productCategory . '","productId":"' . $prod->productId . '","productType":"' . $prod->productType . '"}]';
				$details = @json_decode($this->browser->post('https://mob.tescobank.com/broker/api/products/' . $this->myID, array('products' => $req)));
				if ($details == null || property_exists($details, 'errorResponse')) { continue; }

				$account->setSortCode('00-00-01');
				$account->setAccountNumber($details->products[0]->cardNumber);
				$account->setBalance(0 - $details->products[0]->balance);

				$accountKey = preg_replace('#[^0-9]#', '', $account->getSortCode().$account->getAccountNumber());
				$this->accountLinks[$accountKey] = '{"customerId":null,"productCategory":"' . $prod->productCategory . '","productId":"' . $prod->productId . '","productType":"' . $prod->productType . '","amountDue":null,"amountOfPaymentsGoingOut":null}';
				$this->accountReq[$accountKey] = $req;

				if ($transactions) {
					$this->updateTransactions($account, $historical, $historicalVerbose, $historicalLimit);
				}

				$accounts[] = $account;
			}

			return $accounts;
		}

		public function updateTransactions($account, $historical = false, $historicalVerbose = true, $historicalLimit = 0) {
			if (empty($this->myID)) { $this->login(); }
			$account->clearTransactions();

			$accountKey = preg_replace('#[^0-9]#', '', $account->getSortCode().$account->getAccountNumber());

			$details = @json_decode($this->browser->post('https://mob.tescobank.com/broker/api/products/' . $this->myID, array('products' => $this->accountReq[$accountKey])));

			// Get last statement balance.
			$lastBalance = 0 - $details->products[0]->statementBalance;

			// Now get most recent transactions.
			$transactions = @json_decode($this->browser->post('https://mob.tescobank.com/broker/api/products/' . $this->myID . '/transactions/page/1' , array('product' => $this->accountLinks[$accountKey])));
			var_dump($transactions);

			throw new Exception('TescoBankMobile unable to parse Transactions.');
			// Incomplete, as tesco bank mobile returns bad dates.

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

				// Set the current account balance based on the balance after the
				// most recent transaction.
				$account->setBalance($transactions[0]['balance']);

				// If we're not asking for historical, then we don't need to
				// go back any further.
				if (!$historical) { break; }
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
