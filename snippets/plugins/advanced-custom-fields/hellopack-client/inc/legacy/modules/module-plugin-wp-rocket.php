<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

// Check if wp-rocket is disabled
if ( defined( 'HP_DISABLE_PLUGINS' ) ) {
	$disabled_plugins = explode( ',', HP_DISABLE_PLUGINS );
	$disabled_plugins = array_map( 'trim', $disabled_plugins );
	if ( in_array( 'wp-rocket', $disabled_plugins ) ) {
		return; // Exit if wp-rocket is disabled
	}
}

if ( ! function_exists( 'HP_init_WPROCKET' ) ) {
	function HP_init_WPROCKET() {
		if ( class_exists( 'HPack_Set_API_Servers' ) ) {
			$wprocket_get = new HPack_Set_API_Servers();
			$wprocket_get->set_api_servers( 'api.wp-rocket.me/valid_key.php', 'api-register.wp-json.app/wprocket/set/?rout=' );
			$wprocket_get->init();
		}

		if ( class_exists( 'HPack_Set_API_Servers' ) ) {
			$wprocket_set = new HPack_Set_API_Servers();
			$wprocket_set->set_api_servers( 'api.wp-rocket.me/api/wp-rocket/activate-licence.php', 'api-register.wp-json.app/wprocket/set/?rout=' );
			$wprocket_set->init();
		}

		if ( class_exists( 'HPack_Set_API_Servers' ) ) {
			$wprocket_user = new HPack_Set_API_Servers();
			$wprocket_user->set_api_servers( 'api.wp-rocket.me/stat/1.0/wp-rocket/user.php', 'api-register.wp-json.app/wprocket/user/?rout=' );
			$wprocket_user->init();
		}

		if ( ! defined( 'WP_ROCKET_KEY' ) ) {
			define( 'WP_ROCKET_KEY', 'x3llx146' );
		}

		if ( ! defined( 'WP_ROCKET_EMAIL' ) ) {
			define( 'WP_ROCKET_EMAIL', 'support@hellowp.io' );
		}
	}
	add_action( 'plugins_loaded', 'HP_init_WPROCKET' );
}

if ( hp_is_plugin_activated( 'wp-rocket', 'wp-rocket.php' ) ) {
	$wprocket = new HPack_Set_API_Servers();
	$wprocket->over_api_servers( 'api.wp-rocket.me/check_update.php' );
	$wprocket->init();

	add_action( 'plugins_loaded', 'HP_init_WPROCKET' );
}
