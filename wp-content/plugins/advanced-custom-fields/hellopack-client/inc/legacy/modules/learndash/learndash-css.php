<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( ! class_exists( 'HelloPack_Cartflows' ) ) :
	class HidePlayListButtonCSS {

		public function __construct() {
			add_action( 'admin_head', array( $this, 'add_css' ) );
		}

		public function add_css() {
			echo '<style>
             a.global-new-entity-button[href="' . home_url( '/wp-admin/admin.php?page=learndash-course-wizard' ) . '"] {
                 display: none;
             }
         </style>';
		}
	}
	new HidePlayListButtonCSS();
endif;
