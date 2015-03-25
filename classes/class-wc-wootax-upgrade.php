<?php

/**
 * Scripts for updating WooTax data
 * TODO: FINISH WRITING
 *
 * @since 4.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Do not allow direct access 
}

class WC_WooTax_Upgrade {
	// SHOULD RUN ON ADMIN INIT, CHECK IF UPDATE IS NECESSARY
	// IF SO, USE WOOTAX_ADD_MESSAGE TO ADD PERSISTENT, NON-DISMISSABLE MESSAGE THAT ASKS TO UPGRADE
	// NEED TO ADD A HIDDEN ADMIN PAGE WHERE THE USER CAN BE REDIRECTED TO START THE UPGRADE
	// THEN, WE WILL USE A REDIRECT LOOP TO GO THROUGH ALL POSTS AND UPGRADE UNTIL ALL HAVE BEEN HANDLED
	
	/**
	 * Hooks into WordPress actions/filters
	 *
	 * @since 4.2
	 */
	private function hook_wordpress() {
		// Run update routine if necessary
		add_action( 'admin_init', array( $this, 'update_wootax' ) );

		// Maybe show activation message
		add_action( 'admin_init', array( $this, 'maybe_show_activation_success' ) );
	}

	/**
	 * Handles updates
	 * Only runs if the value of the wootax_version option does not match the current plugin version OR no WooTax tax rate is detected
	 *
	 * @since 4.2
	 */
	public function update_wootax() {
		global $wpdb;

		$version = get_option( 'wootax_version' );

		if ( !$version || version_compare( $version, WT_VERSION, '<' ) ) {

			// Upgrade old addresses to use new multi-address system
			$old_address_field = get_option( 'wootax_address1' );

			if ( $old_address_field ) {

				// Set new address array 
				wootax_set_option( 'wootax_addresses', fetch_business_addresses() );

				// Delete old options
				delete_option( 'wootax_address1' );
				delete_option( 'wootax_address2' );
				delete_option( 'wootax_state' );
				delete_option( 'wootax_city' );
				delete_option( 'wootax_zip5' );
				delete_option( 'wootax_zip4' );

			}

			// Delete deprecated "wootax_shipping_taxable" option if it still exists
			if ( get_option( 'wootax_shipping_taxable' ) )
				delete_option( 'wootax_shipping_taxable' );

			// Transfer settings so they can be used with WooCommerce settings API
			$options = array(
				'tc_id',
				'tc_key',
				'usps_id',
				'show_exempt',
				'exemption_text',
				'company_name',
				'show_zero_tax',
				'tax_based_on',
				'addresses',
				'default_address',
			);
			
			foreach ( $options as $option ) {
				if ( get_option( 'wootax_' . $option ) ) {
					wootax_set_option( $option, get_option( 'wootax_' . $option ) );
					delete_option( 'wootax_' . $option );
				}
			}

			// Loop through all wootax_order posts; transfer metadata to associated shop_order
			// This completes the upgrade from WooTax 4.1 to WooTax 4.2
			$wootax_orders = new WP_Query( array(
				'post_type'      => 'wootax_order',
				'post_status'    => 'any',
				'posts_per_page' => -1,
			) );

			if ( $wootax_orders->have_posts() ) {

				while ( $wootax_orders->have_posts() ) { 

					$wootax_orders->the_post();

					// Get info about original order
					$wt_order_id = $wootax_orders->post->ID;
					$wc_order_id = get_post_meta( $wt_order_id, '_wootax_wc_order_id', true );

					if ( !$wc_order_id ) 
						continue;

					$wc_order = new WC_Order( $wc_order_id );

					// Transfer meta that doesn't need to be changed
					$direct_meta_keys = array( 'tax_total', 'shipping_tax_total', 'captured', 'refunded', 'customer_id', 'tax_item_id', 'exemption_applied' );

					foreach ( $direct_meta_keys as $key ) {
						update_post_meta( $wc_order_id, '_wootax_' . $key, get_post_meta( $wt_order_id, '_wootax_' . $key, true ) );
					}

					// Update _wootax_index, _wootax_location_id, _wootax_tax_amount meta for order items, fees, and shipping methods (in 2.2.x)
					// Also, update mapping array and taxcloud_ids array
					$lookup_data = get_post_meta( $wt_order_id, '_wootax_lookup_data', true );
					$cart_taxes  = get_post_meta( $wt_order_id, '_wootax_cart_taxes', true );

					$new_mapping_array = array();
					$new_tc_ids        = array();
					$identifiers       = array();

					if ( is_array( $lookup_data ) ) {

						$order_items = $wc_order->get_items();
						$order_fees  = $wc_order->get_fees();

						foreach ( $lookup_data as $location_key => $items ) {

							if ( !isset( $new_mapping_array[ $location_key ] ) ) {
								$new_mapping_array[ $location_key ] = array();
							}

							foreach ( $items as $index => $item ) {

								if ( !is_array( $item ) ) 
									continue;

								$tax_amount = isset( $cart_taxes[ $location_key ][ $index ] ) ? $cart_taxes[ $location_key ][ $index ] : 0;
								$item_ident = $item['ItemID'];

								if ( $item_ident == 99999 ) {

									$shipping_item_id = -1;

									// Shipping
									if ( version_compare( WOOCOMMERCE_VERSION, '2.2', '<' ) ) {

										$shipping_item_id = WT_SHIPPING_ITEM;

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
										$identifiers[ WT_SHIPPING_ITEM ] = $item_ident;
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

						}

					}

					// Update TaxCloud Ids
					$new_tc_ids[ $location_key ]['cart_id']  = $items['cart_id'];
					$new_tc_ids[ $location_key ]['order_id'] = $items['order_id'];

					update_post_meta( $wc_order_id, '_wootax_taxcloud_ids', $new_tc_ids );

					// Update mapping array
					update_post_meta( $wc_order_id, '_wootax_mapping_array', $new_mapping_array );

					// Update item identifiers
					update_post_meta( $wc_order_id, '_wootax_identifiers', $identifiers );

				}

				// Delete all wootax_order posts and any associated meta
				$wpdb->query( "DELETE p, pm FROM {$wpdb->posts} p LEFT JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID WHERE p.post_type = 'wootax_order'" );
			
			}

			// Update current version to avoid running the upgrade routine multiple times
			update_option( 'wootax_version', WT_VERSION );

		}

	}
}