<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Shipping.
 *
 * Contains functionality related to shipping.
 *
 * @author 	Simple Sales Tax
 * @package SST
 * @since 	5.0
 */
class SST_Shipping {
	
	/**
	 * Is the provided shipping method a local pickup method?
	 *
	 * @since 5.0
	 *
	 * @param  string $method Shipping method slug or name.
	 * @return bool
	 */
	public static function is_local_pickup( $method ) {
		return in_array( $method, apply_filters( 'wootax_local_pickup_methods', array( 'local_pickup', 'Local Pickup' ) ) );
	}

	/**
	 * Is the provided shipping method a local delivery method?
	 *
	 * @since 5.0
	 *
	 * @param  string $method Method name or slug.
	 * @return bool
	 */
	public static function is_local_delivery( $method ) {
		return in_array( $method, apply_filters( 'wootax_local_delivery_methods', array( 'local_delivery', 'Local Delivery' ) ) );
	}
}
