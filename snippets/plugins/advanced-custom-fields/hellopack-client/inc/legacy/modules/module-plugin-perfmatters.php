<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( hp_is_plugin_activated( 'perfmatters', 'perfmatters.php' ) ) {

     HP_check_options( 'perfmatters_edd_license_status', 'valid' );
     
}
