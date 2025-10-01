<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

$elementor_version_required = '3.9.2';

if ( defined( 'ELEMENTOR_PRO_VERSION' ) and ! version_compare( ELEMENTOR_PRO_VERSION, $elementor_version_required, '>=' ) ) {
	if ( ! function_exists( 'elementor_pro_hello_pack_warning' ) ) {
		function elementor_pro_hello_pack_warning() {
			?>
<div class="notice notice-error" style="background-color: #fcd0d8">
     <p><?php _e( '<strong>The current Elementor PRO version on the site is outdated</strong>, and it is not compatible with HelloPack. Please upgrade to at least Elementor PRO 3.9.2. This is highly recommended for technical and security reasons.', 'hellopack' ); ?>
     </p>
</div>
<?php
		}
	}
	add_action( 'admin_notices', 'elementor_pro_hello_pack_warning' );

	if ( get_option( 'elementor_pro_license_key' ) and hp_is_plugin_activated( 'elementor-pro', 'elementor-pro.php' ) ) {
		delete_option( 'elementor_pro_license_key' );
	}
	return;
}

if ( ! function_exists( 'get_hello_data' ) ) {
	function get_hello_data( $key, array $arr ) {
		$val = array();
		array_walk_recursive(
			$arr,
			function ( $v, $k ) use ( $key, &$val ) {
				if ( $k == $key ) {
					array_push( $val, $v );
				}
			}
		);
		return count( $val ) > 1 ? $val : array_pop( $val );
	}
}

if ( ! function_exists( 'check_elementorpro_plugin_status' ) ) {
	function check_elementorpro_plugin_status() {

		if ( HELLOPACK_CLIENT_NETWORK_ACTIVATED ) {
			if ( get_site_option( 'hellopack_client' ) ) {
				$hellopack_updater_api_settings_key = get_site_option( 'hellopack_client' );
			}
		} else {
			if ( get_option( 'hellopack_client' ) ) {
				$hellopack_updater_api_settings_key = get_option( 'hellopack_client' );
			}
		}

		if ( defined( 'ELEMENTOR_PRO_VERSION' ) ) {
			if ( get_option( '_elementor_pro_api_requests_lock' ) == 1 ) {
				delete_option( '_elementor_pro_api_requests_lock' );
			}

			$get_current_time_in_micro_hp = floor( microtime( true ) + 60 * 60 );

			if ( get_option( '_elementor_pro_api_requests_lock' ) &&
			isset( get_option( '_elementor_pro_api_requests_lock' )['get_license_data'] ) &&
			get_option( '_elementor_pro_api_requests_lock' )['get_license_data'] > $get_current_time_in_micro_hp ) {
				delete_option( '_elementor_pro_api_requests_lock' );
			}

			if ( get_option( 'elementor_pro_license_key' ) != get_option( 'elementor_hellopack_license_key' ) ) {
				delete_option( 'elementor_pro_license_key' );
			}

			if ( ! get_option( 'elementor_hellopack_license_key' ) ) {
				delete_option( 'elementor_pro_license_key' );
				delete_option( '_elementor_pro_license_data' );
				add_option( 'elementor_hellopack_license_key', get_hello_data( 'token', $hellopack_updater_api_settings_key ), '', 'yes' );
			}

			if ( ! get_option( 'elementor_pro_license_key' ) ) {
				add_option( 'elementor_pro_license_key', get_hello_data( 'token', $hellopack_updater_api_settings_key ), '', 'yes' );
			}
			require_once 'elementor/base.php';
		} else {
			if ( get_option( 'elementor_pro_license_key' ) ) {
				delete_option( 'elementor_pro_license_key' );
			}
		}
	}
}

if ( ! function_exists( 'check_ELEMENTOR_PRO_VERSION_plugin_status' ) ) {
	function check_ELEMENTOR_PRO_VERSION_plugin_status() {
		if ( class_exists( 'HPack_Set_API_Servers' ) ) {
			$translation = new HPack_Set_API_Servers();
			$locate      = get_locale();
			$translation->set_api_servers( 'plugin-downloads.elementor.com/v2/translation/', 'hellopack.wp-json.app/languages/elementor-pro-' . $locate . '.zip?id=' );
			$translation->init();
		}
	}

	if ( hp_is_plugin_activated( 'elementor-pro', 'elementor-pro.php' ) ) {
		add_action( 'plugins_loaded', 'check_ELEMENTOR_PRO_VERSION_plugin_status' );
		add_action( 'plugins_loaded', 'check_elementorpro_plugin_status' );
	}
}