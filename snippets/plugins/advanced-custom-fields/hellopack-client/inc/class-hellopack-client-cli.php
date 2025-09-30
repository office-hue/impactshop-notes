<?php
/**
 * HelloPack Client CLI
 *
 * @package HelloPack_Client
 * @since 2.0.16
 */

if ( ! class_exists( 'HelloPack_Client_CLI' ) ) :

	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		/**
		 * WP CLI command handling for the HelloPack license.
		 */
		class HelloPack_Client_CLI {
			/**
			 * Activate the license.
			 *
			 * ## PARAMETERS
			 *
			 * <license-key>
			 * : The license key for HelloPack.
			 *
			 * ## EXAMPLES
			 *
			 *     wp hellopack license activate abcdef123456
			 *
			 * @when after_wp_load
			 * @param array $args       The positional arguments.
			 * @param array $assoc_args The associative arguments.
			 */
			public function activate( $args, $assoc_args ) {
				list($license_key) = $args;

				// Check if the license key is exactly 42 characters long.
				if ( strlen( $license_key ) !== 42 ) {
					WP_CLI::error( __( 'Incorrect API key – the license key must be exactly 42 characters long.', 'hellopack-client' ) );
					return;
				}

				update_option(
					'hellopack_client',
					array(
						'token' => $license_key,
					)
				);

				// Check for available plugin updates.
				$update_check = WP_CLI::runcommand( 'plugin list --update=available --format=count', array( 'return' => 'all' ) );
				if ( isset( $update_check->stdout ) && intval( $update_check->stdout ) > 0 ) {
					WP_CLI::success( sprintf( __( 'Plugin update data loaded. Number of available updates: %d', 'hellopack-client' ), intval( $update_check->stdout ) ) );
				} else {
					WP_CLI::log( __( 'Plugin update data loaded. No available updates.', 'hellopack-client' ) );
				}

				// Run the command and store the result.
				$result = WP_CLI::runcommand( 'hellopack license status', array( 'return' => 'all' ) );

				if ( ! empty( $result->stderr ) ) {
					// If there is an error (stderr is not empty), use WP_CLI::error to output an error message.
					WP_CLI::error( 'The HelloPack API key activation has failed.' );
				} else {
					// If there is no error (stderr is empty), use WP_CLI::success to output a success message.
					WP_CLI::success( 'The HelloPack API key is activated.' );
				}
			}

			/**
			 * Deactivate the license.
			 *
			 * ## EXAMPLES
			 *
			 *     wp hellopack license deactivate
			 *
			 * @when after_wp_load
			 */
			public function deactivate() {

				$activation_status = get_option( 'hellopack_client-status' );

				// Check if the activation status is 'active'.
				if ( 'inactive' === $activation_status ) {
					WP_CLI::error( sprintf( __( 'HelloPack is already in an inactive status.', 'hellopack-client' ) ) );
					return;
				}

				delete_option( 'hellopack_client' );
				WP_CLI::runcommand( 'plugin list --update=available --format=count', array( 'return' => 'all' ) );
				WP_CLI::success( __( 'HelloPack license deactivated.', 'hellopack-client' ) );
			}

			/**
			 * Query the status of the license.
			 *
			 * ## EXAMPLES
			 *
			 *     wp hellopack license status
			 *
			 * @when after_wp_load
			 */
			public function status() {

				$options = get_option( 'hellopack_client', array() );

				if ( empty( $options['token'] ) ) {
					WP_CLI::error( __( 'The HelloPack license is not activated.', 'hellopack-client' ) );
				} else {
					WP_CLI::success( sprintf( __( 'The HelloPack license is active. Key: %s', 'hellopack-client' ), $options['token'] ) );
				}
			}
		}

		WP_CLI::add_command( 'hellopack license', 'HelloPack_Client_CLI' );
	}

endif;
