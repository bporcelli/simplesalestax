<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Order Controller.
 *
 * Handles requests related to orders.
 *
 * @author 	Simple Sales Tax
 * @package SST
 * @since 	5.0
 */
class SST_Order_Controller {

	/**
	 * Constructor.
	 *
	 * @since 5.0
	 */
	public function __construct() {
		add_action( 'woocommerce_order_status_completed', array( $this, 'capture_order' ) );
		add_action( 'woocommerce_refund_created', array( $this, 'refund_order' ), 10, 2 );
		add_action( 'woocommerce_payment_complete', array( $this, 'maybe_capture_order' ) );
		add_filter( 'woocommerce_ajax_after_calc_line_taxes', array( $this, 'calc_line_taxes' ), 10, 4 );
	}

	/**
	 * When an order is completed, mark it as captured in TaxCloud.
	 *
	 * @since 5.0
	 *
	 * @param  int $order_id ID of completed order.
	 * @return bool True on success, false on failure.
	 */
	public function capture_order( $order_id ) {
		$order = new SST_Order( $order_id );
		return $order->do_capture();
	}
	
	/**
	 * When a new refund is created, send a Returned request to TaxCloud.
	 *
	 * @since 5.0
	 *
	 * @param  int $refund_id ID of new refund.
	 * @param  array $args Refund arguments (see wc_create_refund()).
	 * @return bool True on success, false on failure.
	 */
	public function refund_order( $refund_id, $args ) {
		$items = isset( $args['line_items'] ) ? $args['line_items'] : array();
		$order = new SST_Order( $args['order_id'] );

		/* If items specified, convert to format expected by do_refund() */
		if ( ! empty( $items ) ) { 
			$all_items = $order->get_items( array( 'line_item', 'shipping', 'fee' ) );

			foreach ( $items as $item_id => $data ) {
				if ( $data['refund_total'] > 0 && isset( $all_items[ $item_id ] ) ) {
					$item_type = $all_items[ $item_id ]['type'];

					/* Match line total with value entered by user */
					if ( 'shipping' == $item_type )
						$all_items[ $item_id ]['cost'] = $data['refund_total'];
					else
						$all_items[ $item_id ]['line_total'] = $data['refund_total'];

					/* Match quantity with value entered by user */
					if ( 'line_item' == $item_type )
						$all_items[ $item_id ]['qty'] = $data['qty'];

					$items[ $item_id ] = $all_items[ $item_id ];
				} else
					unset( $items[ $item_id ] );
			}
		}

		/* Delete refund if refund fails */
		$result = false;
		try {
			$result = $order->do_refund( $items );
			
			if ( ! $result ) {
				wp_delete_post( $refund_id, true );
			}
		} catch ( Exception $ex ) {
			wp_delete_post( $refund_id, true );
			throw $ex; /* Let Woo handle the exception */
		}

		return $result;
	}

	/**
	 * If the "Capture Orders Immediately" option is enabled, capture orders
	 * when payment is received.
	 *
	 * @since 5.0
	 *
	 * @param int $order_id
	 */
	public function maybe_capture_order( $order_id ) {
		if ( 'yes' == SST_Settings::get( 'capture_immediately' ) ) {
			$order = new SST_Order( $order_id );
			$order->do_capture();
		}
	}

	/**
	 * Recalculate sales tax via AJAX.
	 *
	 * @since 4.2
	 */
	public static function calc_line_taxes() {
		// TODO: update to ensure back compatibility
		// USE  FILTER INSTEAD?
		global $wpdb;

		$order_id = absint( $_POST[ 'order_id' ] );
		$country  = strtoupper( esc_attr( $_POST[ 'country' ] ) );
				
		// Get WC_WooTax_Order object
		$order = self::get_order( $order_id );
	
		if ( $country != 'US' && $country != 'United States' || ! SST_Compatibility::taxes_enabled() ) {
			return; // Returning here allows WC_AJAX::calc_line_taxes to take over for non-US orders
		} else {
			// Build items array
			parse_str( $_POST[ 'items' ], $items );

			$order_items = array();
			$final_items = array();

			// Add cart items and fees
			$order_items = array_merge( $items[ 'order_item_id' ], $order_items );

			// Add shipping items
			if ( isset( $items[ 'shipping_method_id' ] ) ) {
				$order_items = array_merge( $items[ 'shipping_method_id' ], $order_items );
			}

			// Construct items array from POST data
			foreach ( $order_items as $item_id ) {
				$qty = 1;

				if ( is_array( $items[ 'shipping_method_id' ] ) && in_array( $item_id, $items[ 'shipping_method_id' ] ) ) {
					// Shipping method
					$tic  = apply_filters( 'wootax_shipping_tic', SST_DEFAULT_SHIPPING_TIC );
					$cost = $items[ 'shipping_cost' ][$item_id];
					$type = 'shipping';
				} else if ( isset( $items[ 'order_item_qty' ][$item_id] ) ) {
					// Cart item
					$product_id   = $order->get_item_meta( $item_id, '_product_id' );
					$variation_id = $order->get_item_meta( $item_id, '_variation_id' );

					$tic  = SST_Product::get_tic( $product_id, $variation_id );
					$cost = $items[ 'line_total' ][ $item_id ];
					$type = 'cart';
					$qty  = SST_Settings::get( 'tax_based_on' ) == 'line-subtotal' ? 1 : $items[ 'order_item_qty' ][ $item_id ];
				} else {
					// Fee
					$tic  = apply_filters( 'wootax_fee_tic', SST_DEFAULT_FEE_TIC );
					$cost = $items[ 'line_total' ][$item_id];
					$type = 'fee';
				}

				// Calculate unit price
				$unit_price = $cost / $qty;

				// Add item to final items array
				if ( $unit_price != 0 ) {
					// Map item_id to item type 
					$type_array[ $item_id ] = $type == 'shipping' ? 'shipping' : 'cart';
									
					// Add to items array 
					$item_data = array(
						'Index'  => '', // Leave index blank. It is assigned later.
						'ItemID' => $item_id, 
						'Qty'    => $qty, 
						'Price'  => $unit_price,	
						'Type'   => $type,
					);	

					if ( ! empty( $tic ) && $tic )
						$item_data['TIC'] = $tic;

					$final_items[] = $item_data;
				}
			}
			
			// Send lookup request using the generated items and mapping array
			$res = $order->do_lookup( $final_items, $type_array );

			// Convert response array to be sent back to client
			// @see WC_AJAX::calc_line_taxes()
			if ( is_array( $res ) ) {
					
				if ( ! isset( $items[ 'line_tax' ] ) )
					$items[ 'line_tax' ] = array();
				if ( ! isset( $items[ 'line_subtotal_tax' ] ) )
					$items[ 'line_subtotal_tax' ] = array();

				$items[ 'order_taxes' ] = array();

				foreach ( $res as $item )  {
					$id  = $item->ItemID;
					$tax = $item->TaxAmount; 

					if ( is_array( $items[ 'shipping_method_id' ] ) && in_array( $id, $items[ 'shipping_method_id' ] ) ) {
						$items[ 'shipping_taxes' ][ $id ][ SST_RATE_ID ] = $tax;
					} else {
						$items[ 'line_tax' ][ $id ][ SST_RATE_ID ] = $tax;
						$items[ 'line_subtotal_tax' ][ $id ][ SST_RATE_ID ] = $tax;
					}
				}

				// Added in 4.6: add new tax item if old item has been removed
				$order = $order->order;
				$taxes = $order->get_taxes();
				$tax_item_id = self::get_meta( $order_id, 'tax_item_id' );

				if ( empty( $tax_item_id ) || ! in_array( $tax_item_id, array_keys( $taxes ) ) ) {
					$tax_item_id = $order->add_tax( SST_RATE_ID, $tax_total, $shipping_tax_total );
					self::update_meta( $order_id, 'tax_item_id', $tax_item_id );
				}

				$items[ 'order_taxes' ][ $tax_item_id ] = absint( SST_RATE_ID );

				// Save order items
				wc_save_order_items( $order_id, $items );

				// Return HTML items
				$data  = get_post_meta( $order_id );

				include( WC()->plugin_path() . '/includes/admin/meta-boxes/views/html-order-items.php' );

				die();
			} else {
				die( 'Could not update order taxes. It is possible that the order has already been "completed," or that the customer\'s shipping address is unavailable. Please refresh the page and try again.' ); 
			}
		}
	}
}

new SST_Order_Controller();