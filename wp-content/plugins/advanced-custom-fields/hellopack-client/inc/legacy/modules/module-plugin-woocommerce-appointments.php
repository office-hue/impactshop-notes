<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( hp_is_plugin_activated( 'woocommerce-appointments', 'woocommerce-appointments.php' ) ) {
	HP_check_options( 'bizz_woocommerce_appointments_license_active', 'valid' );
}
