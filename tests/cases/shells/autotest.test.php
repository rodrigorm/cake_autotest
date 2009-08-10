<?php
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
	array('_runTest')
);

define('TEST_APP', dirname(dirname(dirname(__FILE__))) . DS . 'test_app');
define('PASS_OUTPUT', "Hello rodrigomoyle,\n\nWelcome to CakePHP v1.1.18.5850 Console\n---------------------------------------------------------------\nApp : app\nPath: /path/to/app\n---------------------------------------------------------------\nCakePHP Test Shell\n---------------------------------------------------------------\nRunning app case vendors/shells/autotest\nIndividual test case: vendors/shells/autotest.test.php\n1/1 test cases complete: 9 passes.\n");

define('FAIL_OUTPUT', "Hello rodrigomoyle,\n\nWelcome to CakePHP v1.1.18.5850 Console\n---------------------------------------------------------------\nApp : app\nPath: /Volumes/Sites/Cinemenu/site/app\n---------------------------------------------------------------\nCakePHP Test Shell\n---------------------------------------------------------------\nRunning app case vendors/shells/autotest\nIndividual test case: vendors/shells/autotest.test.php\n1) Equal expectation fails at character 0 with [Fail] and [Pass] at [/Volumes/Sites/Cinemenu/site/app/tests/cases/vendors/shells/autotest.test.php line 124]\n\tin testHandleResults\n\tin AutoTestTestCase\n\tin /Volumes/Sites/Cinemenu/site/app/tests/cases/vendors/shells/autotest.test.php\nFAIL->/Volumes/Sites/Cinemenu/site/app/tests/cases/vendors/shells/autotest.test.php->AutoTestTestCase->testHandleResults->Equal expectation fails at character 0 with [Fail] and [Pass] at [/Volumes/Sites/Cinemenu/site/app/tests/cases/vendors/shells/autotest.test.php line 124]\n1/1 test cases complete: 10 passes, 1 fails.\n");

define('ERROR_OUTPUT', "Welcome to CakePHP v1.2.0.7692 RC3 Console\n---------------------------------------------------------------\nApp : app\nPath: /path/to/app\n---------------------------------------------------------------\nCakePHP Test Shell\n---------------------------------------------------------------\nRunning app case models/datasources/twitter_source\nPHP Parse error:  syntax error, unexpected ')', expecting '&' or T_VARIABLE in\n/Users/rodrigomoyle/Desktop/Code/mkt/natal2008/app/tests/cases/models/datasources/twitter_source.test.php on line 5");

class AutoTestTestCase extends CakeTestCase {
	function setUp() {
		$this->Folder = new Folder();
		$this->Dispatcher = new MockShellDispatcher();
		$this->AutoTest = new AutoTestShellTestVersion();
		$this->AutoTest->Dispatch = $this->Dispatcher;
		$this->AutoTest->folder = new Folder(TEST_APP);
		$this->AutoTest->params['working'] = TEST_APP;
	}

	function tearDown() {}

	function testPresenceOfClass() {
		$this->assertTrue(class_exists('AutoTestShell'));
	}

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

	function testRunTests() {
		$time = mktime();
		$past = $time - 1;
		$this->AutoTest->last_mtime = $past;
		touch(TEST_APP . DS . 'controllers' . DS . 'posts_controller.php', $time);

		$testfile = TEST_APP . DS . 'tests' . DS . 'cases' . DS . 'controllers' . DS . 'posts_controller.test.php';

		$this->AutoTest->setReturnValue('_runTest', PASS_OUTPUT, array($testfile));

		ob_start();
		$this->AutoTest->_runTests();
		ob_end_clean();

		$this->assertEqual($this->AutoTest->results, array($testfile => PASS_OUTPUT));
	}

	function testHandleResults() {
		$testfile = TEST_APP . DS . 'tests' . DS . 'cases' . DS . 'controllers' . DS . 'posts_controller.test.php';

		$this->AutoTest->files_to_test = array($testfile);
		$this->AutoTest->results = array(
			$testfile => PASS_OUTPUT
		);
		$this->AutoTest->_handleResults();
		$this->assertNull($this->AutoTest->files_to_test);

		$this->AutoTest->files_to_test = array($testfile);
		$this->AutoTest->results = array(
			$testfile => FAIL_OUTPUT
		);
		$this->AutoTest->_handleResults();
		$this->assertEqual($this->AutoTest->files_to_test, array($testfile));

		$this->AutoTest->files_to_test = array($testfile);
		$this->AutoTest->results = array(
			$testfile => ERROR_OUTPUT
		);
		$this->AutoTest->_handleResults();
		$this->assertEqual($this->AutoTest->files_to_test, array($testfile));
	}

	function testWaitForChanges() {
		$testfile = TEST_APP . DS . 'tests' . DS . 'cases' . DS . 'controllers' . DS . 'posts_controller.test.php';

		$filetime = filemtime($testfile);
		$time = strtotime('+1 second');
		$future = strtotime('+2 seconds');

		$this->AutoTest->last_mtime = $time;
		touch($testfile, $future);
		$this->AutoTest->_waitForChanges();
		$this->assertEqual($this->AutoTest->files_to_test, array($testfile));

		touch($testfile, $filetime);
	}
}