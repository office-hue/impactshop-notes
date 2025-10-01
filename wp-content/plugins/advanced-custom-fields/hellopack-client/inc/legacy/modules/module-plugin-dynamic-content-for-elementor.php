<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit  if accessed directly
}

if ( hp_is_plugin_activated( 'dynamic-content-for-elementor', 'dynamic-content-for-elementor.php' ) ) {


	// delete dce_license_error option
	delete_option( 'dce_license_error' );

	HP_check_options( 'dce_license_activated', 1 );
	HP_check_options( 'dce_license_status', 'active' );

	$dynamic = new HPack_Set_API_Servers();
	$dynamic->over_api_servers( 'license.dynamic.ooo' );
	$dynamic->init();

	add_action( 'admin_init', 'disable_upgrader_pre_download' );


}
