<?php
	/**
	 * Basic templating engine, in the future this may wrap something better,
	 * for now this will do.
	 */
	class Templater {
		/** Directory where templates are stored. */
		private $dir;
		private $compiledDir;
		private $factory;
		private $vars = array();
		private $file;
		private $output;
		private $webdir = '';
		private $parserVersion = '1';

		public function __construct(TemplateFactory $factory) {
			$this->factory = $factory;

			$dir = $this->factory->getDir();
			$theme = $this->factory->getTheme();
			$compiledDir = $this->factory->getCompiledDir();

			if ($dir === null) {
				$dir = realpath(dirname(__FILE__) . '/../') . '/templates/';
			}
			if ($compiledDir === null) {
				$compiledDir = rtrim($dir, '/') . '_c/';
			}

			$this->dir = $dir;
			$this->webdir = preg_replace('#^' . preg_quote(realpath(dirname(__FILE__) . '/../') . '/') . '#', '', $dir);
			$this->theme = $theme;

			$this->compiledDir = $compiledDir;
			if (!file_exists($compiledDir)) {
				@mkdir($compiledDir);
			}
		}

		public function getLocation($file, $web = false) {
			$dir = $web ? $this->webdir : $this->dir;
			$f = '';

			if (!empty($file)) {
				$f = sprintf('/%s/%s', $this->theme, $file);
				if (!file_exists($this->dir . $f)) {
					$f = sprintf('/default/%s', $file);
				}
			}

			if ($web) {
				$result = $this->hasVar('webdir') ? $this->getVar('webdir') . '/' : '';
				if (!empty($file)) {
					$result .= $this->webdir;
					$result .= $f;
				}
			} else {
				$result = $this->dir . $f;
			}

			return preg_replace('#/+#', '/', $result);
		}

		public function getWebLocation($file = '') {
			return $this->getLocation($file, true);
		}

		public function getNewPageLink($page = '', $query = array()) {
			if (empty($page)) {
				$url = $_SERVER['REDIRECT_URL'];
			} else {
				$url = $this->getWebLocation();
				$url .= $page;
			}

			if ($query !== FALSE) {
				$p = $this->getVar('params');
				if (isset($p['query'])) {
					parse_str($p['query'], $q);
					$q = array_merge((is_array($q) ? $q : array()), $query);
				} else {
					$q = $query;
				}
				if (count($q) > 0) {
					$url .= '?';
					$url .= http_build_query($q);
				}
			}

			return $url;
		}

		public function ca($type, $page) {
			if ($type == 'page' && $this->getVar('__pagename', FALSE) === $page) {
				echo ' class="active" ';
			} else if ($type == 'group' && $this->getVar('__groupname', FALSE) === $page) {
				echo ' class="active" ';
			}
		}

		public function loadTemplate($template) {
			$this->file = $template;

			$f = $this->getLocation($template . '.tpl');

			if (!file_exists($f)) {
				throw new Exception('Unable to find template: ' . $template);
			}

			return $this;
		}

		public function setVar($name, $value) {
			$this->vars[$name] = $value;

			return $this;
		}

		public function getVar($name, $fallback = null) {
			return isset($this->vars[$name]) ? $this->vars[$name] : $fallback;
		}

		public function hasVar($name) {
			return isset($this->vars[$name]);
		}

		public function display() {
			if (empty($this->output)) {
				$this->parse();
			}

			echo $this->output;
		}

		public function e($string, $escape = true) {
			if ($escape) {
				echo htmlspecialchars($string);
			} else {
				echo $string;
			}
		}

		private function getCompiledName($file) {
			$compiledName = $file;
			$compiledName = preg_replace('#^' . preg_quote(realpath(dirname(__FILE__) . '/../') . '/') . '#', '', $file);
			$compiledName = str_replace('/', '__', $compiledName);

			$compiledName = $this->compiledDir . '/' . $this->parserVersion .  '-' . abs(crc32($file)) . '-' . $compiledName;

			return $compiledName;
		}

		private function parse() {
			$file = $this->getLocation($this->file . '.tpl');

			$compiledName = $this->getCompiledName($file);
			if (!file_exists($compiledName) || filemtime($compiledName) <= filemtime($file)) {
				$contents = file_get_contents($file);

				// This bit originally comes from "Templum" at
				// http://templum.electricmonk.nl/About/
				// modified a bit as required.
				//
				// This is sufficient for now.
				$contents = preg_replace(
				            array("/{{(.+?)(`?)}}/", // Echo Variable
				                  /* "/\[\[/", // Start of PHP Block */
				                  /* "/\]\]/", // End of PHP Block */
				                  "/\[{([^(]*)(\((.*)\))?}\]/U",   // [{ }] - eval
				                  "/{\[([^(]*)(\((.*)\))?\]}/U",   // {[ ]} - eval and echo
				                  "/({--.*--})/Ums",   // Comment
				                  '/^\s*@(.*)$/mU'  // Full-Line PHP
				                 ),
				            array("<?php \$this->e(\\1, ('' === '\\2')); ?>",
				                  /* "<?php ", */
				                  /* " ?>", */
				                  "<?php if (method_exists(\$this, '\\1')) { \$this->\\1(\\3); } else { \\1(\\3); } ?>",
				                  "<?php if (method_exists(\$this, '\\1')) { \$this->e(\$this->\\1(\\3)); } else { \$this->e(\\1(\\3)); } ?>",
				                  "",
				                  "<?php \\1 ?>"
				                 ),
				            $contents);

				if (@file_put_contents($compiledName, $contents) !== FALSE) {
					$contents = '';
				}
			}

			ob_start();
			foreach ($this->vars as $k => $v) {
				if (!isset($$k)) {
					$$k = $v;
				}
			}

			if (empty($contents)) {
				include $compiledName;
			} else {
				eval('?>' . $contents);
			}
			$this->output = ob_get_contents();
			ob_end_clean();
		}
	}

	class TemplateFactory {
		/** Directory where templates are stored. */
		private $dir;
		private $theme;
		private $compiledDir;
		private $vars = array();

		public function __construct($dir = null, $theme = 'default', $compiledDir = null) {
			if ($dir === null) {
				$dir = dirname(__FILE__) . '/templates/';
			}

			$this->dir = $dir;
			$this->theme = $theme;
			$this->compiledDir = $compiledDir;
		}

		public function get($template = '') {
			$t = new Templater($this);

			if (!empty($template)) {
				$t->loadTemplate($template);
			}

			foreach ($this->vars as $k => $v) {
				$t->setVar($k, $v);
			}

			return $t;
		}

		public function setVar($name, $value) {
			$this->vars[$name] = $value;

			return $this;
		}

		public function getVar($name, $fallback = null) {
			return isset($this->vars[$name]) ? $this->vars[$name] : $fallback;
		}

		public function hasVar($name) {
			return isset($this->vars[$name]);
		}

		public function getDir() { return $this->dir; }
		public function getTheme() { return $this->theme; }
		public function getCompiledDir() { return $this->compiledDir; }
	}
?>
