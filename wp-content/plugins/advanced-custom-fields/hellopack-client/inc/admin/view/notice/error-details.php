<?php
/**
 * Error details
 *
 * @package HelloPack_Client
 * @since 2.0.2
 */

?>
<div class="notice notice-error is-dismissible hellopack-notice">
     <p>
          <strong><?php _e( 'Additional Error Details:', 'hellopack-client' ); ?></strong><br />
          <?php printf( '%s.<br/> %s <br/> %s', esc_html( $title ), esc_html( $message ), esc_html( json_encode( $data ) ) ); ?>
     </p>
</div>