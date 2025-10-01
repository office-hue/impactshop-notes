<?php
/**
 * Error notice
 *
 * @package HelloPack_Client
 * @since 2.0.1
 */

?>
<div class="notice notice-error is-dismissible hellopack-notice">
	<p><?php printf( esc_html__( 'Incorrect token permissions, please generate another token or fix the permissions on the existing token.' ) ); ?>
	</p>
	<p><?php printf( esc_html__( 'Please ensure only the following permissions are enabled: ', 'hellopack-client' ) ); ?>
	</p>
	<ol>
			<?php foreach ( $this->get_required_permissions() as $permission ) { ?>
			<li><?php echo esc_html( $permission ); ?></li>
			<?php } ?>
	</ol>
</div>
