<?php
	class taggedtransactions_page extends page {

		/** {@inheritDoc} */
		public function pageConstructor() {
			$this->tf()->setVar('title', 'Money Tracker :: Transactions');

			$p = $this->getParams();
			if (!isset($p['sub']) || empty($p['sub'])) {
				$this->redirectTo('transactions');
			}

			$this->tf()->setVar('showPeriods', true);
			$q = $this->getQuery();

			$period = isset($q['period']) ? $q['period'] : '';
			list($name, $start, $end) = getPeriod($period);
			$this->tf()->setVar('periodName', $name);
			$this->tf()->setVar('start', $start);
			$this->tf()->setVar('end', $end);
			$this->tf()->setVar('period', $period);
			$this->tf()->setVar('thisPeriod', $period);
		}

		/** {@inheritDoc} */
		public function displayPage() {
			$db = $this->tf()->getVar('db', null);

			$params = $this->getQuery();
			$this->tf()->setVar('hideEmpty', true);

			$period = $this->tf()->getVar('period');
			$start = $this->tf()->getVar('start');
			$end = $this->tf()->getVar('end');

			$p = $this->getParams();
			$tag = $p['sub'];
			$accounts = $db->getAccounts(true, $start);
			foreach ($accounts as $acct) {
				$old = $acct->getTransactions();
				$acct->clearTransactions();
				foreach ($old as $t) {
					if (count($t->getTags()) == 0 && $tag == -1) {
						$acct->addTransaction($t);
					} else {
						foreach ($t->getTags() as $ttag) {
							if ($ttag[0] == $tag) {
								$acct->addTransaction($t);
								break;
							}
						}
					}
				}
			}

			$tags = array();
			$jsontags = array();
			foreach ($db->getAllTags() as $t) {
				$tags[$t['tagid']] = $t['category'] . ' :: ' . $t['tag'];
				$jsontags[$t['category']][$t['tag']] = $t['tagid'];
			}

			$this->tf()->setVar('tags', $tags);
			$this->tf()->setVar('jsontags', $jsontags);
			$this->tf()->setVar('accounts', $accounts);
			$this->tf()->setVar('onlyUntagged', isset($params['untagged']));
			$this->tf()->setVar('filtered', true);
			$this->tf()->get('transactions')->display();
		}
	}
?>
