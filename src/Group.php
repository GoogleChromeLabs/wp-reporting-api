<?php
/**
 * Class Google\WP_Reporting_API\Group
 *
 * @package   Google\WP_Reporting_API
 * @copyright 2019 Google LLC
 * @license   GNU General Public License, version 2
 * @link      https://wordpress.org/plugins/reporting-api/
 */

namespace Google\WP_Reporting_API;

/**
 * Class representing a reporting endpoint group.
 *
 * @since 0.1.0
 */
class Group {

	/**
	 * Group name.
	 *
	 * @since 0.1.0
	 * @var string
	 */
	protected $name = '';

	/**
	 * Group arguments.
	 *
	 * @since 0.1.0
	 * @var array
	 */
	protected $args = array();

	/**
	 * Constructor.
	 *
	 * Sets the group name and arguments.
	 *
	 * @since 0.1.0
	 *
	 * @param string $name Group name.
	 * @param array  $args {
	 *     Group arguments.
	 *
	 *     @type int    $max_age            Lifetime of the endpoint group, in seconds. Default is one year.
	 *     @type bool   $include_subdomains Whether to include the endpoint group for all subdomains of the current
	 *                                      origin's host. Default false.
	 *     @type array  $endpoints          List of endpoint definitions. At least one endpoint is required. Each
	 *                                      endpoint must contain a "url" property, and can optionally contain
	 *                                      "priority" and "weight".
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
	 * Sets the group arguments.
	 *
	 * @since 0.1.0
	 *
	 * @param array $args List of group arguments. See {@see Group::__construct()} for a list of supported
	 *                    arguments.
	 */
	protected function set_args( array $args ) {
		$this->args = wp_parse_args(
			$args,
			array(
				'max_age'            => YEAR_IN_SECONDS,
				'include_subdomains' => false,
				'endpoints'          => array(),
			)
		);

		foreach ( $this->args['endpoints'] as $index => $endpoint ) {
			if ( is_string( $endpoint ) ) {
				$endpoint = array( 'url' => $endpoint );
			}

			$this->args['endpoints'][ $index ] = $this->parse_endpoint( (array) $endpoint );
		}

		$this->args['endpoints'] = array_values( $this->args['endpoints'] );
	}

	/**
	 * Parses an endpoint array as part of the arguments.
	 *
	 * @since 0.1.0
	 *
	 * @param array $endpoint {
	 *     Endpoint arguments.
	 *
	 *     @type string $url      URL to the endpoint. Default is the built-in REST API endpoint.
	 *     @type int    $priority Failover class the endpoint belongs to. Must be non-negative.
	 *     @type int    $weight   Defines load balancing for the failover class the endpoint belongs to. Must be
	 *                            non-negative.
	 * }
	 * @return array Parsed $endpoint array.
	 */
	protected function parse_endpoint( array $endpoint ) {
		if ( ! isset( $endpoint['url'] ) ) {
			$endpoint['url'] = Plugin::instance()->reporting_endpoint_url();
		}

		if ( isset( $endpoint['priority'] ) && 0 >= (int) $endpoint['priority'] ) {
			unset( $endpoint['priority'] );
		}

		if ( isset( $endpoint['weight'] ) && 0 >= (int) $endpoint['weight'] ) {
			unset( $endpoint['weight'] );
		}

		return $endpoint;
	}
}
