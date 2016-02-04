<?php

/**
 * Adds support for the WooCommerce Subscriptions plugin by Brent Shepard
 * @see PLUGIN_DIR/woocommerce-subscriptions/woocommerce-subscriptions.php
 *
 * @package WooCommerce TaxCloud
 * @since 4.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Prevent direct access
}

class WC_WooTax_Subscriptions {
	/**
	 * Constructor
	 *
	 * Hook into WordPress/WooCommerce
	 *
	 * @since 4.2
	 */
	public function __construct() {
		// Change WooCommerce tax key so recurring taxes are displayed correctly
		add_filter( 'woocommerce_get_order_item_totals', array( $this, 'get_order_item_totals' ), 5, 2 );

		// Issue a lookup when a new renewal order is created
		if ( wt_is_subs_2_0() ) {
			add_filter( 'wcs_renewal_order_created', array( $this, 'issue_renewal_lookup' ), 10, 2 );
		} else {
			add_filter( 'woocommerce_subscriptions_renewal_order_created', array( $this, 'issue_renewal_lookup' ), 10, 2 );			
		}

		// Fix recurring taxes: add "cart_tax" and "shipping_tax" meta keys
		add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'fix_recurring_taxes' ), 15, 2 );

		// Remove duplicate tax columns from renewal orders
		add_action( 'woocommerce_subscriptions_renewal_order_created', array( $this, 'remove_duplicate_renewal_taxes' ), 10, 2 );
	}

	/**
	 * Modify WooTax item in order total rows; Change key from wootax-rate-do-not-remove to sales-tax
	 *
	 * @since 4.2
	 */
	public function get_order_item_totals( $total_rows, $order ) {
		$contains_sub = wt_is_subs_2_0() ? wcs_order_contains_subscription( $order ) : WC_Subscriptions_Order::order_contains_subscription( $order );

		if ( $contains_sub && 'incl' !== $order->tax_display_cart ) {				
			$new_total_rows = array();

			foreach ( $total_rows as $row_key => $data ) {
				if ( $row_key == strtolower( apply_filters( 'wootax_rate_code', 'WOOTAX-RATE-DO-NOT-REMOVE' ) ) ) {
					$row_key = 'sales-tax';
				}

				$new_total_rows[ $row_key ] = $data;
			}
			return $new_total_rows;
		}

		return $total_rows;
	}

	/**
	 * Issue a tax lookup when a new renewal order is created
	 *
	 * @param $renewal_order - renewal order object
	 * @param $order_or_subscription - original order or subscription object
	 * @since 1.0
	 */
	public function issue_renewal_lookup( $renewal_order, $order_or_subscription ) {
		$renewal_order_id = $renewal_order->id;

		$order = WT_Orders::get_order( $renewal_order_id );

		// Find parent order object
		$parent_order = $order_or_subscription;
			
		if ( class_exists( 'WC_Subscription' ) && $parent_order instanceof WC_Subscription ) {
			if ( $parent_order->is_manual() ) {
				return $renewal_order; // If subscription requires manual renewal, a lookup will occur when the renewal is processed
			} else {
				$parent_order = $order_or_subscription->order;
			}
		}

		// Get customer address from original order/subscription if necessary
		if ( ! wootax_is_valid_address( $order->destination_address, true ) ) {
			$order->destination_address = $this->get_destination_address( $parent_order );
		}

		// Reset WooTax meta values
		foreach ( WT_Orders::$defaults as $key => $val ) {
			WT_Orders::update_meta( $renewal_order_id, $key, $val );
		}

		// Build and format items array
		$order_items = $order->order->get_items() + $order->order->get_fees();

		if ( version_compare( WOOCOMMERCE_VERSION, '2.2', '<' ) ) {
			$shipping_method = array( 
				'type' => 'shipping',
				'cost' => $order->order->get_total_shipping(),
			);

			$order_items = $order_items + array( WT_SHIPPING_ITEM => $shipping_method );
		} else {
			$order_items = $order_items + $order->order->get_shipping_methods();
		}

		$final_items = $type_array = array();
		$tax_based_on = WC_WooTax::get_option( 'tax_based_on' );

		foreach ( $order_items as $item_id => $item ) {
			$type = $item['type'];
			$qty  = isset( $item['qty'] ) ? $item['qty'] : 1;
			$cost = isset( $item['cost'] ) ? $item['cost'] : $item['line_total']; // 'cost' key used by shipping methods in 2.2

			switch ( $type ) {
				case 'shipping':
					$tic = WT_SHIPPING_TIC;
					break;
				case 'fee':
					$tic = WT_FEE_TIC;
					break;
				case 'line_item':
					$tic  = wt_get_product_tic( $item['product_id'], $item['variation_id'] );
					$type = 'cart';
					break;
			}

			// Only add an item if its cost is nonzero
			if ( $cost != 0 ) {
				$unit_price = $cost / $qty;

				if ( $tax_based_on == 'item-price' || !$tax_based_on ) {
					$price = $unit_price; 
				} else {
					$qty   = 1;
					$price = $cost; 
				}

				$item_data = array(
					'Index'  => '',
					'ItemID' => $item_id,
					'Qty'    => $qty,
					'Price'  => $price,
					'Type'   => $type,
				);	

				if ( $tic )
					$item_data['TIC'] = $tic;

				$final_items[] = $item_data;

				$type_array[ $item_id ] = $type;
			}
		}

		// Perform lookup
		$result = $order->do_lookup( $final_items, $type_array );

		if ( ! is_array( $result ) ) {
			$parent_order->add_order_note( sprintf( __( 'Tax lookup for renewal order %s failed. Reason: '. $result, 'woocommerce-subscriptions' ), $renewal_order_id ) );
		} else {
			$parent_order->add_order_note( sprintf( __( 'Tax lookup for renewal order %s successful.', 'woocommerce-subscriptions' ), $renewal_order_id ) );
		}

		return $renewal_order;
	}

	/**
	 * Add cart_tax/shipping_tax key to any recurring taxes
	 *
	 * @since 4.2
	 * @param $order_id a WooCommerce order ID
	 */
	public function fix_recurring_taxes( $order_id, $posted ) {
		$order = new WC_Order( $order_id );

		$contains_sub = wt_is_subs_2_0() ? wcs_order_contains_subscription( $order ) : WC_Subscriptions_Order::order_contains_subscription( $order );

		if ( $contains_sub && 'incl' !== $order->tax_display_cart ) {			
			if ( count( $order->get_items( 'recurring_tax' ) ) > 0 ) {
				foreach ( $order->get_items( 'recurring_tax' ) as $item_id => $item ) {
					wc_update_order_item_meta( $item_id, 'cart_tax', $item['tax_amount'] );
					wc_update_order_item_meta( $item_id, 'shipping_tax', $item['shipping_tax_amount'] );
					wc_update_order_item_meta( $item_id, 'compound', true );
				}
			}
		}
	}

	/**
	 * Get destination address information from original order
	 *
	 * @since 4.2
	 */
	public function get_destination_address( $order ) {
		// Initialize blank address array
		$address = array();
		
		// Construct final address arraya
		$parsed_zip = parse_zip( $order->shipping_postcode );

		$address['Address1'] = $order->shipping_address_1;
		$address['Address2'] = $order->shipping_address_2;
		$address['Country']  = $order->shipping_country;
		$address['State']    = $order->shipping_state;
		$address['City']     = $order->shipping_city;
		$address['Zip5']     = $parsed_zip['zip5'];
		$address['Zip4']     = $parsed_zip['zip4']; 

		// Return final address
		return $address;
	}

	/**
	 * Remove duplicate tax column from renewal orders
	 *
	 * @since 4.2
	 */
	public function remove_duplicate_renewal_taxes( $renewal_order, $original_order ) {
		global $wpdb;

		$original_taxes = $original_order->get_items( 'recurring_tax' );
		$new_taxes      = $renewal_order->get_taxes();
		$to_remove      = array();

		foreach ( $original_taxes as $tax_item_id => $data ) {
			if ( $data['rate_id'] != WT_RATE_ID ) {
				continue;
			}

			foreach ( $new_taxes as $tax_id => $tax_data ) {
				if ( $tax_data['tax_amount'] == $data['tax_amount'] && $tax_data['rate_id'] == $data['rate_id'] ) {
					$to_remove[] = $tax_id;
				}
			}
		}	

		foreach ( $to_remove as $tax_item_id ) {
			wc_delete_order_item( $tax_item_id );
		}
	}

	/**
	 * Check whether all the subscription product items in the cart have a free trial.
	 *
	 * This is a bridging method used to ensure that WooTax code works with Subs 2.0.x and Subs 1.5.x
	 *
	 * @since 4.6
	 */
	public static function all_cart_items_have_free_trial() {
		if ( version_compare( WC_Subscriptions::$version, '2.0', '>=' ) ) {
			return WC_Subscriptions_Cart::all_cart_items_have_free_trial();
		} else {
			$all_items_have_free_trial = true;

			foreach ( WC()->cart->get_cart() as $cart_item ) {
				if ( ! WC_Subscriptions_Product::is_subscription( $cart_item['product_id'] ) ) {
					$all_items_have_free_trial = false;
					break;
				} else {
					$trial_length = ( isset( $cart_item['data']->subscription_trial_length ) ) ? $cart_item['data']->subscription_trial_length : WC_Subscriptions_Product::get_trial_length( $cart_item['data'] );
					if ( 0 == $trial_length ) {
						$all_items_have_free_trial = false;
						break;
					}
				}
			}

			return $all_items_have_free_trial;
		}
	}
}

new WC_WooTax_Subscriptions();