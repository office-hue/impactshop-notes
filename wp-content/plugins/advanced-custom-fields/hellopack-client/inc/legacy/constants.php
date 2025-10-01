<?php


define( 'HP_PLUGIN_API_SERVER', 'api.wp-json.app' );
define( 'HP_PLUGIN_REGISTER_SERVER', 'api-register.wp-json.app' );
define( 'HP_PLUGIN_REGISTER_SERVER_HTTPS', 'https://api-register.wp-json.app' );
define( 'HP_PLUGIN_INSTALLER_SERVER', 'plugin-installer.wp-json.app' );
define( 'HP_UPDATER_INC', HELLOPACK_CLIENT_PATH . 'inc/legacy/' );
define( 'HP_GLOBAL_SERIAL', hellopack_client()->get_option( 'token' ) );
define( 'HP_GLOBAL_URL', get_site_url() );

// new servers and defines
define( 'HELLOPACK_LICENSE_MANAGER_SERVER', 'license.v2.wp-json.app' );


if ( ! function_exists( 'HP_check_options' ) ) {
	/**
	 * Check options
	 *
	 * @param string $name The name of the option.
	 * @param string $value The value of the option.
	 */
	function HP_check_options( $name, $value ) {
		$current_status = get_option( $name );

		if ( false !== $current_status ) {
			update_option( $name, $value );
		} else {
			add_option( $name, $value, '', 'yes' );
		}
	}

}

if ( ! function_exists( 'disable_upgrader_pre_download' ) ) {

	function disable_upgrader_pre_download() {
		remove_all_filters( 'upgrader_pre_download' );
	}
}