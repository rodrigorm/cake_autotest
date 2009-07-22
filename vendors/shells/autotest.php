<?php
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

	private function __construct() {}
}

class AutoTestShell extends Shell {
	var $last_mtime    = null;
	var $files_to_test = array();
	var $results       = null;
	static $hooks      = array();

	public $settings = array(
		'interval' => 0.01, // 0.05 minutes = every 3s
		'debug' => false,
		'ignorePatterns' => array(
			'/index\.php/',
			'/(config|locale|tmp|tests|webroot)\//'
		),
		'notify' => null,
		'checkAllOnStart' => true
	);

	function main() {
		App::import('Core', 'Folder');
		if (file_exists($this->params['working'] . DS . '.autotest')) {
			include($this->params['working'] . DS . '.autotest');
		}
		if (!empty($this->params['notify'])) {
			$this->settings['notify'] = $this->params['notify'];
		} elseif (DS === '/') {
			$Folder = new Folder(dirname(__FILE__) . '/autotest');
			$notifiers = $Folder->find('.*\.php$');
			foreach($notifiers as $notifyProg) {
				$notifyProg = str_replace('.php', '', $notifyProg);
				system('which ' . $notifyProg, $return);
				if ($return) {
					continue;
				}
				$this->settings['notify'] = $notifyProg;
				break;
			}
		}
		if ($this->settings['notify']) {
			include('autotest/' . $this->settings['notify'] . '.php');
		}

		$this->buildPaths();
		$this->run();
	}

	function buildPaths(){
		$this->paths = array(
			'console' => array_pop(Configure::corePaths('cake')) . 'console' . DS . 'cake',
		);
	}

	function run() {
		$this->_hook(Hooks::initialize);
		do {
			$this->_getToGreen();
			$this->_rerunAllTests();
			$this->_waitForChanges();
		} while (true);
		$this->_hook(Hooks::quit);
	}

	function _getToGreen() {
		do {
			$this->_runTests();
			if (!$this->_allGood()) {
				$this->_waitForChanges();
			}
		} while (!$this->_allGood());
	}

	function _runTests() {
		$this->_hook(Hooks::run_command);
		$this->files_to_test = $this->_findFiles();
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
			} elseif (strpos($result, '❯')) {
				$this->results['skipped'][$file] = '❯';//$result;
			} elseif (strpos($result, '✘')) {
				$this->results['failed'][$file] = '✘';//$result;
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

	function _runTest($file) {
		$out = exec($this->paths['console'].' -app '.$this->params['working'].' repo checkFile ' . $file, $_, $return);
		return implode($_, "\n");
	}

	function _waitForChanges() {
		$this->_hook(Hooks::waiting);
		do {
			$this->files_to_test = $this->_findFiles();
			sleep($this->settings['interval'] * 60);
		} while (!$this->files_to_test);
	}

	function _allGood() {
		return empty($this->files_to_test);
	}

	function _rerunAllTests() {
		$this->_reset();
		$this->_runTests();
		if ($this->_allGood()) {
			$this->out('All tests passed.');
			$this->_hook(Hooks::all_good);
		}
	}

	function _reset() {
		$this->files_to_test = null;
		$this->last_mtime = null;

		$this->_hook(Hooks::reset);
	}

	function debug($message) {
		if (!$this->settings['debug']) {
			return;
		}
		$this->out($message);
	}

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

	function _findFiles($dir = null, $modifiedMins = null) {
		if (!$dir) {
			$dir = $this->params['working'];
		}
		if ($modifiedMins === null) {
			if (!$this->settings['checkAllOnStart']) {
				$modifiedMins = $this->settings['interval'];
			}
		}

		if (DS === '\\') {
			if (empty($this->Folder)) {
				$this->Folder = new Folder($dir);
			}
			$files = $this->Folder->findRecursive('.*\.php$');
			if ($modifiedMins) {
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
					$this->last_mtime = $time;
				}
			}
			if ($this->settings['checkAllOnStart']) {
				$files = array();
			}
		} else {
			$suffix = '';
			if ($modifiedMins) {
				$suffix = ' -mmin ' . $modifiedMins * 1.1;
			}
			$cmd = 'find ' . $dir . ' ! -iwholename "*.svn*" \
			! -iwholename "*.git*" ! -iwholename "*/tmp/*" ! -iwholename "*webroot*" \
			! -iwholename "*Zend*" ! -iwholename "*simpletest*" ! -iwholename "*firephp*" \
			! -iwholename "*jquery*" ! -iwholename "*Text*" -name "*.php" -type f' . $suffix;
			exec($cmd, $files);
		}
		if (!empty($this->settings['ignorePatterns'])) {
			foreach ($files as $key => $file) {
				foreach ($this->settings['ignorePatterns'] as $ignore) {
					if (preg_match($ignore, $file)) {
						unset($files[$key]);
					}
				}
			}
		}
		if ($this->settings['checkAllOnStart']) {
			$this->settings['checkAllOnStart'] = false;
		}
		return array_values($files);
	}
}