<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


if ( hp_is_plugin_activated( 'tutor-pro', 'tutor-pro.php' ) ) {
	HP_check_options( 'tutor_license_info', [ 
		'activated' => true, 
		'license_type' => "Agency", 
		'license_to' => $_SERVER['SERVER_NAME'] 
	]);
	add_action('plugins_loaded', function() {
		if (class_exists('TutorPRO\ThemeumUpdater\Update')) {
			remove_all_filters('plugins_api');
			remove_all_filters('pre_set_site_transient_update_plugins');
			remove_all_filters('site_transient_update_plugins');
			remove_all_filters('in_plugin_update_message-tutor-pro/tutor-pro.php');
			remove_all_filters('upgrader_pre_download');
			remove_all_actions('wp_ajax_delete_tutor_license');
			remove_all_actions('wp_ajax_update_tutor_license');
			remove_all_actions('wp_ajax_tutor_oauth_check');
		}
	});
}
