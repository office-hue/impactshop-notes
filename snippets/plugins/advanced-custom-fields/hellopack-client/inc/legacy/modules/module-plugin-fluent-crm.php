<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( ! function_exists( 'check_FLUENTCRM_VERSION_plugin_status' ) ) {
	function check_FLUENTCRM_VERSION_plugin_status() {
		$__fluentcrm_campaign_license = array(
			'license_key' => HP_GLOBAL_SERIAL,
			'price_id'    => '',
			'expires'     => date( 'Y-m-d H:i:s', strtotime( '+1 year' ) ),
			'status'      => 'valid',
		);
		HP_check_options( '__fluentcrm_campaign_license', $__fluentcrm_campaign_license );
	}

	if ( hp_is_plugin_activated( 'fluentcampaign-pro', 'fluentcampaign-pro.php' ) ) {
		add_action( 'plugins_loaded', 'check_FLUENTCRM_VERSION_plugin_status' );
	}
}