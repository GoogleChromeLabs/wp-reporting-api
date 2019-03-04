<?php
/**
 * Class Google\WP_Reporting_API\Groups
 *
 * @package   Google\WP_Reporting_API
 * @copyright 2019 Google LLC
 * @license   GNU General Public License, version 2
 * @link      https://wordpress.org/plugins/reporting-api/
 */

namespace Google\WP_Reporting_API;

/**
 * Class for controlling reporting endpoint groups.
 *
 * @since 0.1.0
 */
class Groups {

	/**
	 * Internal storage for lazy-loaded groups, also to prevent double initialization.
	 *
	 * @since 0.1.0
	 * @var array
	 */
	protected $groups = array();

	/**
	 * Gets all the available groups.
	 *
	 * @since 0.1.0
	 *
	 * @return array Associative array of $group_name => $group_instance pairs.
	 */
	public function get_all() {
		if ( ! empty( $this->groups ) ) {
			return $this->groups;
		}

		$groups = array(
			'default' => array(
				'endpoints' => array(
					'url' => Plugin::instance()->reporting_endpoint_url(),
				),
			),
		);

		$this->groups = array();
		foreach ( $groups as $group => $group_info ) {
			$this->groups[ $group ] = new Group(
				$group,
				$group_info
			);
		}

		return $this->groups;
	}
}
