<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * WooTax TaxCloud.
 *
 * Facilitates communication with TaxCloud API.
 *
 * @author 	Simple Sales Tax
 * @package SST
 * @since 	4.2
 */
class WC_WooTax_TaxCloud {

	/**
	 * @var string TaxCloud API endpoint.
	 * @since 4.2
	 */
	private $tc_endpoint = 'https://api.taxcloud.net/1.0/TaxCloud/';

	/**
	 * @var string API Login ID.
	 * @since 4.2
	 */
	private $login_id;

	/**
	 * @var string API Key.
	 * @since 4.2
	 */
	private $key;

	/**
	 * @var string Last error message.
	 * @since 4.2
	 */
	private $last_error;

	/**
	 * @var WC_Logger WooCommerce logger.
	 * @since 4.2
	 */
	private $logger;

	/**
	 * @var SoapClient SOAPClient.
	 * @since 4.2
	 */
	private $client;

	/**
	 * @var WC_WooTax_TaxCloud The only instance of WC_WooTax_TaxCloud.
	 * @since 4.2
	 */
	protected static $_instance;

	/**
	 * Returns the singleton instance.
	 *
	 * @since 4.4
	 *
	 * @return WC_WooTax_TaxCloud
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) )
			self::$_instance = new self();
		return self::$_instance;
	}

	/**
	 * Forbid cloning.
	 *
	 * @since 4.2
	 */
	public function __clone() {
		return;
	}

	/**
	 * Forbid deserialization.
	 *
	 * @since 4.2
	 */
	public function __wakeup() {
		return;
	}

	/**
	 * Constructor.
	 *
	 * @since 4.2
	 *
	 * @param $login_id
	 */
	public function __construct() {
		// Instantiate SoapClient
		$opts = array( 'http' => array( 'protocol_version' => '1.0' ) );
		$context = stream_context_create( $opts );
		$this->client = new SoapClient( $this->tc_endpoint, array( 
			'trace'          => true, 
			'soap_version'   => SOAP_1_2,
			'stream_context' => $context,
		) ); 

		// Set up logger
		if ( SST_LOG_REQUESTS )
			$this->logger = class_exists( 'WC_Logger' ) ? new WC_Logger() : $woocommerce->logger();
	}
	
	/**
	 * Set the API Login ID.
	 *
	 * @since 4.2
	 *
	 * @param string $id TaxCloud API Login ID.
	 */
	public function set_id( $id ) {
		$this->login_id = $id;
	} 
	
	/**
	 * Sets the API Key.
	 *
	 * @since 4.2
	 *
	 * @param string $key TaxCloud API Key.
	 */
	public function set_key( $key ) {
		$this->key = $key;
	}
	
	/**
	 * Getter for last_error.
	 *
	 * @since 4.2
	 *
	 * @return string 
	 */
	public function get_error_message() {
		return $this->last_error;
	}
	
	/**
	 * Send API request.
	 *
	 * @since 4.2
	 *
	 * @param  string $type Type of request being sent.
	 * @param  array $params Request parameters.s
	 * @return mixed
	 */
	public function send_request( $type, $params = array() ) {
		$last_error = false;

		if ( SST_LOG_REQUESTS )
			$this->logger->add( 'wootax', 'Started '. $type .' request.' );

		if ( ! $this->login_id || ! $this->key ) {
			$last_error = 'Could not make '. $type .' request: API Login ID and API Key are required.';
		} else if ( $type == 'Lookup' && ( !SST_Addresses::is_valid( $params[ 'origin' ] ) || !SST_Addresses::is_valid( $params[ 'destination' ] ) ) ) {
			$last_error = 'Could not make '. $type .' request: Valid origin and destination addresses are required.';
		} else if ( $type == 'VerifyAddress' && ! $params[ 'uspsUserID' ] ) {
			$last_error = 'Could not make '. $type .' request: A USPS Web Tools ID is required.';
		} else {
			$request = array_merge( array( 
				'apiLoginID' => $this->login_id, 
				'apiKey'     => $this->key, 
			), $params );

			try {
				$response = $this->client->$type( $request );

				if ( SST_LOG_REQUESTS ) {
					$this->logger->add( 'wootax', 'Request: '. print_r( $request, true ) );
					$this->logger->add( 'wootax', 'Response: '. print_r( $response, true ) );
				}

				// Check response for errors
				$response = $response->{$type . 'Result'}; // Doing this here should make the transition to cURL easier later on

				if ( $this->is_response_error( $response ) ) {
					$last_error = $this->last_error;
				}
			} catch ( SoapFault $e ) {
				$last_error = 'Could not make '. $type .' request due to SoapFault. It is possible that the request was not formatted correctly. Please try again.';
			}
		}
		
		if ( $last_error ) {
			$this->last_error = $last_error;

			if ( SST_LOG_REQUESTS )
				$this->logger->add( 'wootax', 'Request failed: '. $last_error );

			return false;
		} else {
			if ( SST_LOG_REQUESTS )
				$this->logger->add( 'wootax', 'Request succeeded!' );

			return $response;
		}
	}

	/**
	 * Checks if the provided response is an error
	 * 
	 * Responses will be considered "errors" if:
	 * 1. a TaxCloud error is returned, or
	 * 2. an HTTP error code is returned
	 *
	 * Stores the last error message in last_error and adds log message if appropriate
	 * 
	 * @since 4.2
	 *
	 * @param  array $resp TaxCloud response. 
	 * @return bool True if response is error, otherwise false.
	 */
	private function is_response_error( $resp ) {
		$this->last_error = false;

		// Check for bool false resp/HTTP code other than 200
		// TODO: GET RESPONSE CODE DIRECTLY FROM RESP
		if ( $resp == false || $this->last_response_code != '200' ) {
			$this->last_error = 'HTTP code '. $this->last_response_code .' received.';
		} else {
			// Check for TaxCloud error messages
			$errors = '';

			if ( isset( $resp->ResponseType ) && ( $resp->ResponseType == 'Error' || $resp->ResponseType === 0 ) ) {
				foreach ( $resp->Messages as $message ) {
					$errors .= $message->ResponseType .' - '. $message->Message .'<br />';
				}

				$errors = substr( $errors, 0, strlen( $errors ) - 6 );
			} else if ( isset( $resp->ErrDescription ) && !empty( $resp->ErrDescription ) ) {
				$errors = $resp->ErrDescription;
			}

			if ( !empty( $errors ) ) {
				$this->last_error = 'TaxCloud said: '. $errors;
			}

			if ( $this->last_error ) {
				return true;
			} else {
				return false;
			}
		}
	}
}

/**
 * Return an instance of WC_WooTax_TaxCloud. If no login ID/key provided,
 * use saved values.
 *
 * @since 4.4
 *
 * @param string $login_id API Login ID (default: null)
 * @param string $login_key API Key (default: null)
 * @return WC_WooTax_TaxCloud
 */
function TaxCloud( $login_id = null, $login_key = null ) {
	if ( ! $login_id )
		$login_id = SST()->get_option( 'tc_id' );

	if ( ! $login_key )
		$login_key = SST()->get_option( 'tc_key' );

	$instance = WC_WooTax_TaxCloud::instance();

	$instance->set_id( $login_id );
	$instance->set_key( $login_key );

	return $instance;
}