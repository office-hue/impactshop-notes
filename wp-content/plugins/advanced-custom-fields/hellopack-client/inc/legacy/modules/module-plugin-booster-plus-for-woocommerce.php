<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( hp_is_plugin_activated( 'booster-plus-for-woocommerce', 'booster-plus-for-woocommerce.php' ) ) {

	$admin_menu_editor_pro = new HPack_Set_API_Servers();
	$admin_menu_editor_pro->set_api_servers( 'booster.io/?check_site_key=', HELLOPACK_LICENSE_MANAGER_SERVER . '/booster-plus-for-woocommerce/activate-license?s=' );
	$admin_menu_editor_pro->init();

	HP_check_options( 'wcj_site_key_status', '' );
	HP_check_options( 'wcj_site_key', 'AWHD-1234-EFGH-5678-IJKL-9012' );

}
