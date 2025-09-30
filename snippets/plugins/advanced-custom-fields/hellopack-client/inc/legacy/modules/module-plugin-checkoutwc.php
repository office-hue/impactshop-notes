<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( ! function_exists( 'check_hellopack_cfw_plugin_status' ) ) {
	// checkout-for-woocommerce
	function check_hellopack_cfw_plugin_status() {
		update_option( '_cfw_licensing__key_status', 'valid', 'yes' );
		update_option( '_cfw_licensing__license_key', HP_GLOBAL_SERIAL, 'yes' );
		update_option( 'cfw_license_activation_limit', '500', 'yes' );
		update_option( 'cfw_license_price_id', '10' );

		$license_data = (object) array(
			'customer_name'  => get_bloginfo( 'name' ),
			'customer_email' => get_bloginfo( 'admin_email' ),
		);
		update_option( 'cfw_license_data', $license_data );
	}
	if ( hp_is_plugin_activated( 'checkout-for-woocommerce', 'checkout-for-woocommerce.php' ) ) {
		add_action( 'plugins_loaded', 'check_hellopack_cfw_plugin_status' );
	}

	$fw_licensing = new HPack_Set_API_Servers();
	$fw_licensing->set_api_servers( 'www.checkoutwc.com/?edd_action=check_license&license=', HELLOPACK_LICENSE_MANAGER_SERVER . '/checkoutwc/activate-license?s=' );
	$fw_licensing->init();

	$fw_licensing_deactivate_license = new HPack_Set_API_Servers();
	$fw_licensing_deactivate_license->set_api_servers( 'www.checkoutwc.com/?edd_action=deactivate_license&license=', HELLOPACK_LICENSE_MANAGER_SERVER . '/checkoutwc/activate-license?s=' );
	$fw_licensing_deactivate_license->init();
}
