<?php
	class tags_page extends page {

		/** {@inheritDoc} */
		public function pageConstructor() {
			$this->tf()->setVar('title', 'Money Tracker :: Tags');
		}

		public function doHeaders() {
			$dbmap = $this->tf()->getVar('db', null);

			$params = $this->getQuery();
			$tagAction = isset($params['tagaction_action']) ? $params['tagaction_action'] : false;
			$tagActionID = isset($params['tagaction_id']) ? $params['tagaction_id'] : false;
			$tagActionValue = isset($params['tagaction_value']) ? $params['tagaction_value'] : false;

			if ($tagAction !== false) {
	 			if ($tagAction == 'editTag') {
					$dbmap->getDB()->tags->where('id', $tagActionID)->update(array('tag' => $tagActionValue));
				} else if ($tagAction == 'deleteTag') {
					$dbmap->getDB()->tags->where('id', $tagActionID)->delete();
				} else if ($tagAction == 'addTag') {
					$dbmap->getDB()->tags->insert(array('category' => $tagActionID, 'tag' => $tagActionValue));
				} else if ($tagAction == 'addCategory') {
					$foo = $dbmap->getDB()->categories->insert(array('name' => $tagActionValue));
				} else if ($tagAction == 'editCategory') {
					$dbmap->getDB()->categories->where('id', $tagActionID)->update(array('name' => $tagActionValue));
				} else if ($tagAction == 'deleteCategory') {
					$dbmap->getDB()->categories->where('id', $tagActionID)->delete();
				}

				$this->redirectTo('tags');
			}
		}

		/** {@inheritDoc} */
		public function displayPage() {
			$dbmap = $this->tf()->getVar('db', null);

			$tags = array();
			foreach ($dbmap->getAllTags(true) as $t) {
				if ($t['tagid'] != null) {
					$tags[$t['categoryid']]['tags'][$t['tag']] = $t['tagid'];
				} else {
					$tags[$t['categoryid']]['tags'] = array();
				}
				$tags[$t['categoryid']]['name'] = $t['category'];
			}

			$this->tf()->setVar('tags', $tags);
			$this->tf()->get('tags')->display();
		}
	}
?>