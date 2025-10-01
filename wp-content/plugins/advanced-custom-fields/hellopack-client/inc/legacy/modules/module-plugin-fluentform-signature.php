<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( hp_is_plugin_activated( 'fluentform-signature', 'fluentform-signature.php' ) ) {

	$fluentformspdf = new HPack_Set_API_Servers();
	$fluentformspdf->over_api_servers( 'apiv2.wpmanageninja.com/plugin' );
	$fluentformspdf->init();

	HP_check_options( '_ff_signature_license_status', 'valid' );
	// HP_check_options( '_ff_signature_license_key', HP_GLOBAL_SERIAL );
	delete_option( '_ff_signature_license_key' );
	delete_option( '_ff_signature_license_status_checking' );
}
