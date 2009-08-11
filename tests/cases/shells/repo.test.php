<?php
/**
 * Short description for repo.test.php
 *
 * Long description for repo.test.php
 *
 * PHP versions 5
 *
 * Copyright (c) 2009, Andy Dawson
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @filesource
 * @copyright     Copyright (c) 2009, Andy Dawson
 * @link          www.ad7six.com
 * @package       base
 * @subpackage    base.tests.cases.shells
 * @since         v 1.0 (06-Jul-2009)
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */
App::import('Core', 'Shell');

if (!defined('DISABLE_AUTO_DISPATCH')) {
	define('DISABLE_AUTO_DISPATCH', true);
}
define('TEST_APP', dirname(dirname(dirname(__FILE__))) . DS . 'test_app');

if (!class_exists('ShellDispatcher')) {
	ob_start();
	$argv = false;
	require CAKE . 'console' .  DS . 'cake.php';
	ob_end_clean();
}

if (!class_exists('RepoShell')) {
	App::import('Shell', 'Autotest.Repo');
}

/**
 * TestRepoShell class
 *
 * @uses          RepoShell
 * @package       base
 * @subpackage    base.tests.cases.shells
 */
class TestRepoShell extends RepoShell {

/**
 * Log messages property
 *
 * @var array
 * @access public
 */
	var $logMessages = array();

/**
 * construct method
 *
 * Set up the parameters prevent the passedTests rule from being considered (avoids loops, avoids
 * pointless warnings/logic being executed)
 *
 * @param array $params array()
 * @return void
 * @access private
 */
	function __construct($params = array()) {
		$this->params = am(array(
			'working' => APP,
			'app' => APP
		), $params);
		$this->initialize();
		unset($this->settings['rules']['failsTests']);
		$this->settings['skipTests'] = false;
		$this->_reset();

	}

/**
 * listFiles method
 *
 * @return void
 * @access public
 */
	function listFiles() {
		return $this->_listFiles();
	}

/**
 * out method
 *
 * @param mixed $message
 * @return void
 * @access public
 */
	function out($message) {
		$this->logMessages[] = $message;
	}

/**
 * reset method
 *
 * Call the reset method AND set the return value to 0 as this test case will only
 * check individual files
 *
 * @return void
 * @access public
 */
	function reset() {
		$this->_reset();
		$this->returnValue = 0;
		$this->errors = array();
	}

/**
 * mapToTest method
 *
 * Sidestep the auto test detection
 *
 * @param mixed $file
 * @return void
 * @access protected
 */
	function _mapToTest($file) {
		if (strpos($file, TEST_APP) !== false) {
			$file = str_replace(TEST_APP, '/var/www/someapp', $file);
		}
		return parent::_mapToTest($file);
	}

/**
 * stop method
 *
 * @return void
 * @access protected
 */
	function _stop() {
		return $this->returnValue;
	}
}

/**
 * RepoShellTest class
 *
 * @uses          CakeTestCase
 * @package       base
 * @subpackage    base.tests.cases.shells
 */
class RepoShellTest extends CakeTestCase {

/**
 * testPath property
 *
 * Placeholder for the path to the test files
 *
 * @var mixed null
 * @access public
 */
	var $testPath = null;

/**
 * assertFailedRules method
 *
 * Cheat the test stack so the error message is meaningful
 *
 * @param mixed $rule null
 * @return void
 * @access public
 */
	function assertFailedRules($rule = null, $isError = true) {
		if ($this->Repo->errors) {
			$keys = array_keys(current($this->Repo->errors));
		} else {
			$keys = array('no error found');
		}
		$file = key($this->Repo->errors);
		$keys = array_combine($keys, $keys);

		if (!is_array($rule)) {
			$rule = array($rule);
		}
		$rules = array_combine($rule, $rule);

		ksort($keys);
		ksort($rules);

		$this->_reporter->_test_stack[] = 'test' . Inflector::classify(implode($rules, ', '));
		$this->assertIdentical($rules, $keys);

		if ($isError) {
			$this->assertTrue($this->Repo->returnValue);
		}
		array_pop($this->_reporter->_test_stack);
	}

/**
 * startTest method
 *
 * @return void
 * @access public
 */
	function startTest() {
		$this->Repo = new TestRepoShell();
		$this->Repo->params['working'] = dirname(dirname(dirname(__FILE__))) . DS . 'repo_test_files' . DS;
		$this->Folder = new Folder(TESTS . 'repo_test_files');
	}

/**
 * testListFiles method
 *
 * Check that some files are found, and all of them are php/ctp files
 *
 * @return void
 * @access public
 */
	function testListFiles() {
		$files = $this->Repo->listFiles();
		$this->assertTrue($files);
		foreach($files as $file) {
			$ext = array_pop(explode('.', $file));
			$this->assertPattern( '/(php|ctp)/', $ext);
		}
	}

/**
 * testReset method
 *
 * Make sure that the way this test case is written isn't based on false logic
 *
 * @return void
 * @access public
 */
	function testReset() {
		$this->Repo->returnValue = 1;
		$this->Repo->reset();
		$this->assertIdentical($this->Repo->returnValue, 0);
	}

/**
 * testSkipFile method
 *
 * @return void
 * @access public
 */
	function testSkipFile() {
		$path = $this->_path('skip_file_noerrors_pass.php');
		$this->Repo->reset();
		$this->Repo->checkFile($path);
		$this->assertIdentical($this->Repo->returnValue, 0);

		$path = $this->_path('skip_file_parse_error_pass.php');
		$this->Repo->reset();
		$this->Repo->checkFile($path);
		$this->assertIdentical($this->Repo->returnValue, 0);

		$path = $this->_path('skip_file_should_fail.php');
		$this->Repo->reset();
		$this->Repo->checkFile($path);
		$this->assertFailedRules('phpLint');

		$path = $this->_path('skip_file_should_pass.php');
		$this->Repo->reset();
		$this->Repo->checkFile($path);
		$this->assertIdentical($this->Repo->returnValue, 0);
	}

/**
 * testMultpleErrors method
 *
 * @return void
 * @access public
 */
	function testMultipleErrors() {
		$path = $this->_path('multiple_debug.php');
		$this->Repo->reset();
		$this->Repo->checkFile($path);
		$this->assertIdentical(count($this->Repo->errors[$path]['debug']), 2);

		$path = $this->_path('multiple_failing_rules.php');
		$this->Repo->reset();
		$this->Repo->checkFile($path);
		$this->assertFailedRules(array('spaceIndented', 'doubleEmpty'));
	}

/**
 * testPassesTests method
 *
 * Deliberatly empty.
 * Skip the test method to avoid a potential loop
 *
 * @return void
 * @access public
 */
	function testPassesTests() {
	}

/**
 * testMapControllerToTests method
 *
 * @return void
 * @access public
 */
	function testMapControllerToTests() {
		$expected = array(
			'app',
			'controllers' . DS . 'posts_controller',
			'/var/www/someapp/tests/cases/controllers/posts_controller.test.php'
		);

		$file = TEST_APP . DS . 'controllers' . DS . 'posts_controller.php';
		$result = $this->Repo->_mapToTest($file);
		$this->assertEqual($result, $expected);

		$file = TEST_APP . DS . 'tests' . DS . 'cases' . DS . 'controllers' . DS . 'posts_controller.test.php';
		$result = $this->Repo->_mapToTest($file);
		$this->assertEqual($result, $expected);
	}

/**
 * testMapComponentToTest method
 *
 * @return void
 * @access public
 */
	function testMapComponentToTest() {
		$expected = array(
			'app',
			'components' . DS . 'one',
			'/var/www/someapp/tests/cases/components/one.test.php'
		);

		$file = TEST_APP . DS . 'controllers' . DS . 'components' . DS . 'one.php';
		$result = $this->Repo->_mapToTest($file);
		$this->assertEqual($result, $expected);

		$file = TEST_APP . DS . 'tests' . DS . 'cases' . DS . 'components' . DS . 'one.test.php';
		$result = $this->Repo->_mapToTest($file);
		$this->assertEqual($result, $expected);
	}

/**
 * testMapModelToTests method
 *
 * @return void
 * @access public
 */
	function testMapModelToTests() {
		$expected = array(
			'app',
			'models' . DS . 'post',
			'/var/www/someapp/tests/cases/models/post.test.php'
		);

		$file = TEST_APP . DS . 'models' . DS . 'post.php';
		$result = $this->Repo->_mapToTest($file);
		$this->assertEqual($result, $expected);

		$file = TEST_APP . DS . 'tests' . DS . 'cases' . DS . 'models' . DS . 'post.test.php';
		$result = $this->Repo->_mapToTest($file);
		$this->assertEqual($result, $expected);
	}

/**
 * testMapBehaviorToTest method
 *
 * @return void
 * @access public
 */
	function testMapBehaviorToTest() {
		$expected = array(
			'app',
			'behaviors' . DS . 'one',
			'/var/www/someapp/tests/cases/behaviors/one.test.php'
		);

		$file = TEST_APP . DS . 'models' . DS . 'behaviors' . DS . 'one.php';
		$result = $this->Repo->_mapToTest($file);
		$this->assertEqual($result, $expected);

		$file = TEST_APP . DS . 'tests' . DS . 'cases' . DS . 'behaviors' . DS . 'one.test.php';
		$result = $this->Repo->_mapToTest($file);
		$this->assertEqual($result, $expected);
	}

/**
 * testMapHelperToTest method
 *
 * @return void
 * @access public
 */
	function testMapHelperToTest() {
		$expected = array(
			'app',
			'helpers' . DS . 'one',
			'/var/www/someapp/tests/cases/helpers/one.test.php'
		);

		$file = TEST_APP . DS . 'views' . DS . 'helpers' . DS . 'one.php';
		$result = $this->Repo->_mapToTest($file);
		$this->assertEqual($result, $expected);

		$file = TEST_APP . DS . 'tests' . DS . 'cases' . DS . 'helpers' . DS . 'one.test.php';
		$result = $this->Repo->_mapToTest($file);
		$this->assertEqual($result, $expected);
	}

/**
 * testMapPluginControllerToTests method
 *
 * @return void
 * @access public
 */
	function testMapPluginControllerToTests() {
		$expected = array(
			'test_plugin',
			'controllers' . DS . 'test_plugin_controller',
			'/var/www/someapp/plugins/test_plugin/tests/cases/controllers/test_plugin_controller.test.php'
		);

		$file = TEST_APP . DS . 'plugins' . DS . 'test_plugin' . DS . 'controllers' . DS . 'test_plugin_controller.php';
		$result = $this->Repo->_mapToTest($file);
		$this->assertEqual($result, $expected);

		$file = TEST_APP . DS . 'plugins' . DS . 'test_plugin' . DS . 'tests' . DS . 'cases' . DS . 'controllers' . DS . 'test_plugin_controller.test.php';
		$result = $this->Repo->_mapToTest($file);
		$this->assertEqual($result, $expected);
	}

/**
 * testRules method
 *
 * For any rule that doesn't have a specific test defined - check if there is a test file for it
 * and automatically run the test for it. Therefore defining a rule, and creating rule.php is all
 * that's required - For any none trivial rule it's of course better to have a specific test
 *
 * @return void
 * @access public
 */
	function testRules() {
		$testMethods = get_class_methods($this);
		$rules = $this->Repo->settings['rules'];
		foreach($rules as $rule => $_) {
			if (is_numeric($rule)) {
				$rule = $_;
			}
			$method = Inflector::variable('test_' . $rule);
			if (in_array($method, $testMethods)) {
				continue;
			}
			$this->_testRule($rule);
		}
	}

/**
 * path method
 *
 * @param mixed $file null
 * @return void
 * @access protected
 */
	function _path($file = null) {
		return $this->Repo->params['working'] . $file;
	}

/**
 * testRule method
 *
 * @param mixed $rule
 * @return void
 * @access protected
 */
	function _testRule($rule) {
		$testFiles = $this->Folder->find(Inflector::underscore($rule) . '.*');
		if (!$testFiles) {
			$this->fail("no test defined for rule: $rule");
			return;
		}
		foreach($testFiles as $testFile) {
			$expectPass = strpos($testFile, '_pass.');
			$this->Repo->reset();
			$this->Repo->checkFile($this->_path($testFile));
			if ($expectPass) {
				$this->assertIdentical($this->Repo->returnValue, 0);
			} else {
				$this->assertFailedRules($rule);
			}
		}
		$this->Repo->reset();
	}
}