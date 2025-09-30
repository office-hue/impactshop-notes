<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( hp_is_plugin_activated( 'fluentformpro', 'fluentformpro.php' ) ) {

	HP_check_options( '_ff_fluentform_pro_license_key', HP_GLOBAL_SERIAL );
	HP_check_options( '_ff_fluentform_pro_license_status', 'valid' );

	delete_option( '_ff_fluentform_pro_license_key' );
	delete_option( '_ff_fluentform_pro_license_status_checking' );

}
