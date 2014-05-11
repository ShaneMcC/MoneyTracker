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

			if (isset($params['tags']) || isset($params['cat'])) { $t->tagOnly(); }
			else { $t->catOnly(); }


			list($period, $start, $end) = getPeriod(isset($params['period']) ? $params['period'] : '');

			$this->tf()->setVar('start', $start);
			$this->tf()->setVar('end', $end);
			$this->tf()->setVar('period', $period);
			$t->start($start)->end($end);

			$t2 = clone $t;
			$data = array();
			$data['incoming'] = $t->incoming()->get();
			$data['outgoing'] = $t2->outgoing()->get();

			$chart = array();
			foreach ($data as $type => $d) {
				$chart[$type] = array('total' => 0, 'data' => array());
				$chart[$type]['data'][] = array('Category', 'Amount');
				$chart[$type]['metadata'] = array();
				foreach ($d as $row) {
					if (isset($params['cat']) && !empty($params['cat']) && $row['catid'] != $params['cat']) { continue; }

					$name = $row['cat'] . (isset($row['tag']) ? ' :: ' . $row['tag'] : '');
					$amount = $row['value'];
					$chart[$type]['total'] += $amount;

					$chart[$type]['data'][] = array($name, abs((float)$amount));
					$metadata['catid'] = $row['catid'];
					if (isset($row['tagid'])) {
						$metadata['tagid'] = $row['tagid'];
					}
					$chart[$type]['metadata'][] = $metadata;
				}
			}

			$this->tf()->setVar('chart', $chart);
			$this->tf()->get('data')->display();
		}
	}
?>
