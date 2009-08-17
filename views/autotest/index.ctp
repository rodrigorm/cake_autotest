<style type="text/css" media="screen">
#files {
	clear: both;
	list-style-type: none;
	margin: 0;
	}
	#files li {
		margin: 0.2em 0;
		}
		#files li  span {}
			#files li  span.status {
				margin-right: 0.5em;
				}
			#files li  span.name {
				margin-right: 0.2em;
				}
		#files li.passed span.status {
			color: green;
			}
		#files li.failed span.status {
			color: red;
			}
		#files li.skipped span.red {
			color: blue;
			}
		#files li.unknown span.status {}
		#files li.untested span.status {
			color: gray;
			}
		#files li pre {
			background-color: #F6F5FE;
			border: solid 1px gray;
			padding: 0.5em;
			margin: 0.2em;
			margin-left: 2em;
			}

#filters {
	list-style-type: none;
	}
	#filters li {
		float: left;
		margin-right: 0.4em;
		}
		#filters li label {
			display: block;
			padding: 0.2em;
			}
			#filters li label input {
				float: none;
				margin-right: 0.2em;
				vertical-align: middle;
				}
	#filters li.passed {
		color: green;
		}
	#filters li.failed {
		color: red;
		}
	#filters li.skipped {
		color: blue;
		}
	#filters li.unknown {}
	#filters li.untested {
		color: gray;
		}

#progress {
	clear: both;
	}
	#progress-bar {
		border: solid 1px gray;
		width: 40em;
		}
		#progress-bar-current {
			background-color: gray;
			height: 1em;
			width: 20em;
			}
	#progress-bar.passed #progress-bar-current {
		background-color: green;
		}
	#progress-bar.failed #progress-bar-current {
		background-color: red;
		}
</style>

<h1>AutoTest Web UI</h1>

<ul id="filters">
	<li class="passed"><label><input type="checkbox" name="filters[]" value="passed" checked="checked" /> Passed (<span class="count">0</span>)</label></li>
	<li class="failed"><label><input type="checkbox" name="filters[]" value="failed" checked="checked" /> Failed (<span class="count">0</span>)</label></li>
	<li class="skipped"><label><input type="checkbox" name="filters[]" value="skipped" checked="checked" /> Skipped (<span class="count">0</span>)</label></li>
	<li class="unknown"><label><input type="checkbox" name="filters[]" value="unknown" checked="checked" /> Unknow (<span class="count">0</span>)</label></li>
	<li class="untested"><label><input type="checkbox" name="filters[]" value="untested" checked="checked" /> Untested (<span class="count">0</span>)</label></li>
</ul>

<div id="progress">
	<div id="progress-bar">
		<div id="progress-bar-current">
		</div>
	</div>
</div>

<ul id="files">
</ul>

<?php echo $javascript->link('/autotest/js/jquery-1.3.2.min') ?>
<?php echo $javascript->link('/autotest/js/autotest') ?>