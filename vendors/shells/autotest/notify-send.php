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
			$title = APP_DIR;
		}
		$message = addslashes($message);
		$title = addslashes($title);

		shell_exec("notify-send -i $img \"{$title}\" \"{$message}\"");
	}

	static function green($params) {
		NotifySend::show(NotifySend::normalize($params), 'Tests Passed');
	}

	static function red($fails, $params) {
		NotifySend::show(NotifySend::normalize($params), $fails . ' Fails', -2, 'error');
	}

	static function allGood() {
		NotifySend::show('All tests passed.', 'Tests Passed');
	}

	static function normalize($params) {
		if (!isset($params['complete'])) {
			$params['complete'] = 0;
		}
		$message = $params['complete'] . '/' . $params['total'];
		unset($params['complete']);
		unset($params['total']);

		foreach ($params as $key => $value) {
			if ($value === 0) {
				unset($params[$key]);
				continue;
			}
			if (is_array($value)) {
				$params[$key] = '';
				foreach ($value as $k => $v) {
					$params[$key] .= str_replace(APP, '', $k) . " $v\n";
				}
			} else {
				$params[$key] = $value . ' ' . $key;
			}
		}
		return $message . "\n" . implode($params, "\n");
	}
}

AutoTestShell::addHook(Hooks::green, array('NotifySend', 'green'));
AutoTestShell::addHook(Hooks::red, array('NotifySend', 'red'));
AutoTestShell::addHook(Hooks::all_good, array('NotifySend', 'allGood'));