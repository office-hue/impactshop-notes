<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'DSM_DIR_PATH_plugin_status' ) ) {
	function DSM_DIR_PATH_plugin_status() {
		$license_data['key']        = HP_GLOBAL_SERIAL;
		$license_data['last_check'] = time();
		HP_check_options( 'dsm_pro_license', $license_data );
	}
	if ( hp_is_plugin_activated( 'supreme-modules-pro-for-divi', 'supreme-modules-pro-for-divi.php' ) ) {
		add_action( 'plugins_loaded', 'DSM_DIR_PATH_plugin_status' );
	}
}
