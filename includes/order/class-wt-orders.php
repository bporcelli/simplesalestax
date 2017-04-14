<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( ! class_exists( 'WC_WooTax_Order' ) ) {
	require_once trailingslashit( dirname( __FILE__ ) ) . 'class-wc-wootax-order.php';
}

/**
 * WT Orders.
 *
 * Contains methods for managing and manipulating order taxes.
 *
 * @author 	Simple Sales Tax
 * @package SST
 * @since 	4.4
 */
class WT_Orders {

	/**
	 * @var string Prefix for meta keys.
	 * @since 4.4
	 */
	private static $prefix = '_wootax_';

	/**
	 * @var array Default values for order meta fields.
	 * @since 4.4
	 */
	public static $defaults = array(
		'tax_total'              => 0,
		'shipping_tax_total'     => 0,
		'customer_id'            => 0,
		'tax_item_id'            => 0,
		'exemption_applied'      => false,
		'captured'               => false,
		'refunded'               => false,
		'first_found'            => '',
		'shipping_index'         => '',
		'mapping_array'          => array(),
		'location_mapping_array' => array(),
		'lookup_data'            => array(),
		'taxcloud_ids'			 => array(),
		'identifiers'            => array(),
	);

	/**
	 * @var WC_WooTax_Order The only WC_WooTax_Order instance.
	 * @since 4.4
	 */
	private static $order_instance = NULL;

	/**
	 * @var WC_Logger Logger.
	 * @since 4.4
	 */
	private static $logger = false;

	/**
	 * @var array Array of configured business addresses.
	 * @since 4.4
	 */
	public static $addresses = array();

	/**
	 * Hook into WooCommerce actions/filters.
	 *
	 * @since 4.4
	 */
	public static function init() {
		self::hooks();

		self::$addresses = SST_Addresses::get_origin_addresses();

		if ( SST_LOG_REQUESTS )
			self::init_logger();
	}

	/**
	 * Register action/filter hooks.
	 * 
	 * @since 4.4
	 */
	private static function hooks() {
		// Define meta fields to hide from user
		add_filter( 'woocommerce_hidden_order_itemmeta', array( __CLASS__, 'hide_order_item_meta' ), 10, 1 );

		// Maybe capture an order immediately after checkout
		// TODO: only capture if payment is received!
		add_action( 'woocommerce_checkout_update_order_meta', array( __CLASS__, 'maybe_capture_order' ), 15, 1 );

		// Calculate taxes from "Edit Order" screen
		add_action( 'wp_ajax_woocommerce_calc_line_taxes', array( __CLASS__, 'ajax_update_order_tax' ), 1 );

		// Send AuthorizedWithCapture request to TaxCloud when an order is marked as completed
		add_action( 'woocommerce_order_status_completed', array( __CLASS__, 'capture_order' ), 10, 1 );
		
		// Send Returned request to TaxCloud when an order's status is changed to refunded or cancelled
		add_action( 'woocommerce_order_status_refunded', array( __CLASS__, 'refund_order' ), 10, 1 );
		add_action( 'woocommerce_order_status_cancelled', array( __CLASS__, 'refund_order' ), 10, 1 );
	}

	/** 
	 * Given an order ID, return a WC_WooTax_Order object.
	 *
	 * @since 4.4
	 *
	 * @param  int $order_id
	 * @return WC_WooTax_Order
	 */
	public static function get_order( $order_id = -1 ) {
		if ( is_null( self::$order_instance ) )
			self::$order_instance = new WC_WooTax_Order();
		if ( self::$order_instance->order_id != $order_id )
			self::$order_instance->load( $order_id );
		return self::$order_instance;
	}

	/**
	 * Set self::$logger. 
	 *
	 * @since 4.4
	 */
	public static function init_logger() {
		self::$logger = class_exists( 'WC_Logger' ) ? new WC_Logger() : WC()->logger();
	}

	/**
	 * If WC < 2.2 is being used, store the index of the shipping item when the
	 * order is created. (TODO: WHY?)
	 *
	 * @since 4.4
	 *
     * @param int $order_id ID of new order.
	 */
	public static function maybe_store_shipping_index( $order_id ) {
		if ( version_compare( SST_WOO_VERSION, '2.2', '<' ) ) {
			$location_mapping = self::get_meta( $order_id, 'location_mapping_array' );
			$mapping          = self::get_meta( $order_id, 'mapping_array' );

			$location_id = isset( $location_mapping[ SST_SHIPPING_ITEM ] ) ? $location_mapping[ SST_SHIPPING_ITEM ] : 0;

			if ( isset( $mapping[ $location_id ][ SST_SHIPPING_ITEM ] ) ) {
				self::update_meta( $order_id, 'shipping_index', $mapping[ $location_id ][ SST_SHIPPING_ITEM ] );
			}
		}
	}

	/**
	 * Updates the "taxes" meta for shipping items to include tax added by us. Note
	 * that this method assumes only one shipping method per order.
	 *
	 * @since 4.2
	 *
	 * @param int $order_id
	 * @param int $item_id ID of shipping item.
	 * @param object $shipping_rate Shipping rate.
	 */
	public static function add_shipping_tax( $order_id, $item_id, $shipping_rate )  {
		$taxes = array_map( 'wc_format_decimal', $shipping_rate->taxes );
		$taxes[ SST_RATE_ID ] = self::get_meta( $order_id, 'shipping_tax_total' );
		wc_update_order_item_meta( $item_id, 'taxes', $taxes );
	}

	/**
	 * Set cart/shipping totals for new order.
	 *
	 * @since 4.2
	 *
	 * @param int $order_id
	 * @param int $item_id Tax item ID.
	 * @param int $tax_rate_id ID of rate associated with tax item.
	 */
	public static function add_order_tax_rate( $order_id, $item_id, $tax_rate_id ) {
		if( $tax_rate_id != SST_RATE_ID )
			return;

		// Store tax item id
		self::update_meta( $order_id, 'tax_item_id', $item_id );

		// Update tax item with correct amounts
		$tax          = self::get_meta( $order_id, 'tax_total' );
		$shipping_tax = self::get_meta( $order_id, 'shipping_tax_total' );

		wc_add_order_item_meta( $item_id, 'tax_amount', wc_format_decimal( $tax ) );
		wc_add_order_item_meta( $item_id, 'shipping_tax_amount', wc_format_decimal( $shipping_tax ) );
	}

	/**
	 * AJAX handler. Update order taxes.
	 *
	 * @since 4.2
	 */
	public static function ajax_update_order_tax() {
		// TODO: update to ensure back compatibility
		global $wpdb;

		$order_id = absint( $_POST[ 'order_id' ] );
		$country  = strtoupper( esc_attr( $_POST[ 'country' ] ) );
				
		// Get WC_WooTax_Order object
		$order = self::get_order( $order_id );
	
		if ( $country != 'US' && $country != 'United States' || ! SST_Compatibility::taxes_enabled() ) {
			return; // Returning here allows WC_AJAX::calc_line_taxes to take over for non-US orders
		} else {
			// Build items array
			if ( version_compare( SST_WOO_VERSION, '2.2', '>=' ) ) {
			    parse_str( $_POST[ 'items' ], $items );
			} else if ( version_compare( SST_WOO_VERSION, '2.1.0', '>=' ) ) {
				$items = array(
					'order_item_id'      => array(),
					'order_item_qty'     => array(),
					'line_total'         => array(),
					'shipping_method_id' => array(),
					'shipping_cost'      => array(),
				);

				// Add cart items/fees
				foreach ( $_POST[ 'items' ] as $item_id => $item ) {
					$items[ 'order_item_id' ][] = $item_id;

					if ( get_post_type( $order->get_item_meta( $item_id, '_product_id' ) ) == 'product' ) {
						$items[ 'order_item_qty' ][$item_id] = isset( $item[ 'quantity' ] ) ? $item[ 'quantity' ] : 1;
					}

					$items['line_total'][$item_id] = $item['line_total'];
				}

				// Add item for shipping cost
				if ( isset( $_POST[ 'shipping' ] ) && $_POST[ 'shipping' ] != 0 ) {
					$items[ 'shipping_cost' ][ SST_SHIPPING_ITEM ] = $_POST[ 'shipping' ];
					$items[ 'shipping_method_id' ][]              = SST_SHIPPING_ITEM;
				}
			}

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
					$qty  = SST()->get_option( 'tax_based_on' ) == 'line-subtotal' ? 1 : $items[ 'order_item_qty' ][ $item_id ];
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
				
					// Add tax amount to tax array
					$old_taxes[ $item_id ] = $order->get_item_tax( $item_id );
					
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
				if ( version_compare( SST_WOO_VERSION, '2.2', '>=' ) ) {
					
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

					include( ABSPATH . '/'. PLUGINDIR . '/woocommerce/includes/admin/meta-boxes/views/html-order-items.php' );

					die();
				} else if ( version_compare( SST_WOO_VERSION, '2.1', '>=' ) ) {
					// We are going to send back a JSON response
					header( 'Content-Type: application/json; charset=utf-8' );

					$item_tax = $shipping_tax = 0;
					$tax_row_html = '';
					$item_taxes = array();

					// Update item taxes
					foreach ( $res as $item ) {
						$id  = $item->ItemID;
						$tax = $item->TaxAmount; 

						if ( $id == SST_SHIPPING_ITEM ) {
							$shipping_tax += $tax;
						} else {
							$item_taxes[ $id ] = array(
								'line_subtotal_tax' => wc_format_localized_price( $tax ),
								'line_tax'          => wc_format_localized_price( $tax ),
							);

							$item_tax += $tax;
						}	
					}

					// Fetch array mapping tax rate ids to tax codes
					$tax_codes = array();
					$taxes     = $order->order->get_taxes();

					foreach ( $taxes as $item_id => $data ) {
						$code = array();

						$rate_id   = $data[ 'rate_id' ];
						$rate_data = $wpdb->get_row( "SELECT * FROM {$wpdb->prefix}woocommerce_tax_rates WHERE tax_rate_id = '$rate_id'" );

						$code[] = $rate_data->tax_rate_country;
						$code[] = $rate_data->tax_rate_state;
						$code[] = $rate_data->tax_rate_name ? sanitize_title( $rate_data->tax_rate_name ) : 'TAX';
						$code[] = absint( $rate_data->tax_rate_priority );

						$tax_codes[ $rate_id ] = strtoupper( implode( '-', array_filter( $code ) ) );
					}

					// Loop through tax items to build tax row HTML
					ob_start();

					foreach ( $taxes as $item_id => $item ) {
						include( ABSPATH . '/'. PLUGINDIR . '/woocommerce/includes/admin/post-types/meta-boxes/views/html-order-tax.php' );
					}

					$tax_row_html = ob_get_clean();

					// Return
					echo json_encode( array(
						'item_tax' 		=> $item_tax,
						'item_taxes' 	=> $item_taxes,
						'shipping_tax' 	=> $shipping_tax,
						'tax_row_html' 	=> $tax_row_html,
					) );

					// Quit out
					die();
				} 
			} else {
				die( 'Could not update order taxes. It is possible that the order has already been "completed," or that the customer\'s shipping address is unavailable. Please refresh the page and try again.' ); 
			}
		}
	}

	/**
	 * When an order is completed, mark it as captured in TaxCloud.
	 *
	 * @since 4.2
	 *
	 * @param int $order_id ID of completed order.
	 * @param bool $cron Is a cronjob being executed?
	 * @return mixed
	 */
	public static function capture_order( $order_id, $cron = false ) {
		// Exit if the order has already been captured
		if ( self::get_meta( $order_id, 'captured' ) )
			return;

		// Loop through sub-orders and send request for each
		$taxcloud_ids = self::get_meta( $order_id, 'taxcloud_ids' );
		$customer_id  = self::get_meta( $order_id, 'customer_id' );

		foreach ( $taxcloud_ids as $address_key => $data ) {
			// Send captured request
			$authorized_date = date( 'c' );

			$req = array(
				'cartID'         => $data[ 'cart_id' ], 
				'customerID'     => $customer_id, 
				'orderID'        => $data[ 'order_id' ],
				'dateAuthorized' => $authorized_date, 
				'dateCaptured'   => $authorized_date,
			);

			$res = TaxCloud()->send_request( 'AuthorizedWithCapture', $req );

			// Check for errors
			if ( $res == false ) {
				if ( !$cron ) {
					wootax_add_message( 'There was an error while marking the order as Captured. '. TaxCloud()->get_error_message() );
					return;
				} else {
					return TaxCloud()->get_error_message();
				}
			}
		}

		self::update_meta( $order_id, 'captured', true );
		return true;
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
	public static function refund_order( $order_id, $cron = false, $items = array() ) {
		$order = self::get_order( $order_id );

		// Different rules need to be applied for full refunds
		$full_refund = count( $items ) == 0; 

		$destination_address = !$full_refund ? $order->destination_address : array();

		// Exit if the order has already been refunded, has not been captured, or was placed by international customer
		if ( $full_refund && true === self::get_meta( $order_id, 'refunded' ) ) {
			return;
		} else if( !self::get_meta( $order_id, 'captured' ) ) {
			if ( !$cron && $full_refund ) {
				wootax_add_message( '<strong>WARNING:</strong> This order was not refunded in TaxCloud because it has not been captured yet. Please set the order\'s status to completed before refunding it.', 'update-nag' );
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
					wootax_add_message( 'There was an error while refunding the order. '. TaxCloud()->get_error_message() );
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

	/**
	 * Hide WooTax item meta.
	 *
	 * @since 4.2
	 *
	 * @param  array $to_hide Array of keys of hidden meta fields.
	 * @return array
	 */
	public static function hide_order_item_meta( $to_hide ) {
		$to_hide[] = '_wootax_tax_amount';
		$to_hide[] = '_wootax_location_id';
		$to_hide[] = '_wootax_index';

		return $to_hide;
	}

	/**
	 * If "Capture Orders Immediately" is enabled, capture newly created orders
	 * immediately after checkout.
	 *
	 * @since 4.5
	 *
	 * @param int $order_id ID of new order.
	 */
	public static function maybe_capture_order( $order_id ) {
		if ( SST()->get_option( 'capture_immediately' ) == 'yes' ) {
			$res = self::capture_order( $order_id, true );

			if ( $res !== true && self::$logger )
				self::$logger->add( 'wootax', 'Failed to capture order '. $order_id .' after checkout.' );
		}
	}

	/**
	 * Get order meta value. Return default if not set.
	 *
	 * @since 4.4
	 *
	 * @param  int $order_id
	 * @param  mixed $key
	 * @return mixed Meta value or default if not set.
	 */
	public static function get_meta( $order_id, $key ) {
		$raw_value = get_post_meta( $order_id, self::$prefix . $key, true );

		if ( ! $raw_value ) {
			return isset( self::$defaults[ $key ] ) ? self::$defaults[ $key ] : NULL;
		} else {
			return $raw_value;
		}
	}

	/**
	 * Set order meta.
	 *
	 * @since 4.4
	 *
	 * @param int $order_id
	 * @param mixed $key
	 * @param mixed $value
	 */
	public static function update_meta( $order_id, $key, $value ) {
		update_post_meta( $order_id, self::$prefix . $key, $value );
	}
}

WT_Orders::init();