<?php
/**
 * WordPress PHPUnit bootstrap for Flipset.
 *
 * @package Flipset
 */

$plugin_dir = dirname( __DIR__, 2 );

$_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $_tests_dir ) {
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	fwrite( STDERR, "WordPress test suite not found at {$_tests_dir}.\nRun: bash bin/install-wp-tests.sh wordpress_test root root 127.0.0.1 latest\n" );
	exit( 1 );
}

$autoload = $plugin_dir . '/vendor/autoload.php';
if ( ! file_exists( $autoload ) ) {
	fwrite( STDERR, "Composer autoload not found. Run: composer install\n" );
	exit( 1 );
}
require $autoload;

if ( ! defined( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' ) ) {
	define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', $plugin_dir . '/vendor/yoast/phpunit-polyfills' );
}

require_once $_tests_dir . '/includes/functions.php';

/**
 * Load Flipset as a mu-plugin so shortcodes and hooks register.
 */
function flipset_tests_load_plugin() {
	require dirname( __DIR__, 2 ) . '/flipset.php';
}
tests_add_filter( 'muplugins_loaded', 'flipset_tests_load_plugin' );

require $_tests_dir . '/includes/bootstrap.php';
require_once $plugin_dir . '/tests/php/class-flipset-testcase.php';
