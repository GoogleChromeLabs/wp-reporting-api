<?php
/**
 * Unit tests bootstrap script.
 *
 * @package Google\WP_Reporting_API
 * @license GNU General Public License, version 2
 * @link    https://wordpress.org/plugins/reporting-api/
 */

// Detect project directory.
define( 'TESTS_PLUGIN_DIR', dirname( dirname( __DIR__ ) ) );

// Disable xdebug backtrace.
if ( function_exists( 'xdebug_disable' ) ) {
	xdebug_disable();
}

require_once TESTS_PLUGIN_DIR . '/vendor/autoload.php';

// PHPUnit < 6.0 compatibility shim.
require_once __DIR__ . '/phpunit-compat.php';
