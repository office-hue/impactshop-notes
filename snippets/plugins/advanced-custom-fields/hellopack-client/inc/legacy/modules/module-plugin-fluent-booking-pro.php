<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit  if accessed directly
}

if ( hp_is_plugin_activated( 'fluent-booking-pro', 'fluent-booking-pro.php' ) ) {


	$booking_data = array(
		'license_key' => HP_GLOBAL_SERIAL,
		'price_id'    => '',
		'expires'     => gmdate( 'Y-m-d H:i:s', strtotime( '+1 year' ) ),
		'status'      => 'valid',
	);


	HP_check_options( '__fluent_booking_pro_license', $booking_data );


}
