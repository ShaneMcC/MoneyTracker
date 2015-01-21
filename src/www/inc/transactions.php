<?php
	class transactions_page extends page {

		/** {@inheritDoc} */
		public function pageConstructor() {
			$this->tf()->setVar('title', 'Money Tracker :: Transactions');
			$this->tf()->setVar('showPeriods', true);
			$q = $this->getQuery();
			$this->tf()->setVar('thisPeriod', isset($q['period']) ? $q['period'] : 'last7days');

			// Prepare the sidebar menu.
			$sidebar = $this->tf()->getVar('sidebar');
			$section = array('__HEADER__' => 'Transactions');
			$section[] = array('Title' => 'Show all', 'Icon' => 'home', 'Link' => $this->getNewPageLink('', array('untagged' => null)), 'Active' => (!isset($q['untagged'])));
			$section[] = array('Title' => 'Show only untagged', 'Icon' => 'home', 'Link' => $this->getNewPageLink('', array('untagged' => true)), 'Active' => (isset($q['untagged'])));
			$sidebar[] = $section;
			$this->tf()->setVar('sidebar', $sidebar);
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

			$params = $this->getQuery();
			$this->tf()->setVar('hideEmpty', isset($params['period']));
			$periodInput = isset($params['period']) ? $params['period'] : 'last7days';
			list($period, $start, $end) = getPeriod($periodInput);
			$this->tf()->setVar('start', $start);
			$this->tf()->setVar('end', $end);
			$this->tf()->setVar('period', $period);
			$this->tf()->setVar('periodid', $periodInput);

			$this->tf()->setVar('tags', $tags);
			$this->tf()->setVar('jsontags', $jsontags);
			$this->tf()->setVar('accounts', $accounts);

			$this->tf()->setVar('onlyUntagged', isset($params['untagged']));
			$this->tf()->setVar('filtered', isset($params['untagged']));
			$this->tf()->get('transactions')->display();
		}
	}
?>
