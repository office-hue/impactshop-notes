<?php
/**
 * Upgrader skin classes.
 *
 * @package HelloPack_Client
 */

// Include the WP_Upgrader_Skin class.
if ( ! class_exists( 'WP_Upgrader_Skin', false ) ) :
	include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader-skins.php';
endif;

if ( ! class_exists( 'HelloPack_Client_Theme_Installer_Skin' ) ) :

	/**
	 * Theme Installer Skin.
	 *
	 * @class HelloPack_Client_Theme_Installer_Skin
	 * @version 1.0.0
	 * @since 2.0.0
	 */
	class HelloPack_Client_Theme_Installer_Skin extends Theme_Installer_Skin {

		/**
		 * Modify the install actions.
		 *
		 * @since 2.0.0
		 */
		public function after() {
			if ( empty( $this->upgrader->result['destination_name'] ) ) {
				return;
			}

			$theme_info = $this->upgrader->theme_info();
			if ( empty( $theme_info ) ) {
				return;
			}

			$name       = $theme_info->display( 'Name' );
			$stylesheet = $this->upgrader->result['destination_name'];
			$template   = $theme_info->get_template();

			$activate_link = add_query_arg(
				array(
					'action'     => 'activate',
					'template'   => urlencode( $template ),
					'stylesheet' => urlencode( $stylesheet ),
				),
				admin_url( 'themes.php' )
			);
			$activate_link = wp_nonce_url( $activate_link, 'switch-theme_' . $stylesheet );

			$install_actions = array();

			if ( current_user_can( 'edit_theme_options' ) && current_user_can( 'customize' ) ) {
				// translators: %s: theme name.
				$install_actions['preview'] = '<a href="' . wp_customize_url( $stylesheet ) . '" class="hide-if-no-customize load-customize"><span aria-hidden="true">' . __( 'Live Preview', 'hellopack-client' ) . '</span><span class="screen-reader-text">' . sprintf( __( 'Live Preview &#8220;%s&#8221;', 'hellopack-client' ), $name ) . '</span></a>';
			}

			if ( is_multisite() ) {
				if ( current_user_can( 'manage_network_themes' ) ) {
					$install_actions['network_enable'] = '<a href="' . esc_url( network_admin_url( wp_nonce_url( 'themes.php?action=enable&amp;theme=' . urlencode( $stylesheet ) . '&amp;paged=1&amp;s', 'enable-theme_' . $stylesheet ) ) ) . '" target="_parent">' . __( 'Network Enable', 'hellopack-client' ) . '</a>';
				}
			}
			// translators: %s: theme name.
			$install_actions['activate'] = '<a href="' . esc_url( $activate_link ) . '" class="activatelink"><span aria-hidden="true">' . __( 'Activate', 'hellopack-client' ) . '</span><span class="screen-reader-text">' . sprintf( __( 'Activate &#8220;%s&#8221;', 'hellopack-client' ), $name ) . '</span></a>';

			$install_actions['themes_page'] = '<a href="' . esc_url( admin_url( 'admin.php?page=' . hellopack_client()->get_slug() . '&tab=themes' ) ) . '" target="_parent">' . __( 'Return to Theme Installer', 'hellopack-client' ) . '</a>';

			if ( ! $this->result || is_wp_error( $this->result ) || is_multisite() || ! current_user_can( 'switch_themes' ) ) {
				unset( $install_actions['activate'], $install_actions['preview'] );
			}

			if ( ! empty( $install_actions ) ) {
				$this->feedback( implode( ' | ', $install_actions ) );
			}
		}
	}

endif;

if ( ! class_exists( 'HelloPack_Client_Plugin_Installer_Skin' ) ) :

	/**
	 * Plugin Installer Skin.
	 *
	 * @class HelloPack_Client_Plugin_Installer_Skin
	 * @version 1.0.0
	 * @since 2.0.0
	 */
	class HelloPack_Client_Plugin_Installer_Skin extends Plugin_Installer_Skin {

		/**
		 * Modify the install actions.
		 *
		 * @since 2.0.0
		 */
		public function after() {
			$plugin_file     = $this->upgrader->plugin_info();
			$install_actions = array();

			if ( current_user_can( 'activate_plugins' ) ) {
				$install_actions['activate_plugin'] = '<a href="' . esc_url( wp_nonce_url( 'plugins.php?action=activate&amp;plugin=' . urlencode( $plugin_file ), 'activate-plugin_' . $plugin_file ) ) . '" target="_parent">' . __( 'Activate Plugin', 'hellopack-client' ) . '</a>';
			}

			if ( is_multisite() ) {
				unset( $install_actions['activate_plugin'] );

				if ( current_user_can( 'manage_network_plugins' ) ) {
					$install_actions['network_activate'] = '<a href="' . esc_url( network_admin_url( wp_nonce_url( 'plugins.php?action=activate&amp;plugin=' . urlencode( $plugin_file ), 'activate-plugin_' . $plugin_file ) ) ) . '" target="_parent">' . __( 'Network Activate', 'hellopack-client' ) . '</a>';
				}
			}

			$install_actions['plugins_page'] = '<a href="' . esc_url( admin_url( 'admin.php?page=' . hellopack_client()->get_slug() . '&tab=plugins' ) ) . '" target="_parent">' . __( 'Return to Plugin Installer', 'hellopack-client' ) . '</a>';

			if ( ! $this->result || is_wp_error( $this->result ) ) {
				unset( $install_actions['activate_plugin'], $install_actions['site_activate'], $install_actions['network_activate'] );
			}

			if ( ! empty( $install_actions ) ) {
				$this->feedback( implode( ' | ', $install_actions ) );
			}
		}
	}

endif;
