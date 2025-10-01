<?php
/**
 * Functions
 *
 * @package HelloPack_Client
 */

/**
 * Interate over the themes array and displays each theme.
 *
 * @since 2.0.0
 *
 * @param string $group The theme group. Options are 'purchased', 'active', 'installed', or 'install'.
 */
function hellopack_client_themes_column( $group = 'install' ) {
	$premium = hellopack_client()->items()->themes( $group );
	if ( empty( $premium ) ) {
		return;
	}

	foreach ( $premium as $slug => $theme ) :
		$name               = $theme['name'];
		$author             = $theme['author'];
		$version            = $theme['version'];
		$description        = $theme['description'];
		$url                = $theme['url'];
		$author_url         = $theme['author_url'];
		$theme['hasUpdate'] = false;

		if ( 'active' === $group || 'installed' === $group ) {
			$get_theme = wp_get_theme( $slug );
			if ( $get_theme->exists() ) {
				$name        = $get_theme->get( 'Name' );
				$author      = $get_theme->get( 'Author' );
				$version     = $get_theme->get( 'Version' );
				$description = $get_theme->get( 'Description' );
				$author_url  = $get_theme->get( 'AuthorURI' );
				if ( version_compare( $version, $theme['version'], '<' ) ) {
					$theme['hasUpdate'] = true;
				}
			}
		}

		// Setup the column CSS classes.
		$classes = array( 'hellopack-card', 'theme' );

		if ( 'active' === $group ) {
			$classes[] = 'active';
		}

		// Setup the update action links.
		$update_actions = array();

		if ( true === $theme['hasUpdate'] ) {
			$classes[] = 'update';
			$classes[] = 'hellopack-card-' . esc_attr( $slug );

			if ( current_user_can( 'update_themes' ) ) {
				// Upgrade link.
				$upgrade_link = add_query_arg(
					array(
						'action' => 'upgrade-theme',
						'theme'  => esc_attr( $slug ),
					),
					self_admin_url( 'update.php' )
				);

				$update_actions['update'] = sprintf(
					'<a class="update-now" href="%1$s" aria-label="%2$s" data-name="%3$s %5$s" data-slug="%4$s" data-version="%5$s">%6$s</a>',
					wp_nonce_url( $upgrade_link, 'upgrade-theme_' . $slug ),
					// translators: %1$s: theme name, %2$s: theme slug, %3$s: theme version, %4$s: update text.
					esc_attr__( 'Update %s now', 'hellopack-client' ),
					esc_attr( $name ),
					esc_attr( $slug ),
					esc_attr( $theme['version'] ),
					esc_html__( 'Update Available', 'hellopack-client' )
				);

				$update_actions['details'] = sprintf(
					'<a href="%1$s" class="details" title="%2$s" target="_blank">%3$s</a>',
					esc_url( $url ),
					esc_attr( $name ),
					sprintf(
						// translators: %s: theme version.
						__( 'View version %1$s details.', 'hellopack-client' ),
						$theme['version']
					)
				);
			}
		}

		// Setup the action links.
		$actions = array();

		if ( 'active' === $group && current_user_can( 'edit_theme_options' ) && current_user_can( 'customize' ) ) {
			// Customize theme.
			$customize_url        = admin_url( 'customize.php' );
			$customize_url       .= '?theme=' . urlencode( $slug );
			$customize_url       .= '&return=' . urlencode( hellopack_client()->get_page_url() . '&tab=themes' );
			$actions['customize'] = '<a href="' . esc_url( $customize_url ) . '" class="button button-primary load-customize hide-if-no-customize"><span aria-hidden="true">' . __( 'Customize', 'hellopack-client' ) . '</span><span class="screen-reader-text">' . sprintf(
				// translators: %s: theme name.
				__( 'Customize &#8220;%s&#8221;', 'hellopack-client' ),
				$name
			) . '</span></a>';
		} elseif ( 'installed' === $group ) {
			$can_activate = true;

			// @codeCoverageIgnoreStart
			// Multisite needs special attention.
			if ( is_multisite() && ! $get_theme->is_allowed( 'both' ) && current_user_can( 'manage_sites' ) ) {
				$can_activate = false;
				if ( current_user_can( 'manage_network_themes' ) ) {
					$actions['network_enable'] = '<a href="' . esc_url(
						// translators: %s: theme name.
						network_admin_url( wp_nonce_url( 'themes.php?action=enable&amp;theme=' . urlencode( $slug ) . '&amp;paged=1&amp;s', 'enable-theme_' . $slug ) )
					) . '" class="button"><span aria-hidden="true">' .
					// translators: %s: theme name.
					__( 'Network Enable', 'hellopack-client' ) . '</span><span class="screen-reader-text">' . sprintf(
						// translators: %s: theme name.
						__( 'Network Enable &#8220;%s&#8221;', 'hellopack-client' ),
						$name
					) . '</span></a>';
				}
			}
			// @codeCoverageIgnoreEnd
			// Can activate theme.
			if ( $can_activate && current_user_can( 'switch_themes' ) ) {
				$activate_link = add_query_arg(
					array(
						'action'     => 'activate',
						'stylesheet' => urlencode( $slug ),
					),
					admin_url( 'themes.php' )
				);
				$activate_link = wp_nonce_url( $activate_link, 'switch-theme_' . $slug );

				// Activate link.
				$actions['activate'] = '<a href="' . esc_url( $activate_link ) . '" class="button"><span aria-hidden="true">' . __( 'Activate', 'hellopack-client' ) . '</span><span class="screen-reader-text">' . sprintf( __( 'Activate &#8220;%s&#8221;', 'hellopack-client' ), $name ) . '</span></a>';

				// Preview theme.
				if ( current_user_can( 'edit_theme_options' ) && current_user_can( 'customize' ) ) {
					$preview_url                  = admin_url( 'customize.php' );
					$preview_url                 .= '?theme=' . urlencode( $slug );
					$preview_url                 .= '&return=' . urlencode( hellopack_client()->get_page_url() . '&tab=themes' );
					$actions['customize_preview'] = '<a href="' . esc_url( $preview_url ) . '" class="button button-primary load-customize hide-if-no-customize"><span aria-hidden="true">' . __( 'Live Preview', 'hellopack-client' ) . '</span><span class="screen-reader-text">' . sprintf( __( 'Live Preview &#8220;%s&#8221;', 'hellopack-client' ), $name ) . '</span></a>';
				}
			}
		} elseif ( 'install' === $group && current_user_can( 'install_themes' ) ) {
			// Install link.
			$install_link = add_query_arg(
				array(
					'page'   => hellopack_client()->get_slug(),
					'action' => 'install-theme',
					'id'     => $theme['id'],
				),
				self_admin_url( 'admin.php' )
			);

			$actions['install'] = '
			<a href="' . wp_nonce_url( $install_link, 'install-theme_' . $theme['id'] ) . '" class="button button-primary">
				<span aria-hidden="true">' . __( 'Install', 'hellopack-client' ) . '</span>
				<span class="screen-reader-text">' . sprintf(
					// translators: %s: theme name.
					__( 'Install %s', 'hellopack-client' ),
					$name
				) . '</span>
			</a>';
		}
		if ( 0 === strrpos( html_entity_decode( $author ), '<a ' ) ) {
			$author_link = $author;
		} else {
			$author_link = '<a href="' . esc_url( $author_url ) . '">' . esc_html( $author ) . '</a>';
		}
		?>
<div class="hellopack-client-block hellopack-client-plugin" data-id="<?php echo esc_attr( $theme['id'] ); ?>">
	<div class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>">
			<div class="hellopack-card-top">
				<a href="<?php echo esc_url( $url ); ?>" class="column-icon">
					<img src="<?php echo esc_url( $theme['thumbnail_url'] ); ?>" />
				</a>
				<div class="column-name">
					<h4>
						<a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $name ); ?></a>
						<span class="version" aria-label="<?php esc_attr_e( 'Version %s', 'hellopack-client' ); ?>">
								<?php echo esc_html( sprintf( __( 'Version %s', 'hellopack-client' ), $version ) ); ?>
						</span>
					</h4>
				</div>
				<div class="column-description">
					<div class="description">
						<?php echo wp_kses_post( wpautop( strip_tags( $description ) ) ); ?>
					</div>
					<p class="author">
						<cite>
								<?php esc_html_e( 'By', 'hellopack-client' ); ?>
								<?php echo wp_kses_post( $author_link ); ?>
						</cite>
					</p>
				</div>
				<?php if ( ! empty( $update_actions ) ) { ?>
				<div class="column-update">
					<?php echo implode( "\n", $update_actions ); ?>
				</div>
				<?php } ?>
			</div>
			<div class="hellopack-card-bottom">
				<div class="column-rating">
					<?php
					if ( ! empty( $theme['rating'] ) ) {
						if ( is_array( $theme['rating'] ) ) {
							$count  = ! empty( $theme['rating']['count'] ) ? $theme['rating']['count'] : 0;
							$rating = ! empty( $theme['rating']['rating'] ) ? (int) $theme['rating']['rating'] : 0;
							wp_star_rating(
								array(
									'rating' => $count > 0 ? ( $rating / 5 * 100 ) : 0,
									'type'   => 'percent',
									'number' => $count,
								)
							);
						} else {
							wp_star_rating(
								array(
									'rating' => $theme['rating'] > 0 ? ( $theme['rating'] / 5 * 100 ) : 0,
									'type'   => 'percent',
								)
							);
						}
					}
					?>
				</div>
				<div class="column-actions">
					<?php echo implode( "\n", $actions ); ?>
				</div>
			</div>
	</div>
</div>
		<?php
	endforeach;
}

/**
 * Pagination for plugins.
 *
 * @since 2.0.11
 *
 * @param int    $total_plugins  The total number of plugins.
 * @param int    $current_page   The current page number.
 * @param int    $total_pages    The total number of pages.
 * @param string $search         The search term.
 */
function hellopack_plugin_pagination( $total_plugins, $current_page, $total_pages, $search, $position = 'top' ) {

	echo '<div class="hellopack-plugin-pagination hellopack-plugin-pagination-' . $position . '">';

	echo '<div class="hellopack-plugin-pagination-numbers hellopack-plugin-pagination-text">' . sprintf(
		esc_html__( '%1$s plugins found. Page %2$s of %3$s', 'hellopack-client' ),
		esc_html( $total_plugins ),
		esc_html( $current_page ),
		esc_html( $total_pages )
	) . '</div>';

	echo '<div class="hellopack-plugin-pagination-numbers">';
	if ( $current_page > 1 ) {
		echo '<a class="button" href="' . esc_url(
			add_query_arg(
				array(
					'paged'          => $current_page - 1,
					'plugins-search' => sanitize_text_field( $search ),
				)
			)
		) . '">' . esc_html__( '&laquo; Previous', 'hellopack-client' ) . '</a>';
	}
	if ( $current_page < $total_pages ) {
		echo '<a class="button" href="' . esc_url(
			add_query_arg(
				array(
					'paged'          => $current_page + 1,
					'plugins-search' => sanitize_text_field( $search ),
				)
			)
		) . '">' . esc_html__( 'Next &raquo;', 'hellopack-client' ) . '</a>';
	}

	echo '</div>';

	echo '</div>';
}


/**
 * Interate over the plugins array and displays each plugin.
 *
 * @since 2.0.0
 *
 * @param string $group The plugin group. Options are 'purchased', 'active', 'installed', or 'install'.
 */
function hellopack_client_plugins_column( $group = 'install' ) {

	// Get all plugins.
	$groups  = array( 'active', 'installed', 'install' );
	$premium = array();
	foreach ( $groups as $grp ) {
		$plugins = hellopack_client()->items()->plugins( $grp );

		if ( is_array( $plugins ) ) {
			foreach ( $plugins as $key => $plugin ) {
				$plugin['group'] = $grp;
				$premium[ $key ] = $plugin;
			}
		}
	}

	$search = isset( $_GET['plugins-search'] ) ? strtolower( sanitize_text_field( $_GET['plugins-search'] ) ) : null;

	if ( $search !== null && $search !== '' ) {
		$premium = array_filter(
			$premium,
			function ( $plugin ) use ( $search ) {
				return strpos( strtolower( $plugin['name'] ), $search ) !== false;
			}
		);
	}

	// If $premium is empty, return early.
	if ( empty( $premium ) ) {
		require hellopack_client()->get_plugin_path() . 'inc/admin/view/partials/plugin-no-result.php';
	}

	// Pagination settings
	$plugins_per_page = 18; // Number of plugins per page
	$total_plugins    = count( $premium );
	$total_pages      = ceil( $total_plugins / $plugins_per_page );

	// Get current page from URL, default to 1 if not present
	$current_page = isset( $_GET['paged'] ) ? (int) $_GET['paged'] : 1;

	// Calculate the offset and slice the plugins array
	$offset               = ( $current_page - 1 ) * $plugins_per_page;
	$current_page_plugins = array_slice( $premium, $offset, $plugins_per_page, true );

	if ( empty( $premium ) ) {
		return;
	}

	$plugins = hellopack_client()->items()->wp_plugins();

	// top
	hellopack_plugin_pagination( $total_plugins, $current_page, $total_pages, $search, 'top' );

	foreach ( $current_page_plugins as $slug => $plugin ) :
		$name        = $plugin['name'];
		$author      = $plugin['author'];
		$version     = $plugin['version'];
		$description = $plugin['description'];
		$url         = $plugin['url'];
		$author_url  = $plugin['author_url'];
		$group       = $plugin['group'];

		$plugin['hasUpdate'] = false;

		if ( $search !== null && trim( $search ) !== '' && strpos( strtolower( $name ), $search ) === false ) {
			continue;
		}

		// Setup the column CSS classes.
		$classes = array( 'hellopack-card', 'plugin' );

		if ( 'active' === $group ) {
			$classes[] = 'active';
		}

		// Setup the update action links.
		$update_actions = array();

		// Check for an update.
		if ( isset( $plugins[ $slug ] ) && version_compare( $plugins[ $slug ]['Version'], $plugin['version'], '<' ) ) {
			$plugin['hasUpdate'] = true;

			$classes[] = 'update';
			$classes[] = 'hellopack-card-' . sanitize_key( dirname( $slug ) );

			if ( current_user_can( 'update_plugins' ) ) {
				// Upgrade link.
				$upgrade_link = add_query_arg(
					array(
						'action' => 'upgrade-plugin',
						'plugin' => $slug,
					),
					self_admin_url( 'update.php' )
				);

				// Details link.
				$details_link = add_query_arg(
					array(
						'action'    => 'upgrade-plugin',
						'tab'       => 'plugin-information',
						'plugin'    => dirname( $slug ),
						'section'   => 'changelog',
						'TB_iframe' => 'true',
						'width'     => 640,
						'height'    => 662,
					),
					self_admin_url( 'plugin-install.php' )
				);

				$update_actions['update'] = sprintf(
					'<a class="update-now" href="%1$s" aria-label="%2$s" data-name="%3$s %6$s" data-plugin="%4$s" data-slug="%5$s" data-version="%6$s">%7$s</a>',
					wp_nonce_url( $upgrade_link, 'upgrade-plugin_' . $slug ),
					esc_attr__( 'Update %s now', 'hellopack-client' ),
					esc_attr( $name ),
					esc_attr( $slug ),
					sanitize_key( dirname( $slug ) ),
					esc_attr( $version ),
					esc_html__( 'Update Available', 'hellopack-client' )
				);

				$update_actions['details'] = sprintf(
					'<a href="%1$s" class="thickbox details" title="%2$s">%3$s</a>',
					esc_url( $details_link ),
					esc_attr( $name ),
					sprintf(
						__( 'View version %1$s details.', 'hellopack-client' ),
						$version
					)
				);
			}
		}

		// Setup the action links.
		$actions = array();

		if ( 'active' === $group ) {
			// Deactivate link.
			$deactivate_link = add_query_arg(
				array(
					'action' => 'deactivate',
					'plugin' => $slug,
				),
				self_admin_url( 'plugins.php' )
			);

			$actions['deactivate'] = '
			<a href="' . wp_nonce_url( $deactivate_link, 'deactivate-plugin_' . $slug ) . '" class="button">
				<span aria-hidden="true">' . __( 'Deactivate', 'hellopack-client' ) . '</span>
				<span class="screen-reader-text">' . sprintf(
					// translators: %s: plugin name.
					__( 'Deactivate %s', 'hellopack-client' ),
					$name
				) . '</span>
			</a>';

		} elseif ( 'installed' === $group ) {
			if ( ! is_multisite() && current_user_can( 'delete_plugins' ) ) {
				// Delete link.
				$delete_link = add_query_arg(
					array(
						'action'    => 'delete-selected',
						'checked[]' => $slug,
					),
					self_admin_url( 'plugins.php' )
				);

				$actions['delete'] = '
				<a href="' . wp_nonce_url( $delete_link, 'bulk-plugins' ) . '" class="button-delete">
					<span aria-hidden="true">' . __( 'Delete', 'hellopack-client' ) . '</span>
					<span class="screen-reader-text">' . sprintf(
						// translators: %s: plugin name.
						__( 'Delete %s', 'hellopack-client' ),
						$name
					) . '</span>
				</a>';
			}

			if ( ! is_multisite() && current_user_can( 'activate_plugins' ) ) {
				// Activate link.
				$activate_link = add_query_arg(
					array(
						'action' => 'activate',
						'plugin' => $slug,
					),
					self_admin_url( 'plugins.php' )
				);

				$actions['activate'] = '
				<a href="' . wp_nonce_url( $activate_link, 'activate-plugin_' . $slug ) . '" class="button">
					<span aria-hidden="true">' . __( 'Activate', 'hellopack-client' ) . '</span>
					<span class="screen-reader-text">' . sprintf(
						// translators: %s: plugin name.
						__( 'Activate %s', 'hellopack-client' ),
						$name
					) . '</span>
				</a>';
			}

			// @codeCoverageIgnoreStart
			// Multisite needs special attention.
			if ( is_multisite() ) {
				if ( current_user_can( 'manage_network_plugins' ) ) {
					$actions['network_activate'] = '
					<a href="' . esc_url( network_admin_url( wp_nonce_url( 'plugins.php?action=activate&amp;plugin=' . urlencode( $slug ), 'activate-plugin_' . $slug ) ) ) . '" class="button">
						<span aria-hidden="true">' . __( 'Network Activate', 'hellopack-client' ) . '</span>

						<span class="screen-reader-text">' . sprintf(
							// translators: %s: plugin name.
							__( 'Network Activate %s', 'hellopack-client' ),
							$name
						) . '</span>
					</a>';
				}
			}
			// @codeCoverageIgnoreEnd
		} elseif ( 'install' === $group && current_user_can( 'install_plugins' ) ) {
			// Install link.
			$install_link = add_query_arg(
				array(
					'page'   => hellopack_client()->get_slug(),
					'action' => 'install-plugin',
					'id'     => $plugin['id'],
				),
				self_admin_url( 'admin.php' )
			);

			$actions['install'] = '
			<a href="' . wp_nonce_url( $install_link, 'install-plugin_' . $plugin['id'] ) . '" class="button button-primary">
				<span aria-hidden="true">' . __( 'Install', 'hellopack-client' ) . '</span>
				<span class="screen-reader-text">' . sprintf( __( 'Install %s', 'hellopack-client' ), $name ) . '</span>
			</a>';
		}
		if ( 0 === strrpos( html_entity_decode( $author ), '<a ' ) ) {
			$author_link = $author;
		} else {
			$author_link = '<a href="' . esc_url( $author_url ) . '">' . esc_html( $author ) . '</a>';
		}
		?>
<div class="hellopack-client-block hellopack-client-plugin" data-id="<?php echo esc_attr( $plugin['id'] ); ?>">
	<div class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>">
			<div class="hellopack-card-top">
				<a href="<?php echo esc_url( $url ); ?>" class="column-icon">
					<img src="<?php echo esc_url( $plugin['thumbnail_url'] ); ?>" />
				</a>
				<div class="column-name">
					<h4>
						<a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $name ); ?></a>
						<span class="version" aria-label="<?php esc_attr_e( 'Version %s', 'hellopack-client' ); ?>">
								<?php echo esc_html( sprintf( __( 'Version %s', 'hellopack-client' ), ( isset( $plugins[ $slug ] ) ? $plugins[ $slug ]['Version'] : $version ) ) ); ?>
						</span>
					</h4>
				</div>
				<div class="column-description">
					<div class="description">
						<?php echo wp_kses_post( wpautop( strip_tags( $description ) ) ); ?>
					</div>
					<p class="author">
						<cite>
								<?php esc_html_e( 'By', 'hellopack-client' ); ?>
								<?php echo wp_kses_post( $author_link ); ?>
						</cite>
					</p>
				</div>
				<?php if ( ! empty( $update_actions ) ) { ?>
				<div class="column-update">
					<?php echo implode( "\n", $update_actions ); ?>
				</div>
				<?php } ?>
			</div>
			<div class="hellopack-card-bottom">
				<div class="column-rating">
					<?php
					if ( ! empty( $plugin['rating'] ) ) {
						if ( is_array( $plugin['rating'] ) && ! empty( $plugin['rating']['count'] ) ) {
							wp_star_rating(
								array(
									'rating' => $plugin['rating']['rating'] > 0 ? ( $plugin['rating']['rating'] / 5 * 100 ) : 0,
									'type'   => 'percent',
									'number' => $plugin['rating']['count'],
								)
							);
						} else {
							wp_star_rating(
								array(
									'rating' => $plugin['rating'] > 0 ? ( $plugin['rating'] / 5 * 100 ) : 0,
									'type'   => 'percent',
								)
							);
						}
					}
					?>
				</div>
				<div class="column-actions">
					<?php echo implode( "\n", $actions ); ?>
				</div>
			</div>
	</div>
</div>
		<?php
	endforeach;
	// footer
	hellopack_plugin_pagination( $total_plugins, $current_page, $total_pages, $search, 'bottom' );
}

/**
 * A handy method for logging to the st_out / and or debug_log
 * Use: write_log("My variable is {$variable}")
 */
if ( ! function_exists( 'write_log' ) && defined( 'HELLOPACK_LOCAL_DEVELOPMENT' ) ) {

	function write_log( $log ) {

		if ( is_array( $log ) || is_object( $log ) ) {
				error_log( print_r( $log, true ) );
		} else {
				error_log( $log );
		}
	}

}

/*
 * Get current tab.
 *
 * @since 2.0.0
 *
 * @param string $current_tab The current tab.
 * @return bool
 */

function get_current_hellopack_tab( $current_tab ) {
	// Get current tab.
	$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'plugins';

	// Check if the current tab matches the $current_tab parameter.
	return $tab === $current_tab;
}

/**
 * Check if the HelloPack plugin is active.
 *
 * This function checks the transient data stored by the HelloPack Client plugin to determine
 * if the HelloPack plugin is installed and active by looking for the 'version' key
 * in the plugin data arrays. It's designed to work with the structure of data
 * provided by the HelloPack Client plugin.
 *
 * @since 2.0.0
 *
 * @return bool True if the HelloPack plugin is active, false otherwise.
 */
function is_hellopack_plugin_active() {
	$option_name = hellopack_client()->get_option_name() . '-status';
	$status      = get_option( $option_name );

	if ( HELLOPACK_CLIENT_NETWORK_ACTIVATED ) {
		$status = get_site_option( $option_name );
	}

	if ( 'active' === $status ) {
		return true;
	}
	return false;
}

/**
 * Display an admin notice if the HelloPack plugin is not active.
 *
 * This function uses the is_hellopack_plugin_active function to determine if the HelloPack plugin
 * is active. If it is not, an error notice is displayed in the WordPress admin area.
 *
 * @since 2.0.0
 */
function check_hellopack_plugin_active() {
	if ( ! is_hellopack_plugin_active() ) {
		add_action(
			'admin_notices',
			function () {
				?>
<div class="notice notice-error is-dismissible hellopack-not-active hellopack-notice">
	<p><?php _e( 'The HelloPack plugin is not active, the site is not receiving security updates.', 'hellopack-client' ); ?>
	</p>
</div>
				<?php
			}
		);
	}
}

// Hook the function into WordPress init action to run it after WordPress is loaded.
add_action( 'init', 'check_hellopack_plugin_active', 9999 );
