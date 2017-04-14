<?php

/**
 * Admin functions for the WooTax Subscriptions extension
 *
 * @author Brett Porcelli
 * @since 1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * AJAX Handler. Update recurring line taxes for WooCommerce Subscriptions <= 1.5.
 *
 * @since 1.0
 */
function wt_ajax_update_recurring_tax() {
	global $wpdb;

	check_ajax_referer( 'woocommerce-subscriptions', 'security' );

	$order_id  = absint( $_POST[ 'order_id' ] );
	$country   = strtoupper( esc_attr( $_POST[ 'country' ] ) );

	// Step out of the way if the customer is not located in the US
	if ( $country != 'US' )
		return;

	$shipping      = $_POST[ 'shipping' ];
	$line_subtotal = isset( $_POST[ 'line_subtotal' ] ) ? esc_attr( $_POST['line_subtotal'] ) : 0;
	$line_total    = isset( $_POST[ 'line_total' ] ) ? esc_attr( $_POST['line_total'] ) : 0;

	// Set up WC_WooTax_Order object
	$order = WT_Orders::get_order( $order_id );
	
	$taxes = $shipping_taxes = array();
    
    $return     = array();
 	$item_data  = array();
 	$type_array = array();

 	// Get product ID, and, if possible, variatian ID
	if ( isset( $_POST[ 'order_item_id' ] ) ) {
		$product_id   = woocommerce_get_order_item_meta( $_POST[ 'order_item_id' ], '_product_id' );
		$variation_id = woocommerce_get_order_item_meta( $_POST[ 'order_item_id' ], '_variation_id' );
	} elseif ( isset( $_POST[ 'product_id' ] ) ) {
		$product_id   = esc_attr( $_POST[ 'product_id' ] );
		$variation_id = '';
	}

	$final_id = $variation_id ? $variation_id : $product_id;

	if ( ! empty( $product_id ) && WC_Subscriptions_Product::is_subscription( $final_id ) ) {
		// Add product to items array
		$product = WC_Subscriptions::get_product( $final_id );

		$item_info = array(
			'Index'  => '',
			'ItemID' => isset( $_POST[ 'order_item_id' ] ) ? $_POST[ 'order_item_id' ] : $final_id, 
			'Qty'    => 1, 
			'Price'  => $line_subtotal > 0 ? $line_subtotal : $product->get_price(),	
			'Type'   => 'cart',
		);

		$tic = SST_Product::get_tic( $product_id, $variation_id );

		if ( ! empty( $tic ) && $tic )
			$item_info[ 'TIC' ] = $tic;

		$item_data[] = $item_info;

		$type_array[ $_POST[ 'order_item_id' ] ] = 'cart';

		// Add shipping to items array
		if ( $shipping > 0 ) {
			$item_data[] = array(
				'Index'  => '',
				'ItemID' => SST_SHIPPING_ITEM, 
				'TIC'    => apply_filters( 'wootax_shipping_tic', SST_DEFAULT_SHIPPING_TIC ),
				'Qty'    => 1, 
				'Price'  => $shipping,	
				'Type'   => 'shipping',
			);

			$type_array[ SST_SHIPPING_ITEM ] = 'shipping';
		}

		// Add fees to items array
		foreach ( $order->order->get_fees() as $item_id => $fee ) {
			if ( $fee[ 'recurring_line_total' ] == 0 )
				continue;

			$item_data[] = array(
				'Index'  => '',
				'ItemID' => $item_id, 
				'TIC'    => apply_filters( 'wootax_fee_tic', SST_DEFAULT_FEE_TIC ),
				'Qty'    => 1, 
				'Price'  => $fee[ 'recurring_line_total' ],	
				'Type'   => 'fee',
			);

			$type_array[ $item_id ] = 'fee';
		}

		// Issue Lookup request
		$res = $order->do_lookup( $item_data, $type_array, true );

		if ( is_array( $res ) ) {
			$return[ 'recurring_shipping_tax' ]      = 0;
			$return[ 'recurring_line_subtotal_tax' ] = 0;
			$return[ 'recurring_line_tax' ]          = 0;

			foreach ( $res as $item ) {

				$item_id  = $item->ItemID;
				$item_tax = $item->TaxAmount;

				if ( $item_id == SST_SHIPPING_ITEM ) {
					$return[ 'recurring_shipping_tax' ] += $item_tax;
				} else {
					$return[ 'recurring_line_subtotal_tax' ] += $item_tax;
					$return[ 'recurring_line_tax' ]          += $item_tax;
				}

			}

			$taxes[ SST_RATE_ID ]          = $return[ 'recurring_line_tax' ];
			$shipping_taxes[ SST_RATE_ID ] = $return[ 'recurring_shipping_tax' ];

		 	// Get tax rates
			$tax_codes = array( SST_RATE_ID => apply_filters( 'wootax_rate_code', 'WOOTAX-RATE-DO-NOT-REMOVE' ) );

			// Remove old tax rows
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}woocommerce_order_itemmeta WHERE order_item_id IN ( SELECT order_item_id FROM {$wpdb->prefix}woocommerce_order_items WHERE order_id = %d AND order_item_type = 'recurring_tax' )", $order_id ) );
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}woocommerce_order_items WHERE order_id = %d AND order_item_type = 'recurring_tax'", $order_id ) );

			// Now merge to keep tax rows
			ob_start();

			foreach ( array_keys( $taxes + $shipping_taxes ) as $key ) {
				$item            = array();
				$item[ 'rate_id' ] = $key;
				$item[ 'name' ]    = $tax_codes[ $key ];

				if ( version_compare( SST_WOO_VERSION, '2.2', '>=' ) ) {
					$item[ 'label' ]    = WC_Tax::get_rate_label( $key );
					$item[ 'compound' ] = WC_Tax::is_compound( $key );
				} else {
					// get_rate_label() and is_compound() were instance methods in WooCommerce < 2.3
					$tax = new WC_Tax();

					$item[ 'label' ]    = $tax->get_rate_label( $key );
					$item[ 'compound' ] = $tax->is_compound( $key ) ? 1 : 0;
				}

				$item[ 'tax_amount' ]          = wc_round_tax_total( isset( $taxes[ $key ] ) ? $taxes[ $key ] : 0 );
				$item[ 'shipping_tax_amount' ] = wc_round_tax_total( isset( $shipping_taxes[ $key ] ) ? $shipping_taxes[ $key ] : 0 );

				if ( ! $item[ 'label' ] )
					$item[ 'label' ] = WC()->countries->tax_or_vat();

				// Add line item
				$item_id = woocommerce_add_order_item( $order_id, array(
					'order_item_name' => $item[ 'name' ],
					'order_item_type' => 'recurring_tax'
				) );

				// Add line item meta
				if ( $item_id ) {
					woocommerce_add_order_item_meta( $item_id, 'rate_id', $item[ 'rate_id' ] );
					woocommerce_add_order_item_meta( $item_id, 'label', $item[ 'label' ] );
					woocommerce_add_order_item_meta( $item_id, 'compound', $item[ 'compound' ] );
					woocommerce_add_order_item_meta( $item_id, 'tax_amount', $item[ 'tax_amount' ] );
					woocommerce_add_order_item_meta( $item_id, 'shipping_tax_amount', $item[ 'shipping_tax_amount' ] );
				}

				include( plugin_dir_path( WC_Subscriptions::$plugin_file ) . 'templates/admin/post-types/writepanels/order-tax-html.php' );
			}

			$return[ 'tax_row_html' ] = ob_get_clean();

			echo json_encode( $return );
		}
	}

	die();
}

add_action( 'wp_ajax_woocommerce_subscriptions_calculate_line_taxes', 'wt_ajax_update_recurring_tax', 1 );

/**
 * Update the shipping/cart tax totals for a given tax item.
 *
 * @since 1.0
 *
 * @param int $tax_item_id ID of the tax item to update.
 * @param double $cart_tax New cart tax value.
 * @param double $shipping_tax New shipping tax value.
 */
function wt_update_tax_item_totals( $tax_item_id, $cart_tax, $shipping_tax ) {
	wc_update_order_item_meta( $tax_item_id, 'cart_tax', $cart_tax );
	wc_update_order_item_meta( $tax_item_id, 'shipping_tax', $shipping_tax );
}

add_action( 'wt_tax_item_updated', 'wt_update_tax_item_totals', 10, 3 );