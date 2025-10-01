<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( hp_is_plugin_activated( 'admin-menu-editor-pro', 'menu-editor.php' ) ) {

		$admin_menu_editor_pro = new HPack_Set_API_Servers();
		$admin_menu_editor_pro->set_api_servers( '/adminmenueditor.com/licensing_api/products/admin-menu-editor-pro/licenses', HELLOPACK_LICENSE_MANAGER_SERVER . '/admin-menu-editor-pro/licenses/?s=' );
		$admin_menu_editor_pro->init();

		$adminmenueditor = new HPack_Set_API_Servers();
		$adminmenueditor->set_api_servers( 'adminmenueditor.com', HELLOPACK_LICENSE_MANAGER_SERVER . '/admin-menu-editor-pro/licenses/?s=' );
		$adminmenueditor->init();

	add_action( 'admin_init', 'disable_upgrader_pre_download' );

}
