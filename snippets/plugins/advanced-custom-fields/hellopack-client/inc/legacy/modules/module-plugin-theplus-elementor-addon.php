<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( hp_is_plugin_activated( 'theplus_elementor_addon', 'theplus_elementor_addon.php' ) ) {

	HP_check_options(
		'theplus_verified',
		array(
			'expire'  => 'lifetime',
			'license' => 'valid',
			'verify'  => 1,
		)
	);
	HP_check_options( 'theplus_purchase_code', array( 'tp_api_key' => HP_GLOBAL_SERIAL ) );

}
