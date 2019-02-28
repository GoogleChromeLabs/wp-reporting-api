<?php
/**
 * Class Google\WP_Reporting_API\Report_Log
 *
 * @package   Google\WP_Reporting_API
 * @copyright 2019 Google LLC
 * @license   GNU General Public License, version 2
 * @link      https://wordpress.org/plugins/reporting-api/
 */

namespace Google\WP_Reporting_API;

/**
 * Class representing a report log.
 *
 * @since 0.1.0
 */
class Report_Log {

	/**
	 * Unique report log ID, or 0 if not persisted yet.
	 *
	 * @since 0.1.0
	 * @var int
	 */
	protected $id = 0;

	/**
	 * Report log properties.
	 *
	 * @since 0.1.0
	 * @var array
	 */
	protected $props = array();

	/**
	 * Constructor.
	 *
	 * Sets the report log properties.
	 *
	 * @since 0.1.0
	 *
	 * @param array $props {
	 *     Report log properties, including the ID if present.
	 *
	 *     @type int           $id         Unique report log ID, or 0 if not persisted yet. Default 0.
	 *     @type int           $report_id  ID of the report associated with this report log. Default 0.
	 *     @type string        $url        The URL to which the report log applies. Default empty string.
	 *     @type string        $user_agent The user agent to which the report log applies. Default empty string.
	 *     @type string        $triggered  The date the report was first triggered, in GMT. Default is the value
	 *                                     of $reported if present, or the current date.
	 *     @type string        $reported   The date the report was first reported, in GMT. Default is the value
	 *                                     of $triggered if present, or the current date.
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
	 * Gets the report associated with this report log.
	 *
	 * @since 0.1.0
	 *
	 * @return Report|null Report instance, or null if not available.
	 */
	public function get_report() {
		if ( ! $this->props['report_id'] ) {
			return null;
		}

		return Plugin::instance()->reports()->get( $this->props['report_id'] );
	}

	/**
	 * Sets the report log properties.
	 *
	 * @since 0.1.0
	 *
	 * @param array $props List of report log properties. See {@see Report_Log::__construct()} for a list of supported
	 *                     properties.
	 */
	protected function set_props( array $props ) {
		$defaults = array(
			'report_id'  => 0,
			'url'        => '',
			'user_agent' => '',
			'triggered'  => '',
			'reported'   => '',
		);

		$this->props = array_intersect_key( array_merge( $defaults, $props ), $defaults );

		$this->props['report_id'] = (int) $this->props['report_id'];

		if ( ! empty( $this->props['url'] ) ) {
			$this->props['url'] = trailingslashit( $this->props['url'] );
		}

		if ( $this->is_date_empty( $this->props['triggered'] ) ) {
			$this->props['triggered'] = $this->get_fallback_date( $this->props['reported'] );
		}

		if ( $this->is_date_empty( $this->props['reported'] ) ) {
			$this->props['reported'] = $this->props['triggered'];
		}
	}

	/**
	 * Gets a fallback date.
	 *
	 * @since 0.1.0
	 *
	 * @param string $fallback_date Fallback date to use, possibly an empty string.
	 * @return string Returns the fallback date if valid, or otherwise the current GMT date.
	 */
	protected function get_fallback_date( $fallback_date ) {
		if ( ! $this->is_date_empty( $fallback_date ) ) {
			return $fallback_date;
		}

		return current_time( 'mysql', true );
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
