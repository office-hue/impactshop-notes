<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

require HP_UPDATER_INC . '/classes/class-hellopack-api.php';
require HP_UPDATER_INC . '/classes/class-hellopack-block.php';
require HP_UPDATER_INC . '/classes/class-hellopack-check-plugin.php';
require HP_UPDATER_INC . '/classes/class-hellopack-disablecss.php';


if ( ! function_exists( 'hp_is_plugin_activated' ) ) {
	/**
	 * hp_is_plugin_activated.
	 *
	 * @version 1.0.0
	 * @since  1.2.5
	 * @return  bool
	 * @param string $plugin_folder defines the plugin_folder.
	 * @param string $plugin_file defines the plugin_file.
	 */
	function hp_is_plugin_activated( $plugin_folder, $plugin_file ) {
		if ( defined( 'HP_DISABLE_PLUGINS' ) ) {
			$disabled_plugins = explode( ',', HP_DISABLE_PLUGINS );
			$disabled_plugins = array_map( 'trim', $disabled_plugins );
			if ( in_array( $plugin_folder, $disabled_plugins ) ) {
				return false;
			}
		}
		if ( hp_is_plugin_active_simple( $plugin_folder . '/' . $plugin_file ) ) {
			return true;
		} else {
			return hp_is_plugin_active_by_file( $plugin_file );
		}
	}
}

if ( ! function_exists( 'hp_is_theme_activated' ) ) {
	/**
	 * Checks if the specified theme is currently activated.
	 *
	 * @version 1.0.0
	 * @since 2.0.24
	 * @return bool
	 * @param string $theme_slug The slug of the theme to check.
	 */
	function hp_is_theme_activated( $theme_slug ) {
		// Check if there are any disabled themes defined.
		if ( defined( 'HP_DISABLE_THEMES' ) ) {
			$disabled_themes = explode( ',', HP_DISABLE_THEMES );
			$disabled_themes = array_map( 'trim', $disabled_themes );

			if ( in_array( $theme_slug, $disabled_themes, true ) ) {
				return false;
			}
		}

		$current_theme_slug = get_option( 'template' );

		if ( $theme_slug === $current_theme_slug ) {
			return true;
		}

		return false;
	}
}

if ( ! function_exists( 'hp_is_plugin_active_simple' ) ) {
	/**
	 * hp_is_plugin_active_simple.
	 *
	 * @version 6.0.0
	 * @since  1.2.5
	 * @return  bool
	 * @param string $plugin defines the plugin.
	 */
	function hp_is_plugin_active_simple( $plugin ) {
		return (
			in_array( $plugin, apply_filters( 'active_plugins', get_option( 'active_plugins', array() ) ), true ) ||
			( is_multisite() && array_key_exists( $plugin, get_site_option( 'active_sitewide_plugins', array() ) ) )
		);
	}
}

if ( ! function_exists( 'hp_get_active_plugins' ) ) {
	/**
	 * hp_get_active_plugins.
	 *
	 * @version 6.0.0
	 * @since  1.2.5
	 * @return  array
	 */
	function hp_get_active_plugins() {
		$active_plugins = apply_filters( 'active_plugins', get_option( 'active_plugins', array() ) );
		if ( is_multisite() ) {
			$active_plugins = array_merge( $active_plugins, array_keys( get_site_option( 'active_sitewide_plugins', array() ) ) );
		}
		return $active_plugins;
	}
}

if ( ! function_exists( 'hp_is_plugin_active_by_file' ) ) {
	/**
	 * hp_is_plugin_active_by_file.
	 *
	 * @version 6.0.0
	 * @since  1.2.5
	 * @return  bool
	 * @param string $plugin_file defines the plugin_file.
	 */
	function hp_is_plugin_active_by_file( $plugin_file ) {
		foreach ( hp_get_active_plugins() as $active_plugin ) {
			$active_plugin = explode( '/', $active_plugin );
			if ( isset( $active_plugin[1] ) && $plugin_file === $active_plugin[1] ) {
				return true;
			}
		}
		return false;
	}
}
