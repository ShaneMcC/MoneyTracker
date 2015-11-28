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
				$fluid = $this->tf()->getVar('fluid', false);


				// TODO: This bit needs to be templated better.
				if (session::isLoggedIn() && ($this->tf()->getVar('showSidebar', true) || $this->tf()->getVar('showPeriods', false))) {
					if ($fluid) {
						echo '<div style="width: 270px; float: left">';
					} else {
						echo '<div class="col-sm-' . $sidebarWidth . '">';
					}
					$this->tf()->get('sidebar')->display();
					echo '</div>';

					if ($fluid) {
						echo '<div id="mainContentContainer" style="padding-left: 30px; float: left; width: calc(100% - 270px);">';
					} else {
						echo '<div class="col-sm-' . (12 - $sidebarWidth). '">';
					}
				} else {
					echo '<div class="col-sm-12">';
				}

				// echo '<div class="', ($fluid ? 'container-fluid' : 'container'), '" role="main" style="padding-right: 0px">';
				echo '<div class="container-fluid" role="main" style="padding-right: 0px; padding-left: 0px">';
				echo '<div class="row">';

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
				echo '</div>';
				echo '</div>';

				?><script>
					var cssCalcTestElement = document.createElement('div');
					cssCalcTestElement.style.cssText = 'width: calc(1px);';
					if (cssCalcTestElement.style.length < 1) {
						// Calc not supported, hack it.
						$(window).resize(function(){
							$("#mainContentContainer").css("width", "100%").css("width", "-=270px");
						});
						$(window).resize();
					}
				</script><?php

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
		 * Maniuplate the current location.
		 */
		public function getNewPageLink($page = '', $query = array()) {
			if (empty($page)) {
				$url = $_SERVER['REDIRECT_URL'];
			} else {
				$url = page::getWebLocation();
				$url .= $page;
			}

			if ($query !== FALSE) {
				$q = $this->getQuery();
				$q = array_merge((is_array($q) ? $q : array()), $query);
				if (count($q) > 0) {
					$url .= '?';
					$url .= http_build_query($q);
				}
			}

			return $url;
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
			die('Redirect: <a href='.$loc.'>'.$loc.'</a>');
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
