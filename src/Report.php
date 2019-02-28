<?php
/**
 * Class Google\WP_Reporting_API\Report
 *
 * @package   Google\WP_Reporting_API
 * @copyright 2019 Google LLC
 * @license   GNU General Public License, version 2
 * @link      https://wordpress.org/plugins/reporting-api/
 */

namespace Google\WP_Reporting_API;

/**
 * Class representing a report.
 *
 * @since 0.1.0
 */
class Report {

	/**
	 * Unique report ID, or 0 if not persisted yet.
	 *
	 * @since 0.1.0
	 * @var int
	 */
	protected $id = 0;

	/**
	 * Report properties.
	 *
	 * @since 0.1.0
	 * @var array
	 */
	protected $props = array();

	/**
	 * Constructor.
	 *
	 * Sets the report properties.
	 *
	 * @since 0.1.0
	 *
	 * @param array $props {
	 *     Report properties, including the ID if present.
	 *
	 *     @type int           $id   Unique report ID, or 0 if not persisted yet. Default 0.
	 *     @type string        $type Report type. Default empty string.
	 *     @type object|string $body The report body data, either as object with public properties or JSON string.
	 *                               The properties that the object should have depends on the report $type. Default
	 *                               empty object.
	 * }
	 */
	public function __construct( array $props ) {
		if ( isset( $props['id'] ) ) {
			$this->id = (int) $props['id'];
			unset( $props['id'] );
		}

		$this->set_props( $props );
	}

	/**
	 * Magic isset-er.
	 *
	 * @since 0.1.0
	 *
	 * @param string $prop Property to check for.
	 * @return bool True if the property is set, false otherwise.
	 */
	public function __isset( $prop ) {
		if ( 'id' === $prop ) {
			return true;
		}

		return isset( $this->props[ $prop ] );
	}

	/**
	 * Magic getter.
	 *
	 * @since 0.1.0
	 *
	 * @param string $prop Property to get.
	 * @return mixed The property value, or null if property not set.
	 */
	public function __get( $prop ) {
		if ( 'id' === $prop ) {
			return $this->id;
		}

		if ( isset( $this->props[ $prop ] ) ) {
			return $this->props[ $prop ];
		}

		return null;
	}

	/**
	 * Queries report logs associated with this report.
	 *
	 * @since 0.1.0
	 *
	 * @param array $query_vars Array of report log query arguments. See {@see Report_Log_Query::__construct()} for a
	 *                          list of supported arguments. The $report_id, $fields and $no_found_rows arguments are
	 *                          automatically set, i.e. not supported here.
	 * @return array List of {@see Report_Log} instances.
	 */
	public function query_logs( array $query_vars ) {
		if ( ! $this->id ) {
			return array();
		}

		$query_vars['report_id'] = $this->id;

		return Plugin::instance()->report_logs()->query( $query_vars );
	}

	/**
	 * Queries log data associated with the report.
	 *
	 * @since 0.1.0
	 *
	 * @return array Associative array with all log data.
	 */
	public function query_log_data() {
		global $wpdb;

		$defaults = array(
			'urls'            => array(),
			'user_agents'     => array(),
			'count'           => 0,
			'first_triggered' => '',
			'last_triggered'  => '',
			'first_reported'  => '',
			'last_reported'   => '',
		);

		if ( empty( $this->id ) ) {
			return $defaults;
		}

		$data = wp_cache_get( (string) $this->id . '_log_data', Reports::CACHE_GROUP );
		if ( false === $data ) {
			$report_logs = Plugin::instance()->report_logs();
			$table_name  = $report_logs->get_db_table_name();

			$data = $wpdb->get_row( $wpdb->prepare( "SELECT COUNT(*) AS count, MIN(triggered) AS first_triggered, MAX(triggered) AS last_triggered, MIN(reported) AS first_reported, MAX(reported) AS last_reported FROM {$table_name} WHERE report_id = %d", $this->id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL

			$data['count']       = (int) $data['count'];
			$data['urls']        = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT url FROM {$table_name} WHERE report_id = %d", $this->id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
			$data['user_agents'] = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT user_agent FROM {$table_name} WHERE report_id = %d", $this->id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL

			wp_cache_add( (string) $this->id . '_log_data', $data, Reports::CACHE_GROUP );
		}

		return array_merge( $defaults, $data );
	}

	/**
	 * Sets the report properties.
	 *
	 * @since 0.1.0
	 *
	 * @param array $props List of report properties. See {@see Report::__construct()} for a list of supported
	 *                     properties.
	 */
	protected function set_props( array $props ) {
		$defaults = array(
			'type' => '',
			'body' => new \stdClass(),
		);

		$this->props = array_intersect_key( array_merge( $defaults, $props ), $defaults );

		if ( is_string( $this->props['body'] ) ) {
			$this->props['body'] = $this->decode_body( $this->props['body'] );
		}
	}

	/**
	 * JSON-decodes the report body.
	 *
	 * @since 0.1.0
	 *
	 * @param string $body Report body JSON string.
	 * @return object Object based on $body.
	 */
	protected function decode_body( $body ) {
		if ( ! empty( $body ) ) {
			return json_decode( $body );
		}

		return new \stdClass();
	}
}
