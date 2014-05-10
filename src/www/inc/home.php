<?php
	class home_page extends page {

		/** {@inheritDoc} */
		public function pageConstructor() {
			$this->tf()->setVar('title', 'Money Tracker');
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
