<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( hp_is_plugin_activated( 'woocommerce-pretty-emails', 'emailplus.php' ) ) {
	$pretty_emails = new HPack_Set_API_Servers();
	$pretty_emails->over_api_servers( 'www.mbcreation.com' );
	$pretty_emails->init();
}
