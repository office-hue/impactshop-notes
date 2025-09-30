<?php
/**
 * Themes panel partial
 *
 * @package HelloPack_Client
 * @since 2.0.0
 */

$themes = hellopack_client()->items()->themes( 'purchased' );

?>
<div id="themes" class="panel <?php echo empty( $themes ) ? 'hidden' : ''; ?>">

	<div
			class="hellopack-builder-important-notice hellopack-template-builder hellopack-db-card hellopack-db-card-first">
			<div class="intro-text">
				<h1 class="hellopack-panel-title">
					<svg class="hellopack-layout-icon">
						<use
								xlink:href="<?php echo HELLOPACK_CLIENT_URI . 'images/sprite.svg'; ?>?v=<?php echo HELLOPACK_CLIENT_VERSION; ?>#hellopack-layout-icon">
						</use>
					</svg>
					<?php esc_html_e( 'Themes', 'hellopack-client' ); ?>
				</h1>

				<p><?php esc_html_e( 'Here you can find the latest version of the themes in the HelloPack repository. You can also install and update them.', 'hellopack-client' ); ?>
				</p>
			</div>
	</div>

	<div class="hellopack-client-blocks">
			<?php
			if ( ! empty( $themes ) ) {
				hellopack_client_themes_column( 'active' );
				hellopack_client_themes_column( 'installed' );
				hellopack_client_themes_column( 'install' );
			}
			?>
	</div>
</div>
