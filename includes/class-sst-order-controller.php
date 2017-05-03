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
}

new SST_Order_Controller();