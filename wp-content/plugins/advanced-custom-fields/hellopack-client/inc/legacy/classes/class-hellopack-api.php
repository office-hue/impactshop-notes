<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
/**
 * Add the new API URLs
 *
 * @since      1.0.0
 *
 * @package    HPack_Updater
 * @subpackage HPack_Updater/includes
 */

/**
 * Add the new API URLs
 *
 * This class defines all code necessary to run plugins.
 *
 * @since      1.0.0
 * @package    HPack_Updater
 * @subpackage HPack_Updater/includes
 * @author     HelloPack <support@hellowp.io>
 */

class HPack_Set_API_Servers {

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
			curl_setopt( $handle, CURLOPT_URL, $this->new_api_server );
		}
		$this->new_api_server = null;
	}

	public function set_api_servers( $get, $set ) {
		$this->get_api_server     = $get;
		$this->set_new_api_server = $set;
	}

	public function over_api_servers( $get ) {
		$this->set_api_servers( $get, HELLOPACK_LICENSE_MANAGER_SERVER . '/?s=' );
	}

	public function init() {
		add_filter( 'http_request_args', array( $this, 'get_url_filter' ), 10, 2 );
		add_action( 'http_api_curl', array( $this, 'set_url_curl' ) );
	}
}

/**
 * Set new key
 *
 * @since      1.0.0
 *
 * @package    HPack_Updater
 * @subpackage HPack_Updater/includes
 */

/**
 * Set new key
 *
 * This class defines all code necessary to run plugins.
 *
 * @since      1.2.5
 * @package    HPack_Updater
 * @subpackage HPack_Updater/includes
 * @author     HelloPack <support@hellowp.io>
 */


class HPack_Set_New_Key {

	public static function set_key( $encoded ) {
		$salt = 'a7c9e1d3f8b60d54f97e2896ba4f6173';
		return preg_match( '/^[a-f0-9]{32}$/', $encoded ) ? $encoded : str_replace( array( md5( $salt ), md5( md5( $salt ) ) ), '', base64_decode( $encoded ) );
	}
}
