<?php
/**
 * Theme Upgrader class.
 *
 * @package HelloPack_Client
 */

// Include the WP_Upgrader class.
if ( ! class_exists( 'WP_Upgrader', false ) ) :
	include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
endif;

if ( ! class_exists( 'HelloPack_Client_Theme_Upgrader' ) ) :

	/**
	 * Extends the WordPress Theme_Upgrader class.
	 *
	 * This class makes modifications to the strings during install & upgrade.
	 *
	 * @class HelloPack_Client_Plugin_Upgrader
	 * @version 1.0.0
	 * @since 2.0.0
	 */
	class HelloPack_Client_Theme_Upgrader extends Theme_Upgrader {

		/**
		 * Initialize the upgrade strings.
		 *
		 * @since 2.0.0
		 */
		public function upgrade_strings() {
			parent::upgrade_strings();

			$this->strings['downloading_package'] = __( 'Downloading the HelloPack Client upgrade package&#8230;', 'hellopack-client' );
		}

		/**
		 * Initialize the install strings.
		 *
		 * @since 2.0.0
		 */
		public function install_strings() {
			parent::install_strings();

			$this->strings['downloading_package'] = __( 'Downloading the HelloPack Client install package&#8230;', 'hellopack-client' );
		}
	}

endif;

if ( ! class_exists( 'HelloPack_Client_Plugin_Upgrader' ) ) :

	/**
	 * Extends the WordPress Plugin_Upgrader class.
	 *
	 * This class makes modifications to the strings during install & upgrade.
	 *
	 * @class HelloPack_Client_Plugin_Upgrader
	 * @version 1.0.0
	 * @since 2.0.0
	 */
	class HelloPack_Client_Plugin_Upgrader extends Plugin_Upgrader {

		/**
		 * Initialize the upgrade strings.
		 *
		 * @since 2.0.0
		 */
		public function upgrade_strings() {
			parent::upgrade_strings();

			$this->strings['downloading_package'] = __( 'Downloading the HelloPack Client upgrade package&#8230;', 'hellopack-client' );
		}

		/**
		 * Initialize the install strings.
		 *
		 * @since 2.0.0
		 */
		public function install_strings() {
			parent::install_strings();

			$this->strings['downloading_package'] = __( 'Downloading the HelloPack Client install package&#8230;', 'hellopack-client' );
		}
	}

endif;
