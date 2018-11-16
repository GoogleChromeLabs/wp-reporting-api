<?php
/**
 * Plugin initialization file
 *
 * @package Google\WP_Reporting_API
 * @license GNU General Public License, version 2
 * @link    https://wordpress.org/plugins/reporting-api/
 *
 * @wordpress-plugin
 * Plugin Name: Reporting API
 * Plugin URI:  https://wordpress.org/plugins/reporting-api/
 * Description: WordPress plugin for implementing an endpoint for tbrowser reporting.
 * Version:     0.1.0
 * Author:      Google
 * Author URI:  https://opensource.google.com/
 * License:     GNU General Public License v2 (or later)
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: reporting-api
 * Tags:        reporting, api
 */

/* This file must be parseable by PHP 5.2. */

/**
 * Loads the plugin.
 *
 * @since 0.1.0
 */
function _wp_reporting_api_load() {
	if ( version_compare( phpversion(), '5.6', '<' ) ) {
		add_action( 'admin_notices', '_wp_reporting_api_display_php_version_notice' );
		return;
	}

	if ( version_compare( get_bloginfo( 'version' ), '4.7', '<' ) ) {
		add_action( 'admin_notices', '_wp_reporting_api_display_wp_version_notice' );
		return;
	}

	if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
		require __DIR__ . '/vendor/autoload.php';
	}

	call_user_func( array( 'Google\\WP_Reporting_API\\Plugin', 'load' ), __FILE__ );
}

/**
 * Displays an admin notice about an unmet PHP version requirement.
 *
 * @since 0.1.0
 */
function _wp_reporting_api_display_php_version_notice() {
	?>
	<div class="notice notice-error">
		<p>
			<?php
			sprintf(
				/* translators: 1: required version, 2: currently used version */
				__( 'Reporting API requires at least PHP version %1$s. Your site is currently running on PHP %2$s.', 'reporting-api' ),
				'5.6',
				phpversion()
			);
			?>
		</p>
	</div>
	<?php
}

/**
 * Displays an admin notice about an unmet WordPress version requirement.
 *
 * @since 0.1.0
 */
function _wp_reporting_api_display_wp_version_notice() {
	?>
	<div class="notice notice-error">
		<p>
			<?php
			sprintf(
				/* translators: 1: required version, 2: currently used version */
				__( 'Reporting API requires at least WordPress version %1$s. Your site is currently running on WordPress %2$s.', 'reporting-api' ),
				'4.7',
				get_bloginfo( 'version' )
			);
			?>
		</p>
	</div>
	<?php
}

_wp_reporting_api_load();
