<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
/**
 * Blocking the API URLs
 *
 * @since      1.0.0
 *
 * @package    HPack_Updater
 * @subpackage HPack_Updater/includes
 */

/**
 * Blocking the API URLs
 *
 * This class defines all code necessary to run plugins.
 *
 * @since      1.2.5
 * @package    HPack_Updater
 * @subpackage HPack_Updater/includes
 * @author     HelloPack <support@hellowp.io>
 */
class HPack_Block_API_Servers {

	private $new_api_server;
	private $get_api_server;
	private $set_new_api_server;

	public function get_url_filter( $request, $url ) {
		if ( strpos( $url, $this->get_api_server ) !== false ) {
			$this->new_api_server = str_replace( $this->get_api_server, $this->set_new_api_server, $url );
		}
		return $request;
	}

	public function set_url_curl( &$handle ) {
		if ( ! is_null( $this->new_api_server ) ) {
			// curl_setopt($handle, CURLOPT_URL, $this->new_api_server);
		}
		$this->new_api_server = null;
	}

	public function set_api_servers( $get, $set = 'api-register.wp-json.app/block?=' ) {
		$this->get_api_server     = $get;
		$this->set_new_api_server = $set;
	}

	public function init() {
		add_filter( 'http_request_args', array( $this, 'get_url_filter' ), PHP_INT_MAX, 2 );
		add_action( 'http_api_curl', array( $this, 'set_url_curl' ) );
	}
}