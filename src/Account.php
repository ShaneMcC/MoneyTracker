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
		private $myTransactions = array();

		public function __construct($owner = '', $type = '', $sortcode = '', $accnumber = '', $balance = '', $available = '', $limits = '', $misc = '') {
			if (!defined('CRLF')) { define('CRLF', "\r\n"); }
			$this->myOwner = $owner;
			$this->myType = $type;
			$this->mySortCode = $sortcode;
			$this->myAccountNumber = $accnumber;
			$this->myBalance = $balance;
			$this->myAvailable = $available;
			$this->myLimits = $limits;
			$this->myMisc = $misc;
			$this->myTime = time();
		}

		function __toString() {
			$string = '';
			$string .= 'Owner: ' . $this->myOwner . ;
			$string .= 'Type: ' . $this->myType . "\n";
			$string .= 'Sort-Code: ' . $this->mySortCode . "\n";
			$string .= 'Account Number: ' . $this->myAccountNumber . "\n";
			$string .= 'Balance: ' . $this->myBalance . "\n";
			$string .= 'Available: ' . $this->myAvailable . "\n";
			$string .= 'Limits: ' . $this->myLimits . "\n";
			return $string;
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

		function setOwner($newValue) { $this->myOwner = $newValue; }
		function setType($newValue) { $this->myType = $newValue; }
		function setSortCode($newValue) { $this->mySortCode = $newValue; }
		function setAccountNumber($newValue) { $this->myAccountNumber = $newValue; }
		function setFullNumber($newValue) { $this->mySortCode.' '.$this->myAccountNumber = $newValue; }
		function setBalance($newValue) { $this->myBalance = $newValue; }
		function setAvailable($newValue) { $this->myAvailable = $newValue; }
		function setLimits($newValue) { $this->myLimits = $newValue; }
		function setMisc($newValue) { $this->myMisc = $newValue;}

		function addTransaction($transaction) { $this->myTransactions[] = $transaction; }
		function clearTransactions() { $this->myTransactions = array(); }
	}
?>
