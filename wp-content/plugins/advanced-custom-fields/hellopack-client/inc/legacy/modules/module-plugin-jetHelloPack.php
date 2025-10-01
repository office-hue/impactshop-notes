<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( ! function_exists( 'jet_hello_pack_warning' ) ) {
	function jet_hello_pack_warning() {
		?>
<div class="notice notice-warning" style="background-color: #fffcee">
	<p><?php _e( 'The <strong>JetHelloPack</strong> plugin is active on your site, but it is no longer needed. You can safely deactivate and delete it.', 'hellopack-client' ); ?>
	</p>
</div>
		<?php
	}
}

if ( hp_is_plugin_activated( 'jet-a-hellopack', 'jet-a-hellopack.php' ) ) {
	add_action( 'admin_notices', 'jet_hello_pack_warning' );
}
