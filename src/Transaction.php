<?php
	class Transaction {
		private $myTime;
		private $myType;
		private $myTypeCode;
		private $myDescription;
		private $myAmount;
		private $myBalance;
		private $myExtra;
		private $mySource;
		private $myAccountKey;

		public function __construct($source = '', $accountKey = '', $time = '', $type = '', $typecode = '', $description = '', $amount = '', $balance = '', $extra = '') {
			$this->myTime = $time;
			$this->myType =  $type;
			$this->myTypeCode =  $typecode;
			$this->myDescription = $description;
			$this->myAmount = $amount;
			$this->myBalance = $balance;
			$this->myExtra = $extra;
			$this->mySource = $source;
			$this->myAccountKey = $accountKey;
		}

		function __toString() {
			$string = '';
			$string .= 'Time: ' . $this->myTime. "\n";
			$string .= 'Account Key: ' . $this->myAccountKey . "\n";
			$string .= 'Source: ' . $this->mySource . "\n";
			$string .= 'Type: ' . $this->myType . ' (' . $this->myTypeCode . ')'. "\n";
			$string .= 'Description: ' . $this->myDescription. "\n";
			$string .= 'Amount: ' . $this->myAmount. "\n";
			$string .= 'Balance: ' . $this->myBalance . "\n";
			return $string;
		}

		function toArray() {
			return array('hash' => $this->getHash(),
			             'time' => $this->myTime,
			             'accountkey' => $this->myAccountKey,
			             'changetype' => $this->myType,
			             'typecode' => $this->myTypeCode,
			             'description' => $this->myDescription,
			             'amount' => $this->myAmount,
			             'balance' => $this->myBalance,
			             'extra' => $this->myExtra,
			             'source' => $this->mySource);
		}

		static function fromArray($array) {
			$obj = new Transaction();
			$obj->myTime = $array['time'];
			$obj->myAccountKey = $array['accountkey'];
			$obj->myType = $array['changetype'];
			$obj->myTypeCode = $array['typecode'];
			$obj->myDescription = $array['description'];
			$obj->myAmount = $array['amount'];
			$obj->myBalance = $array['balance'];
			$obj->myExtra = $array['extra'];
			$obj->mySource = $array['source'];

			return $obj;
		}

		function getTime() { return $this->myTime; }
		function getType() { return $this->myType; }
		function getTypeCode() { return $this->myTypeCode; }
		function getAccountKey() { return $this->myAccountKey; }
		function getDescription() { return $this->myDescription; }
		function getAmount() { return $this->myAmount; }
		function getBalance() { return $this->myBalance; }
		function getExtra() { return $this->myExtra; }
		function getSource() { return $this->mySource; }

		// Create an almost unique hash for this transaction using the data we have.
		//
		// This is not foolproof.
		//
		// If there are 2 outgoing transactions on the same day, for the same amount
		// with the same description, that result in the same final balance, this
		// will return a duplicate. :(
		// But hopefully that won't ever happen!
		// If only we could get transaction times or something aswell as dates :(
		function getHash() {
			return sprintf('%s-%u-%u-%s-%s-%s', $this->getAccountKey(),
			                                 $this->getTime(),
			                                 crc32($this->getDescription()),
			                                 $this->getTypeCode(),
			                                 str_replace('-', 'N', $this->getAmount()),
			                                 str_replace('-', 'N', $this->getBalance())
			                                 );
		}
	}
?>
