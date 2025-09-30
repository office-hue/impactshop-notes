<?php
/**
 * Plugins panel partial
 *
 * @package HelloPack_Client
 * @since 2.0.0
 */

$plugins = hellopack_client()->items()->plugins( 'purchased' );

?>

<div id="plugins" class="panel <?php echo empty( $plugins ) ? 'hidden' : ''; ?>">

     <div
          class="hellopack-builder-important-notice hellopack-template-builder hellopack-db-card hellopack-db-card-first">
          <div class="intro-text">
               <h1 class="hellopack-panel-title">
                    <svg class="hellopack-box-icon">
                         <use
                              xlink:href="<?php echo HELLOPACK_CLIENT_URI . 'images/sprite.svg'; ?>?v=<?php echo HELLOPACK_CLIENT_VERSION; ?>#hellopack-box-icon">
                         </use>
                    </svg>
                    <?php
					esc_html_e( 'Plugins', 'hellopack-client' );
					if ( isset( $_GET['plugins-search'] ) && ! empty( $_GET['plugins-search'] ) ) {
						$search_term = strtolower( $_GET['plugins-search'] );
						$search_term = str_replace( ' ', '-', $search_term );

						echo ': #' . esc_html( $search_term );
					}
					?>

               </h1>

               <p><?php esc_html_e( 'Here you can find the latest version of the plugins in the HelloPack repository. You can also install and update them.', 'hellopack-client' ); ?>
               </p>
          </div>

     </div>

     <?php HelloPack_Client_Admin::render_plugin_search_panel_partial(); ?>

     <div class="hellopack-client-blocks">
          <?php
			if ( ! empty( $plugins ) ) {
				hellopack_client_plugins_column( 'active' );
			}
			?>
     </div>
</div>