<?php
class Growl {
	static $statuses = array(
		'success' => '/Applications/Mail.app/Contents/Resources/status-available.tiff',
		'error'   => '/Applications/Mail.app/Contents/Resources/redlight.tiff',
		'caution' => '/Applications/Mail.app/Contents/Resources/Caution.tiff',
	);

	static function show($message, $title = null, $priority = 0, $status = 'success') {
		$img = null;
		if (!empty(Growl::$statuses[$status])) {
			$img = Growl::$statuses[$status];
		}
		if (empty($title)) {
			$title = $message;
		}
		$message = addslashes($message);
		$title = addslashes($title);

		shell_exec("growlnotify -n \"CakePHP AutoTest Shell\" --image $img -p $priority -m \"$message\" \"$title\"");
	}
	
	static function green() {
		Growl::show('Tests passed.', 'Tests Passed');
	}

	static function red($files_to_test) {
		Growl::show(count($files_to_test) . ' tests failed.', 'Tests Failed', -2, 'error');
	}

	static function allGood() {
		Growl::show('All tests passed.', 'Tests Passed');
	}
}

AutoTestShell::addHook(Hooks::green, array('Growl', 'green'));
AutoTestShell::addHook(Hooks::red, array('Growl', 'red'));
AutoTestShell::addHook(Hooks::all_good, array('Growl', 'allGood'));
?>