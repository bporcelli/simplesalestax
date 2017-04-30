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
	 * Is one of the given shipping methods a local pickup method?
	 *
	 * @since 5.0
	 *
	 * @param  array $method_ids
	 * @return bool
	 */
	public static function is_local_pickup( $method_ids ) {
		return ( true === apply_filters( 'woocommerce_apply_base_tax_for_local_pickup', true ) && sizeof( array_intersect( $method_ids, apply_filters( 'woocommerce_local_pickup_methods', array( 'legacy_local_pickup', 'local_pickup' ) ) ) ) > 0 );
	}

	/**
	 * Is the provided shipping method a local delivery method?
	 *
	 * @since 5.0
	 *
	 * @param  string $method_id Method ID (default '')
	 * @return bool
	 */
	public static function is_local_delivery( $method_id = '' ) {
		return in_array( $method_id, apply_filters( 'wootax_local_delivery_methods', array( 'local_delivery', 'legacy_local_delivery' ) ) );
	}
}
