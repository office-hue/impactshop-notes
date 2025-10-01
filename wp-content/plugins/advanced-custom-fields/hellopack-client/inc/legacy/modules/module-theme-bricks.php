<?php
/**
 * Module Name: Bricks
 * Description: Bricks module for HelloPack
 *
 * @package HelloPack
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// HP_DISABLE_THEMES

if ( hp_is_theme_activated( 'bricks' ) ) {

	HP_check_options( 'bricks_license_key', 'AWHD-1234-EFGH-5678-IJKL-9012' );

	$license_status =
	array(
		'status' => 'active',

	);
	$expiration_time = 10000 * 168 * HOUR_IN_SECONDS;

	set_transient( 'bricks_license_status', $license_status, $expiration_time );

	$bricks = new HPack_Set_API_Servers();
	$bricks->set_api_servers( 'bricksbuilder.io/api/commerce/license/', HELLOPACK_LICENSE_MANAGER_SERVER . '/bricks/activate-license?s=' );
	$bricks->init();

}
