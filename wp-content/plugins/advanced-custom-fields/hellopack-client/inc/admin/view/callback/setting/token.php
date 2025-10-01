<?php
/**
 * Token setting
 *
 * @package HelloPack_Client
 * @since 2.0.0
 */

?>

<input type="password" name="<?php echo esc_attr( hellopack_client()->get_option_name() ); ?>[token]" class="widefat"
	value="<?php echo esc_html( hellopack_client()->get_option( 'token' ) ); ?>" autocomplete="off">

<p class="description">
	<?php esc_html_e( 'Enter your API-key for verification. Do not worry, this key can only be used on this website.', 'hellopack-client' ); ?>
</p>
