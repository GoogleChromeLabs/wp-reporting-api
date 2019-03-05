<?php
/**
 * Class Google\WP_Reporting_API\Admin\Pointers
 *
 * @package   Google\WP_Reporting_API
 * @copyright 2019 Google LLC
 * @license   GNU General Public License, version 2
 * @link      https://wordpress.org/plugins/reporting-api/
 */

namespace Google\WP_Reporting_API\Admin;

/**
 * Class for controlling admin pointers.
 *
 * @since 0.1.0
 */
class Pointers {

	/**
	 * The plugin activation pointer ID.
	 *
	 * @since 0.1.0
	 * @var string
	 */
	const ACTIVATION = 'reporting_api_activation';

	/**
	 * Initializes admin pointers.
	 *
	 * @since 0.1.0
	 *
	 * @param string $hook_suffix The current admin page.
	 */
	public function enqueue_scripts( $hook_suffix ) {
		$pointers = $this->get_pointers( $hook_suffix );
		if ( empty( $pointers ) ) {
			return;
		}

		$dismissed = explode( ',', (string) get_user_meta( get_current_user_id(), 'dismissed_wp_pointers', true ) );

		$pointers = array_diff_key( $pointers, array_flip( $dismissed ) );

		$got_pointers = false;
		foreach ( $pointers as $pointer_id => $pointer_args ) {
			if ( empty( $pointer_args['render_callback'] ) ) {
				continue;
			}

			if ( ! empty( $pointer_args['active_callback'] ) && ! call_user_func( $pointer_args['active_callback'] ) ) {
				continue;
			}

			add_action( 'admin_print_footer_scripts', $pointer_args['render_callback'] );
			$got_pointers = true;
		}

		// Bail if no pointers need to be loaded.
		if ( ! $got_pointers ) {
			return;
		}

		wp_enqueue_style( 'wp-pointer' );
		wp_enqueue_script( 'wp-pointer' );
	}

	/**
	 * Gets available admin pointers.
	 *
	 * @since 0.1.0
	 *
	 * @param string $hook_suffix The current admin page.
	 * @return array Associative array of $pointer_id => $pointer_args pairs.
	 */
	protected function get_pointers( $hook_suffix ) {
		$pointers = array(
			self::ACTIVATION => array(
				'render_callback' => function() {
					$content = '<h3>' . __( 'Reporting API', 'reporting-api' ) . '</h3>';
					$content .= '<p>' . __( 'You now receive reports of client errors and policy violations on your site, which you can browse under <strong>Tools &gt; Reporting API</strong>.', 'reporting-api' ) . '</p>';

					$position = array(
						'edge'  => is_rtl() ? 'right' : 'left',
						'align' => 'bottom',
					);

					$js_args = array(
						'content'      => $content,
						'position'     => $position,
						'pointerClass' => 'wp-pointer arrow-bottom',
					);
					$this->print_js( self::ACTIVATION, '#menu-tools', $js_args );
				},
				'active_callback' => function() {
					if ( ! current_user_can( Reports_Screen::CAPABILITY ) ) {
						return false;
					}

					return true;
				},
			),
		);

		$pointers = array(
			'index.php'   => array( self::ACTIVATION => $pointers[ self::ACTIVATION ] ),
			'plugins.php' => array( self::ACTIVATION => $pointers[ self::ACTIVATION ] ),
		);

		if ( ! isset( $pointers[ $hook_suffix ] ) ) {
			return array();
		}

		return $pointers[ $hook_suffix ];
	}

	/**
	 * Prints JavaScript data for a given pointer.
	 *
	 * @since 0.1.0
	 *
	 * @param string $pointer_id The pointer ID.
	 * @param string $selector   The HTML elements, on which the pointer should be attached.
	 * @param array  $args       Arguments to be passed to the pointer JS (see wp-pointer.js).
	 */
	protected function print_js( $pointer_id, $selector, $args ) {
		if ( empty( $pointer_id ) || empty( $selector ) || empty( $args ) || empty( $args['content'] ) ) {
			return;
		}

		?>
		<script type="text/javascript">
		(function($){
			var options = <?php echo wp_json_encode( $args ); ?>, setup;

			if ( ! options )
				return;

			options = $.extend( options, {
				close: function() {
					$.post( ajaxurl, {
						pointer: '<?php echo esc_js( $pointer_id ); ?>',
						action: 'dismiss-wp-pointer'
					});
				}
			});

			setup = function() {
				$('<?php echo esc_js( $selector ); ?>').first().pointer( options ).pointer('open');
			};

			if ( options.position && options.position.defer_loading )
				$(window).bind( 'load.wp-pointers', setup );
			else
				$(document).ready( setup );

		})( jQuery );
		</script>
		<?php
	}
}
