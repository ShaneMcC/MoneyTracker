<?php
	require_once(dirname(__FILE__) . '/../Bank.php');
	require_once(dirname(__FILE__) . '/../Account.php');
	require_once(dirname(__FILE__) . '/../Transaction.php');

	/**
	 * Code to pull data from Monzo
	 */
	class Monzo extends WebBank {
		private $account = '';
		private $clientid = '';
		private $clientSecret = '';

		private $accounts = null;
		private $whoami = null;

		/**
		 * Create a Monzo.
		 *
		 * @param $account Account name
		 * @param $clientid OAuth Client ID
		 * @param $clientSecret OAuth Client Secret
		 */
		public function __construct($account, $clientid, $clientSecret) {
			parent::__construct();
			$this->account = $account;
			$this->clientid = $clientid;
			$this->clientSecret = $clientSecret;

			$this->loadPermData();
		}

		/**
		 * String representation of this bank.
		 */
		public function __toString() {
			return 'Monzo/' . $this->account;
		}

		private function monzoGet($method) {
			$opts = array('http' => array('method'  => 'GET'));
			if (isset($this->permdata['authData']) && isset($this->permdata['authData']['access_token'])) {
				$opts['http']['header'] = 'Authorization: Bearer ' . $this->permdata['authData']['access_token'];
			}
			$context  = stream_context_create($opts);
			$result = @file_get_contents('https://api.monzo.com' . $method, false, $context);

			return @json_decode($result, true);
		}

		/**
		 * Force a fresh login.
		 *
		 * @return true if login was successful, else false.
		 */
		public function login($fresh = false) {
			global $config;

			if (isset($this->permdata['authData']) && isset($this->permdata['authData']['access_token'])) {
				// Check that we still have valid auth data.
				if ($this->isLoggedIn()) {
					// We do, return immedaitely.
					return true;
				} else {
					// Try and refresh our access token.
					if (isset($this->permdata['authData']['refresh_token'])) {
						$postData = http_build_query(array("grant_type" => "refresh_token",
						                                   "client_id" => $this->clientid,
						                                   "client_secret" => $this->clientSecret,
						                                   "refresh_token" => $this->permdata['authData']['refresh_token'],
						                                  )
						                            );

						$authData = file_post_contents("https://api.monzo.com/oauth2/token", $postData);

						$this->permdata['authData'] = @json_decode($authData, true);
						$this->savePermData();
						return true;
					} else {
						// No refresh token means we need new auth data.
						unset($this->permdata['authData']);
					}
				}
			} else {
				// Auth Data may be incomplete, remove it.
				unset($this->permdata['authData']);
			}

			$this->savePermData();

			// If we do not have valid authdata, try and get some.
			if (!isset($this->permdata['authData']) || !isset($this->permdata['authData']['access_token'])) {
				// Request a new token, this requires user engagement...
				$stateToken = bin2hex(openssl_random_pseudo_bytes(10));
				$redirect_uri = $config['baseurl'] . 'monzo.php';
				$clientURL = 'https://auth.getmondo.co.uk/?client_id=' . urlencode($this->clientid) . '&response_type=code&state=' . urlencode($stateToken) . '&redirect_uri=' . urlencode($redirect_uri);

				echo 'Please visit: ', $clientURL, "\n";
				$authCode = getUserInput('Then enter the provided code: ');
				if ($authCode === false) {
					return false;
				}

				$postData = http_build_query(array("grant_type" => "authorization_code",
				                                   "client_id" => $this->clientid,
				                                   "client_secret" => $this->clientSecret,
				                                   "redirect_uri" => $redirect_uri,
				                                   "code" => $authCode,
				                                  )
				                            );
				$authData = file_post_contents("https://api.monzo.com/oauth2/token", $postData);

				$this->permdata['authData'] = json_decode($authData, true);
				$this->savePermData();

				// Check that what we got was valid.
				return $this->isLoggedIn();
			}

			// No valid login data :(
			return false;
		}

		public function isLoggedIn($page = '') {
			// Check that what we got was valid.
			$result = $this->monzoGet('/ping/whoami');
			$this->whoami = $result;

			return (isset($result['authenticated']) && $result['authenticated']);
		}

		/**
		 * Take a Balance as exported by Monzo, and return it as a standard balance.
		 *
		 * @param $balance Balance input (eg: "£1.00" or "-£1.00")
		 * @return Correct balance (eg: "1.00" or "-1.00")
		 */
		private function parseBalance($balance) {
			if (empty($balance) && $balance !== 0) { return ''; }
			return $balance / 100;
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

			if (!$this->login()) {
				return FALSE;
			}

			$this->accounts = array();
			$accountData = $this->monzoGet('/accounts');

			foreach ($accountData['accounts'] as $acc) {
				$account = new Account();
				$account->setSource($this->__toString());
				$account->setType($acc['type']);
				$account->setOwner($this->account);

				if (!in_array($acc['type'], ['uk_prepaid', 'uk_retail'])) {
					echo 'Unknown MONZO Account type: ', $acc['type'], "\n";
					continue;
				}

				if (isset($acc['sort_code'])) {
					$account->setSortCode($acc['sort_code']);
					$account->setAccountNumber($acc['account_number']);
				} else {
					$account->setSortCode('00-00-03');
					$account->setAccountNumber($acc['id']);
				}

				$accountKey = preg_replace('#[^0-9a-z]#i', '', $account->getSortCode() . $account->getAccountNumber());
				$this->accountLinks[$accountKey] = array('id' => $acc['id']);

				$balance = $this->monzoGet('/balance?account_id=' . $acc['id']);
				$account->setBalance($this->parseBalance($balance['balance']));

				if ($transactions) {
					$this->updateTransactions($account, $historical, $historicalVerbose, $historicalLimit);
				}

				$this->accounts[] = $account;
			}

			return $this->accounts;
		}

		private function getType($typecode) {
			$typecodes['TOP'] = 'Topup';
			$typecodes['CR'] = 'Credit';
			$typecodes['DR'] = 'Debit';

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
		public function updateTransactions($account, $historical = false, $historicalVerbose = true, $historicalLimit = 0) {
			$account->clearTransactions();
			$accountKey = preg_replace('#[^0-9a-z]#i', '', $account->getSortCode() . $account->getAccountNumber());

			$accData = $this->accountLinks[$accountKey];

			$transactionData = $this->monzoGet('/transactions?expand[]=merchant&account_id=' . $accData['id']);

			$lastBalance = NULL;

			$tlist = new SortedLinkedList($transactionData['transactions'], new Monzo_SLLNF());
			$transactions = [];

			// *grumble*
			$noValidBalance = ($account->getType() == 'uk_retail');

			if ($noValidBalance) {
				$tlist->fastforward();
				$lastBalance = $account->getBalance() * 100;
			} else {
				$tlist->rewind();
			}
			$count = 0;
			while ($tlist->valid()) {
				if ($count++ > 10000) { throw new Exception('Monzo: Something went wrong with tlist loop, exceeded 10k transacions.'); }
				$node = $tlist->current();

				$trans = &$node->data;

				if (!$noValidBalance) {
					$lastBalance = ($node->prev == null) ? null : $node->prev->data['account_balance'];
				}

				if (isset($trans['decline_reason'])) {
					if ($noValidBalance) {
						$tlist->prev();
					} else {
						$tlist->next();
					}
					continue;
				}

				// Pull out the data
				$transaction = array();
				if (!isset($trans['isSettlement'])) { $trans['isSettlement'] = false; }

				$transaction['date'] = strtotime($trans['created']);

				$transaction['description'] = $trans['isSettlement'] ? 'Settlement Changes: ' : '';
				$transaction['description'] .= preg_replace('#(\t|\s{2,})#', ' // ', $trans['description']);
				if (isset($trans['notes']) && !empty($trans['notes'])) {
					$transaction['description'] .= ' // ' . $trans['notes'];
				}

				$transaction['amount'] = $this->parseBalance($trans['amount']);

				// Some account types don't have a real balance (*grumble*)
				// So we need to work it out.
				if ($noValidBalance) {
					$transaction['balance'] = $this->parseBalance($lastBalance);
					$lastBalance -= $trans['amount'];

					// If we haven't settled this transaction yet, throw it out
					// and all transactions we've noticed so far, as we don't
					// actually know if these are right yet. *grumble*
					if (!isset($trans['settled']) || strtotime($trans['settled']) >= time()) {
						$transactions = [];
						continue;
					}
				} else {
					// Settlement transactions won't have a balance yet, set one.
					if (!isset($trans['account_balance'])) { $trans['account_balance'] = $lastBalance + $trans['amount']; }

					$transaction['balance'] = $this->parseBalance($trans['account_balance']);
				}

				$transaction['typecode'] = $trans['amount'] < 0 ? 'DR' : 'CR';
				if (isset($trans['metadata']['is_topup']) && $trans['metadata']['is_topup']) {
					$transaction['typecode'] = 'TOP';
				}

				$transaction['type'] = $this->getType($transaction['typecode']);

				$transaction['extra'] = array();
				$transaction['extra']['hashcode'] = $trans['id'] . '-' . $trans['dedupe_id'];
				$transaction['extra']['txid'] = $trans['id'];
				$transaction['extra']['category'] = $trans['category'];
				if ($trans['merchant'] != null) {
					$transaction['extra']['merchant_id'] = $trans['merchant']['id'];
					$transaction['extra']['merchant_name'] = $trans['merchant']['name'];
					$transaction['extra']['merchant_address'] = $trans['merchant']['address']['short_formatted'];
				}

				if ($trans['currency'] != $trans['local_currency']) {
					$transaction['extra']['local_currency'] = $trans['local_currency'];
					$transaction['extra']['local_amount'] = $this->parseBalance($trans['local_amount']);
				}

				if ($trans['isSettlement']) {
					$transaction['extra']['hashcode'] .= '-settlement';
					$transaction['extra']['settlment_for'] = $trans['id'];
					$transaction['extra']['original_date'] = strtotime($trans['original_date']);
				} else if (!$noValidBalance) {
					$wantBalance = $lastBalance + $trans['amount'];

					if ($lastBalance != NULL && $trans['account_balance'] != $wantBalance) {
						if ($trans['currency'] != $trans['local_currency']) {
							// Currency conversion rate changes between the time
							// we paid it, and the time it was settled.
							//
							// The 'amount' value is equal to the post-settlement rate.
							// The 'account_balance' value is equal to what our balance was after the initial pre-settlement conversion.
							//
							// Fix the current transaction to be the amount it was
							// when it was first paid, and add a separate settlement
							// transaction that we deal with later.

							// The actual amount paid initially is the difference
							// between the current balance and the previous balance.
							$transaction['amount'] = $this->parseBalance($trans['account_balance'] - $lastBalance);

							// Create settlement transaction to parse later.
							$settlement = $trans;

							// Set the date of the new transaction to the
							// settled date so that it gets inserted into the
							// list in the right place.
							$settlement['created'] = $trans['settled'];
							$settlement['original_date'] = $trans['created'];

							// The settlement value is the difference between
							// the post-settlement 'amount' and the difference
							// between the 2 balances
							$settlement['amount'] = $trans['amount'] - ($trans['account_balance'] - $lastBalance);

							// Unset the account_balance so that we recalculate
							// it later based on the position in the list.
							//
							// (Monzo does haxy magic to silently correct balances)
							unset($settlement['account_balance']);
							$settlement['isSettlement'] = true;
							$tlist->insert($settlement);
						}
					}
				}

				$transactions[] = $transaction;

				if ($noValidBalance) {
					$tlist->prev();
				} else {
					$tlist->next();
				}
			}

			if ($noValidBalance) {
				$transactions = array_reverse($transactions);
			}

			$lastDate = NULL;
			foreach ($transactions as $transaction) {
				// Fix equal-date transactions to ensure we pull them out
				// in the right order later.
				if ($transaction['date'] <= $lastDate) { $transaction['date'] = $lastDate + 1;}
				$lastDate = $transaction['date'];

				$t = new Transaction($this->__toString(), $account->getAccountKey(), $transaction['date'], $transaction['type'], $transaction['typecode'], $transaction['description'], $transaction['amount'], $transaction['balance'], $transaction['extra']);
				$account->addTransaction($t);
			}
		}
	}


	class Monzo_SLLN extends SortedLinkedListNode {
		public function getKey() {
			return new DateTime($this->data['created']);
		}
	}

	class Monzo_SLLNF extends SortedLinkedListNodeFactory {
		public function newNode($data) {
			return new Monzo_SLLN($data, null, null);
		}
	}
