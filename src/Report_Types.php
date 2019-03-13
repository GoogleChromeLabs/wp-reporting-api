<?php
/**
 * Class Google\WP_Reporting_API\Report_Types
 *
 * @package   Google\WP_Reporting_API
 * @copyright 2019 Google LLC
 * @license   GNU General Public License, version 2
 * @link      https://wordpress.org/plugins/reporting-api/
 */

namespace Google\WP_Reporting_API;

/**
 * Class for controlling report types.
 *
 * @since 0.1.0
 */
class Report_Types {

	/**
	 * Internal storage for lazy-loaded report types, also to prevent double initialization.
	 *
	 * @since 0.1.0
	 * @var array
	 */
	protected $report_types = array();

	/**
	 * Gets all the available report types.
	 *
	 * @since 0.1.0
	 *
	 * @return array Associative array of $report_type_name => $report_type_instance pairs.
	 */
	public function get_all() {
		if ( ! empty( $this->report_types ) ) {
			return $this->report_types;
		}

		$report_types = array(
			'csp'                      => array(
				'title'           => __( 'Content Security Policy', 'reporting-api' ),
				'header_callback' => function( $group ) {
					// Use report-uri directive for browser backward compatibility.
					$report_uri = '';
					if ( 'default' === $group ) {
						$report_uri = 'report-uri ' . Plugin::instance()->reporting_endpoint_url() . '; ';
					}

					$value = "{$report_uri}report-to {$group}";
					header( "Content-Security-Policy-Report-Only: {$value}" );
				},
			),
			'crash'                    => array(
				'title' => __( 'Crash', 'reporting-api' ),
			),
			'deprecation'              => array(
				'title' => __( 'Deprecation', 'reporting-api' ),
			),
			'feature-policy-violation' => array(
				'title' => __( 'Feature Policy Violation', 'reporting-api' ),
			),
			'hpkp'                     => array(
				'title' => __( 'HTTP Public Key Pinning', 'reporting-api' ),
			),
			'intervention'             => array(
				'title' => __( 'Intervention', 'reporting-api' ),
			),
			'network-error'            => array(
				'title'           => __( 'Network Error', 'reporting-api' ),
				'header_callback' => function( $group ) {
					$value = wp_json_encode(
						array(
							'report_to' => $group,
							'max_age'   => 4 * WEEK_IN_SECONDS,
						)
					);
					header( "NEL: {$value}" );
				},
			),
		);

		$this->report_types = array();
		foreach ( $report_types as $report_type => $report_type_info ) {
			$this->report_types[ $report_type ] = new Report_Type(
				$report_type,
				$report_type_info
			);
		}

		return $this->report_types;
	}
}
