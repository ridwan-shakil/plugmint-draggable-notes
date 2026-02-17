<?php
require '/wp-phpunit/includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	function () {
		require __DIR__ . '/../unit-test-ridwan.php';
	}
);

require '/wp-phpunit/includes/bootstrap.php';
