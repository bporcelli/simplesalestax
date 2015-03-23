<?php

/**
 * Contains methods for compatibility with WooCommerce Subscriptions plugin
 *
 * @package WooTax
 * @since 4.2
 */

if ( is_plugin_active( 'woocommerce-subscriptions/woocommerce-subscriptions.php' ) ):

/**
 * Loops through recurring tax items and sets the cart_tax and shipping_tax meta values
 *
 * @since 4.0
 * @param $order_id a WooCommerce order ID
 */
function fix_recurring_taxes( $order_id ) {

	$order = new WC_Order( $order_id );

	$recurring_taxes = $order->get_items( 'recurring_tax' );
	
	if ( count($recurring_taxes) == 0 ) {
		return;
	}

	foreach ($recurring_taxes as $key => $tax) {

		if ($tax['rate_id'] == get_option('wootax_rate_id') || $tax['rate_id'] == 'wootax_shipping_tax') {
			woocommerce_update_order_item_meta( $key, 'compound', true );
			woocommerce_update_order_item_meta( $key, 'name', 'Sales Tax' );
			woocommerce_update_order_item_meta( $key, 'cart_tax', $tax['tax_amount'] );
			woocommerce_update_order_item_meta( $key, 'shipping_tax', $tax['shipping_tax_amount'] );
		}

	} 

}

/**
 * Added to improve support for WooCommerce Subscriptions plugin
 * Changes the name of the tax total row from "tax" to "sales-tax" so Subscriptions recognizes it
 *
 * @since 4.0
 * @param $total_rows an array of order totals
 * @return an array of order totals
 */
function fix_tax_key( $total_rows ) {

	if ( !isset( $total_rows['tax'] ) && !isset( $total_rows[get_option( 'wootax_rate_id' )] ) )
		return $total_rows;

	$new_total_rows = array();

	foreach ($total_rows as $key => $values) {

		if ($key == 'tax' || $key == get_option( 'wootax_rate_id' )) {
			$new_total_rows[sanitize_title( $values['label'] )] = $values;
		} else {
			$new_total_rows[$key] = $values;
		}

	}

	return $new_total_rows;

}

/**
 * Fired when order renewal payments are complete
 * Notifys TaxCloud such that the tax collected is reported
 *
 * @since 4.0
 * @param $order_id the ID of the renewal order
 */
function handle_renewal_order( $order_id ) {

	global $WC_WooTax_Order;

	// Create a new WC_WooTax_Order object
	$order = $WC_WooTax_Order;
	$order->load_order( $order_id );

	// Associate the renewal order with the new WooTax Order
	//update_post_meta( $order_id, '_wootax_order_id', $order->orderID );
	//$order->wc_order_id = $order_id;

	// Make sure WC_WooTax_Order.php knows we are handling a renewal
	$order->is_renewal = true;

	// Set original_order property so we can fetch the customer's destination address
	$order->original_order = new WC_Order( get_post_meta( $order_id, '_original_order', true ) );

	// Force update of order meta
	$order->save();

	// Perform a tax lookup with the renewal prices
	$res = $order->do_lookup();

	// Add errors as notes if necessary
	if ($res != true) {
		$original_order->add_order_note( sprintf( __( 'Tax lookup for renewal order %s failed. Reason: '. $res, 'woocommerce-subscriptions' ), $order_id) );
	} else {

		// Simulate post-checkout conditions
		$order->do_post_checkout($order_id);

		// Mark order as captured
		$order->complete();

		// Add success note
		$original_order->add_order_note( sprintf( __( 'TaxCloud was successfully notified of renewal order %s.', 'woocommerce-subscriptions' ), $order_id) );

	}

}

// Hook into WordPress/WooCommerce
//add_action( 'woocommerce_checkout_update_order_meta', 'fix_recurring_taxes', 15, 1 );
//add_filter( 'woocommerce_get_order_item_totals', 'fix_tax_key', 5, 1 );
add_action( 'woocommerce_renewal_order_payment_complete', 'handle_renewal_order', 12, 4 );

endif;