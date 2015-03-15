<?php

/**
 * WooTax cronjobs
 *
 * @since 4.4
 */

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

add_action( 'wootax_check_orders', 'wootax_check_orders' );

/**
 * Update recurring tax for subscriptions
 * Not executed if Subscriptions plugin is not active
 *
 * @since 4.4
 * @return void
 */
function wootax_update_recurring_tax() {

}

add_action( 'wootax_update_recurring_tax', 'wootax_update_recurring_tax' );