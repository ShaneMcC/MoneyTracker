<?php
	class transactions_page extends page {

		/** {@inheritDoc} */
		public function pageConstructor() {
			$this->tf()->setVar('title', 'Money Tracker :: Transactions');
		}

		/** {@inheritDoc} */
		public function displayPage() {
			$db = $this->tf()->getVar('db', null);

			$p = $this->getParams();
			if (isset($p['sub']) && !empty($p['sub'])) {
				$accounts = array($db->getAccount($p['sub']));
			} else {
				$accounts = $db->getAccounts();
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

			$this->tf()->get('transactions')->display();
		}
	}
?>
