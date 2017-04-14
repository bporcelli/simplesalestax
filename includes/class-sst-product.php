<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * SST Product.
 *
 * Contains methods for working with products.
 *
 * @author 	Simple Sales Tax
 * @package SST
 * @since 	5.0
 */
class SST_Product {
	
	/**
	 * Returns an array of origin addresses for a given product. If no origin
	 * addresses have been configured, returns default origin address.
	 *
	 * @since 5.0
	 *
	 * @param  int $product_id
	 * @return array
	 */
	public static function get_origin_addresses( $product_id ) {
		$origin_addresses = get_post_meta( $product_id, '_wootax_origin_addresses', true );		
		if ( ! $origin_addresses ) {
			$origin_addresses = array( SST()->settings->get_option( 'default_address' ) );
		}
		return $origin_addresses;
	}

	/**
	 * Get the TIC for a product.
	 *
	 * @since 5.0
	 *
	 * @param  int $product_id
	 * @param  int $variation_id (default: 0)
	 * @return mixed TIC for product, or false if none set.
	 */
	public static function get_tic( $product_id, $variation_id = 0 ) {
		$product_tic   = get_post_meta( $product_id, 'wootax_tic', true );
		$variation_tic = ! $variation_id ? false : get_post_meta( $variation_id, 'wootax_tic', true );
		$tic           = $variation_tic ? $variation_tic : $product_tic;
		
		return empty( $tic ) ? false : $tic;
	}
}