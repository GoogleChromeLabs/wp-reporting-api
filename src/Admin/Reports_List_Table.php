<?php
/**
 * Class Google\WP_Reporting_API\Admin\Reports_List_Table
 *
 * @package   Google\WP_Reporting_API
 * @copyright 2019 Google LLC
 * @license   GNU General Public License, version 2
 * @link      https://wordpress.org/plugins/reporting-api/
 */

namespace Google\WP_Reporting_API\Admin;

use Google\WP_Reporting_API\Reports;
use Google\WP_Reporting_API\Report;
use Google\WP_Reporting_API\Report_Logs;
use Google\WP_Reporting_API\Report_Log;
use Google\WP_Reporting_API\Report_Types;
use WP_List_Table;

/**
 * Class for displaying a list of reports in an HTML admin list table.
 *
 * @since 0.1.0
 */
class Reports_List_Table extends WP_List_Table {

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
	 * Constructor.
	 *
	 * @since 0.1.0
	 *
	 * @param Reports      $reports      The reports controller instance.
	 * @param Report_Logs  $report_logs  The report logs controller instance.
	 * @param Report_Types $report_types The report types controller instance.
	 */
	public function __construct( Reports $reports, Report_Logs $report_logs, Report_Types $report_types ) {
		$this->reports      = $reports;
		$this->report_logs  = $report_logs;
		$this->report_types = $report_types;

		parent::__construct(
			array(
				'plural'   => 'reports',
				'singular' => 'report',
				'screen'   => get_current_screen(),
			)
		);
	}

	/**
	 * Checks the current user's permissions.
	 *
	 * @since 0.1.0
	 */
	public function ajax_user_can() {
		return current_user_can( Reports_Screen::CAPABILITY );
	}

	/**
	 * Prepares the list of items for displaying.
	 *
	 * @since 0.1.0
	 *
	 * @global string $mode List table view mode.
	 */
	public function prepare_items() {
		global $mode;

		$mode = filter_input( INPUT_GET, 'mode', FILTER_SANITIZE_STRING ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.OverrideProhibited
		if ( ! empty( $mode ) && in_array( $mode, array( 'excerpt', 'list' ), true ) ) {
			set_user_setting( "{$this->screen->id}_list_mode", $mode );
		} else {
			$mode = get_user_setting( "{$this->screen->id}_list_mode", 'list' ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.OverrideProhibited
		}

		$page     = $this->get_pagenum();
		$per_page = $this->get_items_per_page( "{$this->screen->id}_per_page" );

		$args                  = $this->get_current_filter_args();
		$args['number']        = $per_page;
		$args['offset']        = ( $page - 1 ) * $per_page;
		$args['no_found_rows'] = false;
		if ( ! empty( $args['m'] ) ) {
			if ( strlen( $args['m'] ) === 6 ) {
				$args['date_query'] = array(
					'column'   => 'reported',
					'relation' => 'AND',
					array(
						'year'  => substr( $args['m'], 0, 4 ),
						'month' => substr( $args['m'], 4, 2 ),
					),
				);
			}
			unset( $args['m'] );
		}

		$query = $this->reports->get_query( $args );

		$this->items = $query->get_results();
		$this->set_pagination_args(
			array(
				'total_items' => $query->found_results,
				'per_page'    => $per_page,
			)
		);
	}

	/**
	 * Displays a message when there are no items.
	 *
	 * @since 0.1.0
	 */
	public function no_items() {
		esc_html_e( 'No reports found.', 'reporting-api' );
	}

	/**
	 * Displays the 'id' column for a given report.
	 *
	 * @since 0.1.0
	 *
	 * @param Report $report Report to display column for.
	 */
	public function column_id( Report $report ) {
		echo esc_html( $report->id );
	}

	/**
	 * Displays the 'body' column for a given report.
	 *
	 * @since 0.1.0
	 *
	 * @global string $mode List table view mode.
	 *
	 * @param Report $report Report to display column for.
	 */
	public function column_body( Report $report ) {
		global $mode;

		if ( 'excerpt' === $mode ) {
			$json = wp_json_encode( $report->body, JSON_PRETTY_PRINT );
			?>
			<textarea class="widefat code" rows="5" readonly="readonly" disabled="disabled"><?php echo esc_textarea( $json ); ?></textarea>
			<?php
			return;
		}

		$json = wp_json_encode( $report->body );
		if ( strlen( $json ) > 40 ) {
			$json = substr( $json, 0, 39 ) . '&hellip;';
		}
		?>
		<code><?php echo esc_html( $json ); ?></code>
		<?php
	}

	/**
	 * Displays the 'type' column for a given report.
	 *
	 * @since 0.1.0
	 *
	 * @param Report $report Report to display column for.
	 */
	public function column_type( Report $report ) {
		$types = $this->report_types->get_all();
		$type  = $report->type;

		if ( ! isset( $types[ $type ] ) ) {
			return;
		}

		$filter_args         = $this->get_current_filter_args();
		$filter_args['type'] = $type;

		$filter_url = add_query_arg( $filter_args );

		?>
		<a href="<?php echo esc_url( $filter_url ); ?>"><?php echo esc_html( $types[ $type ]->title ); ?></a>
		<?php
	}

	/**
	 * Displays the 'url' column for a given report.
	 *
	 * @since 0.1.0
	 *
	 * @param Report $report Report to display column for.
	 */
	public function column_url( Report $report ) {
		$data = $report->query_log_data();

		if ( empty( $data['urls'] ) ) {
			return;
		}

		$limit = 5;

		$urls       = $data['urls'];
		$more_count = 0;
		if ( count( $urls ) > $limit ) {
			$more_count = count( $urls ) - $limit;
			$urls       = array_slice( $urls, 0, $limit );
		}

		$filter_args = $this->get_current_filter_args();

		$home_url = home_url();

		$urls = array_map(
			function( $url ) use ( $filter_args, $home_url ) {
				$filter_args['url'] = $url;
				$display_url        = $url;
				if ( 0 === strpos( $display_url, $home_url ) ) {
					$display_url = substr( $display_url, strlen( $home_url ) );
					if ( empty( $display_url ) ) {
						$display_url = '/';
					}
				}
				return '<a href="' . esc_url( add_query_arg( $filter_args ) ) . '"><code>' . esc_html( $display_url ) . '</code></a>';
			},
			$urls
		);

		echo implode( _x( ', ', 'separator', 'reporting-api' ), $urls ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		if ( $more_count > 0 ) {
			/* translators: %d: URL count */
			echo ' ' . esc_html( sprintf( _nx( '[and %d more]', '[and %d more]', $more_count, 'URL list', 'reporting-api' ), $more_count ) );
		}
	}

	/**
	 * Displays the 'user_agent' column for a given report.
	 *
	 * @since 0.1.0
	 *
	 * @param Report $report Report to display column for.
	 */
	public function column_user_agent( Report $report ) {
		$data = $report->query_log_data();

		$data = $report->query_log_data();

		if ( empty( $data['user_agents'] ) ) {
			return;
		}

		$limit = 2;

		$user_agents = $data['user_agents'];
		$more_count  = 0;
		if ( count( $user_agents ) > $limit ) {
			$more_count  = count( $user_agents ) - $limit;
			$user_agents = array_slice( $user_agents, 0, $limit );
		}

		$user_agents = array_map(
			function( $user_agent ) {
				return '<code>' . esc_html( $user_agent ) . '</code>';
			},
			$user_agents
		);

		echo implode( _x( ', ', 'separator', 'reporting-api' ), $user_agents ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		if ( $more_count > 0 ) {
			/* translators: %d: user agent count */
			echo ' ' . esc_html( sprintf( _nx( '[and %d more]', '[and %d more]', $more_count, 'user agent list', 'reporting-api' ), $more_count ) );
		}
	}

	/**
	 * Displays the 'count' column for a given report.
	 *
	 * @since 0.1.0
	 *
	 * @param Report $report Report to display column for.
	 */
	public function column_count( Report $report ) {
		$data = $report->query_log_data();

		$count = isset( $data['count'] ) ? $data['count'] : 0;
		echo esc_html( $count );
	}

	/**
	 * Displays the 'reported' column for a given report.
	 *
	 * @since 0.1.0
	 *
	 * @global string $mode List table view mode.
	 *
	 * @param Report $report Report to display column for.
	 */
	public function column_reported( Report $report ) {
		global $mode;

		$data = $report->query_log_data();

		if ( empty( $data['last_reported'] ) || '0000-00-00 00:00:00' === $data['last_reported'] ) {
			return;
		}

		$datetime = get_date_from_gmt( $data['last_reported'] );

		if ( 'excerpt' === $mode ) {
			/* translators: 1: date format, 2: time format */
			$format = sprintf( _x( '%1$s %2$s', 'date and time format', 'reporting-api' ), get_option( 'date_format' ), get_option( 'time_format' ) );
		} else {
			$format = get_option( 'date_format' );
		}

		echo esc_html( mysql2date( $format, $datetime ) );
	}

	/**
	 * Returns the list of columns to display.
	 *
	 * @since 0.1.0
	 *
	 * @return array Columns as $column_slug => $column_title pairs.
	 */
	public function get_columns() {
		$type = filter_input( INPUT_GET, 'type', FILTER_SANITIZE_STRING );

		$columns = array();

		$columns['id']   = _x( 'ID', 'column name', 'reporting-api' );
		$columns['body'] = _x( 'Details', 'column name', 'reporting-api' );

		// Only include type column if not already filtered by it.
		if ( empty( $type ) ) {
			$columns['type'] = _x( 'Type', 'column name', 'reporting-api' );
		}

		$columns['url']        = _x( 'URLs', 'column name', 'reporting-api' );
		$columns['user_agent'] = _x( 'User agents', 'column name', 'reporting-api' );
		$columns['count']      = _x( 'Report Count', 'column name', 'reporting-api' );
		$columns['reported']   = _x( 'Last reported', 'column name', 'reporting-api' );

		return $columns;
	}

	/**
	 * Returns the list of sortable columns.
	 *
	 * @since 0.1.0
	 *
	 * @return array Columns as $column_slug => $orderby_value pairs.
	 */
	protected function get_sortable_columns() {
		return array(
			'type'     => 'type',
			'reported' => array( 'reported', true ),
		);
	}

	/**
	 * Gets the list of CSS classes for the table tag.
	 *
	 * @since 0.1.0
	 *
	 * @return array List of CSS classes for the table tag.
	 */
	protected function get_table_classes() {
		$classes = parent::get_table_classes();

		$key = array_search( 'fixed', $classes, true );
		if ( false !== $key ) {
			unset( $classes[ $key ] );
			$classes = array_values( $classes );
		}

		return $classes;
	}

	/**
	 * Displays pagination UI.
	 *
	 * @since 0.1.0
	 *
	 * @global string $mode List table view mode.
	 *
	 * @param string $which Either 'top' or 'bottom'.
	 */
	protected function pagination( $which ) {
		global $mode;

		parent::pagination( $which );

		if ( 'top' === $which ) {
			$this->view_switcher( $mode );
		}
	}

	/**
	 * Displays extra controls between bulk actions and pagination.
	 *
	 * @since 0.1.0
	 *
	 * @param string $which Either 'top' or 'bottom'.
	 */
	protected function extra_tablenav( $which ) {
		if ( 'top' !== $which || ! $this->has_items() ) {
			return;
		}

		$type = filter_input( INPUT_GET, 'type', FILTER_SANITIZE_STRING );

		?>
		<div class="alignleft actions">
			<?php
			$this->months_dropdown( $type );
			submit_button( __( 'Filter', 'reporting-api' ), '', 'filter_action', false );
			?>
		</div>
		<?php
	}

	/**
	 * Displays a monthly dropdown for filtering items.
	 *
	 * @since 0.1.0
	 *
	 * @global wpdb      $wpdb      WordPress database abstraction object.
	 * @global WP_Locale $wp_locale WordPress locale object.
	 *
	 * @param string $type The currently filtered report type.
	 */
	protected function months_dropdown( $type ) {
		global $wpdb, $wp_locale;

		$last_changed = wp_cache_get_last_changed( Reports::CACHE_GROUP ) . ':' . wp_cache_get_last_changed( Report_Logs::CACHE_GROUP );
		$cache_key    = "months:$type:$last_changed";
		$months       = wp_cache_get( $cache_key, Reports::CACHE_GROUP );
		if ( false === $months ) {
			$reports_table_name     = $this->reports->get_db_table_name();
			$report_logs_table_name = $this->report_logs->get_db_table_name();

			$extra = '';
			if ( ! empty( $type ) ) {
				$extra = $wpdb->prepare( "INNER JOIN {$reports_table_name} AS r ON ({$report_logs_table_name}.report_id = r.id AND r.type = %s)", $type ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			}

			$request = "SELECT DISTINCT YEAR( triggered ) AS year, MONTH( triggered ) AS month FROM $report_logs_table_name $extra ORDER BY triggered DESC";
			$months  = $wpdb->get_results( $request ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared

			wp_cache_add( $cache_key, $months, Reports::CACHE_GROUP );
		}

		$month_count = count( $months );

		if ( ! $month_count || ( 1 === $month_count && 0 === $months[0]->month ) ) {
			return;
		}

		$m = filter_input( INPUT_GET, 'm', FILTER_VALIDATE_INT );
		if ( ! $m ) {
			$m = 0;
		}
		?>
		<label for="filter-by-date" class="screen-reader-text"><?php esc_html_e( 'Filter by date', 'reporting-api' ); ?></label>
		<select name="m" id="filter-by-date">
			<option<?php selected( $m, 0 ); ?> value="0"><?php esc_html_e( 'All dates', 'reporting-api' ); ?></option>
		<?php
		foreach ( $months as $arc_row ) {
			if ( 0 === (int) $arc_row->year ) {
				continue;
			}

			$month = zeroise( $arc_row->month, 2 );
			$year  = $arc_row->year;

			printf(
				"<option %s value='%s'>%s</option>\n",
				selected( $m, $year . $month, false ),
				esc_attr( $year . $month ),
				/* translators: 1: month name, 2: 4-digit year */
				sprintf( __( '%1$s %2$d', 'reporting-api' ), $wp_locale->get_month( $month ), $year ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			);
		}
		?>
		</select>
		<?php
	}

	/**
	 * Gets the list of currently applied filter arguments.
	 *
	 * @since 0.1.0
	 *
	 * @return array Associative array of reports list table arguments.
	 */
	protected function get_current_filter_args() {
		return array_filter(
			array(
				'm'       => (string) filter_input( INPUT_GET, 'm', FILTER_VALIDATE_INT ),
				'orderby' => filter_input( INPUT_GET, 'orderby', FILTER_SANITIZE_STRING ),
				'order'   => filter_input( INPUT_GET, 'order', FILTER_SANITIZE_STRING ),
				'type'    => filter_input( INPUT_GET, 'type', FILTER_SANITIZE_STRING ),
				'url'     => filter_input( INPUT_GET, 'url', FILTER_SANITIZE_STRING ),
			)
		);
	}
}
