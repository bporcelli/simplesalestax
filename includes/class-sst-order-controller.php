<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Order Controller.
 *
 * Handles requests related to orders.
 *
 * @author  Simple Sales Tax
 * @package SST
 * @since   5.0
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
		add_filter( 'woocommerce_hidden_order_itemmeta', array( $this, 'hide_order_item_meta' ) );
		add_filter( 'woocommerce_order_item_get_taxes', array( $this, 'fix_shipping_tax_issue' ), 10, 2 );
		add_action( 'woocommerce_before_order_object_save', array( $this, 'on_order_saved' ) );
		add_filter(
			'woocommerce_order_data_store_cpt_get_orders_query',
			array( $this, 'handle_version_query_var' ),
			10,
			2
		);
	}

	/**
	 * When an order is completed, mark it as captured in TaxCloud.
	 *
	 * @param int $order_id ID of completed order.
	 *
	 * @return bool True on success, false on failure.
	 *
	 * @throws Exception If capture fails.
	 * @since 5.0
	 */
	public function capture_order( $order_id ) {
		$order = new SST_Order( $order_id );

		return $order->do_capture();
	}

	/**
	 * When a new refund is created, send a Returned request to TaxCloud.
	 *
	 * @param int   $refund_id ID of new refund.
	 * @param array $args      Refund arguments (see wc_create_refund()).
	 *
	 * @return bool True on success, false on failure.
	 *
	 * @throws Exception If refund fails.
	 * @since 5.0
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
					if ( 'shipping' === $item_type ) {
						$all_items[ $item_id ]['cost'] = $data['refund_total'];
					} else {
						$all_items[ $item_id ]['line_total'] = $data['refund_total'];
					}

					/* Match quantity with value entered by user */
					if ( 'line_item' === $item_type ) {
						$all_items[ $item_id ]['qty'] = isset( $data['qty'] ) ? $data['qty'] : 1;
					}

					$items[ $item_id ] = $all_items[ $item_id ];
				} else {
					unset( $items[ $item_id ] );
				}
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
	 * @param int $order_id ID of order for which payment was just received.
	 *
	 * @throws Exception If attempt to capture order fails.
	 * @since 5.0
	 */
	public function maybe_capture_order( $order_id ) {
		if ( 'yes' === SST_Settings::get( 'capture_immediately' ) ) {
			$order = new SST_Order( $order_id );

			$order->do_capture();
		}
	}

	/**
	 * Hides Simple Sales Tax order item meta.
	 *
	 * @param array $to_hide Meta keys to hide.
	 *
	 * @return array
	 * @since 5.0
	 */
	public function hide_order_item_meta( $to_hide ) {
		$to_hide[] = '_wootax_tax_amount';
		$to_hide[] = '_wootax_location_id';
		$to_hide[] = '_wootax_index';

		return $to_hide;
	}

	/**
	 * Temporary fix for #50. Ensures that tax data for shipping items is
	 * correctly formatted.
	 *
	 * @param array         $taxes Tax data for WooCommerce order item.
	 * @param WC_Order_Item $item  WooCommerce order item object.
	 *
	 * @return array
	 * @since 5.0
	 */
	public function fix_shipping_tax_issue( $taxes, $item ) {
		if ( 'shipping' === $item->get_type() ) {
			if ( isset( $taxes['total'], $taxes['total']['total'] ) ) {
				unset( $taxes['total']['total'] );
			}
		}

		return $taxes;
	}

	/**
	 * Runs when an order is saved.
	 *
	 * Calculates the tax for the order if it was created via the REST API and
	 * ensures that the order DB version is set.
	 *
	 * @param WC_Order $order The WooCommerce order that is about to be saved.
	 */
	public function on_order_saved( $order ) {
		if ( 'rest-api' === $order->get_created_via() ) {
			// Remove hook temporarily to prevent infinite loop.
			remove_action( 'woocommerce_before_order_object_save', array( $this, 'on_order_saved' ) );

			sst_order_calculate_taxes( $order );

			add_action( 'woocommerce_before_order_object_save', array( $this, 'on_order_saved' ) );
		}

		$db_version = $order->get_meta( '_wootax_db_version', true );

		if ( empty( $db_version ) ) {
			$order->update_meta_data( '_wootax_db_version', SST()->version );
		}
	}

	/**
	 * Adds support for querying orders by DB version and TaxCloud status with wc_get_orders()
	 *
	 * @param array $query      Args for WP_Query.
	 * @param array $query_vars Query vars from WC_Order_Query.
	 *
	 * @return array
	 */
	public function handle_version_query_var( $query, $query_vars ) {
		if ( ! is_array( $query['meta_query'] ) ) {
			$query['meta_query'] = array();
		}

		if ( ! empty( $query_vars['wootax_version'] ) ) {
			$query['meta_query'][] = array(
				'key'   => '_wootax_db_version',
				'value' => esc_attr( $query_vars['wootax_version'] ),
			);
		}

		if ( ! empty( $query_vars['wootax_status'] ) ) {
			$query['meta_query'][] = array(
				'key'   => '_wootax_status',
				'value' => esc_attr( $query_vars['wootax_status'] ),
			);
		}

		return $query;
	}

}

new SST_Order_Controller();
