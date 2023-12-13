<?php
/**
 * Plugin Name: SST Change Order IDs
 * Description: Use order numbers instead of IDs to generate order IDs for TaxCloud
 * Version: 0.0.1
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter(
	'sst_package_order_id',
	function ( $_, $order, $package_key ) {
		$order_id = $order->get_meta( 'sst_order_id' );

		if ( ! $order_id ) {
			$order_id = bin2hex( random_bytes( 18 ) );
			$order->update_meta_data( 'sst_order_id', $order_id );
			$order->save();
		}

		return "{$order_id}_{$package_key}";
	},
	10,
	3
);
