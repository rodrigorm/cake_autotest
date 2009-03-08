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
	var $folder        = null;
	var $ignore_files  = array();
	var $debug         = false;
	static $hooks      = array();

	function main() {
		App::import('Core', 'Folder');
		$this->folder = new Folder($this->params['working']);
		$this->buildPaths();
		if (file_exists($this->params['working'] . DS . '.autotest')) {
			include($this->params['working'] . DS . '.autotest');
		}
		$this->run();
	}

	function buildPaths(){
		$this->paths = array(
			'console' => array_pop(Configure::corePaths('cake')) . 'console' . DS . 'cake',
			'img'     => array_pop(Configure::read('pluginPaths')) . 'cake_autotest' . DS . 'vendors' . DS . 'img' . DS,
			'libs'    => array_pop(Configure::read('pluginPaths')) . 'cake_autotest' . DS . 'vendors' . DS . 'shells' . DS . 'autotest' . DS
		);
	}

	function run() {
		$this->_hook(Hooks::initialize);
		do {
			set_time_limit(100);
			$this->_getToGreen();

			$this->_rerunAllTests();

			$this->_waitForChanges();
		} while (true);
		$this->_hook(Hooks::quit);
	}

	function _findFiles() {
		$files = $this->folder->findRecursive('^[^\\.].*\.php');
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

		if (preg_match('|^(plugins\\' . DS . '[^\\' . DS . ']+\\' . DS . ')?tests\\' . DS . '.*\\.test\\.php$|', $file)) {
			return $filename;
		}

		preg_match('/^([^\\' . DS . ']+)\\' . DS . '([^\\' . DS . ']+)(\\' . DS . '([^\\' . DS . ']+)\\' . DS . '([^\\' . DS . ']+))?/i', $file, $match);
		if (empty($match)) {
			return $this->params['working'] . DS . str_replace('.php', '.test.php', $file);
		}
		$plugin = null;
		$type = $match[1];
		$subType = $match[2];
		
		if ($type == 'plugins') {
			$plugin = $subType;
			$type = $match[4];
			$subType = $match[5];
		}
		
		$dirname = dirname($file);
		$basename = basename($file, '.php');

		$path = $type;
		if ($subType == 'components' || $subType == 'behaviors' || $subType == 'helpers') {
			$path = $subType;
		}
		$path = 'tests' . DS . 'cases' . DS . $path;
		if (!empty($plugin)) {
			$path = 'plugins' . DS . $plugin . DS . $path;
		}

		return $this->params['working'] . DS . $path . DS . $basename . '.test.php';
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
		$this->last_mtime = $new_time;

		$results = array();

		$tests = $this->_mapFilesToTests();

		foreach ($tests as $key => $test) {
			if (!file_exists($test)) {
				$this->out('File test not found: ' . str_replace($this->params['working'] . DS, '', $test));
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
		
		return shell_exec($this->paths['console'].' -app '.$this->params['working'].' testsuite ' . $category . ' case ' . $case);
	}

	function _handleResults() {
		$this->files_to_test = array();
		if (empty($this->results)) {
			return;
		}

		$params = array(
			'complete'   => 0, 
			'total'      => 0, 
			'passes'     => 0, 
			'fails'      => 0, 
			'exceptions' => 0
		);
		foreach ($this->results as $file => $result) {
			$completed = preg_match('/(?<complete>\d+)\/(?<total>\d+) test cases complete: (?<passes>\d+) passes\\./', $result, $matchCompleted);
			$failed = preg_match('/(?<complete>\d+)\/(?<total>\d+) test cases complete: (?<passes>\d+) passes, (?<fails>\d+) fails(, (?<exceptions>\d+) exceptions)?./im', $result, $matchFailed) || empty($matchCompleted['total']);

			$match = null;

			if ($failed) {
				$this->files_to_test[] = $file;
				$match = $matchFailed;
			} else if ($completed) {
				$match = $matchCompleted;
			}
			if (!empty($match[0])) {
				$this->out($match[0]);
			}
			foreach ($params as $key => $value) {
				if (isset($match[$key])) {
					$params[$key] += (int)$match[$key];
				}
			}
		}
		if (empty($this->files_to_test)) {
			$this->files_to_test = null;
			$this->_hook(Hooks::green, $params);
		} else {
			$this->_hook(Hooks::red, $this->files_to_test, $params);
		}
	}

	function _waitForChanges() {
		$this->_hook(Hooks::waiting);
		do {
			set_time_limit(100);
			$time = $this->_findFilesToTest();
			sleep(1);
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