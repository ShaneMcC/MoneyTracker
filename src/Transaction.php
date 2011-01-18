<?php
	class Transaction {
		private $myTime;
		private $myType;
		private $myDescription;
		private $myAmount;
		private $myBalance;

		public function __construct($time = '', $type = '', $description = '', $amount = '', $balance = '') {
			$this->myTime = $time;
			$this->myType =  $type;
			$this->myDescription = $description;
			$this->myAmount = $amount;
			$this->myBalance = $balance;
		}

		function __toString() {
			$string = '';
			$string .= 'Time: ' . $this->myTime. "\n";
			$string .= 'Type: ' . $this->myType. "\n";
			$string .= 'Description: ' . $this->myDescription. "\n";
			$string .= 'Amount: ' . $this->myAmount. "\n";
			$string .= 'Balance: ' . $this->myBalance . "\n";
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
