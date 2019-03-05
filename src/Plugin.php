<?php
/**
 * Class Google\WP_Reporting_API\Plugin
 *
 * @package   Google\WP_Reporting_API
 * @copyright 2019 Google LLC
 * @license   GNU General Public License, version 2
 * @link      https://wordpress.org/plugins/reporting-api/
 */

namespace Google\WP_Reporting_API;

/**
 * Main class for the plugin.
 *
 * @since 0.1.0
 */
class Plugin {

	/**
	 * Absolute path to the plugin main file.
	 *
	 * @since 0.1.0
	 * @var string
	 */
	protected $main_file;

	/**
	 * The reports controller instance.
	 *
	 * @since 0.1.0
	 * @var Reports
	 */
	protected $reports;

	/**
	 * The report logs controller instance.
	 *
	 * @since 0.1.0
	 * @var Report_Logs
	 */
	protected $report_logs;

	/**
	 * The report types controller instance.
	 *
	 * @since 0.1.0
	 * @var Report_Types
	 */
	protected $report_types;

	/**
	 * The endpoint groups controller instance.
	 *
	 * @since 0.1.0
	 * @var Groups
	 */
	protected $groups;

	/**
	 * Main instance of the plugin.
	 *
	 * @since 0.1.0
	 * @var Plugin|null
	 */
	protected static $instance = null;

	/**
	 * Sets the plugin main file.
	 *
	 * @since 0.1.0
	 *
	 * @param string $main_file Absolute path to the plugin main file.
	 */
	public function __construct( $main_file ) {
		$this->main_file = $main_file;

		$this->reports      = new Reports();
		$this->report_logs  = new Report_Logs( $this->reports );
		$this->report_types = new Report_Types();
		$this->groups       = new Groups();
	}

	/**
	 * Returns the reports controller instance.
	 *
	 * @since 0.1.0
	 *
	 * @return Reports The reports controller instance.
	 */
	public function reports() {
		return $this->reports;
	}

	/**
	 * Returns the report logs controller instance.
	 *
	 * @since 0.1.0
	 *
	 * @return Report_Logs The report logs controller instance.
	 */
	public function report_logs() {
		return $this->report_logs;
	}

	/**
	 * Registers the plugin with WordPress.
	 *
	 * @since 0.1.0
	 */
	public function register() {
		$this->register_db_table_names();

		register_activation_hook(
			$this->main_file,
			function( $network_wide ) {
				if ( $network_wide ) {
					$site_ids = get_sites(
						array(
							'fields'     => 'ids',
							'network_id' => get_current_network_id(),
						)
					);
					foreach ( $site_ids as $site_id ) {
						switch_to_blog( $site_id );
						$this->install_db_tables();
						restore_current_blog();
					}
					return;
				}
				$this->install_db_tables();
			}
		);

		add_filter(
			'user_has_cap',
			array( $this, 'grant_reporting_api_cap' )
		);

		add_action(
			'send_headers',
			function() {
				$group_headers = new Group_Headers( $this->groups );
				$group_headers->send_headers();

				$report_type_headers = new Report_Type_Headers( $this->report_types, $this->groups );
				$report_type_headers->send_headers();
			}
		);

		add_action(
			'rest_api_init',
			function() {
				$controller = new REST\Reporting_Controller( $this->reports, $this->report_logs, $this->report_types );
				$controller->register_routes();
			}
		);

		add_action(
			'admin_menu',
			function() {
				$admin_screen = new Admin\Reports_Screen( $this->reports, $this->report_logs, $this->report_types );
				$admin_screen->register_menu();
			}
		);

		add_action(
			'admin_enqueue_scripts',
			function( $hook_suffix ) {
				$admin_screen = new Admin\Pointers();
				$admin_screen->enqueue_scripts( $hook_suffix );
			}
		);
	}

	/**
	 * Gets the plugin basename, which consists of the plugin directory name and main file name.
	 *
	 * @since 0.1.0
	 *
	 * @return string Plugin basename.
	 */
	public function basename() {
		return plugin_basename( $this->main_file );
	}

	/**
	 * Gets the absolute path for a path relative to the plugin directory.
	 *
	 * @since 0.1.0
	 *
	 * @param string $relative_path Optional. Relative path. Default '/'.
	 * @return string Absolute path.
	 */
	public function path( $relative_path = '/' ) {
		return plugin_dir_path( $this->main_file ) . ltrim( $relative_path, '/' );
	}

	/**
	 * Gets the full URL for a path relative to the plugin directory.
	 *
	 * @since 0.1.0
	 *
	 * @param string $relative_path Optional. Relative path. Default '/'.
	 * @return string Full URL.
	 */
	public function url( $relative_path = '/' ) {
		return plugin_dir_url( $this->main_file ) . ltrim( $relative_path, '/' );
	}

	/**
	 * Gets the URL to the plugin's built-in reporting REST endpoint.
	 *
	 * @since 0.1.0
	 *
	 * @return string Reporting endpoint URL.
	 */
	public function reporting_endpoint_url() {
		return rest_url( REST\Reporting_Controller::REST_NAMESPACE . '/' . REST\Reporting_Controller::REST_BASE );
	}

	/**
	 * Gets the URL to the plugin's reports screen.
	 *
	 * @since 0.1.0
	 *
	 * @return string Reports screen URL.
	 */
	public function reports_screen_url() {
		return add_query_arg( 'page', Admin\Reports_Screen::SLUG, admin_url( Admin\Reports_Screen::PARENT_SLUG ) );
	}

	/**
	 * Dynamically grants the 'manage_reporting_api' capability based on 'manage_options'.
	 *
	 * This method is hooked into the `user_has_cap` filter and can be unhooked and replaced with custom functionality
	 * if needed.
	 *
	 * @since 0.1.0
	 *
	 * @param array $allcaps Associative array of $cap => $grant pairs.
	 * @return array Filtered $allcaps array.
	 */
	public function grant_reporting_api_cap( array $allcaps ) {
		if ( isset( $allcaps['manage_options'] ) ) {
			$allcaps[ Admin\Reports_Screen::CAPABILITY ] = $allcaps['manage_options'];
		}

		return $allcaps;
	}

	/**
	 * Registers the database table names.
	 *
	 * @since 0.1.0
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 */
	protected function register_db_table_names() {
		global $wpdb;

		$reports_table     = Reports::DB_TABLE;
		$report_logs_table = Report_Logs::DB_TABLE;

		$wpdb->tables[]             = $reports_table;
		$wpdb->tables[]             = $report_logs_table;
		$wpdb->{$reports_table}     = $wpdb->prefix . $reports_table;
		$wpdb->{$report_logs_table} = $wpdb->prefix . $report_logs_table;
	}

	/**
	 * Installs the database tables.
	 *
	 * @since 0.1.0
	 */
	protected function install_db_tables() {
		global $wpdb;

		$queries = array();

		$max_index_length = 191;
		$charset_collate  = $wpdb->get_charset_collate();

		$reports_table = Reports::DB_TABLE;
		if ( ! $wpdb->get_var( "SHOW TABLES LIKE '{$wpdb->{$reports_table}}'" ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
			$reports_schema = array(
				'id bigint(20) unsigned NOT NULL auto_increment',
				"type char(50) NOT NULL default ''",
				'body longtext NOT NULL',
				'PRIMARY KEY  (id)',
				'KEY type (type)',
			);
			$reports_schema = "\n\t" . implode( ",\n\t", $reports_schema ) . "\n";

			$queries[] = "CREATE TABLE {$wpdb->{$reports_table}} ({$reports_schema}) {$charset_collate};";
		}

		$report_logs_table = Report_Logs::DB_TABLE;
		if ( ! $wpdb->get_var( "SHOW TABLES LIKE '{$wpdb->{$report_logs_table}}'" ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
			$report_logs_schema = array(
				'id bigint(20) unsigned NOT NULL auto_increment',
				'report_id bigint(20) unsigned NOT NULL',
				'url varchar(255) NOT NULL',
				"user_agent varchar(255) NOT NULL default ''",
				"triggered datetime NOT NULL default '0000-00-00 00:00:00'",
				"reported datetime NOT NULL default '0000-00-00 00:00:00'",
				'PRIMARY KEY  (id)',
				'KEY report_id (report_id)',
				"KEY url (url($max_index_length))",
			);
			$report_logs_schema = "\n\t" . implode( ",\n\t", $report_logs_schema ) . "\n";

			$queries[] = "CREATE TABLE {$wpdb->{$report_logs_table}} ({$report_logs_schema}) {$charset_collate};";
		}

		if ( empty( $queries ) ) {
			return;
		}

		if ( ! function_exists( 'dbDelta' ) ) {
			require_once ABSPATH . '/wp-admin/includes/upgrade.php';
		}

		dbDelta( implode( "\n", $queries ) );
	}

	/**
	 * Retrieves the main instance of the plugin.
	 *
	 * @since 0.1.0
	 *
	 * @return Plugin Plugin main instance.
	 */
	public static function instance() {
		return static::$instance;
	}

	/**
	 * Loads the plugin main instance and initializes it.
	 *
	 * @since 0.1.0
	 *
	 * @param string $main_file Absolute path to the plugin main file.
	 * @return bool True if the plugin main instance could be loaded, false otherwise.
	 */
	public static function load( $main_file ) {
		if ( null !== static::$instance ) {
			return false;
		}

		static::$instance = new static( $main_file );
		static::$instance->register();

		return true;
	}
}
