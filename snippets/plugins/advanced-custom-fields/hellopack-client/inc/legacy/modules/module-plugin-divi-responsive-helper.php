<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( hp_is_plugin_activated( 'divi-responsive-helper', 'divi-responsive-helper.php' ) ) {

	$affiliate_wp = new HPack_Set_API_Servers();

	$affiliate_wp->over_api_servers( 'www.peeayecreative.com/product/divi-responsive-helper' );
	$affiliate_wp->init();

	add_action( 'admin_init', 'disable_upgrader_pre_download' );



}
