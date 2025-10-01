<?php
/**
 * Intro partial
 *
 * @package HelloPack_Client
 * @since 2.0.0
 */

$themes  = hellopack_client()->items()->themes( 'purchased' );
$plugins = hellopack_client()->items()->plugins( 'purchased' );

if ( ! empty( $plugins ) ) {
	$menu_class = '';
} else {
	$menu_class = 'hidden';
}
?>
<div class="col hellopack-dashboard">
	<header class="hellopack-db-header-main">
			<div class="hellopack-db-header-main-container">
				<a class="hellopack-db-logo"
					href="<?php echo esc_url( 'admin.php?page=' . hellopack_client()->get_slug() ); ?>"
					aria-label="<?php echo esc_attr__( 'Link to hellopack dashboard', 'hellopack-client' ); ?>">

					<div class="hellopack-db-logo-image">

						<?php
						if ( ! defined( 'HELLOPACK_WHITELABEL' ) ) {
							?>
						<img src="<?php echo esc_url( HELLOPACK_CLIENT_URI . 'images/logo.svg' ); ?>"
								alt="<?php esc_attr_e( 'hellopack', 'hellopack-client' ); ?>" width="115" height="25">

								<?php
						} else {
							printf( '<h4 class="hellopack-client-whitelabel-logo">%s</h4>', esc_html__( 'HelloPack', 'hellopack-client' ) );
						}
						?>

						<code id="hellopack-admin-version"><?php echo HELLOPACK_CLIENT_VERSION; ?></code>
					</div>
				</a>
				<nav class="hellopack-db-menu-main">
					<ul class="hellopack-db-menu">
						<li
								class="hellopack-db-menu-item hellopack-db-menu-item-options <?php echo esc_attr( $menu_class ); ?>">
								<a class="hellopack-db-menu-item-link <?php echo get_current_hellopack_tab( 'plugins' ) ? 'hellopack-db-active' : ''; ?>"
									href="<?php echo esc_url( 'admin.php?page=' . hellopack_client()->get_slug() . '&tab=plugins' ); ?>">
									<span class="hellopack-db-menu-item-text">
										<svg class="hellopack-box-icon">
											<use
													xlink:href="<?php echo esc_url( HELLOPACK_CLIENT_URI . 'images/sprite.svg?v=' . HELLOPACK_CLIENT_VERSION ); ?>#hellopack-box-icon">
											</use>
										</svg>
										<span
											class="hellopack-menu-item-text"><?php esc_html_e( 'Plugins', 'hellopack-client' ); ?></span>
									</span>
								</a>
						</li>
						<li
								class="hellopack-db-menu-item hellopack-db-menu-item-prebuilt-websites <?php echo esc_attr( $menu_class ); ?>">
								<a class="hellopack-db-menu-item-link <?php echo get_current_hellopack_tab( 'themes' ) ? 'hellopack-db-active' : ''; ?>"
									href="<?php echo esc_url( 'admin.php?page=' . hellopack_client()->get_slug() . '&tab=themes' ); ?>">
									<span class="hellopack-db-menu-item-text">
										<svg class="hellopack-home-icon">
											<use
													xlink:href="<?php echo esc_url( HELLOPACK_CLIENT_URI . 'images/sprite.svg?v=' . HELLOPACK_CLIENT_VERSION ); ?>#hellopack-layout-icon">
											</use>
										</svg>
										<span
											class="hellopack-menu-item-text"><?php esc_html_e( 'Themes', 'hellopack-client' ); ?></span>
									</span>
								</a>
						</li>
						<li class="hellopack-db-menu-item hellopack-db-menu-item-hellopack-studio">
								<a class="hellopack-db-menu-item-link <?php echo get_current_hellopack_tab( 'settings' ) ? 'hellopack-db-active' : ''; ?>"
									href="<?php echo esc_url( 'admin.php?page=' . hellopack_client()->get_slug() . '&tab=settings' ); ?>">
									<span class="hellopack-db-menu-item-text">
										<svg class="hellopack-settings-icon">
											<use
													xlink:href="<?php echo esc_url( HELLOPACK_CLIENT_URI . 'images/sprite.svg?v=' . HELLOPACK_CLIENT_VERSION ); ?>#hellopack-settings-icon">
											</use>
										</svg>
										<span
											class="hellopack-menu-item-text"><?php esc_html_e( 'Settings', 'hellopack-client' ); ?></span>
									</span>
								</a>
						</li>
						<li class="hellopack-db-menu-item hellopack-db-menu-item-hellopack-studio">
								<a class="hellopack-db-menu-item-link <?php echo get_current_hellopack_tab( 'help' ) ? 'hellopack-db-active' : ''; ?>"
									href="<?php echo esc_url( 'admin.php?page=' . hellopack_client()->get_slug() . '&tab=help' ); ?>">
									<span class="hellopack-db-menu-item-text">
										<svg class="hellopack-home-icon">
											<use
													xlink:href="<?php echo esc_url( HELLOPACK_CLIENT_URI . 'images/sprite.svg?v=' . HELLOPACK_CLIENT_VERSION ); ?>#hellopack-layer-icon">
											</use>
										</svg>
										<span
											class="hellopack-menu-item-text"><?php esc_html_e( 'Help', 'hellopack-client' ); ?></span>
									</span>
								</a>
						</li>
					</ul>
				</nav>
				<?php
				if ( ! defined( 'HELLOPACK_WHITELABEL' ) ) {
					echo '<a target="_blank" class="button button-primary hellopack-db-live hellopack-link-support" href="' . esc_url( 'https://hellowp.io/hu/tamogatas/' ) . '">' . esc_html__( 'Support', 'hellopack-client' ) . '</a>';
				}

				?>
			</div>
	</header>

	<h1 class="about-title hidden"></h1>

</div>
