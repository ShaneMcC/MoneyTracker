<?php
	/**
	 * Main index page.
	 *
	 * This page handles requests for other pages, and farms them out to the
	 * appropriate classes.
	 *
	 * This also deals with some of the initial parsing of the environment.
	 */

	// Init the environment
	require_once(dirname(__FILE__) . '/init.php');

	$templateFactory->setVar('webdir', dirname($_SERVER['SCRIPT_NAME']));

	// Prepare to create a new page
	$page = null;
	$params = array();

	if (session::exists('nextparams')) {
		$params = session::get('nextparams');
		session::remove('nextparams');
	}

	// Have we been asked for a specific page?
	if (isset($_REQUEST['p']) && !empty($_REQUEST['p'])) {
		$inc = $_REQUEST['p'];
	} else {
		$inc = isset($params['inc']) ? $params['inc'] : 'index';
	}
	$params['inc'] = $inc;

	// What about a sub page?
	$sub = isset($params['sub']) ? $params['sub'] : '';
	$params['sub'] = isset($_REQUEST['s']) ? $_REQUEST['s'] : $sub;

	// And any query strings?
	if (isset($_SERVER['REQUEST_URI'])) {
		$bits = explode('?', $_SERVER['REQUEST_URI'], 2);
		if (isset($bits[1])) {
			$params['query'] = $bits[1];
		}
	}

	// Now POST data.
	if (isset($_POST)) {
		$params['_POST'] = $_POST;
	}

	// Some pages are handled specially, handle them here.
	if ($inc == 'index') {
		$inc = 'home';
	}

	// Prepare the sidebar menu.
	$sidebar = array();
	$section = array('__HEADER__' => 'Site');
	$section[] = array('Title' => 'Home', 'Icon' => 'home', 'Link' => page::getWebLocation() . 'home', 'Active' => ($inc == 'home'));
	// $section[] = array('Title' => 'Debug Page', 'Icon' => 'warning-sign', 'Link' => page::getWebLocation() . 'debug.php');
	$section[] = array('Title' => 'Transactions', 'Icon' => 'transfer', 'Link' => page::getWebLocation() . 'transactions', 'Active' => ($inc == 'transactions'));
	$section[] = array('Title' => 'Tags', 'Icon' => 'tasks', 'Link' => page::getWebLocation() . 'tags', 'Active' => ($inc == 'tags'));
	$sidebar[] = $section;

	$section = array('__HEADER__' => 'Data');
	// $section[] = array('Title' => 'Data', 'Icon' => 'stats', 'Link' => page::getWebLocation() . 'data', 'Active' => ($inc == 'data'));
	$section[] = array('Title' => 'Last 7 days', 'Icon' => 'stats', 'Link' => page::getWebLocation() . 'data');
	$section[] = array('Title' => 'This month', 'Icon' => 'stats', 'Link' => page::getWebLocation() . 'data?period=this');
	$section[] = array('Title' => 'Last month', 'Icon' => 'stats', 'Link' => page::getWebLocation() . 'data?period=last');
	$section[] = array('Title' => 'Last 2 months', 'Icon' => 'stats', 'Link' => page::getWebLocation() . 'data?period=last2');
	$section[] = array('Title' => 'Last 3 months', 'Icon' => 'stats', 'Link' => page::getWebLocation() . 'data?period=last3');
	$section[] = array('Title' => '2 months ago', 'Icon' => 'stats', 'Link' => page::getWebLocation() . 'data?period=2last');
	$section[] = array('Title' => '3 months ago', 'Icon' => 'stats', 'Link' => page::getWebLocation() . 'data?period=3last');
	$sidebar[] = $section;

	// Fluid Theme
	session::set('fluid', true);

	// There is no concept of users in this app.
	session::setCurrentUser(true);

	$templateFactory->setVar('db', $db);
	$templateFactory->setVar('sidebar', $sidebar);
	$templateFactory->setVar('fluid', session::get('fluid', false));
	$templateFactory->setVar('showSidebar', count($sidebar) > 0);

	$templateFactory->setVar('__pagename', $inc);
	$templateFactory->setVar('__pagegroup', $inc);
	$page = page::getPage($inc, $templateFactory, $params);

	if (is_null($page)) {
		$page = page::getPage('error404', $templateFactory, $params);
	}

	// Check if we are allowed access this.
/*	$accesscheck = $page->checkAccess();
	if ($accesscheck !== true) {
		if ($accesscheck === false) {
			session::append('message', array('error', 'Error!', 'Access denied.'));
		} else {
			session::append('message', array('error', 'Error!', 'Access denied: ' . $accesscheck));
		}
		session::set('postLogin', $params);
		$page->redirectTo('login');
	} else if ($inc != 'login' && $inc != 'favicon.ico') {
		session::remove('postLogin');
	} */

	// And display the page.

	$page->display();
?>
