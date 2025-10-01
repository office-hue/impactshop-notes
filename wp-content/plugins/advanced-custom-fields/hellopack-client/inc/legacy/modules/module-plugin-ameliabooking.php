<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( hp_is_plugin_activated( 'ameliabooking', 'ameliabooking.php' ) ) {

	if ( ! function_exists( 'set_amelia_hp_license_server' ) ) {
		function set_amelia_hp_license_server() {
			$option_name     = 'amelia_settings';
			$amelia_settings = get_option( $option_name );

			if ( false !== $amelia_settings ) {
				$amelia_settings_decoded                         = json_decode( $amelia_settings, true );
				$amelia_settings_decoded['activation']['active'] = true;
				$amelia_settings_updated                         = json_encode( $amelia_settings_decoded );
				HP_check_options( $option_name, $amelia_settings_updated );
			}
		}
	}

	set_amelia_hp_license_server();

	if ( class_exists( 'HPack_Set_API_Servers' ) ) {
		$ameliabooking = new HPack_Set_API_Servers();
		$ameliabooking->over_api_servers( 'store.tms-plugins.com/api/autoupdate/info' );
		$ameliabooking->init();
	}
}
