<?php
/**
 * Class Google\WP_Reporting_API\Group_Headers
 *
 * @package   Google\WP_Reporting_API
 * @copyright 2019 Google LLC
 * @license   GNU General Public License, version 2
 * @link      https://wordpress.org/plugins/reporting-api/
 */

namespace Google\WP_Reporting_API;

/**
 * Class for controlling endpoint group headers.
 *
 * @since 0.1.0
 */
class Group_Headers {

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
	 * @param Groups $groups Endpoint groups controller instance.
	 */
	public function __construct( Groups $groups ) {
		$this->groups = $groups;
	}

	/**
	 * Sends Report-To header for all the endpoint groups.
	 *
	 * @since 0.1.0
	 */
	public function send_headers() {
		$groups = $this->groups->get_all();

		$headers = array();
		foreach ( $groups as $group ) {
			$headers[] = wp_json_encode(
				array(
					'group'              => $group->name,
					'include_subdomains' => $group->include_subdomains,
					'max_age'            => $group->max_age,
					'endpoints'          => $group->endpoints,
				)
			);
		}

		if ( empty( $headers ) ) {
			return;
		}

		$value = implode( ', ', $headers );

		header( "Report-To: {$value}" );
	}
}
