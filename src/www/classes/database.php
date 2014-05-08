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

				if ($t->getHash() != $r['hash']) {
					if ($t->getHash(true) != $r['hash']) {
						echo '<strong>Error: </strong> ', $t->getHash(), ' != ', $r['hash'], '<br>';
					} else {
						echo '<strong>Warning: </strong> ', $t->getHash(), ' using old hash-type - converting...<br>';
						$this->db->transactions->where('hash',  $r['hash'])->update(array('hash' => $t->getHash()));
					}
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

		public function getAllTags($includeEmptyCategories = FALSE) {
			$q = $this->pdo->query('SELECT t.id AS tagid, c.name AS category, c.id AS categoryid, t.tag AS tag, t.ignore AS `ignore` FROM tags AS t '.($includeEmptyCategories ? 'RIGHT' : '').' JOIN categories AS c ON t.category = c.id ORDER by c.name ASC, t.tag ASC');
			$result = $q->fetchAll(PDO::FETCH_ASSOC);
			return $result;
		}

		public function getDB() {
			return $this->db;
		}

		public function getPDO() {
			return $this->pdo;
		}
	}

	class TaggedTransactions {
		private $pdo, $start, $end, $category, $tag, $direction, $group, $allowIgnored;

		public function __construct($db) {
			$this->pdo = $db->getPDO();
			$this->reset();
		}

		/** Set start time. */
		public function start($value) { $this->start = $value; return $this; }

		/** Set end time. */
		public function end($value) { $this->end = $value; return $this; }

		/** Limit to specific tag. */
		public function tag($value) { $this->tag = $value; return $this; }

		/** Limit to specific category. */
		public function category($value) { $this->category = $value; return $this; }

		/** Limit to incoming transactions. */
		public function incoming() { $this->direction = 'in'; return $this; }

		/** Limit to outgoing transactions. */
		public function outgoing() { $this->direction = 'out'; return $this; }

		/** Group By Category */
		public function catOnly() { $this->group = 'category'; return $this; }

		/** Group By Tag */
		public function tagOnly() { $this->group = 'tag'; return $this; }

		/** Total */
		public function total() { $this->group = 'total'; return $this; }

		/** Include Ignored Transaction Types */
		public function ignored() { $this->allowIgnored = true; return $this; }

		/** Reset. */
		public function reset() {
			$this->start = $this->end = $this->category = $this->tag = $this->direction = $this->group = null;
			$this->allowIgnored = false;
			return $this;
		}

		/** Get the tagged transactions and reset. */
		public function get() {

			$params = $select = $from = $where = array();

			$params[':start'] = ($this->start == null) ? strtotime('-30 days 00:00:00') : $this->start;
			$params[':end'] = ($this->end == null) ? time() : $this->end;

			if ($this->group != 'total') { $select[] = 'c.name as cat'; }
			if ($this->group == null || $this->group == 'tag') {  $select[] = 'ts.tag as tag'; }

			$val = 'IF(t.amount > 0, tt.value, 0 - tt.value)';
			$select[] = ($this->group == null) ? $val . ' as value' : 'sum('.$val.') as value';

			if ($this->group == null) { $select[] = 't.*'; }
			if ($this->group != 'total') { $select[] = 'c.id as catid'; }
			if ($this->group == null || $this->group == 'tag') { $select[] = 'tt.tag as tagid'; }

			$from[] = 'taggedtransaction AS tt';
			$from[] = 'transactions AS t ON tt.transaction = t.hash';
			$from[] = 'tags AS ts ON tt.tag = ts.id';
			$from[] = 'categories AS c ON ts.category = c.id';

			$where[] = 't.time >= :start';
			$where[] = 't.time < :end';

			if ($this->direction == 'in') { $where[] = 't.amount > 0'; }
			else if ($this->direction == 'out') { $where[] = 't.amount < 0'; }

			if ($this->tag != null) {
				$where[] = 'tt.tag = :tag';
				$params[':tag'] = $this->tag;
			}

			if ($this->category != null) {
				$where[] = 'c.name = :cat';
				$params[':cat'] = $this->category;
			}

			if (!$this->allowIgnored) {
				$where[] = 'ts.ignore = 0';
			}

			$q = sprintf('SELECT %s FROM %s WHERE %s', implode(', ', $select), implode(' JOIN ', $from), implode(' AND ', $where));
			if ($this->group == 'category') { $q .= ' GROUP by catid'; }
			if ($this->group == 'tag') { $q .= ' GROUP by tagid'; }
			$q .= ' ORDER BY value';

			$this->reset();

			if ($q = $this->pdo->prepare($q)) {
				if ($q->execute($params)) {
					return $q->fetchAll(PDO::FETCH_ASSOC);
				}
			}

			return FALSE;
		}

	}
?>
