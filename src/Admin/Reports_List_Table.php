<?php
/**
 * Class Google\WP_Reporting_API\Admin\Reports_List_Table
 *
 * @package Google\WP_Reporting_API
 * @license GNU General Public License, version 2
 * @link    https://wordpress.org/plugins/reporting-api/
 */

namespace Google\WP_Reporting_API\Admin;

use Google\WP_Reporting_API\Reports;
use Google\WP_Reporting_API\Report;
use Google\WP_Reporting_API\Report_Logs;
use Google\WP_Reporting_API\Report_Log;
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
	 * Constructor.
	 *
	 * @since 0.1.0
	 *
	 * @param Reports     $reports     The reports controller instance.
	 * @param Report_Logs $report_logs The report logs controller instance.
	 */
	public function __construct( Reports $reports, Report_Logs $report_logs ) {
		$this->reports     = $reports;
		$this->report_logs = $report_logs;

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
		return current_user_can( Reporting_Screen::CAPABILITY );
	}

	/**
	 * Prepares the list of items for displaying.
	 *
	 * @since 0.1.0
	 */
	public function prepare_items() {
		// TODO.
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
	 * Displays the 'body' column for a given report.
	 *
	 * @since 0.1.0
	 *
	 * @param Report $report Report to display column for.
	 */
	public function column_body( Report $report ) {
		// TODO.
	}

	/**
	 * Displays the 'type' column for a given report.
	 *
	 * @since 0.1.0
	 *
	 * @param Report $report Report to display column for.
	 */
	public function column_type( Report $report ) {
		// TODO.
	}

	/**
	 * Displays the 'url' column for a given report.
	 *
	 * @since 0.1.0
	 *
	 * @param Report $report Report to display column for.
	 */
	public function column_url( Report $report ) {
		// TODO.
	}

	/**
	 * Displays the 'user_agent' column for a given report.
	 *
	 * @since 0.1.0
	 *
	 * @param Report $report Report to display column for.
	 */
	public function column_user_agent( Report $report ) {
		// TODO.
	}

	/**
	 * Displays the 'count' column for a given report.
	 *
	 * @since 0.1.0
	 *
	 * @param Report $report Report to display column for.
	 */
	public function column_count( Report $report ) {
		// TODO.
	}

	/**
	 * Displays the 'reported' column for a given report.
	 *
	 * @since 0.1.0
	 *
	 * @param Report $report Report to display column for.
	 */
	public function column_reported( Report $report ) {
		// TODO.
	}

	/**
	 * Returns the list of columns to display.
	 *
	 * @since 0.1.0
	 *
	 * @return array Columns as $column_slug => $column_title pairs.
	 */
	public function get_columns() {
		$type = filter_input( INPUT_GET, 'type' );

		$columns = array();

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
			'count'    => array( 'log_count', true ),
			'reported' => array( 'reported', true ),
		);
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

		$type = filter_input( INPUT_GET, 'type' );

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
				esc_attr( $arc_row->year . $month ),
				/* translators: 1: month name, 2: 4-digit year */
				sprintf( __( '%1$s %2$d', 'reporting-api' ), $wp_locale->get_month( $month ), $year ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			);
		}
		?>
		</select>
		<?php
	}
}
