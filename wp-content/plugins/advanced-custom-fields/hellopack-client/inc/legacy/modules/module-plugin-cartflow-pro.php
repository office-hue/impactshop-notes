<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

// TODO: Need complete rewrite this file

if ( ! class_exists( 'HelloPack_Cartflows' ) ) :

	/**
	 * The main class for the Cartflows module.
	 */
	class HelloPack_Cartflows {

		/**
		 * The Cartflows API server.
		 *
		 * @var HPack_Set_API_Servers
		 */
		public function __construct() {
			if ( class_exists( 'HPack_Set_API_Servers' ) ) {
				$this->cartflows = new HPack_Set_API_Servers();
				$this->cartflows->set_api_servers( 'templates.cartflows.com', 'api-register.wp-json.app/cartflow/?rout=' );
				$this->cartflows->init();
			}

			if ( class_exists( 'HPack_Set_API_Servers' ) ) {
				$this->cartflows_registry = new HPack_Set_API_Servers();
				$this->cartflows_registry->set_api_servers( 'my.cartflows.com/?wc-api=am-software-api', 'api-register.wp-json.app/cartflow/registry?rout=' );
				$this->cartflows_registry->init();
			}

			$this->create_cartflows_options_table();
		}

		/**
		 * Create the Cartflows options table.
		 */
		public function create_cartflows_options_table() {
			$wc_am_client_cartflows = get_option( 'wc_am_client_cartflows' );
			if ( false === $wc_am_client_cartflows ) {
				add_option( 'wc_am_client_cartflows', array( 'wc_am_client_cartflows_api_key' => HP_GLOBAL_SERIAL ) );
			} elseif ( $wc_am_client_cartflows !== array( 'wc_am_client_cartflows_api_key' => HP_GLOBAL_SERIAL ) ) {
				update_option( 'wc_am_client_cartflows', array( 'wc_am_client_cartflows_api_key' => HP_GLOBAL_SERIAL ) );
			}

			$wc_am_client_cartflows_api_key = get_option( 'wc_am_client_cartflows_api_key' );
			if ( false === $wc_am_client_cartflows_api_key ) {
				add_option( 'wc_am_client_cartflows_api_key', array( 'api_key' => HP_GLOBAL_SERIAL ) );
			} elseif ( $wc_am_client_cartflows_api_key !== array( 'api_key' => HP_GLOBAL_SERIAL ) ) {
				update_option( 'wc_am_client_cartflows_api_key', array( 'api_key' => HP_GLOBAL_SERIAL ) );
			}

			$options = array(
				'_cfw_licensing__license_key'      => HP_GLOBAL_SERIAL,
				'_cfw_licensing__key_status'       => 'valid',
				'cfw_license_activation_limit'     => 500,
				'cfw_license_price_id'             => 9,
				'wc_am_client_cartflows_activated' => 'Activated',
			);

			foreach ( $options as $option_name => $option_value ) {
				if ( get_option( $option_name ) !== false ) {
					update_option( $option_name, $option_value );
				} else {
					add_option( $option_name, $option_value );
				}
			}

			$wc_am_client_cartflows_api_key = get_option( 'cartflows_license_backup_data' );
			if ( false === $wc_am_client_cartflows_api_key ) {
				add_option(
					'cartflows_license_backup_data',
					array(
						'wc_am_client_cartflows_api_key' => array( 'api_key' => HP_GLOBAL_SERIAL ),
						'wc_am_client_cartflows'         => array( 'wc_am_client_cartflows_api_key' => HP_GLOBAL_SERIAL ),
					)
				);
			} elseif ( $wc_am_client_cartflows_api_key !== array(
				'wc_am_client_cartflows_api_key' => array( 'api_key' => HP_GLOBAL_SERIAL ),
				'wc_am_client_cartflows'         => array( 'wc_am_client_cartflows_api_key' => HP_GLOBAL_SERIAL ),
			) ) {
				update_option(
					'cartflows_license_backup_data',
					array(
						'wc_am_client_cartflows_api_key' => array( 'api_key' => HP_GLOBAL_SERIAL ),
						'wc_am_client_cartflows'         => array( 'wc_am_client_cartflows_api_key' => HP_GLOBAL_SERIAL ),
					)
				);
			}
		}
	}

endif;

if ( ! function_exists( 'check_CARTFLOWS_VER_plugin_status' ) ) {
	function check_CARTFLOWS_VER_plugin_status() {
		new HelloPack_Cartflows();
	}
	if ( hp_is_plugin_activated( 'cartflows-pro', 'cartflows-pro.php' ) ) {
		add_action( 'plugins_loaded', 'check_CARTFLOWS_VER_plugin_status' );

			$cartflows = new HPack_Block_API_Servers();
			$cartflows->set_api_servers( 'my.cartflows.com/?wc-api=wc-am-api&request=update&slug=cartflows-pro&plugin_name=cartflows-pro' );
			$cartflows->init();

			$tmsplugins = new HPack_Block_API_Servers();
			$tmsplugins->set_api_servers( 'store.tms-plugins.com/api/autoupdate/info' );
			$tmsplugins->init();
	}
}
