<?php
/**
 * Class Google\WP_Reporting_API\Report_Log_Query
 *
 * @package   Google\WP_Reporting_API
 * @copyright 2019 Google LLC
 * @license   GNU General Public License, version 2
 * @link      https://wordpress.org/plugins/reporting-api/
 */

namespace Google\WP_Reporting_API;

use WP_Date_Query;

/**
 * Class for querying report logs.
 *
 * @since 0.1.0
 */
class Report_Log_Query {

	/**
	 * Parent report logs controller instance.
	 *
	 * @since 0.1.0
	 * @var Report_Logs
	 */
	protected $report_logs;

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
	 * List of report logs located by the query.
	 *
	 * @since 0.1.0
	 * @var array
	 */
	public $results = array();

	/**
	 * The amount of found report logs for the current query.
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
	 * @param Report_Logs $report_logs Parent report logs controller instance.
	 * @param array       $query_vars  {
	 *     Array of report log query arguments.
	 *
	 *     @type array        $include       Array of report log IDs to include. Default empty.
	 *     @type array        $exclude       Array of report log IDs to exclude. Default empty.
	 *     @type int          $number        Maximum number of report logs to retrieve. Default 10.
	 *     @type int          $offset        Number of report logs to offset the query. Used to build LIMIT clause.
	 *                                       Default 0.
	 *     @type bool         $no_found_rows Whether to disable the `SQL_CALC_FOUND_ROWS` query. Default false.
	 *     @type string|array $orderby       Report log orderby field or array of orderby fields. Accepts 'id', 'url',
	 *                                       'triggered', 'reported', 'include'. Default 'reported'.
	 *     @type string       $order         How to order retrieved report logs. Accepts 'ASC', 'DESC'. Default 'DESC'.
	 *     @type int          $report_id     Limit results to those affiliated with a given report ID. Default 0.
	 *     @type string|array $url           Limit results to those that occurred with a given URL. Default empty
	 *                                       string.
	 *     @type string|array $user_agent    Limit results to those that occurred with a given user agent. Default
	 *                                       empty string.
	 *     @type array        $date_query    Date query clauses to limit report logs by. Valid columns are 'triggered'
	 *                                       and 'reported', with the latter being the default column. See
	 *                                       WP_Date_Query. Default null.
	 *     @type string       $search        Search term(s) to retrieve matching report logs for. Default empty.
	 *     @type string       $fields        Report log fields to return. Accepts 'ids' (returns an array of report log
	 *                                       IDs), 'count' (returns a report log count) or 'all' (returns an array of
	 *                                       complete report log objects).
	 *     @type bool         $update_cache  Whether to prime the cache for found report logs. Default true.
	 * }
	 */
	public function __construct( Report_Logs $report_logs, array $query_vars ) {
		$this->report_logs        = $report_logs;
		$this->query_var_defaults = array(
			'include'       => array(),
			'exclude'       => array(),
			'number'        => 10,
			'offset'        => 0,
			'no_found_rows' => false,
			'orderby'       => 'reported',
			'order'         => 'DESC',
			'report_id'     => 0,
			'url'           => '',
			'user_agent'    => '',
			'date_query'    => null,
			'search'        => '',
			'fields'        => 'all',
			'update_cache'  => true,
		);

		$this->parse_query_vars( $query_vars );
	}

	/**
	 * Gets results for the query.
	 *
	 * This method will first check for cached query results, and only perform actual SQL queries if necessary, by
	 * calling {@see Report_Log_Query::query_results()}. The method will then take care of parsing the reponse into the
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
		$last_changed = wp_cache_get_last_changed( Report_Logs::CACHE_GROUP );

		$cache_key   = "query:$key:$last_changed";
		$cache_value = wp_cache_get( $cache_key, Report_Logs::CACHE_GROUP );

		if ( false === $cache_value ) {
			$result_ids          = $this->query_results();
			$this->found_results = $this->get_found_results( $result_ids );

			$cache_value = array(
				'ids'   => $result_ids,
				'found' => $this->found_results,
			);
			wp_cache_add( $cache_key, $cache_value, Report_Logs::CACHE_GROUP );
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
		$this->results = array_filter( array_map( array( $this->report_logs, 'get' ), $result_ids ) );

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

		$table_name = $this->report_logs->get_db_table_name();

		$fields     = $this->parse_fields();
		$distinct   = '';
		$limits     = $this->parse_limits();
		$found_rows = ! $this->query_vars['no_found_rows'] ? 'SQL_CALC_FOUND_ROWS' : '';

		// Get WHERE clauses.
		$this->sql_clauses['where'] = $this->parse_where();

		// Get ORDER BY clause.
		$orderby = $this->parse_orderby();

		$where = implode( ' AND ', $this->sql_clauses['where'] );

		$join    = '';
		$groupby = '';

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
		$this->sql_clauses['from']    = "FROM {$table_name} $join";
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
	 * Returns the SQL field name to query.
	 *
	 * @since 0.1.0
	 *
	 * @return string SQL for the fields to SELECT.
	 */
	protected function parse_fields() {
		$table_name = $this->report_logs->get_db_table_name();

		if ( 'count' === $this->query_vars['fields'] ) {
			return "COUNT({$table_name}.id)";
		}

		return "{$table_name}.id";
	}

	/**
	 * Parses the $number and $offset query variables into an SQL LIMIT clause.
	 *
	 * @since 0.1.0
	 *
	 * @return string SQL clause including LIMIT, or empty string if not relevant.
	 */
	protected function parse_limits() {
		$number = $this->query_vars['number'];
		$offset = $this->query_vars['offset'];

		if ( $number ) {
			if ( $offset ) {
				return "LIMIT $offset,$number";
			}

			return "LIMIT $number";
		}

		return '';
	}

	/**
	 * Parses query variables into an array of WHERE clauses and JOIN conditions.
	 *
	 * Table identifiers for the report logs table should use the 'l' alias.
	 *
	 * @since 0.1.0
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 *
	 * @return array List of WHERE clauses.
	 */
	protected function parse_where() {
		global $wpdb;

		$table_name = $this->report_logs->get_db_table_name();

		$where_clauses = array();

		if ( ! empty( $this->query_vars['include'] ) ) {
			$where_clauses['include'] = "{$table_name}.id IN ( " . implode( ',', wp_parse_id_list( $this->query_vars['include'] ) ) . ' )';
		}

		if ( ! empty( $this->query_vars['exclude'] ) ) {
			$where_clauses['exclude'] = "{$table_name}.id NOT IN ( " . implode( ',', wp_parse_id_list( $this->query_vars['exclude'] ) ) . ' )';
		}

		if ( ! empty( $this->query_vars['report_id'] ) ) {
			$where_clauses['report_id'] = $wpdb->prepare( "{$table_name}.report_id = %d", (int) $this->query_vars['report_id'] ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		if ( ! empty( $this->query_vars['url'] ) ) {
			if ( is_array( $this->query_vars['url'] ) ) {
				$where_clauses['url'] = "{$table_name}.url IN ( '" . implode( "', '", $wpdb->_escape( $this->query_vars['url'] ) ) . "' )";
			} else {
				$where_clauses['url'] = $wpdb->prepare( "{$table_name}.url = %s", $this->query_vars['url'] ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			}
		}

		if ( ! empty( $this->query_vars['user_agent'] ) ) {
			if ( is_array( $this->query_vars['user_agent'] ) ) {
				$where_clauses['user_agent'] = "{$table_name}.user_agent IN ( '" . implode( "', '", $wpdb->_escape( $this->query_vars['user_agent'] ) ) . "' )";
			} else {
				$where_clauses['user_agent'] = $wpdb->prepare( "{$table_name}.user_agent = %s", $this->query_vars['user_agent'] ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			}
		}

		if ( ! empty( $this->query_vars['search'] ) ) {
			$where_clauses['search'] = $this->get_search_sql( $this->query_vars['search'] );
		}

		if ( ! empty( $this->query_vars['date_query'] ) && is_array( $this->query_vars['date_query'] ) ) {
			$where_clauses['date_query'] = $this->get_date_query_sql( $this->query_vars['date_query'] );
		}

		return $where_clauses;
	}

	/**
	 * Parses the $orderby and $order query variables into an SQL orderby clause.
	 *
	 * Table identifiers for the report logs table should use the 'l' alias.
	 *
	 * @since 0.1.0
	 *
	 * @return string SQL to be appended to an `ORDER BY` clause.
	 */
	protected function parse_orderby() {
		$orderby    = $this->query_vars['orderby'];
		$order      = $this->query_vars['order'];
		$table_name = $this->report_logs->get_db_table_name();

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
			'id'        => "{$table_name}.id",
			'url'       => "{$table_name}.url",
			'triggered' => "{$table_name}.triggered",
			'reported'  => "{$table_name}.reported",
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
	 * Parses a given search string into an SQL clause for text search.
	 *
	 * @since 0.1.0
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 *
	 * @param string $search Search string.
	 * @return string SQL to be used as a WHERE clause.
	 */
	protected function get_search_sql( $search ) {
		global $wpdb;

		$table_name = $this->report_logs->get_db_table_name();

		if ( false !== strpos( $search, '*' ) ) {
			$like = '%' . implode( '%', array_map( array( $wpdb, 'esc_like' ), explode( '*', $search ) ) ) . '%';
		} else {
			$like = '%' . $wpdb->esc_like( $search ) . '%';
		}

		return $wpdb->prepare( "({$table_name}.url LIKE %s OR {$table_name}.user_agent LIKE %s)", $like, $like ); // phpcs:ignore WordPress.DB.PreparedSQL
	}

	/**
	 * Parses a given date query array into an SQL clause.
	 *
	 * @since 0.1.0
	 *
	 * @param array $date_query Date query array.
	 * @return string SQL to be used as a JOIN clause.
	 */
	protected function get_date_query_sql( array $date_query ) {
		$table_name = $this->report_logs->get_db_table_name();

		$date_query_filter = function( array $valid_columns ) {
			$valid_columns[] = 'triggered';
			$valid_columns[] = 'reported';
			return $valid_columns;
		};

		add_filter( 'date_query_valid_columns', $date_query_filter );

		$this->date_query         = new WP_Date_Query( $date_query, 'reported' );
		$this->date_query->column = 'reported';

		$sql = $this->date_query->get_sql();
		$sql = str_replace(
			array( 'triggered', 'reported' ),
			array( "{$table_name}.triggered", "{$table_name}.reported" ),
			$sql
		);
		$sql = preg_replace( '/^\s*AND\s*/', '', $sql );

		remove_filter( 'date_query_valid_columns', $date_query_filter );

		return $sql;
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
		global $wpdb;

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

		$non_cached_ids = _get_non_cached_ids( $result_ids, Report_Logs::CACHE_GROUP );
		if ( empty( $non_cached_ids ) ) {
			return;
		}

		$fresh_results = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			sprintf(
				// phpcs:ignore WordPress.DB.PreparedSQL
				"SELECT * FROM {$this->report_logs->get_db_table_name()} WHERE id IN (%s)",
				join( ',', array_map( 'intval', $non_cached_ids ) ) // phpcs:ignore WordPress.DB.PreparedSQL
			),
			ARRAY_A
		);
		if ( ! $fresh_results ) {
			return;
		}

		foreach ( $fresh_results as $result ) {
			wp_cache_add( $result['id'], $result, Report_Logs::CACHE_GROUP );
		}
	}

	/**
	 * Parses query vars provided.
	 *
	 * @since 0.1.0
	 *
	 * @param array $query_vars Array of report query arguments. See {@see Report_Log_Query::__construct()} for a list
	 *                          of supported arguments.
	 */
	protected function parse_query_vars( array $query_vars ) {
		$this->query_vars = wp_parse_args( $query_vars, $this->query_var_defaults );

		if ( ! $this->query_vars['number'] || 'count' === $this->query_vars['fields'] ) {
			$this->query_vars['no_found_rows'] = true;
		}
	}
}
