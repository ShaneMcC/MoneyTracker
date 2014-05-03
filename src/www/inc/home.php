<?php
	class home_page extends page {

		/** {@inheritDoc} */
		public function pageConstructor() {
			$this->tf()->setVar('title', 'Money Tracker');
		}

		/** {@inheritDoc} */
		public function displayPage() {
			$this->tf()->get('home')->display();
		}
	}
?>