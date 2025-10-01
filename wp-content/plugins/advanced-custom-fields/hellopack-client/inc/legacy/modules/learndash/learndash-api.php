<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


if ( ! defined( 'LEARNDASH_UPDATES_ENABLED' ) ) {
	define( 'LEARNDASH_UPDATES_ENABLED', 'enabled' );
}

if ( ! function_exists( 'check_LEARNDASH_VERSION_plugin_status' ) ) {
	function check_LEARNDASH_VERSION_plugin_status() {
		if ( defined( 'LEARNDASH_VERSION' ) ) {
			update_option( 'nss_plugin_license_email_sfwd_lms', 'support@hellowp.io' );
			update_option( 'nss_plugin_license_sfwd_lms', 'HFSS-A3CG-DAS4-HW2X' );

			if ( class_exists( 'HPack_Set_API_Servers' ) ) {
				$checkoutlearndash = new HPack_Set_API_Servers();
				$checkoutlearndash->set_api_servers( 'checkout.learndash.com/wp-json', HP_PLUGIN_API_SERVER );
				$checkoutlearndash->init();

				$pluginupdate = new HPack_Set_API_Servers();
				$pluginupdate->set_api_servers( 'support.learndash.com/?pluginupdate', HP_PLUGIN_API_SERVER . '/learndash/v1/pluginupdate?api' );
				$pluginupdate->init();

			}
		}
	}
}
add_action( 'plugins_loaded', 'check_LEARNDASH_VERSION_plugin_status' );
