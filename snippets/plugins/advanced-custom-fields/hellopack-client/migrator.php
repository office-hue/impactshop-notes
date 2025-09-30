<?php
/**
 * HELLOPACK 1.0 TO 2.0 MIGRATOR
 *
 * @package HelloPack_Client
 * @since 2.0.9
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! function_exists( '_is_old_hellopack_client' ) ) {
	/**
	 * Checks if the old HelloPack client is active.
	 *
	 * @return bool True if the old HelloPack client is active, false otherwise.
	 */
	function _is_old_hellopack_client() {
			include_once ABSPATH . 'wp-admin/includes/plugin.php';
			$file_path = 'hellopack-client/hellopack-client.php';
			return is_plugin_active( $file_path );
	}
}

if ( _is_old_hellopack_client() ) {
	include_once ABSPATH . 'wp-admin/includes/plugin.php';
	$file_path = 'hellopack/hellopack-updater.php';

	if ( is_plugin_active( $file_path ) ) {
		deactivate_plugins( $file_path );
	}
}

/**
 * HelloPack_Client_Migrator Class
 */
class HelloPack_Client_Migrator {

	/**
	 * HelloPack_Client_Migrator Class Constructor.
	 * Initializes the migration process if necessary.
	 */
	public function __construct() {
		if ( $this->should_run_migration() ) {
			$this->update_hellopack_client_settings();
		}
	}

	/**
	 * Checks if the migration should run.
	 *
	 * @return bool True if migration should run, false otherwise.
	 */
	private function should_run_migration() {
		$migrator_complete = get_option( 'hellopack_migrator_complate' );
		$api_settings      = get_option( 'hellopack_updater_api_settings' );

		return 'yes' !== $migrator_complete || ! $api_settings;
	}

	/**
	 * Retrieves the HelloPack API key from settings.
	 *
	 * @return string|null The API key if available, null otherwise.
	 */
	private function migrator_get_hellopack_api_key() {
		$settings = get_option( 'hellopack_updater_api_settings' );

		if ( $settings && is_array( $settings ) ) {
			if ( isset( $settings['api_key'] ) ) {
				return $settings['api_key'];
			}
		}

		return null;
	}

	/**
	 * Updates the HelloPack client settings with the new API key and version.
	 */
	private function update_hellopack_client_settings() {
		$settings = get_option( 'hellopack_client' );

		if ( $settings ) {
			$settings_array = maybe_unserialize( $settings );

			$new_token = $this->migrator_get_hellopack_api_key();

			if ( $new_token ) {
				$settings_array['token']             = $new_token;
				$settings_array['installed_version'] = HELLOPACK_CLIENT_VERSION;

				if ( $new_token && strlen( $new_token ) === 42 ) {
					update_option( 'hellopack_client', $settings_array );
				}

				$this->check_for_plugin_updates();
				update_option( 'hellopack_migrator_complate', 'yes' );

			}
		}
	}
	/**
	 * Checks for plugin updates.
	 */
	private function check_for_plugin_updates() {
		wp_maybe_auto_update();
	}
}

new HelloPack_Client_Migrator();
