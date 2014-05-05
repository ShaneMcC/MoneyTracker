<?php
	class deletetag_page extends page {

		/** {@inheritDoc} */
		public function showChrome() { return false; }

		/** {@inheritDoc} */
		public function displayPage() {
			$dbmap = $this->tf()->getVar('db', null);

			$params = $this->getQuery();
			$transaction = isset($params['transaction']) ? $params['transaction'] : false;

			if ($transaction !== false) {
				$transaction = $dbmap->getTransaction($transaction);
			}

			$tag = isset($params['tagid']) ? $params['tagid'] : false;

			$tags = array();
			foreach ($dbmap->getAllTags() as $t) {
				$tags[$t['tagid']] = $t['category'] . ' :: ' . $t['tag'];
			}

			if ($transaction === false) {
				echo 'Error';
				return;
			} else if ($tag !== false && isset($tags[$tag])) {
				$dbmap->deleteTransactionTag($transaction, $tag);
			}

			echo getTagHTML($transaction, $tags);
		}
	}
?>