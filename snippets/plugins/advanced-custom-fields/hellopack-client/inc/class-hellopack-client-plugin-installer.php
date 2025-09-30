<?php
/**
 * HelloPack Plugin Installer.
 * Forked from WooCommerce. Thx to the WooCommerce team. ❤️
 *
 * @package HelloPack_Client
 * @since 2.0.15
 */

if ( ! class_exists( 'HelloPack_Client_Plugin_Installer' ) ) :

	/**
	 * Contains backend logic for the Marketplace feature.
	 */
	class HelloPack_Client_Plugin_Installer {

		const HELLOPACK_MARKETPLACE_TAB_SLUG = 'hellopack';

		/**
		 * Class initialization, to be executed when the class is resolved by the container.
		 */
		final public function init() {
			// TODO: Add disabler for the this feature.

			// Add a Woo Marketplace link to the plugin install action links.
			add_filter( 'install_plugins_tabs', array( $this, 'add_woo_plugin_install_action_link' ) );
			add_action( 'install_plugins_pre_hellopack', array( $this, 'maybe_open_woo_tab' ) );
			add_action( 'admin_print_styles-plugin-install.php', array( $this, 'add_plugins_page_styles' ) );
		}


		/**
		 * Add a Woo Marketplace link to the plugin install action links.
		 *
		 * @param array $tabs Plugins list tabs.
		 * @return array
		 */
		public function add_woo_plugin_install_action_link( $tabs ) {
			$tabs[ self::HELLOPACK_MARKETPLACE_TAB_SLUG ] = 'HelloPack';
			return $tabs;
		}

		/**
		 * Open the Woo tab when the user clicks on the Woo link in the plugin installer.
		 */
		public function maybe_open_woo_tab() {
			// phpcs:disable WordPress.Security.NonceVerification.Recommended
			if ( ! isset( $_GET['tab'] ) || self::HELLOPACK_MARKETPLACE_TAB_SLUG !== $_GET['tab'] ) {
				return;
			}
			// phpcs:enable WordPress.Security.NonceVerification.Recommended

			$woo_url = add_query_arg(
				array(
					'page' => 'hellopack-client',
					'ref'  => 'plugins',
				),
				admin_url( 'admin.php' )
			);

			wp_safe_redirect( $woo_url );
			exit;
		}

		/**
		 * Add styles to the plugin install page.
		 */
		public function add_plugins_page_styles() {
			?>
<style>
.plugin-install-hellopack>a::after {
	content: "";
	display: inline-block;
	background-image: url("data:image/svg+xml,%3Csvg width='16' height='16' viewBox='0 0 16 16' fill='none' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath fill-rule='evenodd' clip-rule='evenodd' d='M8.33321 3H12.9999V7.66667H11.9999V4.70711L8.02009 8.68689L7.31299 7.97978L11.2928 4H8.33321V3Z' fill='%23646970'/%3E%3Cpath d='M6.33333 4.1665H4.33333C3.8731 4.1665 3.5 4.5396 3.5 4.99984V11.6665C3.5 12.1267 3.8731 12.4998 4.33333 12.4998H11C11.4602 12.4998 11.8333 12.1267 11.8333 11.6665V9.6665' stroke='%23646970'/%3E%3C/svg%3E%0A");
	width: 16px;
	height: 16px;
	background-repeat: no-repeat;
	vertical-align: text-top;
	margin-left: 2px;
}

.plugin-install-hellopack:hover>a::after {
	background-image: url("data:image/svg+xml,%3Csvg width='16' height='16' viewBox='0 0 16 16' fill='none' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath fill-rule='evenodd' clip-rule='evenodd' d='M8.33321 3H12.9999V7.66667H11.9999V4.70711L8.02009 8.68689L7.31299 7.97978L11.2928 4H8.33321V3Z' fill='%23135E96'/%3E%3Cpath d='M6.33333 4.1665H4.33333C3.8731 4.1665 3.5 4.5396 3.5 4.99984V11.6665C3.5 12.1267 3.8731 12.4998 4.33333 12.4998H11C11.4602 12.4998 11.8333 12.1267 11.8333 11.6665V9.6665' stroke='%23135E96'/%3E%3C/svg%3E%0A");
}
</style>
			<?php
		}
	}

endif;

if ( ! function_exists( 'hellopack_marketplace_init' ) ) {
	/**
	 * Initialize the Marketplace feature.
	 */
	function hellopack_marketplace_init() {
		if ( class_exists( 'HelloPack_Client_Plugin_Installer' ) ) {
			$hellopack_marketplace = new HelloPack_Client_Plugin_Installer();
			$hellopack_marketplace->init();
		}
	}

	add_action( 'plugins_loaded', 'hellopack_marketplace_init' );
}
