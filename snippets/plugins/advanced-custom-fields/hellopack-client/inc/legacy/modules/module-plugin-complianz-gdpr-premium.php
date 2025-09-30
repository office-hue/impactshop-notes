<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( hp_is_plugin_activated( 'complianz-gdpr-premium', 'complianz-gdpr-premium.php' ) ) {

	update_site_option( 'cmplz_license_key', 'activated' );
	update_site_option( 'cmplz_license_status', 'valid' );
	update_site_option( 'cmplz_license_activation_limit', '999' );
	update_site_option( 'cmplz_license_activations_left', '990' );
	update_site_option( 'cmplz_license_expires', 'lifetime' );

}
