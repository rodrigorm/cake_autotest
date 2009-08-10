<?php
/**
 * Short description for autotest.test.php
 *
 * Long description for autotest.test.php
 *
 * PHP version 4 and 5
 *
 * Copyright (c) 2009, Andy Dawson
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @filesource
 * @copyright     Copyright (c) 2009, Andy Dawson
 * @link          www.ad7six.com
 * @package       autotest
 * @subpackage    autotest.tests.cases.shells
 * @since         v 1.0 (10-Aug-2009)
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */
if (!class_exists('ShellDispatcher')) {
	ob_start();
	$argv = false;
	require CAKE . 'console' .  DS . 'cake.php';
	ob_end_clean();
}

Mock::generate('ShellDispatcher');

App::import('Core', 'Folder');

App::import('Vendor', 'autotest.shells/autotest');

Mock::generatePartial(
	'AutoTestShell',
	'AutoTestShellTestVersion',
	array('void')
);

define('TEST_APP', dirname(dirname(dirname(__FILE__))) . DS . 'test_app');
define('PASS_OUTPUT', "Hello rodrigomoyle,\n\nWelcome to CakePHP v1.1.18.5850 Console\n---------------------------------------------------------------\nApp : app\nPath: /path/to/app\n---------------------------------------------------------------\nCakePHP Test Shell\n---------------------------------------------------------------\nRunning app case vendors/shells/autotest\nIndividual test case: vendors/shells/autotest.test.php\n1/1 test cases complete: 9 passes.\n");

define('FAIL_OUTPUT', "Hello rodrigomoyle,\n\nWelcome to CakePHP v1.1.18.5850 Console\n---------------------------------------------------------------\nApp : app\nPath: /Volumes/Sites/Cinemenu/site/app\n---------------------------------------------------------------\nCakePHP Test Shell\n---------------------------------------------------------------\nRunning app case vendors/shells/autotest\nIndividual test case: vendors/shells/autotest.test.php\n1) Equal expectation fails at character 0 with [Fail] and [Pass] at [/Volumes/Sites/Cinemenu/site/app/tests/cases/vendors/shells/autotest.test.php line 124]\n\tin testHandleResults\n\tin AutoTestTestCase\n\tin /Volumes/Sites/Cinemenu/site/app/tests/cases/vendors/shells/autotest.test.php\nFAIL->/Volumes/Sites/Cinemenu/site/app/tests/cases/vendors/shells/autotest.test.php->AutoTestTestCase->testHandleResults->Equal expectation fails at character 0 with [Fail] and [Pass] at [/Volumes/Sites/Cinemenu/site/app/tests/cases/vendors/shells/autotest.test.php line 124]\n1/1 test cases complete: 10 passes, 1 fails.\n");

define('ERROR_OUTPUT', "Welcome to CakePHP v1.2.0.7692 RC3 Console\n---------------------------------------------------------------\nApp : app\nPath: /path/to/app\n---------------------------------------------------------------\nCakePHP Test Shell\n---------------------------------------------------------------\nRunning app case models/datasources/twitter_source\nPHP Parse error:  syntax error, unexpected ')', expecting '&' or T_VARIABLE in\n/Users/rodrigomoyle/Desktop/Code/mkt/natal2008/app/tests/cases/models/datasources/twitter_source.test.php on line 5");

/**
 * AutoTestTestCase class
 *
 * @uses          CakeTestCase
 * @package       autotest
 * @subpackage    autotest.tests.cases.shells
 */
class AutoTestTestCase extends CakeTestCase {

/**
 * setUp method
 *
 * @return void
 * @access public
 */
	function setUp() {
		$this->Folder = new Folder();
		$this->Dispatcher = new MockShellDispatcher();
		$this->AutoTest = new AutoTestShellTestVersion();
		$this->AutoTest->Dispatch = $this->Dispatcher;
		$this->AutoTest->folder = new Folder(TEST_APP);
		$this->AutoTest->params = array(
			'working' => TEST_APP,
			'app' => 'test_app',
			'root' => dirname(TEST_APP),
			'webroot' => 'webroot',
			'notify' => 'log',
			'checkAllOnStart' => false,
		);
		$this->AutoTest->buildPaths();
	}

/**
 * tearDown method
 *
 * @return void
 * @access public
 */
	function tearDown() {}

/**
 * testPresenceOfClass method
 *
 * @return void
 * @access public
 */
	function testPresenceOfClass() {
		$this->assertTrue(class_exists('AutoTestShell'));
	}

/**
 * testFindFiles method
 *
 * @return void
 * @access public
 */
	function testFindFiles() {
		$this->AutoTest->settings['ignorePatterns'] = array(
			'/one(\\.test)?\\.php$/'
		);
		$expected = array(
			TEST_APP . DS . 'controllers' . DS . 'posts_controller.php',
			TEST_APP . DS . 'models' . DS . 'post.php',
			TEST_APP . DS . 'plugins' . DS . 'test_plugin' . DS . 'controllers' . DS . 'test_plugin_controller.php',
			TEST_APP . DS . 'plugins' . DS . 'test_plugin' . DS . 'tests' . DS . 'cases' . DS . 'controllers' . DS . 'test_plugin_controller.test.php',
			TEST_APP . DS . 'tests' . DS . 'cases' . DS . 'controllers' . DS . 'posts_controller.test.php',
		);
		$result = $this->AutoTest->_findFiles();
		$this->assertEqual($result, $expected);
		$this->AutoTest->last_mtime = null;
	}

/**
 * testFindFilesIgnore method
 *
 * @return void
 * @access public
 */
	function testFindFilesIgnore() {
		$this->AutoTest->settings['ignorePatterns'] = array(
			'/models.post\.php$/',
			'/test_plugin/',
			'/one(\\.test)?\\.php$/'
		);
		$expected = array(
			TEST_APP . DS . 'controllers' . DS . 'posts_controller.php',
			TEST_APP . DS . 'tests' . DS . 'cases' . DS . 'controllers' . DS . 'posts_controller.test.php',
		);
		$result = $this->AutoTest->_findFiles();
		$this->assertEqual($result, $expected);
		$this->AutoTest->last_mtime = null;
	}

/**
 * testFindFilesToTest method
 *
 * @return void
 * @access public
 */
	function testFindFilesToTest() {
		$time = mktime();
		$past = $time - 1;
		$this->AutoTest->last_mtime = $past;
		touch(TEST_APP . DS . 'controllers' . DS . 'posts_controller.php', $time);

		$expected = array(
			TEST_APP . DS . 'controllers' . DS . 'posts_controller.php',
		);
		$result = $this->AutoTest->_findFiles();
		$this->assertEqual($result, $expected);
	}

/**
 * testPass method
 *
 * @return void
 * @access public
 */
	function testPass() {
		$this->AutoTest->params['working'] = TEST_APP . DS . 'controllers';
		$base = TEST_APP . DS . 'controllers' . DS;
		$testFile = $base . 'posts_controller.php';
		$Folder = new Folder($base);

		foreach($Folder->find() as $file) {
			if ($file === basename($testFile)) {
				touch($testFile);
			} else {
				$prev = time() - 60 * 60;
				touch($base . $file, $prev, $prev);
			}
		}
		$this->AutoTest->last_mtime = time() - 1;
		$this->AutoTest->_runTests();

		$expected = array(
			'passed' => array(
				$testFile => '✔'
			),
			'skipped' => array(),
			'failed' => array(),
			'unknown' => array(),
			'passedCount' => 1,
			'skippedCount' => 0,
			'failedCount' => 0,
			'unknownCount' => 0,
			'totalCount' => 1,
		);
		$this->assertEqual($this->AutoTest->results, $expected);
	}

/**
 * testFail method
 *
 * @return void
 * @access public
 */
	function testFail() {
		$this->AutoTest->params['working'] = TEST_APP . DS . 'controllers';
		$base = TEST_APP . DS . 'controllers' . DS;
		$testFile = $base . 'other_controller.php';
		$Folder = new Folder($base);

		$File = new File($testFile);
		$File->write('<?php junk');

		foreach($Folder->find() as $file) {
			if ($file === basename($testFile)) {
				touch($testFile);
			} else {
				$prev = time() - 60 * 60;
				touch($base . $file, $prev, $prev);
			}
		}

		$this->AutoTest->last_mtime = time() - 1;
		$this->AutoTest->_runTests();

		$expected = array(
			'passed' => array(),
			'skipped' => array(),
	        'failed' => array(
				$testFile => '✘'
			),
			'unknown' => array(),
	        'skippedCount' => 0,
	        'failedCount' => 1,
			'unknownCount' => 0,
			'totalCount' => 1,
		);
		$File->delete();
		$this->assertEqual($this->AutoTest->results, $expected);
	}

/**
 * testWaitForChanges method
 *
 * @return void
 * @access public
 */
	function testWaitForChanges() {
		$testfile = TEST_APP . DS . 'tests' . DS . 'cases' . DS . 'controllers' . DS . 'posts_controller.test.php';

		$filetime = filemtime($testfile);
		$time = strtotime('+1 second');
		$future = strtotime('+2 seconds');

		$this->AutoTest->last_mtime = $time;
		touch($testfile, $future);
		$this->AutoTest->_waitForChanges();
		$this->assertEqual($this->AutoTest->files_to_test, array($testfile));
	}
}