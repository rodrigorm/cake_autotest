<?php
if (!defined('DISABLE_AUTO_DISPATCH')) {
	define('DISABLE_AUTO_DISPATCH', true);
}
if (!class_exists('ShellDispatcher')) {
	ob_start();
	$argv = false;
	require CAKE . 'console' .  DS . 'cake.php';
	ob_end_clean();
}

class MockShellDispatcher extends ShellDispatcher {
	function __construct() {}
}

if (!class_exists('Shell')) {
	App::import('Core', 'Shell');
}
App::import('Shell', 'Autotest.AutoTest');

class AutotestController extends Controller {
	var $name = 'Autotest';
	var $helpers = array('Javascript', 'Html');
	var $components = array('RequestHandler');
	var $uses = null;

	function beforeFilter() {
		$this->Folder = new Folder();
		$this->Dispatcher = new MockShellDispatcher();
		$this->AutoTest = new AutoTestShell($this->Dispatcher);
		$this->AutoTest->folder = new Folder(APP);
		$this->AutoTest->params = array(
			'working'         => rtrim(APP, DS),
			'app'             => basename(APP),
			'root'            => rtrim(dirname(APP), DS),
			'webroot'         => 'webroot',
			'notify'          => false,
			'checkAllOnStart' => false
		);
		$this->AutoTest->initialize();
		$this->AutoTest->buildPaths();
	}

	function index() {
	}

	function run() {
		$file = APP . $this->params['url']['file'];

		$output = $this->AutoTest->_runTest($file);
		if (strpos($output, '✔')) {
			$status = 'passed';
		} elseif (strpos($output, '❯')) {
			$status = 'skipped';
		} elseif (strpos($output, '✘')) {
			$status = 'failed';
		} else {
			$status = 'unknown';
		}
		$this->set(array(
			'status' => $status,
			'output' => $output
		));
	}

	function find_files() {
		if (!empty($this->params['url']['lastMTime'])) {
			$this->AutoTest->lastMTime = $this->params['url']['lastMTime'];
		}
		
		$files = $this->AutoTest->_findFiles();

		foreach ($files as $key => $file) {
			$files[$key] = trim(str_replace(APP, '', $file), '/');
		}

		$this->set(array(
			'lastMTime' => $this->AutoTest->lastMTime,
			'files'     => $files
		));
	}
}
?>