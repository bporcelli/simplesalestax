<?php

if ( !defined( 'ABSPATH' ) ) {
	exit; // Prevent direct access
}

// Do not execute script if partial refunds are not supported (WC < 2.2)
if ( version_compare( WOOCOMMERCE_VERSION, '2.2', '>=' ) ) :

// We will need access to the WC_Order_Refund class!
if ( !class_exists( 'WC_Order_Refund' ) ) {
	require_once( plugin_dir_path( '../../woocommerce/woocommerce.php' ) .'includes/class-wc-order-refund.php' );
}

/**
 * WC_WooTax_Refund
 * Enables support for partial refunds
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
	 * Calls the parent calculate_totals method and tells WooTax to process partial refund
	 *
	 * @since 4.2
	 */
	public function calculate_totals( $and_taxes = false ) {
		parent::calculate_totals( $and_taxes );
		self::process_refund( $this );
	}

	/**
	 * Process partial refunds; prepare data and send Returned request to TaxCloud
	 * 
	 * @since 4.4
	 * @param (WC_WooTax_Refund) $refund refund order object
	 * @param (bool) $cron is this method being called from a WooTax cronjob?
	 */
	public static function process_refund( $refund, $cron = false ) {
		$refund_items = array();

		// Get order object (use original order ID, not refund order ID)
		$order_id = $refund->post->post_parent;
		$order    = WT_Orders::get_order( $order_id );

		// Holds the key of the first found origin address for the order
		$first_found = WT_Orders::get_meta( $order_id, 'first_found' );
		$first_found = empty( $first_found ) ? false : $first_found;

		// Create array mapping product IDs to locations
		// This needs to be done since the refund order and original order will not have same item IDs
		$mapping_array = array();
		$id_array      = array();
		$identifiers   = WT_Orders::get_meta( $order_id, 'identifiers' );

		foreach ( $order->order->get_items() as $item_id => $item ) {
			$product_id = !empty( $item['variation_id'] ) ? $item['variation_id'] : $item['product_id'];
			
			$mapping_array[ $product_id ] = $order->get_item_meta( $item_id, '_wootax_location_id' );

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

		$id_array[ WT_SHIPPING_ITEM ] = isset( $identifiers[ WT_SHIPPING_ITEM ] ) ? $identifiers[ WT_SHIPPING_ITEM ] : WT_SHIPPING_ITEM;

		if ( version_compare( WOOCOMMERCE_VERSION, '2.2', '>=' ) && !isset( $identifiers[ WT_SHIPPING_ITEM ] ) ) {			
			foreach ( $order->order->get_shipping_methods() as $method_id => $method ) {
				$id_array[ WT_SHIPPING_ITEM ] = $method_id;
			}
		}

		// Add items
		foreach ( $refund->get_items() as $item_id => $item ) {
			$product = $refund->get_product_from_item( $item );

			if ( $item['qty'] == 0 ) {
				continue;
			}

			$product_id = !empty( $item['variation_id'] ) ? $item['variation_id'] : $item['product_id'];
			$tic        = get_post_meta( $item['product_id'], 'wootax_tic', true );

			// Get location key for item
			$location_key = $mapping_array[ $product_id ];

			// Get real item ID
			// When a Lookup has not been sent from the backend yet, this will be the item key sent during checkout
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
			if ( $fee['line_total'] == 0 ) {
				continue; 
			}

			// Get item ID
			$key     = sanitize_title( $fee['name'] );
			$real_id = $id_array[ $key ];

			$refund_items[ $first_found ][] = array(
				'Index'  => '', 
				'ItemID' => $real_id, 
				'TIC'    => WT_FEE_TIC,
				'Qty'    => 1, 
				'Price'  => $fee['line_total'] * -1,
			);
		}

		// Add shipping costs
		// Shipping costs are always associated with first found location
		$shipping_cost = $refund->get_total_shipping();

		if ( $shipping_cost != 0 ) {
			$item_id = $id_array[ WT_SHIPPING_ITEM ];

			$refund_items[ $first_found ][] = array(
				'Index'  => '', 
				'ItemID' => $item_id, 
				'TIC'    => WT_SHIPPING_TIC, 
				'Qty'    => 1, 
				'Price'  => $shipping_cost * -1,
			);
		}

		// Process refund
		$res = WT_Orders::refund_order( $order_id, $cron, $refund_items );

		if ( $res !== true && !$cron ) {			
			// Delete refund
			wp_delete_post( $refund->post->ID );

			// Throw exception so refund is halted
			throw new Exception( 'Refund failed: '. $res );			
		} else if ( $res !== true && $cron ) {
			return $res;
		} else if ( $cron ) {
			return true;
		}
	}

	/**
	 * Returns the name of our custom wrapper for the WC_Order_Refund class
	 *
	 * @since 4.4
	 * @param (string) $classname the classname WooCommerce wants to use for the order object being generated
	 * @return (string) original class name or WC_WooTax_Refund
	 */
	public static function get_refund_classname( $classname ) {
		if ( $classname == 'WC_Order_Refund' ) {
			return 'WC_WooTax_Refund';
		}

		return $classname;
	}
}

// Make WooCommerce aware of our custom refund class
add_filter( 'woocommerce_order_class', array( 'WC_WooTax_Refund', 'get_refund_classname' ), 1, 1 );

endif;