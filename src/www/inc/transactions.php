<?php
	class transactions_page extends page {

		/** {@inheritDoc} */
		public function pageConstructor() {
			$this->tf()->setVar('title', 'Money Tracker :: Transactions');
			$this->tf()->setVar('showPeriods', true);
			$q = $this->getQuery();

			$period = isset($q['period']) ? $q['period'] : '';
			list($name, $start, $end) = getPeriod($period);
			$this->tf()->setVar('periodName', $name);
			$this->tf()->setVar('start', $start);
			$this->tf()->setVar('end', $end);
			$this->tf()->setVar('period', $period);
			$this->tf()->setVar('thisPeriod', $period);

			$showHiddenAccounts = isset($q['showHiddenAccounts']) ? parseBool($q['showHiddenAccounts']) : false;
			$this->tf()->setVar('showHiddenAccounts', $showHiddenAccounts);

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

			$params = $this->getQuery();

			$period = $this->tf()->getVar('period');
			$start = $this->tf()->getVar('start');
			$end = $this->tf()->getVar('end');

			$this->tf()->setVar('hideEmpty', isset($params['period']));

			$p = $this->getParams();
			$singleAccount = false;
			if (isset($p['sub']) && !empty($p['sub'])) {
				$singleAccount = true;
				$accounts = array($db->getAccount($p['sub'], $start));
				$this->tf()->setVar('wantedAccount', $p['sub']);
			} else {
				$accounts = $db->getAccounts(true, $start);
				$this->tf()->setVar('wantedAccount', '');
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
			$this->tf()->setVar('filtered', isset($params['untagged']));
			$this->tf()->get('transactions')->display();

			if ($singleAccount && count($accounts) > 0) {
				$this->tf()->setVar('account', $accounts[0]);
				$this->tf()->get('chart_balance')->display();
			}
		}
	}
?>
