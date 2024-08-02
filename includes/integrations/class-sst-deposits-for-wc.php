<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Deposits for WooCommerce integration for Simple Sales Tax.
 *
 * @author Brett Porcelli <bporcelli@taxcloud.com>
 */
class SST_Deposits_For_WC {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_filter(
			'woocommerce_get_cart_contents',
			array( $this, 'add_tax_to_totals' )
		);

		add_filter(
			'sst_should_calculate_order_tax',
			array( $this, 'should_calculate_order_tax' ),
			10,
			2
		);
	}

	/**
	 * Calculate taxes on amount due in future.
	 */
	protected function calculate_future_taxes() {
		$filter_packages = function( $packages ) {
			return array_map(
				function( $package ) {
					// Remove shipping and fees (collected with deposit)
					$package['shipping']->cost = 0;
					$package['fees']           = array();

					foreach ( $package['contents'] as $key => &$item ) {
						if ( empty( $item['has_deposit'] ) ) {
							// Remove items with no deposit
							unset( $package['contents'][ $key ] );
							continue;
						}

						// Update total to reflect amount due in future
						$item['line_total'] =
							$item['quantity'] * ( $item['full_amount'] - $item['deposit'] );
					}

					return $package;
				},
				$packages
			);
		};

		add_filter( 'wootax_cart_packages', $filter_packages );
		add_filter( 'wootax_save_packages_for_capture', '__return_false' );

		WC()->cart->calculate_totals();

		remove_filter( 'wootax_cart_packages', $filter_packages );
		remove_filter( 'wootax_save_packages_for_capture', '__return_false' );

		return array_reduce(
			WC()->cart->get_cart_contents(),
			function( $taxes, $item ) {
				$taxes[ $item['key'] ] = $item['line_tax'];
				return $taxes;
			},
			[]
		);
	}

	/**
	 * Adds tax to the `full_amount` and product price for each item with
	 * a deposit to match Deposits for WooCommerce's expectations.
	 *
	 * @param array $cart_contents WC cart contents
	 *
	 * @return float
	 */
	public function add_tax_to_totals( $cart_contents ) {
		// Don't filter unless displaying totals in cart or order review
		if (
			! doing_action( 'woocommerce_cart_totals_before_order_total' ) &&
			! doing_action( 'woocommerce_review_order_before_order_total' )
		) {
			return $cart_contents;
		}

		// Remove filter to avoid infinite recursion
		remove_filter(
			'woocommerce_get_cart_contents',
			array( $this, 'add_tax_to_totals' )
		);

		// Calculate tax due in future and update totals
		$future_taxes = $this->calculate_future_taxes();

		foreach ( $cart_contents as &$item ) {
			if ( empty( $item['has_deposit'] ) ) {
				continue;
			}

			$future_tax = $future_taxes[ $item['key'] ] ?? 0;
			$unit_tax   = $future_tax / $item['quantity'];

			$item['full_amount'] = $item['full_amount'] + $unit_tax;
		}

		// Restore tax totals for amount due today
		WC()->cart->calculate_totals();

		// Add removed filter back
		add_filter(
			'woocommerce_get_cart_contents',
			array( $this, 'add_tax_to_totals' )
		);

		// TODO: Fix remaining amount order/invoice support (calculate after order created/before invoice sent, ideally)
		// TODO: Fix payment plan support
		// TODO: Check checkout based deposits support (same as plans?)
		return $cart_contents;
	}

	/**
	 * Filter sst_should_calculate_order_tax to enable tax calculations
	 * for orders created by Deposits for WooCommerce.
	 *
	 * @param bool     $should_calculate Whether to calculate order taxes
	 * @param WC_Order $order            Order object
	 *
	 * @return bool
	 */
	public function should_calculate_order_tax( $should_calculate, $order ) {
		if ( $order->get_created_via() === 'Tyche Deposits for Woocommerce Plugin' ) {
			return true;
		}

		if ( ! empty( $_GET['invoice_order_remaining_balance'] ) ) {
			return true;
		}

		return $should_calculate;
	}

}

new SST_Deposits_For_WC();
