<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( hp_is_plugin_activated( 'wp-seopress-pro', 'seopress-pro.php' ) ) {

	if ( ! defined( 'SEOPRESS_LICENSE_KEY' ) ) {
		define( 'SEOPRESS_LICENSE_KEY', HP_GLOBAL_SERIAL );

	}

	$seopress_licensing = new HPack_Set_API_Servers();
	$seopress_licensing->set_api_servers( 'www.seopress.org', HELLOPACK_LICENSE_MANAGER_SERVER . '/universal/activate-license?s=' );
	$seopress_licensing->init();

	HP_check_options( 'seopress_pro_license_status', 'valid' );

}
