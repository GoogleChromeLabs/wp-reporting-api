<?php
/**
 * Class Google\WP_Reporting_API\Report
 *
 * @package Google\WP_Reporting_API
 * @license GNU General Public License, version 2
 * @link    https://wordpress.org/plugins/reporting-api/
 */

namespace Google\WP_Reporting_API;

/**
 * Class representing a report.
 *
 * @since 0.1.0
 */
class Report {

	/**
	 * Unique report ID, or 0 if not persisted yet.
	 *
	 * @since 0.1.0
	 * @var int
	 */
	protected $id = 0;

	/**
	 * Report properties.
	 *
	 * @since 0.1.0
	 * @var array
	 */
	protected $props = array();

	/**
	 * Constructor.
	 *
	 * Sets the report properties.
	 *
	 * @since 0.1.0
	 *
	 * @param array $props {
	 *     Report properties, including the ID if present.
	 *
	 *     @type int           $id              Unique report ID, or 0 if not persisted yet. Default 0.
	 *     @type string        $type            Report type. Default empty string.
	 *     @type string        $first_triggered The date the report was first triggered, in GMT. Default is the
	 *                                          value of $first_reported if present, or the current date.
	 *     @type string        $first_reported  The date the report was first reported, in GMT. Default is the
	 *                                          value of $first_triggered if present, or the current date.
	 *     @type string        $last_triggered  The date the report was last triggered, in GMT. Default is the value
	 *                                          of $first_triggered.
	 *     @type string        $last_reported   The date the report was last reported, in GMT. Default is the value
	 *                                          of $last_triggered.
	 *     @type string        $url             The URL to which the report applies. Default empty string.
	 *     @type string        $user_agent      The user agent to which the report applies. Default empty string.
	 *     @type object|string $body            The report body data, either as object with public properties or JSON
	 *                                          string. The properties that the object should have depends on the
	 *                                          report $type. Default empty object.
	 * }
	 */
	public function __construct( array $props ) {
		if ( isset( $props['id'] ) ) {
			$this->id = (int) $props['id'];
			unset( $props['id'] );
		}

		$this->set_props( $props );
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
		if ( 'id' === $prop ) {
			return true;
		}

		return isset( $this->props[ $prop ] );
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
		if ( 'id' === $prop ) {
			return $this->id;
		}

		if ( isset( $this->props[ $prop ] ) ) {
			return $this->props[ $prop ];
		}

		return null;
	}

	/**
	 * Sets the report properties.
	 *
	 * @since 0.1.0
	 *
	 * @param array $props List of report properties. See {@see Report::__construct()} for a list of supported
	 *                     properties.
	 */
	protected function set_props( array $props ) {
		$defaults = array(
			'type'            => '',
			'first_triggered' => '',
			'first_reported'  => '',
			'last_triggered'  => '',
			'last_reported'   => '',
			'url'             => '',
			'user_agent'      => '',
			'body'            => new \stdClass(),
		);

		$this->props = array_intersect_key( array_merge( $defaults, $props ), $defaults );

		// TODO: Validate type.
		if ( $this->is_date_empty( $this->props['first_triggered'] ) ) {
			if ( ! $this->is_date_empty( $this->props['first_reported'] ) ) {
				$this->props['first_triggered'] = $this->props['first_reported'];
			} else {
				$this->props['first_triggered'] = current_time( 'mysql', true );
			}
		}

		if ( $this->is_date_empty( $this->props['first_reported'] ) ) {
			$this->props['first_reported'] = $this->props['first_triggered'];
		}

		if ( $this->is_date_empty( $this->props['last_triggered'] ) ) {
			$this->props['last_triggered'] = $this->props['first_triggered'];
		}

		if ( $this->is_date_empty( $this->props['last_reported'] ) ) {
			$this->props['last_reported'] = $this->props['first_reported'];
		}

		if ( is_string( $this->props['body'] ) ) {
			if ( ! empty( $this->props['body'] ) ) {
				$this->props['body'] = json_decode( $this->props['body'] );
			} else {
				$this->props['body'] = new \stdClass();
			}
		}
	}

	/**
	 * Checks whether a given date is considered empty.
	 *
	 * This applies to an actually empty date string as well as the value '0000-00-00 00:00:00'.
	 *
	 * @since 0.1.0
	 *
	 * @param string $date MySQL date string.
	 * @return bool True if the date is considered empty, false otherwise.
	 */
	protected function is_date_empty( $date ) {
		return empty( $date ) || '0000-00-00 00:00:00' === $date;
	}
}
