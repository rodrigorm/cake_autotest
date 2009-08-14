<?php
/**
 * Short description for autotest.test.php
 *
 * Long description for autotest.test.php
 *
 * PHP version 4 and 5
 *
 * Copyright (c) 2009, Rodrigo Moyle
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @filesource
 * @copyright     Copyright (c) 2009, Rodrigo Moyle
 * @link          blog.rodrigorm.com.br
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

App::import('Shell', 'Autotest.AutoTest');
App::import('Shell', 'AutoTest');
Mock::generatePartial(
	'AutoTestShell',
	'AutoTestShellTestVersion',
	array(
		'void',
		'_runTest'
	)
);

define('TEST_APP', dirname(dirname(dirname(__FILE__))) . DS . 'test_app');

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
	function startTest() {
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
	function endTest() {}

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
	function testAddHooks() {
		$this->AutoTest->addHooks();
		$expected = array(
			Hooks::green => array(
				array('Notify', 'green')
			),
			Hooks::red => array(
				array('Notify', 'red')
			),
			Hooks::all_good => array(
				array('Notify', 'allGood')
			)
		);
		$this->assertEqual(AutoTestShell::$hooks, $expected);
	}

/**
 * testRunTests method
 *
 * @return void
 * @access public
 */
	function testRunTests() {
		$this->AutoTest->filesToTest = array(
			'file_1.php',
			'file_2.php'
		);
		$this->AutoTest->setReturnValue('_runTest', 'Pass ✔');
		$this->AutoTest->expectCallCount('_runTest', 2);
		$this->AutoTest->_runTests();
	}

/**
 * testRunTestsSetFailsOnFailTest method
 *
 * @return void
 * @access public
 */
	function testRunTestsSetFailsOnFailTest() {
		$this->AutoTest->filesToTest = array(
			'file_1.php',
			'file_2.php'
		);
		$this->AutoTest->setReturnValueAt(0, '_runTest', 'Pass ✔');
		$this->AutoTest->setReturnValueAt(1, '_runTest', 'Fail ✘');
		$this->AutoTest->_runTests();
		$expected = array(
			'file_2.php' => 'file_2.php'
		);
		$this->assertEqual($this->AutoTest->fails, $expected);
	}

/**
 * testAllGood method
 *
 * @return void
 * @access public
 */
	function testAllGood() {
		$this->assertTrue($this->AutoTest->_allGood());
	}

/**
 * testAllGoodReturnFalse method
 *
 * @return void
 * @access public
 */
	function testAllGoodReturnFalse() {
		$this->AutoTest->fails = array(
			'file_1.php'
		);
		$this->assertFalse($this->AutoTest->_allGood());
	}

/**
 * testReset method
 *
 * @return void
 * @access public
 */
	function testReset() {
		$this->AutoTest->filesToTest = array(
			'file_1.php'
		);
		$this->AutoTest->lastMTime = time();
		$this->AutoTest->fails = array(
			'file_2.php'
		);
		$this->AutoTest->_reset();
		$this->assertNull($this->AutoTest->filesToTest);
		$this->assertNull($this->AutoTest->lastMTime);
		$this->assertEqual($this->AutoTest->fails, array());
	}

/**
 * testAddHook method
 *
 * @return void
 * @access public
 */
	function testAddHook() {
		$_hooks = AutoTestShell::$hooks;
		AutoTestShell::$hooks = array();
		$callback = 'sprintf';
		$this->AutoTest->addHook(Hooks::green, $callback);
		$expected = array(
			Hooks::green => array(
				$callback
			)
		);
		$this->assertEqual(AutoTestShell::$hooks, $expected);
		AutoTestShell::$hooks = $_hooks;
	}

/**
 * testFindFiles method
 *
 * @return void
 * @access public
 */
	function testFindFiles() {
		$this->AutoTest->settings['excludePattern'] = '@one(\\.test)?\\.php$@';
		$expected = array(
			TEST_APP . DS . 'controllers' . DS . 'posts_controller.php',
			TEST_APP . DS . 'models' . DS . 'post.php',
			TEST_APP . DS . 'plugins' . DS . 'test_plugin' . DS . 'controllers' . DS . 'test_plugin_controller.php',
			TEST_APP . DS . 'plugins' . DS . 'test_plugin' . DS . 'tests' . DS . 'cases' . DS . 'controllers' . DS . 'test_plugin_controller.test.php',
			TEST_APP . DS . 'tests' . DS . 'cases' . DS . 'controllers' . DS . 'posts_controller.test.php',
		);
		$result = $this->AutoTest->_findFiles();
		$this->assertEqual($result, $expected);
		$this->AutoTest->lastMTime = null;
	}

/**
 * testFindFilesIgnore method
 *
 * @return void
 * @access public
 */
	function testFindFilesIgnore() {
		$this->AutoTest->settings['excludePattern'] = '@(models[\\\/]post\.php|[\\\/]test_plugin[\\\/]|[\\\/]one(\.test)?\.php$)@';
		$expected = array(
			TEST_APP . DS . 'controllers' . DS . 'posts_controller.php',
			TEST_APP . DS . 'tests' . DS . 'cases' . DS . 'controllers' . DS . 'posts_controller.test.php',
		);
		$result = $this->AutoTest->_findFiles();
		$this->assertEqual($result, $expected);
		$this->AutoTest->lastMTime = null;
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
		$this->AutoTest->lastMTime = $past;
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
		$testFile = 'posts_controller.php';
		$Folder = new Folder($base);

		foreach($Folder->find() as $file) {
			if ($file === $testFile) {
				touch($base . $testFile);
			} else {
				$prev = time() - 60 * 60;
				touch($base . $file, $prev, $prev);
			}
		}
		$this->AutoTest->lastMTime = time() - 1;
		$this->AutoTest->setReturnValue('_runTest', 'Pass ✔');
		$this->AutoTest->_runTests();

		$expected = array(
			'passed' => array(
				'posts_controller.php' => '✔'
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
		$testFile = 'other_controller.php';
		$Folder = new Folder($base);

		$File = new File($base . $testFile);
		$File->write('<?php junk');

		foreach($Folder->find() as $file) {
			if ($file === $testFile) {
				touch($base . $testFile);
			} else {
				$prev = time() - 60 * 60;
				touch($base . $file, $prev, $prev);
			}
		}

		$this->AutoTest->lastMTime = time() - 1;
		$this->AutoTest->setReturnValue('_runTest', 'Fail ✘');
		$this->AutoTest->_runTests();

		$expected = array(
			'passed' => array(),
			'skipped' => array(),
			'failed' => array(
				$testFile => '✘'
			),
			'unknown' => array(),
			'passedCount' => 0,
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

		$this->AutoTest->lastMTime = $time;
		touch($testfile, $future);
		$this->AutoTest->_waitForChanges();
		$this->assertEqual($this->AutoTest->filesToTest, array($testfile));
	}
}