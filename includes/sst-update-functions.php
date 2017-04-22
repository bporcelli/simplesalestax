<?php

/**
 * Update functions.
 *
 * Callbacks invoked by the SST updater.
 *
 * @author 	Simple Sales Tax
 * @package SST
 * @since 	5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * In 2.6, we eliminated the ability to manually disable shipping tax. This
 * function deletes the related option.
 *
 * @since 5.0
 */
function sst_update_26_remove_shipping_taxable_option() {
	delete_option( 'wootax_shipping_taxable' );
}

/**
 * In 3.8, we started allowing multiple business addresses. This function
 * migrates existing address data to work with the new address system.
 *
 * @since 5.0
 */
function sst_update_38_update_addresses() {
	// Set new address array 
	SST()->set_option( 'wootax_addresses', SST_Addresses::get_origin_addresses() );

	// Delete old options
	delete_option( 'wootax_address1' );
	delete_option( 'wootax_address2' );
	delete_option( 'wootax_state' );
	delete_option( 'wootax_city' );
	delete_option( 'wootax_zip5' );
	delete_option( 'wootax_zip4' );
}

/**
 * In version 4.2, we started using the WooCommerce settings API to manage plugin
 * settings. This function migrates existing settings so they can work with the
 * API.
 *
 * @since 5.0
 */
function sst_update_42_migrate_settings() {
	global $wpdb;

	$options = array(
		'wootax_tc_id',
		'wootax_tc_key',
		'wootax_usps_id',
		'wootax_show_exempt',
		'wootax_exemption_text',
		'wootax_company_name',
		'wootax_show_zero_tax',
		'wootax_tax_based_on',
		'wootax_addresses',
		'wootax_default_address',
	);

	// Get old options
	$existing = $wpdb->get_results( "SELECT * FROM {$wpdb->options} WHERE option_name IN ( ". implode( ',', $options ) ." );" );
	
	// Migrate
	$new_options = get_option( 'woocommerce_wootax_settings', array() );
	
	foreach ( $existing as $old_option ) {
		$new_options[ $old_option->option_name ] = maybe_unserialize( $old_option->option_value );
	}

	update_option( 'woocommerce_wootax_settings', $new_options );

	// Delete old options
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name IN ( ". implode( ',', $options ) ." );" );
}

/**
 * In version 4.2, we eliminated the "wootax_order" post type and started
 * storing tax data as order metadata. This function transfer all metadata
 * associated with wootax_order posts to the corresponding WooCommerce order.
 *
 * Unlike its predecessor, this function DOES NOT reformat existing cart_taxes,
 * lookup_data, and mapping arrays. This means that the refund/capture/lookup 
 * routines must detect and handle legacy orders.
 *
 * @since 5.0
 */
function sst_update_42_migrate_order_data() {
	global $wpdb;

	// Associate all existing metadata for wootax_order posts with the
	// corresponding WooCommerce order.
	$meta_keys = array(
		'_wootax_tax_total', 
		'_wootax_shipping_tax_total', 
		'_wootax_captured', 
		'_wootax_refunded', 
		'_wootax_customer_id', 
		'_wootax_tax_item_id', 
		'_wootax_exemption_applied',
		'_wootax_lookup_data',
		'_wootax_cart_taxes',
	);

	$wpdb->query( "
		UPDATE {$wpdb->postmeta} wt, (
			SELECT * FROM {$wpdb->postmeta}
			WHERE meta_key = '_wootax_wc_order_id'
			AND meta_key <> 0
		) wc
		SET wt.post_id = wc.meta_value
		WHERE wt.post_id = wc.post_id
		AND wt.meta_key <> 0
		AND wt.meta_key IN ( ". implode( ',', $meta_keys ) ." );
	" );

	// Process WooCommerce orders. Add new order and item metadata introduced
	// in 4.2
	$orders = $wpdb->get_results( "
		SELECT p.ID as wt_oid, pm.meta_value AS wc_oid
		FROM {$wpdb->posts} p, {$wpdb->postmeta} pm
		WHERE p.ID = pm.post_id
		AND pm.meta_key = '_wootax_wc_order_id';
	" );

	foreach ( $orders as $order ) {

		$lookup_data = get_post_meta( $order->wt_oid, '_wootax_lookup_data', true );
		$cart_taxes  = get_post_meta( $order->wt_oid, '_wootax_cart_taxes', true );

		// No need to update order if lookup_data not set
		if ( ! is_array( $lookup_data ) )
			continue;

		foreach ( $lookup_data as $location_key => $items ) {
			// Skip cart_id/order_id
			if ( ! is_array( $items ) )
				continue;

			foreach ( $items as $index => $item ) {
				// Get sales tax for item
				if ( isset( $cart_taxes[ $location_key ][ $index ] ) ) {
					$tax_amount = $cart_taxes[ $location_key ][ $index ];
				} else {
					$tax_amount = 0;
				}

				// Update item metadata
				$item_id   = $item[ 'ItemID' ];
				$item_type = sst_update_42_get_item_type( $item_id );

				switch ( $item_type ) {
					case 'product':
						if ( 'product_variation' == get_post_type( $item_id ) ) {
							$meta_key = '_variation_id';
						} else {
							$meta_key = '_product_id';
						}

						$cart_item_id = $wpdb->get_var( $wpdb->prepare( "
							SELECT order_item_id
							FROM {$wpdb->prefix}woocommerce_order_itemmeta
							WHERE meta_key = %s
							AND meta_value = %s
							AND order_id = %d
						", $meta_key, $item_id, $order->wc_oid ) );

						if ( ! is_null( $cart_item_id ) ) {
							wc_update_order_item_meta( $cart_item_id, '_wootax_index', $index );
							wc_update_order_item_meta( $cart_item_id, '_wootax_tax_amount', $tax_amount );
							wc_update_order_item_meta( $cart_item_id, '_wootax_location_id', $location_key );
						}
					break;
					case 'shipping':
						if ( version_compare( WC_VERSION, '2.2', '<' ) ) {
							update_post_meta( $order->wc_oid, '_wootax_first_found', $location_key );
							update_post_meta( $order->wc_oid, '_wootax_shipping_index', $index );
						} else {
							// NOTE: This assumes one shipping method per order
							$shipping_id = $wpdb->get_var( $wpdb->prepare( "
								SELECT order_item_id FROM {$wpdb->prefix}woocommerce_order_items
								WHERE order_item_type = 'shipping'
								AND order_id = %d
								LIMIT 1;
							", $order->wc_oid ) );

							if ( ! is_null( $shipping_id ) ) {
								wc_update_order_item_meta( $shipping_id, '_wootax_index', $index );
								wc_update_order_item_meta( $shipping_id, '_wootax_tax_amount', $tax_amount );
								wc_update_order_item_meta( $shipping_id, '_wootax_location_id', $location_key );
							}
						}
					break;
					case 'fee':
						$fee_id = $wpdb->get_var( $wpdb->prepare( "
							SELECT order_item_id
							FROM {$wpdb->prefix}woocommerce_order_items
							WHERE order_item_name = %s
							AND order_item_type = 'fee'
							AND order_id = %d
						", $item_id, $order->wc_oid ) );

						if ( ! is_null( $fee_id ) ) {
							wc_update_order_item_meta( $fee_id, '_wootax_index', $index );
							wc_update_order_item_meta( $fee_id, '_wootax_tax_amount', $tax_amount );
							wc_update_order_item_meta( $fee_id, '_wootax_location_id', $location_key );
						}
					break;
				}
			}
		}
	}

	// Remove all wootax_order posts and the corresponding metadata
	$wpdb->query( "
		DELETE FROM {$wpdb->posts} p, {$wpdb->postmeta} pm
		WHERE p.ID = pm.post_id
		AND p.post_type = 'wootax_order'
	" );
}

/**
 * Helper for 4.2 update: Get item type given item ID.
 *
 * @since 5.0
 *
 * @param  int $item_id
 * @return string "shipping," "cart," or "fee" 
 */
function sst_update_42_get_item_type( $item_id ) {
	global $wpdb;

	if ( $item_id == 99999 ) {
		return "shipping";
	} else if ( in_array( get_post_type( $item_id ), array( 'product', 'product_variation' ) ) ) {
		return "product";
	} else {
		return "fee";
	}
}

/**
 * After version 4.5, license keys were no longer required. Remove the license
 * key option.
 *
 * @since 5.0
 */
function sst_update_45_remove_license_option() {
	delete_option( 'wootax_license_key' );
}