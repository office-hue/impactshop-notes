<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( hp_is_plugin_activated( 'sfwd-lms', 'sfwd_lms.php' ) ) {
	$hp_learndash_dir   = HP_UPDATER_INC . 'modules/learndash/';
	$hp_learndash_files = scandir( $hp_learndash_dir );

	foreach ( $hp_learndash_files as $hp_learndash_file ) {
		if ( $hp_learndash_file == '.' || $hp_learndash_file == '..' ) {
			continue;
		}
		if ( strpos( $hp_learndash_file, '.php' ) !== false ) {
			include_once $hp_learndash_dir . $hp_learndash_file;
		}
	}

	// Add admin CSS for LearnDash false notice
	add_action( 'admin_head', function() {
		echo '<style>
			.learndash-updates-disabled-dismissible {
				display: none !important;
			}
		</style>';
	});
}
