<?php

// Prevent direct access to script
if ( ! defined( 'ABSPATH' ) ) exit; 

/**
 * Utility for making TaxCloud API requests
 *
 * @since 4.2
 * @package WooTax
 * @version: 1.5
 */

class WC_WooTax_TaxCloud {
	private $apiLoginID;
	private $apiKey;
	private $client;
	private $lastRequest;
	private $lastResponse;
	private $lastResponseHeaders;
	private $lastError;
	
	/**
	 * Class constructor
	 * Sets up class params
	 */
	public function __construct( $apiLoginID, $apiKey ) {
		// Set TaxCloud properties
		$this->apiLoginID = $apiLoginID;
		$this->apiKey = $apiKey;

		// Set up SOAPClient
		$this->client = new SOAPClient( 'https://api.taxcloud.net/1.0/?wsdl', array( 
			'trace' => true, 
			'soap_version' => SOAP_1_2 
		) );
	}
	
	/**
	 * Setter for property "apiLoginID"
	 */
	public function setID( $id ) {
		$this->apiLoginID = $id;
	} 
	
	/**
	 * Setter for property "apiKey"
	 */
	public function setKey( $key ) {
		$this->apiKey = $key;
	}
	
	/**
	 * Checks if the provided response is an error
	 * Responses will be considered "errors" if a) a TaxCloud error is returned or b) an HTTP error code is returned
	 * Stores the last error message to the lastError property
	 */
	public function isError( $resp ) {
		// Check for bool false resp/HTTP code other than 200 
		if ( $resp == false || !stristr( $this->lastResponseHeaders, '200 OK' ) ) {
			return true;
		}

		// Check for TaxCloud error messages
		if ( isset( $resp->ResponseType ) && $resp->ResponseType == 'Error' ) {

			$errors = '';

			foreach ( $resp->Messages as $message ) {
				$errors .= 'Error: '. $message->ResponseType .' - '. $message->Message .'<br />';
			}

			$errors = substr( $errors, 0, strlen( $errors ) - 6 );
			$this->lastError = $errors;
			
			return true;

		} else if ( isset( $resp->ErrDescription ) && !empty( $resp->ErrDescription ) ) {
			$this->lastError = $resp->ErrDescription;
			return true;
		}

		return false;
	}
	
	/**
	 * Allows access to get the lastError property
	 * Returns last error message
	 */
	public function getErrorMessage() {
		return $this->lastError;
	}
	
	/**
	 * Allows access to the lastRequest property
	 * Returns last request
	 */
	public function getLastRequest() {
		return $this->lastRequest;	
	}
	
	/**
	 * Allows access to the lastResponse property
	 * Returns the last response from TaxCloud
	 */
	public function getLastResponse() {
		return $this->lastResponse;
	}
	
	/**
	 * Fills in debug information, including the last request message and the last response message
	 * Executed after each request to the TaxCloud API
	 */
	public function log_request_details() {

		$this->lastRequest = $this->client->__getLastRequest();
		$this->lastResponse = $this->client->__getLastResponse();
		$this->lastResponseHeaders = $this->client->__getLastResponseHeaders();

	}
	
	/**
	 * Checks for a valid address. For an address to be valid, Address 1, Country, City, State, and 5-digit ZIP must be provided
	 *
	 * @param Address $address
	 * @return bool true if the address is valid; else, bool false
	 */
	public function isValidAddress( $address, $dest = false ) {
		// Convert all array keys to lowercase for consistency
		$address = array_change_key_case( array_map( 'strtolower', $address ) );
		$required_fields = array( /*'address1',*/'country', 'city', 'state', 'zip5' );
		
		// Check for presence of required fields
		foreach ( $required_fields as $required ) {
			$val = isset( $address[$required] ) ? $address[$required] : '' ;
			
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
	 * Send a TaxCloud Ping request
	 *
	 * @return response from TaxCloud
	 */
	public function Ping() {
		if ( !empty( $this->apiLoginID ) && !empty( $this->apiKey ) ) {
			// Build & Send request
			$request = array( 
				'apiLoginID' => $this->apiLoginID, 
				'apiKey' => $this->apiKey,
			);

			$response = $this->client->Ping($request);

			// Fill in debug info
			$this->log_request_details();

			// Return response
			return $response;
		} else {
			$this->lastError = 'Could not make Ping request: API Login ID and API Key must be set.';
			return false;
		}
	}
	
	/** 
	 * Sends a TaxCloud VerifyAddress request
	 *
	 * @param Address $address
	 * @return TaxCloud response
	 */
	public function VerifyAddress( $address ) {
		if ( !empty( $this->apiLoginID ) && !empty( $this->apiKey ) ) {
			// Unset uspsUserID
			$user_id = '';

			if ( isset( $address['uspsUserID'] ) ) {
				$user_id = $address['uspsUserID'];

				unset( $address['uspsUserID'] );
			}

			// Convert all address keys to lowercase
			$address = array_change_key_case( $address );

			// Check for valid address
			if ( $this->isValidAddress( $address ) ) {
				// Reset uspsUserID
				$address['uspsUserID'] = $user_id;

				// Unset country
				unset( $address['country'] );

				// Build & Send request
				$request = array_merge( array( 
					'apiLoginID' => $this->apiLoginID, 
					'apiKey' => $this->apiKey 
				), $address );

				try {
					$response = $this->client->VerifyAddress( $request );
				} catch (SoapFault $e) {
					$this->lastError = 'Could not make Lookup request due to SOAPFault. Please try again.';
					return false;
				}
				
				// Fill in debug info
				$this->log_request_details();

				// Return response
				return $response;
			} else {
				$this->lastError = 'Could not make VerifyAddress request: required parameters are missing.';
				return false;
			}
		} else {
			$this->lastError = 'Could not make VerifyAddress request: API Login ID and API Key must be set.';
			return false;
		}
	}
	
	/**
	 * Performs tax Lookup request
	 *
	 * @param request parameters $params
	 * @return TaxCloud response 
	 */
	public function Lookup( $params ) {
		$origin = $params['origin'];
		$dest = $params['destination'];

		if ( !empty( $this->apiLoginID ) && !empty( $this->apiKey ) ) {
			if ( $this->isValidAddress( $origin ) && $this->isValidAddress( $dest, true ) ) {
				// Build & Send request
				$request = array_merge( array( 
					'apiLoginID' => $this->apiLoginID, 
					'apiKey' => $this->apiKey 
				), $params );

				try {
					$response = $this->client->Lookup( $request );
				} catch (SoapFault $e) {
					$this->lastError = 'Could not make Lookup request due to SOAPFault. Please try again.';
					return false;
				}
				
				// Fill in debug info
				$this->log_request_details();

				// Return response
				return $response;
			} else {
				$this->lastError = 'Could not make Lookup request. Valid origin and destination addresses are required.';
				return false;
			}
		} else {
			$this->lastError = 'Could not make Lookup request: API Login ID and API Key must be set.';
			return false;
		}
	}
	
	/**
	 * Performs Authorize request; signifies the beginning of the checkout process
	 *
	 * @param request parameters $params
	 * @return TaxCloud response
	 */
	public function Authorized($params) {
		// Build & Send request
		$request = array_merge( array( 
			'apiLoginID' => $this->apiLoginID, 
			'apiKey' => $this->apiKey 
		), $params );

		try {
			$response = $this->client->Authorized( $request );
		} catch (SoapFault $e) {
			$this->lastError = 'Could not make Lookup request due to SOAPFault. Please try again.';
			return false;
		}

		// Fill in debug info
		$this->log_request_details();

		// Return response
		return $response;
	}
	
	/**
	 * Performs Captured request; signifies the completion of an order
	 *
	 * @param parameters $params
	 * @return TaxCloud response
	 */
	public function Captured($params) {
		// Build & Send request
		$request = array_merge( array( 
			'apiLoginID' => $this->apiLoginID, 
			'apiKey' => $this->apiKey 
		), $params );

		try {
			$response = $this->client->Captured( $request );
		} catch (SoapFault $e) {
			$this->lastError = 'Could not make Lookup request due to SOAPFault. Please try again.';
			return false;
		}

		// Fill in debug info
		$this->log_request_details();

		// Return response
		return $response;
	}
	
	/**
	 * Performs AuthorizedWithCapture request
	 *
	 * @param request parameters $params
	 * @return TaxCloud response
	 */
	public function AuthorizedWithCapture($params) {
		// Build & Send request
		$request = array_merge( array( 
			'apiLoginID' => $this->apiLoginID, 
			'apiKey' => $this->apiKey 
		), $params );

		try {
			$response = $this->client->AuthorizedWithCapture( $request );
		} catch (SoapFault $e) {
			$this->lastError = 'Could not make Lookup request due to SOAPFault. Please try again.';
			return false;
		}

		// Fill in debug info
		$this->log_request_details();

		// Return response
		return $response;
	}
	
	/**
	 * Performs Returned request
	 *
	 * @param request parameters $params
	 * @return TaxCloud response
	 */
	public function Returned($params) {
		// Build & Send request
		$request = array_merge( array( 
			'apiLoginID' => $this->apiLoginID, 
			'apiKey' => $this->apiKey 
		), $params );

		try {
			$response = $this->client->Returned( $request );
		} catch (SoapFault $e) {
			$this->lastError = 'Could not make Lookup request due to SOAPFault. Please try again.';
			return false;
		}
		
		// Fill in debug info
		$this->log_request_details();

		// Return response
		return $response;
	}
	
	/**
	 * Sends request to GetExemptCertificates endpoint
	 *
	 * @param request parameters $params
	 * @return TaxCloud response
	 */
	public function GetExemptCertificates($params) {
		// Build & Send request
		$request = array_merge( array( 
			'apiLoginID' => $this->apiLoginID, 
			'apiKey' => $this->apiKey 
		), $params );

		try {
			$response = $this->client->GetExemptCertificates( $request );
		} catch (SoapFault $e) {
			$this->lastError = 'Could not make Lookup request due to SOAPFault. Please try again.';
			return false;
		}

		// Fill in debug info
		$this->log_request_details();

		// Return response
		return $response;
	}
	
	/**
	 * Sends request to AddExemptCertificate endpoint
	 *
	 * @param parameters $params
	 * @return TaxCloud response
	 */
	public function AddExemptCertificate( $params ) {
		// Build & Send request
		$request = array_merge( array( 
			'apiLoginID' => $this->apiLoginID, 
			'apiKey' => $this->apiKey ), 
		$params );

		try {
			$response = $this->client->AddExemptCertificate( $request );
		} catch (SoapFault $e) {
			$this->lastError = 'Could not make Lookup request due to SOAPFault. Please try again.';
			return false;
		}

		// Fill in debug info
		$this->log_request_details();

		// Return response
		return $response;
	}
	
	/**
	 * Sends request to DeleteExemptCertificate endpoint
	 *
	 * @param parameters $params
	 * @return TaxCloud response
	 */
	public function DeleteExemptCertificate( $params ) {
		// Build & Send request
		$request = array_merge( array( 
			'apiLoginID' => $this->apiLoginID, 
			'apiKey' => $this->apiKey 
		), $params );

		try {
			$response = $this->client->DeleteExemptCertificate( $request );
		} catch (SoapFault $e) {
			$this->lastError = 'Could not make Lookup request due to SOAPFault. Please try again.';
			return false;
		}

		// Fill in debug info
		$this->log_request_details();

		// Return response
		return $response;
	}
}