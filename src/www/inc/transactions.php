<?php
	class transactions_page extends page {

		/** {@inheritDoc} */
		public function pageConstructor() {
			$this->tf()->setVar('title', 'Money Tracker :: Transactions');
		}

		/** {@inheritDoc} */
		public function displayPage() {
			$dbmap = $this->tf()->getVar('db', null);
			$accounts = $dbmap->getAccounts();

			$tags = array();
			$jsontags = array();
			foreach ($dbmap->getAllTags() as $t) {
				$tags[$t['tagid']] = $t['category'] . ' :: ' . $t['tag'];
				$jsontags[$t['category']][$t['tag']] = $t['tagid'];
			}

			$this->tf()->setVar('tags', $tags);
			$this->tf()->setVar('jsontags', $jsontags);
			$this->tf()->setVar('accounts', $accounts);

			$this->tf()->get('transactions')->display();
		}
	}
?>