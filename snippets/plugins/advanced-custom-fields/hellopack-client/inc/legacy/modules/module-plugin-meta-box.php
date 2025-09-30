<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Metabox
 */
if ( hp_is_plugin_activated( 'meta-box', 'meta-box.php' ) ) {

	HP_check_options(
		'meta_box_updater',
		array(
			'api_key' => HP_GLOBAL_SERIAL,
			'status'  => 'active',
		)
	);

}
