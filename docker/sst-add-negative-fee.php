<?php
/**
 * Plugin Name: SST Add Negative Fee
 * Description: Adds a negative fee to the cart to faciliate testing of negative fee handling.
 * Version: 0.0.1
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'init',
	function() {
		if ( isset( $_GET['add_negative_fee'] ) ) {
			WC()->session->set(
				'add_negative_fee',
				'yes' === $_GET['add_negative_fee']
			);
		}
	}
);

add_action(
	'woocommerce_add_to_cart',
	function() {
		WC()->session->set( 'add_negative_fee', false );
	}
);

add_action(
	'woocommerce_cart_calculate_fees',
	function ( $cart ) {
		if ( WC()->session->get( 'add_negative_fee' ) ) {
			$cart->add_fee( 'Discount', -0.1 * $cart->get_subtotal(), false );
		}
	},
);
