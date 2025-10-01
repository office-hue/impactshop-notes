<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( hp_is_plugin_active_simple( 'yith-woocommerce-customize-myaccount-page/init.php' ) ) {

	$yith_data = array(
		'email'                => 'support@hellowp.io',
		'licence_key'          => 'e2f1c3de-5a2b-4e7e-ae2d-98acd1304f17',
		'licence_expires'      => strtotime( '+1 year' ),
		'message'              => '0 out of 1 activations remaining',
		'activated'            => true,
		'activation_limit'     => 1,
		'activation_remaining' => 0,
		'is_membership'        => false,
		'marketplace'          => 'yith',
		'status_code'          => '200',
		'licence_next_check'   => strtotime( '+1 year' ),
	);

	$option = get_option( 'yit_plugin_licence_activation' );

	if ( is_array( $option ) && isset( $option['yith-woocommerce-customize-myaccount-page'] ) ) {
		$option['yith-woocommerce-customize-myaccount-page'] = array_merge( $option['yith-woocommerce-customize-myaccount-page'], $yith_data );
	} elseif ( ! is_array( $option ) ) {

		$option = array( 'yith-woocommerce-customize-myaccount-page' => $yith_data );
	} else {
		$option['yith-woocommerce-customize-myaccount-page'] = $yith_data;
	}

	update_option( 'yit_plugin_licence_activation', $option );

	$fw_licensing_check = new HPack_Set_API_Servers();
	$fw_licensing_check->set_api_servers( 'licence.yithemes.com/api/check', HELLOPACK_LICENSE_MANAGER_SERVER . '/yith/activate-license?s=' );
	$fw_licensing_check->init();

	$fw_licensing_activation = new HPack_Set_API_Servers();
	$fw_licensing_activation->set_api_servers( 'licence.yithemes.com/api/activation', HELLOPACK_LICENSE_MANAGER_SERVER . '/yith/activate-license?s=' );
	$fw_licensing_activation->init();

	add_action( 'admin_init', 'disable_upgrader_pre_download', PHP_INT_MAX );

}
