<?php
/**
 * Class Google\WP_Reporting_API\Report_Type
 *
 * @package   Google\WP_Reporting_API
 * @copyright 2019 Google LLC
 * @license   GNU General Public License, version 2
 * @link      https://wordpress.org/plugins/reporting-api/
 */

namespace Google\WP_Reporting_API;

/**
 * Class representing a report type.
 *
 * @since 0.1.0
 */
class Report_Type {

	/**
	 * Report type name.
	 *
	 * @since 0.1.0
	 * @var string
	 */
	protected $name = '';

	/**
	 * Report type arguments.
	 *
	 * @since 0.1.0
	 * @var array
	 */
	protected $args = array();

	/**
	 * Constructor.
	 *
	 * Sets the report type name and arguments.
	 *
	 * @since 0.1.0
	 *
	 * @param string $name Report type name.
	 * @param array  $args {
	 *     Report type arguments.
	 *
	 *     @type string   $title           User-facing report type title.
	 *     @type callable $header_callback Callback for sending the header to point reports of this type to a given
	 *                                     endpoint group. The callback must accept a single parameter for the endpoint
	 *                                     group name.
	 * }
	 */
	public function __construct( $name, array $args ) {
		$this->name = $name;
		$this->set_args( $args );
	}

	/**
	 * Magic isset-er.
	 *
	 * @since 0.1.0
	 *
	 * @param string $prop Property to check for.
	 * @return bool True if the property is set, false otherwise.
	 */
	public function __isset( $prop ) {
		if ( 'name' === $prop ) {
			return true;
		}

		return isset( $this->args[ $prop ] );
	}

	/**
	 * Magic getter.
	 *
	 * @since 0.1.0
	 *
	 * @param string $prop Property to get.
	 * @return mixed The property value, or null if property not set.
	 */
	public function __get( $prop ) {
		if ( 'name' === $prop ) {
			return $this->name;
		}

		if ( isset( $this->args[ $prop ] ) ) {
			return $this->args[ $prop ];
		}

		return null;
	}

	/**
	 * Sets the report type arguments.
	 *
	 * @since 0.1.0
	 *
	 * @param array $args List of report type arguments. See {@see Report_Type::__construct()} for a list of supported
	 *                    arguments.
	 */
	protected function set_args( array $args ) {
		$this->args = wp_parse_args(
			$args,
			array(
				'title'           => '',
				'header_callback' => null,
			)
		);
	}
}
