<?php
/**
 * Class Google\WP_Reporting_API\Report_Type_Headers
 *
 * @package   Google\WP_Reporting_API
 * @copyright 2019 Google LLC
 * @license   GNU General Public License, version 2
 * @link      https://wordpress.org/plugins/reporting-api/
 */

namespace Google\WP_Reporting_API;

/**
 * Class for controlling report type headers.
 *
 * @since 0.1.0
 */
class Report_Type_Headers {

	/**
	 * Report types controller instance.
	 *
	 * @since 0.1.0
	 * @var Report_Types
	 */
	protected $report_types;

	/**
	 * Endpoint groups controller instance.
	 *
	 * @since 0.1.0
	 * @var Groups
	 */
	protected $groups;

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 *
	 * @param Report_Types $report_types Report types controller instance.
	 * @param Groups       $groups       Endpoint groups controller instance.
	 */
	public function __construct( Report_Types $report_types, Groups $groups ) {
		$this->report_types = $report_types;
		$this->groups       = $groups;
	}

	/**
	 * Sends headers to route reports of each type to the correct reporting endpoint group.
	 *
	 * @since 0.1.0
	 */
	public function send_headers() {
		$report_types = $this->report_types->get_all();
		$groups       = $this->groups->get_all();

		$default_group = isset( $groups['default'] ) ? 'default' : key( $groups );

		foreach ( $report_types as $type ) {
			$header_callback = $type->header_callback;
			if ( ! $header_callback || ! is_callable( $header_callback ) ) {
				continue;
			}

			call_user_func( $header_callback, $default_group );
		}
	}
}
