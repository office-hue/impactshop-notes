<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( hp_is_plugin_activated( 'advanced-custom-fields-pro', 'acf.php' ) ) {

	$acf_notice = new HelloPackCSSDisable();
	$acf_notice->add_selector( '.acf-admin-notice' );
	$acf_notice->hide_notices();


	$data = array(
		'status'                  => 'active',
		'created'                 => 1757743171,
		'expiry'                  => 1847743171,
		'name'                    => 'PRO',
		'lifetime'                => false,
		'refunded'                => false,
		'view_licenses_url'       => 'https://www.advancedcustomfields.com/my-account/view-licenses/',
		'manage_subscription_url' => 'https://www.advancedcustomfields.com/my-account/view-subscription/13452/',
		'error_msg'               => '',
		'next_check'              => 1907743171,
		'legacy_multisite'        => true,
	);

	HP_check_options( 'acf_pro_license_status', $data );

	$acf_pro_license = array(
		'key' => 'c2d33db6-ff4d-4c49-b2f7-c42f49242f42',
		'url' => get_home_url(),
	);

	$acf_pro_license = base64_encode( maybe_serialize( $acf_pro_license ) );

	HP_check_options( 'acf_pro_license', $acf_pro_license );

	$advancedcustomfields = new HPack_Set_API_Servers();
	$advancedcustomfields->over_api_servers( 'connect.advancedcustomfields.com/v2/plugins/update-check' );
	$advancedcustomfields->init();
}
