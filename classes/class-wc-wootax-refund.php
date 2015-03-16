<?php

if ( function_exists( 'wc_create_refund' ) ):

if ( !class_exists( 'WC_Order_Refund' ) ) {
	require_once( plugin_dir_path( '../../woocommerce/woocommerce.php' ) .'includes/class-wc-order-refund.php' );
}

/**
 * WC_WooTax_Refund
 * Wraps around the WC_Order_Refund class when a partial refund is made
 *
 * @package WooTax
 * @since 4.2
 */
class WC_WooTax_Refund extends WC_Order_Refund {
	/**
	 * Call parent constructor when this object is created
	 * @param int|WC_Order_Refund $refund
	 */
	public function __construct( $refund ) {
		parent::__construct( $refund );
	}

	/**
	 * Calls the parent calculate_totals method and triggers the wootax_refund_initiated hook 
	 */
	public function calculate_totals( $and_taxes = false ) {
		parent::calculate_totals( $and_taxes );

		do_action( 'wootax_refund_initiated', $this );
	}
}

/**
 * Returns the name of our custom wrapper for the WC_Order_Refund class
 * Added for WooCommerce 2.2 support
 *
 * @since 4.0
 * @param $classname the classname WooCommerce wants to use for the order object being generated
 * @return original class name or WC_WooTax_Refund
 */
function get_refund_classname( $classname ) {

	if ( $classname == 'WC_Order_Refund' ) {
		return 'WC_WooTax_Refund';
	}

	return $classname;

}

/**
 * Process partial refunds created on the edit order screen
 * 
 * @since 4.0
 * @param $refund   WC_Order_Refund   refund object that was just created
 * @param $cron     boolean           is this method being called from a WooTax cronjob? Default: false.
 */
function process_refund( $refund, $cron = false ) {

	global $WC_WooTax_Order;

	$refund_items = array();

	// Instantiate order object (using original order id, not refund id)
	$order = $WC_WooTax_Order;
	$order->load_order( $refund->post->post_parent );

	// Holds the ID of the first found location
	$first_found = empty( $order->first_found ) ? false : $order->first_found;

	// Map product IDs to locations
	$location_mapping_array = array();
	$id_array               = array();
	$identifiers            = $order->identifiers;

	foreach ( $order->order->get_items() as $item_id => $item ) {

		$product_id = !empty( $item['variation_id'] ) ? $item['variation_id'] : $item['product_id'];
		$location_mapping_array[ $product_id ] = $order->order->get_item_meta( $item_id, '_wootax_location_id', true );

		if ( isset( $identifiers[ $product_id ] ) ) {
			$identifier = $identifiers[ $product_id ];
		} else {
			$identifier = $item_id;
		}

		$id_array[ $product_id ] = $identifier;

	}

	foreach ( $order->order->get_fees() as $fee_id => $item ) {

		$fee_key = sanitize_title( $item['name'] );

		if ( isset( $identifiers[ $fee_key ] ) ) {
			$identifier = $fee_key;
		} else {
			$identifier = $fee_id;
		}
		
		$id_array[ $fee_key ] = $identifier;

	}

	$id_array[ WOOTAX_SHIPPING_ITEM ] = isset( $identifiers[ WOOTAX_SHIPPING_ITEM ] ) ? $identifiers[ WOOTAX_SHIPPING_ITEM ] : WOOTAX_SHIPPING_ITEM;

	if ( version_compare( WOOCOMMERCE_VERSION, '2.2', '>=' ) && !isset( $identifiers[ WOOTAX_SHIPPING_ITEM ] ) ) {
		
		foreach ( $order->order->get_shipping_methods() as $method_id => $method ) {

			$id_array[ WOOTAX_SHIPPING_ITEM ] = $method_id;

		}

	}

	// Add items
	foreach ( $refund->get_items() as $item_id => $item ) {

		$product = $refund->get_product_from_item( $item );

		if ( !$product->is_taxable() || $item['qty'] == 0 ) {
			continue;
		}

		$product_id = !empty( $item['variation_id'] ) ? $item['variation_id'] : $item['product_id'];
		$tic        = get_post_meta( $item['product_id'], 'wootax_tic', true );

		// Get location key for item
		$location_key = $location_mapping_array[ $product_id ];

		// Get real item ID
		// When a Lookup has not been sent from the backend yet, this will be the cart item key 
		// that was originally sent to TaxCloud
		$product_id = $id_array[ $product_id ];

		// Set first found if needed
		if ( $first_found === false ) {
			$first_found = $location_key;
		}

		if ( !isset( $refund_items[ $location_key ] ) ) {
			$refund_items[ $location_key ] = array();
		}

		$new_item = array(
			'Index'  => '',
			'ItemID' => $product_id,
			'TIC'    => '',
			'Qty'    => $item['qty'],
			'Price'  => $item['line_total'] * -1 / $item['qty'],
		);

		if ( $tic !== false && !empty( $tic ) ) {
			$new_item['TIC'] = $tic;
		} 

		$refund_items[ $location_key ][] = $new_item;

	}

	// Add fees 
	foreach ( $refund->get_fees() as $fee_id => $fee ) {

		if ( $fee['line_total'] == 0 )
			continue; 

		// Get item ID
		$key     = sanitize_title( $fee['name'] );
		$real_id = $id_array[ $key ];

		$refund_items[ $first_found ][] = array(
			'Index'  => '', 
			'ItemID' => $real_id, 
			'TIC'    => WOOTAX_FEE_TIC,
			'Qty'    => 1, 
			'Price'  => $fee['line_total'] * -1,
		);

	}

	// Add shipping costs
	$shipping_cost = $refund->get_total_shipping();

	if ( $shipping_cost != 0 ) {

		$item_id = $id_array[ WOOTAX_SHIPPING_ITEM ];

		$refund_items[ $first_found ][] = array(
			'Index'  => '', 
			'ItemID' => $item_id, 
			'TIC'    => WOOTAX_SHIPPING_TIC, 
			'Qty'    => 1, 
			'Price'  => $shipping_cost * -1,
		);

	}

	// Process refund
	$res = $order->refund_items( $refund_items );

	if ( $res !== true && !$cron ) {
		
		// Delete refund
		wp_delete_post( $refund->post->ID );

		// Throw exception so refund is halted
		throw new Exception('Refund failed: '. $res);
		
	} else if ( $res !== true && $cron ) {
		return $res;
	} else if ( $cron ) {
		return true;
	}

}

// Hook WordPress/WooCommerce
add_action( 'wootax_refund_initiated', 'process_refund', 1, 1 );
add_filter( 'woocommerce_order_class', 'get_refund_classname', 1, 1 );

endif;