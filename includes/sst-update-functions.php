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
	$address = new TaxCloud\Address(
		get_option( 'wootax_address1' ),
		get_option( 'wootax_address2' ),
		get_option( 'wootax_city' ),
		get_option( 'wootax_state' ),
		get_option( 'wootax_zip5' ),
		get_option( 'wootax_zip4' )
	);

	SST_Settings::set( 'addresses', array( json_encode( $address ) ) );

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

/**
 * In 5.0, we added support for multiple default origin addresses. This function
 * migrates existing address data to work with the new system.
 *
 * @since 5.0
 */
function sst_update_50_origin_addresses() {
	$default_address = SST_Settings::get( 'default_address' );
	$addresses       = SST_Settings::get( 'addresses' );

	foreach ( $addresses as $key => $address ) {
		try {
			$new_address = new SST_Origin_Address(
				$key,
				$key === $default_address,
				isset( $address['address_1'] ) ? $address['address_1'] : '',
				isset( $address['address_2'] ) ? $address['address_2'] : '',
				isset( $address['city'] ) ? $address['city'] : '',
				isset( $address['state'] ) ? $address['state'] : '',
				isset( $address['zip5'] ) ? $address['zip5'] : '',
				isset( $address['zip4'] ) ? $address['zip4'] : ''
			);

			$addresses[ $key ] = json_encode( $new_address );
		} catch ( Exception $ex ) {
			// Address was invalid -- not much we can do
			unset( $addresses[ $key ] );
		}
	}

	SST_Settings::set( 'addresses', $addresses );
	SST_Settings::set( 'default_address', null );
}

/**
 * Prior to 5.0, default category TICs were stored as WordPress options. After 5.0,
 * category TICs are stored as term metadata.
 *
 * @since 5.0
 */
function sst_update_50_category_tics() {
	$terms = get_terms( array(
		'taxonomy'   => 'product_cat',
		'hide_empty' => false,
	) );

	foreach ( $terms as $term ) {
		$tic = get_option( 'tic_' . $term->term_id );
		if ( ! empty( $tic ) ) {
			if ( is_array( $tic ) ) {
				$tic = $tic['tic'];
			}
			
			update_term_meta( $term->term_id, 'tic', $tic );
			
			delete_option( 'tic_' . $term->term_id );
		}
	}
}

/**
 * Before 5.0, we used a "lookup_data" data structure to store information about
 * the tax lookups for an order. The structure consisted of a two-dimensional array
 * of CartItems indexed by origin address key. One lookup was sent for each of
 * the entries in "lookup_data," and the CartID/OrderID sent to TaxCloud were stored
 * in a separate "taxcloud_ids" array, also indexed by origin address key. 
 *
 * In addition to the above, each order had two other data structures: an "identifiers"
 * array and a "mapping" array. The identifiers array was used to deal with the fact
 * that different item identifiers were sent to TaxCloud during checkout and after. It
 * mapped universally available item IDs (product/variation IDs for line items, 
 * sanitized name for fees, SHIPPING for shipping methods) to the IDs sent to TaxCloud
 * during checkout, and was meant to ensure that orders could be captured immediately
 * after checkout without an additional lookup.
 *
 * The mapping array was generated for each entry in lookup_data before a lookup and
 * mapped tuples (origin address key, CartItem index) to an internal item ID (or, during
 * checkout, an array with keys 'type', 'index', 'id' and 'key').
 *
 * Each order also had two boolean flags 'captured' and 'refunded' associated with
 * it. The 'captured' flag was set after the order was captured in TaxCloud, and the
 * 'refunded' flag was set when a partial or full refund was issued.
 *
 * In 5.0, the "lookup_data" and "mapping_array" data structures were merged into a
 * simplified "packages" data structure. In addition, the "identifiers" data structure
 * was eliminated. The boolean flags 'captured' and 'refunded' have also been merged
 * into a single 'status' field. This function updates all existing orders to use the
 * new data structures.
 */
function sst_update_50_order_data() {
	global $wpdb;

	/* Get orders */
	$orders = wc_get_orders( array(
		'status' => 'any',
		'type'   => 'shop_order',
		'limit'  => -1,
	) );

	foreach ( $orders as $order ) {
		$_order = new SST_Order( $order );

		/* Exemption certificates previously stored under key 'exemption_applied'.
		 * In 5.0, we move them to key 'exempt_cert' and store them in a different
		 * format. */
		$old_certificate = $_order->get_meta( 'exemption_applied' );
		
		if ( is_array( $old_certificate ) && isset( $old_certificate['CertificateID'] ) ) {
			$_order->update_meta( 'exempt_cert', new TaxCloud\ExemptionCertificateBase(
				$old_certificate['CertificateID']
			) );
		}

		/* Actions we take from here will depend on the order status (pending,
		 * captured, refunded). */
		$captured = $_order->get_meta( 'captured' );
		$refunded = $_order->get_meta( 'refunded' );

		if ( ! $captured && ! $refunded ) {			/* Pending */

			/* Recalc taxes to regenerate data structures */
			$_order->calculate_taxes();
			$_order->calculate_totals( false );

			$_order->update_meta( 'status', 'pending' );
		} else if ( $captured && ! $refunded ) {	/* Captured */

			$lookup_data   = $_order->get_meta( 'lookup_data' );
			$taxcloud_ids  = $_order->get_meta( 'taxcloud_ids' );
			$mapping_array = $_order->get_meta( 'mapping_array' );
			$identifiers   = $_order->get_meta( 'identifiers' );

			/* Create a package for each entry in lookup_data */
			if ( is_array( $lookup_data ) ) {
				$packages = array();

				foreach ( $lookup_data as $address_key => $cart_items ) {

					/* Create package */
					$package = sst_create_package();

					/* Set cart ID and order ID */
					$package['cart_id']  = $taxcloud_ids[ $address_key ]['cart_id'];
					$package['order_id'] = $taxcloud_ids[ $address_key ]['order_id'];

					/* Update map */
					$old_map = $mapping_array[ $address_key ];

					foreach ( $old_map as $item_key => $item ) {

						if ( is_array( $item ) ) {	/* Map generated during checkout */
							
							$type    = 'cart' == $item['type'] ? 'line_item' : $item['type'];
							$id      = $item['id'];
							$cart_id = $id;
							
							/* Original ID for line items is a cart key. Map to product id
							 * using identifiers array */
							if ( 'line_item' == $type ) {
								$id = array_search( $item['id'], $identifiers );
							}
						} else {					/* Map generated post-checkout */
							
							$cart_id = $item;
							$_item   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}woocommerce_order_items WHERE order_item_id = %d", $item ) );
							$type    = $_item->order_item_type;
							
							switch ( $type ) {
								case 'line_item':
									$product_id   = wc_get_order_item_meta( $item, '_product_id' );
									$variation_id = wc_get_order_item_meta( $item, '_variation_id' );
									$id           = $variation_id ? $variation_id : $product_id;
								break;
								case 'shipping':
									$id = SST_SHIPPING_ITEM;
								break;
								case 'fee':
									$id = sanitize_title( $_item->order_item_name );
							}
						}

						$package['map'][ $item_key ] = array(
							'type'    => $type,
							'id'      => $id,
							'cart_id' => $cart_id,
						);
					}

					/* Convert cart items to CartItem objects. Set the package 
					 * shipping method and find any extra shipping methods. */
					$shipping_item_ids = array();
					$extra_methods     = array();

					foreach ( $cart_items as $item_key => $item ) {
						$cart_items[ $item_key ] = new TaxCloud\CartItem(
							$item['Index'],
							$item['ItemID'],
							isset( $item['TIC'] ) ? $item['TIC'] : null,
							$item['Price'],
							$item['Qty']
						);

						if ( 'shipping' == $package['map'][ $item_key ]['type'] ) {
							/* There's no perfect way to determine the shipping method this
							 * item corresponds to -- we'll use the item price and order id
							 * to make an educated guess */
							$method = $wpdb->get_row( $wpdb->prepare( "
								SELECT i.order_item_id AS item_id, m.meta_value AS method_id
								FROM {$wpdb->prefix}woocommerce_order_itemmeta m, {$wpdb->prefix}woocommerce_order_items i
								WHERE i.order_id = %d
								AND m.meta_key = 'method_id'
								AND i.order_item_id = m.order_item_id
								AND i.order_item_type = 'shipping' 
								AND EXISTS (
									SELECT * FROM {$wpdb->prefix}woocommerce_order_itemmeta
									WHERE order_item_id = i.order_item_id
									AND meta_key = 'cost'
									AND meta_value = %d
								)
								AND i.order_item_id NOT IN ( " . implode( ',', $shipping_item_ids ) . " )
							", $_order->get_id(), $item['Price'] ) );

							if ( $method ) {
								$shipping_item_ids[] = $method->item_id;
								$shipping_method     = new WC_Shipping_Rate( '', '', $item['Price'], array(), $method->method_id );
								
								if ( is_null( $package['shipping'] ) )
									$package['shipping'] = $shipping_method;
								else
									$extra_methods[ $item_key ] = $shipping_method;
							}  /* How to handle failure? */
						}
					}

					$package['contents'] = $cart_items;

					/* Create additional shipping package for each extra shipping
					 * method. This is necessary because 5.0 only allows one method
					 * per package. */
					foreach ( $extra_methods as $item_key => $method ) {
						$new_package = $package;

						$package['contents'][ $item_key ]['Index'] = 0;

						$new_package['contents'] = array( $package['contents'][ $item_key ] );
						$new_package['shipping'] = $method;
						$new_package['map']      = array( $package['map'][ $item_key ] );
						
						unset( $package['contents'][ $item_key ] );
						unset( $package['map'][ $item_key ] );

						$packages[] = $new_package;
					}

					$packages[] = $package;

					/* For each package, generate a lookup request. We ignore all
					* parameters other than cartItems because they aren't used
					* when performing refunds. */
					foreach ( $packages as &$package ) {
						$package['request'] = new TaxCloud\Request\Lookup(
							'',						// apiLoginID
							'',						// apiKey
							'',						// customerID
							'',						// cartID
							$package['contents'],	// cartItems
							null, 					// origin
							null  					// destination
						);
					}
				}
			
				$_order->update_meta( 'packages', $packages );
			}
		
			$_order->update_meta( 'status', 'captured' );
		} else if ( $refunded ) {					/* Refunded */
			
			$_order->update_meta( 'status', 'refunded' );
		}

		$_order->save();
	}
}
