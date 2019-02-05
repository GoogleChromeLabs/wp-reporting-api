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

		$table_name = $this->reports->get_db_table_name();

		$fields = "{$table_name}.id";
		if ( 'count' === $this->query_vars['fields'] ) {
			$fields = "COUNT({$fields})";
		}

		$distinct = '';

		$number = $this->query_vars['number'];
		$offset = $this->query_vars['offset'];

		if ( $number ) {
			if ( $offset ) {
				$limits = "LIMIT $offset,$number";
			} else {
				$limits = "LIMIT $number";
			}
		}

		$found_rows = '';
		if ( ! $this->query_vars['no_found_rows'] ) {
			$found_rows = 'SQL_CALC_FOUND_ROWS';
		}

		$join_report_logs = false;

		// TODO: Parse where args.
		$this->sql_clauses['where'] = array();

		// TODO: Parse orderby.
		$orderby = $this->parse_orderby( $this->query_vars['orderby'], $this->query_vars['order'] );

		$join    = '';
		$groupby = '';
		if ( $join_report_logs ) {
			$report_logs_table_name = Plugin::instance()->report_logs()->get_db_table_name();

			$join    = "INNER JOIN {$report_logs_table_name} AS l ON ({$table_name}.id = l.report_id)";
			$groupby = "{$table_name}.id";
		}

		$where = implode( ' AND ', $this->sql_clauses['where'] );

		$pieces = array( 'fields', 'join', 'where', 'orderby', 'limits', 'groupby' );

		$clauses = compact( $pieces );

		$fields  = isset( $clauses['fields'] ) ? $clauses['fields'] : '';
		$join    = isset( $clauses['join'] ) ? $clauses['join'] : '';
		$where   = isset( $clauses['where'] ) ? $clauses['where'] : '';
		$orderby = isset( $clauses['orderby'] ) ? $clauses['orderby'] : '';
		$limits  = isset( $clauses['limits'] ) ? $clauses['limits'] : '';
		$groupby = isset( $clauses['groupby'] ) ? $clauses['groupby'] : '';

		if ( $where ) {
			$where = "WHERE $where";
		}

		if ( $orderby ) {
			$orderby = "ORDER BY $orderby";
		}

		if ( $groupby ) {
			$groupby = "GROUP BY $groupby";
		}

		$this->sql_clauses['select']  = "SELECT $distinct $found_rows $fields";
		$this->sql_clauses['from']    = "FROM %{$table_name}% $join";
		$this->sql_clauses['groupby'] = $groupby;
		$this->sql_clauses['orderby'] = $orderby;
		$this->sql_clauses['limits']  = $limits;

		$this->request = "{$this->sql_clauses['select']} {$this->sql_clauses['from']} {$where} {$this->sql_clauses['groupby']} {$this->sql_clauses['orderby']} {$this->sql_clauses['limits']}";

		if ( 'count' === $this->query_vars['fields'] ) {
			return (int) $wpdb->get_var( $this->request ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
		}

		return (array) $wpdb->get_col( $this->request ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
	}

	/**
	 * Parses a given search string into an SQL clause for text search.
	 *
	 * @since 0.1.0
	 *
	 * @param string $search Search string.
	 * @return string SQL to be used as a where clause.
	 */
	protected function get_search_sql( $search ) {
		global $wpdb;

		$table_name = $this->reports->get_db_table_name();

		if ( false !== strpos( $search, '*' ) ) {
			$like = '%' . implode( '%', array_map( array( $wpdb, 'esc_like' ), explode( '*', $search ) ) ) . '%';
		} else {
			$like = '%' . $wpdb->esc_like( $search ) . '%';
		}

		return $wpdb->prepare( "{$table_name}.body LIKE %s", $like ); // phpcs:ignore WordPress.DB.PreparedSQL
	}

	/**
	 * Parses the $orderby and $order query variables into an SQL orderby clause.
	 *
	 * @since 0.1.0
	 *
	 * @param array|string $orderby The orderby query variable.
	 * @param string       $order   The order query variable.
	 * @return string SQL to be appended to an `ORDER BY` clause.
	 */
	protected function parse_orderby( $orderby, $order ) {
		$table_name = $this->reports->get_db_table_name();

		if ( in_array( $orderby, array( 'none', array(), false ), true ) ) {
			return '';
		}

		if ( empty( $orderby ) ) {
			$order = 'ASC' === strtoupper( $order ) ? 'ASC' : 'DESC';
			return "{$table_name}.id {$order}";
		}

		if ( ! is_array( $orderby ) ) {
			$orderby = array( $orderby => $order );
		}

		$valid_orderby = array(
			'type'            => "{$table_name}.type",
			'first_triggered' => 'MIN(l.triggered)',
			'first_reported'  => 'MIN(l.reported)',
			'last_triggered'  => 'MAX(l.triggered)',
			'last_reported'   => 'MAX(l.reported)',
		);

		$orderby_array = array();
		foreach ( $orderby as $_orderby => $_order ) {
			if ( 'include' === $_orderby ) {
				$include_ids     = implode( ',', array_map( 'absint', $this->query_vars['include'] ) );
				$orderby_array[] = "FIELD( {$table_name}.id, $include_ids )";
				continue;
			}

			if ( ! isset( $valid_orderby[ $_orderby ] ) ) {
				continue;
			}

			$_order = 'ASC' === strtoupper( $_order ) ? 'ASC' : 'DESC';

			$orderby_array[] = $valid_orderby[ $_orderby ] . ' ' . $_order;
		}

		return implode( ', ', $orderby_array );
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
