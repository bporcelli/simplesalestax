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
	// TODO: REFACTOR/IMPROVE
	// Set new address array 
	SST()->set_option( 'wootax_addresses', fetch_business_addresses() );

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
	// TODO: CHECK AFTER SETTINGS SYSTEM IS UPDATED
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
 * storing tax data as order metadata. This function removes all existing
 * "wootax_order" posts and updates order data to use the new format.
 *
 * @since 5.0
 */
function sst_update_42_migrate_order_data() {
	// TODO: CHECK WHETHER UPDATE IS NEEDED BEFORE RUNNING.
	$needs_update = count( get_posts( 'post_type=wootax_order&posts_per_page=1&post_status=any' ) ) == 1;

	global $wpdb;

	// Number of posts to process at once
	$posts_per_page = 10;

	// Index of last processed post
	$last_post = $_POST[ 'last_post' ];

	// Page counters
	$total_pages  = $last_post == 0 ? 0 : $_POST[ 'total_pages' ];
	$current_page = $last_post == 0 ? 1 : $_POST[ 'current_page' ];

	// On first run, determine $total_count/$total_pages
	if ( $last_post == 0 ) {
		$total_count = $wpdb->get_var( "SELECT COUNT(ID) FROM $wpdb->posts WHERE post_type = 'wootax_order'" );

		if ( $total_count == 0 ) {
			update_option( 'wootax_version', SST()->version );

			self::dismiss_update_message();

			die ( json_encode( array( 
				'status'   => 'done', 
				'message'  => 'No more posts to update. Redirecting...',
				'redirect' => get_admin_url( 'plugins.php' ),
			) ) );
		}
		
		$total_pages = ceil( $total_count / $posts_per_page );
	}

	// Select posts from index $last_post to $posts_per_page for processing
	$posts = $wpdb->get_results( "SELECT p.ID AS WTID, pm.meta_value AS WCID FROM $wpdb->posts p LEFT JOIN $wpdb->postmeta pm ON pm.post_id = p.ID WHERE p.post_type = 'wootax_order' AND pm.meta_key = '_wootax_wc_order_id' ORDER BY p.ID ASC LIMIT $last_post, $posts_per_page" );

	if ( count ( $posts ) == 0 ) {
		update_option( 'wootax_version', SST()->version );

		self::dismiss_update_message();
		self::remove_order_posts();

		die ( json_encode( array( 
			'status'   => 'done', 
			'message'  => 'No more posts to update. Redirecting...',
			'redirect' => get_admin_url( 'plugins.php' ),
		) ) );
	}

	// Loop through posts and update
	foreach ( $posts as $post ) {
		$wt_order_id = $post->WTID;
		$wc_order_id = $post->WCID;

		// Transfer meta that doesn't need to be changed
		$direct_meta_keys = array( 'tax_total', 'shipping_tax_total', 'captured', 'refunded', 'customer_id', 'tax_item_id', 'exemption_applied' );

		foreach ( $direct_meta_keys as $key ) {
			update_post_meta( $wc_order_id, '_wootax_' . $key, get_post_meta( $wt_order_id, '_wootax_' . $key, true ) );
		}

		// WooTax order item meta and mapping array structure was changed drastically in 4.2; update accordingly
		$lookup_data = get_post_meta( $wt_order_id, '_wootax_lookup_data', true );
		$cart_taxes  = get_post_meta( $wt_order_id, '_wootax_cart_taxes', true );

		$new_mapping_array = array();
		$new_tc_ids        = array();
		$identifiers       = array();

		if ( is_array( $lookup_data ) ) {
			$wc_order = new WC_Order( $wc_order_id );

			$order_items = $wc_order->get_items();
			$order_fees  = $wc_order->get_fees();

			foreach ( $lookup_data as $location_key => $items ) {
				if ( !isset( $new_mapping_array[ $location_key ] ) ) {
					$new_mapping_array[ $location_key ] = array();
				}

				foreach ( $items as $index => $item ) {
					if ( !is_array( $item ) ) {
						continue;
					}

					$tax_amount = isset( $cart_taxes[ $location_key ][ $index ] ) ? $cart_taxes[ $location_key ][ $index ] : 0;
					$item_ident = $item['ItemID'];

					if ( $item_ident == 99999 ) {
						$shipping_item_id = -1;

						// Shipping
						if ( version_compare( SST_WOO_VERSION, '2.2', '<' ) ) {
							$shipping_item_id = SST_SHIPPING_ITEM;

							update_post_meta( $wc_order_id, '_wootax_first_found', $location_key );
							update_post_meta( $wc_order_id, '_wootax_shipping_index', $index );
						} else {
							$shipping_methods = $wc_order->get_items( 'shipping' );

							foreach ( $shipping_methods as $item_id => $method ) {
								if ( $shipping_item_id == -1 ) {
									$shipping_item_id = $item_id;

									wc_update_order_item_meta( $item_id, '_wootax_index', $index );
									wc_update_order_item_meta( $item_id, '_wootax_tax_amount', $tax_amount );
									wc_update_order_item_meta( $item_id, '_wootax_location_id', $location_key );
								}									
							}
						}	

						if ( $shipping_item_id != -1 ) {
							$new_mapping_array[ $location_key ][ $item_ident ] = $index;

							$identifiers[ SST_SHIPPING_ITEM ] = $item_ident;
						}
					} else if ( in_array( get_post_type( $item_ident ), array( 'product', 'product-variation' ) ) ) {
						// Cart item
						$cart_item_id = -1;

						if ( get_post_type( $item_ident ) == 'product' ) {
							$product_id   = $item_ident;
							$variation_id = '';
						} else if ( get_post_type( $item_ident ) == 'product-variation' ) {
							$variation_id = $item_ident;
							$product_id   = wp_get_post_parent_id( $variation_id );
						}

						foreach ( $order_items as $item_id => $item_data ) {
							if ( !empty( $item_data['variation_id'] ) && $item_data['variation_id'] == $variation_id || $item_data['product_id'] == $product_id ) {
								$cart_item_id = $item_id;
								break;
							}
						}

						if ( $cart_item_id != -1 ) {
							wc_update_order_item_meta( $cart_item_id, '_wootax_index', $index );
							wc_update_order_item_meta( $cart_item_id, '_wootax_tax_amount', $tax_amount );
							wc_update_order_item_meta( $cart_item_id, '_wootax_location_id', $location_key );

							$new_mapping_array[ $location_key ][ $item_ident ] = $index;

							$identifiers[ $item_ident ] = $item_ident;
						} 
					} else {
						// Fee
						$fee_id = -1;

						foreach ( $order_fees as $item_id => $item_data ) {
							if ( sanitize_title( $item_data['name'] ) == $item_ident ) {
								$fee_id = $item_id;
							}
						}

						if ( $fee_id != -1 ) {
							wc_update_order_item_meta( $fee_id, '_wootax_index', $index );
							wc_update_order_item_meta( $fee_id, '_wootax_tax_amount', $tax_amount );
							wc_update_order_item_meta( $fee_id, '_wootax_location_id', $location_key );

							$new_mapping_array[ $location_key ][ $item_ident ] = $index;

							$identifiers[ $item_ident ] = $item_ident;
						}
					}
				}

				$new_tc_ids[ $location_key ]['cart_id']  = $items['cart_id'];
				$new_tc_ids[ $location_key ]['order_id'] = $items['order_id'];
			}
		}

		// Update TaxCloud Ids
		update_post_meta( $wc_order_id, '_wootax_taxcloud_ids', $new_tc_ids );

		// Update mapping array
		update_post_meta( $wc_order_id, '_wootax_mapping_array', $new_mapping_array );

		// Update item identifiers
		update_post_meta( $wc_order_id, '_wootax_identifiers', $identifiers );
	}

	// Notify client that processing has succeeded and continue processing
	if ( $current_page < $total_pages ) {
		$last_post += $posts_per_page;
		$current_page++;
	} else {
		$last_post += count( $posts );
	}

	die( json_encode( array( 
		'status'       => 'working', 
		'last_post'    => $last_post, 
		'current_page' => $current_page, 
		'total_pages'  => $total_pages,
	) ) );

	// TODO: REMOVE ALL WOOTAX_ORDER POSTS AND THE POST TYPE
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