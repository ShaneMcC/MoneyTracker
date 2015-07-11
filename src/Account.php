<?php
	require_once(dirname(__FILE__) . '/Transaction.php');

	class Account {
		private $myOwner = null;
		private $myType = null;
		private $mySortCode = null;
		private $myAccountNumber = null;
		private $myBalance = null;
		private $myTime = null;
		private $myAvailable = null;
		private $myLimits = null;
		private $myMisc = null;
		private $myDescription = null;
		private $myExtra = null;
		private $mySource = null;
		private $myHidden = null;
		private $myTransactions = array();

		public function __construct($source = null, $owner = null, $type = null, $sortcode = null, $accnumber = null, $balance = null, $available = null, $limits = null, $misc = null, $desc = null, $extra = null, $hidden = null) {
			$this->myOwner = $owner;
			$this->myType = $type;
			$this->mySortCode = $sortcode;
			$this->myAccountNumber = $accnumber;
			$this->myBalance = $balance;
			$this->myAvailable = $available;
			$this->myLimits = $limits;
			$this->myMisc = $misc;
			$this->myDescription = $desc;
			$this->myExtra = $extra;
			$this->mySource = $source;
			$this->myHidden = $hidden;
			$this->myTime = time();
		}

		function __toString() {
			$string = '';
			$string .= 'Owner: ' . $this->myOwner . "\n";
			$string .= 'Type: ' . $this->myType . "\n";
			$string .= 'Description: ' . $this->myDescription . "\n";
			$string .= 'Source: ' . $this->mySource . "\n";
			$string .= 'Sort-Code: ' . $this->mySortCode . "\n";
			$string .= 'Account Number: ' . $this->myAccountNumber . "\n";
			$string .= 'Balance: ' . $this->myBalance . "\n";
			$string .= 'Available: ' . $this->myAvailable . "\n";
			$string .= 'Limits: ' . $this->myLimits . "\n";
			$string .= 'Transaction Count: ' . count($this->myTransactions) . "\n";
			return $string;
		}

		function toArray() {
			$result = array('accountkey' => $this->getAccountKey(),
			                'sortcode' => $this->mySortCode,
			                'accountnumber' => $this->myAccountNumber,
			                'type' => $this->myType,
			                'description' => $this->myDescription,
			                'owner' => $this->myOwner,
			                'lastbalance' => $this->myBalance,
			                'limits' => $this->myLimits,
			                'available' => $this->myAvailable,
			                'misc' => $this->myMisc,
			                'extra' => $this->myExtra,
			                'source' => $this->mySource,
			                'hidden' => $this->myHidden);

			foreach (array_keys($result) as $k) {
				if ($result[$k] === null) { unset($result[$k]); }
			}

			if (!isset($result['accountnumber']) || !isset($result['source'])) { die('Bad Account'); }

			return $result;
		}

		static function fromArray($array) {
			$obj = new Account();
			$obj->mySortCode = $array['sortcode'];
			$obj->myAccountNumber = $array['accountnumber'];
			$obj->myType = $array['type'];
			$obj->myDescription = $array['description'];
			$obj->myOwner = $array['owner'];
			$obj->myBalance = $array['lastbalance'];
			$obj->myLimits = $array['limits'];
			$obj->myAvailable = $array['available'];
			$obj->myMisc = $array['misc'];
			$obj->myExtra = $array['extra'];
			$obj->mySource = $array['source'];
			$obj->myHidden = $array['hidden'];

			return $obj;
		}

		function getOwner() { return $this->myOwner; }
		function getType() { return $this->myType; }
		function getSortCode() { return $this->mySortCode; }
		function getAccountNumber() { return $this->myAccountNumber; }
		function getFullNumber() { return $this->mySortCode.' '.$this->myAccountNumber; }
		function getBalance() { return $this->myBalance; }
		function getAvailable() { return $this->myAvailable; }
		function getLimits() { return $this->myLimits; }
		function getMisc() { return $this->myMisc; }
		function getTransactions() { return $this->myTransactions; }
		function getDescription() { return $this->myDescription; }
		function getExtra() { return $this->myExtra; }
		function getSource() { return $this->mySource; }
		function getHidden() { return $this->myHidden; }

		function getDisplayName() {
			return (!startsWith($this->mySortCode, '00-') ? $this->mySortCode.' ' : '') . $this->myAccountNumber;
		}

		function getDescriptionOrType() {
			return !empty($this->myDescription) ? $this->myDescription : $this->myType;
		}

		function setOwner($newValue) { $this->myOwner = $newValue; }
		function setType($newValue) { $this->myType = $newValue; }
		function setSortCode($newValue) { $this->mySortCode = $newValue; }
		function setAccountNumber($newValue) { $this->myAccountNumber = $newValue; }
		function setFullNumber($newValue) { $this->mySortCode.' '.$this->myAccountNumber = $newValue; }
		function setBalance($newValue) { $this->myBalance = $newValue; }
		function setAvailable($newValue) { $this->myAvailable = $newValue; }
		function setLimits($newValue) { $this->myLimits = $newValue; }
		function setMisc($newValue) { $this->myMisc = $newValue;}
		function setDescription($newValue) { $this->myDescription = $newValue; }
		function setExtra($newValue) { $this->myExtra = $newValue; }
		function setSource($newValue) { $this->mySource = $newValue; }
		function setHidden($newValue) { $this->myHidden = $newValue; }

		function addTransaction($transaction) { $this->myTransactions[] = $transaction; }
		function clearTransactions() { $this->myTransactions = array(); }
		function setTransactions($transactions) { $this->myTransactions = $transactions; }

		function sortTransactions($oldestFirst = true) {
			usort($this->myTransactions, function ($a, $b) use ($oldestFirst) {
				if ($a->getTime() == $b->getTime()) { return 0; }
				if ($oldestFirst) {
					return ($a->getTime() < $b->getTime()) ? -1 : 1;
				} else {
					return ($b->getTime() < $a->getTime()) ? -1 : 1;
				}
			});
		}

		function getAccountKey() { return preg_replace('#[^0-9]#', '', $this->getSortCode() . $this->getAccountNumber()); }
	}
?>
