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

define('TEST_APP', dirname(__FILE__) . DS . 'test_app');
define('PASS_OUTPUT', "Hello rodrigomoyle,\n\nWelcome to CakePHP v1.1.18.5850 Console\n---------------------------------------------------------------\nApp : app\nPath: /path/to/app\n---------------------------------------------------------------\nCakePHP Test Shell\n---------------------------------------------------------------\nRunning app case vendors/shells/autotest\nIndividual test case: vendors/shells/autotest.test.php\n1/1 test cases complete: 9 passes.\n");
define('FAIL_OUTPUT', "Hello rodrigomoyle,\n\nWelcome to CakePHP v1.1.18.5850 Console\n---------------------------------------------------------------\nApp : app\nPath: /Volumes/Sites/Cinemenu/site/app\n---------------------------------------------------------------\nCakePHP Test Shell\n---------------------------------------------------------------\nRunning app case vendors/shells/autotest\nIndividual test case: vendors/shells/autotest.test.php\n1) Equal expectation fails at character 0 with [Fail] and [Pass] at [/Volumes/Sites/Cinemenu/site/app/tests/cases/vendors/shells/autotest.test.php line 124]\n\tin testHandleResults\n\tin AutoTestTestCase\n\tin /Volumes/Sites/Cinemenu/site/app/tests/cases/vendors/shells/autotest.test.php\nFAIL->/Volumes/Sites/Cinemenu/site/app/tests/cases/vendors/shells/autotest.test.php->AutoTestTestCase->testHandleResults->Equal expectation fails at character 0 with [Fail] and [Pass] at [/Volumes/Sites/Cinemenu/site/app/tests/cases/vendors/shells/autotest.test.php line 124]\n1/1 test cases complete: 10 passes, 1 fails.\n");

class AutoTestTestCase extends CakeTestCase {
	function setUp() {
		$this->Folder =& new Folder();
		$this->Dispatcher =& new MockShellDispatcher();
		$this->AutoTest =& new AutoTestShellTestVersion();
		$this->AutoTest->Dispatch = $this->Dispatcher;
		$this->AutoTest->folder =& new Folder(TEST_APP);
		$this->AutoTest->params['working'] = TEST_APP;
	}

	function tearDown() {}

	function testPresenceOfClass() {
		$this->assertTrue(class_exists('AutoTestShell'));
	}

	function testFindFiles() {
		$expected = array(
			TEST_APP . DS . 'controllers' . DS . 'posts_controller.php',
			TEST_APP . DS . 'models' . DS . 'post.php',
			TEST_APP . DS . 'plugins' . DS . 'test_plugin' . DS . 'controllers' . DS . 'test_plugin_controller.php',
			TEST_APP . DS . 'plugins' . DS . 'test_plugin' . DS . 'tests' . DS . 'cases' . DS . 'controllers' . DS . 'test_plugin_controller.test.php',
			TEST_APP . DS . 'tests' . DS . 'cases' . DS . 'controllers' . DS . 'posts_controller.test.php',
		);
		$this->assertEqual($this->AutoTest->_findFiles(), $expected);
	}

	function testFindFilesIgnore() {
		$this->AutoTest->ignore_files = array(
			'/models.post\.php$/', 
			'/test_plugin/'
		);
		$expected = array(
			TEST_APP . DS . 'controllers' . DS . 'posts_controller.php',
			TEST_APP . DS . 'tests' . DS . 'cases' . DS . 'controllers' . DS . 'posts_controller.test.php',
		);
		$this->assertEqual($this->AutoTest->_findFiles(), $expected);
	}

	function testFindFilesToTest() {
		$time = mktime();
		$past = $time - 1;
		$this->AutoTest->last_mtime = $past;
		touch(TEST_APP . DS . 'controllers' . DS . 'posts_controller.php', $time);
		
		$expected = array(
			TEST_APP . DS . 'controllers' . DS . 'posts_controller.php',
		);
		$this->assertEqual($this->AutoTest->_findFilesToTest(), $time);
		$this->assertEqual($this->AutoTest->files_to_test, $expected);
	}

	function testMapFilesToTests() {
		$files = array(
			TEST_APP . DS . 'controllers' . DS . 'posts_controller.php',
		);
		$expected = array(
			TEST_APP . DS . 'tests' . DS . 'cases' . DS . 'controllers' . DS . 'posts_controller.test.php',
		);
		$this->assertEqual($this->AutoTest->_mapFilesToTests($files), $expected);

		$files = array(
			TEST_APP . DS . 'tests' . DS . 'cases' . DS . 'controllers' . DS . 'posts_controller.test.php',
		);
		$expected = array(
			TEST_APP . DS . 'tests' . DS . 'cases' . DS . 'controllers' . DS . 'posts_controller.test.php',
		);
		$this->assertEqual($this->AutoTest->_mapFilesToTests($files), $expected);

		$files = array(
			TEST_APP . DS . 'controllers' . DS . 'posts_controller.php',
			TEST_APP . DS . 'tests' . DS . 'cases' . DS . 'controllers' . DS . 'posts_controller.test.php',
		);
		$expected = array(
			TEST_APP . DS . 'tests' . DS . 'cases' . DS . 'controllers' . DS . 'posts_controller.test.php',
		);
		$this->assertEqual($this->AutoTest->_mapFilesToTests($files), $expected);
	}

	function testMapFileToTest() {
		$file = TEST_APP . DS . 'controllers' . DS . 'posts_controller.php';
		$expected = TEST_APP . DS . 'tests' . DS . 'cases' . DS . 'controllers' . DS . 'posts_controller.test.php';
		$this->assertEqual($this->AutoTest->_mapFileToTest($file), $expected);

		$file = TEST_APP . DS . 'tests' . DS . 'cases' . DS . 'controllers' . DS . 'posts_controller.test.php';
		$expected = TEST_APP . DS . 'tests' . DS . 'cases' . DS . 'controllers' . DS . 'posts_controller.test.php';
		$this->assertEqual($this->AutoTest->_mapFileToTest($file), $expected);
	}

	function testMapFileToTestWithPluginTests() {
		$file = TEST_APP . DS . 'plugins' . DS . 'test_plugin' . DS . 'controllers' . DS . 'test_plugin_controller.php';
		$expected = TEST_APP . DS . 'plugins' . DS . 'test_plugin' . DS . 'tests' . DS . 'cases' . DS . 'controllers' . DS . 'test_plugin_controller.test.php';
		$this->assertEqual($this->AutoTest->_mapFileToTest($file), $expected);
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
?>