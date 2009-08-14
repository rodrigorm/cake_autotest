<style type="text/css" media="screen">
#files {}
	#files li {
		margin: 0.2em 0;
		}
		#files li  span {}
			#files li  span.result {
				margin-right: 0.5em;
				}
			#files li  span.name {}
		#files li.passed span.result {
			color: green;
			}
		#files li.failed span.result {
			color: red;
			}
		#files li.skipped span.red {
			color: blue;
			}
		#files li.unknown span.result {
			color: gray;
			}
		#files li pre {
			background-color: #F6F5FE;
			border: solid 1px gray;
			padding: 0.5em;
			margin: 0.2em;
			margin-left: 2em;
			}
</style>

<h1>AutoTest Web UI</h1>

<form id="filters">
	<ul>
		<li><label><input type="checkbox" name="filters[]" value="passed" checked="checked" /> Passed</label></li>
		<li><label><input type="checkbox" name="filters[]" value="skipped" checked="checked" /> Skipped</label></li>
		<li><label><input type="checkbox" name="filters[]" value="failed" checked="checked" /> Failed</label></li>
		<li><label><input type="checkbox" name="filters[]" value="unknown" checked="checked" /> Unknow</label></li>
	</ul>
</form>

<ul id="files">
</ul>

<?php echo $javascript->link('/autotest/js/jquery-1.3.2.min') ?>
<?php echo $javascript->link('/autotest/js/autotest') ?>