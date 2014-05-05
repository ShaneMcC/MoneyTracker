<?php
	class database {
		private $db;
		private $pdo;

		public function __construct($pdo) {
			$this->db = new NotORM($pdo);
			$this->pdo = $pdo;
		}

		public function getAccounts() {
			$accounts = array();
			foreach ($this->db->accounts as $row) {
				$r = iterator_to_array($row);
				$acct = Account::fromArray($r);
				$acct->setTransactions($this->getTransactions($acct->getAccountKey()));
				$accounts[] = $acct;
			}
			return $accounts;
		}

		public function getTransactions($accountKey) {
			$transactions = array();
			foreach ($this->db->transactions->where('accountkey',  $accountKey)->order("`time` asc") as $row) {
				$r = iterator_to_array($row);
				$t = Transaction::fromArray($r);
				foreach ($this->db->taggedtransaction->where('transaction',  $t->getHash()) as $tag) {
					$t->addTag($tag['tag'], $tag['value']);
				}
				$t->resetTagsChanged();
				$transactions[] = $t;
			}
			return $transactions;
		}

		public function getTransaction($tid) {
			foreach ($this->db->transactions->where('hash', $tid) as $row) {
				$r = iterator_to_array($row);
				$t = Transaction::fromArray($r);
				foreach ($this->db->taggedtransaction->where('transaction',  $t->getHash()) as $tag) {
					$t->addTag($tag['tag'], $tag['value']);
				}
				$t->resetTagsChanged();
				return $t;
			}

			return FALSE;
		}

		public function deleteTransactionTag($transaction, $tag) {
			$tags = $transaction->getTags();
			$transaction->clearTags();
			foreach ($tags as $t) {
				if ($t[0] != $tag) {
					$transaction->addTag($t[0], $t[1]);
				}
			}

			$this->db->taggedtransaction->where(array('transaction' => $transaction->getHash(), 'tag' => $tag))->delete();
			$transaction->resetTagsChanged();
		}

		public function addTransactionTag($transaction, $tag, $value) {
			if ($transaction->addTag($tag, $value)) {
				$this->db->taggedtransaction->insert(array('transaction' => $transaction->getHash(), 'tag' => $tag, 'value' => $value));
				$transaction->resetTagsChanged();
			}
		}

		public function getAllTags() {
			$q = $this->pdo->query('SELECT t.id AS tagid, c.name AS category, t.tag AS tag FROM tags AS t JOIN categories AS c ON t.category = c.id ORDER by c.name ASC, t.tag ASC');
			$result = $q->fetchAll(PDO::FETCH_ASSOC);
			return $result;
		}
	}
?>