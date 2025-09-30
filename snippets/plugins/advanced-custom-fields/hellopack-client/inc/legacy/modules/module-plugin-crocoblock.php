<?php
/**
 * Module: Crocoblock
 *
 * @since 2.0.14
 * @package HelloPack_Client
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'check_crocoblock_plugin_status' ) ) {
	/**
	 * Check Crocoblock plugin status
	 */
	function check_crocoblock_plugin_status() {
		if ( class_exists( 'HPack_Set_API_Servers' ) ) {
			$crocoblock = new HPack_Set_API_Servers();
			$crocoblock->set_api_servers( 'api.crocoblock.com', HP_PLUGIN_API_SERVER . '/crocoblock' );
			$crocoblock->init();
		}
	}
	add_action( 'plugins_loaded', 'check_crocoblock_plugin_status' );
}
