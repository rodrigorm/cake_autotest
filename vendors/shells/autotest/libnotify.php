<?php
class Libnotify {
	static $statuses = array(
		'success' => 'test-pass-icon.png',
		'error'   => 'test-error-icon.png',
		'caution' => 'test-fail-icon.png',
	);

	static function show($message, $title = null, $priority = 0, $status = 'success') {
    
		$img = array_pop(Configure::read('pluginPaths'));
		if (!empty(Libnotify::$statuses[$status])) {
			$img .= 'cake_autotest'.DS.'vendors'.DS.'img'.DS.Libnotify::$statuses[$status];
		}
		if (empty($title)) {
			$title = $message;
		}
		$message = addslashes($message);
		$title = addslashes($title);

		shell_exec("notify-send -i $img \"{$title}\" \"{$message}\"");
	}
	
	static function green($params) {
		Libnotify::show("Tests passed.\n" . Libnotify::normalize($params), 'Tests Passed');
	}

	static function red($files_to_test, $params) {
		Libnotify::show(count($files_to_test) . " tests failed.\n" . Libnotify::normalize($params), 'Tests Failed', -2, 'error');
	}

	static function allGood() {
		Libnotify::show('All tests passed.', 'Tests Passed');
	}

	static function normalize($params) {
		$message = $params['complete'] . '/' . $params['total'] . ' test cases complete: ';
		unset($params['complete']);
		unset($params['total']);

		foreach ($params as $key => $value) {
			if ($value == 0) {
				unset($params[$key]);
				continue;
			}
			$params[$key] = $value . ' ' . $key;
		}
		return $message . implode(', ', $params) . '.';
	}
}

AutoTestShell::addHook(Hooks::green, array('Libnotify', 'green'));
AutoTestShell::addHook(Hooks::red, array('Libnotify', 'red'));
AutoTestShell::addHook(Hooks::all_good, array('Libnotify', 'allGood'));
?>