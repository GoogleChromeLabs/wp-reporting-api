<?php
/**
 * Class Google\WP_Reporting_API\Report_Query
 *
 * @package Google\WP_Reporting_API
 * @license GNU General Public License, version 2
 * @link    https://wordpress.org/plugins/reporting-api/
 */

namespace Google\WP_Reporting_API;

use WP_Date_Query;

/**
 * Class for querying reports.
 *
 * @since 0.1.0
 */
class Report_Query {

	/**
	 * Parent reports controller instance.
	 *
	 * @since 0.1.0
	 * @var Reports
	 */
	protected $reports;

	/**
	 * SQL for database query.
	 *
	 * @since 0.1.0
	 * @var string
	 */
	protected $request = '';

	/**
	 * SQL query clauses.
	 *
	 * @since 0.1.0
	 * @var array
	 */
	protected $sql_clauses = array(
		'select'  => '',
		'from'    => '',
		'where'   => array(),
		'groupby' => '',
		'orderby' => '',
		'limits'  => '',
	);

	/**
	 * Date query container.
	 *
	 * @since 0.1.0
	 * @var WP_Date_Query
	 */
	protected $date_query = null;

	/**
	 * Query vars provided.
	 *
	 * @since 0.1.0
	 * @var array
	 */
	protected $query_vars = array();

	/**
	 * Default values for query vars.
	 *
	 * @since 0.1.0
	 * @var array
	 */
	protected $query_var_defaults = array();

	/**
	 * List of reports located by the query.
	 *
	 * @since 0.1.0
	 * @var array
	 */
	public $results = array();

	/**
	 * The amount of found reports for the current query.
	 *
	 * @since 0.1.0
	 * @var int
	 */
	public $found_results = 0;

	/**
	 * The number of pages.
	 *
	 * @since 0.1.0
	 * @var int
	 */
	public $max_num_pages = 0;

	/**
	 * Constructor.
	 *
	 * Sets the query vars.
	 *
	 * @since 0.1.0
	 *
	 * @param Reports $reports    Parent reports controller instance.
	 * @param array   $query_vars {
	 *     Array of report query arguments.
	 *
	 *     @type array        $include        Array of report IDs to include. Default empty.
	 *     @type array        $exclude        Array of report IDs to exclude. Default empty.
	 *     @type int          $number         Maximum number of reports to retrieve. Default 10.
	 *     @type int          $offset         Number of reports to offset the query. Used to build LIMIT clause.
	 *                                        Default 0.
	 *     @type bool         $no_found_rows  Whether to disable the `SQL_CALC_FOUND_ROWS` query. Default false.
	 *     @type string|array $orderby        Report orderby field or array of orderby fields. Accepts 'id', 'type',
	 *                                        'first_triggered', 'first_reported', 'last_triggered', 'last_reported'.
	 *                                        Default 'last_reported'.
	 *     @type string       $order          How to order retrieved reports. Accepts 'ASC', 'DESC'. Default 'DESC'.
	 *     @type string|array $type           Limit results to those affiliated with a given type. Default empty
	 *                                        string.
	 *     @type array        $date_query     Date query clauses to limit reports by. See WP_Date_Query. Default null.
	 *     @type string       $search         Search term(s) to retrieve matching reports for. Default empty.
	 *     @type array        $search_columns Array of column names to be searched. Accepts 'url', 'user_agent', and
	 *                                        'body'. Default empty array.
	 *     @type string       $fields         Report fields to return. Accepts 'ids' (returns an array of report IDs),
	 *                                        'count' (returns a report count) or 'all' (returns an array of complete
	 *                                        report objects).
	 *     @type bool         $update_cache   Whether to prime the cache for found reports. Default true.
	 * }
	 */
	public function __construct( Reports $reports, array $query_vars ) {
		$this->reports            = $reports;
		$this->query_var_defaults = array(
			'include'        => array(),
			'exclude'        => array(),
			'number'         => 10,
			'offset'         => 0,
			'no_found_rows'  => false,
			'orderby'        => 'last_reported',
			'order'          => 'DESC',
			'type'           => '',
			'date_query'     => null,
			'search'         => '',
			'search_columns' => array(),
			'fields'         => 'all',
			'update_cache'   => true,
		);

		$this->parse_query_vars( $query_vars );
	}

	/**
	 * Gets results for the query.
	 *
	 * This method will first check for cached query results, and only perform actual SQL queries if necessary, by
	 * calling {@see Report_Query::query_results()}. The method will then take care of parsing the reponse into the
	 * correct format and setting up the relevant class properties.
	 *
	 * @since 0.1.0
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 *
	 * @return array|int List of results if $fields is 'all', list of result IDs if $fields is 'ids', or the number of
	 *                   found results if $fields is 'count'.
	 */
	public function get_results() {
		global $wpdb;

		$_args = wp_array_slice_assoc( $this->query_vars, array_keys( $this->query_var_defaults ) );

		// Set the 'fields' arg to one distinct value unless it is 'count', since the results will match regardless.
		if ( 'count' !== $_args['fields'] ) {
			$_args['fields'] = 'all';
		}

		$key          = md5( serialize( $_args ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
		$last_changed = wp_cache_get_last_changed( Reports::CACHE_GROUP );

		$cache_key   = "query:$key:$last_changed";
		$cache_value = wp_cache_get( $cache_key, Reports::CACHE_GROUP );

		if ( false === $cache_value ) {
			$result_ids          = $this->query_results();
			$this->found_results = $this->get_found_results( $result_ids );

			$cache_value = array(
				'ids'   => $result_ids,
				'found' => $this->found_results,
			);
			wp_cache_add( $cache_key, $cache_value, Reports::CACHE_GROUP );
		} else {
			$result_ids          = $cache_value['ids'];
			$this->found_results = $cache_value['found'];
		}

		if ( $this->found_results && $this->query_vars['number'] ) {
			$this->max_num_pages = ceil( $this->found_results / $this->query_vars['number'] );
		}

		if ( 'count' === $this->query_vars['fields'] ) {
			// $result_ids is actually a count in this case.
			return (int) $result_ids;
		}

		$result_ids = array_map( 'intval', $result_ids );

		if ( 'ids' === $this->query_vars['fields'] ) {
			$this->results = $result_ids;

			return $this->results;
		}

		if ( $this->query_vars['update_cache'] ) {
			$this->prime_caches( $result_ids );
		}

		// Convert to Report instances.
		$this->results = array_filter( array_map( array( $this->reports, 'get_report' ), $result_ids ) );

		return $this->results;
	}

	/**
	 * Creates and executes the SQL query to query results.
	 *
	 * @since 0.1.0
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 *
	 * @return array|int List of result IDs if $fields is 'all' or 'ids', or the number of found results if $fields
	 *                   is 'count'.
	 */
	protected function query_results() {
		global $wpdb;

		// TODO.
		return array();
	}

	/**
	 * Gets the number of found results.
	 *
	 * Depending on the query vars provided and the results returned, this method either queries the number of all
	 * found database rows, or simply counts the results themselves.
	 *
	 * @since 0.1.0
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 *
	 * @param array|int $result_ids List of result IDs, or result count if $fields query var is 'count'.
	 * @return int Number of found results.
	 */
	protected function get_found_results( $result_ids ) {
		// $result_ids is actually a count already in this case.
		if ( 'count' === $this->query_vars['fields'] ) {
			return (int) $result_ids;
		}

		if ( ! empty( $result_ids ) && $this->query_vars['number'] && ! $this->query_vars['no_found_rows'] ) {
			return (int) $wpdb->get_var( 'SELECT FOUND_ROWS()' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}

		return count( $result_ids );
	}

	/**
	 * Adds any results from the given IDs to the cache that do not already exist in cache.
	 *
	 * @since 0.1.0
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 *
	 * @param array $result_ids List of result IDs.
	 */
	protected function prime_caches( $result_ids ) {
		global $wpdb;

		$non_cached_ids = _get_non_cached_ids( $result_ids, Reports::CACHE_GROUP );
		if ( empty( $non_cached_ids ) ) {
			return;
		}

		$fresh_results = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			sprintf(
				// phpcs:ignore WordPress.DB.PreparedSQL
				"SELECT * FROM {$this->reports->get_db_table_name()} WHERE id IN (%s)",
				join( ',', array_map( 'intval', $non_cached_ids ) ) // phpcs:ignore WordPress.DB.PreparedSQL
			),
			ARRAY_A
		);
		if ( ! $fresh_results ) {
			return;
		}

		foreach ( $fresh_results as $result ) {
			wp_cache_add( $result->id, $result, Reports::CACHE_GROUP );
		}
	}

	/**
	 * Parses query vars provided.
	 *
	 * @since 0.1.0
	 *
	 * @param array $query_vars Array of report query arguments. See {@see Report_Query::__construct()} for a list of
	 *                          supported arguments.
	 */
	protected function parse_query_vars( array $query_vars ) {
		$this->query_vars = wp_parse_args( $query_vars, $this->query_var_defaults );

		if ( ! $this->query_vars['number'] || 'count' === $this->query_vars['fields'] ) {
			$this->query_vars['no_found_rows'] = true;
		}
	}
}
