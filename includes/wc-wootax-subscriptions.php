<?php

/**
 * Core functions to enable WooCommerce Subscriptions support
 *
 * @author Brett Porcelli
 * @since 1.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) 
	exit;

/**
 * Set cart_tax/shipping_tax meta for WooTax tax item
 *
 * @param (int) $order_id - ID of order being created
 * @return void
 * @since 1.0
 */
function wt_store_tax_item_totals( $order_id, $item_id = null, $tax_rate_id = null ) {
	if ( !empty( $item_id ) ) {
		$tax_item_id = $item_id;
	} else {
		$tax_item_id = WT_Orders::get_meta( $order_id, 'tax_item_id' );
	}

	$tax_total          = WT_Orders::get_meta( $order_id, 'tax_total' );
	$shipping_tax_total = WT_Orders::get_meta( $order_id, 'shipping_tax_total' );

	wc_add_order_item_meta( $tax_item_id, 'cart_tax', wc_format_decimal( $tax_total ) );
	wc_add_order_item_meta( $tax_item_id, 'shipping_tax', wc_format_decimal( $shipping_tax_total ) );
}

if ( version_compare( WOOCOMMERCE_VERSION, '2.2', '<' ) ) {
	add_action( 'woocommerce_checkout_update_order_meta', 'wt_store_tax_item_totals', 10, 1 );
} else {
	add_action( 'woocommerce_order_add_tax', 'wt_store_tax_item_totals', 12, 3 );
}

/**
 * Determine whether or not an order requires a manual renewal
 *
 * @param $order WC_WooTax_Order object
 * @return bool
 * @since 1.0
 */
function wt_is_manual( $order ) {
	if ( class_exists( 'WC_Subscription' ) && $order instanceof WC_Subscription ) {
		return $order->is_manual();
	} else {
		return WC_Subscriptions_Order::requires_manual_renewal( $order );
	}
}

/**
 * Determine whether or not the payment gateway for a subscription order supports changes to recurring totals
 *
 * @param $order WC_WooTax_Order object
 * @return bool
 * @since 1.0
 */
function wt_can_change( $order ) {
	if ( class_exists( 'WC_Subscription' ) && $order instanceof WC_Subscription ) {
		return $order->payment_method_supports( 'subscription_amount_changes' );
	} else {
		$chosen_gateway = WC_Subscriptions_Payment_Gateways::get_payment_gateway( get_post_meta( $order->id, '_recurring_payment_method', true ) );
				
		if ( false === $chosen_gateway ) {
			$can_change = true;
		} else {
			$can_change = $chosen_gateway->supports( 'subscription_amount_changes' );
		}

		return $can_change;
	}
}

/**
 * Return true if WooCommerce Subscriptions 2.0 or greater is installed
 */
function wt_is_subs_2_0() {
	return version_compare( WC_Subscriptions::$version, '2.0', '>=' );
}