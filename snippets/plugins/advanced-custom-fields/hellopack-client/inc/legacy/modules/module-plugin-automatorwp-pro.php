<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( hp_is_plugin_activated( 'automatorwp-pro', 'automatorwp-pro.php' ) ) {

	$automatorwp = new HelloPackCSSDisable();
	$automatorwp->add_selector( '#automatorwp-pro-update strong' );
	$automatorwp->hide_notices();
	if ( ! function_exists( 'hellopack_remove_automatorwp_add_ons_menu' ) ) {
		function hellopack_remove_automatorwp_add_ons_menu() {
			remove_submenu_page( 'automatorwp', 'automatorwp_add_ons' );
			remove_submenu_page( 'automatorwp', 'automatorwp_licenses' );
		}
	}
	add_action( 'admin_menu', 'hellopack_remove_automatorwp_add_ons_menu', 999 );

}