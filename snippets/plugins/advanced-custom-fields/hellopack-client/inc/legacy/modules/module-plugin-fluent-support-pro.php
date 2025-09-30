<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( ! function_exists( 'check_FLUENTSUPPORT_VERSION_plugin_status' ) ) {
	function check_FLUENTSUPPORT_VERSION_plugin_status() {
		$__fluentcrm_campaign_license = array(
			'license_key' => HP_GLOBAL_SERIAL,
			'price_id'    => '',
			'expires'     => date( 'Y-m-d H:i:s', strtotime( '+1 year' ) ),
			'status'      => 'valid',
		);

		HP_check_options( '__fluentsupport_pro_license', $__fluentcrm_campaign_license );
	}

	if ( hp_is_plugin_activated( 'fluent-support-pro', 'fluent-support-pro.php' ) ) {
		add_action( 'plugins_loaded', 'check_FLUENTSUPPORT_VERSION_plugin_status' );
	}
}
