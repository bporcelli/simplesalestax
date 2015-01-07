<?php

// Prevent direct access to script
if ( ! defined( 'ABSPATH' ) ) exit; 

/**
 * Utility for making TaxCloud API requests
 *
 * @since 4.2
 * @package WooTax
 * @version: 2.0
 */

class WC_WooTax_TaxCloud {
	private $login_id;
	private $key;
	private $client;
	private $last_request;
	private $last_response;
	private $last_response_headers;
	private $last_error;
	private $logger;
	
	/**
	 * Class constructor
	 *
	 * @since 4.2
	 * @param $login_id 
	 */
	public function __construct( $login_id = false, $key = false ) {

		// Set TaxCloud properties
		$this->login_id = $login_id;
		$this->key      = $key;

		// Set up SOAPClient
		$this->client = new SOAPClient( 'https://api.taxcloud.net/1.0/?wsdl', array( 
			'trace' => true, 
			'soap_version' => SOAP_1_2 
		) );

		// Set up logger
		$this->logger = false;
		$log_requests = wootax_get_option( 'log_requests' );

		if ( $log_requests == 'yes' || !$log_requests ) {
			$this->logger = class_exists( 'WC_Logger' ) ? new WC_Logger() : $woocommerce->logger();
		}

	}
	
	/**
	 * Sets the API Login ID
	 *
	 * @since 4.2
	 * @param $id (string) TaxCloud API Login ID
	 */
	public function set_id( $id ) {
		$this->login_id = $id;
	} 
	
	/**
	 * Sets the API Key
	 *
	 * @since 4.2
	 * @param $key (string) TaxCloud API Key
	 */
	public function set_key( $key ) {
		$this->key = $key;
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
	 * @param $resp (array) response from TaxCloud 
	 * @return (bool) true if the response has an error, otherwise false
	 */
	private function is_response_error( $resp ) {

		$this->last_error = false;

		// Check for bool false resp/HTTP code other than 200 
		if ( $resp == false || !stristr( $this->last_response_headers, '200 OK' ) ) {
			$this->last_error = 'An error occurred during the last API request. Response headers indicate an HTTP failure: '. print_r( $this->last_response_headers, true );
		}

		// Check for TaxCloud error messages
		if ( isset( $resp->ResponseType ) && $resp->ResponseType == 'Error' ) {

			$errors = '';

			foreach ( $resp->Messages as $message ) {
				$errors .= $message->ResponseType .' - '. $message->Message .'<br />';
			}

			$errors = substr( $errors, 0, strlen( $errors ) - 6 );
			
			$this->last_error = 'An error occurred during the last API request. TaxCloud said: '. $errors;

		} else if ( isset( $resp->ErrDescription ) && !empty( $resp->ErrDescription ) ) {
			$this->last_error = 'An error occurred during the last API request. TaxCloud said: ' . $resp->ErrDescription;
		}

		if ( $this->last_error ) {
			return true;
		} else {
			return false;
		}

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
	 * Fills in debug information, including the last request message and the last response message
	 * Executed after each request to the TaxCloud API
	 *
	 * @since 4.2
	 */
	private function log_request_details() {

		$this->last_request          = $this->client->__getLastRequest();
		$this->last_response         = $this->client->__getLastResponse();
		$this->last_response_headers = $this->client->__getLastResponseHeaders();

	}
	
	/**
	 * Checks for a valid address. For an address to be valid, Country, City, State, and 5-digit ZIP must be provided
	 *
	 * @param Address $address
	 * @return (bool) true if the address is valid; else, false
	 */
	public function is_valid_address( $address, $dest = false ) {

		// Convert all array keys to lowercase for consistency
		$address         = array_change_key_case( array_map( 'strtolower', $address ) );
		$required_fields = array( 'country', 'city', 'state', 'zip5' );
		
		// Check for presence of required fields
		foreach ( $required_fields as $required ) {

			$val = isset( $address[ $required ] ) ? $address[ $required ] : '' ;
			
			if ( empty( $val ) ) {
				return false;
			}

		}
		
		// If the destination country is not the US, return false
		if ( $dest == true && isset( $address['country'] ) && ( strtolower( $address['country'] ) != 'us' && strtolower( $address['country'] ) != 'united states' ) )  {
			return false;
		}
			
		return true;

	}
	
	/**
	 * Send API request
	 *
	 * @since 4.2
	 * @param $type (string) type of request being sent
	 * @param $params (array) request parameters
	 * @return (mixed) response result or boolean false if an error occurs
	 */
	public function send_request( $type, $params = array() ) {

		$last_error = false;

		if ( $this->logger ) {
			$this->logger->add( 'wootax', 'Started '. $type .' request.' );
		}

		if ( !$this->login_id || !$this->key ) {
			$last_error = 'Could not make '. $type .' request: API Login ID and API Key are required.';
		} else if ( $type == 'Lookup' && ( !$this->is_valid_address( $params['origin'] ) || !$this->is_valid_address( $params['destination'], true ) ) ) {
			$last_error = 'Could not make '. $type .' request: Valid origin and destination addresses are required.';
		} else {
			// Perform some special formatting 
			if ( $type == 'VerifyAddress' ) {

				// Unset uspsUserID
				$user_id = '';

				if ( isset( $params['uspsUserID'] ) ) {
					$user_id = $params['uspsUserID'];
					unset( $params['uspsUserID'] );
				}

				$params = array_change_key_case( $params );

				// Check for valid address
				if ( !$this->is_valid_address( $params ) ) {
					$last_error = 'Could not make '. $type .' request: A valid address is required.';
				} else {
					// Reset uspsUserID
					$params['uspsUserID'] = $user_id;

					// Unset country
					unset( $params['country'] );
				}

			}

			if ( $last_error == false ) { // Prevents VerifyAddress requests from being executed when an invalid address is passed

				// Add API Login ID/Key to request parameters
				$request = array_merge( array( 
					'apiLoginID' => $this->login_id, 
					'apiKey'     => $this->key, 
				), $params );

				try {

					$response = $this->client->$type( $request );

					// Record request details (response headers, request/response)
					$this->log_request_details();

					if ( $this->logger ) {
						$this->logger->add( 'wootax', 'Request: '. print_r( $request, true ) );
						$this->logger->add( 'wootax', 'Response: '. print_r( $response, true ) );
					}

					// Check response for errors 
					$response_key = $type . 'Result';

					if ( $this->is_response_error( $response->$response_key ) ) {
						$last_error = $this->last_error;
					}

				} catch ( SoapFault $e ) {
					$last_error = 'Could not make '. $type .' request due to SoapFault. It is possible that the request was not formatted correctly. Please try again.';
				}

			}
		}
		
		if ( $last_error ) {

			$this->last_error = $last_error;

			if ( $this->logger ) {
				$this->logger->add( 'wootax', 'Request failed: '. $last_error );
			}

			return false;

		} else {

			if ( $this->logger ) {
				$this->logger->add( 'wootax', 'Request succeeded!' );
			}

			return $response;

		}

	}

}