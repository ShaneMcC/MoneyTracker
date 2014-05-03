<?php

	/**
	 * Session management class
	 */
	class session {
		private static $user = null;
		private static $hasInit = false;
		private static $storedVars = array();

		/**
		 * Get the user object for the current session.
		 *
		 * @return The user object for the current session.
		 */
		static function getCurrentUser() {
			return self::$user;
		}

		/**
		 * Set the user object for the current session.
		 *
		 * @param $user The user object for the current session.
		 */
		static function setCurrentUser($user) {
			self::$user = $user;

			session::save();
		}

		/**
		 * Get the user object for the current session.
		 *
		 * @return True if there is a user account in this session
		 */
		static function isLoggedIn() {
			return (self::getCurrentUser() != null);
		}

		/**
		 * Initialise this session.
		 */
		static function init($name = null) {
			if (self::$hasInit) { return; }
			self::$hasInit = true;

			session_name($name == null ? 'web-'.crc32(__FILE__) : $name);
			session_start();

			self::$user = isset($_SESSION['session::user']) ? unserialize($_SESSION['session::user']) : '';
			self::$storedVars = isset($_SESSION['session::storedVars']) ? unserialize($_SESSION['session::storedVars']) : array();
		}

		/**
		 * Clear this session.
		 */
		static function clear() {
			self::$user = null;
			self::$storedVars = array();
			session::save();
		}

		/**
		 * Store a variable in this session. This will overwrite any existing
		 * variable with the same name.
		 *
		 * @param $variable Variable name
		 * @param $value Value to store
		 * @param $save (Default: true) Automatically call session::save?
		 */
		static function set($variable, $value, $save = true) {
			self::$storedVars[$variable] = $value;

			if ($save) {
				session::save();
			}
		}

		/**
		 * Store a variable in this session. This will turn the variable into
		 * an array if it is not already one, and then append a new element.
		 *
		 * @param $variable Variable name
		 * @param $value Value to store
		 * @param $save (Default: true) Automatically call session::save?
		 */
		static function append($variable, $value, $save = true) {
			if (!isset(self::$storedVars[$variable])) {
				self::$storedVars[$variable] = array();
			} else if (!is_array(self::$storedVars[$variable])) {
				self::$storedVars[$variable] = array(self::$storedVars[$variable]);
			}
			self::$storedVars[$variable][] = $value;

			if ($save) {
				session::save();
			}
		}

		/**
		 * Check to see if a variable is stored in this session under a given name.
		 *
		 * @param $variable Variable name
		 */
		static function exists($variable) {
			return isset(self::$storedVars[$variable]);
		}

		/**
		 * Get a variable stored in this session.
		 *
		 * @param $variable Variable name
		 * @param $fallback Fallback value if value is not found.
		 */
		static function get($variable, $fallback = null) {
			return isset(self::$storedVars[$variable]) ? self::$storedVars[$variable] : $fallback;
		}

		/**
		 * Remove a stored variable from this session.
		 *
		 * @param $variable Variable name
		 * @param $save (Default: true) Automatically call session::save?
		 */
		static function remove($variable, $save = true) {
			unset(self::$storedVars[$variable]);

			if ($save) {
				session::save();
			}
		}

		/**
		 * Save this session.
		 *
		 * This should be called whenever there are changes to the session state,
		 * otherwise they may be lost.
		 */
		static function save() {
			$_SESSION['session::user'] = serialize(self::$user);
			$_SESSION['session::storedVars'] = serialize(self::$storedVars);
		}
	}

	session::init();

?>