<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( ! function_exists( 'HP_Add_Extra_CSS' ) ) {
	/**
	 * @return HP_Add_Extra_CSS
	 */
	function HP_Add_Extra_CSS() {
		echo '<style>
      .notice-otgs,#yith-license-notice,#booster-plus-for-woocommerce-update-site-key, #ultimate-elementor-update i,.bsf-core-plugin-link.bsf-core-license-form-btn.inactive,#gform-settings-section-section_license_key_details,.gform-settings-input__container #license_key,#new_slider_from_template, #add_new_slider_wrap #add_on_management, #rs_memarea_registered, #plugin_activation_row .pli_right, #wpgb-admin-navigation li:nth-child(5) {display: none; }
      </style>';
	}
	add_action( 'admin_head', 'HP_Add_Extra_CSS' );
}
