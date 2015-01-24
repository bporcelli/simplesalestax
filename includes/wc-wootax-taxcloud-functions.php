<?php

/**
 * TaxCloud functions
 *
 * @since 4.3
 */

// Prevent data leaks
if ( ! defined( 'ABSPATH' ) ) 
	exit; 

require( WOOTAX_PATH .'classes/class-wc-wootax-taxcloud.php' );

/**
 * Get a TaxCloud object 
 *
 * @since 4.2
 * @return WC_WooTax_TaxCloud object or boolean false if the user hasn't configured their TaxCloud API creds yet
 */
function get_taxcloud() {

	$taxcloud_id  = wootax_get_option( 'tc_id' );
	$taxcloud_key = wootax_get_option( 'tc_key' );

	// If we have a valid configuration, initialize a WC_WooTax_TaxCloud object
	if ( !empty( $taxcloud_id ) && !empty( $taxcloud_key ) ) {
		$taxcloud = new WC_WooTax_TaxCloud( $taxcloud_id, $taxcloud_key );
	} else {
		$taxcloud = false;
	}

	return $taxcloud;

}

?>