<?php
/**
 * HelloPack Client Github class.
 *
 * @package HelloPack_Client
 */

if ( ! class_exists( 'HelloPack_Client_Github' ) ) :

	/**
	 * Creates the connection between Github to install & update the HelloPack Client plugin.
	 *
	 * @class HelloPack_Client_Github
	 * @version 1.0.0
	 * @since 2.0.0
	 */
	class HelloPack_Client_Github {

		/**
		 * Action nonce.
		 *
		 * @type string
		 */
		const AJAX_ACTION = 'hellopack_client_dismiss_notice';

		/**
		 * The single class instance.
		 *
		 * @since 2.0.0
		 * @access private
		 *
		 * @var object
		 */
		private static $_instance = null;

		/**
		 * The API URL.
		 *
		 * @since 2.0.0
		 * @access private
		 *
		 * @var string
		 */
		private static $api_url = 'https://api.v2.wp-json.app/v2/hellopack-client/';

		/**
		 * The HelloPack_Client_Items Instance
		 *
		 * Ensures only one instance of this class exists in memory at any one time.
		 *
		 * @see HelloPack_Client_Github()
		 * @uses HelloPack_Client_Github::init_actions() Setup hooks and actions.
		 *
		 * @since 2.0.0
		 * @static
		 * @return object The one true HelloPack_Client_Github.
		 * @codeCoverageIgnore
		 */
		public static function instance() {
			if ( is_null( self::$_instance ) ) {
				self::$_instance = new self();
				self::$_instance->init_actions();
			}
			return self::$_instance;
		}

		/**
		 * A dummy constructor to prevent this class from being loaded more than once.
		 *
		 * @see HelloPack_Client_Github::instance()
		 *
		 * @since 2.0.0
		 * @access private
		 * @codeCoverageIgnore
		 */
		private function __construct() {
			/* We do nothing here! */
		}

		/**
		 * You cannot clone this class.
		 *
		 * @since 2.0.0
		 * @codeCoverageIgnore
		 */
		public function __clone() {
			_doing_it_wrong( __FUNCTION__, esc_html__( 'Cheatin&#8217; huh?', 'hellopack-client' ), '1.0.0' );
		}

		/**
		 * You cannot unserialize instances of this class.
		 *
		 * @since 2.0.0
		 * @codeCoverageIgnore
		 */
		public function __wakeup() {
			_doing_it_wrong( __FUNCTION__, esc_html__( 'Cheatin&#8217; huh?', 'hellopack-client' ), '1.0.0' );
		}

		/**
		 * Setup the actions and filters.
		 *
		 * @uses add_action() To add actions.
		 * @uses add_filter() To add filters.
		 *
		 * @since 2.0.0
		 */
		public function init_actions() {

			// Bail outside of the WP Admin panel.
			if ( ! is_admin() ) {
				return;
			}

			add_filter( 'http_request_args', array( $this, 'update_check' ), 5, 2 );
			add_filter( 'plugins_api', array( $this, 'plugins_api' ), 999999999, 3 );
			add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'update_plugins' ), PHP_INT_MAX, 1 );
			add_filter( 'pre_set_transient_update_plugins', array( $this, 'update_plugins' ), PHP_INT_MAX, 1 );
			add_filter( 'site_transient_update_plugins', array( $this, 'update_state' ), PHP_INT_MAX, 1 );
			add_filter( 'transient_update_plugins', array( $this, 'update_state' ), PHP_INT_MAX, 1 );
			add_action( 'admin_notices', array( $this, 'notice' ) );
			add_action( 'wp_ajax_' . self::AJAX_ACTION, array( $this, 'dismiss_notice' ) );
		}

		/**
		 * Check Github for an update.
		 *
		 * @since 2.0.0
		 *
		 * @return false|object
		 */
		public function api_check() {
			$raw_response = wp_remote_get( self::$api_url );
			if ( is_wp_error( $raw_response ) ) {
				return false;
			}

			if ( ! empty( $raw_response['body'] ) ) {
				$raw_body = json_decode( $raw_response['body'], true );
				if ( $raw_body ) {
					return (object) $raw_body;
				}
			}

			return false;
		}

		/**
		 * Disables requests to the wp.org repository for HelloPack Client.
		 *
		 * @since 2.0.0
		 *
		 * @param array  $request An array of HTTP request arguments.
		 * @param string $url The request URL.
		 * @return array
		 */
		public function update_check( $request, $url ) {

			// Plugin update request.
			if ( false !== strpos( $url, '//api.wordpress.org/plugins/update-check/1.1/' ) ) {

				// Decode JSON so we can manipulate the array.
				$data = json_decode( $request['body']['plugins'] );

				// Remove the HelloPack Client.
				unset( $data->plugins->{'hellopack-client/hellopack-client.php'} );

				// Encode back into JSON and update the response.
				$request['body']['plugins'] = wp_json_encode( $data );
			}

			return $request;
		}

		/**
		 * API check.
		 *
		 * @since 2.0.0
		 *
		 * @param bool   $api Always false.
		 * @param string $action The API action being performed.
		 * @param object $args Plugin arguments.
		 * @return mixed $api The plugin info or false.
		 */
		public function plugins_api( $api, $action, $args ) {
			if ( isset( $args->slug ) && 'hellopack-client' === $args->slug ) {
				$api_check = $this->api_check();
				if ( is_object( $api_check ) ) {
					$api = $api_check;
				}
			}
			return $api;
		}

		/**
		 * Update check.
		 *
		 * @since 2.0.0
		 *
		 * @param object $transient The pre-saved value of the `update_plugins` site transient.
		 * @return object
		 */
		public function update_plugins( $transient ) {
			$state = $this->state();
			if ( 'activated' === $state ) {
				$api_check = $this->api_check();
				if ( is_object( $api_check ) && version_compare( hellopack_client()->get_version(), $api_check->version, '<' ) ) {
					$transient->response['hellopack-client/hellopack-client.php'] = (object) array(
						'slug'        => 'hellopack-client',
						'plugin'      => 'hellopack-client/hellopack-client.php',
						'new_version' => $api_check->version,
						'url'         => 'https://github.com/hellopack/wp-hellopack-client',
						'package'     => $api_check->download_link,
					);
				}
			}
			return $transient;
		}

		/**
		 * Set the plugin state.
		 *
		 * @since 2.0.0
		 *
		 * @return string
		 */
		public function state() {
			$option         = 'hellopack_client_state';
			$active_plugins = apply_filters( 'active_plugins', get_option( 'active_plugins' ) );
			// We also have to check network activated plugins. Otherwise this plugin won't update on multisite.
			$active_sitewide_plugins = get_site_option( 'active_sitewide_plugins' );
			if ( ! is_array( $active_plugins ) ) {
				$active_plugins = array();
			}
			if ( ! is_array( $active_sitewide_plugins ) ) {
				$active_sitewide_plugins = array();
			}
			$active_plugins = array_merge( $active_plugins, array_keys( $active_sitewide_plugins ) );
			if ( in_array( 'hellopack-client/hellopack-client.php', $active_plugins ) ) {
				$state = 'activated';
				update_option( $option, $state );
			} else {
				$state = 'install';
				update_option( $option, $state );
				foreach ( array_keys( get_plugins() ) as $plugin ) {
					if ( strpos( $plugin, 'hellopack-client.php' ) !== false ) {
						$state = 'deactivated';
						update_option( $option, $state );
					}
				}
			}
			return $state;
		}

		/**
		 * Force the plugin state to be updated.
		 *
		 * @since 2.0.0
		 *
		 * @param object $transient The saved value of the `update_plugins` site transient.
		 * @return object
		 */
		public function update_state( $transient ) {
			$state = $this->state();
			return $transient;
		}

		/**
		 * Admin notices.
		 *
		 * @since 2.0.0
		 *
		 * @return string
		 */
		public function notice() {
			$screen = get_current_screen();
			$slug   = 'hellopack-client';
			$state  = get_option( 'hellopack_client_state' );
			$notice = get_option( self::AJAX_ACTION );

			if ( empty( $state ) ) {
				$state = $this->state();
			}

			if (
				'activated' === $state ||
				'update-core' === $screen->id ||
				'update' === $screen->id ||
				'plugins' === $screen->id && isset( $_GET['action'] ) && 'delete-selected' === $_GET['action'] ||
				'dismissed' === $notice
				) {
				return;
			}

			if ( 'deactivated' === $state ) {
				$activate_url = add_query_arg(
					array(
						'action'   => 'activate',
						'plugin'   => urlencode( "$slug/$slug.php" ),
						'_wpnonce' => urlencode( wp_create_nonce( "activate-plugin_$slug/$slug.php" ) ),
					),
					self_admin_url( 'plugins.php' )
				);

				$message = sprintf(
					// translators: %1$s and %2$s are the opening and closing <a> tags.
					esc_html__( '%1$sActivate the HelloPack Client plugin%2$s to get updates for your plugins and themes.', 'hellopack-client' ),
					'<a href="' . esc_url( $activate_url ) . '">',
					'</a>'
				);
			} elseif ( 'install' === $state ) {
				$install_url = add_query_arg(
					array(
						'action' => 'install-plugin',
						'plugin' => $slug,
					),
					self_admin_url( 'update.php' )
				);

				$message = sprintf(
					// translators: %1$s and %2$s are the opening and closing <a> tags, %3$s is the plugin name.
					esc_html__( '%1$sInstall the HelloPack Client plugin%2$s to get updates for your themes and plugins.', 'hellopack-client' ),
					'<a href="' . esc_url( wp_nonce_url( $install_url, 'install-plugin_' . $slug ) ) . '">',
					'</a>'
				);
			}

			if ( isset( $message ) ) {
				?>
<div class="updated hellopack-client-notice notice is-dismissible">
	<p><?php echo wp_kses_post( $message ); ?></p>
</div>
<script>
jQuery(document).ready(function($) {
	$(document).on('click', '.hellopack-client-notice .notice-dismiss', function() {
			$.ajax({
				url: ajaxurl,
				data: {
					action: '<?php echo self::AJAX_ACTION; ?>',
					nonce: '<?php echo wp_create_nonce( self::AJAX_ACTION ); ?>'
				}
			});
	});
});
</script>
				<?php
			}
		}

		/**
		 * Dismiss admin notice.
		 *
		 * @since 2.0.0
		 */
		public function dismiss_notice() {
			check_ajax_referer( self::AJAX_ACTION, 'nonce' );

			update_option( self::AJAX_ACTION, 'dismissed' );
			wp_send_json_success();
		}
	}

	if ( ! function_exists( 'hellopack_client_github' ) ) :
		/**
		 * HelloPack_Client_Github Instance
		 *
		 * @since 2.0.0
		 *
		 * @return HelloPack_Client_Github
		 */
		function hellopack_client_github() {
			return HelloPack_Client_Github::instance();
		}
	endif;

	/**
	 * Loads the main instance of HelloPack_Client_Github
	 *
	 * @since 2.0.0
	 */
	add_action( 'after_setup_theme', 'hellopack_client_github', 99 );

endif;
