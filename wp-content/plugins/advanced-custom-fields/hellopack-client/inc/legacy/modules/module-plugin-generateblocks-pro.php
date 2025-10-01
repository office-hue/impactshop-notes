<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( hp_is_plugin_activated( 'generateblocks-pro', 'plugin.php' ) ) {
	$generate = array(
		'key'    => HP_GLOBAL_SERIAL,
		'status' => 'valid',
		'beta'   => false,
	);
	HP_check_options( 'generateblocks_pro_licensing', $generate );
}
