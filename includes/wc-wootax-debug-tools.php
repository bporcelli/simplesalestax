<?php

/**
 * Contains custom WooCommerce debug tools for WooTax
 *
 * @package WooTax
 * @since 4.2
 */

/**
 * Add "Check WooTax Orders" debug tool
 *
 * @since 4.4
 * @param $tools  array   array of debug tools
 * @return modified array of debug tools
 */ 
function wootax_debug_tool_check_orders( $tools ) {

	$tools['wootax_check_orders'] = array(
		'name'		=> __( 'Check WooTax Orders',''),
		'button'	=> __( 'Check orders','woocommerce-wootax' ),
		'desc'		=> __( 'This tool checks your orders to make sure they are synced with TaxCloud.', '' ),
		'callback'  => 'wootax_check_orders',
	);

	return $tools;

}

/**
 * Check WooTax Orders
 *
 * Checks to make sure that all orders are updated in TaxCloud
 * - Orders that are marked as refunded should have the TaxCloud status "Refunded"
 * - Orders that are completed should have the TaxCloud status "Completed"
 *
 * If orders are found that are not updated in TaxCloud, attempts to fix them
 *
 * @since 4.4
 * @return void
 */
function wootax_check_orders() {

	$query = new WP_Query( array(
		'post_type'   => 'shop_order',
		'post_status' => 'wc-completed',
		'meta_query'  => array(
			array( 
				'key'     => '_wootax_captured',
				'value'   => false,
				'compare' => '=',
			),
		),
	) );

	if ( !$query->have_posts() ) {
		echo '<div class="updated"><p>All of your orders are synced with TaxCloud.</p></div>';
	} else {
		echo '<div class="updated"><p>The orders with the following IDs need to be updated: ';
		for ( $i = 0; $i < count( $query->posts ); $i++ ) {
			echo $query->posts[$i]->ID . ( $i == count( $query->posts ) - 1 ? '' : ', ' );
		}
		echo '</p></div>';
	}
	
}

// Hook into WordPress/WooCommerce
add_filter( 'woocommerce_debug_tools', 'wootax_debug_tool_check_orders' );