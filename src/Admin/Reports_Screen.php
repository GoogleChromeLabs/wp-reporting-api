<?php
/**
 * Class Google\WP_Reporting_API\Admin\Reports_Screen
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

/**
 * Class representing the admin screen that lists reports.
 *
 * @since 0.1.0
 */
class Reports_Screen {

	/**
	 * The admin page slug.
	 *
	 * @since 0.1.0
	 * @var string
	 */
	const SLUG = 'reporting_api_reports';

	/**
	 * The admin page parent slug.
	 *
	 * @since 0.1.0
	 * @var string
	 */
	const PARENT_SLUG = 'tools.php';

	/**
	 * The capability required to access the admin screen.
	 *
	 * @since 0.1.0
	 * @var string
	 */
	const CAPABILITY = 'manage_reporting_api';

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
	 * The list table for displaying reports.
	 *
	 * @since 0.1.0
	 * @var Reports_List_Table
	 */
	protected $list_table;

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
	}

	/**
	 * Registers the menu item for the admin screen.
	 *
	 * @since 0.1.0
	 */
	public function register_menu() {
		$hook_suffix = add_submenu_page(
			self::PARENT_SLUG,
			__( 'Reporting API: Reports', 'reporting-api' ),
			__( 'Reporting API', 'reporting-api' ),
			self::CAPABILITY,
			self::SLUG,
			array( $this, 'render' )
		);
		add_action(
			"load-{$hook_suffix}",
			function() {
				$this->prepare_list();
			}
		);
	}

	/**
	 * Renders the admin screen.
	 *
	 * @since 0.1.0
	 */
	public function render() {
		$type   = filter_input( INPUT_GET, 'type', FILTER_SANITIZE_STRING );
		$search = filter_input( INPUT_GET, 's', FILTER_SANITIZE_STRING );

		$title = __( 'Reporting API: Reports', 'reporting-api' );
		if ( ! empty( $type ) ) {
			$types = $this->report_types->get_all();
			if ( isset( $types[ $type ] ) ) {
				/* translators: %s: report type label */
				$title = sprintf( __( 'Reporting API: %s Reports', 'reporting-api' ), $types[ $type ]->title );
			}
		}

		?>
		<style type="text/css">
			.external-link > .dashicons {
				font-size: 16px;
				text-decoration: none;
			}

			.external-link:hover > .dashicons,
			.external-link:focus > .dashicons {
				text-decoration: none;
			}

			@media (min-width: 783px) {
				.reports .column-body {
					width: 40%;
				}
			}
		</style>
		<div class="wrap">
			<h1 class="wp-heading-inline">
				<?php echo esc_html( $title ); ?>
			</h1>
			<?php
			if ( ! empty( $type ) ) {
				echo ' <a href="' . esc_url( remove_query_arg( 'type' ) ) . '" class="page-title-action">' . esc_html__( 'View All Reports', 'reporting-api' ) . '</a>';
			}
			if ( ! empty( $search ) ) {
				/* translators: %s: search keywords */
				printf( ' <span class="subtitle">' . __( 'Search results for &#8220;%s&#8221;', 'reporting-api' ) . '</span>', esc_html( $search ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
			?>
			<hr class="wp-header-end">

			<p>
				<?php esc_html_e( 'Reporting API allows you to receive browser-generated reports about client-side errors and policy violations on your site.', 'reporting-api' ); ?>
				<?php
				printf(
					'<a class="external-link" href="%1$s" target="_blank">%2$s<span class="screen-reader-text"> %3$s</span><span aria-hidden="true" class="dashicons dashicons-external"></span></a>',
					esc_url( _x( 'https://developers.google.com/web/updates/2018/09/reportingapi', 'learn more link', 'reporting-api' ) ),
					esc_html__( 'Learn more about Reporting API', 'reporting-api' ),
					/* translators: accessibility text */
					esc_html__( '(opens in a new tab)', 'reporting-api' )
				);
				?>
			</p>

			<?php $this->list_table->views(); ?>

			<form id="reports-filter" method="get">
				<?php $this->list_table->search_box( __( 'Search reports', 'reporting-api' ), 'search-reports' ); ?>

				<input type="hidden" name="page" value="<?php echo esc_attr( self::SLUG ); ?>" />
				<?php
				if ( ! empty( $type ) ) {
					?>
					<input type="hidden" name="type" value="<?php echo esc_attr( $type ); ?>" />
					<?php
				}
				?>

				<?php $this->list_table->display(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Prepares the list table for displaying reports and runs the query.
	 *
	 * @since 0.1.0
	 */
	protected function prepare_list() {
		$screen = get_current_screen();

		$screen->add_option(
			'per_page',
			array(
				'default' => 20,
				'option'  => "{$screen->id}_per_page",
			)
		);
		$screen->set_screen_reader_content(
			array(
				'heading_pagination' => __( 'Reports list navigation', 'reporting-api' ),
				'heading_list'       => __( 'Reports list', 'reporting-api' ),
			)
		);

		$this->list_table = new Reports_List_Table( $this->reports, $this->report_logs, $this->report_types );
		$this->list_table->prepare_items();
	}
}
