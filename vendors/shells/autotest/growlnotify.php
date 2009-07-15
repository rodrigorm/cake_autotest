<?php
class GrowlNotify {
	static $statuses = array(
		'success' => '/Applications/Mail.app/Contents/Resources/status-available.tiff',
		'error'   => '/Applications/Mail.app/Contents/Resources/redlight.tiff',
		'caution' => '/Applications/Mail.app/Contents/Resources/Caution.tiff',
	);

	static function show($message, $title = null, $priority = 0, $status = 'success') {
		$img = null;
		if (!empty(GrowlNotify::$statuses[$status])) {
			$img = GrowlNotify::$statuses[$status];
		}
		if (empty($title)) {
			$title = $message;
		}
		$message = addslashes($message);
		$title = APP_DIR . ': ' . addslashes($title);

		shell_exec("growlnotify -n \"CakePHP AutoTest Shell\" --image $img -p $priority -m \"$message\" \"$title\"");
	}

	static function green($params) {
		GrowlNotify::show("Tests passed.\n" . GrowlNotify::normalize($params), 'Tests Passed');
	}

	static function red($files_to_test, $params) {
		GrowlNotify::show(count($files_to_test) . " tests failed.\n" . GrowlNotify::normalize($params), 'Tests Failed', -2, 'error');
	}

	static function allGood() {
		GrowlNotify::show('All tests passed.', 'Tests Passed');
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

AutoTestShell::addHook(Hooks::green, array('GrowlNotify', 'green'));
AutoTestShell::addHook(Hooks::red, array('GrowlNotify', 'red'));
AutoTestShell::addHook(Hooks::all_good, array('GrowlNotify', 'allGood'));
?>