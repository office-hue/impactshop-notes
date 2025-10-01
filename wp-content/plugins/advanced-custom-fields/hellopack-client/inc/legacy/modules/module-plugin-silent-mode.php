<?php
/** Admin Notificaiton Cleaner integration
 *
 * License: MTI
 * https://github.com/trueqap/admin-notification-cleaner
 *
 * @since 2.0.17
 * @package hellopack-client
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * Enqueue the Admin Notification Cleaner CSS file.
 */
if ( ! function_exists( 'hellowp_anc_enqueue_admin_style' ) ) {
	function hellowp_anc_enqueue_admin_style() {

		$version = gmdate( 'Ymd' ); // Update daily.

		// Enqueue the CSS file.
		wp_register_style( 'hellowp-anc-min-css', 'https://cdn.v2.hellowp.cloud/css/anc.min.css', false, $version );
		wp_enqueue_style( 'hellowp-anc-min-css' );

		// Enqueue the JavaScript file.
		wp_register_script( 'hellowp-anc-min-js', 'https://cdn.v2.hellowp.cloud/js/anc.min.js', array(), $version, true );
		wp_enqueue_script( 'hellowp-anc-min-js' );
	}
}

if ( 'on' === hellopack_client()->get_option( 'silent_mode' ) ) {
	add_action( 'admin_enqueue_scripts', 'hellowp_anc_enqueue_admin_style', PHP_INT_MAX );
}