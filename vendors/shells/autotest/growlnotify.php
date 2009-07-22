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
			$title = APP_DIR;
		}
		$message = addslashes($message);
		$title = addslashes($title);

		shell_exec("growlnotify -n \"CakePHP AutoTest Shell\" --image $img -p $priority -m \"$message\" \"$title\"");
	}

	static function green($params) {
		GrowlNotify::show(GrowlNotify::normalize($params), 'Tests Passed');
	}

	static function red($files_to_test, $params) {
		GrowlNotify::show(GrowlNotify::normalize($params), $fails . ' Fails', -2, 'error');
	}

	static function allGood() {
		GrowlNotify::show('All tests passed.', 'Tests Passed');
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

AutoTestShell::addHook(Hooks::green, array('GrowlNotify', 'green'));
AutoTestShell::addHook(Hooks::red, array('GrowlNotify', 'red'));
AutoTestShell::addHook(Hooks::all_good, array('GrowlNotify', 'allGood'));
?>