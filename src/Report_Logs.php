<?php
/**
 * Class Google\WP_Reporting_API\Report_Logs
 *
 * @package   Google\WP_Reporting_API
 * @copyright 2019 Google LLC
 * @license   GNU General Public License, version 2
 * @link      https://wordpress.org/plugins/reporting-api/
 */

namespace Google\WP_Reporting_API;

use WP_Error;

/**
 * Class for accessing report logs.
 *
 * @since 0.1.0
 */
class Report_Logs {

	const DB_TABLE    = 'ra_report_logs';
	const CACHE_GROUP = 'ra_report_logs';

	/**
	 * The reports controller instance.
	 *
	 * @since 0.1.0
	 * @var Reports
	 */
	protected $reports;

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 *
	 * @param Reports $reports The reports controller instance.
	 */
	public function __construct( Reports $reports ) {
		$this->reports = $reports;
	}

	/**
	 * Gets multiple report logs using a query.
	 *
	 * @since 0.1.0
	 *
	 * @param array $query_vars Array of report log query arguments. See {@see Report_Log_Query::__construct()} for a
	 *                          list of supported arguments. The $fields and $no_found_rows arguments are automatically
	 *                          set, i.e. not supported here.
	 * @return array List of {@see Report_Log} instances.
	 */
	public function query( array $query_vars ) {
		$query_args['fields']        = 'all';
		$query_vars['no_found_rows'] = true;

		$query = $this->get_query( $query_vars );
		return $query->get_results();
	}

	/**
	 * Counts report logs using a query.
	 *
	 * @since 0.1.0
	 *
	 * @param array $query_vars Array of report log query arguments. See {@see Report_Log_Query::__construct()} for a
	 *                          list of supported arguments. The $fields and $no_found_rows arguments are automatically
	 *                          set, i.e. not supported here.
	 * @return int Count of report logs returned by the query.
	 */
	public function count( array $query_vars ) {
		$query_args['fields']        = 'count';
		$query_vars['no_found_rows'] = true;

		$query = $this->get_query( $query_vars );
		return $query->get_results();
	}

	/**
	 * Gets an existing report log.
	 *
	 * @since 0.1.0
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 *
	 * @param int $id Report log ID.
	 * @return Report_Log|null Report log instance, or null if not found.
	 */
	public function get( $id ) {
		global $wpdb;

		$id = (int) $id;

		$props = wp_cache_get( $id, self::CACHE_GROUP );
		if ( false === $props ) {
			$props = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL
					"SELECT * FROM {$this->get_db_table_name()} WHERE id = %d",
					$id
				),
				\ARRAY_A
			);
			if ( ! $props ) {
				return null;
			}

			wp_cache_add( $id, $props, self::CACHE_GROUP );
		}

		return new Report_Log( $props );
	}

	/**
	 * Inserts a new report log into the database.
	 *
	 * @since 0.1.0
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 *
	 * @param Report_Log $report_log Report log to insert. Must not have an ID set.
	 * @return Report_Log|WP_Error New report log instance on success, WP_Error on failure.
	 */
	public function insert( Report_Log $report_log ) {
		global $wpdb;

		if ( $report_log->id > 0 ) {
			return new WP_Error( 'report_log_already_exists', __( 'Cannot insert an existing report log.', 'reporting-api' ) );
		}

		$db_args = $this->prepare_db_args( $report_log );

		$status = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$this->get_db_table_name(),
			$db_args,
			array_fill( 0, count( $db_args ), '%s' )
		);
		if ( ! $status ) {
			return new WP_Error( 'report_log_insertion_failed', __( 'Cannot insert report log due to an internal error.', 'reporting-api' ) );
		}

		$new_id = (int) $wpdb->insert_id;

		$this->clean_cache( $new_id );
		$this->reports->clean_cache( $db_args['report_id'] );

		return $this->get( $new_id );
	}

	/**
	 * Updates a report log in the database.
	 *
	 * @since 0.1.0
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 *
	 * @param Report_Log $report_log Report log to update. Must have an ID set.
	 * @return Report_Log|WP_Error Updated report log instance on success, WP_Error on failure.
	 */
	public function update( Report_Log $report_log ) {
		global $wpdb;

		if ( ! $report_log->id ) {
			return new WP_Error( 'report_log_not_exists', __( 'Cannot update an non-existing report log.', 'reporting-api' ) );
		}

		$db_args = $this->prepare_db_args( $report_log );

		$status = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$this->get_db_table_name(),
			$db_args,
			array( 'id' => $report_log->id ),
			array_fill( 0, count( $db_args ), '%s' ),
			array( '%d' )
		);
		if ( ! $status ) {
			return new WP_Error( 'report_log_update_failed', __( 'Cannot update report log due to an internal error.', 'reporting-api' ) );
		}

		$this->clean_cache( $report_log->id );
		$this->reports->clean_cache( $db_args['report_id'] );

		return $this->get( $report_log->id );
	}

	/**
	 * Deletes a report log from the database.
	 *
	 * @since 0.1.0
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 *
	 * @param Report_Log $report_log Report log to delete. Must have an ID set.
	 * @return Report_Log|WP_Error Deleted report log instance on success, WP_Error on failure.
	 */
	public function delete( Report_Log $report_log ) {
		global $wpdb;

		if ( ! $report_log->id ) {
			return new WP_Error( 'report_log_not_exists', __( 'Cannot delete a non-existing report log.', 'reporting-api' ) );
		}

		$status = $wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$this->get_db_table_name(),
			array( 'id' => $report_log->id ),
			array( '%d' )
		);
		if ( ! $status ) {
			return new WP_Error( 'report_log_deletion_failed', __( 'Cannot delete report log due to an internal error.', 'reporting-api' ) );
		}

		$db_args = $this->prepare_db_args( $report_log );

		$this->clean_cache( $report_log->id );
		$this->reports->clean_cache( $db_args['report_id'] );

		return new Report_Log( $db_args );
	}

	/**
	 * Cleans the cache for a specific report log.
	 *
	 * @since 0.1.0
	 *
	 * @param int $id Report log ID for which to clean the cache.
	 */
	public function clean_cache( $id ) {
		$id = (int) $id;

		wp_cache_delete( $id, self::CACHE_GROUP );
		wp_cache_set( 'last_changed', microtime(), self::CACHE_GROUP );
	}

	/**
	 * Gets a new report log query instance without executing it.
	 *
	 * @since 0.1.0
	 *
	 * @param array $query_vars Array of report log query arguments. See {@see Report_Log_Query::__construct()} for a
	 *                          list of supported arguments.
	 * @return Report_Log_Query New report log query instance.
	 */
	public function get_query( array $query_vars ) {
		return new Report_Log_Query( $this, $query_vars );
	}

	/**
	 * Gets the full database table name.
	 *
	 * @since 0.1.0
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 *
	 * @return string Full Database table name.
	 */
	public function get_db_table_name() {
		global $wpdb;

		$unprefixed = self::DB_TABLE;

		return $wpdb->{$unprefixed};
	}

	/**
	 * Prepares a report log's properties as arguments for the database.
	 *
	 * @since 0.1.0
	 *
	 * @param Report_Log $report_log The report log to prepare database arguments for.
	 * @return array The database arguments for the report log.
	 */
	protected function prepare_db_args( Report_Log $report_log ) {
		$args = array(
			'report_id'  => $report_log->report_id,
			'url'        => $report_log->url,
			'user_agent' => $report_log->user_agent,
			'triggered'  => $report_log->triggered,
			'reported'   => $report_log->reported,
		);

		return $args;
	}
}
