<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WPBakery Page Builder
 */
if ( hp_is_plugin_activated( 'js_composer', 'js_composer.php' ) ) {

	$w_p_bakery_updates_support = new HPack_Set_API_Servers();
	$w_p_bakery_updates_support->set_api_servers( 'support.wpbakery.com/check-license-key', HELLOPACK_LICENSE_MANAGER_SERVER . '/wpbakery/check-license-key?s=' );
	$w_p_bakery_updates_support->init();


	$w_p_bakery_updates_support = new HPack_Set_API_Servers();
	$w_p_bakery_updates_support->set_api_servers( 'support.wpbakery.com/finish-license-activation', HELLOPACK_LICENSE_MANAGER_SERVER . '/wpbakery/finish-license-activation?s=' );
	$w_p_bakery_updates_support->init();


	add_action( 'admin_init', 'disable_upgrader_pre_download' );

	HP_check_options( 'wpb_license_errors', array() );
	HP_check_options( 'wpb_js_js_composer_purchase_code', HP_GLOBAL_SERIAL );

}
