<?php

/**
 * Functions for handling subscriptions on frontend.
 *
 * @author 	Simple Sales Tax
 * @package SST
 * @since 	1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Ensure shipping tax totals are preserved when WooCommerce Subscriptions
 * calculates recurring order totals.
 *
 * @since 1.0
 */
function wt_handle_subscription_checkout() {
	if ( WT_SUBS_ACTIVE && WC_Subscriptions_Cart::cart_contains_subscription() ) {
		add_filter( 'woocommerce_calculated_total', 'wt_store_shipping_taxes', 10, 2 );
		add_action( 'woocommerce_cart_updated', 'wt_restore_shipping_taxes' );
	}
}

add_action( 'wt_start_lookup_checkout', 'wt_handle_subscription_checkout', 10 );

/**
 * Used to set is_renewal flag when recurring order totals are being calculated.
 *
 * @since 1.0
 *
 * @param  bool $is_renewal
 * @return bool
 */
function wt_is_renewal( $is_renewal ) {
	if ( WT_SUBS_ACTIVE && WC_Subscriptions_Cart::get_calculation_type() == 'recurring_total' ) {
		return true;
	} else {
		return false;
	} 
}

add_filter( 'wt_cart_is_renewal', 'wt_is_renewal', 10, 1 );

/**
 * Used to set is_subscription flag when recurring order totals are being 
 * calculated
 *
 * @since 1.0
 *
 * @param  bool $is_subscription
 * @return bool
 */
function wt_is_subscription( $is_subscription ) {
	if ( WT_SUBS_ACTIVE && WC_Subscriptions_Cart::cart_contains_subscription() ) {
		return true;
	} else {
		return false;
	}
}

add_filter( 'wt_cart_is_subscription', 'wt_is_subscription', 10, 1 );

/**
 * Save calculated shipping taxes to session before recurring tax totals are updated.
 * If this isn't done, the totals will be reset by Subscriptions.
 *
 * @since 1.0
 *
 * @param  double $total Current cart total.
 * @param  WC_Cart $cart Cart object.
 * @return double
 */
function wt_store_shipping_taxes( $total, $cart ) {
	$calc_type = WC_Subscriptions_Cart::get_calculation_type();

	if ( in_array( $calc_type, array( 'none', 'recurring_total' ) ) ) {
		$shipping_taxes_back = WC()->session->get( 'shipping_taxes_back' );

		if ( ! is_array( $shipping_taxes_back ) ) {
			$shipping_taxes_back = array();
		}

		$shipping_taxes_back[ $calc_type ] = $cart->shipping_taxes;		
		WC()->session->set( 'shipping_taxes_back', $shipping_taxes_back );
	}

	return $total;
}

/**
 * Restore calculated shipping tax total after recurring order totals are updated.
 *	
 * @since 1.0
 */
function wt_restore_shipping_taxes() {
	$calc_type = WC_Subscriptions_Cart::get_calculation_type();

	if ( in_array( $calc_type, array( 'none', 'recurring_total' ) ) ) {
		$shipping_taxes_back = WC()->session->get( 'shipping_taxes_back' );

		if ( is_array( $shipping_taxes_back ) && array_key_exists( $calc_type, $shipping_taxes_back ) ) {
			WC()->cart->shipping_taxes = $shipping_taxes_back[ $calc_type ];
		}
	}
}

/**
 * Remove session data after checkout.
 *
 * @since 1.0
 */
function wt_subs_remove_session_data() {
	WC()->session->set( 'shipping_taxes_back', array() );
}

add_action( 'wt_delete_session_data', 'wt_subs_remove_session_data' );