<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( ! function_exists( 'remove_wpbs_calendars_submenu' ) ) {
	function remove_wpbs_calendars_submenu() {
		remove_submenu_page( 'wp-booking-system', 'wpbs-addons' );
	}
}

if ( ! function_exists( 'check_wpbs_plugin_status' ) ) {
	function check_wpbs_plugin_status() {
		HP_check_options( 'wpbs_registered_website_id', HP_GLOBAL_SERIAL );
		HP_check_options( 'wpbs_serial_key', HP_GLOBAL_SERIAL );
	}
}

if ( hp_is_plugin_activated( 'wp-booking-system-premium', 'index.php' ) ) {


	$bookingsys = new HPack_Set_API_Servers();
	$bookingsys->over_api_servers( 'www.wpbookingsystem.com/u/' );
	$bookingsys->init();


	add_action( 'admin_menu', 'remove_wpbs_calendars_submenu', 999 );
	add_action( 'plugins_loaded', 'check_wpbs_plugin_status' );
}
