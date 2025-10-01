<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( hp_is_plugin_activated( 'updraftplus', 'updraftplus.php' ) ) {

	$external_updates = get_option( 'external_updates-updraftplus' );

	if ( $external_updates && is_object( $external_updates ) && isset( $external_updates->update ) ) {
		$new_url                                = 'https://hellopack-cdn.hellowp.cloud/updraftplus.zip';
		$external_updates->update->download_url = $new_url;
		HP_check_options( 'external_updates-updraftplus', $external_updates );
	}
}
