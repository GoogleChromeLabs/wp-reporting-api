<?php
/**
 * Class Google\WP_Reporting_API\Admin\Reporting_Screen
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

/**
 * Class representing the reporting admin screen.
 *
 * @since 0.1.0
 */
class Reporting_Screen {

	/**
	 * The admin page slug.
	 *
	 * @since 0.1.0
	 * @var string
	 */
	const SLUG = 'reporting-api';

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
	}

	/**
	 * Registers the menu item for the admin screen.
	 *
	 * @since 0.1.0
	 */
	public function register_menu() {
		add_submenu_page( self::PARENT_SLUG, __( 'Reports', 'reporting-api' ), __( 'Reporting API', 'reporting-api' ), self::CAPABILITY, self::SLUG, array( $this, 'render_screen' ) );
	}

	/**
	 * Renders the admin screen.
	 *
	 * @since 0.1.0
	 */
	public function render_screen() {
		$list_table = new Reports_List_Table( $this->reports, $this->report_logs );
		$list_table->prepare_items();

		$type   = filter_input( INPUT_GET, 'type' );
		$search = filter_input( INPUT_GET, 's' );

		$title = __( 'Reports', 'reporting-api' );
		if ( ! empty( $type ) ) {
			$types = Reports::get_types();
			if ( isset( $types[ $type ] ) ) {
				/* translators: %s: report type label */
				$title = sprintf( __( '%s Reports', 'reporting-api' ), $types[ $type ] );
			}
		}

		?>
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
				printf( ' <span class="subtitle">' . __( 'Search results for &#8220;%s&#8221;' ) . '</span>', esc_html( $search ) );
			}
			?>
			<hr class="wp-header-end">

			<?php $list_table->views(); ?>

			<form id="reports-filter" method="get">
				<?php $list_table->search_box( __( 'Search reports', 'reporting-api' ), 'search-reports' ); ?>

				<?php
				if ( ! empty( $type ) ) {
					?>
					<input type="hidden" name="type" value="<?php echo esc_attr( $type ); ?>" />
					<?php
				}
				?>

				<?php $list_table->display(); ?>
			</form>
		</div>
		<?php
	}
}