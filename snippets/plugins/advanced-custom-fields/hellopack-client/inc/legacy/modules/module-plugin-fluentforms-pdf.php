<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( hp_is_plugin_activated( 'fluentforms-pdf', 'fluentforms-pdf.php' ) ) {

	$fluentformspdf = new HPack_Set_API_Servers();
	$fluentformspdf->over_api_servers( 'apiv2.wpmanageninja.com/plugin' );
	$fluentformspdf->init();
}
