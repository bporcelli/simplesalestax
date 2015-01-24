<?php

/**
 * wc-wootax-functions.php
 * Contains common methods
 *
 * @package WooTax
 * @since 4.2
 */

// Prevent data leaks
if ( ! defined( 'ABSPATH' ) ) 
	exit; 

/**
 * Receives an Address array and runs it through the USPS address verification API; returns either the verified address or the original address on failure
 *
 * @since 1.0
 * @param $address An Address array
 */
function validate_address( $address ) {

	$taxcloud = get_taxcloud();

	// We will return the original address if verification fails
	$verified_address = $address;

	// Prepare address for verification by executing strtolower on each array value
	$address = array_map( 'strtolower', $address );

	// Verify address if possible
	$usps_id = wootax_get_option( 'usps_id' );

	if ( $usps_id ) {

		$address['uspsUserID'] = $usps_id;

		// Send request to TaxCloud
		$res = $taxcloud->send_request( 'VerifyAddress', $address );

		// Check for errors
		if ( $res !== false ) {
			$verified_address = $res->VerifyAddressResult;

			// TaxCloud will return an ErrNumber field even when there isn't an error; remove it so only the address is returned
			unset( $verified_address->ErrNumber );
		} else {
			$verified_address = $address;
			
			// Remove USPS ID
			if ( isset( $verified_address['uspsUserID'] ) ) {
				unset( $verified_address['uspsUserID'] );
			}
		}

	}
	
	// The address country field will not be returned by TaxCloud, but we want to return it to the calling code; re-add it here
	if ( isset( $address['Country'] ) && is_object( $verified_address ) && !isset( $verified_address->Country ) ) {
		$verified_address->Country = $address['Country'];
	}

	if( !isset( $verified_address->Address2 ) && is_object( $verified_address ) ) {
		$verified_address->Address2 = '';
	}
		
	return (array) $verified_address;

}

/**
 * Add flash message to be displayed on admin side
 * 
 * @since 3.5
 */
function wootax_add_flash_message( $content, $type = 'error' ) {

	// Fetch current message array (stored in transient wootax_flash_messages)
	$messages = get_transient( 'wootax_flash_messages' ) == false ? array() : get_transient( 'wootax_flash_messages' );
	
	// Add new message
	$messages[] = array('content' => $content, 'type' => $type);
	
	// Update transient
	set_transient( 'wootax_flash_messages', $messages );

}

/**
 * Display flash messages 
 * This is best placed here because it needs to run regardless of whether or not WooCommerce is activated and the WooTax admin class
 * is only loaded when Woo is active
 *
 * @since 4.2
 */
function wootax_display_flash_messages() {

	$messages = get_transient( 'wootax_flash_messages' );
		
	// Exit if we don't have messages to display
	if ( $messages == false || is_array( $messages ) && count( $messages ) == 0 ) {
		return;
	}
	
	// Loop through messages and output
	foreach ( $messages as $message ) {
		echo '<div class="'. $message['type'] .'"><p>'. $message['content'] .'</p></div>';
	}

}

add_action( 'admin_notices', 'wootax_display_flash_messages' );

/**
 * Removes flash messages after page load
 *
 * @since 4.2
 */
function wootax_remove_flash_messages() {

	delete_transient( 'wootax_flash_messages' );

}

add_action( 'shutdown', 'wootax_remove_flash_messages' );

/**
 * Fetches valid origin addresses for a given product
 * Returns an array with the default origin address if the user has not set an origin address for a product
 *
 * @since 3.8
 * @param $product_id a product (post) ID
 * @return an array of origin address IDs
 */
function fetch_product_origin_addresses( $product_id ) {

	// We might receive a product variation id; to ensure that we have the actual product id, instantiate a WC_Product object
	$product = get_product( $product_id );
	$id = isset( $product->parent ) ? $product->parent->id : $product_id;

	// Fetch origin addresses array
	$raw_origin_addresses = get_post_meta( $product_id, '_wootax_origin_addresses', true );		

	// Set origin address array to default if it hasn't been configured for this product
	if ( !is_array( $raw_origin_addresses ) || count( $raw_origin_addresses ) == 0 ) {
		$default_address = wootax_get_option( 'default_address' ) == false ? 0 : wootax_get_option( 'default_address' );
		$origin_addresses = array( $default_address );
	} else {
		$origin_addresses = $raw_origin_addresses;
	} 

	return $origin_addresses;

}

/**
 * Returns an array of all business addresses added by the user
 *
 * @since 3.8
 * @return an array of origin addresses
 */
function fetch_business_addresses() {

	$addresses = wootax_get_option( 'addresses' );

	// Ensures that users who upgraded from older versions of the plugin are still good to go
	if ( !is_array( $addresses ) ) {
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

/**
 * Wrapper around get_option to make it easier to get WooTax settings
 *
 * @since 4.2
 * @param $key the key of the option to be fetched
 * @return requested option or boolean false if it isn't set
 */
function wootax_get_option( $key = '' ) {

	$settings_key = 'woocommerce_wootax_settings';

	$settings = get_option( $settings_key );

	if ( !isset( $settings[$key] ) || !$settings[$key] ) {
		return false;
	} else {
		return $settings[$key];
	}

}

/**
 * Wrapper around update_option to make it easier to change WooTax settings
 *
 * @since 4.2
 * @param $key the key of the option to be updated
 * @param $value the new value of the option
 */
function wootax_set_option( $key = '', $value = '' ) {

	$settings_key = 'woocommerce_wootax_settings';

	$settings = get_option( $settings_key );

	if( !is_array( $settings ) ) {
		$settings = array();
	}

	$settings[$key] = $value;

	update_option( $settings_key, $settings );

}

/** 
 * Generates an order ID for requests sent to TaxCloud
 *
 * @since 4.2
 */
function generate_order_id() { 

	return md5( $_SERVER['REMOTE_ADDR'] . microtime() );

}

/**
 * Parse a ZIP code and return its 5 and 4 digit parts
 *
 * @since 4.2
 * @param $zip the original ZIP code
 * @return an array with two keys: 'zip5' and 'zip4'
 */
function parse_zip( $zip ) {

	$parsed_zip = array( 
		'zip5' => '',
		'zip4' => '',
	);

	if ( empty( $zip ) ) {
		return $parsed_zip;
	}

	$zip = str_replace( array( ' ', '-' ), '', $zip );

	if ( strlen( $zip ) == 5 ) {
		$parsed_zip['zip5'] = $zip;
	} else if ( strlen( $zip ) == 4 ) {
		$parsed_zip['zip4'] = $zip;
	} else if ( strlen( $zip ) == 9 ) {
		$parsed_zip['zip5'] = substr( $zip, 0, 5 );
		$parsed_zip['zip4'] = substr( $zip, 5, 10 );
	}

	return $parsed_zip;

}