<?php

// Prevent data leaks
if ( ! defined( 'ABSPATH' ) ) exit; 

/**
 * WC_WooTax_Subscriptions
 *
 * Adds support for Subscriptions by Brent Shepard
 *
 * @package WooTax
 * @since 4.2
 */

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

		// Handle renewal orders
		add_action( 'woocommerce_renewal_order_payment_complete', array( $this, 'handle_renewal_order' ), 10, 4 );

		// Fix recurring taxes: add "cart_tax" and "shipping_tax" meta keys
		add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'fix_recurring_taxes' ), 15, 2 );

	}

	/**
	 * Modify WooTax item in order total rows; Change key from wootax-rate-do-not-remove to sales-tax
	 *
	 * @since 4.2
	 */
	public function get_order_item_totals( $total_rows, $order ) {

		if ( WC_Subscriptions_Order::order_contains_subscription( $order ) && WC_Subscriptions_Order::get_recurring_total_tax( $order ) > 0 && 'incl' !== $order->tax_display_cart ) {
				
			$new_total_rows = array();

			foreach ( $total_rows as $row_key => $data ) {

				if ( $row_key == 'wootax-rate-do-not-remove' ) {
					$row_key = 'sales-tax';
				}

				$new_total_rows[ $row_key ] = $data;

			}

			return $new_total_rows;

		}

		return $total_rows;

	}

	/**
	 * Notify TaxCloud of tax collected on renewals
	 *
	 * @since 4.0
	 * @param $order_id the ID of the renewal order
	 */
	function handle_renewal_order( $order_id ) {

		global $WC_WooTax_Order;

		// Create a new WC_WooTax_Order object
		$order = $WC_WooTax_Order;
		$order->load_order( $order_id );

		// Build order items array
		$final_items = array();
		$type_array  = array();

		if ( version_compare( WOOCOMMERCE_VERSION, '2.2', '>=' ) ) {
			$order_items = $order->order->get_items() + $order->order->get_fees() + $order->order->get_shipping_methods();
		} else {
			$order_items = $order->order->get_items() + $order->order->get_fees();
		}

		// Construct items array from POST data
		foreach ( $order_items as $item_id => $item ) {

			$product_id = isset( $item['product_id'] ) ? $item['product_id'] : -1;

			if ( get_post_type( $product_id ) == 'product' ) {

				$product = new WC_Product( $product_id );
					
				if ( !$product->is_taxable() ) {
					continue;
				}

			} 

			$qty  = isset( $item['qty'] ) ? $item['qty'] : 1;
			$type = $item['type'];
			$cost = isset( $item['cost'] ) ? $item['cost'] : $item['line_total']; // 'cost' key used by shipping methods in 2.2

			switch ( $type ) {

				case 'shipping':
					$tic  = WOOTAX_SHIPPING_TIC;
					break;
				case 'fee':
					$tic  = WOOTAX_FEE_TIC;
					break;
				case 'line_item':
					$tic  = get_post_meta( $product_id, 'wootax_tic', true );
					break;

			}

			// Calculate unit price
			$unit_price = $cost / $qty;

			// Add item to final items array
			if ( $unit_price != 0 ) {

				// Map item_id to item type 
				$type_array[ $item_id ] = $type == 'shipping' ? 'shipping' : 'cart';
			
				// Add tax amount to tax array
				$old_taxes[ $item_id ] = $order->get_item_tax( $item_id );
				
				// Add to items array 
				$item_data = array(
					'Index'  => '', // Leave Index blank because it is reassigned when WooTaxOrder::generate_lookup_data() is called
					'ItemID' => $item_id, 
					'Qty'    => $qty, 
					'Price'  => $unit_price,	
					'Type'   => $type,
				);	

				if ( !empty( $tic ) && $tic ) {
					$item_data['TIC'] = $tic;
				}

				$final_items[] = $item_data;

			}

		}

		// If we are not using WC 2.2, we need to add a shipping item manually
		if ( version_compare( WOOCOMMERCE_VERSION, '2.2', '<' ) ) {

			if ( $order->order->get_total_shipping() > 0 ) {

				$item_data = array(
					'Index'  => '', // Leave Index blank because it is reassigned when WooTaxOrder::generate_lookup_data() is called
					'ItemID' => WOOTAX_SHIPPING_ITEM, 
					'Qty'    => 1, 
					'Price'  => $order->order->get_total_shipping(),	
					'Type'   => 'shipping',
					'TIC'    => WOOTAX_SHIPPING_TIC
				);	

				$type_array[ WOOTAX_SHIPPING_ITEM ] = 'shipping';

				$final_items[] = $item_data;

			}

		}

		// Perform a tax lookup with the renewal prices
		$result = $order->do_lookup( $final_items, $type_array );

		// Add errors as notes if necessary
		$original_order = new WC_Order( $order->order->post->post_parent );

		if ( $result != true ) {

			$original_order->add_order_note( sprintf( __( 'Tax lookup for renewal order %s failed. Reason: '. $result, 'woocommerce-subscriptions' ), $order_id ) );
		
		} else {

			// Mark order as captured
			$order->complete( $order_id );

			// Add success note
			$original_order->add_order_note( sprintf( __( 'TaxCloud was successfully notified of renewal order %s.', 'woocommerce-subscriptions' ), $order_id ) );

		}

	}

	/**
	 * Add cart_tax/shipping_tax key to any recurring taxes
	 *
	 * @since 4.2
	 * @param $order_id a WooCommerce order ID
	 */
	public function fix_recurring_taxes( $order_id, $posted ) {

		$order = new WC_Order( $order_id );

		if ( WC_Subscriptions_Order::order_contains_subscription( $order ) && WC_Subscriptions_Order::get_recurring_total_tax( $order ) > 0 && 'incl' !== $order->tax_display_cart ) {
			
			if ( count( $order->get_items( 'recurring_tax' ) ) > 0 ) {

				foreach ( $order->get_items( 'recurring_tax' ) as $item_id => $item ) {
					wc_add_order_item_meta( $item_id, 'cart_tax', $item['tax_amount'] );
					wc_add_order_item_meta( $item_id, 'shipping_tax', $item['shipping_tax_amount'] );
				}

			}

		}

	}

}

new WC_WooTax_Subscriptions();