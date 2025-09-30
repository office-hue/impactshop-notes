<?php
/**
 * Success notice
 *
 * @package HelloPack_Client
 * @since 2.0.0
 */

?>
<div class="notice notice-success is-dismissible hellopack-notice">
	<p><?php esc_html_e( 'Your HelloPack API-key has been verified.', 'hellopack-client' ); ?></p>
	<script>
	document.addEventListener('DOMContentLoaded', function() {
			var notice = document.querySelector('.hellopack-not-active');
			if (notice) {
				notice.classList.add('hidden');
			}
	});
	</script>
</div>
