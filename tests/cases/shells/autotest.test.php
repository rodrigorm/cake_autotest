<?php
App::import('Core', 'Shell');

if (!defined('DISABLE_AUTO_DISPATCH')) {
	define('DISABLE_AUTO_DISPATCH', true);
}

if (!class_exists('ShellDispatcher')) {
	ob_start();
	$argv = false;
	require CAKE . 'console' .  DS . 'cake.php';
	ob_end_clean();
}

if (!class_exists('AutotestShell')) {
	App::import('Shell', 'Autotest.Autotest');
}

class TestAutotestShell extends AutotestShell {
	function __construct($params = array()) {
		$this->params = am(array(
			'working' => APP,
			'app' => APP
		), $params);
		$this->initialize();
	}

	function out($message) {
		$this->logMessages[] = $message;
	}
}

Mock::generatePartial(
	'TestAutotestShell',
	'MockAutotestShell',
	array(
		'_runTest'
	)
);

define('AUTOTEST_APP', dirname(__FILE__) . DS . 'autotest_app' . DS);

class AutotestShellTest extends CakeTestCase {
	function startTest() {
		$this->Autotest = new MockAutotestShell();
		$this->Autotest->settings['ignorePatterns'] = false;
		$this->Autotest->settings['interval'] = 0;
		$this->Autotest->params['working'] = AUTOTEST_APP;
	}

	function testAddHooks() {
		$this->Autotest->addHooks();
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
		$this->assertEqual(AutotestShell::$hooks, $expected);
	}

	function testRunTests() {
		$this->Autotest->files_to_test = array(
			'file_1.php',
			'file_2.php'
		);
		$this->Autotest->setReturnValue('_runTest', 'Pass ✔');
		$this->Autotest->expectCallCount('_runTest', 2);
		$this->Autotest->_runTests();
	}

	function testRunTestsSetFailsOnFailTest() {
		$this->Autotest->files_to_test = array(
			'file_1.php',
			'file_2.php'
		);
		$this->Autotest->setReturnValueAt(0, '_runTest', 'Pass ✔');
		$this->Autotest->setReturnValueAt(1, '_runTest', 'Fail ✘');
		$this->Autotest->_runTests();
		$expected = array(
			'file_2.php' => 'file_2.php'
		);
		$this->assertEqual($this->Autotest->fails, $expected);
	}

	function testWaitForChanges() {
		$this->Autotest->last_mtime = time() - 1;
		$file = AUTOTEST_APP . '/changed.php';
		touch($file);
		$this->Autotest->_waitForChanges();
		$expected = array(
			$file
		);
		$this->assertEqual($this->Autotest->files_to_test, $expected);
		@unlink($file);
	}

	function testAllGood() {
		$this->assertTrue($this->Autotest->_allGood());
	}

	function testAllGoodReturnFalse() {
		$this->Autotest->fails = array(
			'file_1.php'
		);
		$this->assertFalse($this->Autotest->_allGood());
	}

	function testReset() {
		$this->Autotest->files_to_test = array(
			'file_1.php'
		);
		$this->Autotest->last_mtime = time();
		$this->Autotest->fails = array(
			'file_2.php'
		);
		$this->Autotest->_reset();
		$this->assertNull($this->Autotest->files_to_test);
		$this->assertNull($this->Autotest->last_mtime);
		$this->assertEqual($this->Autotest->fails, array());
	}

	function testAddHook() {
		AutotestShell::$hooks = array();
		$callback = 'sprintf';
		$this->Autotest->addHook(Hooks::green, $callback);
		$expected = array(
			Hooks::green => array(
				$callback
			)
		);
		$this->assertEqual(AutotestShell::$hooks, $expected);
	}

	function testFindFiles() {
		$this->Autotest->last_mtime = time() - 1;
		$file = AUTOTEST_APP . '/changed.php';
		touch($file);
		$fileMTime = filemtime($file);
		$changedFiles = $this->Autotest->_findFiles();
		$expected = array(
			$file
		);
		$this->assertEqual($changedFiles, $expected);
		$this->assertEqual($this->Autotest->last_mtime, $fileMTime);
		@unlink($file);
	}
}
?>
