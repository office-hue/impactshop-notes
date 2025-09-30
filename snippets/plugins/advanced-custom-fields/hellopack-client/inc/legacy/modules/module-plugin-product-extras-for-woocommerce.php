<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( hp_is_plugin_activated( 'product-extras-for-woocommerce', 'product-extras-for-woocommerce.php' ) ) {
	HP_check_options( 'pewc_license_status', 'valid' );
	HP_check_options( 'pewc_payment_id', '82200' );
	HP_check_options( 'pewc_license_id', '1100' );
	HP_check_options( 'pewc_license_level', '0' );
	HP_check_options( 'pewc_license_key', HP_GLOBAL_SERIAL );
	delete_option( 'pewc_license_status_message' );

	if ( get_option( 'pewc_license_level' ) == 1 ) {
		HP_check_options( 'pewc_license_level', '0' );
	}

	$pewc_license = new HPack_Set_API_Servers();
	$pewc_license->over_api_servers( 'pluginrepublic.com' );
	$pewc_license->init();
}
