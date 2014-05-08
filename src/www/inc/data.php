<?php
	class data_page extends page {

		/** {@inheritDoc} */
		public function pageConstructor() {
			$this->tf()->setVar('title', 'Money Tracker :: Data');
		}

		/** {@inheritDoc} */
		public function displayPage() {
			$db = $this->tf()->getVar('db', null);
			$t = new TaggedTransactions($db);

			$params = $this->getQuery();

			if (isset($params['tags'])) { $t->tagOnly(); }
			else { $t->catOnly(); }


			if (isset($params['period']) && $params['period'] == 'this') {
				$period = 'This Month';
				$start = mktime(0, 0, 0, date("m"), 1, date("Y"));
				$end = time();
			} else if (isset($params['period']) && $params['period'] == 'last') {
				$period = 'Previous Month';
				$start = mktime(0, 0, 0, date("m")-1, 1, date("Y"));
				$end = mktime(0, 0, 0, date("m"), 1, date("Y"));
			} else if (isset($params['period']) && $params['period'] == '2last') {
				$period = '2 months ago';
				$start = mktime(0, 0, 0, date("m")-2, 1, date("Y"));
				$end = mktime(0, 0, 0, date("m")-1, 1, date("Y"));
			} else if (isset($params['period']) && $params['period'] == '3last') {
				$period = '3 months ago';
				$start = mktime(0, 0, 0, date("m")-3, 1, date("Y"));
				$end = mktime(0, 0, 0, date("m")-2, 1, date("Y"));
			} else if (isset($params['period']) && $params['period'] == 'last2') {
				$period = 'Last 2 months';
				$start = mktime(0, 0, 0, date("m")-2, 1, date("Y"));
				$end = mktime(0, 0, 0, date("m"), 1, date("Y"));
			} else if (isset($params['period']) && $params['period'] == 'last3') {
				$period = 'Last 3 months';
				$start = mktime(0, 0, 0, date("m")-3, 1, date("Y"));
				$end = mktime(0, 0, 0, date("m"), 1, date("Y"));
			} else {
				$period = 'Last 7 days';
				$start = strtotime('-7 days 00:00:00');
				$end = time();
			}

			$this->tf()->setVar('start', $start);
			$this->tf()->setVar('end', $end);
			$this->tf()->setVar('period', $period);
			$t->start($start)->end($end);


			$t2 = clone $t;
			$this->tf()->setVar('incoming', $t->incoming()->get());
			$this->tf()->setVar('outgoing', $t2->outgoing()->get());

			$this->tf()->get('data')->display();
		}
	}
?>
