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
		 * @param $historicalLimit (Default: 0) How far back in time to go.
		 * @return accounts associated with this login.
		 */
		abstract function getAccounts($useCached = true, $transactions = false, $historical = false, $historicalVerbose = false, $historicalLimit = 0);

		/**
		 * Update the transactions on the given account object.
		 *
		 * @param $account Account to update.
		 * @param $historical (Default: false) Also try to get historical
		 *                    transactions?
		 * @param $historicalVerbose (Default: false) Should verbose data be
		 *                           collected for historical, or is a single-line
		 *                           description ok?
		 * @param $historicalLimit (Default: 0) How far back in time to go.
		 */
		abstract function updateTransactions($account, $historical = false, $historicalVerbose = true, $historicalLimit = 0);

		protected function getPermDataName() { return $this->__toString(); }

		protected function getPermDataFile() {
			$reflector = new ReflectionObject($this);
			return dirname($reflector->getFileName()) . '/.permdata-' . str_replace('/', '_', $this->getPermDataName());
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

	class ScraperException extends Exception { }

	abstract class WebBank extends Bank {
		protected $browser = null;
		protected $cachedDNS = array();

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
			$this->browser->setGetHostAddr(function ($host) { return $this->cacheResolveAddress($host); });
		}

		/**
		 * This function will resolve addresses once, and then remember them
		 * for the lifetime of the object. This gets around some fucky and
		 * broken load-balancers.
		 *
		 * @param $host Host to look up
		 * @return Address to connect to.
		 */
		protected function cacheResolveAddress($host) {
			if (!isset($this->cachedDNS[strtolower($host)])) {
				// Resolve once, remember forever.
				$this->cachedDNS[strtolower($host)] = gethostbyname($host);
			}

			return $this->cachedDNS[strtolower($host)];
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
			$this->handleRedirects($page);
			if (!$justGet && !$this->isLoggedIn($page)) {
				if (!$this->login()) { return false; }
				$page = $this->browser->get($url);
				$this->handleRedirects($page);
			}
			return $page;
		}

		abstract function login($fresh = false);
		abstract function isLoggedIn($page);

		/**
		 * Check the page to see if any redirects are required and handle them
		 * if they are.
		 *
		 * Used to emulate javascript redirects and the like. This function
		 * should loop internally until all redirects have been followed.
		 *
		 * @param &$page Page data. This should be updated after the redirects.
		 */
		public function handleRedirects(&$page) { }

		/**
		 * Debugging function to output data from the most recent request.
		 */
		public function dumpRequestData() {
			echo '==[REQUEST]===========================',"\n";
			print_r($this->browser->getRequest());
			echo "\n";
			echo '==[HEADERS]===========================',"\n";
			print_r($this->browser->getHeaders());
			echo "\n";
			echo '==[RESPONSE]===========================',"\n";
			print_r($this->browser->getContent());
			echo "\n";
			echo '=======================================',"\n";
		}

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
