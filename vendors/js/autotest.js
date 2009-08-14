(function($) {

var AutoTest = {
	lastMTime: null,
	filesToTest: {},
	fails: {},

	run: function run() {
		AutoTest._findFiles();
	},

	_runTests: function _runTests() {
		if (!AutoTest.filesToTest) {
			AutoTest.filesToTest = AutoTest._findFiles();
		}

		AutoTest._runTest();
	},

	_allGood: function _allGood() {
		return Files.$files.find('li.failed').size() == 0;
	},

	_runTest: function _runTest(file) {
		if (!file) {
			for (var file in AutoTest.filesToTest) {
				break;
			}
			if (!file) {
				AutoTest._waitForChanges();
				return;
			}
			delete(AutoTest.filesToTest[file]);
		}
		Files.update(file, {result: 'loading'});

		$.get('/autotest/run.json?file=' + file + '&_=' + Math.random(), function(result) {
			var result = eval('(' + result + ')');
			if (result.result == 'failed') {
				AutoTest.fails[file] = file;
			} else if (AutoTest.fails[file]) {
				delete(AutoTest.fails[file]);
			}
			Files.update(file, result);
			AutoTest._runTest();
		});
	},

	_waitForChanges: function _waitForChanges() {
		setTimeout(AutoTest._findFiles, 1000);
	},

	_findFiles: function _findFiles() {
		var lastMTime = '';
		if (AutoTest.lastMTime) {
			lastMTime = '&lastMTime=' + AutoTest.lastMTime;
		}

		$.get('/autotest/find_files.json?_=' + Math.random() + lastMTime, function(result) {
			var result = eval('(' + result + ')');

			AutoTest.lastMTime = result.lastMTime;

			var filesToTest = {};

			if (result.files.length) {
				filesToTest = $.extend({}, AutoTest.fails || {});
				var files = [];
				var length = result.files.length;
				for (var i = 0; i < length; i++) {
					var file = result.files[i];
					filesToTest[file] = file;
					files.push({
						name: file
					})
				}
				Files.render(files);
			}

			AutoTest.filesToTest = filesToTest;

			AutoTest._runTests();
		});
	}
}

var Files = {
	$files: null,

	init: function init() {
		Files.$files = $('#files');
		Files.$files.click(Files.click);
		Files.Filters.init();
	},
	
	update: function update(file, result) {
		var result = result || {result: 'unknown', 'output': ''};
		Files.render([{
			name: file, 
			result: result
		}]);
	},

	render: function render(files) {
		var elements = [];
		for (var i=0; i < files.length; i++) {
			var file = files[i];
			var result = file.result || {result: 'unknown', output: ''};

			var id = file.name
				.split('/')
				.join('_')
				.split('\\')
				.join('_')
				.split('.')
				.join('_');
			var $element = $('#' + id);

			if ($element.size() > 0) {
				$element
					.removeClass('passed')
					.removeClass('skipped')
					.removeClass('failed')
					.removeClass('unknown')
					.removeClass('loading')
					.addClass(result.result)
					.find('span.result')
						.html(result.result)
					.end()
					.find('pre.output')
						.text(result.output);
			} else {
				elements.push([
					'<li id="', id, '" class="', result.result, '">',
						'<span class="result">',
							result.result,
						'</span>',
						'<span class="name">',
							file.name,
						'</span>',
						'<a href="#" class="run">(Run test)</a>',
						'<pre class="output" style="display:none">',
							result.output,
						'</pre>',
					'</li>'
				].join(''));
			}
		}
		Files.$files.append(elements.join(''));
		Files.Filters.apply();
	},

	click: function click(e) {
		var target = $(e.target).parents('li')[0];

		if ($(e.target).is('.run')) {
			var file = $('span.name', target).text();
			AutoTest._runTest(file);
			return false;
		}

		$('pre.output', target).toggle();
	}
}

Files.Filters = {
	$filters: null,

	init: function init() {
		Files.Filters.$filters = $('#filters input[type=checkbox]');
		Files.Filters.$filters.click(Files.Filters.apply);
	},

	apply: function apply() {
		var selected = $.map(Files.Filters.$filters.filter(':checked'), function(element) {
			return 'li.' + element.value;
		});
		selected.push('li.loading');
		Files.$files.find('li:visible').not(selected.join(', ')).animate({height: 'hide'});
		Files.$files.find('li:hidden').filter(selected.join(', ')).animate({height: 'show'});
	}
}

$(document).ready(function() {
	Files.init();
	AutoTest.run();
});

})(jQuery);
