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
		add_action( 'woocommerce_order_status_refunded', array( $this, 'refund_order' ) );
		add_action( 'woocommerce_order_status_cancelled', array( $this, 'refund_order' ) );
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
	 * Fully refund order when status is changed to refunded or cancelled.
	 *
	 * @since 5.0
	 *
	 * @param  int $order_id
	 * @return bool True on success, false on failure.
	 */
	public function refund_order( $order_id ) {
		$order = new SST_Order( $order_id );
		return $order->do_refund();
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