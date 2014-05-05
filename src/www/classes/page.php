<?php
	abstract class page {
		/** Title of this page */
		var $title = '';

		/** Parameters passed to this page. */
		private $params = array();

		/** Query string variables passed to this page. */
		private $query = array();

		/** Template Factory */
		private $templateFactory = null;

		public final function __construct($templateFactory, $params = array()) {
			$this->templateFactory = $templateFactory;

			// Extract query string.
			if (isset($params['query'])) {
				$query = array();
				$args = explode('&', $params['query']);
				foreach ($args as $a) {
					$bits = explode('=', $a, 2);
					$query[urldecode($bits[0])] = isset($bits[1]) ? urldecode($bits[1]) : '';
				}

				$this->query = $query;
				unset($params['query']);
			}

			// Now POST data.
			if (isset($params['_POST'])) {
				foreach ($params['_POST'] as $k => $v) {
					$this->query[$k] = $v;
				}
				unset($params['_POST']);
			}

			// Store params and call per-page constructor.
			$this->params = $params;
			$this->pageConstructor();
		}

		/**
		 * This method is called by the page constructor after the params
		 * variable has been parsed.
		 *
		 * This should not attempt to interact with the user at all.
		 */
		public function pageConstructor() { }

		/**
		 * This method is called to check if the current user is allowed access
		 * this page.
		 */
		public function checkAccess() { return true; }

		/**
		 * This method is called to initialise the page prior to it being used
		 * for anything, this should not be called before checkAccess().
		 *
		 * This is allowed to interact with the user.
		 */
		public function init() { }

		/**
		 * This method is called to show any headers required
		 * A return code of FALSE will cause no more output to be sent.
		 */
		public function doHeaders() { }

		/**
		 * This method is called to show the main part of the page.
		 */
		public function displayPage() { }

		/**
		 * If this returns false, no chrome will be shown.
		 */
		public function showChrome() { return true; }

		/**
		 * This method is called to show the page.
		 */
		public final function display() {
			if ($this->init()) { return; }
			if ($this->doHeaders() === false) { return; }

			$showChrome = $this->showChrome();

			if ($showChrome) {
				$this->tf()->get('header')->display();

				// How many grids wide is the sidebar?
				$sidebarWidth = $this->tf()->getVar('sidebarWidth', 3);

				// Forced sizes?
				$forceSize = $this->tf()->getVar('fluid', false);

				// TODO: This bit needs to be templated better.
				if (session::isLoggedIn() && $this->tf()->getVar('showSidebar', true)) {
					if ($forceSize) {
						echo '<div style="width: 270px; float: left">';
					} else {
						echo '<div class="span' . $sidebarWidth . '">';
					}
					$this->tf()->get('sidebar')->display();
					echo '</div>';

					if ($forceSize) {
						echo '<div style="margin-left: 300px">';
					} else {
						echo '<div class="span' . (12 - $sidebarWidth). '">';
					}
				} else {
					echo '<div class="span12">';
				}

				if (session::exists('message')) {
					$messages = session::get('message');
					foreach ($messages as $message) {
						$this->tf()->get('message')->setVar('message', $message)->display();
					}
					session::remove('message');
				}
			}

			$this->displayPage();

			if ($showChrome) {
				echo '</div>';

				$this->tf()->get('footer')->display();
			}
		}

		/**
		 * Get the template factory.
		 */
		public final function tf() {
			return $this->templateFactory;
		}

		/**
		 * Get a copy of the params array.
		 *
		 * @return a copy of the params array.
		 */
		public final function getParams() {
			return $this->params;
		}

		/**
		 * Figure out the web location.
		 */
		public static function getWebLocation() {
			// Horrible...
			$path = dirname($_SERVER['SCRIPT_FILENAME']) . '/';
			$path = preg_replace('#^' . preg_quote($_SERVER['DOCUMENT_ROOT']) . '#', '/', $path);
			$path = preg_replace('#^/+#', '/', $path);
			return $path;
		}

		/**
		 * Redirect to the given location.
		 *
		 * @param $location Location to redirect to.
		 */
		public final function redirectTo($location = '') {
			$loc = $this->getWebLocation() . '/' . $location;
			$loc = preg_replace('#/+#', '/', $loc);
			header('Location: ' . $loc);
			die();
		}

		/**
		 * Get a copy of the query string array.
		 *
		 * @return a copy of the query string array.
		 */
		public final function getQuery() {
			return $this->query;
		}

		/**
		 * Get the page class for the given page name, or null if there is no
		 * such page.
		 *
		 * @param $name Page to get instance of.
		 * @param $templateFactory Template Factory
		 * @param $params (Default: array()) Params to pass to page.
		 */
		public final static function getPage($name, $templateFactory, $params = array()) {
			// Remove nasties.
			$file = preg_split('#[/\\\]#', $name);
			$file = array_pop($file);
			// Find the file.
			$file = dirname(__FILE__) . '/../inc/' . $file . '.php';

			$page = null;

			$templateFactory->setVar('params', $params);

			// If the file exists, create the page.
			// Otherwise, throw together a basic 404.
			if (file_exists($file)) {
				include($file);
				$name = preg_replace('/[^A-Z0-9-_]/i', '', $name);
				eval('$page = new ' . $name . '_page($templateFactory, $params);');
			}

			return $page;
		}
	}

?>
