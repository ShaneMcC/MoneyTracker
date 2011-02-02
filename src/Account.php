<?php
	include('Transaction.php');

	class Account {
		private $myOwner = '';
		private $myType = '';
		private $mySortCode = '';
		private $myAccountNumber = '';
		private $myBalance = '';
		private $myTime = '';
		private $myAvailable = '';
		private $myLimits = '';
		private $myMisc = '';
		private $myDescription = '';
		private $myExtra = '';
		private $mySource = '';
		private $myTransactions = array();

		public function __construct($source = '', $owner = '', $type = '', $sortcode = '', $accnumber = '', $balance = '', $available = '', $limits = '', $misc = '', $desc = '', $extra = '') {
			if (!defined('CRLF')) { define('CRLF', "\r\n"); }
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
			return array('accountkey' => $this->getAccountKey(),
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
			             'source' => $this->mySource);
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

		function addTransaction($transaction) { $this->myTransactions[] = $transaction; }
		function clearTransactions() { $this->myTransactions = array(); }

		function getAccountKey() { return preg_replace('#[^0-9]#', '', $this->getSortCode() . $this->getAccountNumber()); }
	}
?>
