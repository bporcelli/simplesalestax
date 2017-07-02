<?php

/**
 * Compatibility functions.
 *
 * Functions related to dependency checking.
 *
 * @author 	Simple Sales Tax
 * @package SST
 * @since 	5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Is WooCommerce active?
 *
 * @since 5.0
 *
 * @return bool
 */
function sst_woocommerce_active() {
	if ( function_exists( 'woocommerce_active_check' ) ) {
		return woocommerce_active_check();
	}

	$active_plugins = get_option( 'active_plugins', array() );

	if ( is_multisite() )
		$active_plugins = array_merge( $active_plugins, get_site_option( 'active_sitewide_plugins', array() ) );

	return in_array( 'woocommerce/woocommerce.php', $active_plugins ) || array_key_exists( 'woocommerce/woocommmerce.php', $active_plugins );
}

/**
 * Is WooCommerce Subscriptions active?
 *
 * @since 5.0
 *
 * @return bool
 */
function sst_subs_active() {
	return class_exists( 'WC_Subscriptions' );
}

/**
 * Is WooCommerce Ship To Multiple Addresses active?
 *
 * @since 5.0
 *
 * @return bool
 */
function sst_wcms_active() {
	return class_exists( 'WC_Ship_Multiple' );
}

/**
 * Is the Storefront theme active?
 *
 * @since 5.4
 *
 * @return bool
 */
function sst_storefront_active() {
	$theme = wp_get_theme();

	if ( is_null( $theme ) ) {
		return false;
	}

	if ( ! empty( $theme->template ) ) {
		$theme_name = $theme->template; /* child */
	} else {
		$theme_name = $theme->name;
	}

	return 'storefront' == strtolower( $theme_name );
}