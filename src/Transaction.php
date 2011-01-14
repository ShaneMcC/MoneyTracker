<?php
	class Transaction {
		private $myTime;
		private $myType;
		private $myDescription;
		private $myAmount;
		private $myBalance;
		
		public function __construct($time = '', $type = '', $description = '', $amount = '', $balance = '') {
			if (!defined('CRLF')) { define('CRLF', "\r\n"); }
			$this->myTime = $time;
			$this->myType =  $type;
			$this->myDescription = $description;
			$this->myAmount = $amount;
			$this->myBalance = $balance;
		}
		
		function __toString() {
			$string = '';
			$string .= 'Time: '.$this->myTime.CRLF;
			$string .= 'Type: '.$this->myType.CRLF;
			$string .= 'Description: '.$this->myDescription.CRLF;
			$string .= 'Amount: '.$this->myAmount.CRLF;
			$string .= 'Balance: '.$this->myBalance.CRLF;
			return $string;
		}
		
		function getTime() { return $this->myTime; }
		function getType() { return $this->myType; }
		function getDescription() { return $this->myDescription; }
		function getAmount() { return $this->myAmount; }
		function getBalance() { return $this->myBalance; }

		function getHash() {
			return sprintf('%u-%u-%s', $this->getTime(), crc32($this->getDescription()), $this->getType());
		}
	}
?>
