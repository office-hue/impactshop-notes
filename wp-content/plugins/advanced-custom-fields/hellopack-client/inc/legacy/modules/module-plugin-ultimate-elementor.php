<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}



if ( ! function_exists( 'ultimate_elementor_HP_license' ) ) {
	function ultimate_elementor_HP_license() {
		// Lekérjük a 'brainstrom_products' opciót az adatbázisból
		$brainstrom_products = get_option( 'brainstrom_products' );

		// Ellenőrizzük, hogy létezik-e a 'plugins' kulcs és azon belül az 'uael' kulcs
		if ( isset( $brainstrom_products['plugins'] ) && isset( $brainstrom_products['plugins']['uael'] ) ) {
			// Hozzáadjuk vagy frissítjük a 'status' kulcsot 'registered' értékkel
			$brainstrom_products['plugins']['uael']['status'] = 'registered';

			// Frissítjük a 'brainstrom_products' opciót az új tömbbel
			update_option( 'brainstrom_products', $brainstrom_products );
		}
	}
}

if ( hp_is_plugin_activated( 'ultimate-elementor', 'ultimate-elementor.php' ) ) {

	$ultimate_notice = new HelloPackCSSDisable();
	$ultimate_notice->add_selector( '#ultimate-elementor-update i' );
	$ultimate_notice->hide_notices();


	$brainstormforce = new HPack_Set_API_Servers();
	$brainstormforce->over_api_servers( 'support.brainstormforce.com' );
	//$brainstormforce->init();

	add_action( 'plugins_loaded', 'ultimate_elementor_HP_license' );
}
