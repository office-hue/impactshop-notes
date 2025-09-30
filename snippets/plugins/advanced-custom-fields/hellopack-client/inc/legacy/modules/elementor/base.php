<?php

if ( class_exists( 'HPack_Set_API_Servers' ) ) {
	$validate = new HPack_Set_API_Servers();
	$validate->set_api_servers( 'my.elementor.com/api/v2/license/validate', 'api.wp-json.app/elementor.com/api/v2/license/validate' );
	$validate->init();
}

if ( class_exists( 'HPack_Set_API_Servers' ) ) {
	$library = new HPack_Set_API_Servers();
	$library->set_api_servers( 'my.elementor.com/api/connect/v1/library/get_template_content', 'elementor.wp-json.app/library/?=get_template_content' );
	$library->init();
}

if ( class_exists( 'HPack_Set_API_Servers' ) ) {
	$library = new HPack_Set_API_Servers();
	$library->set_api_servers( 'my.elementor.com/api/v1/kits-library/kits', 'elementor.wp-json.app/kits/?=' );
	$library->init();
}
