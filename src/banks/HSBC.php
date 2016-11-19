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

				if ($tempForm) {
					$method = 'tempForm';
					$page = $this->browser->submitFormByName('tempForm');
				} else if ($autoSubmitForm) {
					$method = 'autoSubmitForm';
					$page = $this->browser->submitFormByName($m[1]);
				} else if ($windowLocationHref) {
					$method = 'windowLocationHref';
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
			$element = $page->find('a[title="Log on to Personal Internet Banking"');
			$loginurl = $element->eq(0)->attr('href');
			$page = $this->browser->get('https://www.hsbc.co.uk' . $loginurl);
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
				$page = $this->browser->get('https://' . $this->saasDomain . '/1/3/HSBCINTEGRATION/welcome?BlitzToken=blitz');
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
			} else if ($this->webVersion == HSBC::VER_PRE_NOV2016) {
				$this->getAccounts_preNov2016($useCached, $transactions, $historical, $historicalVerbose);
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

		private function getAccounts_preNov2016($useCached = true, $transactions = false, $historical = false, $historicalVerbose = false) {
			$this->accounts = array();

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
					$account->setSortCode('00-00-02');
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
			} else {
				$card = $account->getType() == 'CREDIT CARD';
				if ($card) {
					$page = $this->getPage('https://' . $this->saasDomain . '/1/3/personal/online-banking/credit-card-transactions?ActiveAccountKey=' . $account->getAccountNumber() . '&accountId=' . $account->getAccountNumber() . '&productType=CCA&BlitzToken=blitz');
				} else {
					$page = $this->getPage('https://' . $this->saasDomain . '/1/3/personal/online-banking/recent-transaction?ActiveAccountKey=' . $accountKey . '&BlitzToken=blitz');
				}
				if (!$this->isLoggedIn($page)) { return false; }
				$page = $this->getDocument($page);

				if ($card) {
					$this->updateCardTransactions($account, $accountKey, $page, $historical, $historicalVerbose);
				} else {
					$this->updateStandardTransactions($account, $accountKey, $page, $historical, $historicalVerbose);
				}
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
				usort($transactions, function($a, $b) {
					// If the dates are the same, sort such that the lower
					// txnIndex (newer transaction) is first.
					if ($a['date'] == $b['date']) {
						return $b['txnIndex'] - $a['txnIndex'];
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

		/**
		 * Update transactions from a credit-card account view.
		 *
		 * @param $account Account Object
		 * @param $accountKey Calculated Account Key
		 * @param $page Initial Page
		 * @param $historical (Default: false) Also try to get historical
		 *                    transactions?
		 * @param $historicalVerbose (Default: false) Should verbose data be
		 *                           collected for historical, or is a single-line
		 *                           description ok?
		 */
		public function updateCardTransactions($account, $accountKey, $page, $historical = false, $historicalVerbose = true) {
			$head = $page->find('table.extPibTable')->eq(0)->find('th');
			$body = $page->find('table.extPibTable')->eq(0)->find('td');
			$details = array();
			for ($i = 0; $i < count($head); $i++) {
				$key = $this->cleanElement($head->eq($i)->find('p')->eq(0));
				$value = $this->cleanElement($body->eq($i)->find('p')->eq(0));

				$details[strtolower($key)] = $value;
			}

			if (isset($details['current balance'])) {
				$givenBalance = strip_tags($details['current balance']);
				$currentbalance = $this->parseNiceBalance($givenBalance);
				$val = trim(strip_tags($givenBalance));
				$val = explode(' ', $val);
				$inCredit = isset($val[1]) && $val[1] == 'CR';
				if (!$inCredit) { $currentbalance = 0 - $currentbalance; }
				$account->setBalance($currentbalance);
			}
			if (isset($details['current limit'])) {
				$account->setLimits('Credit Limit: '.$this->parseNiceBalance(strip_tags($details['current limit'])));
			}
			if (isset($details['available credit'])) {
				$account->setAvailable($this->parseNiceBalance(strip_tags($details['available credit'])));
			}

			$lastBalance = $account->getBalance();
			$items = $page->find('table[summary!=""] thead tr');
			$transactions = array();
			foreach ($items as $row) {
				$columns = pq($row, $page)->find('td');
				if (count($columns) < 1) { continue; }

				// Pull out the data
				$transaction['date'] = trim(strip_tags($this->cleanElement($columns->eq(0))));
				$transaction['description'] = trim(strip_tags($this->cleanElement($columns->eq(1))));
				$val = trim(strip_tags($this->cleanElement($columns->eq(2))));
				$val = explode(' ', $val);
				$out = !isset($val[1]) || $val[1] != 'CR';
				$val = $this->parseNiceBalance($val[0]);
				$transaction['balance'] = $lastBalance;

				if ($out) {
					$transaction['out'] = $val;
					$transaction['in'] = '';
					$transaction['typecode'] = 'DR';
					$lastBalance += $val;
				} else {
					$transaction['in'] = $val;
					$transaction['out'] = '';
					$transaction['typecode'] = 'CR';
					$lastBalance -= $val;
				}

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

		/**
		 * Update transactions from a standard account view.
		 *
		 * @param $account Account Object
		 * @param $accountKey Calculated Account Key
		 * @param $page Initial Page
		 * @param $historical (Default: false) Also try to get historical
		 *                    transactions?
		 * @param $historicalVerbose (Default: false) Should verbose data be
		 *                           collected for historical, or is a single-line
		 *                           description ok?
		 */
		private function updateStandardTransactions($account, $accountKey, $page, $historical = false, $historicalVerbose = true) {
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

			// Now try the historical ones.
			if ($historical) {
				// Get the first page of the list of historical statements.
				$page = $this->getPage('https://www.saas.hsbc.co.uk/1/3/personal/online-banking/previous-statements');
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
						$url = 'https://'.$this->saasDomain.$link->getAttribute('href');
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
							$transaction['typecode'] = $this->cleanElement($columns->eq(1)->find('p'));
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
									$url = 'https://'.$this->saasDomain.$dlink->attr('href');
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
										/* echo 'ERROR WITH CALCULATED BALANCES:', "\n";
										echo '    Expected: ', $transaction['balance'], "\n";
										echo '    Calculated: ', ($lastBalance + $transaction['amount']), "\n";
										echo '    lastBalance: ', $lastBalance, "\n";
										echo "\n";
										var_dump($transaction);
										echo "\n"; */
										throw new ScraperException("HSBC Unable to calculate accurate balance. (Expected: " . $transaction['balance'] . " - Calculated: " . ($lastBalance + $transaction['amount']) . ")");
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
								$account->addTransaction(new Transaction($this->__toString(), $account->getAccountKey(), $transaction['date'], $transaction['type'], $transaction['typecode'], $transaction['description'], $transaction['amount'], $transaction['balance']));
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
									$nextLink = 'https://'.$this->saasDomain.$link->getAttribute('href');
								}
							}
						}

						if ($nextLink == '') {
							// No other pages of statements, abort.
							break;
						}
					}

					// Also look for a previous link.
					if ($prevLink == '') {
						$links = $page->find('div.extButtons div.hsbcButtonCenter a');
						if (count($links) > 0) {
							foreach ($links as $link) {
								if ($link->getAttribute('title') == 'Previous set of statements') {
									$prevLink = 'https://'.$this->saasDomain.$link->getAttribute('href');
								}
							}
						}
					}

					if ($nextLink != '') {
						$page = $this->getPage($nextLink);
						$page = $this->getDocument($page);
					}
				}
			} // end if ($historical);
		}
	}
?>
