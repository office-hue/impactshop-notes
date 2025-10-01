<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
if ( ! function_exists( 'check_DTB_TOOLBOX_PLUGIN_URI_plugin_status' ) ) {
	function check_DTB_TOOLBOX_PLUGIN_URI_plugin_status() {
		global $submenu;
		HP_check_options( 'wc_am_client_6865_deactivate_checkbox', 'off' );
		HP_check_options( 'wc_am_client_6865_activated', 'Activated' );

		$wc_am_client_6865['wc_am_client_6865_api_key'] = HP_GLOBAL_SERIAL;
		HP_check_options( 'wc_am_client_6865', $wc_am_client_6865 );

		add_action( 'admin_menu', 'wc_am_client_6865_extra_menu', 999 );
		function wc_am_client_6865_extra_menu() {
			remove_submenu_page( 'options-general.php', 'wc_am_client_6865_dashboard' );
		}
	}
	if ( hp_is_plugin_activated( 'divi-toolbox', 'divi-toolbox.php' ) ) {
		add_action( 'plugins_loaded', 'check_DTB_TOOLBOX_PLUGIN_URI_plugin_status' );
	}
}
