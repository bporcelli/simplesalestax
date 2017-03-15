<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( ! class_exists( 'WC_Order_Refund' ) ) {
	require_once WC()->plugin_path() . '/includes/class-wc-order-refund.php';
}

/**
 * WooTax Refund.
 *
 * Enables support for partial order refunds.
 *
 * @author 	Simple Sales Tax
 * @package SST
 * @since 	4.2
 */
class WC_WooTax_Refund extends WC_Order_Refund {

	/**
	 * __construct() method. Calls parent constructor.
	 *
	 * @since 4.2
	 *
	 * @param int|WC_Order_Refund $refund
	 */
	public function __construct( $refund ) {
		parent::__construct( $refund );
	}

	/**
	 * Calculate the new order totals, then process the refund.
	 *
	 * @since 4.2
	 */
	public function calculate_totals( $and_taxes = false ) {
		parent::calculate_totals( $and_taxes );
		self::process_refund( $this );
	}

	/**
	 * Process partial refund by sending a Returned request to TaxCloud.
	 * 
	 * @since 4.4
	 *
	 * @param WC_WooTax_Refund $refund
	 * @param bool $cron True if this function is being called from a cronjob.
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
			$product_id = !empty( $item[ 'variation_id' ] ) ? $item[ 'variation_id' ] : $item[ 'product_id' ];
			
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

		if ( version_compare( WT_WOO_VERSION, '2.2', '>=' ) && !isset( $identifiers[ WT_SHIPPING_ITEM ] ) ) {			
			foreach ( $order->order->get_shipping_methods() as $method_id => $method ) {
				$id_array[ WT_SHIPPING_ITEM ] = $method_id;
			}
		}

		// Add items
		foreach ( $refund->get_items() as $item_id => $item ) {
			$product = $refund->get_product_from_item( $item );

			if ( $item[ 'qty' ] == 0 ) {
				continue;
			}

			$product_id = !empty( $item[ 'variation_id' ] ) ? $item[ 'variation_id' ] : $item[ 'product_id' ];
			$tic        = get_post_meta( $item[ 'product_id' ], 'wootax_tic', true );

			// Get location key for item
			$location_key = $mapping_array[ $product_id ];

			// Get real item ID
			// When a Lookup has not been sent from the backend yet, this will be the item key sent during checkout
			$product_id = $id_array[ $product_id ];

			// Set first found if needed
			if ( $first_found === false ) {
				$first_found = $location_key;
			}

			if ( ! isset( $refund_items[ $location_key ] ) ) {
				$refund_items[ $location_key ] = array();
			}

			$qty = $item[ 'qty' ];
			if ( $qty < 0 )
				$qty *= -1;

			$line_total = $item[ 'line_total' ];
			if ( $line_total < 0 )
				$line_total *= -1;

			$unit_price = $line_total / $qty;

			$new_item = array(
				'Index'  => '',
				'ItemID' => $product_id,
				'TIC'    => '',
				'Qty'    => $qty,
				'Price'  => $unit_price,
			);

			if ( $tic !== false && !empty( $tic ) ) {
				$new_item[ 'TIC' ] = $tic;
			} 

			$refund_items[ $location_key ][] = $new_item;
		}

		// Add fees 
		foreach ( $refund->get_fees() as $fee_id => $fee ) {
			if ( $fee[ 'line_total' ] == 0 ) {
				continue; 
			}

			// Get item ID
			$key     = sanitize_title( $fee[ 'name' ] );
			$real_id = $id_array[ $key ];

			$line_total = $fee[ 'line_total' ];
			if ( $line_total < 0 )
				$line_total *= -1;

			$refund_items[ $first_found ][] = array(
				'Index'  => '', 
				'ItemID' => $real_id, 
				'TIC'    => apply_filters( 'wootax_fee_tic', WT_DEFAULT_FEE_TIC ),
				'Qty'    => 1, 
				'Price'  => $line_total,
			);
		}

		// Add shipping costs
		// Shipping costs are always associated with first found location
		$shipping_cost = $refund->get_total_shipping();

		if ( $shipping_cost != 0 ) {
			$item_id = $id_array[ WT_SHIPPING_ITEM ];

			if ( $shipping_cost < 0 )
				$shipping_cost *= -1;

			$refund_items[ $first_found ][] = array(
				'Index'  => '', 
				'ItemID' => $item_id, 
				'TIC'    => apply_filters( 'wootax_shipping_tic', WT_DEFAULT_SHIPPING_TIC ), 
				'Qty'    => 1, 
				'Price'  => $shipping_cost,
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
	 * Returns the name of our refund class.
	 *
	 * @since 4.4
	 *
	 * @param  string $classname Existing refund class name.
	 * @return string
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