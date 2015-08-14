<?php

/**
 * Represents a WooTax API error
 *
 * @package WooTax
 * @author Brett Porcelli
 * @since 4.6
 */

class WC_WooTax_API_Error extends Exception {
	/**
	 * Constructor
	 *
	 * @since 4.6
	 */
	public function __construct( $msg ) {
		parent::__construct( $msg );
	}

	/**
	 * Determine whether or not the provided API Response is an error
	 *
	 * @param (array) $response
	 * @return bool
	 * @since 4.6
	 */
	public static function is_error( $response ) {
		if ( ! is_array( $response ) || empty( $response ) ) {
			return true;
		} else if ( isset( $response['error'] ) ) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Construct a WC_WooTax_API_Error object based on the provided API response
	 *
	 * @param (array) $response
	 * @return (Object) WC_WooTax_API_Error
	 * @since 4.6
	 */
	public static function get_error( $response ) {
		$error = $response;

		if ( is_array( $response ) ) {
			$error = $response['error'];
		}

		return new self( $error );
	}
}