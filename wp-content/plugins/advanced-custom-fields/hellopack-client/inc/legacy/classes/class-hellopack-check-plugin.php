<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( ! function_exists( 'hp_is_plugin_activated' ) ) {
	/**
	 * hp_is_plugin_activated.
	 *
	 * @version 6.0.0
	 * @since  1.2.5
	 * @return  bool
	 * @param string $plugin_folder defines the plugin_folder.
	 * @param string $plugin_file defines the plugin_file.
	 */
	/*
	function hp_is_plugin_activated($plugin_folder, $plugin_file)
	{
		if (hp_is_plugin_active_simple($plugin_folder . '/' . $plugin_file)) {
			return true;
		} else {
			return hp_is_plugin_active_by_file($plugin_file);
		}
	}
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