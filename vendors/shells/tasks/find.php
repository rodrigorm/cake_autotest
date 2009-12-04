<?php
/**
 * Short description for find.php
 *
 * Long description for find.php
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
 * @package       shells
 * @subpackage    shells.vendors.shells.tasks
 * @since         v 1.0 (27-Aug-2009)
 * @license       http://www.opensource.org/licenses/mit-license.php The MIT License
 */

/**
 * FindTask class
 *
 * @uses          Shell
 * @package       shells
 * @subpackage    shells.vendors.shells.tasks
 */
class FindTask extends Shell {

/**
 * settings property
 *
 * Run time settings @see initialize
 *
 * @var array
 * @access public
 */
	var $settings = array(
		'findCmd' => '',
		'excludePattern' => null,
		'includePattern' => null,
	);

/**
 * startup method
 *
 * @return void
 * @access public
 */
	function startup() {
		if (DS == '\\') {
			$this->settings['findCmd'] = false;
		} else {
			$this->settings['findCmd'] = 'find :working ! -iwholename "*.git*" ! -ipath "*.svn*" -type f';
		}
	}

/**
 * files method
 *
 * @param string $pattern '(.*\.php|.*\.ctp)$'
 * @return void
 * @access public
 */
	function files($pattern = '(.*\.php|.*\.ctp)$', $optimize = true) {
		$return = $this->_files($pattern, $optimize);
		if (empty($this->settings['includePattern']) && empty($this->settings['excludePattern'])) {
			return $return;
		}
		foreach($return as $i => $file) {
			if (!empty($this->settings['includePattern']) && !preg_match($this->settings['includePattern'], $file)) {
				unset($return[$i]);
				continue;
			}
			if (!empty($this->settings['excludePattern']) && preg_match($this->settings['excludePattern'], $file)) {
				unset($return[$i]);
				continue;
			}
		}
		sort($return);
		return $return;
	}

/**
 * find git files
 *
 * If it's the first commit, diff against a fictional commit to list all files
 * Otherwise, diff to head
 * If there's no output, pre-commit has been called explicitly and there isn't anything to be
 * committed - so use the un-committed and un-added changes
 *
 * @return array of files
 * @access protected
 */
	function git($type = 'pre-commit') {
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
 * svn files
 *
 * Use svnlook to find what's changed. Allow checks to be skipped by including "@noverify" in a
 * commit message unless the setting 'disableNoverify' is set to true. This setting is svn specific
 *
 * @return array of files
 * @access protected
 */

	function svn($type = 'pre-commit') {
		if (DS == '/') {
			$svnlook = exec('which svnlook');
		} elseif (!empty($this->params['svnlook'])) {
			$svnlook = $this->params['svnlook'];
		}
		if (empty($svnlook)) {
			trigger_error('FindTask::svn could not find svnlook executable');
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

	function changed() {
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
 * files method
 *
 * @param mixed $pattern
 * @param mixed $optimize
 * @return void
 * @access protected
 */
	function _files($pattern, $optimize) {
		if (!$this->settings['findCmd'] || !$optimize) {
			$Folder = new Folder($this->params['working']);
			return $Folder->findRecursive($pattern);
		}
		$cmd = $this->_prepareCmd($this->settings['findCmd'], $pattern);
		$this->_log($cmd, null, 'debug');
		exec($cmd, $out);
		return $out;
	}

/**
 * log method
 *
 * @param string $string ''
 * @return void
 * @access protected
 */
	function _log($string = '') {
		//$this->out($string);
	}

/**
 * prepareCmd method
 *
 * @TODO $pattern unused
 * @param mixed $cmd
 * @param mixed $pattern
 * @return void
 * @access protected
 */
	function _prepareCmd($cmd, $pattern) {
		$subs = am($this->settings, $this->params);
		return String::insert($cmd, $subs);
	}
}