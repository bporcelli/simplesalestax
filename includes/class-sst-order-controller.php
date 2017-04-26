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
	}

	/**
	 * When an order is completed, mark it as captured in TaxCloud.
	 *
	 * @since 4.2
	 *
	 * @param  int $order_id ID of completed order.
	 * @return bool True on success, false on failure.
	 */
	public function capture_order( $order_id ) {
		$order = new SST_Order( $order_id );

		// Exit if the order has already been captured
		if ( 'captured' == $order->get_meta( 'status' ) )
			return false;

		return $order->capture();
	}
	
	/**
	 * When a partial or full refund is processed, send a Returned request to TaxCloud.
	 *
	 * @since 4.4
	 *
	 * @param  int $order_id
	 * @param  bool $cron Is a cronjob being executed?
	 * @param  array $items If this is a partial refund, the array of items to refund.
	 * @return mixed
	 */
	public function refund_order( $order_id, $cron = false, $items = array() ) {
		// TODO: REWRITE (SHOULD INVOKE refund() METHOD ON SST_Order object)
		$order = self::get_order( $order_id );

		// Different rules need to be applied for full refunds
		$full_refund = count( $items ) == 0; 

		$destination_address = !$full_refund ? $order->destination_address : array();

		// Exit if the order has already been refunded, has not been captured, or was placed by international customer
		if ( $full_refund && true === self::get_meta( $order_id, 'refunded' ) ) {
			return;
		} else if( !self::get_meta( $order_id, 'captured' ) ) {
			if ( !$cron && $full_refund ) {
				SST_Admin_Notices::add( 'refund_error', '<strong>WARNING:</strong> This order was not refunded in TaxCloud because it has not been captured yet. Please set the order\'s status to completed before refunding it.', false, 'update-nag' );
			} else if ( !$cron ) {
				return "You must set this order's status to 'completed' before refunding any items.";
			}

			return;
		} else if ( isset( $destination_address[ 'Country' ] ) && !in_array( $destination_address[ 'Country' ], array( 'United States', 'US' ) ) ) {
			return true;
		}

		// Set up item mapping array if this is a partial refund
		if ( ! $full_refund ) {
			// Construct mapping array
			$mapping_array = self::get_meta( $order_id, 'mapping_array' );

			if ( count( $mapping_array ) == 0 ) {
				foreach ( $items as $location => $items ) {
					$mapping_array[ $location ] = array();

					foreach ( $items as $item ) {
						$mapping_array[ $location ][ $item[ 'ItemID' ] ] = $order->get_item_index( $item[ 'ItemID' ] );
					}
				}
			} 
		}

		// Loop through sub-orders and send Returned request for each
		$taxcloud_ids = self::get_meta( $order_id, 'taxcloud_ids' );

		foreach ( $taxcloud_ids as $address_key => $order_ids ) {
			$refund_items = NULL;

			// Get cart items to refund in correct format if appropriate
			if ( !$full_refund && isset( $items[ $address_key ] ) ) {
				$refund_items = array();

				// Get items in appropriate format
				foreach ( $items[ $address_key ] as $item ) {
					$item[ 'Index' ]  = $mapping_array[ $address_key ][ $item[ 'ItemID' ] ];
					$refund_items[] = $item;
				}
			}

			// Send Returned request
			$date = new DateTime( 'NOW' );
			
			$req = array(
				'cartItems'    => $refund_items, 
				'returnedDate' => $date->format( DateTime::ATOM ), 
				'orderID'      => $order_ids[ 'order_id' ],
			);
			
			$res = TaxCloud()->send_request( 'Returned', $req );
			
			// Check for errors
			if ( $res == false ) {
				if ( !$cron && $full_refund ) {
					SST_Admin_Notices::add( 'refund_error', 'There was an error while refunding the order. '. TaxCloud()->get_error_message(), false, 'error' );
					break;
				} else {
					return TaxCloud()->get_error_message();
				}
			}
		}

		// For full refunds, remove order tax completely
		if ( $full_refund )
			$order->remove_tax();
		
		self::update_meta( $order_id, 'refunded', true );
	
		return true;
	}

}

new SST_Order_Controller();