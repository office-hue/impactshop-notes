<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( hp_is_plugin_activated( 'mainwp', 'mainwp.php' ) ) {


	$mainwp_licensing = new HPack_Set_API_Servers();
	$mainwp_licensing->set_api_servers( 'mainwp.com/?mainwp-api=am-software-api', HELLOPACK_LICENSE_MANAGER_SERVER . '/mainwp/activate-license?s=' );
	$mainwp_licensing->init();

	$plan_info = array(
		'plan_purchased' => 'yearly',
		'plan_status'    => 'active',
	);

	update_option( 'mainwp_extensions_plan_info', json_encode( $plan_info ) );


}
