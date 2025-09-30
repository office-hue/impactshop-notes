<?php
/**
 * Error notice
 *
 * @package HelloPack_Client
 * @since 2.0.1
 */

?>
<div class="notice notice-error is-dismissible hellopack-notice">
	<p><?php esc_html_e( 'Failed to connect to the HelloPack API. Please contact the hosting providier with this message: "The HelloPack Client WordPress plugin requires TLS version 1.2 or above, please confirm if this hosting account supports TLS version 1.2 and allows connections from WordPress to the host api.v2.wp-json.app".', 'hellopack-client' ); ?>
	</p>
</div>
