<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * JetFormBuilder
*/
if ( hp_is_plugin_activated( 'jetformbuilder', 'jet-form-builder.php' ) ) {

	$jet_data = array(
		array(
			'success'                     => true,
			'license'                     => 'valid',
			'item_id'                     => 9,
			'item_name'                   => 'PRO Form Builder Addons Subscription',
			'is_local'                    => true,
			'license_limit'               => 0,
			'site_count'                  => 1,
			'expires'                     => 'lifetime',
			'activations_left'            => 'unlimited',
			'checksum'                    => '8b2b7e4e7f432ee47efb6d4d7c3bfe2a',
			'payment_id'                  => 4241,
			'customer_name'               => 'HelloWP Support',
			'customer_email'              => 'support@hellowp.io',
			'price_id'                    => '3',
			'excluded_plugins'            => array(),
			'has_templates_access'        => false,
			'has_design_templates_access' => false,
			'license_key'                 => HP_GLOBAL_SERIAL,
		),
	);

	HP_check_options( 'jfb-license-data', $jet_data );

	$jetformbuilder_updates_support = new HPack_Set_API_Servers();
	$jetformbuilder_updates_support->set_api_servers( 'account.jetformbuilder.com?edd_action=activate_license', HELLOPACK_LICENSE_MANAGER_SERVER . '/jetformbuilder/license-activation?s=' );
	$jetformbuilder_updates_support->init();

	$jetformbuilder_download = new HPack_Set_API_Servers();
	$jetformbuilder_download->set_api_servers( 'account.jetformbuilder.com?ct_api_action=get_plugin&', HELLOPACK_LICENSE_MANAGER_SERVER . '/jetformbuilder/download?domain=' . HP_GLOBAL_URL . '&' );
	$jetformbuilder_download->init();

}
