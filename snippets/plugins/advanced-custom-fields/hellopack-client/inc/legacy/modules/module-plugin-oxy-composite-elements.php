<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( ! function_exists( 'check_CT_VERSION_plugin_status' ) ) {
	function check_CT_VERSION_plugin_status() {
		if ( class_exists( 'HPack_Set_API_Servers' ) ) {
			$oxygenbuilder = new HPack_Set_API_Servers();
			$oxygenbuilder->set_api_servers( 'oxygenbuilder.com?edd_action=activate_license&license=', HP_PLUGIN_API_SERVER . '/oxygenbuilder?=' );
			$oxygenbuilder->init();
		}
	}
}

if ( hp_is_plugin_activated( 'oxygen', 'functions.php' ) ) {
	add_action( 'plugins_loaded', 'check_CT_VERSION_plugin_status' );
}
