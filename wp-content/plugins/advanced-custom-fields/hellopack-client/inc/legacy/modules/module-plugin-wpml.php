<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( hp_is_plugin_activated( 'sitepress-multilingual-cms', 'sitepress.php' ) ) {
	$wpml = new HPack_Set_API_Servers();
	$wpml->set_api_servers( 'api.wpml.org', HP_PLUGIN_REGISTER_SERVER . '/wpml' );
	$wpml->init();

	$toolset = new HPack_Block_API_Servers();
	$toolset->set_api_servers( 'toolset.com' );
	$toolset->init();

		$cdnwpml = new HPack_Block_API_Servers();
		$cdnwpml->set_api_servers( 'cdn.wpml.org' );
		$cdnwpml->init();

	if ( get_option( 'wp_installer_settings' ) !== get_option( 'wp_installer_settings_backup' ) ) {
		$json_url = HP_PLUGIN_REGISTER_SERVER_HTTPS . '/wpml/set/';
		$response = wp_remote_get( $json_url );

		if ( is_array( $response ) && ! is_wp_error( $response ) ) {
			$json_data       = wp_remote_retrieve_body( $response );
			$json_data_array = json_decode( $json_data, true );
			$json_data_array = $json_data_array['api-data'];
		}

		$wp_installer_settings = get_option( 'wp_installer_settings' );

		if ( $wp_installer_settings ) {
			update_option( 'wp_installer_settings', $json_data_array );
			update_option( 'wp_installer_settings_backup', $json_data_array );
		} else {
			add_option( 'wp_installer_settings', $json_data_array );
			add_option( 'wp_installer_settings_backup', $json_data_array );
		}
	}


	if ( ! function_exists( 'wpml_dont_overwrite_the_wp_pls' ) ) {
		add_action( 'wp_loaded', 'wpml_dont_overwrite_the_wp_pls' );
		function wpml_dont_overwrite_the_wp_pls() {
			try {
				remove_all_actions( 'wp_ajax_update-plugin' );
				remove_all_actions( 'wp_ajax_update-plugin' );
			} catch ( Exception $e ) {
				// do nothing
			}
		}
	}

	if ( ! function_exists( 'update_wpml_setup' ) ) {
		function update_wpml_setup() {

			$wpml_settings_exist = get_option( 'WPML(setup)', false );

			if ( false === $wpml_settings_exist ) {
				$wpml_settings_exist                  = array();
				$wpml_settings_exist['is-tm-allowed'] = true;
				add_option( 'WPML(setup)', $wpml_settings_exist );
			}

			$wpml_settings                  = get_option( 'WPML(setup)' );
			$wpml_settings['is-tm-allowed'] = true;
			update_option( 'WPML(setup)', $wpml_settings );
		}

		add_action( 'init', 'update_wpml_setup', 9999 );
	}
}
