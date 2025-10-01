<?php
/**
 * @since 1.1.9
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( ! function_exists( 'check_TRP_Handle_Included_Addons_plugin_status' ) ) {
	function check_TRP_Handle_Included_Addons_plugin_status() {
		if ( get_option( 'my_multi_options' ) !== 'valid' ) {
			HP_check_options( 'trp_license_status', 'valid' );
			HP_check_options( 'trp_license_details', 'valid' );
			HP_check_options( 'trp_license_key', HP_GLOBAL_SERIAL );
		}
	}
}

if ( hp_is_plugin_activated( 'translatepress-business', 'index.php' ) ) {

	$translatepress = new HPack_Set_API_Servers();
	$translatepress->over_api_servers( 'translatepress.com' );
	$translatepress->init();

	add_action( 'plugins_loaded', 'check_TRP_Handle_Included_Addons_plugin_status' );
}
