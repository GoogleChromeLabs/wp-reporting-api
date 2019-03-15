<?php
/**
 * Plugin initialization file
 *
 * @package   Google\WP_Reporting_API
 * @copyright 2019 Google LLC
 * @license   GNU General Public License, version 2
 * @link      https://wordpress.org/plugins/reporting-api/
 *
 * @wordpress-plugin
 * Plugin Name: Reporting API
 * Plugin URI:  https://wordpress.org/plugins/reporting-api/
 * Description: WordPress plugin for receiving browser reports via a Reporting API endpoint.
 * Version:     0.1.1
 * Author:      Google
 * Author URI:  https://opensource.google.com/
 * License:     GNU General Public License v2 (or later)
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: reporting-api
 */

/* This file must be parseable by PHP 5.2. */

/**
 * Loads the plugin.
 *
 * @since 0.1.0
 * @access private
 */
function reporting_api_load() {
	if ( version_compare( phpversion(), '5.6', '<' ) ) {
		add_action( 'admin_notices', 'reporting_api_display_php_version_notice' );
		return;
	}

	if ( version_compare( get_bloginfo( 'version' ), '4.7', '<' ) ) {
		add_action( 'admin_notices', 'reporting_api_display_wp_version_notice' );
		return;
	}

	if ( ! class_exists( 'Google\\WP_Reporting_API\\Plugin' ) ) {
		if ( ! file_exists( dirname( __FILE__ ) . '/vendor/autoload.php' ) ) {
			add_action( 'admin_notices', 'reporting_api_display_composer_install_requirement' );
			return;
		}

		require_once dirname( __FILE__ ) . '/vendor/autoload.php';
	}

	call_user_func( array( 'Google\\WP_Reporting_API\\Plugin', 'load' ), __FILE__ );
}

/**
 * Displays an admin notice about an unmet PHP version requirement.
 *
 * @since 0.1.0
 * @access private
 */
function reporting_api_display_php_version_notice() {
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
 * @access private
 */
function reporting_api_display_wp_version_notice() {
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

/**
 * Displays an admin notice about the need to run `composer install`.
 *
 * @since 0.1.0
 * @access private
 */
function reporting_api_display_composer_install_requirement() {
	?>
	<div class="notice notice-error">
		<p>
			<?php
			printf(
				/* translators: %s: the composer install command */
				esc_html__( 'Reporting API appears to being run from source and requires %s to complete its installation.', 'reporting-api' ),
				'<code>composer install</code>'
			);
			?>
		</p>
	</div>
	<?php
}

reporting_api_load();
