<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Customer.
 *
 * Provides a backward compatible interface to WC_Customer.
 *
 * @author 	Simple Sales Tax
 * @package SST
 * @since 	5.0
 */
class SST_Customer {

	/**
	 * Get billing_address_1.
	 *
	 * @param  string $context
	 * @return string
	 */
	public static function get_billing_address( $context = 'view' ) {
		if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
			return WC()->customer->get_address();
		}
		return WC()->customer->get_billing_address();
	}

	/**
	 * Get billing_address_2.
	 *
	 * @param  string $context
	 * @return string $value
	 */
	public static function get_billing_address_2( $context = 'view' ) {
		if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
			return WC()->customer->get_address_2();
		}
		return WC()->customer->get_billing_address_2();
	}

	/**
	 * Get billing_city.
	 *
	 * @param  string $context
	 * @return string $value
	 */
	public static function get_billing_city( $context = 'view' ) {
		if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
			return WC()->customer->get_city();
		}
		return WC()->customer->get_billing_city();
	}

	/**
	 * Get billing_state.
	 *
	 * @param  string $context
	 * @return string
	 */
	public static function get_billing_state( $context = 'view' ) {
		if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
			return WC()->customer->get_state();
		}
		return WC()->customer->get_billing_state();
	}

	/**
	 * Get billing_postcode.
	 *
	 * @param  string $context
	 * @return string
	 */
	public static function get_billing_postcode( $context = 'view' ) {
		if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
			return WC()->customer->get_postcode();
		}
		return WC()->customer->get_billing_postcode();
	}

	/**
	 * Get billing_country.
	 *
	 * @param  string $context
	 * @return string
	 */
	public static function get_billing_country( $context = 'view' ) {
		if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
			return WC()->customer->get_country();
		}
		return WC()->customer->get_billing_country();
	}

	/**
	 * Gets the address from the current session.
	 *
	 * @return string
	 */
	public static function get_shipping_address() {
		return WC()->customer->get_shipping_address();
	}

	/**
	 * Gets the address_2 from the current session.
	 *
	 * @return string
	 */
	public static function get_shipping_address_2() {
		return WC()->customer->get_shipping_address_2();
	}

	/**
	 * Gets the city from the current session.
	 *
	 * @return string
	 */
	public static function get_shipping_city() {
		return WC()->customer->get_shipping_city();
	}

	/**
	 * Gets the state from the current session.
	 *
	 * @return string
	 */
	public static function get_shipping_state() {
		return WC()->customer->get_shipping_state();
	}

	/**
	 * Gets the postcode from the current session.
	 *
	 * @return string
	 */
	public static function get_shipping_postcode() {
		return WC()->customer->get_shipping_postcode();
	}

	/**
	 * Get shipping_country.
	 *
	 * @param  string $context
	 * @return string
	 */
	public static function get_shipping_country( $context = 'view' ) {
		return WC()->customer->get_shipping_country();
	}

	/**
	 * Get the taxable address.
	 *
	 * @since 5.0
	 *
	 * @return array
	 */
	public static function get_taxable_address() {
		$tax_based_on = get_option( 'woocommerce_tax_based_on' );
		$billing      = 'billing' == $tax_based_on;
		$raw_addr     = WC()->customer->get_taxable_address();

		// Add address_1/address_2
		$raw_addr[] = $billing ? self::get_billing_address() : self::get_shipping_address();
		$raw_addr[] = $billing ? self::get_billing_address_2() : self::get_shipping_address_2();

		return $raw_addr;
	}
}