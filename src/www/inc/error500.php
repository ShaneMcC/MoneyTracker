<?php
	class error500_page extends page {

		/** {@inheritDoc} */
		public function pageConstructor() {
			$this->tf()->setVar('title', 'Money Tracker :: Error');
		}

		/** {@inheritDoc} */
		public function doHeaders() {
			header($_SERVER["SERVER_PROTOCOL"]." 500 Internal Server Error");
		}

		/** {@inheritDoc} */
		public function displayPage() {
			$this->tf()->get('error500')->display();
		}
	}
?>