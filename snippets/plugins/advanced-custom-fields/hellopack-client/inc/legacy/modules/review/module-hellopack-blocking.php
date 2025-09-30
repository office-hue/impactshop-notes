<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


// iThemes Security Pro
if ( hp_is_plugin_activated( 'ithemes-security-pro', 'ithemes-security-pro.php' ) ) {
	$security = new HPack_Set_API_Servers();
	$security->over_api_servers( 'api.ithemes.com/updater' );
	$security->init();
}


if ( hp_is_plugin_activated( 'wp-grid-builder', 'wp-grid-builder.php' ) ) {
	$wpgridbuilder = new HPack_Set_API_Servers();
	$wpgridbuilder->over_api_servers( 'wpgridbuilder.com' );
	$wpgridbuilder->init();
}

if ( hp_is_plugin_activated( 'fluentcampaign-pro', 'fluentcampaign-pro.php' ) ) {
	$fluentcampaign = new HPack_Set_API_Servers();
	$fluentcampaign->over_api_servers( 'apiv2.wpmanageninja.com/plugin' );
	$fluentcampaign->init();
}
if ( hp_is_plugin_activated( 'automatorwp-pro', 'automatorwp-pro.php' ) ) {
	$automatorwp = new HPack_Set_API_Servers();
	$automatorwp->over_api_servers( 'automatorwp.com/edd-sl-api' );
	$automatorwp->init();
}
