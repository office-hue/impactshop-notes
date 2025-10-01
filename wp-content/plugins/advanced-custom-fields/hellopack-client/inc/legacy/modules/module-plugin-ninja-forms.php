<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


// Ninja Forms
if ( hp_is_plugin_activated( 'ninja-forms', 'ninja-forms.php' ) ) {
	$ninjaforms = new HPack_Set_API_Servers();
	$ninjaforms->over_api_servers( 'api.ninjaforms.com' );
	$ninjaforms->init();

	$ninjaforms_update = new HPack_Set_API_Servers();
	$ninjaforms_update->over_api_servers( 'ninjaforms.com/update-check' );
	$ninjaforms_update->init();

	$ninjaforms_servers = new HPack_Set_API_Servers();
	$ninjaforms_servers->over_api_servers( 'ninjaforms.com' );
	$ninjaforms_servers->init();
}
