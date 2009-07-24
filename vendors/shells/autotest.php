<?php
/**
 * Short description for autotest.php
 *
 * Long description for autotest.php
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
 * @package       cake_autotest
 * @subpackage    cake_autotest.vendors.shells
 * @since         v 1.0 (22-Jul-2009)
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */
App::import('Vendor', 'Autotest.Notify');

/**
 * Hooks class
 *
 * @uses
 * @package       cake_autotest
 * @subpackage    cake_autotest.vendors.shells
 */
class Hooks {
	const all_good    = 'all_good';
	const initialize  = 'initialize';
	const interrupt   = 'interrupt';
	const quit        = 'quit';
	const ran_command = 'ran_command';
	const reset       = 'reset';
	const run_command = 'run_command';
	const waiting     = 'waiting';
	const green       = 'green';
	const red         = 'red';

/**
 * construct method
 *
 * @return void
 * @access private
 */
	private function __construct() {}
}

/**
 * AutoTestShell class
 *
 * @uses          Shell
 * @package       cake_autotest
 * @subpackage    cake_autotest.vendors.shells
 */
class AutoTestShell extends Shell {

/**
 * last_mtime property
 *
 * @var mixed null
 * @access public
 */
	public $last_mtime    = null;

/**
 * files_to_test property
 *
 * @var array
 * @access public
 */
	public $files_to_test = array();

/**
 * results property
 *
 * @var mixed null
 * @access public
 */
	public $results       = null;

/**
 * Enter Description Here
 */
	static $hooks      = array();

/**
 * fails property
 *
 * @var array
 * @access public
 */
	public $fails = array();

/**
 * settings property
 *
 * @var array
 * @access public
 */
	public $settings = array(
		'interval' => 0.05, // 0.05 minutes = every 3s
		'debug' => false,
		'ignorePatterns' => array(
			'/index\.php/',
			'/(config|locale|tmp|tests|webroot)\//'
		),
		'notify' => null,
		'checkAllOnStart' => true
	);

	function initialize() {
		if (file_exists('config' . DS . 'autotest.php')) {
			include('config' . DS . 'autotest.php');
			if (!empty($config)) {
				$this->settings = am($this->settings, $config);
			}
		} elseif (file_exists(APP . 'config' . DS . 'autotest.php')) {
			include(APP . 'config' . DS . 'autotest.php');
			if (!empty($config)) {
				$this->settings = am($this->settings, $config);
			}
		}
	}

/**
 * main method
 *
 * @return void
 * @access public
 */
	function main() {
		App::import('Core', 'Folder');
		if (file_exists($this->params['working'] . DS . '.autotest')) {
			include($this->params['working'] . DS . '.autotest');
		}
		if (!empty($this->params['notify'])) {
			$this->settings['notify'] = $this->params['notify'];
		}

		Notify::$method = $this->settings['notify'];
		$this->addHooks();
		Notify::message('AutoTest Starting', 'in ' . $this->params['working'], 0, false);
		$this->buildPaths();
		$this->run();
	}

/**
 * addHooks method
 *
 * @return void
 * @access public
 */
	function addHooks() {
		AutoTestShell::addHook(Hooks::green, array('Notify', 'green'));
		AutoTestShell::addHook(Hooks::red, array('Notify', 'red'));
		AutoTestShell::addHook(Hooks::all_good, array('Notify', 'allGood'));
	}

/**
 * buildPaths method
 *
 * @return void
 * @access public
 */
	function buildPaths() {
		$this->paths = array('console' => array_pop(Configure::corePaths('cake')) . 'console' . DS . 'cake');
	}

/**
 * run method
 *
 * @return void
 * @access public
 */
	function run() {
		$this->_hook(Hooks::initialize);
		do {
			$this->_getToGreen();
			$this->_rerunAllTests();
			$this->_waitForChanges();
		} while (true);
		$this->_hook(Hooks::quit);
	}

/**
 * getToGreen method
 *
 * @return void
 * @access protected
 */
	function _getToGreen() {
		do {
			$this->_runTests();
			if (!$this->_allGood()) {
				$this->_waitForChanges();
			}
		} while (!$this->_allGood());
	}

/**
 * runTests method
 *
 * @return void
 * @access protected
 */
	function _runTests() {
		$this->_hook(Hooks::run_command);
		if (!$this->files_to_test) {
			$this->files_to_test = $this->_findFiles();
		}
		if (!$this->files_to_test) {
			return;
		}

		$this->results = array(
			'complete' => array(),
			'skipped' => array(),
			'failed' => array(),
		);
		foreach($this->files_to_test as $file) {
			$result = $this->_runTest($file);
			if (strpos($result, '✔')) {
				$this->results['complete'][$file] = '✔';//$result;
				unset($this->fails[$file]);
			} elseif (strpos($result, '❯')) {
				$this->results['skipped'][$file] = '❯';//$result;
				unset($this->fails[$file]);
			} elseif (strpos($result, '✘')) {
				$this->results['failed'][$file] = '✘';//$result;
				$this->fails[$file] = $file;
			} else {
				$this->results['unknown'][$file] = '?';//$result;
			}
			$this->out($result);
		}
		$this->_hook(Hooks::ran_command);

		$total = -count($this->results['skipped']);
		foreach(array('complete', 'skipped', 'failed', 'unknown') as $type) {
			if (empty($this->results[$type])) {
				continue;
			}
			$total += count($this->results[$type]);
			$this->results[$type . ' files'] = $this->results[$type];
			$this->results[$type] = count($this->results[$type]);
		}
		$this->results['total'] = $total;

		if (empty($this->results['failed']) && empty($this->results['unknown'])) {
			$this->_hook(Hooks::green, array_filter($this->results));
		} else {
			unset ($this->results['complete files']);
			$this->_hook(Hooks::red, $this->results['failed'], array_filter($this->results));
		}
	}

/**
 * runTest method
 *
 * @param mixed $file
 * @return void
 * @access protected
 */
	function _runTest($file) {
		$out = exec($this->paths['console'].' -app '.$this->params['working'].' repo checkFile ' . $file, $_, $return);
		return implode($_, "\n");
	}

/**
 * waitForChanges method
 *
 * @return void
 * @access protected
 */
	function _waitForChanges() {
		$this->_hook(Hooks::waiting);
		do {
			sleep($this->settings['interval'] * 60);
			$changedFiles = $this->_findFiles();
			$files = array_unique(am($changedFiles, array_values((array)$this->fails)));
			$this->files_to_test = $files;
		} while (!$changedFiles);
	}

/**
 * allGood method
 *
 * @return void
 * @access protected
 */
	function _allGood() {
		return empty($this->fails);
	}

/**
 * rerunAllTests method
 *
 * @return void
 * @access protected
 */
	function _rerunAllTests() {
		$this->_reset();
		$this->_runTests();
		if ($this->_allGood()) {
			$this->out('All tests passed.');
			$this->_hook(Hooks::all_good);
		}
	}

/**
 * reset method
 *
 * @return void
 * @access protected
 */
	function _reset() {
		$this->files_to_test = null;
		$this->last_mtime = null;

		$this->_hook(Hooks::reset);
	}

/**
 * debug method
 *
 * @param mixed $message
 * @return void
 * @access public
 */
	function debug($message) { // @ignore
		if (!$this->settings['debug']) {
			return;
		}
		$this->out($message);
	}

/**
 * addHook method
 *
 * @param mixed $hook
 * @param mixed $callback
 * @return void
 * @access public
 */
	static function addHook($hook, $callback) {
		if (!is_callable($callback)) {
			return false;
		}

		if (empty(AutoTestShell::$hooks[$hook])) {
			AutoTestShell::$hooks[$hook] = array();
		}
		AutoTestShell::$hooks[$hook][] = $callback;

		return true;
	}

/**
 * hook method
 *
 * @param mixed $hook
 * @return void
 * @access protected
 */
	function _hook($hook) {
		$params = func_get_args();
		array_shift($params);

		if (empty(AutoTestShell::$hooks[$hook])) {
			return false;
		}

		foreach (AutoTestShell::$hooks[$hook] as $callback) {
			call_user_func_array($callback, $params);
		}
	}

/**
 * findFiles method
 *
 * @param mixed $dir null
 * @return void
 * @access protected
 */
	function _findFiles($dir = null) {
		if (!$dir) {
			$dir = $this->params['working'];
		}

		if (!$this->last_mtime && !$this->settings['checkAllOnStart']) {
			$this->last_mtime = time();
			return array();
		}
		if (DS === '\\') {
			if (empty($this->Folder)) {
				$this->Folder = new Folder($dir);
			}
			$files = $this->Folder->findRecursive('.*\.php$');
		} else {
			$suffix = '';
			$sinceLast = time() - $this->last_mtime;
			if ($this->last_mtime) {
				// $suffix = ' -mmin ' . ceil($sinceLast / 60);
			}
			$cmd = 'find ' . $dir . ' ! -ipath "*.svn*" \
			! -ipath "*.git*" ! -iname "*.git*" ! -ipath "*/tmp/*" ! -ipath "*webroot*" \
			! -ipath "*Zend*" ! -ipath "*simpletest*" ! -ipath "*firephp*" \
			! -iname "*jquery*" ! -ipath "*Text*" -name "*.php" -type f' . $suffix;
			exec($cmd, $files);
		}
		$files = array_unique($files);
		sort($files);
		if (!empty($this->settings['ignorePatterns'])) {
			foreach ($files as $key => $file) {
				foreach ($this->settings['ignorePatterns'] as $ignore) {
					if (preg_match($ignore, $file)) {
						unset($files[$key]);
					}
				}
			}
		}
		$lastMTime = 0;
		foreach ($files as $key => $file) {
			$time = filemtime($file);
			if (!empty($this->last_mtime) && $time <= $this->last_mtime) {
				unset ($files[$key]);
				continue;
			}
			if ($time > $lastMTime) {
				$lastMTime = $time;
			}
		}
		if ($lastMTime > $this->last_mtime) {
			$this->last_mtime = $lastMTime;
		}
		return array_values($files);
	}
}