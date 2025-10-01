<?php
/**
 * Admin UI class.
 *
 * @package HelloPack_Client
 */

if ( ! class_exists( 'HelloPack_Client_Admin' ) && class_exists( 'HelloPack_Client' ) ) :

	/**
	 * Creates an admin page to save the HelloPack API OAuth token.
	 *
	 * @class HelloPack_Client_Admin
	 * @version 1.0.0
	 * @since 2.0.0
	 */
	class HelloPack_Client_Admin {

		/**
		 * Action nonce.
		 *
		 * @type string
		 */
		const AJAX_ACTION = 'hellopack_client';

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
		 * Main HelloPack_Client_Admin Instance
		 *
		 * Ensures only one instance of this class exists in memory at any one time.
		 *
		 * @return object The one true HelloPack_Client_Admin.
		 * @codeCoverageIgnore
		 * @uses HelloPack_Client_Admin::init_actions() Setup hooks and actions.
		 *
		 * @since 2.0.0
		 * @static
		 * @see  HelloPack_Client_Admin()
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
		 * @see HelloPack_Client_Admin::instance()
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
		 * Setup the hooks, actions and filters.
		 *
		 * @uses add_action() To add actions.
		 * @uses add_filter() To add filters.
		 *
		 * @since 2.0.0
		 */
		public function init_actions() {
			// @codeCoverageIgnoreStart
			if ( false === hellopack_client()->get_data( 'admin' ) && false === hellopack_client()->get_option( 'is_plugin_active' ) ) { // Turns the UI off if allowed.
				return;
			}
			// @codeCoverageIgnoreEnd
			// Deferred Download.
			add_action( 'upgrader_package_options', array( $this, 'maybe_hp_deferred_downloads' ), PHP_INT_MAX );

			// Add pre download filter to help with 3rd party plugin integration.
			add_filter( 'upgrader_pre_download', array( $this, 'upgrader_pre_download' ), 2, 4 );

			// Add item AJAX handler.
			add_action( 'wp_ajax_' . self::AJAX_ACTION . '_add_item', array( $this, 'ajax_add_item' ) );

			// Remove item AJAX handler.
			add_action( 'wp_ajax_' . self::AJAX_ACTION . '_remove_item', array( $this, 'ajax_remove_item' ) );

			// Health check AJAX handler
			add_action( 'wp_ajax_' . self::AJAX_ACTION . '_healthcheck', array( $this, 'ajax_healthcheck' ) );

			// Maybe delete the site transients.
			add_action( 'init', array( $this, 'maybe_delete_transients' ), 11 );

			// Add the menu icon.
			add_action( 'admin_head', array( $this, 'add_menu_icon' ) );

			// Add the menu.
			add_action( 'admin_menu', array( $this, 'add_menu_page' ) );

			// Register the settings.
			add_action( 'admin_init', array( $this, 'register_settings' ) );

			// We may need to redirect after an item is enabled.
			add_action( 'current_screen', array( $this, 'maybe_redirect' ) );

			// Add authorization notices.
			add_action( 'current_screen', array( $this, 'add_notices' ) );

			// Set the API values.
			add_action( 'current_screen', array( $this, 'set_items' ) );

			// Hook to verify the API token before saving it.
			add_filter(
				'pre_update_option_' . hellopack_client()->get_option_name(),
				array(
					$this,
					'check_api_token_before_saving',
				),
				9,
				3
			);
			add_filter(
				'pre_update_site_option_' . hellopack_client()->get_option_name(),
				array(
					$this,
					'check_api_token_before_saving',
				),
				9,
				3
			);

			// When network enabled, add the network options menu.
			add_action( 'network_admin_menu', array( $this, 'add_menu_page' ) );

			// Ability to make use of the Settings API when in multisite mode.
			add_action( 'network_admin_edit_hellopack_client_network_settings', array( $this, 'save_network_settings' ) );
		}

		/**
		 * This runs before we save the HelloPack Client options array.
		 * If the token has changed then we set a transient so we can do the update check.
		 *
		 * @param array $value The option to save.
		 * @param array $old_value The old option value.
		 * @param array $option Serialized option value.
		 *
		 * @return array $value The updated option value.
		 * @since 2.0.1
		 */
		public function check_api_token_before_saving( $value, $old_value, $option ) {
			if ( ! empty( $value['token'] ) && ( empty( $old_value['token'] ) || $old_value['token'] != $value['token'] || isset( $_POST['hellopack_client'] ) ) ) {
				set_site_transient( hellopack_client()->get_option_name() . '_check_token', $value['token'], HOUR_IN_SECONDS );
			}

			return $value;
		}


		/**
		 * Defers building the API download url until the last responsible moment to limit file requests.
		 *
		 * Filter the package options before running an update.
		 *
		 * @param array $options {
		 *     Options used by the upgrader.
		 *
		 * @type string $package Package for update.
		 * @type string $destination Update location.
		 * @type bool   $clear_destination Clear the destination resource.
		 * @type bool   $clear_working Clear the working resource.
		 * @type bool   $abort_if_destination_exists Abort if the Destination directory exists.
		 * @type bool   $is_multi Whether the upgrader is running multiple times.
		 * @type array  $hook_extra Extra hook arguments.
		 * }
		 * @since 2.0.0
		 */
		public function maybe_hp_deferred_downloads( $options ) {
			$package = $options['package'];
			if ( false !== strrpos( $package, 'deferred_downloads' ) && false !== strrpos( $package, 'hp_item_id' ) ) {
				parse_str( parse_url( $package, PHP_URL_QUERY ), $vars );
				if ( $vars['hp_item_id'] ) {
					$args               = $this->set_bearer_args( $vars['hp_item_id'] );
					$options['package'] = hellopack_client()->api()->download( $vars['hp_item_id'], $args );
				}
			}

			return $options;
		}

		/**
		 * We want to stop certain popular 3rd party scripts from blocking the update process by
		 * adjusting the plugin name slightly so the 3rd party plugin checks stop.
		 *
		 * Currently works for: Visual Composer.
		 *
		 * @param string $reply Package URL.
		 * @param string $package Package URL.
		 * @param object $updater Updater Object.
		 *
		 * @return string $reply    New Package URL.
		 * @since 2.0.0
		 */
		public function upgrader_pre_download( $reply, $package, $updater ) {
			if ( strpos( $package, 'api.v2.wp-json.app/short-dl' ) !== false ) {
				if ( isset( $updater->skin->plugin_info ) && ! empty( $updater->skin->plugin_info['Name'] ) ) {
					$updater->skin->plugin_info['Name'] = $updater->skin->plugin_info['Name'] . '.';
				} else {
					$updater->skin->plugin_info = array(
						'Name' => 'Name',
					);
				}
			}

			return $reply;
		}

		/**
		 * Returns the bearer arguments for a request with a single use API Token.
		 *
		 * @param int $id The item ID.
		 *
		 * @return array
		 * @since 2.0.0
		 */
		public function set_bearer_args( $id ) {
			$token = '';
			$args  = array();
			foreach ( hellopack_client()->get_option( 'items', array() ) as $item ) {
				if ( absint( $item['id'] ) === absint( $id ) ) {
					$token = $item['token'];
					break;
				}
			}
			if ( ! empty( $token ) ) {
				$args = array(
					'headers' => array(
						'Authorization' => 'Bearer ' . $token,
					),
				);
			}

			return $args;
		}

		/**
		 * Maybe delete the site transients.
		 *
		 * @since 2.0.0
		 * @codeCoverageIgnore
		 */
		public function maybe_delete_transients() {
			if ( isset( $_POST[ hellopack_client()->get_option_name() ] ) ) {

				// Nonce check.
				if ( isset( $_POST['_wpnonce'] ) && ! wp_verify_nonce( $_POST['_wpnonce'], hellopack_client()->get_slug() . '-options' ) ) {
					wp_die( __( 'You do not have sufficient permissions to delete transients.', 'hellopack-client' ) );
				}

				self::delete_transients();
			} elseif ( ! hellopack_client()->get_option( 'installed_version', 0 ) || version_compare( hellopack_client()->get_version(), hellopack_client()->get_option( 'installed_version', 0 ), '<' ) ) {

				// When the plugin updates we want to delete transients.
				hellopack_client()->set_option( 'installed_version', hellopack_client()->get_version() );
				self::delete_transients();

			}
		}

		/**
		 * Delete the site transients.
		 *
		 * @since 2.0.0
		 * @access private
		 */
		private function delete_transients() {
			delete_site_transient( hellopack_client()->get_option_name() . '_themes' );
			delete_site_transient( hellopack_client()->get_option_name() . '_plugins' );
		}

		/**
		 * Prints out all settings sections added to a particular settings page in columns.
		 *
		 * @param string $page The slug name of the page whos settings sections you want to output.
		 * @param int    $columns The number of columns in each row.
		 *
		 * @since 2.0.0
		 *
		 * @global array $wp_settings_sections Storage array of all settings sections added to admin pages
		 * @global array $wp_settings_fields Storage array of settings fields and info about their pages/sections
		 */
		public static function do_settings_sections( $page, $columns = 2 ) {
			global $wp_settings_sections, $wp_settings_fields;

			// @codeCoverageIgnoreStart
			if ( ! isset( $wp_settings_sections[ $page ] ) ) {
				return;
			}
			// @codeCoverageIgnoreEnd
			foreach ( (array) $wp_settings_sections[ $page ] as $section ) {
				// @codeCoverageIgnoreStart
				if ( ! isset( $wp_settings_fields ) || ! isset( $wp_settings_fields[ $page ] ) || ! isset( $wp_settings_fields[ $page ][ $section['id'] ] ) ) {
					continue;
				}
				// @codeCoverageIgnoreEnd
				// Set the column class.
				$class = 'hellopack-db-card hellopack-db-card-first';
				?>
<div class="<?php echo esc_attr( $class ); ?>">
     <?php
				if ( ! empty( $section['title'] ) ) {
					echo '<h3>' . esc_html( $section['title'] ) . '</h3>' . "\n";
				}
				if ( ! empty( $section['callback'] ) ) {
					call_user_func( $section['callback'], $section );
				}
				?>
     <table class="form-table">
          <?php do_settings_fields( $page, $section['id'] ); ?>
     </table>
</div>
<?php
			}
		}

		/**
		 * Add a font based menu icon
		 *
		 * @since 2.0.0
		 */
		public function add_menu_icon() {
			// Fonts directory URL.
			$fonts_dir_url = hellopack_client()->get_plugin_url() . 'fonts/';

			// Create font styles.
			$style = '<style type="text/css">
				/*<![CDATA[*/
				@font-face {
					font-family: "hellopack";
					src:url("' . $fonts_dir_url . 'hellopack.eot?20150626");
					src:url("' . $fonts_dir_url . 'hellopack.eot?#iefix20150626") format("embedded-opentype"),
					url("' . $fonts_dir_url . 'hellopack.woff?20150626") format("woff"),
					url("' . $fonts_dir_url . 'hellopack.ttf?20150626") format("truetype"),
					url("' . $fonts_dir_url . 'hellopack.svg?20150626#hellopack") format("svg");
					font-weight: normal;
					font-style: normal;
				}
				#adminmenu .toplevel_page_' . hellopack_client()->get_slug() . ' .menu-icon-generic div.wp-menu-image:before {
					font: normal 17px/1 "hellopack" !important;
					content: "\0048";
					speak: none;
					padding: 9px 0;
					height: 34px;
					width: 15px;
					display: inline-block;
					-webkit-font-smoothing: antialiased;
					-moz-osx-font-smoothing: grayscale;
					-webkit-transition: all .1s ease-in-out;
					-moz-transition:    all .1s ease-in-out;
					transition:         all .1s ease-in-out;
				}
				/*]]>*/
			</style>';

			// Remove space after colons.
			$style = str_replace( ': ', ':', $style );

			// Remove whitespace.
			echo str_replace( array( "\r\n", "\r", "\n", "\t", '	', '		', '		', '  ', '    ' ), '', $style );
		}

		/**
		 * Adds the menu.
		 *
		 * @since 2.0.0
		 */
		public function add_menu_page() {

			if ( HELLOPACK_CLIENT_NETWORK_ACTIVATED && ! is_super_admin() ) {
				// we do not want to show a menu item for people who do not have permission.
				return;
			}

			$page = add_menu_page(
				__( 'HelloPack', 'hellopack-client' ),
				__( 'HelloPack', 'hellopack-client' ),
				'manage_options',
				hellopack_client()->get_slug(),
				array(
					$this,
					'render_admin_callback',
				),
				'',
				66
			);

			// Enqueue admin CSS.
			add_action( 'admin_print_styles-' . $page, array( $this, 'admin_enqueue_style' ) );

			// Enqueue admin JavaScript.
			add_action( 'admin_print_scripts-' . $page, array( $this, 'admin_enqueue_script' ) );

			// Add Underscore.js templates.
			add_action( 'admin_footer-' . $page, array( $this, 'render_templates' ) );
		}

		/**
		 * Enqueue admin css.
		 *
		 * @since  1.0.0
		 */
		public function admin_enqueue_style() {
			$file_url = hellopack_client()->get_plugin_url() . 'css/hellopack-client' . ( is_rtl() ? '-rtl' : '' ) . '.css';
			wp_enqueue_style( hellopack_client()->get_slug(), $file_url, array( 'wp-jquery-ui-dialog' ), hellopack_client()->get_version() );
		}

		/**
		 * Enqueue admin script.
		 *
		 * @since  1.0.0
		 */
		public function admin_enqueue_script() {
			$min        = ( WP_DEBUG ? '' : '.min' );
			$slug       = hellopack_client()->get_slug();
			$version    = hellopack_client()->get_version();
			$plugin_url = hellopack_client()->get_plugin_url();

			wp_enqueue_script(
				$slug,
				$plugin_url . 'js/hellopack-client' . $min . '.js',
				array(
					'jquery',
					'jquery-ui-dialog',
					'wp-util',
				),
				$version,
				true
			);
			wp_enqueue_script(
				$slug . '-updates',
				$plugin_url . 'js/updates' . $min . '.js',
				array(
					'jquery',
					'updates',
					'wp-a11y',
					'wp-util',
				),
				$version,
				true
			);

			// Script data array.
			$exports = array(
				'nonce'  => wp_create_nonce( self::AJAX_ACTION ),
				'action' => self::AJAX_ACTION,
				'i18n'   => array(
					'save'   => __( 'Save', 'hellopack-client' ),
					'remove' => __( 'Remove', 'hellopack-client' ),
					'cancel' => __( 'Cancel', 'hellopack-client' ),
					'error'  => __( 'An unknown error occurred. Try again.', 'hellopack-client' ),
				),
			);

			// Export data to JS.
			wp_scripts()->add_data(
				$slug,
				'data',
				sprintf( 'var _hellopackClient = %s;', wp_json_encode( $exports ) )
			);
		}

		/**
		 * Underscore (JS) templates for dialog windows.
		 *
		 * @codeCoverageIgnore
		 */
		public function render_templates() {
			?>
<script type="text/html" id="tmpl-hellopack-client-auth-check-button">
<a href="<?php echo esc_url( add_query_arg( array( 'authorization' => 'check' ), hellopack_client()->get_page_url() ) ); ?>"
     class="button button-secondary auth-check-button"
     style="margin:0 5px"><?php esc_html_e( 'Test API Connection', 'hellopack-client' ); ?></a>
</script>

<script type="text/html" id="tmpl-hellopack-client-item">
<li data-id="{{ data.id }}">
     <span class="item-name"><?php esc_html_e( 'ID', 'hellopack-client' ); ?>
          : {{ data.id }} - {{ data.name }}</span>
     <button class="item-delete dashicons dashicons-dismiss">
          <span class="screen-reader-text"><?php esc_html_e( 'Delete', 'hellopack-client' ); ?></span>
     </button>
     <input type="hidden"
          name="<?php echo esc_attr( hellopack_client()->get_option_name() ); ?>[items][{{ data.key }}][name]"
          value="{{ data.name }}" />
     <input type="hidden"
          name="<?php echo esc_attr( hellopack_client()->get_option_name() ); ?>[items][{{ data.key }}][token]"
          value="{{ data.token }}" />
     <input type="hidden"
          name="<?php echo esc_attr( hellopack_client()->get_option_name() ); ?>[items][{{ data.key }}][id]"
          value="{{ data.id }}" />
     <input type="hidden"
          name="<?php echo esc_attr( hellopack_client()->get_option_name() ); ?>[items][{{ data.key }}][type]"
          value="{{ data.type }}" />
     <input type="hidden"
          name="<?php echo esc_attr( hellopack_client()->get_option_name() ); ?>[items][{{ data.key }}][authorized]"
          value="{{ data.authorized }}" />
</li>
</script>

<script type="text/html" id="tmpl-hellopack-client-dialog-remove">
<div id="hellopack-client-dialog-remove" title="<?php esc_html_e( 'Remove Item', 'hellopack-client' ); ?>">
     <p><?php esc_html_e( 'You are about to remove the connection between the HelloPack Client API and this item. You cannot undo this action.', 'hellopack-client' ); ?>
     </p>
</div>
</script>

<script type="text/html" id="tmpl-hellopack-client-dialog-form">
<div id="hellopack-client-dialog-form" title="<?php esc_html_e( 'Add Item', 'hellopack-client' ); ?>">
     <form>
          <fieldset>
               <label for="token"><?php esc_html_e( 'Token', 'hellopack-client' ); ?></label>
               <input type="password" name="token" class="widefat" value="" />
               <p class="description">
                    <?php esc_html_e( 'Enter the HelloPack API Personal Token.', 'hellopack-client' ); ?>
               </p>
               <label for="id"><?php esc_html_e( 'Item ID', 'hellopack-client' ); ?></label>
               <input type="password" name="id" class="widefat" value="" />
               <p class="description"><?php esc_html_e( 'Enter the HelloPack Item ID.', 'hellopack-client' ); ?></p>
               <input type="submit" tabindex="-1" style="position:absolute; top:-5000px" />
          </fieldset>
     </form>
</div>
</script>

<script type="text/html" id="tmpl-hellopack-client-dialog-error">
<div class="notice notice-error">
     <p>{{ data.message }}</p>
</div>
</script>

<script type="text/html" id="tmpl-hellopack-client-card">
<div class="hellopack-client-block" data-id="{{ data.id }}">
     <div class="hellopack-card {{ data.type }}">
          <div class="hellopack-card-top">
               <a href="{{ data.url }}" class="column-icon">
                    <img src="{{ data.thumbnail_url }}" />
               </a>
               <div class="column-name">
                    <h4>
                         <a href="{{ data.url }}">{{ data.name }}</a>
                         <span class="version" aria-label="
					<?php // translators: Version %s refers to the version number ?>
						<?php esc_attr_e( 'Version %s', 'hellopack-client' ); ?>">
                              <?php esc_html_e( 'Version', 'hellopack-client' ); ?>
                              {{ data.version }}</span>
                    </h4>
               </div>
               <div class="column-description">
                    <div class="description">
                         <p>{{ data.description }}</p>
                    </div>
                    <p class="author">
                         <cite><?php esc_html_e( 'By', 'hellopack-client' ); ?> {{ data.author }}</cite>
                    </p>
               </div>
          </div>
          <div class="hellopack-card-bottom">
               <div class="column-actions">
                    <a href="{{{ data.install }}}" class="button button-primary">
                         <span aria-hidden="true"><?php esc_html_e( 'Install', 'hellopack-client' ); ?></span>
                         <span class="screen-reader-text"><?php esc_html_e( 'Install', 'hellopack-client' ); ?>
                              {{ data.name }}</span>
                    </a>
               </div>
          </div>
     </div>
</div>
</script>
<?php
		}

		/**
		 * Registers the settings.
		 *
		 * @since 2.0.0
		 */
		public function register_settings() {
			// Setting.
			register_setting( hellopack_client()->get_slug(), hellopack_client()->get_option_name() );

			// OAuth section.
			add_settings_section(
				hellopack_client()->get_option_name() . '_oauth_section',
				__( 'HelloPack activation', 'hellopack-client' ),
				array( $this, 'render_oauth_section_callback' ),
				hellopack_client()->get_slug()
			);

			// Token setting.
			add_settings_field(
				'token',
				__( 'API-key', 'hellopack-client' ),
				array( $this, 'render_token_setting_callback' ),
				hellopack_client()->get_slug(),
				hellopack_client()->get_option_name() . '_oauth_section'
			);

			// Items section.
			add_settings_section(
				hellopack_client()->get_option_name() . '_items_section',
				__( 'Single Item Tokens (Advanced)', 'hellopack-client' ),
				array( $this, 'render_items_section_callback' ),
				hellopack_client()->get_slug()
			);
		}

		/**
		 * Redirect after the enable action runs.
		 *
		 * @since 2.0.0
		 * @codeCoverageIgnore
		 */
		public function maybe_redirect() {
			if ( $this->are_we_on_settings_page() ) {

				if ( ! empty( $_GET['action'] ) && 'install-theme' === $_GET['action'] && ! empty( $_GET['enabled'] ) ) {
					wp_safe_redirect( esc_url( hellopack_client()->get_page_url() ) );
					exit;
				}
			}
		}

		/**
		 * Add authorization notices.
		 *
		 * @since 2.0.0
		 */
		public function add_notices() {

			if ( $this->are_we_on_settings_page() ) {

				// @codeCoverageIgnoreStart
				if ( get_site_transient( hellopack_client()->get_option_name() . '_check_token' ) || ( isset( $_GET['authorization'] ) && 'check' === $_GET['authorization'] ) ) {
					delete_site_transient( hellopack_client()->get_option_name() . '_check_token' );
					self::authorization_redirect();
				}
				// @codeCoverageIgnoreEnd
				// Get the option array.
				$option = hellopack_client()->get_options();

				// Display success/error notices.
				if ( ! empty( $option['notices'] ) ) {
					self::delete_transients();

					// Show succes notice.
					if ( isset( $option['notices']['success'] ) ) {
						add_action(
							( HELLOPACK_CLIENT_NETWORK_ACTIVATED ? 'network_' : '' ) . 'admin_notices',
							array(
								$this,
								'render_success_notice',
							)
						);
					}

					// Show succes no-items notice.
					if ( isset( $option['notices']['success-no-items'] ) ) {
						add_action(
							( HELLOPACK_CLIENT_NETWORK_ACTIVATED ? 'network_' : '' ) . 'admin_notices',
							array(
								$this,
								'render_success_no_items_notice',
							)
						);
					}

					// Show single-use succes notice.
					if ( isset( $option['notices']['success-single-use'] ) ) {
						add_action(
							( HELLOPACK_CLIENT_NETWORK_ACTIVATED ? 'network_' : '' ) . 'admin_notices',
							array(
								$this,
								'render_success_single_use_notice',
							)
						);
					}

					// Show error notice.
					if ( isset( $option['notices']['error'] ) ) {
						add_action(
							( HELLOPACK_CLIENT_NETWORK_ACTIVATED ? 'network_' : '' ) . 'admin_notices',
							array(
								$this,
								'render_error_notice',
							)
						);
					}

					// Show invalid permissions error notice.
					if ( isset( $option['notices']['error-permissions'] ) ) {
						add_action(
							( HELLOPACK_CLIENT_NETWORK_ACTIVATED ? 'network_' : '' ) . 'admin_notices',
							array(
								$this,
								'render_error_permissions',
							)
						);
					}

					// Show single-use error notice.
					if ( isset( $option['notices']['error-single-use'] ) ) {
						add_action(
							( HELLOPACK_CLIENT_NETWORK_ACTIVATED ? 'network_' : '' ) . 'admin_notices',
							array(
								$this,
								'render_error_single_use_notice',
							)
						);
					}

					// Show missing zip notice.
					if ( isset( $option['notices']['missing-package-zip'] ) ) {
						add_action(
							( HELLOPACK_CLIENT_NETWORK_ACTIVATED ? 'network_' : '' ) . 'admin_notices',
							array(
								$this,
								'render_error_missing_zip',
							)
						);
					}

					// Show missing http connection error.
					if ( isset( $option['notices']['http_error'] ) ) {
						add_action(
							( HELLOPACK_CLIENT_NETWORK_ACTIVATED ? 'network_' : '' ) . 'admin_notices',
							array(
								$this,
								'render_error_http',
							)
						);
					}

					// Update the saved data so the notice disappears on the next page load.
					unset( $option['notices'] );

					hellopack_client()->set_options( $option );
				}
			}
		}

		/**
		 * Set the API values.
		 *
		 * @since 2.0.0
		 */
		public function set_items() {
			if ( $this->are_we_on_settings_page() ) {
				hellopack_client()->items()->set_themes();
				hellopack_client()->items()->set_plugins();
			}
		}

		/**
		 * Check if we're on the settings page.
		 *
		 * @since 2.0.0
		 * @access private
		 */
		private function are_we_on_settings_page() {
			return 'toplevel_page_' . hellopack_client()->get_slug() === get_current_screen()->id || 'toplevel_page_' . hellopack_client()->get_slug() . '-network' === get_current_screen()->id;
		}

		/**
		 * Check for authorization and redirect.
		 *
		 * @since 2.0.0
		 * @access private
		 * @codeCoverageIgnore
		 */
		private function authorization_redirect() {
			self::authorization();
			$base_url = esc_url( hellopack_client()->get_page_url() );
			wp_safe_redirect( $base_url . '&tab=settings' );
			exit;
		}

		/**
		 * Set the HelloPack API authorization value.
		 *
		 * @since 2.0.0
		 */
		public function authorization() {
			// Get the option array.
			$option            = hellopack_client()->get_options();
			$option['notices'] = array();

			// Check for global token.
			if ( hellopack_client()->get_option( 'token' ) || hellopack_client()->api()->token ) {

				$notice      = 'success';
				$scope_check = $this->authorize_token_permissions();

				if ( 'http_error' === $scope_check ) {
					$notice = 'http_error';
				} elseif ( 'error' === $this->authorize_total_items() || 'error' === $scope_check ) {
					$notice = 'error';
				} else {
					if ( 'missing-permissions' == $scope_check ) {
						$notice = 'error-permissions';
					} elseif ( 'too-many-permissions' === $scope_check ) {
						$notice = 'error-permissions';
					} else {
						$themes_notice  = $this->authorize_themes();
						$plugins_notice = $this->authorize_plugins();
						if ( 'error' === $themes_notice || 'error' === $plugins_notice ) {
							$notice = 'error';
						} elseif ( 'success-no-themes' === $themes_notice && 'success-no-plugins' === $plugins_notice ) {
							$notice = 'success-no-items';
						}
					}
				}
				$option['notices'][ $notice ] = true;
			}

			// Check for single-use token.
			if ( ! empty( $option['items'] ) ) {
				$failed = false;

				foreach ( $option['items'] as $key => $item ) {
					if ( empty( $item['name'] ) || empty( $item['token'] ) || empty( $item['id'] ) || empty( $item['type'] ) || empty( $item['authorized'] ) ) {
						continue;
					}

					$request_args = array(
						'headers' => array(
							'Authorization' => 'Bearer ' . $item['token'],
						),
					);

					// Uncached API response with single-use token.
					$response = hellopack_client()->api()->item( $item['id'], $request_args );

					if ( ! is_wp_error( $response ) && isset( $response['id'] ) ) {
						$option['items'][ $key ]['authorized'] = 'success';
					} else {
						if ( is_wp_error( $response ) ) {
							$this->store_additional_error_debug_information( 'Unable to query single item ID ' . $item['id'], $response->get_error_message(), $response->get_error_data() );
						}
						$failed                                = true;
						$option['items'][ $key ]['authorized'] = 'failed';
					}
				}

				if ( true === $failed ) {
					$option['notices']['error-single-use'] = true;
				} else {
					$option['notices']['success-single-use'] = true;
				}
			}

			// Set the option array.
			if ( ! empty( $option['notices'] ) ) {
				hellopack_client()->set_options( $option );
			}
		}

		/**
		 * Check that themes are authorized.
		 *
		 * @return bool
		 * @since 2.0.0
		 */
		public function authorize_total_items() {
			$domain   = hellopack_client()->get_hellopack_api_domain();
			$path     = hellopack_client()->api()->api_path_for( 'total-items' );
			$url      = $domain . $path;
			$response = hellopack_client()->api()->request( $url );
			$notice   = 'success';

			if ( is_wp_error( $response ) ) {
				$notice = 'error';
				// TODO: Add error message to debug information.
				$this->store_additional_error_debug_information( __( 'Failed to query total number of items in API response. A common issue is incorrectly entering the wrong domain during API key creation. Please verify that you have entered it correctly. You can see the correct domain under the "Enter the following domain name" section', 'hellopack-client' ), /* $response->get_error_message(), $response->get_error_data() */ );
			} elseif ( ! isset( $response['total-items'] ) ) {
				$notice = 'error';
				$this->store_additional_error_debug_information( 'Incorrect response from API when querying total items' );
			}

			return $notice;
		}


		/**
		 * Get the required API permissions for this plugin to work.
		 *
		 * @single 2.0.1
		 *
		 * @return array
		 */
		public function get_required_permissions() {
			return apply_filters(
				'hellopack_client_required_permissions',
				array(
					'default'           => 'View and search HelloPack sites',
					'purchase:download' => 'Download your purchased items',
					'purchase:list'     => 'List purchases you\'ve made',
				)
			);
		}

		/**
		 * Return the URL a user needs to click to generate a personal token.
		 *
		 * @single 2.0.1
		 *
		 * @return string The full URL to request a token.
		 */
		public function get_generate_token_url() {
			return 'https://hellowp.io/hu/helloconsole/hellopack-kozpont/api-creator/?' . implode(
				'&',
				array_map(
					function ( $val ) {
							return $val . '=t';
					},
					array_keys( $this->get_required_permissions() )
				)
			);
		}

		/**
		 * Check that themes are authorized.
		 *
		 * @return bool
		 * @since 2.0.0
		 */
		public function authorize_token_permissions() {
			if ( defined( 'HELLOPACK_LOCAL_DEVELOPMENT' ) ) {
				return 'success';
			}
			$notice   = 'success';
			$response = hellopack_client()->api()->request( 'https://api.v2.wp-json.app/v2/apicheck/' );

			if ( is_wp_error( $response ) && ( $response->get_error_code() === 'http_error' || $response->get_error_code() == 500 ) ) {
				$this->store_additional_error_debug_information( 'An error occured checking token permissions', $response->get_error_message(), $response->get_error_data() );
				$notice = 'http_error';
			} elseif ( is_wp_error( $response ) || ! isset( $response['scopes'] ) || ! is_array( $response['scopes'] ) ) {
				$this->store_additional_error_debug_information( 'No scopes found in API response message', $response->get_error_message(), $response->get_error_data() );
				$notice = 'error';
			} else {

				$minimum_scopes = $this->get_required_permissions();
				$maximum_scopes = array( 'default' => 'Default' ) + $minimum_scopes;

				foreach ( $minimum_scopes as $required_scope => $required_scope_name ) {
					if ( ! in_array( $required_scope, $response['scopes'] ) ) {
						// The scope minimum required scope doesn't exist.
						$this->store_additional_error_debug_information( 'Could not find required API permission scope in output.', $required_scope );
						$notice = 'missing-permissions';
					}
				}
				foreach ( $response['scopes'] as $scope ) {
					if ( ! isset( $maximum_scopes[ $scope ] ) ) {
						// The available scope is outside our maximum bounds.
						$this->store_additional_error_debug_information( 'Found too many permissions on token.', $scope );
						$notice = 'too-many-permissions';
					}
				}
			}

			return $notice;
		}

		/**
		 * Check that themes or plugins are authorized and downloadable.
		 *
		 * @param string $type The filter type, either 'themes' or 'plugins'. Default 'themes'.
		 *
		 * @return bool|null
		 * @since 2.0.0
		 */
		public function authorize_items( $type = 'themes' ) {
			$domain   = hellopack_client()->get_hellopack_api_domain();
			$path     = hellopack_client()->api()->api_path_for( 'list-purchases' );
			$api_url  = $domain . $path . 'wordpress-' . $type;
			$response = hellopack_client()->api()->request( $api_url );
			$notice   = 'success';

			if ( is_wp_error( $response ) ) {
				$notice = 'error';
				$this->store_additional_error_debug_information( 'Error listing buyer purchases.', $response->get_error_message(), $response->get_error_data() );
			} elseif ( empty( $response ) ) {
				$notice = 'error';
				$this->store_additional_error_debug_information( 'Empty API result listing buyer purchases' );
			} elseif ( empty( $response['results'] ) ) {
				$notice = 'success-no-' . $type;
			} else {
				shuffle( $response['results'] );
				$item = array_shift( $response['results'] );
				if ( ! isset( $item['item']['id'] ) || ! hellopack_client()->api()->download( $item['item']['id'] ) ) {
					$this->store_additional_error_debug_information( 'Failed to find the correct item format in API response' );
					$notice = 'error';
				}
			}

			return $notice;
		}

		/**
		 * Check that themes are authorized.
		 *
		 * @return bool
		 * @since 2.0.0
		 */
		public function authorize_themes() {
			return $this->authorize_items( 'themes' );
		}

		/**
		 * Check that plugins are authorized.
		 *
		 * @return bool
		 * @since 2.0.0
		 */
		public function authorize_plugins() {
			return $this->authorize_items( 'plugins' );
		}

		/**
		 * Install plugin.
		 *
		 * @param string $plugin The plugin item ID.
		 *
		 * @since 2.0.0
		 * @codeCoverageIgnore
		 */
		public function install_plugin( $plugin ) {
			if ( ! current_user_can( 'install_plugins' ) ) {
				$msg = '
				<div class="wrap">
					<h1>' . __( 'Installing Plugin...', 'hellopack-client' ) . '</h1>
					<p>' . __( 'You do not have sufficient permissions to install plugins on this site.', 'hellopack-client' ) . '</p>
					<a href="' . esc_url( 'admin.php?page=' . hellopack_client()->get_slug() . '&tab=plugins' ) . '">' . __( 'Return to Plugin Installer', 'hellopack-client' ) . '</a>
				</div>';
				wp_die( $msg );
			}

			check_admin_referer( 'install-plugin_' . $plugin );

			hellopack_client()->items()->set_plugins( true );
			$install = hellopack_client()->items()->plugins( 'install' );
			$api     = new stdClass();

			foreach ( $install as $value ) {
				if ( absint( $value['id'] ) === absint( $plugin ) ) {
					$api->name    = $value['name'];
					$api->version = $value['version'];
				}
			}

			$array_api = (array) $api;

			if ( empty( $array_api ) ) {
				$msg = '
				<div class="wrap">
					<h1>' . __( 'Installing Plugin...', 'hellopack-client' ) . '</h1>
					<p>' . __( 'An error occurred, please check that the item ID is correct.', 'hellopack-client' ) . '</p>
					<a href="' . esc_url( 'admin.php?page=' . hellopack_client()->get_slug() . '&tab=plugins' ) . '">' . __( 'Return to Plugin Installer', 'hellopack-client' ) . '</a>
				</div>';
				wp_die( $msg );
			}

			/* translators: %s is the plugin name and version */
			$title              = sprintf( __( 'Installing Plugin: %s', 'hellopack-client' ), esc_html( $api->name . ' ' . $api->version ) );
			$nonce              = 'install-plugin_' . $plugin;
			$url                = 'admin.php?page=' . hellopack_client()->get_slug() . '&action=install-plugin&plugin=' . urlencode( $plugin );
			$type               = 'web'; // Install plugin type, From Web or an Upload.
			$api->download_link = hellopack_client()->api()->download( $plugin, $this->set_bearer_args( $plugin ) );

			// Must have the upgrader & skin.
			require hellopack_client()->get_plugin_path() . '/inc/admin/class-hellopack-client-theme-upgrader.php';
			require hellopack_client()->get_plugin_path() . '/inc/admin/class-hellopack-client-theme-installer-skin.php';

			$upgrader = new HelloPack_Client_Plugin_Upgrader( new HelloPack_Client_Plugin_Installer_Skin( compact( 'title', 'url', 'nonce', 'plugin', 'api' ) ) );
			$upgrader->install( $api->download_link );
		}

		/**
		 * Install theme.
		 *
		 * @param string $theme The theme item ID.
		 *
		 * @since 2.0.0
		 * @codeCoverageIgnore
		 */
		public function install_theme( $theme ) {
			if ( ! current_user_can( 'install_themes' ) ) {
				$msg = '
				<div class="wrap">
					<h1>' . __( 'Installing Theme...', 'hellopack-client' ) . '</h1>
					<p>' . __( 'You do not have sufficient permissions to install themes on this site.', 'hellopack-client' ) . '</p>
					<a href="' . esc_url( 'admin.php?page=' . hellopack_client()->get_slug() . '&tab=themes' ) . '">' . __( 'Return to Theme Installer', 'hellopack-client' ) . '</a>
				</div>';
				wp_die( $msg );
			}

			check_admin_referer( 'install-theme_' . $theme );

			hellopack_client()->items()->set_themes( true );
			$install = hellopack_client()->items()->themes( 'install' );
			$api     = new stdClass();

			foreach ( $install as $value ) {
				if ( absint( $value['id'] ) === absint( $theme ) ) {
					$api->name    = $value['name'];
					$api->version = $value['version'];
				}
			}

			$array_api = (array) $api;

			if ( empty( $array_api ) ) {
				$msg = '
				<div class="wrap">
					<h1>' . __( 'Installing Theme...', 'hellopack-client' ) . '</h1>
					<p>' . __( 'An error occurred, please check that the item ID is correct.', 'hellopack-client' ) . '</p>
					<a href="' . esc_url( 'admin.php?page=' . hellopack_client()->get_slug() . '&tab=themes' ) . '">' . __( 'Return to Plugin Installer', 'hellopack-client' ) . '</a>
				</div>';
				wp_die( $msg );
			}

			wp_enqueue_script( 'customize-loader' );
			/* translators: %s is the theme name and version */
			$title              = sprintf( __( 'Installing Theme: %s', 'hellopack-client' ), esc_html( $api->name . ' ' . $api->version ) );
			$nonce              = 'install-theme_' . $theme;
			$url                = 'admin.php?page=' . hellopack_client()->get_slug() . '&action=install-theme&theme=' . urlencode( $theme );
			$type               = 'web'; // Install theme type, From Web or an Upload.
			$api->download_link = hellopack_client()->api()->download( $theme, $this->set_bearer_args( $theme ) );

			// Must have the upgrader & skin.
			require_once hellopack_client()->get_plugin_path() . '/inc/admin/class-hellopack-client-theme-upgrader.php';
			require_once hellopack_client()->get_plugin_path() . '/inc/admin/class-hellopack-client-theme-installer-skin.php';

			$upgrader = new HelloPack_Client_Theme_Upgrader( new HelloPack_Client_Theme_Installer_Skin( compact( 'title', 'url', 'nonce', 'api' ) ) );
			$upgrader->install( $api->download_link );
		}

		/**
		 * AJAX handler for adding items that use a non global token.
		 *
		 * @since 2.0.0
		 * @codeCoverageIgnore
		 */
		public function ajax_add_item() {
			if ( ! check_ajax_referer( self::AJAX_ACTION, 'nonce', false ) ) {
				status_header( 400 );
				wp_send_json_error( 'bad_nonce' );
			} elseif ( 'POST' !== $_SERVER['REQUEST_METHOD'] ) {
				status_header( 405 );
				wp_send_json_error( 'bad_method' );
			} elseif ( empty( $_POST['token'] ) ) {
				wp_send_json_error( array( 'message' => __( 'The Token is missing.', 'hellopack-client' ) ) );
			} elseif ( empty( $_POST['id'] ) ) {
				wp_send_json_error( array( 'message' => __( 'The Item ID is missing.', 'hellopack-client' ) ) );
			}

			$args = array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $_POST['token'],
				),
			);

			$request = hellopack_client()->api()->item( $_POST['id'], $args );
			if ( false === $request ) {
				wp_send_json_error( array( 'message' => __( 'The Token or Item ID is incorrect.', 'hellopack-client' ) ) );
			}

			if ( false === hellopack_client()->api()->download( $_POST['id'], $args ) ) {
				wp_send_json_error( array( 'message' => __( 'The item cannot be downloaded.', 'hellopack-client' ) ) );
			}

			if ( isset( $request['number_of_sales'] ) ) {
				$type = 'plugin';
			} else {
				$type = 'theme';
			}

			if ( isset( $type ) ) {
				$response = array(
					'name'       => $request['name'],
					'token'      => $_POST['token'],
					'id'         => $_POST['id'],
					'type'       => $type,
					'authorized' => 'success',
				);

				$options = get_option( hellopack_client()->get_option_name(), array() );

				if ( ! empty( $options['items'] ) ) {
					$options['items'] = array_values( $options['items'] );
					$key              = count( $options['items'] );
				} else {
					$options['items'] = array();
					$key              = 0;
				}

				$options['items'][] = $response;

				hellopack_client()->set_options( $options );

				// Rebuild the theme cache.
				if ( 'theme' === $type ) {
					hellopack_client()->items()->set_themes( true, false );

					$install_link = add_query_arg(
						array(
							'page'   => hellopack_client()->get_slug(),
							'action' => 'install-theme',
							'id'     => $_POST['id'],
						),
						self_admin_url( 'admin.php' )
					);

					$request['install'] = wp_nonce_url( $install_link, 'install-theme_' . $_POST['id'] );
				}

				// Rebuild the plugin cache.
				if ( 'plugin' === $type ) {
					hellopack_client()->items()->set_plugins( true, false );

					$install_link = add_query_arg(
						array(
							'page'   => hellopack_client()->get_slug(),
							'action' => 'install-plugin',
							'id'     => $_POST['id'],
						),
						self_admin_url( 'admin.php' )
					);

					$request['install'] = wp_nonce_url( $install_link, 'install-plugin_' . $_POST['id'] );
				}

				$response['key']  = $key;
				$response['item'] = $request;
				wp_send_json_success( $response );
			}

			wp_send_json_error( array( 'message' => __( 'An unknown error occurred.', 'hellopack-client' ) ) );
		}

		/**
		 * AJAX handler for removing items that use a non global token.
		 *
		 * @since 2.0.0
		 * @codeCoverageIgnore
		 */
		public function ajax_remove_item() {
			if ( ! check_ajax_referer( self::AJAX_ACTION, 'nonce', false ) ) {
				status_header( 400 );
				wp_send_json_error( 'bad_nonce' );
			} elseif ( 'POST' !== $_SERVER['REQUEST_METHOD'] ) {
				status_header( 405 );
				wp_send_json_error( 'bad_method' );
			} elseif ( empty( $_POST['id'] ) ) {
				wp_send_json_error( array( 'message' => __( 'The Item ID is missing.', 'hellopack-client' ) ) );
			}

			$options = get_option( hellopack_client()->get_option_name(), array() );
			$type    = '';

			foreach ( $options['items'] as $key => $item ) {
				if ( $item['id'] === $_POST['id'] ) {
					$type = $item['type'];
					unset( $options['items'][ $key ] );
					break;
				}
			}
			$options['items'] = array_values( $options['items'] );

			hellopack_client()->set_options( $options );

			// Rebuild the theme cache.
			if ( 'theme' === $type ) {
				hellopack_client()->items()->set_themes( true, false );
			}

			// Rebuild the plugin cache.
			if ( 'plugin' === $type ) {
				hellopack_client()->items()->set_plugins( true, false );
			}

			wp_send_json_success();
		}

		/**
		 * AJAX handler for performing a healthcheck of the current website.
		 *
		 * @since 2.0.6
		 * @codeCoverageIgnore
		 */
		public function ajax_healthcheck() {
			if ( ! check_ajax_referer( self::AJAX_ACTION, 'nonce', false ) ) {
				status_header( 400 );
				wp_send_json_error( 'bad_nonce' );
			} elseif ( 'POST' !== $_SERVER['REQUEST_METHOD'] ) {
				status_header( 405 );
				wp_send_json_error( 'bad_method' );
			}

			$limits = $this->get_server_limits();

			wp_send_json_success(
				array(
					'limits' => $limits,
				)
			);
		}

		/**
		 * AJAX handler for performing a healthcheck of the current website.
		 *
		 * @since 2.0.6
		 * @codeCoverageIgnore
		 */
		public function get_server_limits() {
			$limits = array();

			if ( ! is_plugin_active( 'elementor-pro/elementor-pro.php' ) ) {

				// Check memory limit is > 256 M

				try {
					$memory_limit         = wp_convert_hr_to_bytes( ini_get( 'memory_limit' ) );
					$memory_limit_desired = 256;
					$memory_limit_ok      = $memory_limit < 0 || $memory_limit >= $memory_limit_desired * 1024 * 1024;
					$memory_limit_in_mb   = $memory_limit < 0 ? __( 'Unlimited', 'hellopack-client' ) : floor( $memory_limit / ( 1024 * 1024 ) ) . 'M';

					$limits['memory_limit'] = array(
						'title'   => __( 'PHP Memory Limit:', 'hellopack-client' ),
						'ok'      => $memory_limit_ok,
						'message' => $memory_limit_ok ? sprintf(
							__( 'is ok at %s.', 'hellopack-client' ),
							$memory_limit_in_mb
						) : sprintf(
							// translators: %1$s is the desired memory limit in MB, %2$s is the current memory limit in MB.
							__( '%s may be too small. If you are having issues please set your PHP memory limit to at least 256M - or ask your hosting provider to do this if you\'re unsure.', 'hellopack-client' ),
							$memory_limit_in_mb
						),
					);
				} catch ( \Exception $e ) {
					$limits['memory_limit'] = array(
						'title'   => __( 'PHP Memory Limit:', 'hellopack-client' ),
						'ok'      => false,
						'message' => __( 'Failed to check memory limit. If you are having issues please ask hosting provider to raise the memory limit for you.', 'hellopack-client' ),
					);
				}
			} else {

				// Check memory limit is > 1024 M

				try {
					$memory_limit         = wp_convert_hr_to_bytes( ini_get( 'memory_limit' ) );
					$memory_limit_desired = 1024;
					$memory_limit_ok      = $memory_limit < 0 || $memory_limit >= $memory_limit_desired * 1024 * 1024;
					$memory_limit_in_mb   = $memory_limit < 0 ? __( 'Unlimited', 'hellopack-client' ) : floor( $memory_limit / ( 1024 * 1024 ) ) . 'M';

					$limits['memory_limit'] = array(
						'title'   => __( 'PHP Memory Limit:', 'hellopack-client' ),
						'ok'      => $memory_limit_ok,
						// translators: %1$s is the desired memory limit in MB, %2$s is the current memory limit in MB.
						'message' => $memory_limit_ok ? sprintf( __( 'is ok at %s.', 'hellopack-client' ), $memory_limit_in_mb ) : sprintf( __( '%1$s may be enough, however, for proper functioning of Elementor Pro, a minimum of %2$sM memory limit is recommended. If you are having issues please set your PHP memory limit to at least %3$s - or ask your hosting provider to do this if you\'re unsure.', 'hellopack-client' ), $memory_limit_in_mb, $memory_limit_desired, $memory_limit_desired ),

					);
				} catch ( \Exception $e ) {
					$limits['memory_limit'] = array(
						'title'   => __( 'PHP Memory Limit:', 'hellopack-client' ),
						'ok'      => false,
						'message' => __( 'Failed to check memory limit. If you are having issues please ask hosting provider to raise the memory limit for you.', 'hellopack-client' ),
					);
				}
			}

			// Check upload size.
			try {
				$upload_size_desired = 80;

				$upload_max_filesize       = wp_max_upload_size();
				$upload_max_filesize_ok    = $upload_max_filesize < 0 || $upload_max_filesize >= $upload_size_desired * 1024 * 1024;
				$upload_max_filesize_in_mb = $upload_max_filesize < 0 ? __( 'Unlimited', 'hellopack-client' ) : floor( $upload_max_filesize / ( 1024 * 1024 ) ) . 'M';

				$limits['upload'] = array(
					'ok'      => $upload_max_filesize_ok,
					'title'   => __( 'PHP Upload Limits:', 'hellopack-client' ),
					// translators: %1$s is the current upload limit in MB, %2$s is the desired upload limit in MB.
					'message' => $upload_max_filesize_ok ? sprintf( __( 'is ok at %s.', 'hellopack-client' ), $upload_max_filesize_in_mb ) : sprintf( __( '%1$s may be too small. If you are having issues please set your PHP upload limits to at least %2$sM - or ask your hosting provider to do this if you\'re unsure.', 'hellopack-client' ), $upload_max_filesize_in_mb, $upload_size_desired ),
				);
			} catch ( \Exception $e ) {
				$limits['upload'] = array(
					'title'   => __( 'PHP Upload Limits:', 'hellopack-client' ),
					'ok'      => false,
					'message' => __( 'Failed to check upload limit. If you are having issues please ask hosting provider to raise the upload limit for you.', 'hellopack-client' ),
				);
			}

			// Check max_input_vars.
			try {
				$max_input_vars         = ini_get( 'max_input_vars' );
				$max_input_vars_desired = 10000;
				$max_input_vars_ok      = $max_input_vars < 0 || $max_input_vars >= $max_input_vars_desired;

				$limits['max_input_vars'] = array(
					'ok'      => $max_input_vars_ok,
					'title'   => __( 'PHP Max Input Vars:', 'hellopack-client' ),
					'message' => $max_input_vars_ok ? sprintf(
						// translators: %s is the current input vars.
						__( 'is ok at %s.', 'hellopack-client' ),
						$max_input_vars
					) : sprintf(
							// translators: %1$s is the current input vars, %2$s is the desired input vars.
						__( '%1$s may be too small. If you are having issues please set your PHP max input vars to at least %2$s - or ask your hosting provider to do this if you\'re unsure.', 'hellopack-client' ),
						$max_input_vars,
						$max_input_vars_desired
					),
				);
			} catch ( \Exception $e ) {
				$limits['max_input_vars'] = array(
					'title'   => __( 'PHP Max Input Vars:', 'hellopack-client' ),
					'ok'      => false,
					'message' => __( 'Failed to check input vars limit. If you are having issues please ask hosting provider to raise the input vars limit for you.', 'hellopack-client' ),
				);
			}

			// Check max_execution_time.
			try {
				$max_execution_time         = ini_get( 'max_execution_time' );
				$max_execution_time_desired = 60;
				$max_execution_time_ok      = $max_execution_time <= 0 || $max_execution_time >= $max_execution_time_desired;

				$limits['max_execution_time'] = array(
					'ok'      => $max_execution_time_ok,
					'title'   => __( 'PHP Execution Time:', 'hellopack-client' ),
					'message' => $max_execution_time_ok ? sprintf(
						// translators: %s is the current execution time in seconds.
						__( 'PHP execution time limit is ok at %s seconds.', 'hellopack-client' ),
						$max_execution_time
					) : sprintf(
						// translators: %1$s is the current execution time in seconds, %2$s is the desired execution time in seconds.
						__( '%1$s seconds is too small. Please set your PHP max execution time to at least %2$s seconds - or ask your hosting provider to do this if you\'re unsure.', 'hellopack-client' ),
						$max_execution_time,
						$max_execution_time_desired
					),
				);
			} catch ( \Exception $e ) {
				$limits['max_execution_time'] = array(
					'title'   => __( 'PHP Execution Time:', 'hellopack-client' ),
					'ok'      => false,
					'message' => __( 'Failed to check PHP execution time limit. Please ask hosting provider to raise this limit for you.', 'hellopack-client' ),
				);
			}

			// Additional checks and their translations would follow a similar pattern.

			// Check various hostname connectivity.
			$hosts_to_check = array(
				array(
					'hostname' => 'api.v2.wp-json.app',
					'url'      => 'https://api.v2.wp-json.app/v2/hellopack-client/',
					'title'    => __( 'Plugin Update API', 'hellopack-client' ),
				),
				array(
					'hostname' => 'hellowp.io',
					'url'      => 'https://hellowp.io/hu/robots.txt',
					'title'    => __( 'HelloWP API', 'hellopack-client' ),
				),
				array(
					'hostname' => 'api.v2.wp-json.app',
					'url'      => 'https://api.v2.wp-json.app/v2/ping/',
					'title'    => __( 'HelloPack Client API', 'hellopack-client' ),
				),
				array(
					'hostname' => 'hellopack.wp-json.app',
					'url'      => 'https://hellopack.wp-json.app/wp-json/helloup/v2/schema/',
					'title'    => __( 'HelloPack Download API', 'hellopack-client' ),
				),
				array(
					'hostname' => 'api.wp-json.app',
					'url'      => 'https://api.wp-json.app/',
					'title'    => __( 'HelloPack Activation API', 'hellopack-client' ),
				),
			);

			foreach ( $hosts_to_check as $host ) {
				try {
					$response      = wp_remote_get(
						$host['url'],
						array(
							'user-agent' => 'WordPress - HelloPack Client ' . hellopack_client()->get_version(),
							'timeout'    => 5,
						)
					);
					$response_code = wp_remote_retrieve_response_code( $response );
					if ( $response && ! is_wp_error( $response ) && $response_code === 200 ) {
							$limits[ $host['hostname'] ] = array(
								'ok'      => true,
								'title'   => $host['title'] . ': ',
								'message' => __( 'Connected ok.', 'hellopack-client' ),
							);
					} else {
						$limits[ $host['hostname'] ] = array(
							'ok'      => false,
							'title'   => $host['title'] . ': ',
							'message' => sprintf(
								// translators: %1$s is the response code, %2$s is the host name, %3$s is the error message.
								__( "Connection failed. Status '%1\$s'. Please ensure PHP is allowed to connect to the host '%2\$s' - or ask your hosting provider to do this if you’re unsure. %3\$s", 'hellopack-client' ),
								$response_code,
								$host['hostname'],
								( is_wp_error( $response ) ? $response->get_error_message() : '' )
							),
						);
					}
				} catch ( \Exception $e ) {
					$limits[ $host['hostname'] ] = array(
						'ok'      => false,
						'title'   => $host['title'] . ': ',
						'message' => sprintf(
							// translators: %1$s is the host name, %2$s is the error message.
							__( "Connection failed. Please contact the hosting provider and ensure PHP is allowed to connect to the host '%1\$s'. %2\$s", 'hellopack-client' ),
							$host['hostname'],
							$e->getMessage()
						),
					);
				}
			}

			// Check authenticated API request
			if ( ! defined( 'HELLOPACK_LOCAL_DEVELOPMENT' ) ) {
				$response = hellopack_client()->api()->request( 'https://api.v2.wp-json.app/v2/apicheck/' );

				if ( is_wp_error( $response ) ) {
					$limits['authentication'] = array(
						'ok'      => false,
						'title'   => __( 'HelloPack API Authentication:', 'hellopack-client' ),
						'message' => __( 'Not currently authenticated with the HelloPack API. Please add your API token.', 'hellopack-client' ) . ' ' . $response->get_error_message(),
					);
				} elseif ( ! isset( $response['scopes'] ) ) {
					$limits['authentication'] = array(
						'ok'      => false,
						'title'   => __( 'HelloPack API Authentication:', 'hellopack-client' ),
						'message' => __( 'Missing API permissions. Please re-create your HelloPack API token with the correct permissions.', 'hellopack-client' ),
					);
				} else {
					$minimum_scopes    = $this->get_required_permissions();
					$maximum_scopes    = array( 'default' => __( 'Default', 'hellopack-client' ) ) + $minimum_scopes;
					$missing_scopes    = array();
					$additional_scopes = array();
					foreach ( $minimum_scopes as $required_scope => $required_scope_name ) {
						if ( ! in_array( $required_scope, $response['scopes'] ) ) {
							// The scope minimum required scope doesn't exist.
							$missing_scopes [] = $required_scope;
						}
					}
					foreach ( $response['scopes'] as $scope ) {
						if ( ! isset( $maximum_scopes[ $scope ] ) ) {
							// The available scope is outside our maximum bounds.
							$additional_scopes [] = $scope;
						}
					}
					$limits['authentication'] = array(
						'ok'      => true,
						'title'   => __( 'HelloPack API Authentication:', 'hellopack-client' ),
						'message' => __( 'Authenticated successfully', 'hellopack-client' ),
					);
				}
			}

			// Additional checks and their translations would follow a similar pattern.

			$debug_enabled      = defined( 'WP_DEBUG' ) && WP_DEBUG;
			$limits['wp_debug'] = array(
				'ok'      => ! $debug_enabled,
				'title'   => __( 'WP Debug:', 'hellopack-client' ),
				'message' => $debug_enabled ? __( 'If you’re on a production website, it’s best to set WP_DEBUG to false, please ask your hosting provider to do this if you’re unsure.', 'hellopack-client' ) : __( 'WP Debug is disabled, all ok.', 'hellopack-client' ),
			);

			$zip_archive_installed = class_exists( '\ZipArchive' );
			$limits['zip_archive'] = array(
				'ok'      => $zip_archive_installed,
				'title'   => __( 'ZipArchive Support:', 'hellopack-client' ),
				'message' => $zip_archive_installed ? __( 'ZipArchive is available.', 'hellopack-client' ) : __( 'ZipArchive is not available. If you have issues installing or updating items please ask your hosting provider to enable ZipArchive.', 'hellopack-client' ),
			);

			$php_version_ok        = version_compare( PHP_VERSION, '7.4', '>=' );
			$limits['php_version'] = array(
				'ok'      => $php_version_ok,
				'title'   => __( 'PHP Version:', 'hellopack-client' ),
				'message' => $php_version_ok ? sprintf(
					// translators: %s is the current PHP version.
					__( 'PHP version is ok at %s.', 'hellopack-client' ),
					PHP_VERSION
				) :
				// translators: %s is the current PHP version.
				__( 'Please ask the hosting provider to upgrade your PHP version to at least 7.4 or above.', 'hellopack-client' ),
			);

			require_once ABSPATH . 'wp-admin/includes/file.php';
			$current_filesystem_method = get_filesystem_method();
			if ( $current_filesystem_method !== 'direct' ) {
				$limits['filesystem_method'] = array(
					'ok'      => false,
					'title'   => __( 'WordPress Filesystem:', 'hellopack-client' ),
					'message' => __( 'Please enable WordPress FS_METHOD direct - or ask your hosting provider to do this if you’re unsure.', 'hellopack-client' ),
				);
			}

			$wp_upload_dir                 = wp_upload_dir();
			$upload_base_dir               = $wp_upload_dir['basedir'];
			$upload_base_dir_writable      = is_writable( $upload_base_dir );
			$limits['wp_content_writable'] = array(
				'ok'      => $upload_base_dir_writable,
				'title'   => __( 'WordPress File Permissions:', 'hellopack-client' ),
				'message' => $upload_base_dir_writable ? __( 'is ok.', 'hellopack-client' ) : __( 'Please set correct WordPress PHP write permissions for the wp-content directory - or ask your hosting provider to do this if you’re unsure.', 'hellopack-client' ),
			);

			$active_plugins    = get_option( 'active_plugins' );
			$active_plugins_ok = count( $active_plugins ) < 15;
			if ( ! $active_plugins_ok ) {
				$limits['active_plugins'] = array(
					'ok'      => false,
					'title'   => __( 'Active Plugins:', 'hellopack-client' ),
					'message' => __( 'Please try to reduce the number of active plugins on your WordPress site, as this will slow things down.', 'hellopack-client' ),
				);
			}

			return $limits;
		}


		/**
		 * Admin page callback.
		 *
		 * @since 2.0.0
		 */
		public function render_admin_callback() {
			require hellopack_client()->get_plugin_path() . 'inc/admin/view/callback/admin.php';
		}

		/**
		 * OAuth section callback.
		 *
		 * @since 2.0.0
		 */
		public function render_oauth_section_callback() {
			require hellopack_client()->get_plugin_path() . 'inc/admin/view/callback/section/oauth.php';
		}

		/**
		 * Items section callback.
		 *
		 * @since 2.0.0
		 */
		public function render_items_section_callback() {
			require hellopack_client()->get_plugin_path() . 'inc/admin/view/callback/section/items.php';
		}

		/**
		 * Token setting callback.
		 *
		 * @since 2.0.0
		 */
		public function render_token_setting_callback() {

			require hellopack_client()->get_plugin_path() . 'inc/admin/view/callback/setting/token.php';
		}

		/**
		 * Items setting callback.
		 *
		 * @since 2.0.0
		 */
		public function render_items_setting_callback() {
			require hellopack_client()->get_plugin_path() . 'inc/admin/view/callback/setting/items.php';
		}

		/**
		 * Intro
		 *
		 * @since 2.0.0
		 */
		public function render_intro_partial() {
			require hellopack_client()->get_plugin_path() . 'inc/admin/view/partials/intro.php';
		}

		/**
		 * Tabs
		 *
		 * @since 2.0.0
		 */
		public function render_tabs_partial() {
			require hellopack_client()->get_plugin_path() . 'inc/admin/view/partials/tabs.php';
		}

		/**
		 * Settings panel
		 *
		 * @since 2.0.0
		 */
		public function render_settings_panel_partial() {
			require hellopack_client()->get_plugin_path() . 'inc/admin/view/partials/settings.php';
		}


		/**
		 * Help panel
		 *
		 * @since 2.0.1
		 */
		public function render_help_panel_partial() {
			require hellopack_client()->get_plugin_path() . 'inc/admin/view/partials/help.php';
		}

		/**
		 * Themes panel
		 *
		 * @since 2.0.0
		 */
		public function render_themes_panel_partial() {
			require hellopack_client()->get_plugin_path() . 'inc/admin/view/partials/themes.php';
		}

		/**
		 * Plugins panel
		 *
		 * @since 2.0.0
		 */
		public function render_plugins_panel_partial() {
			require hellopack_client()->get_plugin_path() . 'inc/admin/view/partials/plugins.php';
		}

		/**
		 * Plugins search panel
		 *
		 * @since 2.0.0
		 */
		public function render_plugin_search_panel_partial() {
			require hellopack_client()->get_plugin_path() . 'inc/admin/view/partials/plugin-search.php';
		}

		/**
		 * Plugins search no result panel
		 *
		 * @since 2.0.0
		 */
		public function render_plugin_search_no_result_partial() {
			require hellopack_client()->get_plugin_path() . 'inc/admin/view/partials/plugin-no-result.php';
		}

		/**
		 * Success notice.
		 *
		 * @since 2.0.0
		 */
		public function render_success_notice() {
			require hellopack_client()->get_plugin_path() . 'inc/admin/view/notice/success.php';
		}

		/**
		 * Success no-items notice.
		 *
		 * @since 2.0.0
		 */
		public function render_success_no_items_notice() {
			require hellopack_client()->get_plugin_path() . 'inc/admin/view/notice/success-no-items.php';
		}

		/**
		 * Success single-use notice.
		 *
		 * @since 2.0.0
		 */
		public function render_success_single_use_notice() {
			require hellopack_client()->get_plugin_path() . 'inc/admin/view/notice/success-single-use.php';
		}

		/**
		 * Error details.
		 *
		 * @since 2.0.2
		 */
		public function render_additional_error_details() {
			$error_details = get_site_transient( hellopack_client()->get_option_name() . '_error_information' );
			if ( $error_details && ! empty( $error_details['title'] ) ) {
				extract( $error_details );
				require hellopack_client()->get_plugin_path() . 'inc/admin/view/notice/error-details.php';
			}
		}

		/**
		 * Error notice.
		 *
		 * @since 2.0.0
		 */
		public function render_error_notice() {
			require hellopack_client()->get_plugin_path() . 'inc/admin/view/notice/error.php';
			$this->render_additional_error_details();
		}

		/**
		 * Permission error notice.
		 *
		 * @since 2.0.1
		 */
		public function render_error_permissions() {
			require hellopack_client()->get_plugin_path() . 'inc/admin/view/notice/error-permissions.php';
			$this->render_additional_error_details();
		}

		/**
		 * Error single-use notice.
		 *
		 * @since 2.0.0
		 */
		public function render_error_single_use_notice() {
			require hellopack_client()->get_plugin_path() . 'inc/admin/view/notice/error-single-use.php';
			$this->render_additional_error_details();
		}

		/**
		 * Error missing zip.
		 *
		 * @since 2.0.1
		 */
		public function render_error_missing_zip() {
			require hellopack_client()->get_plugin_path() . 'inc/admin/view/notice/error-missing-zip.php';
			$this->render_additional_error_details();
		}

		/**
		 * Error http
		 *
		 * @since 2.0.1
		 */
		public function render_error_http() {
			require hellopack_client()->get_plugin_path() . 'inc/admin/view/notice/error-http.php';
			$this->render_additional_error_details();
		}

		/**
		 * Use the Settings API when in network mode.
		 *
		 * This allows us to make use of the same WordPress Settings API when displaying the menu item in network mode.
		 *
		 * @since 2.0.0
		 */
		public function save_network_settings() {
			check_admin_referer( hellopack_client()->get_slug() . '-options' );

			global $new_whitelist_options;
			$options = $new_whitelist_options[ hellopack_client()->get_slug() ];

			foreach ( $options as $option ) {
				if ( isset( $_POST[ $option ] ) ) {
					update_site_option( $option, $_POST[ $option ] );
				} else {
					delete_site_option( $option );
				}
			}
			wp_redirect( hellopack_client()->get_page_url() );
			exit;
		}

		/**
		 * Store additional error information in transient so users can self debug.
		 *
		 * @since 2.0.2
		 */
		public function store_additional_error_debug_information( $title, $message = '', $data = array() ) {
			set_site_transient(
				hellopack_client()->get_option_name() . '_error_information',
				array(
					'title'   => $title,
					'message' => $message,
					'data'    => $data,
				),
				120
			);
		}
	}

endif;