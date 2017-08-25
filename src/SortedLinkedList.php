<?php
	class SortedLinkedListNode {
		public $data;
		public $next;
		public $prev;

		public function __construct($data, $prev, $next) {
			$this->data = $data;
			$this->prev = $prev;
			$this->next = $next;
		}

		public function getKey() {
			return $this->data;
		}
	}

	class SortedLinkedListNodeFactory {
		public function newNode($data) {
			return new SortedLinkedListNode($data, null, null);
		}
	}

	class SortedLinkedList {
		protected $factory = null;
		protected $start = null;
		protected $end = null;
		protected $current = null;

		public function __construct($data = NULL, $factory = NULL) {
			$this->setNodeFactory($factory);

			$this->clear();
			$this->insertArray($data);
		}

		public function setNodeFactory($factory) {
			if ($factory !== null && $factory instanceof SortedLinkedListNodeFactory) {
				$this->factory = $factory;
			} else {
				$this->factory = new SortedLinkedListNodeFactory();
			}
		}

		public function clear() {
			$this->start = null;
			$this->end = null;
			$this->current = null;
		}

		public function insertArray($arr) {
			if ($arr !== NULL && is_array($arr)) {
				foreach ($arr as $a) {
					$this->insert($a);
				}
			}
		}

		public function insert($data) {
			$node = $this->factory->newNode($data);

			if ($this->start == null) {
				// First node.
				$this->start = &$node;
				$this->end = &$node;
			} else if ($node->getKey() < $this->start->getKey()) {
				// Older than first node.
				$node->next = $this->start;
				$this->start->prev = $node;
				$this->start = &$node;
			} else  if ($node->getKey() >= $this->end->getKey()) {
				// Later than last node.
				$node->prev = $this->end;
				$this->end->next = $node;
				$this->end = &$node;
			} else {
				// Somewhere in the middle.
				$current = $this->start;
				while ($current) {
					if ($current->getKey() <= $node->getKey() && $current->next->getKey() > $node->getKey()) {
						$node->prev = $current;
						$node->next = $current->next;

						$node->next->prev = $node;
						$node->prev->next = $node;
						break;
					}
					$current = $current->next;
				}
			}
		}

		public function remove($node) {
			$node->next->prev = $node->prev;
			$node->prev->next = $node->next;

			if ($node === $this->start) {
				$this->start = $node->next;
			}

			if ($node === $this->end) {
				$this->end = $node->prev;
			}

			if ($node === $this->current) {
				$this->current = $node->next;
			}
		}

		public function current() {
			return $this->current;
		}

		public function next() {
			$this->current = $this->current->next;
		}

		public function prev() {
			$this->current = $this->current->prev;
		}

		public function rewind() {
			$this->current = $this->start;
		}

		public function fastforward() {
			$this->current = $this->end;
		}

		public function valid() {
			return $this->current !== NULL;
		}
	}
