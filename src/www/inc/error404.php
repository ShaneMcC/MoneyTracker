<?php
	class error404_page extends page {

		/** {@inheritDoc} */
		public function pageConstructor() {
			$this->tf()->setVar('title', 'Money Tracker :: Error');
		}

		/** {@inheritDoc} */
		public function doHeaders() {
			header($_SERVER["SERVER_PROTOCOL"]." 404 Not Found");
		}

		/** {@inheritDoc} */
		public function displayPage() {
			$this->tf()->get('error404')->display();
		}
	}
?>