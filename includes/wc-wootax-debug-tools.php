<?php

/**
 * Contains custom WooCommerce debug tools for WooTax
 *
 * @package WooTax
 * @since 4.2
 */

/**
 * Delete ALL WooCommerce tax rates
 *
 * Add to your theme functions.php then go to woocommerce -> system status -> tools and there will be a delete all tax rates button http://cld.wthms.co/tXvp
 */ 
function custom_woocommerce_debug_tools( $tools ) {

	$tools['woocommerce_delete_tax_rates'] = array(
		'name'		=> __( 'Delete Tax Rates',''),
		'button'	=> __( 'Delete ALL tax rates from WooCommerce','' ),
		'desc'		=> __( 'This tool will delete all your tax rates allowing you to start fresh.', '' ),
		'callback'  => array($this, 'woocommerce_delete_tax_rates')
	);

	return $tools;

}

/**
 * Delete Tax rates
 */
function woocommerce_delete_tax_rates() {
	
	global $wpdb;
			
	$wpdb->query( "TRUNCATE " . $wpdb->prefix . "woocommerce_tax_rates" );
	$wpdb->query( "TRUNCATE " . $wpdb->prefix . "woocommerce_tax_rate_locations" );
 
	echo '<div class="updated"><p>' . __( 'Tax rates successfully deleted', 'woocommerce' ) . '</p></div>';
	
}

// Hook into WordPress/WooCommerce
add_filter( 'woocommerce_debug_tools', 'custom_woocommerce_debug_tools' );

?>