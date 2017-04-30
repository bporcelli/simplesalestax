<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * SST Compatibility.
 *
 * Includes methods to detect missing dependencies. Also defines several
 * functions used to ensure backward compatibility with older versions of 
 * WooCommerce.
 *
 * @author 	Simple Sales Tax
 * @package SST
 * @since 	5.0
 */
class SST_Compatibility {

	/**
	 * Return a list of strings describing missing dependencies.
	 *
	 * @since 5.0
	 *
	 * @return string[]
	 */
	public static function get_missing_dependencies() {
		$missing = array();

		if ( ! defined( 'WC_VERSION' ) || version_compare( WC_VERSION, '2.6', '<' ) )
			$missing[] = 'WooCommerce 2.6+';

		return $missing;
	}

	/**
	 * Are taxes enabled?
	 *
	 * @since 4.6
	 *
	 * @return bool
	 */
	public static function taxes_enabled() {
		if ( function_exists( 'wc_taxes_enabled' ) ) {
			return wc_taxes_enabled();
		} else {
			return apply_filters( 'wc_tax_enabled', get_option( 'woocommerce_calc_taxes' ) == 'yes' );
		}
	}
}