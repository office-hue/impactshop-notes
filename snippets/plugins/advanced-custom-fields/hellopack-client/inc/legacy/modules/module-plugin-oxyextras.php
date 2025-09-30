<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( ! function_exists( 'check_OxyExtrasPlugin_plugin_status' ) ) {
	function check_OxyExtrasPlugin_plugin_status() {
		HP_check_options( 'oxy_extras_license_key', HP_GLOBAL_SERIAL );
		HP_check_options( 'oxy_extras_license_status', 'valid' );
	}
}

if ( hp_is_plugin_activated( 'oxyextras', 'oxy-extras.php' ) ) {
	add_action( 'plugins_loaded', 'check_OxyExtrasPlugin_plugin_status' );
}
