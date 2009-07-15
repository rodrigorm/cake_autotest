<?php
class NotifySend {
	static $statuses = array(
		'success' => 'test-pass-icon.png',
		'error'   => 'test-error-icon.png',
		'caution' => 'test-fail-icon.png',
	);

	static function show($message, $title = null, $priority = 0, $status = 'success') {

		$img = '';
		if (!empty(NotifySend::$statuses[$status])) {
			$img = dirname(dirname(dirname(__FILE__))) . DS . 'img' . DS . NotifySend::$statuses[$status];
		}
		if (empty($title)) {
			$title = $message;
		}
		$message = addslashes($message);
		$title = APP_DIR . ': ' . addslashes($title);

		shell_exec("notify-send -i $img \"{$title}\" \"{$message}\"");
	}

	static function green($params) {
		NotifySend::show("Tests passed.\n" . NotifySend::normalize($params), 'Tests Passed');
	}

	static function red($files_to_test, $params) {
		NotifySend::show(count($files_to_test) . " tests failed.\n" . NotifySend::normalize($params), 'Tests Failed', -2, 'error');
	}

	static function allGood() {
		NotifySend::show('All tests passed.', 'Tests Passed');
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

AutoTestShell::addHook(Hooks::green, array('NotifySend', 'green'));
AutoTestShell::addHook(Hooks::red, array('NotifySend', 'red'));
AutoTestShell::addHook(Hooks::all_good, array('NotifySend', 'allGood'));
?>