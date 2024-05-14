<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Composite Products integration for Simple Sales Tax.
 *
 * @author Brett Porcelli <bporcelli@taxcloud.com>
 */
class SST_WC_Deposits {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'remove_tax_total_filters' ) );
		add_filter(
			'sst_line_item_total',
			array( $this, 'filter_cart_item_total' ),
			10,
			2
		);
		add_filter(
			'woocommerce_checkout_order_created',
			array( $this, 'fix_order_tax_totals' ),
		);
		// TODO: Need to make sure tax on invoice is correct
	}

	/**
	 * Disables WC Deposits filters for altering the cart tax totals.
	 *
	 * These filters assume that the tax will be calculated based
	 * on the full product price, but SST will calculate the tax
	 * based on the discounted price/deposit amount.
	 */
	public function remove_tax_total_filters() {
		$cart_manager = WC_Deposits_Cart_Manager::get_instance();

		remove_filter(
			'woocommerce_cart_tax_totals',
			array( $cart_manager, 'cart_totals_order_taxes' )
		);
	}

	/**
	 * Fix line totals for tax calculations.
	 *
	 * @param float $total Line total
	 * @param array $item  Cart item
	 *
	 * @return float
	 */
	public function filter_cart_item_total( $total, $item ) {
		if (
			! empty( $item['is_deposit'] ) &&
			isset( $item['deposit_amount'] )
		) {
			return round(
				$item['deposit_amount'] * $item['quantity'],
				wc_get_price_decimals()
			);
		}

		return $total;
	}

	/**
	 * Revert changes WC Deposits makes to tax totals on
	 * `woocommerce_checkout_create_order_line_item` by
	 * recalculating the order taxes again.
	 *
	 * This is necessary since we calculate tax based on the
	 * discounted item price whereas deposits assumes taxes
	 * will be calculated based on the full item price.
	 *
	 * Due to package caching this should not result in an
	 * additional tax lookup API request.
	 *
	 * @param WC_Order $wc_order Order object
	 */
	public function fix_order_tax_totals( $wc_order ) {
		// TODO: If this results in an addl lookup we need another sol'n. Check.
		$order = new SST_Order( $wc_order );
		$order->calculate_taxes();
	}

}

new SST_WC_Deposits();
