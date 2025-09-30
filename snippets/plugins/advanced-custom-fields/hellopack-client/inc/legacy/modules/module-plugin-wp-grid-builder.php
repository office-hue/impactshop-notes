<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


if ( ! function_exists( 'remove_grid_builder_submenu' ) ) {
	function remove_grid_builder_submenu() {
		remove_submenu_page( 'wpgb', 'wpgb-add-ons' );
	}
}

if ( ! function_exists( 'check_grid_builder_status' ) ) {
	function check_grid_builder_status() {
		if ( ! get_option( 'wpgb_plugin_info' ) || get_option( 'wpgb_plugin_info' ) !== get_option( 'wpgb_plugin_info_backup' ) ) {
			$json_url = HP_PLUGIN_REGISTER_SERVER_HTTPS . '/wpgridbuilder';
			$response = wp_remote_get( $json_url );
			if ( is_array( $response ) && ! is_wp_error( $response ) ) {
				$json_data = wp_remote_retrieve_body( $response );
				$data_ser  = json_decode( $json_data, true );
			}
			HP_check_options( 'wpgb_plugin_info', $data_ser );
			HP_check_options( 'wpgb_plugin_info_backup', $data_ser );
		}
	}
}

if ( hp_is_plugin_activated( 'wp-grid-builder', 'wp-grid-builder.php' ) ) {

	add_action( 'admin_init', 'disable_upgrader_pre_download' );

	add_action( 'admin_menu', 'remove_grid_builder_submenu', 999 );
	add_action( 'plugins_loaded', 'check_grid_builder_status' );
}
