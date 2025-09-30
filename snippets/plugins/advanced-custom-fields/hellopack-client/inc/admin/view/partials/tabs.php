<?php
/**
 * Tabs partial
 *
 * @package HelloPack_Client
 * @since 2.0.0
 */

$tab    = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';
$themes = hellopack_client()->items()->themes( 'purchased' );
// $plugins = hellopack_client()->items()->plugins( 'purchased' );

?>
<h2 class="nav-tab-wrapper hidden">
     <?php
		// Plugins tab.
		$plugin_class = array();
	if ( ! empty( $themes ) ) {
		if ( empty( $tab ) ) {
			$tab = 'plugins';
		}
		if ( 'plugins' === $tab ) {
			$plugin_class[] = 'nav-tab-active';
		}
	} else {
		$plugin_class[] = 'hidden';
	}
		echo '<a href="#plugins" data-id="plugin" class="nav-tab ' . esc_attr( implode( ' ', $plugin_class ) ) . '">' . esc_html__( 'Plugins', 'hellopack-client' ) . '</a>';

		// Themes tab.
		$theme_class = array();
	if ( ! empty( $themes ) ) {
		if ( empty( $tab ) ) {
			$tab = 'themes';
		}
		if ( 'themes' === $tab ) {
			$theme_class[] = 'nav-tab-active';
		}
	} else {
		$theme_class[] = 'hidden';
	}
		echo '<a href="#themes" data-id="theme" class="nav-tab ' . esc_attr( implode( ' ', $theme_class ) ) . '">' . esc_html__( 'Themes', 'hellopack-client' ) . '</a>';

		// Settings tab.
		echo '<a href="#settings" class="nav-tab ' . esc_attr( 'settings' === $tab || empty( $tab ) ? 'nav-tab-active' : '' ) . '">' . esc_html__( 'Settings', 'hellopack-client' ) . '</a>';

		// Help tab.
		echo '<a href="#help" class="nav-tab ' . esc_attr( 'help' === $tab ? 'nav-tab-active' : '' ) . '">' . esc_html__( 'Help', 'hellopack-client' ) . '</a>';
	?>
</h2>