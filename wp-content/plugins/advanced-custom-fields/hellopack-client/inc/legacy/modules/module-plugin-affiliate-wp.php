<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( hp_is_plugin_activated( 'affiliate-wp', 'affiliate-wp.php' ) ) {

	$affiliate_wp = new HPack_Set_API_Servers();

	$affiliate_wp->over_api_servers( 'plugin.affiliatewp.com/wp-content/notifications.json' );
	$affiliate_wp->init();

	add_action(
		'plugins_loaded',
		function () {
			if ( ! function_exists( 'affiliate_wp' ) ) {
				return;
			}

			$settings = affiliate_wp()->settings;

			$license_data = (object) array(
				'price_id' => 3,
				'license'  => 'valid',
				'success'  => true,
			);

			$settings->set(
				array(
					'license_status' => $license_data,
					'license_key'    => HP_GLOBAL_SERIAL,
				),
				true
			);

			set_transient( 'affwp_license_check', 'valid', DAY_IN_SECONDS );

			$failed_request_cache_key = 'edd_sl_failed_http_' . md5( 'https://affiliatewp.com' );
			update_option( $failed_request_cache_key, strtotime( '+24 hours' ) );
		}
	);

}
