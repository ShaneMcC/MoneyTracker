<?php
	class home_page extends page {

		/** {@inheritDoc} */
		public function pageConstructor() {
			$this->tf()->setVar('title', 'Money Tracker');

			$q = $this->getQuery();
			$showHiddenAccounts = isset($q['showHiddenAccounts']) ? parseBool($q['showHiddenAccounts']) : false;
			$this->tf()->setVar('showHiddenAccounts', $showHiddenAccounts);
		}

		public function doHeaders() {
			$dbmap = $this->tf()->getVar('db', null);

			$params = $this->getQuery();
			$accountAction = isset($params['accountaction_action']) ? $params['accountaction_action'] : false;
			$accountActionID = isset($params['accountaction_id']) ? $params['accountaction_id'] : false;
			$accountActionValue = isset($params['accountaction_value']) ? $params['accountaction_value'] : false;

			if ($accountAction !== false) {
				if ($accountAction == 'editDescription') {
					$dbmap->getDB()->accounts->where('accountkey', $accountActionID)->update(array('description' => $accountActionValue));
				} else if ($accountAction == 'clearDescription') {
					$dbmap->getDB()->accounts->where('accountkey', $accountActionID)->update(array('description' => ''));
				} else if ($accountAction == 'toggleHide') {
					$dbmap->getDB()->accounts->where('accountkey', $accountActionID)->update(array('hidden' => $accountActionValue));
				}

				$this->redirectTo('home');
			}
		}

		/** {@inheritDoc} */
		public function displayPage() {
			$db = $this->tf()->getVar('db', null);

			$accounts = $db->getAccounts(false);
			$this->tf()->setVar('accounts', $accounts);

			$this->tf()->get('home')->display();
		}
	}
?>
