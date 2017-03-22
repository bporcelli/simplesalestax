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

		if ( ! class_exists( 'SoapClient' ) )
			$missing[] = 'PHP SOAP Extension';

		if ( version_compare( self::woocommerce_version(), '2.2', '<' ) )
			$missing[] = 'WooCommerce 2.2+';

		return $missing;
	}

	/**
	 * Is the plugin with the given slug active? (TODO: Remove if no longer needed.)
	 *
	 * @since 5.0
	 *
	 * @param  string $slug Plugin slug.
	 * @return bool
	public static function is_plugin_active( $slug ) {
		$active_plugins = (array) get_option( 'active_plugins', array() );

		if ( is_multisite() )
			$active_plugins = array_merge( $active_plugins, get_site_option( 'active_sitewide_plugins', array() ) );

		return in_array( $slug, $active_plugins ) || array_key_exists( $slug, $active_plugins );
	}*/

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

	/**
	 * Return the version number for WooCommerce. If WooCommerce is not
	 * installed, return '0.0.0';
	 *
	 * @since 5.0
	 *
	 * @return string
	 */
	public static function woocommerce_version() {
		// Favor the WC_VERSION constant in 2.1+
		if ( defined( 'WC_VERSION' ) ) {
			return WC_VERSION;
		} else if ( defined( 'WOOCOMMERCE_VERSION' ) ) {
			return WOOCOMMERCE_VERSION;
		} else {
			return '0.0.0';
		}
	}
}