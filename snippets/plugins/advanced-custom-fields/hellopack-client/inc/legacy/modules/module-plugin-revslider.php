<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


if ( hp_is_plugin_activated( 'revslider', 'revslider.php' ) ) {
	$revslider = new HPack_Set_API_Servers();
	$revslider->over_api_servers( 'updates.themepunch.tools' );
	$revslider->init();

	$revslider_a = new HPack_Set_API_Servers();
	$revslider_a->over_api_servers( 'library.themepunch.tools' );
	$revslider_a->init();

	$revslider_b = new HPack_Set_API_Servers();
	$revslider_b->over_api_servers( 'templates.themepunch.tools' );
	$revslider_b->init();

	$revslider_c = new HPack_Set_API_Servers();
	$revslider_c->over_api_servers( 'themepunch.tools' );
	$revslider_c->init();

	HP_check_options( 'revslider-valid', 'true' );
	HP_check_options( 'revslider-code', HP_GLOBAL_SERIAL );
	HP_check_options( 'revslider-temp-active-notice', 'false' );
}
