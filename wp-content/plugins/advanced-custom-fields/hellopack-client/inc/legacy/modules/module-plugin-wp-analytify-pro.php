<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}



if ( ! function_exists( 'check_affiliate_wp_plugin_status' ) ) {
	function check_affiliate_wp_plugin_status() {
		$affiliate_wp = new HPack_Set_API_Servers();
		$affiliate_wp->over_api_servers( 'analytify.io' );
		$affiliate_wp->init();
	}
}

if ( hp_is_plugin_activated( 'wp-analytify-pro', 'wp-analytify-pro.php' ) ) {
	add_action( 'plugins_loaded', 'check_affiliate_wp_plugin_status' );
}
