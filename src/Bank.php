<?php
	abstract class Bank {
		protected $permdata = array();

		/**
		 * Get the sub-accounts of this object.
		 * This will return cached account objects.
		 *
		 * @param $useCached (Default: true) Return cached values if possib;e?
		 * @param $transactions (Default: false) Also update transactions?
		 *                      (This will force a reload of the accounts only if
		 *                       none of them have any associated transactions)
		 * @param $historical (Default: false) Also try to get historical
		 *                    transactions (not applicable to all Banks)
		 * @param $historicalVerbose (Default: false) Should verbose data be
		 *                           collected for historical, or is a single-line
		 *                           description ok? (not applicable to all Banks)
		 * @return accounts associated with this login.
		 */
		abstract function getAccounts($useCached = true, $transactions = false, $historical = false, $historicalVerbose = false);

		/**
		 * Update the transactions on the given account object.
		 *
		 * @param $account Account to update.
		 * @param $historical (Default: false) Also try to get historical
		 *                    transactions?
		 * @param $historicalVerbose (Default: false) Should verbose data be
		 *                           collected for historical, or is a single-line
		 *                           description ok?
		 */
		abstract function updateTransactions($account, $historical = false, $historicalVerbose = true);

		protected function getPermDataFile() {
			$reflector = new ReflectionObject($this);
			return dirname($reflector->getFileName()) . '/.permdata-' . str_replace('/', '_', $this->__toString());
		}

		protected function savePermData() {
			file_put_contents($this->getPermDataFile(), serialize($this->permdata));
		}

		protected function loadPermData() {
			if (!file_exists($this->getPermDataFile())) { return; }
			$this->permdata = unserialize(file_get_contents($this->getPermDataFile()));
		}

		public function __construct() { }
	}

	abstract class WebBank extends Bank {
		protected $browser = null;

		public function __construct() { parent::__construct(); }

		/**
		 * Create a new Browser Object.
		 */
		protected function newBrowser($loadCookies = true) {
			global $__simpleSocketContext;
			$__simpleSocketContext = array();
			$this->browser = new SimpleBrowser();
			$this->browser->setParser(new SimplePHPPageBuilder());
			$this->browser->setUserAgent('Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:35.0) Gecko/20100101 Firefox/35.0');
			if ($loadCookies) {
				$this->loadCookies();
			}
		}

		protected function getCookieFile() {
			$reflector = new ReflectionObject($this);
			return dirname($reflector->getFileName()) . '/.cookies-' . str_replace('/', '_', $this->__toString());
		}

		protected function saveCookies() {
			$_SimpleBrowser = new ReflectionClass("SimpleBrowser");
			$_SimpleBrowser_user_agent = $_SimpleBrowser->getProperty("user_agent");
			$_SimpleBrowser_user_agent->setAccessible(true);

			$_SimpleUserAgent = new ReflectionClass("SimpleUserAgent");
			$_SimpleUserAgent_cookie_jar = $_SimpleUserAgent->getProperty("cookie_jar");
			$_SimpleUserAgent_cookie_jar->setAccessible(true);

			$useragent = $_SimpleBrowser_user_agent->getValue($this->browser);
			$cookie_jar = $_SimpleUserAgent_cookie_jar->getValue($useragent);

			file_put_contents($this->getCookieFile(), serialize($cookie_jar));
		}

		protected function loadCookies() {
			if (!file_exists($this->getCookieFile())) { return; }
			$_SimpleBrowser = new ReflectionClass("SimpleBrowser");
			$_SimpleBrowser_user_agent = $_SimpleBrowser->getProperty("user_agent");
			$_SimpleBrowser_user_agent->setAccessible(true);

			$_SimpleUserAgent = new ReflectionClass("SimpleUserAgent");
			$_SimpleUserAgent_cookie_jar = $_SimpleUserAgent->getProperty("cookie_jar");
			$_SimpleUserAgent_cookie_jar->setAccessible(true);

			$useragent = $_SimpleBrowser_user_agent->getValue($this->browser);
			$new_cookie_jar = unserialize(file_get_contents($this->getCookieFile()));
			$_SimpleUserAgent_cookie_jar->setValue($useragent, $new_cookie_jar);
		}

		/**
		 * Get the requested page, logging in if needed.
		 *
		 * @param $url URL of page to get.
		 * @param $justGet (Default: false) Just get the page, don't try to auth.
		 */
		protected function getPage($url, $justGet = false) {
			if ($this->browser == null) {
				if ($justGet) {
					$this->newBrowser();
				} else {
					// We need a new browser, so we're going to need to log in.
					if (!$this->login()) { return false; }
				}
			}

			$page = $this->browser->get($url);
			if (!$justGet && !$this->isLoggedIn($page)) {
				if (!$this->login()) { return false; }
				$page = $this->browser->get($url);
			}
			return $page;
		}

		abstract function login($fresh = false);
		abstract function isLoggedIn($page);

		/**
		 * Get a nice tidied and phpQueryed version of a html page.
		 *
		 * @param $html HTML to parse
		 * @return PHPQuery document from the tidied html.
		 */
		protected function getDocument($html) {
			$config = array('indent' => TRUE,
			                'wrap' => 0,
			                'output-xhtml' => true,
			                'clean' => true,
			                'numeric-entities' => true,
			                'char-encoding' => 'utf8',
			                'input-encoding' => 'utf8',
			                'output-encoding' => 'utf8',
			                );
			$tidy = tidy_parse_string($html, $config, 'utf8');
			$tidy->cleanRepair();
			$html = $tidy->value;
			return phpQuery::newDocument($html);
		}

		/**
		 * Clean up an element.
		 *
		 * @return a clean element as a string.
		 */
		protected function cleanElement($element) {
			if (method_exists($element, 'html')) {
				$out = $element->html();
			} else {
				$out = $element->nodeValue;
			}

			// Decode entities.
			// Handle the silly space first.
			$out = str_replace('&#160;', ' ', $out);
			$out = str_replace(html_entity_decode('&#160;'), ' ', $out);
			// Now the rest.
			$out = trim(html_entity_decode($out));

			// I don't remember why I did this, so for now I'll leave it out.
			// $out = trim(preg_replace('#[^\s\w\d-._/\\\'*()<>{}\[\]@&;!"%^]#i', '', $element->html()));

			return trim($out);
		}
	}
?>
