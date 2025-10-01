<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( hp_is_plugin_activated( 'gp-premium', 'gp-premium.php' ) ) {
	HP_check_options( 'gen_premium_license_key', '61f1be33598b9644de31e3214c9d15fb' );
	HP_check_options( 'gen_premium_license_key_status', 'valid' );
}
