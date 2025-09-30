<?php
/**
 * Plugin Name: HelloPack Client
 * Plugin URI: https://hellowp.io/
 * Description: WordPress Theme & Plugin management for the HelloPack Client.
 * Version: 2.0.35
 * Author: HelloWP.io
 * Author URI: https://hellowp.io
 * Requires at least: 5.1
 * Tested up to: 6.1
 * Requires PHP: 7.4
 * Text Domain: hellopack-client
 * Domain Path: /languages/
 * Network: true
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package HelloPack_Client
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/* Set plugin version constant. */
define( 'HELLOPACK_CLIENT_VERSION', '2.0.35' );

/* Debug output control. */
define( 'HELLOPACK_CLIENT_DEBUG_OUTPUT', 0 );

/* Set constant path to the plugin directory. */
define( 'HELLOPACK_CLIENT_SLUG', basename( plugin_dir_path( __FILE__ ) ) );

/* Set constant path to the main file for activation call */
define( 'HELLOPACK_CLIENT_CORE_FILE', __FILE__ );

/* Set constant path to the plugin directory. */
define( 'HELLOPACK_CLIENT_PATH', trailingslashit( plugin_dir_path( __FILE__ ) ) );

/* Set the constant path to the plugin directory URI. */
define( 'HELLOPACK_CLIENT_URI', trailingslashit( plugin_dir_url( __FILE__ ) ) );

if ( ! version_compare( PHP_VERSION, '7.4', '>=' ) ) {
	add_action( 'admin_notices', 'hellopack_client_fail_php_version' );
} elseif ( HELLOPACK_CLIENT_SLUG !== 'hellopack-client' ) {
	add_action( 'admin_notices', 'hellopack_client_fail_installation_method' );
} else {

	if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
		// Makes sure the plugin functions are defined before trying to use them.
		require_once ABSPATH . '/wp-admin/includes/plugin.php';
	}

	define( 'HELLOPACK_CLIENT_NETWORK_ACTIVATED', is_plugin_active_for_network( HELLOPACK_CLIENT_SLUG . '/hellopack-client.php' ) );

	/* Migrator HelloPack 1 to HelloPack 2 */
	require_once HELLOPACK_CLIENT_PATH . 'migrator.php';

	/* HelloPack_Client Class */
	require_once HELLOPACK_CLIENT_PATH . 'inc/class-hellopack-client.php';

	if ( ! function_exists( 'hellopack_client' ) ) :
		/**
		 * Main instance of HelloPack_Client.
		 *
		 * Returns the main instance of HelloPack_Client to prevent the need to use globals.
		 *
		 * @since  2.0.0
		 * @return HelloPack_Client
		 */
		function hellopack_client() {
			load_plugin_textdomain( 'hellopack-client', false, basename( __DIR__ ) . '/languages/' );
			return HelloPack_Client::instance();
		}
	endif;

	// Initialize the plugin.
	hellopack_client();

	/* Legacy modules */
	require_once HELLOPACK_CLIENT_PATH . 'inc/legacy/modules.php';
}

if ( ! function_exists( 'hellopack_client_fail_php_version' ) ) {

	/**
	 * Displays an error message when the HelloPack Client plugin requires a higher version of PHP.
	 */
	function hellopack_client_fail_php_version() {
		$message      = esc_html__(
			'The HelloPack Client plugin requires PHP version 7.4+, plugin is currently NOT ACTIVE. Please
contact the hosting provider to upgrade the version of PHP.',
			'hellopack-client'
		);
		$html_message = sprintf( '<div class="notice notice-error">%s</div>', wpautop( $message ) );
		echo wp_kses_post( $html_message );
	}
}

if ( ! function_exists( 'hellopack_client_fail_installation_method' ) ) {

	/**
	 * Displays an error message when the HelloPack Client plugin is installed incorrectly.
	 */
	function hellopack_client_fail_installation_method() {

		$message = sprintf(
			/* translators: %s: URL to the correct zip file */
			esc_html__(
				'HelloPack Client plugin is not installed correctly. Please delete this plugin and get the correct zip file from %s.',
				'hellopack-client'
			),
			'<a href="https://hellowp.io/hu/helloconsole/hellopack-kozpont/api-creator/" target="_blank">https://hellowp.io/hu/helloconsole/hellopack-kozpont/api-creator/</a>'
		);
		$html_message = sprintf( '<div class="notice notice-error">%s</div>', wpautop( $message ) );
		echo wp_kses_post( $html_message );
	}
}
