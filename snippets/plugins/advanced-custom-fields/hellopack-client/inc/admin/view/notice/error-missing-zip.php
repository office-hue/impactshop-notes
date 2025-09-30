<?php
/**
 * Error notice
 *
 * @package HelloPack_Client
 * @since 2.0.1
 */

?>
<div class="notice notice-error is-dismissible hellopack-notice">

	<p>
			<?php
			// translators: %s: link to the downloads page
			printf( esc_html__( 'Failed to locate the package file for this item.  install/upgrade the item manually from the %s.', 'hellopack-client' ), '<a href="https://hellowp.io/hu/helloconsole/hellopack-kozpont/api-creator/" target="_blank">downloads page</a>' );
			?>
	</p>
</div>
