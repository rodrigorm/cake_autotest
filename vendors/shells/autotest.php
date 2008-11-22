<?php
/**
* 
*/
class Hooks {
	const all_good = 'all_good';
	const initialize = 'initialize';
	const interrupt = 'interrupt';
	const quit = 'quit';
	const ran_command = 'ran_command';
	const reset = 'reset';
	const run_command = 'run_command';
	const waiting = 'waiting';
	const green = 'green';
	const red = 'red';
	
	private function __construct() {}
}


class AutoTestShell extends Shell {
	var $last_mtime = null;
	var $files_to_test = array();
	var $results = null;
	var $folder = null;
	var $tainted = false;
	var $ignore_files = array();
	var $debug = false;
	static $hooks = array();

	function main() {
		App::import('Core', 'Folder');
		$this->folder = new Folder($this->params['working']);

		if (file_exists('./.autotest')) {
			include('./.autotest');
		}

		$this->run();
	}

	function run() {
		$this->_hook(Hooks::initialize);
		do {
			set_time_limit(100);
			$this->_getToGreen();
			if ($this->tainted) {
				$this->_rerunAllTests();
			} else {
				$this->out('All tests passed.');
				$this->_hook(Hooks::all_good);
			}
			$this->_waitForChanges();
		} while (true);
		$this->_hook(Hooks::quit);
	}

	function _findFiles() {
		$files = $this->folder->findRecursive('^[^\.].*\.php');
		if (!empty($this->ignore_files)) {
			foreach ($files as $key => $file) {
				foreach ($this->ignore_files as $ignore) {
					if (preg_match($ignore, $file)) {
						unset($files[$key]);
					}
				}
			}
		}

		return array_values($files);
	}

	function _findFilesToTest() {
		$updated = $this->_findFiles();
		$total = count($updated);
		$times = array();
		for ($i = 0; $i < $total; $i++) {
			$time = filemtime($updated[$i]);
			if (!empty($this->last_mtime) && $time <= $this->last_mtime) {
				unset($updated[$i]);
			} else {
				$times[] = $time;
			}
		}

		$updated = array_values($updated);

		if (!empty($updated)) {
			$this->debug('Updated');
		}

		if (is_array($this->files_to_test)) {
			$merge = array_merge($this->files_to_test, $updated);
		} else {
			$merge = $updated;
		}
		$this->files_to_test = array_unique($merge);

		if (empty($times)) {
			return null;
		}
		return max($times);
	}

	function _mapFilesToTests($files = null) {
		if (empty($files)) {
			$files = $this->files_to_test;
		}
		$files = array_map(array(&$this, '_mapFileToTest'), $files);
		$files = array_unique($files);

		return $files;
	}

	function _mapFileToTest($filename) {
		$file = str_replace($this->params['working'] . DS, '', $filename);
		if (preg_match('|^(plugins\\' . DS . '[^\\' . DS . ']+\\' . DS . ')?tests\\' . DS . '.*\.test\.php$|', $file)) {
			return $filename;
		} else if (preg_match('/.*\.php$/', $file)) {
			if (preg_match('|^plugins\\' . DS . '|', $file)) {
				$pluginFile = preg_replace('|^plugins\\' . DS . '([^\\' . DS . ']+)\\' . DS . '(.*)\.php$|', 'plugins' . DS . '$1' . DS . 'tests' . DS . 'cases' . DS . '$2.test.php', $file);
				
				if (file_exists($this->params['working'] . DS . $pluginFile)) {
					return $this->params['working'] . DS . $pluginFile;
				}
			}

			$file = preg_replace('/(.*)\.php$/', 'tests' . DS . 'cases' . DS . '$1.test.php', $file);
			return $this->params['working'] . DS . $file;
		} else {
			return null;
		}
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
		$new_time = $this->_findFilesToTest();
		if (empty($new_time)) {
			return;
		}
		$this->last_mtime =$new_time;

		$results = array();

		$tests = $this->_mapFilesToTests();

		foreach ($tests as $key => $test) {
			if (!file_exists($test)) {
				$this->out('File test no found: ' . str_replace($this->params['working'] . DS, '', $test));
				continue;
			}

			$out = $this->_runTest($test);

			$results[$test] = $out;
			// $this->out($out);
		}
		$this->_hook(Hooks::ran_command);

		$this->results = $results;
		$this->_handleResults();
	}

	function _runTest($testfile) {
		$case = str_replace($this->params['working'] . DS, '', $testfile);
		$case = str_replace('.test.php', '', $case);
		$this->debug('Testing: ' . $case);

		$category = 'app';
		if (preg_match('|^plugins\\' . DS . '([^\\' . DS . ']+)|', $case, $matchs)) {
			$category = $matchs[1];
			$case = str_replace('plugins' . DS . $category . DS, '', $case);
			// $case = preg_replace('|^plugins\\' . DS . '([^\\' . DS . ']+)|', '', $case);
		}
		$case = str_replace('tests' . DS . 'cases' . DS, '', $case);

		return shell_exec('./cake/console/cake testsuite ' . $category . ' case ' . $case);
	}

	function _handleResults() {
		$this->files_to_test = array();

		foreach ($this->results as $file => $result) {
			$failed = preg_match('/\d+\/\d+ test cases complete: \d+ passes, \d+ fails(, \d+ exceptions)?./im', $result, $matchFailed);
			$completed = preg_match('/\d+\/\d+ test cases complete: \d+ passes\./', $result, $matchCompleted);
			
			if ($failed) {
				$this->files_to_test[] = $file;
				$this->out($matchFailed[0]);
			} else {
				$this->out($matchCompleted[0]);
			}
		}
		if (empty($this->files_to_test)) {
			$this->files_to_test = null;
			$this->_hook(Hooks::green);
		} else {
			$hook = Hooks::red;
			$this->tainted = true;
			$this->_hook(Hooks::red, $this->files_to_test);
		}
	}

	function _waitForChanges() {
		$this->_hook(Hooks::waiting);
		do {
			set_time_limit(100);
			$time = $this->_findFilesToTest();
			// sleep(1);
		} while (is_null($time));
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
		$this->tainted = false;

		$this->_hook(Hooks::reset);
	}

	function debug($message) {
		if (!$this->debug) {
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
}
?>