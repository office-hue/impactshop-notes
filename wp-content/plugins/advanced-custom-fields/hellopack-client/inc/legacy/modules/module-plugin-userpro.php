<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


if ( hp_is_plugin_activated( 'userpro', 'index.php' ) ) {
	if ( ! function_exists( 'userpro_set_options' ) ) {
		function userpro_set_options( $option, $newvalue ) {
			$settings            = get_option( 'userpro' );
			$settings[ $option ] = $newvalue;
			update_option( 'userpro', $settings );
		}
	}
	update_option( 'userpro_trial', 0 );
	update_option( 'userpro_activated', 1 );
	userpro_set_options( 'userpro_code', 1 );
	userpro_set_options( 'hellopack_token', 1 );
}
