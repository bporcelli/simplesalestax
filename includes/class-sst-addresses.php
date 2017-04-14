<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Addresses.
 *
 * Contains methods for getting and validating origin and destination addresses.
 *
 * @author 	Simple Sales Tax
 * @package SST
 * @since 	5.0
 */
class SST_Addresses {

	/**
	 * Converts an address array to a formatted string.
	 *
	 * @since 5.0
	 *
	 * @param  array $address
	 * @return string
	 */
	public static function format( $address ) {
		return $address['address_1'] .', '. $address[ 'city' ] .', '. $address['state'] .' '. $address['zip5'];
	}

	/**
	 * Determines whether an address is "valid." An address is considered to be
	 * valid if a country, city, state, address, and ZIP code are provided and
	 * the country is United States.
	 *
	 * @since 5.0
	 *
	 * @param  array $address
	 * @return bool
	 */
	public static function is_valid( $address ) {
		// Normalize address array by converting all keys and values to lowercase
		$address = array_change_key_case( array_map( 'strtolower', $address ) );

		foreach ( array( 'country', 'city', 'state', 'zip5' ) as $required ) {
			$val = isset( $address[ $required ] ) ? $address[ $required ] : '';
			if ( empty( $val ) ) 
				return false;
		}
		
		// If the destination country is not the US, return false
		if ( ! in_array( strtolower( $address['country'] ), array( 'us', 'united states' ) ) ) {
			return false;
		}
			
		return true;
	}

	/**
	 * Return business address with given location key.
	 *
	 * @since 4.4
	 *
	 * @param  int $index Location key.
	 * @return array
	 */
	public static function get_address( $index ) {
		$addresses = self::get_origin_addresses();
		$found     = isset( $addresses[ $index ] ) ? $addresses[ $index ] : array();
		$address   = array(
			'Address1' => isset( $found[ 'address_1' ] ) ? $found[ 'address_1' ] : '',
			'Address2' => isset( $found[ 'address_2' ] ) ? $found[ 'address_2' ] : '',
			'Country'  => isset( $found[ 'country' ] ) ? $found[ 'country' ] : '',
			'State'    => isset( $found[ 'state' ] ) ? $found[ 'state' ] : '',
			'City'     => isset( $found[ 'city' ] ) ? $found[ 'city' ] : '',
			'Zip5'     => isset( $found[ 'zip5' ] ) ? $found[ 'zip5' ] : '',
			'Zip4'     => isset( $found[ 'zip4' ] ) ? $found[ 'zip4' ] : '',
		);
		return $address;
	}

	/**
	 * Validate an address, parsing the ZIP into its 5-digit and 4-digit
	 * components if necessary.
	 *
	 * @since 5.0
	 */
	private static function validate_address( $address ) {
		// Parse zip into 5-digit/4-digit parts
		if ( strlen( $address['Zip5'] ) > 5 ) {
			$new_zip = str_replace( array( ' ', '-' ), '', $address['Zip5'] );
			$address['Zip5'] = substr( $new_zip, 0, 5 );
			$address['Zip4'] = substr( $new_zip, 5, 10 );
		}
		
		$addresses = get_transient( 'sst_validated_addresses' );
		if ( ! is_array( $addresses ) )
			$addresses = array();

		$md5_hash = md5( json_encode( $address ) );

		if ( array_key_exists( $md5_hash, $addresses ) ) {
			return $addresses[ $md5_hash ];
		} else {
			// Array keys/values must be lowercase for validation to work properly
			$request = array_change_key_case( array_map( 'strtolower', $address ) );

			if ( isset( $request['country'] ) ) {
				unset( $request['country'] );
			}

			if ( ( $usps_id = SST()->get_option( 'usps_id' ) ) ) {
				$request['uspsUserID'] = $usps_id;

				$res = TaxCloud()->send_request( 'VerifyAddress', $address );

				if ( $res !== false ) {
					$address = array(
						'Address1' => $res->Address1,
						'Address2' => isset( $res->Address2 ) ? $res->Address2 : '',
						'Country'  => $address['Country'],
						'State'    => $res->State,
						'Zip5'	   => $res->Zip5,
						'Zip4'     => $res->Zip4,
					);
				}
			}

			$addresses[ $md5_hash ] = $address;

			// Cache validated addresses for 3 days
			set_transient( 'sst_validated_addresses', $addresses, 2 * DAY_IN_SECONDS );
		}
	}

	/**
	 * Get default pickup address.
	 *
	 * @since 5.0
	 *
	 * @return array
	 */
	private static function get_default_address() {
		return self::get_address( SST()->settings->get_option( 'default_address' ) );
	}

	/**
	 * Get destination address.
	 *
	 * @since 5.0
	 *
	 * @param  int $order Order object (default: null).
	 * @return array Associative array representing address.
	 */
	public static function get_destination_address( $order = NULL ) {
		$tax_based_on = get_option( 'woocommerce_tax_based_on' );

		// Handle local pickups (ripped from core)
		if ( SST_Shipping::is_local_pickup() )
			return apply_filters( 'wootax_pickup_address', self::get_default_address(), -1 );

		$billing = 'billing' === $tax_based_on;

		if ( $order ) {
			$address = array(
				'Address1' => $billing ? $order->get_billing_address_1() : $order->get_shipping_address_1(),
				'Address2' => $billing ? $order->get_billing_address_2() : $order->get_shipping_address_2(),
				'Country'  => $billing ? $order->get_billing_country() : $order->get_shipping_country(),
				'State'    => $billing ? $order->get_billing_state() : $order->get_shipping_state(),
				'City'     => $billing ? $order->get_billing_city() : $order->get_shipping_city(),
				'Zip5'     => $billing ? $order->get_billing_postcode() : $order->get_shipping_postcode(),
			);
		} else {
			$raw_addr = WC_Customer::get_taxable_address();	// country, state, postcode, city

			$address = array(
				'Address1' => $billing ? WC()->customer->get_billing_address() : WC()->customer->get_shipping_address(),
				'Address2' => $billing ? WC()->customer->get_billing_address_2() : WC()->customer->get_shipping_address_2(),
				'Country'  => $raw_addr[0],
				'State'    => $raw_addr[1],
				'City'     => $raw_addr[3],
				'Zip5'     => $raw_addr[2],
			);
		}

		return self::validate_address( $address );
	}

	/**
	 * Get all business addresses configured by the admin.
	 *
	 * @since 5.0
	 *
	 * @return array
	 */
	public static function get_origin_addresses() {
		$addresses = SST()->get_option( 'addresses' );

		// Ensures that users who upgraded from older versions of the plugin are still good to go
		if ( ! is_array( $addresses ) ) {
			$addresses = array(
				array(
					'address_1' => get_option( 'wootax_address1' ),
					'address_2' => get_option( 'wootax_address2' ),
					'country' 	=> 'United States', // hardcoded because this is the only option as of right now
					'state'		=> get_option( 'wootax_state' ),
					'city' 		=> get_option( 'wootax_city' ),
					'zip5'		=> get_option( 'wootax_zip5' ),
					'zip4'		=> get_option( 'wootax_zip4' ),
				)
			);
		}

		return $addresses;
	}
}