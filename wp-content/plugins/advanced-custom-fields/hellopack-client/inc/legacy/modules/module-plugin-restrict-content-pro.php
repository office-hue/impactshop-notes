<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( hp_is_plugin_activated( 'restrict-content-pro', 'restrict-content-pro.php' ) ) {

	$restrict = new HPack_Set_API_Servers();
	$restrict->over_api_servers( 'restrictcontentpro.com' );
	$restrict->init();

}
