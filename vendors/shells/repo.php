<?php
/**
 * A utility shell to do all the things you want your repo to do for you upon commit/merge
 *
 * This shell will perform basic syntax checks
 *
 * Usage:
 * 	# Test all app files
 * 	cake repo checkFiles
 * 	# Test all model files
 * 	cake repo checkFiles models
 * 	# Test this specific file
 * 	cake repo checkFile some/file.php
 *
 * Original idea from http://phpadvent.org/2008/dont-commit-that-error-by-travis-swicegood
 *
 * PHP versions 4 and 5
 *
 * Copyright (c) 2009, Andy Dawson
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @see           vendors/pre-commit
 * @filesource
 * @copyright     Copyright (c) 2009, Andy Dawson
 * @link          www.ad7six.com
 * @package       autotest
 * @subpackage    autotest.vendors.shells
 * @since         v 1.0 (03-Jul-2009)
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

/**
 * RepoShell class
 *
 * @uses          Shell
 * @package       autotest
 * @subpackage    autotest.vendors.shells
 */
class RepoShell extends Shell {

/**
 * tasks property
 *
 * @var array
 * @access public
 */
	var $tasks = array('Find');

/**
 * name property
 *
 * @var string 'Repo'
 * @access public
 */
	var $name = 'Repo';

/**
 * errors property
 *
 * Array of filenames that contain errors
 *
 * @var array
 * @access public
 */
	var $errors = array();

/**
 * messages property
 *
 * Suppressed messages, temporary storage so that messages appear e.g.
 * Errors Found:
 * 	Foo
 * 	Bar
 *
 * @var array
 * @access public
 */
	var $messages = array();

/**
 * returnValue property
 *
 * @var int 0
 * @access public
 */
	var $returnValue = 0;

/**
 * current file data
 *
 * @var array
 * @access public
 */
	var $current = array();

/**
 * methods property
 *
 * This class' methods
 *
 * @var array
 * @access public
 */
	var $methods = array();

/**
 * settings property
 *
 * Run time settings @see initialize
 *
 * @var array
 * @access public
 */
	var $settings = array();

/**
 * defaultSettings property
 *
 * quiet - suppress most output
 * that have been generated will be output
 * logLevel - limit what sort of messages are shown. careful with 'debug' - very verbose
 * vimTips - write tips into error messages
 * suppressDuplicateErrors - if the same error appears 1+ times in a file, only report the first error
 * rules - array of name => params
 * 	rule => the name of a method, or a regex to check
 * 	last => if this rule fails - bail on the rest
 * 	logLevel => if the method fails - the log level for the failure message
 * 	isError => true/false allows for a rule to generate a message without failing
 *
 * @var array
 * @access protected
 */
	var $_defaultSettings = array(
		'repoType' => 'git',
		'quiet' => false,
		'logLevel' => 'notice', // 'err', 'warning', 'notice', 'info', 'debug'
		'vimTips' => true,
		'dontDeletePattern' => '@(tmp[\\\/].*empty)@',
		'excludePattern' => null,
		'includePattern' => null,
		'skipTests' => '@(test_app[\\\/])@',
		'suppressDuplicateErrors' => true,
		'rules' => array(
			'skipFile' => array(
				'isError' => false,
				'last' => true,
				'logLevel' => 'info',
				'message' => 'Skipped: @noverify marker found',
				'rule' => '/@noverify\r?\n/s'
			),
			'mergeConflict' => array(
				'last' => true,
				'rule' => '/\n([<>=])\1{6}/s'
			),
			'noCommit' => array(
				'last' => true,
				'rule' => '/@nocommit\r?\n/s',
			),
			'phpLint' => array(
				'last' => true,
				'message' => false,
			),
			'debug' => array(
				'rule' => '/(?!function[^\r\n]*)(print_r|var_dump|debug)\s*\((?![^\r\n]*@ignore)/s',
			),
			'leadingWhitespace' => array(
				'singleMatch' => true,
			),
			'newLineAtEndOfFile' => array(
				'rule' => '/[\s\r\n]$/s',
				'singleMatch' => true,
				'vimTip' => 'Open and save using the Cakephp plugin to avoid this'
			),
			'php53DeprecatedAssignValueOfNewByReference' => array(
				'rule' => '/=[ \t]*&[ \t]*new/s',
			),
			'doubleEmpty' => array(
				'logLevel' => 'warning',
				'message' => 'Two or more empty lines found',
				'rule' => '/(\r?\n){3,}/s',
				'vimTip' =>':v/./,/./-j',
			),
			'indentedCommentBlock' => array(
				'logLevel' => 'warning',
				'rule' => '/[ \t]+\/\*\*(?![^\r\n]*@ignore)/s',
			),
			'noSpaceAfterCommaInFunctionDeclaration' => array(
				'logLevel' => 'warning',
				'rule' => '/\n[^\r\n\'\*"]*function[^\r\n]*\([^\r\n]*,[^ \'"](?![^\r\n]*@ignore)/s',
			),
			'noSpaceBetweenClosingBracketAndCurlyBrace' => array(
				'logLevel' => 'warning',
				'rule' => '/\){(?![^\r\n]*@ignore)\r?\n/s',
			),
			'spaceBeforeClosingBracketInFunctionDeclaration' => array(
				'logLevel' => 'warning',
				'rule' => '/\n[^\r\n\'\*"]*function[^\r\n]* \)[^\r\n]*{(?![^\r\n]*@ignore)/s',
			),
			'spaceBeforeCommaInFunctionDeclaration' => array(
				'logLevel' => 'warning',
				'rule' => '/\n[^\r\n\'\*"]*function[^\r\n]* ,(?![^\r\n]*@ignore)/s',
			),
			'spaceBeforeFirstParameterInFunctionDeclaration' => array(
				'logLevel' => 'warning',
				'rule' => '/\n[^\r\n\'\*"]*function[^\r\n]*\( (?![^\r\n]*@ignore)/s',
			),
			'spaceBeforeOpeningBracketInFunctionCall' => array(
				'logLevel' => 'warning',
				'rule' => '/\n[^\r\n\'\*"]*\w(->|::)[\w\d]+ \((?![^\r\n]*@ignore)/s',
			),
			'spaceBetweenFunctionNameAndBracket' => array(
				'logLevel' => 'warning',
				'rule' => '/\n[^\r\n\'\*"]*function &?\w+ \((?![^\r\n]*@ignore)/s',
			),
			'spaceIndented' => array(
				'logLevel' => 'warning',
				'rule' => '/\n {2,}/s',
				'vimTip' => ':%s/^\(\t*\) \{4}/\1\t/gc'
			),
			'trailingWhitespace' => array(
				'logLevel' => 'warning',
				'rule' => '/(?!\*)[ \t]\r?\n/s',
				'vimTip' => ':%s/\(\*\)\@<!\s\+$//g'
			),
			/*
			'trailingPhpTag' => array(
				'logLevel' => 'warning',
				'rule' => '/\?>[\r?\n]*$/s',
			),
			*/
			'windowsNewLine' => array(
				'logLevel' => 'warning',
				'rule' => '/\r/s',
				'vimTip' => ':%s/\r//g'
			),
			'passesTests' => array(
				'failMissingTests' => false,
				'message' => 'Fails the test case',
			),
		)
	);

/**
 * logLevel property
 *
 * @var array
 * @access protected
 */
	var $_logLevel = array(
		'err' => 3,
		'warning' => 4,
		'notice' => 5,
		'info' => 6,
		'debug' => 7
	);

/**
 * oldRules property
 *
 * @TODO port to ^
 * @var array
 * @access protected
 */
	var $_oldRules = array(
		'all' => array(
			'Code', // Marker for Code checks
			// So long as it's not inside a string
			// Below rules may give false negatives
			'/\w+->[^\w\d]+\( /' => 'Space before first parameter in function call',
			'/\w+->.+\ ,/' => 'Space before comma in function call',
			// Quotes added to regex to avoid "," being captured
			'/\w+->.+\,[^ \'"].+/' => 'No Space found after comma in function call',
			'/\w+->.+ \)/' => 'Space before closing parenthases in function call',
			// none capture ($foo-> or "foo" or 'foo')(nospace)(= or != or .= or =& or == or !== or ===  or => or ||)(nospace)
			'/(?:\$[\w->]+|[\'"].*[\'"])[^\t ](?:!?\.?=+&?|=>|\|\|)[^ ]/' => 'No spaces around assignment/logic test',
		),
	);

/**
 * testResults property
 *
 * Track test results to prevent running duplicate test cases
 *
 * @var array
 * @access protected
 */
	var $_testResults = array(
		'_summary' => array(
			'caseTotal' => 0,
			'casePass' => 0,
			'passes' => 0,
			'fails' => 0,
			'exceptions' => 0,
			'missing' => 0,
		)
	);

/**
 * help method
 *
 * @return void
 * @access public
 */
	function help() {
		$name = Inflector::underscore($this->name);
		$methods = array_diff(get_class_methods($this), array('main'), get_class_methods('Shell'));
		$this->out('Available methods');
		foreach ($methods as $method) {
			if ($method[0] !== '_') {
				$this->out("\tcake $name $method");
			}
		}
	}

/**
 * initialize method
 *
 * To change the rules used, or any setting; create config/repo.php with:
 * 	$config = array(
 * 		'someSetting' => 'foo',
 * 		'rules => array(
 * 			'full',
 * 			'rule',
 * 			'list'
 * 		)
 * 	);
 * Rules can be added/merged by referring to $this->settings inside the config file
 *
 * Check for quiet mode
 *
 * @return void
 * @access public
 */
	function initialize() {
		$this->methods = array_map('strtolower', get_class_methods($this));

		$this->settings = $this->_defaultSettings;
		if (!empty($this->params['config'])) {
			if (file_exists($this->params['config'])) {
				include($this->params['config']);
				if (!empty($config)) {
					$this->settings = am($this->settings, $config);
				}
			}
		} elseif (file_exists('config' . DS . 'repo.php')) {
			include('config' . DS . 'repo.php');
			if (!empty($config)) {
				$this->settings = am($this->settings, $config);
			}
		} elseif (file_exists(APP . 'config' . DS . 'repo.php')) {
			include(APP . 'config' . DS . 'repo.php');
			if (!empty($config)) {
				$this->settings = am($this->settings, $config);
			}
		}
		if (!empty($this->params['q'])) {
			$this->settings['quiet'] = true;
			$this->settings['logLevel'] = 'warning';
		}
		if (!empty($this->params['v'])) {
			$this->settings['quiet'] = false;
			$this->settings['logLevel'] = 'info';
		}
		if (!empty($this->params['mode'])) {
			switch($this->params['mode']) {
				case 'sanity':
					$this->settings['rules'] = array(
						'skipFile' => $this->settings['rules']['skipFile'],
						'mergeConflict' => $this->settings['rules']['mergeConflict'],
						'phpLint' => $this->settings['rules']['phpLint'],
						'leadingWhitespace'
					);
					break;
				case 'test':
				case 'tests':
					$this->settings['rules'] = array(
						'passesTests' => $this->settings['rules']['passesTests']
					);
					break;
				case 'quality':
					unset($this->settings['rules']['passesTests']);
					break;
				default:
					$rules = $this->settings['rules'];
					$this->settings['rules'] = array();
					foreach (explode(',', $this->params['mode']) as $rule) {
						if (isset($rules[$rule])) {
							$this->settings['rules'][$rule] = $rules[$rule];
						}
					}
			}
		}
		$this->_buildPaths();
	}

/**
 * main method
 *
 * Show help if no params passed
 *
 * @return void
 * @access public
 */
	function main() {
		$this->help();
	}

/**
 * checkFiles method
 *
 * @return void
 * @access public
 */
	function checkFiles() {
		$files = $this->_listFiles();
		$count = count($files);
		$suffix = '';
		$this->out(sprintf('%s Files found' . $suffix, $count));
		$this->settings['_supressMessages'] = true;
		foreach ($files as $i => $file) {
			$this->out($file . ' ', false);
			if (!file_exists($file)) {
				if (empty($this->settings['dontDeletePattern']) || !preg_match($this->settings['dontDeletePattern'], $file)) {
					$this->out('❯');
					continue;
				}
				$this->errors[$file]['dontDeletePattern'][0] = 'An empty file has been deleted';
				$this->returnValue = 1;
				$this->out('✘');
			} elseif(!preg_match('@(.*\.php|.*\.ctp)$@', $file) ||
				(!empty($this->settings['includePattern']) && !preg_match($this->settings['includePattern'], $file))) {
				$this->out('❯');
				continue;
			} elseif ($this->checkFile($file)) {
				$this->out('✔');
			} else {
				$this->out('✘');
			}
		}
		$this->out(null);
		$this->_printMessages();
		$this->hr();
		$this->_printErrors($count);
		extract ($this->_testResults['_summary']);
		$this->out(sprintf('%s/%s Test cases complete: %s passes, %s fails, %s exceptions, %s missing test cases.',
			$casePass, $caseTotal, $passes, $fails, $exceptions, $missing));
		$this->_stop();
	}

/**
 * checkFile method
 *
 * Add some simple in a comment/not in a comment detection, and process each line one by one
 *
 * @param mixed $file
 * @return void
 * @access public
 */
	function checkFile($file = null) {
		if (!$file) {
			if (!empty($this->args) && file_exists($this->args[0])) {
				$file = $this->args[0];
				$this->out(str_replace($this->params['working'] . DS, '', $file) . ' ', false);
			} else {
				$this->out("No arguments, or file doesn't exist (" . $this->args[0] . ")");
				return;
			}
		}
		$string = file_get_contents($file);
		$this->_reset(array(
			'file' => $file,
		));
		$this->checkString($string);
		$result = false;
		if (empty($this->errors[$this->current['file']])) {
			$result = true;
		}
		if ($this->command != 'checkFile') {
			return $result;
		}

		if ($this->_logLevel[$this->settings['logLevel']] >= $this->_logLevel['notice']) {
			$this->out($file . ' ', false);
		}
		if ($result) {
			$this->out('✔');
		} else {
			$this->out('✘');
			$this->err(Debugger::trimPath($file) . ' ' . '✘');
		}
		$this->_printErrors(1);
	}

/**
 * checkString method
 *
 * Modified model validation routine
 *
 * @param string $string ''
 * @return void
 * @access public
 */
	function checkString($string = null) {
		if ($string === null) {
			if (!empty($this->args)) {
				$string = $this->args[0];
				$this->_reset(array(
					'file' => 'virtual',
				));
			} else {
				return;
			}
		}
		$_string = trim($string);
		$this->_log("Testing line >>>$_string<<<", null, 'debug');

		$default = array(
			'rule' => null,
			'last' => false,
			'logLevel' => 'err',
			'isError' => true,
		);
		foreach ($this->settings['rules'] as $index => $validator) {
			if (!is_array($validator)) {
				$index = $validator;
				$validator = array('rule' => $validator);
			} elseif (!isset($validator['rule'])) {
				$validator['rule'] = $index;
			}
			$validator = array_merge($default, $validator);
			if (isset($validator['message'])) {
				$message = $validator['message'];
			} else {
				$message = Inflector::humanize(Inflector::underscore($index));
			}
			$this->current['rule'] = $index;

			$testString = $string;
			$this->_log("Testing rule {$validator['rule']}", null, 'debug');

			if (is_array($validator['rule'])) {
				$rule = $validator['rule'][0];
				unset($validator['rule'][0]);
				$ruleParams = array_merge(array($testString), array_values($validator['rule']));
			} else {
				$rule = $validator['rule'];
				$ruleParams = array($testString);
			}
			$valid = true;

			$_rule = '_' . Inflector::variable('check_' . $rule);
			if (in_array(strtolower($_rule), $this->methods)) {
				$ruleParams[] = $validator;
				$ruleParams[0] = array($testString);
				$valid = call_user_func_array(array($this, $_rule), $ruleParams);
			} elseif (!is_array($validator['rule'])) {
				$valid = !preg_match($rule, $testString);
			}
			if (!$valid || (is_string($valid) && strlen($valid) > 0)) {
				if (is_string($valid) && strlen($valid) > 0) {
					$message = $valid;
				}

				if ($message) {
					if ($rule[0] === '/' && empty($validator['singleMatch'])) {
						$newRegex = '/(.*?)' . substr($rule, 1);
						preg_match_all($newRegex, $testString, $matches);
						if ($matches) {
							$lineNo = 1;
							foreach($matches[0] as $match) {
								$lineNo += substr_count($match, "\n");
								$this->current['lineNo'] =  $lineNo;
								$this->_log($message, $index, $validator['logLevel'],  $validator['isError']);
							}
						}
						$lineNo = '*';
					} else {
						$this->_log($message, $index, $validator['logLevel'],  $validator['isError']);
					}
				}

				if ($validator['last']) {
					$this->current['finished'] = true;
					break;
				}
			}
		}
	}

/**
 * linkPreCommit method
 *
 * @return void
 * @access public
 */
	function linkPreCommit() {
		$source = realpath(dirname(dirname(__FILE__))) . DS . 'pre-commit';
		$files = am($this->Find->files('pre-commit.sample', false), $this->Find->files('pre-commit', false));
		$files = array_unique($files);
		foreach($files as &$file) {
			$file = str_replace('.sample', '', $file);
		}
		$files = array_unique($files);
		$total = count($files);

		foreach($files as &$file) {
			$file = str_replace('.sample', '', $file);
		}
		if ($key = array_search($source, $files)) {
			unset ($files[$key]);
		}
		$files = array_unique($files);
		$unique = count($files);
		$this->out($unique . ' pre-commit files found');

		foreach($files as &$file) {
			$file = realpath($file);
		}
		$files = array_filter(array_unique($files));
		$toProcess = count($files);
		$this->out($toProcess . ' pre-commit files to process');
		if (DS === '\\') {
			foreach($files as $file) {
				$file .= '.bat';
				if (copy($source, $file)) {
					$this->out($file, ' created');
				} else {
					$this->out($file, ' couldn\'t be created');
				}
			}
		} else {
			foreach($files as $file) {
				if (!empty($this->params['dry'])) {
					$this->out($file. ' identified');
					continue;
				}
				rename($file, $file . '.bak');
				if (symlink($source, $file)) {
					$this->out($file. ' (link) created');
				} elseif (copy($source, $file)) {
					$this->out($file. ' created');
				} else {
					$this->out($file. ' couldn\'t be created');
				}
			}
		}
	}

/**
 * loadTasks method
 *
 * @return void
 * @access public
 */
	function loadTasks() {
		parent::loadTasks();
		foreach($this->Find->settings as $key => $val) {
			if (isset($this->settings[$key])) {
				$this->Find->settings[$key] =& $this->settings[$key];
			}
		}
		$this->Find->startup();
	}

/**
 * checkLeadingWhitespace method
 *
 * @param string $string ''
 * @return void
 * @access protected
 */
	function _checkLeadingWhitespace($string = '') {
		if (!preg_match('/\.php$/', $this->current['file'])) {
			return true;
		}
		if (is_array($string)) {
			$string = current($string);
		}
		if (preg_match('/^\s/', $string)) {
			return false;
		}
		return true;
	}

/**
 * checkPassesTests method
 *
 * @return void
 * @access protected
 */
	function _checkPassesTests() {
		$test = $this->_mapToTest($this->current['file']);
		if (!$test) {
			return true;
		}
		list($type, $case, $testFile) = $test;
		if (!file_exists($testFile)) {
			$this->_testResults['_summary']['missing']++;
			if (isset($this->settings['rules']['passesTests']) &&
				!empty($this->settings['rules']['passesTests']['failMissingTests'])) {
				return 'No test exists';
			}
			$this->_log('Test not found: ' . $testFile, null, 'notice');
			return true;
		}

		$cmdShort = $cmd = $this->paths['console'] . ' testsuite ' . $type . ' case ' . $case;
		if (rtrim(APP, DS) === rtrim($this->params['root'] . DS . $this->params['app'], DS)) {
			$cmd .= '  -app ' . $this->params['root'] . DS . $this->params['app'];
		}
		if (isset($this->_testResults[$cmd])) {
			return $this->_testResults[$cmd];
		}
		$this->_testResults['_summary']['caseTotal']++;
		$return = $this->_exec($cmd, $out);
		$result = end($out);
		if (!trim($result)) {
			$result = 'test did not complete';
			$return = 9;
		}
		if (preg_match_all('@Error: (.+)@', $result, $matches)) {
			$return = 9;
			$this->_testResults['_summary']['fails'] += 1;
		} elseif (preg_match_all('@(\d+) (passes|fails|exceptions)@', $result, $matches)) {
			if ($matches) {
				foreach ($matches[2] as $i => $type) {
					$this->_testResults['_summary'][$type] += $matches[1][$i];
				}
			}
			$this->out($result . ' ', false);
		}
		if($return) {
			$this->_log($cmd, null, 'info');
			foreach(array_slice($out, 10) as $line) {
				$this->_log($line, null, 'info');
			}
			$cmd = str_replace($this->paths['console'], 'cake', $cmdShort);
			$this->_testResults[$cmd] = "'$cmd' failed";
			return "'$cmd' failed'";
		} else {
			$this->_testResults['_summary']['casePass']++;
		}
		$this->_testResults[$cmd] = true;
		return true;
	}

/**
 * checkPhpLint method
 *
 * @return void
 * @access protected
 */
	function _checkPhpLint() {
		if (!file_exists($this->current['file'])) {
			return true;
		}
		$this->_log(sprintf('Lint testing %s', Debugger::trimPath($this->current['file'])), null, 'debug');
		$out = null;
		$return = $this->_exec('php -l ' . escapeshellarg($this->current['file']), $out);
		if ($return != 0) {
			foreach ($out as $error) {
				if (!empty($error[0]) && in_array($error[0], array('P', 'F'))) {
					$this->_log($error, null, 'err', true);
				}
			}
			return false;
		}
		return true;
	}

/**
 * coreConfig method
 *
 * @return void
 * @access protected
 */
	function _coreConfig() {
		/*
		$this->settings['fileNamePattern'] = '@\.php$@';
		$excludes = array(
			'bootstrap\.php',
			'config[\\\/]',
			'app_(controller|model|helper)\.php',
			'skel[\\\/]',
			'overloadable_'
		);
		$this->settings['skipTests'] = '@(' . implode($excludes, '|' . ' )@';
		*/
	}

/**
 * listFiles method
 *
 * For windows use $Folder->findRecursive and find all php/ctp files in the working folder
 * Otherwise use find via the command line (alot faster), excluding tmp files, the webroot and a
 * few vendors
 *
 * @return array of files
 * @access protected
 */
	function _listFiles() {
		if (!empty($this->args)) {
			if ($this->args[0] == 'pre-commit') {
				if ($this->settings['repoType'] == 'git') {
					return $this->Find->git();
				} elseif ($this->settings['repoType'] == 'svn') {
					return $this->Find->svn();
				}
			} elseif ($this->args[0] == 'working') {
				if ($this->settings['repoType'] == 'git') {
					return $this->Find->git('working');
				} elseif ($this->settings['repoType'] == 'svn') {
					return $this->Find->svn('working');
				}
			}
			return $this->Find->files($this->args[0]);
		}
		return $this->Find->files();
	}

/**
 * log method
 *
 * If the message level is greater than the set logLevel - ignore it
 * If it's an error - don't echo it inline to prevent duplicate messages (once when found, and
 * once in the summary when all files are processed)
 *
 * @param string $string ''
 * @param mixed $ruleKey null
 * @param mixed $level 'info'
 * @param bool $isError false
 * @return void
 * @access protected
 */
	function _log($string = '', $ruleKey = null, $level = 'info', $isError = false) {
		if ($isError) {
			$this->returnValue = 1;
		}
		if ($this->_logLevel[$level] > $this->_logLevel[$this->settings['logLevel']]) {
			return;
		}
		if ($isError) {
			$this->returnValue = 1;
			$file = Debugger::trimPath($this->current['file']);
			if (!strpos($string, $file)) {
				$string .= ' in ' . $file;
				if ($this->current['lineNo'] !== '*') {
					$string .= ' on line ' . $this->current['lineNo'];
				}
			}
			$this->errors[$this->current['file']][$this->current['rule']][$this->current['lineNo']] = $string;
			return;
		}
		if ($this->_logLevel[$level] === $this->_logLevel['err']) {
			$string = 'Error: ' . $string;
		} elseif ($this->_logLevel[$level] === $this->_logLevel['warning']) {
			$string = 'Warning: ' . $string;
		}
		if (!empty($this->settings['_supressMessages'])) {
			$this->messages[] = $string;
			return;
		}
		$this->out($string);
	}

/**
 * mapToTest method
 *
 * @param mixed $file
 * @return void
 * @access protected
 */
	function _mapToTest($file) {
		if (!preg_match('@\.php$@', $file) || ($this->settings['skipTests'] && preg_match($this->settings['skipTests'], $file))) {
			return false;
		}
		$type = $this->_testType($file);
		$case = str_replace('.php', '', $file);
		if (preg_match('@tests[\\\/]@', $file)) {
			if (preg_match('@\.test@', $file)) {
				if ($case = preg_replace('@.*tests[\\\/]cases[\\\/]@', '', $case)) {
					$case = str_replace('.test', '', $case);
					return array($type, $case, $file);
				}
			}
			return false;
		}
		if ($type === 'core') {
			$libs = strpos($case, 'cake' . DS . 'libs' . DS);
			if ($libs !== false) {
				$libs = 'libs' . DS;
			}
			$case = preg_replace('@.*cake[\\\/](libs[\\\/])?@', '', $case);
			return array($type, $case, CAKE_TESTS . 'cases' . DS . $libs . $case . '.test.php');
		}
		preg_match('@(.*[\\\/])(?:(?:config|controllers|libs|locale|models|tests|vendors|views)[\\\/])@', $case, $matches);
		$base = '';
		if ($matches) {
			$base = $matches[1];
			$case = str_replace($base, '', $case);
		}
		$map = array(
			'controllers' . DS . 'components' => 'components',
			'models' . DS . 'behaviors' => 'behaviors',
			'models' . DS . 'datasources' => 'datasources',
			'views' . DS . 'helpers' => 'helpers',
			'vendors' . DS . 'shells' => 'shells',
		);
		foreach ($map as $path => $_type) {
			if (strpos($case, $path) === 0) {
				$case = str_replace($path, $_type, $case);
				break;
			}
		}
		return array($type, $case, $base . 'tests' . DS . 'cases' . DS . $case . '.test.php');
	}

/**
 * testType method
 *
 * @param mixed $file
 * @return void
 * @access protected
 */
	function _testType($file) {
		$_file = realpath($file);
		if ($_file) {
			$file = $_file;
		}
		if (strpos($file, CAKE) === 0) {
			return 'core';
		} elseif (preg_match('@plugins[\\\/]([^\\/]*)@', $file, $match)) {
			return $match[1];
		}
		return 'app';
	}

/**
 * printMessages method
 *
 * @return void
 * @access protected
 */
	function _printMessages() {
		if (empty($this->settings['quiet'])) {
			foreach($this->messages as $string) {
				$this->out($string);
			}
		}
		$this->messages = array();
	}

/**
 * exec method
 *
 * @param string $cmd
 * @param string $out
 * @return void
 * @access protected
 */
	function _exec($cmd, &$out = null) {
		if (DS === '/') {
			exec($cmd . ' 2>&1', $out, $return);
		} else {
			exec($cmd, $out, $return);
		}
		return $return;
	}

/**
 * reset method
 *
 * Reset run time data to initial state - overriden/initialized if appropriate
 *
 * @param array $overrides array()
 * @return void
 * @access protected
 */
	function _reset($overrides = array()) {
		$this->current = am(array(
			'finished' => false,
			'errors' => array(),
			'file' => '',
			'lineNo' => '*',
		), $overrides);
	}

/**
 * stop method
 *
 * @return void
 * @access protected
 */
	function _stop() {
		$this->_log('Return value: ' . (int)$this->returnValue, null, 'debug');
		return parent::_stop($this->returnValue);
	}

/**
 * welcome method
 *
 * @return void
 * @access protected
 */
	function _welcome() {
		if (!empty($this->params['q']) || !empty($this->params['quiet'])) {
			return;
		}
		return parent::_welcome();
	}

/**
 * buildPaths method
 *
 * @return void
 * @access protected
 */
	function _buildPaths() {
		$this->paths = array('console' => array_pop(Configure::corePaths('cake')) . 'console' . DS . 'cake');
	}

/**
 * _printErrors method
 *
 * @return void
 * @access protected
 */
	function _printErrors($count) {
		$errors = count($this->errors);
		if ($errors) {
			if ($errors == 1) {
				$this->out(sprintf('%s Files checked, Errors:', $count));
			} else {
				$this->out(sprintf('%s Files checked, %s with errors:', $count, $errors));
			}
			foreach($this->errors as $file => &$messages) {
				$this->out('    ' . $file);
				foreach($messages as $rule => &$fails) {
					if ($this->settings['suppressDuplicateErrors']) {
						reset($fails);
						$fails = array(current($fails));
					}
					foreach($fails as $error) {
						$this->out('            ' . $error);
					}
				}
			}
			if (!empty($this->settings['vimTips'])) {
				$this->_writeErrorFile();
			}
			if (!empty($this->args) && $this->args[0] == 'pre-commit') {
				$this->out('Commit aborted');
				if ($this->settings['repoType'] === 'git') {
					$this->out('    you can override this check with the --no-verify flag');
				} elseif ($this->settings['repoType'] === 'svn') {
					if (empty($this->settings['disableNoverify'])) {
						$this->out('    you can override this check by including in your commit ' .
								'message @noverify (at the end of any line)');
					}
				}
			}
		} else {
			$this->out(sprintf('%s Files checked, No errors found', $count));
		}
	}

/**
 * writeErrorFile method
 *
 * @return void
 * @access protected
 */
	function _writeErrorFile() {
		$errors = $script = array();
		foreach($this->errors as $file => &$messages) {
			if ($this->_logLevel[$this->settings['logLevel']] < $this->_logLevel['err']) {
				continue;
			}
			foreach($messages as $rule => &$fails) {
				foreach($fails as $line => $error) {
					if (preg_match('@in [^ ] on line @', $error)) {
						$errors[$file . $line] = $error;
					}
					if (!is_numeric($line)) {
						$line = '0';
					}
					if ($this->settings['vimTips']) {
						if (!empty($this->settings['rules'][$rule]['vimTip'])) {
							$error = $this->settings['rules'][$rule]['vimTip'] . ' ' . $error;
							$script[$file][$rule][$line] = $this->settings['rules'][$rule]['vimTip'];
						} else {
							$script[$file][$rule][$line] = "match Error /\%{$line}l/";
						}
					}
					$errors[$file . $rule . $line] = "$error in $file on line $line";
				}
			}
		}
		if (!$errors) {
			return;
		}
		file_put_contents($this->params['working'] . DS . 'errors.err', implode("\n", array_filter($errors)));
		$this->out("type 'vim -q errors.err' to review failures");
		if ($script) {
			$command = '';
			foreach($script as $file => $rules) {
				foreach($rules as $rule => $lines) {
					foreach($lines as $line => $tip) {
						$command .= ":{$line} | $tip ";
					}
				}
				$script[$file] = 'vim ' . $file . ' -c "' . $command . '"';
			}
		}
		file_put_contents($this->params['working'] . DS . 'review.sh', implode("\n", array_filter($script)));
		$this->out("type '. review.sh' to auto-correct and review failures");
	}
}