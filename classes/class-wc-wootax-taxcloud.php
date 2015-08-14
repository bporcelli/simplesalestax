<?php

/**
 * Utility for making TaxCloud API requests
 * 
 * @since 4.2
 * @package WooTax
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Prevent direct access
}

class WC_WooTax_TaxCloud {
	/** TaxCloud API Endpoint */
	private $tc_endpoint = 'https://api.taxcloud.net/1.0/?wsdl';

	/** API Login ID and key */
	private $login_id;
	private $key;

	/** Information about last request and response */
	private $last_request;
	private $last_response;
	private $last_response_code;
	private $last_error;

	/** WooCommerce logger */
	private $logger;

	/** SoapClient object */
	private $client;

	/** The only instance of WC_WooTax_TaxCloud */
	protected static $_instance;

	/**
	 * Returns main WC_WooTax_TaxCloud Instance
	 *
	 * @since 4.4
	 * @static
	 * @see TaxCloud()
	 * @return Main instance of WC_WooTax_TaxCloud class
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * Cloning is forbidden.
	 * @since 2.1
	 */
	public function __clone() {
		return;
	}

	/**
	 * Unserializing instances of this class is forbidden.
	 * @since 2.1
	 */
	public function __wakeup() {
		return;
	}

	/**
	 * Class constructor
	 *
	 * @since 4.2
	 * @param $login_id 
	 */
	public function __construct() {
		// Instantiate SoapClient
		$this->client = new SoapClient( $this->tc_endpoint, array( 
			'trace' => true, 
			'soap_version' => SOAP_1_2 
		) ); 

		// Set up logger
		if ( WT_LOG_REQUESTS ) {
			$this->logger = class_exists( 'WC_Logger' ) ? new WC_Logger() : $woocommerce->logger();
		}
	}
	
	/**
	 * Sets the API Login ID
	 *
	 * @since 4.2
	 * @param (string) $id TaxCloud API Login ID
	 */
	public function set_id( $id ) {
		$this->login_id = $id;
	} 
	
	/**
	 * Sets the API Key
	 *
	 * @since 4.2
	 * @param (string) $key TaxCloud API Key
	 */
	public function set_key( $key ) {
		$this->key = $key;
	}
	
	/**
	 * Returns the last error message
	 *
	 * @since 4.2
	 * @return last error message (string) 
	 */
	public function get_error_message() {
		return $this->last_error;
	}
	
	/**
	 * Returns the contents of the last API request
	 *
	 * @since 4.2
	 * @return last request content (string)
	 */
	public function get_last_request() {
		return $this->last_request;	
	}
	
	/**
	 * Returns the contents of the last API response
	 *
	 * @since 4.2
	 * @return last API response (string)
	 */
	public function get_last_response() {
		return $this->last_response;
	}
	
	/**
	 * Send API request
	 *
	 * @since 4.2
	 * @param $type    string  type of request being sent
	 * @param $params  array   request parameters
	 * @return (mixed) response result or boolean false if an error occurs
	 */
	public function send_request( $type, $params = array() ) {
		$last_error = false;

		if ( WT_LOG_REQUESTS )
			$this->logger->add( 'wootax', 'Started '. $type .' request.' );

		if ( !$this->login_id || !$this->key ) {
			$last_error = 'Could not make '. $type .' request: API Login ID and API Key are required.';
		} else if ( $type == 'Lookup' && ( !wootax_is_valid_address( $params['origin'] ) || !wootax_is_valid_address( $params['destination'], true ) ) ) {
			$last_error = 'Could not make '. $type .' request: Valid origin and destination addresses are required.';
		} else if ( $type == 'VerifyAddress' && !$params['uspsUserID'] ) {
			$last_error = 'Could not make '. $type .' request: A USPS Web Tools ID is required.';
		} else {
			$request = array_merge( array( 
				'apiLoginID' => $this->login_id, 
				'apiKey'     => $this->key, 
			), $params );

			try {
				$response = $this->client->$type( $request );

				// Parse out the last response HTTP code
				$headers = $this->client->__getLastResponseHeaders();

				if ( $c = preg_match_all( "/.*?\\d+.*?\\d+.*?(\\d+)/is", $headers, $matches ) )					  
					$http_code = $matches[1][0];
				
				// Record request details (response headers, request/response)
				$this->log_request_details( $request, $response, $http_code );

				if ( WT_LOG_REQUESTS ) {
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

			if ( WT_LOG_REQUESTS )
				$this->logger->add( 'wootax', 'Request failed: '. $last_error );

			return false;
		} else {
			if ( WT_LOG_REQUESTS )
				$this->logger->add( 'wootax', 'Request succeeded!' );

			return $response;
		}
	}

	/**
	 * Checks if the provided response is an error
	 * Responses will be considered "errors" if:
	 * - a) a TaxCloud error is returned, or
	 * - b) an HTTP error code is returned
	 *
	 * Stores the last error message in last_error and adds log message if appropriate
	 * 
	 * @since 4.2
	 * @param (array) $resp response from TaxCloud 
	 * @return (bool) true if the response has an error, otherwise false
	 */
	private function is_response_error( $resp ) {
		$this->last_error = false;

		// Check for bool false resp/HTTP code other than 200 
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

	/**
	 * Fills in debug information, including the last request message and the last response message
	 * Executed after each request to the TaxCloud API
	 *
	 * @since 4.2
	 * @param (string) $request the last request in JSON format
	 * @param (string) $reponse the last response in JSON format
	 * @param (int) $http_code the last http code received from TaxCloud
	 */
	private function log_request_details( $request, $response, $http_code ) {
		$this->last_request       = $request;
		$this->last_response      = $response;
		$this->last_response_code = $http_code;
	}
}

/** 
 * Return instance of WC_WooTax_TaxCloud class, optionally initialized with Login ID/Key
 * If no login ID/key are provided, use stored settings
 *
 * @since 4.4
 * @param (string) $login_id optional API Login ID
 * @param (string) $login_key optional API Login Key
 */
function TaxCloud( $login_id = false, $login_key = false ) {
	if ( !$login_id ) {
		$login_id = WC_WooTax::get_option( 'tc_id' );
	}

	if ( !$login_key ) {
		$login_key = WC_WooTax::get_option( 'tc_key' );
	}

	$instance = WC_WooTax_TaxCloud::instance();

	$instance->set_id( $login_id );
	$instance->set_key( $login_key );

	return $instance;
}