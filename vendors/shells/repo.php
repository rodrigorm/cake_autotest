<?php
/* SVN FILE: $Id$ */

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
 * @see           /branches/misc/hooks/pre-commit
 * @filesource
 * @copyright     Copyright (c) 2009, Andy Dawson
 * @link          www.ad7six.com
 * @package       base
 * @subpackage    base.vendors.shells
 * @since         v 1.0 (03-Jul-2009)
 * @version       $Revision$
 * @modifiedby    $LastChangedBy$
 * @lastmodified  $Date$
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

/**
 * RepoShell class
 *
 * @uses          Shell
 * @package       base
 * @subpackage    base.vendors.shells
 */
class RepoShell extends Shell {

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
 * vimTips - @TODO or delete
 * fileNamePattern - only files matching this pattern will be processed
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
		'fileNamePattern' => '/\.php$|\.ctp$|\.js$|\.css$/',
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
				'rule' => '/(?![\r\n] \*)[^\r\n]*(?!function[^\r\n]*)debug\s*\((?![^\r\n]*@ignore)/s',
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
				'vimTip' =>':v/./,/./-j',
			),
			'noSpaceAfterCommaInFunctionDeclaration' => array(
				'logLevel' => 'warning',
				'rule' => '/\n[^\r\n\'"]*function[^\r\n]*,[^ \'"](?![^\r\n]*@ignore)/s',
			),
			'noSpaceBetweenClosingBracketAndCurlyBrace' => array(
				'logLevel' => 'warning',
				'rule' => '/\){(?![^\r\n]*@ignore)\r?\n/s',
			),
			'spaceBeforeClosingBracketInFunctionDeclaration' => array(
				'logLevel' => 'warning',
				'rule' => '/\n[^\r\n\'"]*function[^\r\n]* \)[^\r\n]*{(?![^\r\n]*@ignore)/s',
			),
			'spaceBeforeCommaInFunctionDeclaration' => array(
				'logLevel' => 'warning',
				'rule' => '/\n[^\r\n\'"]*function[^\r\n]* ,(?![^\r\n]*@ignore)/s',
			),
			'spaceBeforeFirstParameterInFunctionDeclaration' => array(
				'logLevel' => 'warning',
				'rule' => '/\n[^\r\n\'"]*function[^\r\n]*\( (?![^\r\n]*@ignore)/s',
			),
			'spaceBeforeOpeningBracketInFunctionCall' => array(
				'logLevel' => 'warning',
				'rule' => '/\n[^\r\n\'"]*\w(->|::)[\w\d]+ \((?![^\r\n]*@ignore)/s',
			),
			'spaceBetweenFunctionNameAndBracket' => array(
				'logLevel' => 'warning',
				'rule' => '/\n[^\r\n\'"]*function &?\w+ \((?![^\r\n]*@ignore)/s',
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
	var $_testResults = array();

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
		$this->buildPaths();
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
		$nl = false;
		foreach ($files as $i => $file) {
			if ($this->_logLevel[$this->settings['logLevel']] >= $this->_logLevel['notice']) {
				$this->out($file . ' ', false);
				$nl = true;
			}
			if (!file_exists($file) || !preg_match($this->settings['fileNamePattern'], $file)) {
				$this->out('❯', $nl);
				continue;
			}
			if ($this->checkFile($file)) {
				$this->out('✔', $nl);
			} else {
				$this->out('✘', $nl);
			}
		}
		$this->out(null);
		$this->_printMessages();
		$this->hr();
		$errors = count($this->errors);
		if ($errors) {
			if ($errors == 1) {
				$this->out(sprintf('%s Files checked, FAILS:', $count));
			} else {
				$this->out(sprintf('%s Files checked, %s FAILS:', $count, $errors));
			}
			foreach($this->errors as $file => $messages) {
				$this->out('	' . $file);
				if ($this->_logLevel[$this->settings['logLevel']] >= $this->_logLevel['err']) {
					foreach($messages as $rule => $fails) {
						foreach($fails as $error) {
							$this->out('		' . $error);
						}
					}
				}
			}
			if (!empty($this->args) && $this->args[0] == 'pre-commit') {
				$this->out('Commit aborted');
				if ($this->settings['repoType'] === 'git') {
					$this->out('	you can override this check with the --no-verify flag');
				} elseif ($this->settings['repoType'] === 'svn') {
					if (empty($this->settings['disableNoverify'])) {
						$this->out('	you can override this check by including in your commit ' .
							'message @noverify (at the end of any line)');
					}
				}
			}
			/*
			if (!empty($this->settings['vimTips'])) { // @TODO
				file_put_contents('errors.err', implode("\n", array_filter($errors)));
				echo "type 'vim -q errors.err' to review failures\n";
			}
			*/
		} else {
			$this->out(sprintf('%s Files checked, No errors found', $count));
		}
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
			} else {
				$this->out("No arguments, or file doesn't exist");
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
			$nl = true;
		}
		if ($result) {
			$this->out('✔', $nl);
		} else {
			$this->out('✘', $nl);
		}
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
		if (!preg_match('@\.php$@', $this->current['file']) || preg_match('@^tests[\\/]|[\\/]?tests[\\/]@', $this->current['file'])) {
			return true;
		}
		$case = ltrim(str_replace('.php', '', $this->current['file']), DS);
		if (preg_match('@^cake' . DS . '@', $case)) {
			$type = 'core';
			$case = preg_replace('@^cake' . DS . '@', '', $case);
			$testFile = CAKE_TESTS . 'cases' . DS . $case . '.test.php';
			$case = preg_replace('@^libs' . DS . '@', '', $case);
		} else {
			$type = 'app';
			if (preg_match('@^' . APP_DIR . DS . '@', $case)) {
				$case = str_replace(APP_DIR . DS, '', $case);
			}
			if (preg_match('@^plugins' . DS . '([^\\/]*)@', $case, $match)) {
				$type = $match[1];
				$case = str_replace('plugins' . DS . $type . DS, '', $case);
			}
			$map = array(
				'controllers' . DS . 'components' => 'components',
				'models' . DS . 'behaviors' => 'behaviors',
				'models' . DS . 'datasources' => 'datasources',
				'views' . DS . 'helpers' => 'helpers',
				'vendors' . DS . 'shells' => 'shells',
			);
			foreach ($map as $path => $_type) {
				if (strpos($this->current['file'], $path) === 0) {
					$case = str_replace($path, $_type, $case);
					break;
				}
			}
			if ($type === 'app') {
				$testFile = TESTS . 'cases' . DS . $case . '.test.php';
			} else {
				$testFile = APP . 'plugins' . DS . $type . DS . 'tests' . DS . 'cases' . DS . $case . '.test.php';
			}
		}
		if (strpos($this->current['file'], '.test.') !== false) {
			$case = preg_replace('@^tests' . DS . '[^\\/]*' . DS . '@', '', $case);
			$case = str_replace('.test', '', $case);
		} elseif (!file_exists($testFile)) {
			if (isset($this->settings['rules']['passesTests']) &&
				!empty($this->settings['rules']['passesTests']['failMissingTests'])) {
				return 'No test exists';
			}
			$this->_log('Test not found: ' . $testFile, null, 'notice');
			return true;
		}
		if (rtrim(APP, DS) === rtrim($this->params['root'] . DS . $this->params['app'], DS)) {
			$cmd = $this->paths['console'] . ' testsuite ' . $type . ' case ' . $case;
		} else {
			$cmd = $this->paths['console'] . ' -app ' . $this->params['root'] . DS . $this->params['app'] . ' testsuite ' . $type . ' case ' . $case;
		}
		if (isset($this->_testResults[$cmd])) {
			return $this->_testResults[$cmd];
		}
		$this->out($cmd . ' ', false);
		$return = $this->_exec($cmd, $out);
		if($return) {
			$this->_log($cmd, null, 'info');
			foreach(array_slice($out, 10) as $line) {
				$this->_log($line, null, 'info');
			}
			$this->_testResults[$cmd] = "'$cmd' failed";
			return "'$cmd' failed'";
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
					return $this->_listGitFiles();
				} elseif ($this->settings['repoType'] == 'svn') {
					return $this->_listSvnFiles();
				}
			} elseif ($this->args[0] == 'working') {
				if ($this->settings['repoType'] == 'git') {
					return $this->_listGitFiles('working');
				} elseif ($this->settings['repoType'] == 'svn') {
					return $this->_listSvnFiles('working');
				}
			}
			$arg = $this->args[0];
			if ($arg[0] === '*') {
				foreach ($this->args as $pattern) {
					if ($pattern[0] === '*') {
						$suffix[] = '-name "' . $pattern . '"';
					}
				}
			} else {
				if (is_file($arg)) {
					return array($arg);
				}
				$this->params['working'] = rtrim($arg, DS) . DS;
			}
		}
		if (DS === '\\') {
			$Folder = new Folder($this->params['working']);
			return $Folder->findRecursive('(.*\.php|.*\.ctp)');
		}
		if (empty($suffix)) {
			$suffix[] = '-name "*.php"';
			$suffix[] = '-name "*.ctp"';
		}
		$suffix = '\( ' . implode (' -o ', $suffix) . ' \)';
		$cmd = 'find ' . $this->params['working'] . ' ! -ipath "*.svn*" \
		! -ipath "*.git*" ! -iname "*.git*" ! -ipath "*/tmp/*" ! -ipath "*webroot*" \
		! -ipath "*Zend*" ! -ipath "*simpletest*" ! -ipath "*firephp*" \
		! -iname "*jquery*" ! -ipath "*Text*" ' . $suffix . ' -type f';
		$this->_log($cmd, null, 'debug');
		exec($cmd, $out);
		return $out;
	}

/**
 * listGitFiles method
 *
 * If it's the first commit, diff against a fictional commit to list all files
 * Otherwise, diff to head
 * If there's no output, pre-commit has been called explicitly and there isn't anything to be
 * committed - so use the un-committed and un-added changes
 *
 * @return array of files
 * @access protected
 */
	function _listGitFiles($type = 'pre-commit') {
		$output = null;
		$return = $this->_exec('git rev-parse --verify HEAD', $output);
		if ($return) {
			$against = '4b825dc642cb6eb9a060e54bf8d69288fbee4904';
		} else {
			$against = 'HEAD';
		}
		$output = null;
		if ($type === 'pre-commit') {
			$this->_exec("git diff-index --cached --name-only $against", $output);
		} else {
			$this->_exec("git diff-index --name-only $against", $output);
		}
		return $output;
	}

/**
 * listSvnFiles method
 *
 * Use svnlook to find what's changed. Allow checks to be skipped by including "@noverify" in a
 * commit message unless the setting 'disableNoverify' is set to true. This setting is svn specific
 *
 * @return array of files
 * @access protected
 */

	function _listSvnFiles($type = 'pre-commit') {
		if (DS == '/') {
			$svnlook = exec('which svnlook');
		} elseif (!empty($this->params['svnlook'])) {
			$svnlook = $this->params['svnlook'];
		}
		if (empty($svnlook)) {
			trigger_error('RepoShell::_listSvnFiles could not find svnlook executable');
			return array();
		}
		if (isset($this->params['txn']) && isset($this->params['repo'])) {
			if (empty($this->settings['disableNoverify'])) {
				$cmd = "$svnlook log -t {$this->params['txn']} " . $this->params['repo'];
				$this->_exec($cmd, $message);
				if (preg_match('/@noverify[\r\n]|@noverify$/s', implode($message, "\n"))) {
					$this->out('Checks overriden by @noverify marker found at the end of a line');
					return array();
				}
			}
			$cmd = "$svnlook changed -t {$this->params['txn']} " . $this->params['repo'];
			$this->_exec($cmd, $out);
		} else {
			$this->_exec('svn status -q', $out);
		}
		foreach($out as &$file) {
			$file = trim(substr($file, 4));
		}
		return $out;
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
			exec($cmd . ' 2> /dev/null', $out, $return);
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
 * buildPaths method
 *
 * @return void
 * @access public
 */
	function buildPaths() {
		$this->paths = array('console' => array_pop(Configure::corePaths('cake')) . 'console' . DS . 'cake');
	}
}