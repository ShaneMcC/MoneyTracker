<?php
	class taggedtransactions_page extends page {

		/** {@inheritDoc} */
		public function pageConstructor() {
			$this->tf()->setVar('title', 'Money Tracker :: Transactions');

			$p = $this->getParams();
			if (!isset($p['sub']) || empty($p['sub'])) {
				$this->redirectTo('transactions');
			}
		}

		/** {@inheritDoc} */
		public function displayPage() {
			$db = $this->tf()->getVar('db', null);

			$p = $this->getParams();
			$tag = $p['sub'];
			$accounts = $db->getAccounts();
			foreach ($accounts as $acct) {
				$old = $acct->getTransactions();
				$acct->clearTransactions();
				foreach ($old as $t) {
					foreach ($t->getTags() as $ttag) {
						if ($ttag[0] == $tag) {
							$acct->addTransaction($t);
							break;
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

			$params = $this->getQuery();
			$this->tf()->setVar('hideEmpty', isset($params['period']));
			$periodInput = isset($params['period']) ? $params['period'] : 'thisyear';
			list($period, $start, $end) = getPeriod($periodInput);
			$this->tf()->setVar('start', $start);
			$this->tf()->setVar('end', $end);
			$this->tf()->setVar('period', $period);

			$this->tf()->setVar('tags', $tags);
			$this->tf()->setVar('jsontags', $jsontags);
			$this->tf()->setVar('accounts', $accounts);
			$this->tf()->setVar('filtered', true);
			$this->tf()->get('transactions')->display();
		}
	}
?>
