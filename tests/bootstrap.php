<?php

error_reporting( error_reporting() & ~E_DEPRECATED & ~E_USER_DEPRECATED );

$_tests_dir = getenv('WP_TESTS_DIR');
if ( !$_tests_dir ) $_tests_dir = '/tmp/wordpress-tests-lib';

if ( ! function_exists( 'create_function' ) ) {
	/**
	 * Polyfill for deprecated create_function().
	 *
	 * @param string $args Comma separated argument list.
	 * @param string $code Function body code.
	 * @return Closure
	 */
	function create_function( $args, $code ) {
		$args = trim( $args );
		$code = trim( $code );

		return eval( 'return function(' . $args . ') { ' . $code . ' };' );
	}
}

$vendor_autoload = dirname( __FILE__ ) . '/../vendor/autoload.php';
if ( file_exists( $vendor_autoload ) ) {
	require_once $vendor_autoload;
}

require_once $_tests_dir . '/includes/functions.php';

function _manually_load_plugin() {
	global $wp_theatre;

	require dirname( __FILE__ ) . '/../theater.php';
}
tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

require $_tests_dir . '/includes/bootstrap.php';
require dirname( __FILE__ ) . '/../functions/wpt_unittestcase.php';
