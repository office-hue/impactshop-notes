<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( ! function_exists( 'hp_morphology_disabled' ) ) {
	function hp_morphology_disabled() {
		echo "<style>
    #yoast-configuration-indexing-container:after {
     margin-top: 10px;
     display: block;
     color: red;
     content: 'A HelloPack nem támogatja a Morphology szolgáltatást (mivel magyarul érdemben használhatatlan), ezért a varázsló hibát fog jelezni. Semmilyen problémát nem okoz, kérlek, hogy nyugodtan nyomj a Tovább gombra, amikor jelzi a hibát.';
   }
  </style>";
	}
}


if ( hp_is_plugin_activated( 'wordpress-seo-premium', 'wp-seo-premium.php' ) ) {

	$updates = new HPack_Set_API_Servers();
	$updates->over_api_servers( 'tracking.yoast.com/stats' );
	$updates->init();

	add_action( 'admin_head', 'hp_morphology_disabled' );
}
