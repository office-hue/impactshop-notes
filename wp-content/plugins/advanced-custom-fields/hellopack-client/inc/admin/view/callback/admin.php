<?php
/**
 * Admin UI
 *
 * @package HelloPack_Client
 * @since 2.0.0
 */

if ( isset( $_GET['action'] ) ) {
	$id = ! empty( $_GET['id'] ) ? absint( trim( $_GET['id'] ) ) : '';

	if ( 'install-plugin' === $_GET['action'] ) {
		HelloPack_Client_Admin::install_plugin( $id );
	} elseif ( 'install-theme' === $_GET['action'] ) {
		HelloPack_Client_Admin::install_theme( $id );
	}
} else {
	add_thickbox();
	?>
<div class="wrap about-wrap full-width-layout hellopack-dashboard">
	<?php HelloPack_Client_Admin::render_intro_partial(); ?>
	<?php HelloPack_Client_Admin::render_tabs_partial(); ?>

	<?php HelloPack_Client_Admin::render_plugins_panel_partial(); ?>
	<?php HelloPack_Client_Admin::render_themes_panel_partial(); ?>

	<form method="POST"
			action="<?php echo esc_url( HELLOPACK_CLIENT_NETWORK_ACTIVATED ? network_admin_url( 'edit.php?action=hellopack_client_network_settings' ) : admin_url( 'options.php' ) ); ?>">

			<?php HelloPack_Client_Admin::render_settings_panel_partial(); ?>
			<?php HelloPack_Client_Admin::render_help_panel_partial(); ?>
	</form>
</div>
	<?php
}
