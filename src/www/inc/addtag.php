<?php
	class addtag_page extends page {

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
			$value = isset($params['value']) ? $params['value'] : false;

			$tags = array();
			foreach ($dbmap->getAllTags() as $t) {
				$tags[$t['tagid']] = $t['category'] . ' :: ' . $t['tag'];
			}

			if ($transaction === false) {
				echo 'Error';
				return;
			} else if ($tag !== false && isset($tags[$tag]) && $value !== false) {
				$dbmap->addTransactionTag($transaction, $tag, $value);
			}

			echo getTagHTML($transaction, $tags);
		}
	}
?>