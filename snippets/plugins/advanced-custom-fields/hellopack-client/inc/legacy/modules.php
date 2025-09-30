<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Check if HelloPack plugin is active
 *
 * @since 1.2.0
 * @return bool
 */
if ( is_hellopack_plugin_active() ) {

	require_once HELLOPACK_CLIENT_PATH . 'inc/legacy/constants.php';
	require_once HELLOPACK_CLIENT_PATH . 'inc/legacy/legacy.php';


	foreach ( glob( HELLOPACK_CLIENT_PATH . 'inc/legacy/modules/*.php' ) as $file ) {
		try {
			include_once $file;
		} catch ( Exception $e ) {
			$error = new WP_Error( 'file_load_error', 'Error loading HelloPack module: ' . $file . ' ' . $e->getMessage() );
		}
	}

	/*
	foreach ( glob( HELLOPACK_CLIENT_PATH . 'inc/legacy/modules/done/*.php' ) as $file ) {
		try {
			include_once $file;
		} catch ( Exception $e ) {
			$error = new WP_Error( 'file_load_error', 'Error loading HelloPack module: ' . $file . ' ' . $e->getMessage() );
		}
	}
	*/
}
