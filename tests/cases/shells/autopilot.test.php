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
if (!defined('DISABLE_AUTO_DISPATCH')) {
	define('DISABLE_AUTO_DISPATCH', true);
}

if (!class_exists('Shell')) {
	App::import('Core', 'Shell');
}

if (!class_exists('ShellDispatcher')) {
	ob_start();
	$argv = false;
	require CAKE . 'console' .  DS . 'cake.php';
	ob_end_clean();
}
Mock::generate('ShellDispatcher');

App::import('Core', 'Folder');

App::import('Shell', 'Autotest.Autopilot');
App::import('Shell', 'Autopilot');
Mock::generatePartial(
	'AutopilotShell',
	'AutopilotShellTestVersion',
	array(
		'void',
		'_runTest'
	)
);

define('TEST_APP', dirname(dirname(dirname(__FILE__))) . DS . 'test_app');

/**
 * AutopilotTestCase class
 *
 * @uses          CakeTestCase
 * @package       autotest
 * @subpackage    autotest.tests.cases.shells
 */
class AutopilotTestCase extends CakeTestCase {

/**
 * setUp method
 *
 * @return void
 * @access public
 */
	function startTest() {
		$this->Folder = new Folder();
		$this->Dispatcher = new MockShellDispatcher();
		$this->Autopilot = new AutopilotShellTestVersion();
		$this->Autopilot->Dispatch = $this->Dispatcher;
		$this->Autopilot->folder = new Folder(TEST_APP);
		$this->Autopilot->params = array(
			'working' => TEST_APP,
			'app' => 'test_app',
			'root' => dirname(TEST_APP),
			'webroot' => 'webroot',
			'notify' => 'log',
			'checkAllOnStart' => false,
		);
		$this->Autopilot->buildPaths();
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
		$this->assertTrue(class_exists('AutopilotShell'));
	}

/**
 * testFindFiles method
 *
 * @return void
 * @access public
 */
	function testAddHooks() {
		$this->Autopilot->addHooks();
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
		$this->assertEqual(AutopilotShell::$hooks, $expected);
	}

/**
 * testRunTests method
 *
 * @return void
 * @access public
 */
	function testRunTests() {
		$this->Autopilot->filesToTest = array(
			'file_1.php',
			'file_2.php'
		);
		$this->Autopilot->setReturnValue('_runTest', 'Pass ✔');
		$this->Autopilot->expectCallCount('_runTest', 2);
		$this->Autopilot->_runTests();
	}

/**
 * testRunTestsSetFailsOnFailTest method
 *
 * @return void
 * @access public
 */
	function testRunTestsSetFailsOnFailTest() {
		$this->Autopilot->filesToTest = array(
			'file_1.php',
			'file_2.php'
		);
		$this->Autopilot->setReturnValueAt(0, '_runTest', 'Pass ✔');
		$this->Autopilot->setReturnValueAt(1, '_runTest', 'Fail ✘');
		$this->Autopilot->_runTests();
		$expected = array(
			'file_2.php' => 'file_2.php'
		);
		$this->assertEqual($this->Autopilot->fails, $expected);
	}

/**
 * testAllGood method
 *
 * @return void
 * @access public
 */
	function testAllGood() {
		$this->assertTrue($this->Autopilot->_allGood());
	}

/**
 * testAllGoodReturnFalse method
 *
 * @return void
 * @access public
 */
	function testAllGoodReturnFalse() {
		$this->Autopilot->fails = array(
			'file_1.php'
		);
		$this->assertFalse($this->Autopilot->_allGood());
	}

/**
 * testReset method
 *
 * @return void
 * @access public
 */
	function testReset() {
		$this->Autopilot->filesToTest = array(
			'file_1.php'
		);
		$this->Autopilot->lastMTime = time();
		$this->Autopilot->fails = array(
			'file_2.php'
		);
		$this->Autopilot->_reset();
		$this->assertNull($this->Autopilot->filesToTest);
		$this->assertNull($this->Autopilot->lastMTime);
		$this->assertEqual($this->Autopilot->fails, array());
	}

/**
 * testAddHook method
 *
 * @return void
 * @access public
 */
	function testAddHook() {
		$_hooks = AutopilotShell::$hooks;
		AutopilotShell::$hooks = array();
		$callback = 'sprintf';
		$this->Autopilot->addHook(Hooks::green, $callback);
		$expected = array(
			Hooks::green => array(
				$callback
			)
		);
		$this->assertEqual(AutopilotShell::$hooks, $expected);
		AutopilotShell::$hooks = $_hooks;
	}

/**
 * testFindFiles method
 *
 * @return void
 * @access public
 */
	function testFindFiles() {
		$this->Autopilot->settings['excludePattern'] = '@one(\\.test)?\\.php$@';
		$expected = array(
			TEST_APP . DS . 'controllers' . DS . 'posts_controller.php',
			TEST_APP . DS . 'models' . DS . 'post.php',
			TEST_APP . DS . 'plugins' . DS . 'test_plugin' . DS . 'controllers' . DS . 'test_plugin_controller.php',
			TEST_APP . DS . 'plugins' . DS . 'test_plugin' . DS . 'tests' . DS . 'cases' . DS . 'controllers' . DS . 'test_plugin_controller.test.php',
			TEST_APP . DS . 'tests' . DS . 'cases' . DS . 'controllers' . DS . 'posts_controller.test.php',
		);
		$result = $this->Autopilot->_findFiles();
		$this->assertEqual($result, $expected);
		$this->Autopilot->lastMTime = null;
	}

/**
 * testFindFilesIgnore method
 *
 * @return void
 * @access public
 */
	function testFindFilesIgnore() {
		$this->Autopilot->settings['excludePattern'] = '@(models[\\\/]post\.php|[\\\/]test_plugin[\\\/]|[\\\/]one(\.test)?\.php$)@';
		$expected = array(
			TEST_APP . DS . 'controllers' . DS . 'posts_controller.php',
			TEST_APP . DS . 'tests' . DS . 'cases' . DS . 'controllers' . DS . 'posts_controller.test.php',
		);
		$result = $this->Autopilot->_findFiles();
		$this->assertEqual($result, $expected);
		$this->Autopilot->lastMTime = null;
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
		$this->Autopilot->lastMTime = $past;
		touch(TEST_APP . DS . 'controllers' . DS . 'posts_controller.php', $time);

		$expected = array(
			TEST_APP . DS . 'controllers' . DS . 'posts_controller.php',
		);
		$result = $this->Autopilot->_findFiles();
		$this->assertEqual($result, $expected);
	}

/**
 * testPass method
 *
 * @return void
 * @access public
 */
	function testPass() {
		$this->Autopilot->params['working'] = TEST_APP . DS . 'controllers';
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
		$this->Autopilot->lastMTime = time() - 1;
		$this->Autopilot->setReturnValue('_runTest', 'Pass ✔');
		$this->Autopilot->_runTests();

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
		$this->assertEqual($this->Autopilot->results, $expected);
	}

/**
 * testFail method
 *
 * @return void
 * @access public
 */
	function testFail() {
		$this->Autopilot->params['working'] = TEST_APP . DS . 'controllers';
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

		$this->Autopilot->lastMTime = time() - 1;
		$this->Autopilot->setReturnValue('_runTest', 'Fail ✘');
		$this->Autopilot->_runTests();

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
		$this->assertEqual($this->Autopilot->results, $expected);
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

		$this->Autopilot->lastMTime = $time;
		touch($testfile, $future);
		$this->Autopilot->_waitForChanges();
		$this->assertEqual($this->Autopilot->filesToTest, array($testfile));
	}
}