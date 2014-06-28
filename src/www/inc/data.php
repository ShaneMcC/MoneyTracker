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
			$cdata = array();

			foreach ($data as $type => $d) {
				$chart[$type] = array('total' => 0, 'data' => array());
				$cdata[$type] = array();
				$chart[$type]['charttype'] = 'PieChart';
				$chart[$type]['data'][] = array('Category', 'Amount');
				$chart[$type]['metadata'] = array();
				foreach ($d as $row) {
					if (isset($params['cat']) && !empty($params['cat']) && $row['catid'] != $params['cat']) { continue; }

					$name = '';
					if (!isset($params['cat'])) { $name .= $row['cat']; }
					if (isset($row['tag']) && !isset($params['cat'])) { $name .=  ' :: '; }
					if (isset($row['tag'])) { $name .= $row['tag']; };

					$amount = $row['value'];
					$chart[$type]['total'] += $amount;

					$chart[$type]['data'][] = array($name, abs((float)$amount));
					$cdata[$type][$name] = (float)$amount;

					$metadata['catid'] = $row['catid'];
					if (isset($row['tagid'])) {
						$metadata['tagid'] = $row['tagid'];
					}
					$chart[$type]['metadata'][] = $metadata;
				}
			}

			$chart['Combined'] = $chart['outgoing'];
			$chart['Combined']['total'] = 0;
			$chart['Combined']['charttype'] = 'ColumnChart';
			foreach ($chart['Combined']['data'] as $key => $row) {
				if (!is_numeric($row[1])) { continue; }
				$chart['Combined']['data'][$key][1] = 0 - $row[1];
				$chart['Combined']['charttype'] = 'ColumnChart';
				if (isset($cdata['incoming'][$row[0]])) {
					$chart['Combined']['data'][$key][1] += $cdata['incoming'][$row[0]];
				}

				$chart['Combined']['total'] += $chart['Combined']['data'][$key][1];
			}

			foreach ($chart['incoming']['data'] as $key => $row) {
				if (!is_numeric($row[1])) { continue; }
				if (!isset($cdata['outgoing'][$row[0]])) {
					$chart['Combined']['data'][] = $row;
					$chart['Combined']['metadata'][] = $chart['incoming']['metadata'][$key - 1];

					$chart['Combined']['total'] += $row[1];
				}
			}

			$chart['Combined']['data'] = $this->flip($chart['Combined']['data']);
			$chart['Combined']['metadata'] = $this->flip($chart['Combined']['metadata']);
			$chart['Combined']['hascolumns'] = true;

			$this->tf()->setVar('chart', $chart);
			$this->tf()->get('data')->display();
		}

		function flip($arr) {
			$out = array();

			foreach ($arr as $key => $subarr) {
				foreach ($subarr as $subkey => $subvalue) {
					$out[$subkey][$key] = $subvalue;
				}
			}

			return $out;
		}
	}
?>
