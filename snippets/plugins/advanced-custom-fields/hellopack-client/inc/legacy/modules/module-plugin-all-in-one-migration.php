<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( hp_is_plugin_activated( 'all-in-one-wp-migration', 'all-in-one-wp-migration.php' ) ) {

    delete_option('ai1wm_updater');

    $all_in_one_wp_migration = new HPack_Set_API_Servers();
	$all_in_one_wp_migration->over_api_servers( 'https://redirect.wp-migration.com/v1/check/unlimited-extension/' );
	$all_in_one_wp_migration->init();

}