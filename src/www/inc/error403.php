<?php
	class error403_page extends page {

		/** {@inheritDoc} */
		public function pageConstructor() {
			$this->tf()->setVar('title', 'Money Tracker :: Error');
		}

		/** {@inheritDoc} */
		public function doHeaders() {
			header($_SERVER["SERVER_PROTOCOL"]." 403 Forbidden");
		}

		/** {@inheritDoc} */
		public function displayPage() {
			$this->tf()->get('error403')->display();
		}
	}
?>