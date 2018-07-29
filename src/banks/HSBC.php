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

		private $securityDomain = 'www.security.hsbc.co.uk';
		private $saasDomain = 'www.saas.hsbc.co.uk';
		private $servicesDomain = 'www.services.online-banking.hsbc.co.uk';
		protected $accountData = array();

		private $accounts = null;


		const VER_UNKNOWN = 0;
		const VER_PRE_NOV2016 = 1;
		const VER_NOV2016 = 2;

		private $webVersion = HSBC::VER_UNKNOWN;
		private $appSettings = null;

		private function initAppSettings() {
			$this->appSettings = array();
			$this->appSettings[HSBC::VER_NOV2016] = ['appVer' => 'UK656',
			                                        'member' => 'hbeu',
			                                        'country' => 'gb',
			                                        'lang' => 'en_GB',
			                                        'contextPath' => '/gpib',
			                                       ];
		}

		private function getAppSetting($setting) {
			if (isset($this->appSettings[$this->webVersion][$setting])) {
				return $this->appSettings[$this->webVersion][$setting];
			} else {
				return '';
			}
		}

		private function setAppSetting($setting, $value) {
			if (!isset($this->appSettings[$this->webVersion])) {
				$this->appSettings[$this->webVersion] = array();
			}

			$this->appSettings[$this->webVersion][$setting] = $value;
		}

		/**
		 * Create a HSBC.
		 *
		 * @param $account Account number (IB...)
		 * @param $secretword Secret Word
		 * @param $securekey Secure Key Code
		 */
		public function __construct($account, $secretword, $securekey) {
			parent::__construct();
			$this->account = $account;
			$this->secretword = $secretword;
			$this->securekey = $securekey;

			$this->initAppSettings();
		}

		/**
		 * String representation of this bank.
		 */
		public function __toString() {
			return 'HSBC/' . $this->account;
		}

		/**
		 * Handle any form-based redirects.
		 *
		 * @param $page (By Ref) Page that we want to check for redirects.
		 */
		public function followFormRedirect(&$page) {
			while (true) {
				$oldURL = $this->browser->getUrl();

				$tempForm = preg_match('#document\.tempForm\.submit#Ums', $page) || preg_match("#document\.getElementById\('tempForm'\)\.submit\(\)#Ums", $page);
				$autoSubmitForm = preg_match('#var autoSubmitForm = document.getElementById\("([^"]+)"\);#Ums', $page, $m);
				$windowLocationHref = preg_match('#window.location.href = "([^"]+)";.*<body>Please wait...</body>#Ums', $page, $m2);
				$redirectFunction = preg_match('#function redirect\(\){[^}]+location.href = "([^"]+)";[^}]+#Ums', $page, $m3);

				if ($tempForm) {
					$method = 'tempForm';
					$page = $this->browser->submitFormByName('tempForm');
				} else if ($autoSubmitForm) {
					$method = 'autoSubmitForm';
					$page = $this->browser->submitFormByName($m[1]);
				} else if ($windowLocationHref || $redirectFunction) {

					if ($windowLocationHref) {
						$method = 'windowLocationHref';
					} else if ($redirectFunction) {
						$method = 'redirectFunction';
						$m2 = $m3;
					}
					$urlInfo = parse_url($this->browser->getUrl());

					if ($m2[1][0] == '/') {
						$newURL = $urlInfo['scheme'] . '://' . $urlInfo['host'] . $m2[1];
						if (isset($urlInfo['query']) && !empty($urlInfo['query'])) {
							$newURL .= '&';
							$newURL .= $urlInfo['query'];
						}
					} else {
						$newURL = $m2[1];
					}

					$page = $this->browser->get($newURL);
					if (empty($page)) {
						// Sometimes the page comes back empty... try again.
						// echo 'REPEATED: ';
						$page = $this->browser->get($newURL);
					}
				} else {
					break;
				}

				// echo $oldURL, ' -[', $method, ']-> ', $this->browser->getUrl(), "\n";
			}
		}

		public function handleRedirects(&$page) {
			$this->followFormRedirect($page);
		}

		/**
		 * Force a fresh login.
		 *
		 * @return true if login was successful, else false.
		 */
		public function login($fresh = false) {
			if (!$fresh) {
				$this->newBrowser(true);
				$page = $this->browser->get('https://' . $this->saasDomain . '/1/3/personal/online-banking?BlitzToken=blitz');
				$this->followFormRedirect($page);

				if ($this->isLoggedIn($page)) {
					return true;
				}
			} else {
				$this->newBrowser(false);
			}
			// echo "Pre-Home", "\n";
			$page = $this->browser->get('https://www.hsbc.co.uk/');
			$this->followFormRedirect($page);
			$page = $this->getDocument($page);
			// echo "Home", "\n";
			// Move to login page
			$element = $page->find('a.login-button');
			$loginurl = $element->eq(0)->attr('href');
			$page = $this->browser->get($loginurl);
			$this->followFormRedirect($page);
			// echo "Login", "\n";
			// Fill out the form and submit it.
			$this->browser->setFieldById('Username1', $this->account);
			// $this->browser->setMaximumRedirects(1);
			$page = $this->browser->submitFormById('ibLogonForm');

			// Submit a couple of SaaS forms.
			$this->followFormRedirect($page);
			// echo "SecureKey Bit", "\n";
			$securityDomain = parse_url($this->browser->getUrl());
			$this->securityDomain = $securityDomain['host'];

			if ($this->securekey == '##') {
				$this->browser->get('https://' . $this->securityDomain . '/gsa/IDV_CAM20_OTP_CHALLENGE/?__USER=withSecKey');
				$this->securekey = getUserInput('Please enter the securekey code for '.$this->account.': ');
				if ($this->securekey === false) {
					return false;
				}

				// Set the fields
				$this->browser->setFieldById('memorableAnswer', $this->secretword);
				$this->browser->setFieldById('idv_OtpCredential', $this->securekey);
			} else if (startsWith($this->securekey, '@')) {
				$page = $this->browser->get('https://' . $this->securityDomain . '/gsa/IDV_CAM10TO30_AUTHENTICATION/?__USER=withOutSecKey');

				if (!preg_match('#chalNums: \[([0-9], [0-9], [0-9])\]#Ums', $page, $matches)) {
					return false;
				}
				$wanted = explode(',', $matches[1]);
				$digits = array();
				foreach ($wanted as $d) {
					if ($d == '8') { $d = strlen($this->securekey) - 1; }
					if ($d == '7') { $d = strlen($this->securekey) - 2; }

					$digits[] = $this->securekey[$d];
				}

				$this->browser->setFieldById('memorableAnswer', $this->secretword);
				$this->browser->setFieldById('password', implode('', $digits));
			}
			// echo "Post-Login", "\n";
			$page = $this->browser->clickSubmit('Continue');
			while (preg_match('#document\.tempForm\.submit#Ums', $page)) {
				$page = $this->browser->submitFormByName('tempForm');
			}

			$bankDomain = parse_url($this->browser->getUrl());
			$this->saasDomain = $bankDomain['host'];
			$this->servicesDomain = $bankDomain['host'];

			$testPage = $this->getDocument($page);
			if ($testPage->find('#_loaderForeground')) {
				// New Style
				$this->webVersion = HSBC::VER_NOV2016;
				$page = $this->browser->get('https://' . $this->saasDomain . $this->getAppSetting('contextPath'));
				$this->followFormRedirect($page);
			} else {
				// Old Style
				$this->webVersion = HSBC::VER_PRE_NOV2016;
				throw new Exception('Pre Nov-2016 Mode is no longer supported.');
			}

			// And done.
			$this->saveCookies();

			// echo "Done?", ($this->isLoggedIn($page) ? 'LOGIN OK' : 'LOGIN FAIL'), "\n";
			return $this->isLoggedIn($page);
		}

		public function isLoggedIn($page) {
			// The horrible javascripty page for the NOV2016 version doesn't
			// actually have any nice text to confirm the login.
			//
			// For now, let's assume that if we can see the customer_id div
			// that, we're logged in...
			$viewMyAccounts = (strpos($page, 'View My accounts') !== FALSE);
			$customerId = (strpos($page, '<div id="customer_id" ') !== FALSE);

			// Guess the version if needed.
			if ($this->webVersion == HSBC::VER_UNKNOWN) {
				if ($customerId) {
					$this->webVersion = HSBC::VER_NOV2016;
				} else if ($viewMyAccounts) {
					$this->webVersion = HSBC::VER_PRE_NOV2016;
					throw new Exception('Pre Nov-2016 Mode is no longer supported.');
				}
			}
			return ($viewMyAccounts || $customerId);
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
		 * Take a Nice Balance as used in some parts of HSBC, and return it
		 * as a standard balance.
		 *
		 * @param $balance Balance input (eg: "£1.00" or "-£1.00")
		 * @return Correct balance (eg: "1.00" or "-1.00")
		 */
		private function parseNiceBalance($balance) {
			$original = $balance;
			if (empty($balance)) { return ''; }
			$negative = strpos($balance, '-') !== FALSE;
			$balance = str_replace(',', '', $balance);

			$balance = trim($balance);
			$bal = explode(' ', $balance);

			if (preg_match('@([0-9]+.[0-9]+)$@', $bal[0], $matches)) {
				return $negative ? 0 - $matches[1] : $matches[1];
			} else {
				throw new ScraperException('HSBC Error parsing Balance: "'.$original.'"' . "\n");
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

			// $page = $this->getPage('https://' . $this->saasDomain . '/1/3/personal/online-banking?BlitzToken=blitz');
			$page = $this->getPage('https://www.hsbc.co.uk/1/3/personal/online-banking');
			if (!$this->isLoggedIn($page)) { die('Unable to login'); return $this->accounts; }

			if ($this->webVersion == HSBC::VER_NOV2016) {
				$this->getAccounts_Nov2016($useCached, $transactions, $historical, $historicalVerbose);
			}

			return $this->accounts;
		}


		private function getAccounts_Nov2016($useCached = true, $transactions = false, $historical = false, $historicalVerbose = false) {
			$contextPath = 'https://' . $this->servicesDomain . $this->getAppSetting('contextPath');

			$url = $contextPath . '/channel/proxy/accountDataSvc/rtrvAcctSumm';
			$urlInfo = parse_url($url);

			$accSummReq = ['accountSummaryFilter' => ['txnTypCdes' => [],
			                                          'entityCdes' => [['ctryCde' => strtoupper($this->getAppSetting('country')),
			                                                            'grpMmbr' => strtoupper($this->getAppSetting('member'))],
			                                                          ],
			                                         ],
			              ];

			$oldHeaders = $this->browser->getAdditionalHeaders();
			$this->browser->addHeader("Content-type: application/json");
			$this->browser->addHeader("X-HDR-Synchronizer-Token: " . $this->browser->getCookieValue($urlInfo['host'], $urlInfo['path'], "SYNC_TOKEN"));
			$page = $this->browser->post($url, json_encode($accSummReq), 'application/json');
			$this->browser->setAdditionalHeaders($oldHeaders);

			$accountdetails = json_decode($page, true);
			if ($accountdetails === null) { return $this->accounts; }

			foreach ($accountdetails['countriesAccountList'][0]['acctLiteWrapper'] as $acct) {
				// Get the values
				$type = $this->getAccountTypeFromCode($acct['entProdTypCde']);
				$owner = $acct['acctHldrFulName'][0];
				$number = explode(' ', $acct['displyID']);
				$balance = (String)$acct['ldgrBal']['amt'];

				$account = new Account();
				$account->setSource($this->__toString());
				$account->setType($type);
				$account->setOwner($owner);
				if ($acct['prodCatCde'] == 'CC') {
					$account->setSortCode('00-00-02');
					$account->setAccountNumber(implode('', $number));
				} else {
					$account->setSortCode($number[0]);
					$account->setAccountNumber($number[1]);
				}
				$account->setBalance($balance);

				// GET DETAILED ACCOUNT INFO
				$detailedURL = $url = $contextPath . '/channel/proxy/accountDataSvc/rtrvDDAcctDtl';
				$arrayType = 'ddAcctDtl';
				if ($acct['prodCatCde'] == 'CC') {
					$detailedURL = $url = $contextPath . '/channel/proxy/accountDataSvc/rtrvCCAcctDtl';
					$arrayType = 'ccAcctDtl';
				}

				$urlInfo = parse_url($url);
				$this->browser->addHeader("Content-type: application/json");
				$this->browser->addHeader("X-HDR-Synchronizer-Token: " . $this->browser->getCookieValue($urlInfo['host'], $urlInfo['path'], "SYNC_TOKEN"));
				$rtrvDDAcctDtl = ["acctIdr" => ["acctIndex" => $acct['acctIndex'],
				                                "entProdTypCde" => $acct['entProdTypCde'],
				                                "entProdCatCde" => $acct['entProdCatCde'],
				                               ],
				                 ];
				$detailPage = $this->browser->post($url, json_encode($rtrvDDAcctDtl), 'application/json');
				$this->browser->setAdditionalHeaders($oldHeaders);
				$acctDetails = json_decode($detailPage, true);

				if ($acctDetails !== null && isset($acctDetails[$arrayType])) {
					$acctD = $acctDetails[$arrayType];
					$acct['detailed_info'] = $acctD;

					if ($acct['prodCatCde'] == 'CC') {
						if (isset($acctD['creditLimit']['amt']) && $acctD['creditLimit']['amt'] != 0) {
							$account->setLimits('Credit Limit: '.$acctD['creditLimit']['amt']);
						}
						if (isset($acctD['availCreditAmt']['amt']) && $acctD['availCreditAmt']['amt'] != 0) {
							$account->setAvailable($acctD['availCreditAmt']['amt']);
						}
					} else {
						if (isset($acctD['creditLimit']['amt']) && $acctD['creditLimit']['amt'] != 0) {
							$account->setLimits('Overdraft Limit: '.$acctD['creditLimit']['amt']);
						}
						if (isset($acctD['availBal']['amt']) && $acctD['availBal']['amt'] != $acctD['ldgrBal']['amt']) {
							$account->setAvailable($acctD['availBal']['amt']);
						}
					}
				} else {
					if (isset($acct['availBal']['amt']) && $acct['availBal']['amt'] != $acct['ldgrBal']['amt']) {
						$account->setAvailable($acct['availBal']['amt']);
					}
				}

				$accountKey = preg_replace('#[^0-9]#', '', $account->getSortCode().$account->getAccountNumber());
				$this->accountData[$accountKey] = $acct;

				if ($transactions) {
					$this->updateTransactions($account, $historical, $historicalVerbose);
				}


				$this->accounts[] = $account;
			}
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
				$current = strtotime($transaction['date'] . ' Europe/London');
				$last = strtotime('-1 year Europe/London', $current);
				$transaction['date'] = ($current > time()) ? $last : $current;
			} else {
				// If we have been given a year, then use it.
				$current = strtotime($transaction['date']. ' ' . $baseYear . ' Europe/London');
				$last = strtotime('-1 year Europe/London', $current);
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
			if (isset($transaction['balance_type'])) {
				$transaction['balance'] = $this->cleanBalance($transaction['balance'], $transaction['balance_type']);
			}

			$transaction['typecode'] = preg_replace('#[^A-Z0-9)]#', '', $transaction['typecode']);
			$transaction['type'] = $this->getType($transaction['typecode']);

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

		private function getAccountTypeFromCode($code) {
			$settings = $this->getAppSetting('ProductTypeDesc_displayText_nls');

			if (empty($settings)) {
				$settings = file_get_contents('https://www.content.online-banking.hsbc.co.uk/ContentService/gsp/ChannelsLibrary/Components/client/actservicing/bijit/nls/en-gb/ProductTypeDesc_displayText_nls.js');
				$settings = json_decode(preg_replace('#^define\((.*)\);$#Ums', '$1', $settings), true);
				$settings = $settings['productTypeCode'];
				$this->setAppSetting('ProductTypeDesc_displayText_nls', $settings);
			}

			return isset($settings[$code]) ? $settings[$code] : $code;
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

			if ($this->webVersion == HSBC::VER_NOV2016) {
				$this->updateTransactions_Nov2016($account, $historical, $historicalVerbose);
			}
		}

		/**
		 * Update transactions from a credit-card account view.
		 *
		 * @param $account Account Object
		 * @param $historical (Default: false) Also try to get historical
		 *                    transactions?
		 * @param $historicalVerbose (Default: false) Should verbose data be
		 *                           collected for historical, or is a single-line
		 *                           description ok?
		 */
		public function updateTransactions_Nov2016($account, $historical, $historicalVerbose) {
			$accountKey = preg_replace('#[^0-9]#', '', $account->getSortCode().$account->getAccountNumber());
			if (!isset($this->accountData[$accountKey])) { return false; }
			$acct = $this->accountData[$accountKey];

			$contextPath = 'https://' . $this->servicesDomain . $this->getAppSetting('contextPath');

			$url = $contextPath . '/channel/proxy/accountDataSvc/rtrvTxnSumm';
			$urlInfo = parse_url($url);

			$txnHistType = null;
			$fromDate = date('Y-m-d', strtotime('-120 days'));
			$toDate = date('Y-m-d');
			if ($acct['prodCatCde'] == 'CC') {
				$txnHistType = 'U';
				$toDate = "";
			}

			$rtrvTxnSumm = ["retreiveTxnSummaryFilter" => ["txnDatRnge" => ["fromDate" => $fromDate, "toDate" => $toDate],
			                                               "numOfRec" => -1,
			                                               "txnAmtRnge" => null,
			                                               "txnHistType" => $txnHistType],
			                "acctIdr" => ["acctIndex" => $acct['acctIndex'],
			                              "entProdTypCde" => $acct['entProdTypCde'],
				                          "entProdCatCde" => $acct['entProdCatCde']],
			                "pagingInfo" => ["startDetail" => null, "pagingDirectionCode" => "D"]
			               ];

			$oldHeaders = $this->browser->getAdditionalHeaders();
			$this->browser->addHeader("Content-type: application/json");
			$this->browser->addHeader("X-HDR-Synchronizer-Token: " . $this->browser->getCookieValue($urlInfo['host'], $urlInfo['path'], "SYNC_TOKEN"));
			$page = $this->browser->post($url, json_encode($rtrvTxnSumm), 'application/json');
			$this->browser->setAdditionalHeaders($oldHeaders);

			$txns = json_decode($page, true);
			if ($txns === null || !isset($txns['txnSumm'])) { return; }

			$transactions = array();
			$txnIndex = 0;
			foreach ($txns['txnSumm'] as $txn) {
				// Pull out the data
				$transaction['date'] = $txn['txnPostDate'];
				$transaction['datestr'] = $txn['txnPostDate'];
				$transaction['date'] = strtotime($transaction['date'] . ' Europe/London');

				if ($acct['prodCatCde'] == 'CC') {
					// Remove un-needed bits from the txnDetail.
					// $txn['txnDetail'][0] == Description
					// $txn['txnDetail'][1] == BusinessType
					// $txn['txnDetail'][2] == Town
					// $txn['txnDetail'][3] == Country
					// $txn['txnDetail'][4] == Payment Method
					// $txn['txnDetail'][5] == Pin Used
					// $txn['txnDetail'][6] == Card Used
					// $txn['txnDetail'][7] == Exchange Rate */
					$bits = $txn['txnDetail'];
					$txn['txnDetail'] = [$bits[0]];
				}

				$transaction['description'] = implode(' // ', $txn['txnDetail']);
				$transaction['description'] = preg_replace('#[\n\s]+#ims', ' ', $transaction['description']);
				$transaction['description'] = html_entity_decode($transaction['description']);
				$transaction['description'] = preg_replace('#//[\s]+$#', '', $transaction['description']);
				$transaction['description'] = trim($transaction['description']);

				$transaction['amount'] = $txn['txnAmt']['amt'];
				if (isset($txn['balRunAmt'])) {
					$transaction['balance'] = $txn['balRunAmt']['amt'];
				} else {
					$transaction['balance'] = NULL;
				}

				if (isset($txn['txnCatCde'])) {
					$transaction['typecode'] = $txn['txnCatCde'];
				} else {
					$transaction['typecode'] = ($txn['txnAmt']['amt'] < 0) ? 'DR' : 'CR';
				}
				$transaction['type'] = $this->getType($transaction['typecode']);
				$transaction['txnIndex'] = $txnIndex++;

				$transactions[] = $transaction;
			}

			// CCs suck and don't provide the balance after the transaction.
			// So calculate it ourselves.
			if ($acct['prodCatCde'] == 'CC') {
				// First, correctly sort by date because CCs are silly.
				// This sorts everything in reverse-order, newest transaction
				// first.
				usort($transactions, function($a, $b) {
					// If the dates are the same, sort such that the lower
					// txnIndex (newer transaction) is first.
					if ($a['date'] == $b['date']) {
						return $a['txnIndex'] - $b['txnIndex'];
					} else {
						// If the dates are not the same, sort such that the
						// newer date is first.
						return $b['date'] - $a['date'];
					}
				});

				// Now, apply balances...
				// Loop through all transactions, and calculate the balance.
				//
				// We know what the balance is "now" and we know how each
				// transaction impacted it, so we can apply the balance that
				// way.
				$lastBalance = $acct['ldgrBal']['amt'];
				foreach ($transactions as &$txn) {
					$txn['balance'] = $lastBalance;
					if ($txn['amount'] < 0) {
						$lastBalance += abs($txn['amount']);
					} else {
						$lastBalance -= abs($txn['amount']);
					}
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
