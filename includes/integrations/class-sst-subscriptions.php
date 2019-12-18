<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Subscriptions.
 *
 * Enables WooCommerce Subscriptions support.
 *
 * @author  Simple Sales Tax
 * @package SST
 * @since   5.0
 */
class SST_Subscriptions {

	/**
	 * Constructor.
	 *
	 * @since 5.0
	 */
	public function __construct() {
		add_filter( 'wootax_product_price', [ $this, 'change_product_price' ], 100, 2 );
		add_filter( 'wootax_shipping_price', [ $this, 'change_shipping_price' ], 10, 2 );
		add_filter( 'wootax_add_fees', [ $this, 'exclude_fees' ] );
		add_filter( 'wootax_cart_packages_before_split', [ $this, 'add_package_for_no_ship_subs' ], 10, 2 );
		add_filter( 'wootax_product_tic', [ $this, 'set_signup_fee_tic' ], 10, 3 );
		add_filter( 'woocommerce_calculated_total', [ $this, 'save_shipping_taxes' ], 1200, 2 );
		add_action( 'woocommerce_cart_updated', [ $this, 'restore_shipping_taxes' ] );
		add_action( 'woocommerce_checkout_update_order_meta', [ $this, 'destroy_session' ] );
		add_filter( 'wcs_renewal_order_created', [ $this, 'recalc_taxes_for_renewal' ], 1, 2 );
		add_filter( 'wootax_save_packages_for_capture', [ $this, 'should_save_packages_for_capture' ] );
	}

	/**
	 * Set product prices based on the current calculation type. If the tax for
	 * an order is being recalculated, leave prices unchanged.
	 *
	 * Needed because Subscriptions removes its price filter before we calculate
	 * the tax due.
	 *
	 * @param float      $price
	 * @param WC_Product $product
	 *
	 * @return float
	 * @since 5.0
	 */
	public function change_product_price( $price, $product ) {
		if ( ! did_action( 'woocommerce_calculate_totals' ) ) {
			return $price; /* Recalculating taxes */
		} else {
			return WC_Subscriptions_Cart::set_subscription_prices_for_calculation( $price, $product );
		}
	}

	/**
	 * If we are calculating tax for an initial subscription order, we must set the taxable
	 * shipping price to zero if WC_Subscriptions_Cart::charge_shipping_up_front() is true.
	 * This function hooks wootax_shipping_price to take care of this.
	 *
	 * @param float  $price         Taxable price.
	 * @param string $shipping_rate Product ID.
	 *
	 * @return float
	 * @since 5.0
	 */
	public function change_shipping_price( $price, $shipping_rate ) {
		if ( ! did_action( 'woocommerce_calculate_totals' ) ) {
			return $price; /* In backend; no concept of charging up-front */
		} else {
			$calc_type = WC_Subscriptions_Cart::get_calculation_type();

			if ( $calc_type != 'recurring_total' && ! WC_Subscriptions_Cart::charge_shipping_up_front() ) {
				return 0;
			}
		}

		return $price;
	}

	/**
	 * Exclude fees from tax lookups when subscription there is no subscription
	 * sign up fee and all subscriptions qualify for a free trial.
	 *
	 * @param bool $add_fees Should fees be included in the lookup?
	 *
	 * @return bool
	 * @since 5.0
	 */
	public function exclude_fees( $add_fees ) {
		if ( ! did_action( 'woocommerce_calculate_totals' ) ) {
			return $add_fees; /* In backend; TODO: better way to handle? */
		} else {
			$calc_type           = WC_Subscriptions_Cart::get_calculation_type();
			$sign_up_fee         = WC_Subscriptions_Cart::get_cart_subscription_sign_up_fee();
			$all_have_free_trial = WC_Subscriptions_Cart::all_cart_items_have_free_trial();

			if ( $calc_type != 'recurring_total' && 0 == $sign_up_fee && $all_have_free_trial ) {
				return false;
			}
		}

		return $add_fees;
	}

	/**
	 * This function is executed when a new renewal order is created. It
	 * recalculates the sales tax for the order to account for the fact
	 * that the customer address (and tax rates) may have changed.
	 *
	 * @param WC_Order        $renewal_order
	 * @param WC_Subscription $subscription
	 *
	 * @return WC_Order
	 * @since 5.0
	 */
	public function recalc_taxes_for_renewal( $renewal_order, $subscription ) {
		$order = new SST_Order( $renewal_order );

		/* Reset packages to force recalc */
		$order->set_packages( [] );

		/* Reset status to ensure Lookup isn't blocked */
		$order->update_meta( 'status', 'pending' );

		/* Recalc taxes */
		try {
			$order->calculate_taxes();
			$order->calculate_totals( false );
		} catch ( Exception $ex ) {
			$order->add_order_note(
				sprintf(
					__( 'Failed to calculate sales tax for renewal order %d: %s.', 'simplesalestax' ),
					$order->get_id(),
					$ex->getMessage()
				)
			);
		}

		return $renewal_order;
	}

	/**
	 * Subscriptions will recalculate the shipping totals for the main cart
	 * after calculating the recurring totals. When this is done, the shipping
	 * taxes for the main cart will be reset. This function saves the
	 * computed shipping taxes before the recurring totals are calculated so
	 * they can be restored later.
	 *
	 * IMPORTANT: This hook needs to run after SST_Checkout::calculate_tax_totals()
	 *
	 * @param double  $total Current cart total.
	 * @param WC_Cart $cart  Cart object.
	 *
	 * @return double
	 * @since 5.0
	 */
	public function save_shipping_taxes( $total, $cart ) {
		$calc_type = WC_Subscriptions_Cart::get_calculation_type();

		if ( in_array( $calc_type, [ 'none', 'recurring_total' ] ) ) {
			$saved_taxes = WC()->session->get( 'sst_saved_shipping_taxes', [] );

			if ( sst_woocommerce_gte_32() ) {
				$saved_taxes[ $calc_type ] = $cart->get_shipping_taxes();
			} else {
				$saved_taxes[ $calc_type ] = $cart->shipping_taxes;
			}

			WC()->session->set( 'sst_saved_shipping_taxes', $saved_taxes );
		}

		return $total;
	}

	/**
	 * Restore shipping taxes after recurring order totals are updated.
	 *
	 * @since 5.0
	 */
	public function restore_shipping_taxes() {
		$calc_type = WC_Subscriptions_Cart::get_calculation_type();

		if ( in_array( $calc_type, [ 'none', 'recurring_total' ] ) ) {
			$saved_taxes = WC()->session->get( 'sst_saved_shipping_taxes', [] );

			if ( array_key_exists( $calc_type, $saved_taxes ) ) {
				if ( sst_woocommerce_gte_32() ) {
					WC()->cart->set_shipping_taxes( $saved_taxes[ $calc_type ] );
				} else {
					WC()->cart->shipping_taxes = $saved_taxes[ $calc_type ];
				}
			}
		}
	}

	/**
	 * Delete session data post-checkout.
	 *
	 * @since 5.0
	 */
	public function destroy_session() {
		WC()->session->set( 'sst_saved_shipping_taxes', [] );
	}

	/**
	 * When the cart calculation type is 'none,' Subscriptions removes all
	 * subscriptions with free trials from the cart packages. Similarly, when
	 * the calculation type is 'recurring_total,' it removes all subscriptions
	 * that have one time shipping. As a consequence, the fees for these subs
	 * (if any) will not be included in lookups. To avoid this, we hook
	 * wootax_cart_packages_before_split and add a special package containing
	 * all removed subs. Since all subs in this package do not ship, we use the
	 * customer billing address as the destination address.
	 *
	 * @param array   $packages
	 * @param WC_Cart $cart
	 *
	 * @return array
	 * @since 5.0
	 */
	public function add_package_for_no_ship_subs( $packages, $cart ) {
		$contents  = [];
		$calc_type = WC_Subscriptions_Cart::get_calculation_type();

		if ( 'none' == $calc_type && WC_Subscriptions_Cart::cart_contains_free_trial() ) {
			foreach ( $cart->get_cart() as $key => $cart_item ) {
				if ( WC_Subscriptions_Product::get_trial_length( $cart_item['data'] ) > 0 ) {
					$contents[ $key ] = $cart_item;
				}
			}
		} else if ( 'recurring_total' == $calc_type ) {
			foreach ( $cart->get_cart() as $key => $cart_item ) {
				if ( WC_Subscriptions_Product::needs_one_time_shipping( $cart_item['data'] ) ) {
					$contents[ $key ] = $cart_item;
				}
			}
		}

		if ( ! empty( $contents ) ) {   /* Add package */
			$packages[] = sst_create_package(
				[
					'contents'    => $contents,
					'user'        => [
						'ID' => get_current_user_id(),
					],
					'destination' => [
						'address'   => WC()->customer->get_billing_address(),
						'address_2' => WC()->customer->get_billing_address_2(),
						'city'      => WC()->customer->get_billing_city(),
						'state'     => WC()->customer->get_billing_state(),
						'postcode'  => WC()->customer->get_billing_postcode(),
					],
				]
			);
		}

		return $packages;
	}

	/**
	 * If we are calculating tax for the initial order (i.e. the calculation
	 * type is 'none'), set the TIC for all subscription products with a free
	 * trial period and sign up fee to "Membership fees" (91070). If this isn't
	 * done, sign-up fees will be taxed as if they are subscriptions.
	 *
	 * @param int $tic
	 * @param int $product_id
	 * @param int $variation_id (default: 0)
	 *
	 * @return int
	 * @since 5.0
	 */
	public function set_signup_fee_tic( $tic, $product_id, $variation_id = 0 ) {
		$initial_order = 'none' == WC_Subscriptions_Cart::get_calculation_type();

		/* On backend, can't use WC_Subscriptions_Cart to determine whether this
		 * is the initial order. */
		if ( isset( $_POST['order_id'] ) ) {
			$order_id      = absint( $_POST['order_id'] );
			$initial_order = 'shop_order' == get_post_type( $order_id );
		}

		$has_free_trial = WC_Subscriptions_Product::get_trial_length( $product_id ) > 0;
		$has_fee        = WC_Subscriptions_Product::get_sign_up_fee( $product_id );

		if ( $initial_order && $has_free_trial && $has_fee ) {
			return apply_filters( 'wootax_sign_up_fee_tic', 91070 ); // Default is "Membership fees" (91070)
		}

		return $tic;
	}

	/**
	 * Filters `wootax_save_packages_for_capture` to prevent Simple Sales Tax from sending recurring totals to TaxCloud.
	 *
	 * @return bool
	 */
	public function should_save_packages_for_capture() {
		return 'none' === WC_Subscriptions_Cart::get_calculation_type();
	}

}

new SST_Subscriptions();
