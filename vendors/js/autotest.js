(function($) {

var Statuses = ['passed', 'failed', 'skipped', 'unknown', 'untested'];

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
		return Files.failed() == 0;
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
		Files.update(file, {status: 'loading'});

		$.get('/autotest/run.json?file=' + file + '&_=' + Math.random(), function(result) {
			var result = eval('(' + result + ')');
			if (result.status == 'failed') {
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
	},
	
	update: function update(file, result) {
		var result = result || {status: 'untested', 'output': ''};
		Files.render([{
			name: file, 
			result: result
		}]);
	},

	render: function render(files) {
		var elements = [];
		for (var i=0; i < files.length; i++) {
			var file = files[i];
			var result = file.result || {status: 'untested', output: ''};

			var id = file.name
				.split(/[\/\\\.]/)
				.join('_');
			var $element = $('#' + id);

			if ($element.size() > 0) {
				$.each(Statuses, function(i, status) {
					$element.removeClass(status);
				});
				$element
					.removeClass('loading')
					.addClass(result.status)
					.find('span.status')
						.html(result.status)
					.end()
					.find('pre.output')
						.text(result.output);
			} else {
				elements.push([
					'<li id="', id, '" class="', result.status, '">',
						'<span class="status">',
							result.status,
						'</span>',
						'<span class="name">',
							file.name,
						'</span>',
						'<pre class="output" style="display:none">',
							result.output,
						'</pre>',
					'</li>'
				].join(''));
			}
		}
		Files.$files.append(elements.join(''));
		Filters.apply();
		ProgressBar.render();
	},

	click: function click(e) {
		var target = $(e.target).parents('li')[0];
		if (!target) {
			return;
		}

		$('pre.output', target).toggle();
	},

	total: function total() {
		return $('li', Files.$files).size();
	}
}

$.each(Statuses, function(i, status) {
	Files[status] = function() {
		return $('li.' + status, Files.$files).size();
	}
});


var Filters = {
	$filters: null,

	init: function init() {
		Filters.$filters = $('#filters input[type=checkbox]');
		Filters.$filters.click(Filters.apply);
	},

	apply: function apply() {
		var selected = $.map(Filters.$filters.filter(':checked'), function(element) {
			return 'li.' + element.value;
		});
		selected.push('li.loading');
		
		setTimeout(function() {
			$('li:visible', Files.$files).not(selected.join(', ')).animate({height: 'hide'});
			$('li:hidden', Files.$files).filter(selected.join(', ')).animate({height: 'show'});
		}, 1);
		Filters.render();
	},

	render: function render() {
		$.each(Statuses, function(i, status) {
			$('#filters li.' + status + ' span.count').html(Files[status]());
		});
	}
}

var ProgressBar = {
	$progressBar: null,
	$progressBarCurrent: null,

	init: function init() {
		ProgressBar.$progressBar = $('#progress-bar');
		ProgressBar.$progressBarCurrent = $('#progress-bar-current');
		ProgressBar.$progressBarCurrent.css('width', 0);
	},

	render: function render() {
		var total = Files.total();
		var current = total - Files.untested();
		var width = (40 * current) / total;

		ProgressBar.$progressBarCurrent.css('width', width + 'em');

		ProgressBar.$progressBar
			.removeClass('passed')
			.removeClass('failed');

		if (current != total) {
			return;
		}

		if (Files.failed() > 0) {
			ProgressBar.$progressBar.addClass('failed');
			return;
		}

		ProgressBar.$progressBar.addClass('passed');
	}
}

$(document).ready(function() {
	Files.init();
	Filters.init();
	ProgressBar.init();
	AutoTest.run();
});

})(jQuery);
