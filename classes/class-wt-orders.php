<?php

/**
 * Contains methods for managing and manipulating order taxes
 *
 * @package WooTax
 * @since 4.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Do not all direct access
}

class WT_Orders {
	/** Meta prefix */
	private static $prefix = '_wootax_';

	/** Array of default values for order meta fields */
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

	/** The only WC_WooTax_Order instance */
	private static $order_instance = NULL;

	/** WC_Logger object */
	private static $logger = false;

	/** All configured business addresses */
	public static $addresses = array();

	/**
	 * Initialize class
	 * Hook into WooCommerce actions/filters
	 *
	 * @since 4.4
	 */
	public static function init() {
		self::hooks();

		self::$addresses = fetch_business_addresses();

		if ( WT_LOG_REQUESTS ) {
			self::init_logger();
		}
	}

	/**
	 * Hook into WooCommerce actions and filters
	 * 
	 * @since 4.4
	 */
	private static function hooks() {
		// Define meta fields to hide from user
		add_filter( 'woocommerce_hidden_order_itemmeta', array( __CLASS__, 'hide_order_item_meta' ), 10, 1 );

		if ( version_compare( WOOCOMMERCE_VERSION, '2.2', '>=' ) ) {
			// Sets correct shipping tax total for newly created order
			add_action( 'woocommerce_order_add_shipping', array( __CLASS__, 'add_shipping_tax' ), 12, 3 );
		
			// Sets tax totals for the WooTax tax item ID
			add_action( 'woocommerce_order_add_tax', array( __CLASS__, 'add_order_tax_rate' ), 12, 3 );
		}

		// Maybe capture an order immediately after checkout
		add_action( 'woocommerce_checkout_update_order_meta', array( __CLASS__, 'maybe_capture_order' ), 15, 1 );

		// Add WooTax meta when order is created or resumed
		add_action( 'woocommerce_new_order', array( __CLASS__, 'add_order_meta' ), 10, 1 );
		add_action( 'woocommerce_resume_order', array( __CLASS__, 'add_order_meta' ), 10, 1 );

		// Add meta to order items when order is created
		add_action( 'woocommerce_add_order_item_meta', array( __CLASS__, 'add_cart_item_meta' ), 10, 3 );
		add_action( 'woocommerce_add_order_fee_meta', array( __CLASS__, 'add_fee_meta' ), 10, 4 );
		add_action( 'woocommerce_add_shipping_order_item', array( __CLASS__, 'add_shipping_meta' ), 10, 3 );

		// Calculate taxes from "Edit Order" screen
		add_action( 'wp_ajax_woocommerce_calc_line_taxes', array( __CLASS__, 'ajax_update_order_tax' ), 1 );

		// Send AuthorizedWithCapture request to TaxCloud when an order is marked as completed
		add_action( 'woocommerce_order_status_completed', array( __CLASS__, 'capture_order' ), 10, 1 );
		
		// Send Returned request to TaxCloud when an order's status is changed to refunded or cancelled
		add_action( 'woocommerce_order_status_refunded', array( __CLASS__, 'refund_order' ), 10, 1 );
		add_action( 'woocommerce_order_status_cancelled', array( __CLASS__, 'refund_order' ), 10, 1 );
	}

	/**
	 * Instantiate WC_WooTax_Order on init
	 */
	private static function instantiate_order() {
		if ( self::$order_instance == NULL ) {
			self::$order_instance = new WC_WooTax_Order();
		}
	}

	/** 
	 * Get WC_WooTax_Order object
	 *
	 * @param (int) $order_id ID of WooCommerce order to load
	 */
	public static function get_order( $order_id = -1 ) {
		if ( is_null( self::$order_instance ) ) {
			self::$order_instance = new WC_WooTax_Order();
		}

		if ( self::$order_instance->order_id != $order_id ) {
			self::$order_instance->load( $order_id );	
		}

		return self::$order_instance;
	}

	/**
	 * Set up WC_Logger 
	 *
	 * @since 4.4
	 */
	public static function init_logger() {
		self::$logger = class_exists( 'WC_Logger' ) ? new WC_Logger() : WC()->logger();
	}

	/**
	 * Sets WooTax order meta when a new order is created
	 * Inserts a log entry indicating an order has been created if logging is enabled
	 *
	 * @since 4.2
	 * @param (int) $order_id ID of newly created WooCommerce order
	 */
	public static function add_order_meta( $order_id ) {
		self::persist_session_data( $order_id );
		self::maybe_store_shipping_index( $order_id );

		if ( self::$logger ) {
			self::$logger->add( 'wootax', 'New order with ID '. $order_id .' created. Tax total: '. self::get_meta( $order_id, 'tax_total' ) .'; Shipping tax total: '. self::get_meta( $order_id, 'shipping_tax_total' ) );
		}

		// Load order so the add_cart_item_meta method can work properly
		self::get_order( $order_id );
	}

	/**
	 * Persist WooTax session data as order meta and reset session
	 * 
	 * @since 4.2
	 * @param (int) $order_id WooCommerce order ID
	 */
	public static function persist_session_data( $order_id ) {
		if ( WC()->session instanceof WC_Session_Handler ) {
			self::update_meta( $order_id, 'taxcloud_ids', WC()->session->get( 'taxcloud_ids' ) );
			self::update_meta( $order_id, 'cart_taxes', WC()->session->get( 'cart_taxes' ) );
			self::update_meta( $order_id, 'location_mapping_array', WC()->session->get( 'location_mapping_array' ) );
			self::update_meta( $order_id, 'customer_id', WC()->session->get( 'wootax_customer_id' ) );
			self::update_meta( $order_id, 'tax_total', WC()->session->get( 'wootax_tax_total' ) );
			self::update_meta( $order_id, 'shipping_tax_total', WC()->session->get( 'wootax_shipping_tax_total' ) );
			self::update_meta( $order_id, 'identifiers', WC()->session->get( 'item_ids' ) );
			self::update_meta( $order_id, 'first_found', WC()->session->get( 'first_found_key' ) );
			self::update_meta( $order_id, 'validated_addresses', WC()->session->get( 'validated_addresses' ) );

			// Flip mapping array and store in reverse order
			$mapping_array = WC()->session->get( 'mapping_array', array() );

			if ( count( $mapping_array ) > 0 ) {
				foreach ( $mapping_array as $address_key => $mappings ) {
					$new_mappings = array();

					foreach ( $mappings as $index => $item ) {
						$new_mappings[ $item['id'] ] = $index;
					}

					$mapping_array[ $address_key ] = $new_mappings;
				}
			}

			self::update_meta( $order_id, 'mapping_array', $mapping_array );

			do_action( 'wt_persist_session_data', $order_id );

			self::delete_session_data();
		}
	}

	/**
	 * Removes all WooTax data from the session post-checkout
	 *
	 * @since 4.2
	 */
	public static function delete_session_data() {
		if ( WC()->session instanceof WC_Session_Handler ) {
			WC()->session->set( 'wootax_lookup_sent', '' );
			WC()->session->set( 'cert_removed', false );
			WC()->session->set( 'cart_taxes', array() );
			WC()->session->set( 'backend_cart_taxes', array() );
			WC()->session->set( 'taxcloud_ids', array() );
			WC()->session->set( 'backend_location_mapping', array() );
			WC()->session->set( 'validated_addresses', array() );
			WC()->session->set( 'wootax_tax_total', 0 );
			WC()->session->set( 'wootax_shipping_tax_total', 0 );

			do_action( 'wt_delete_session_data' );
			
			WC()->session->save_data();
		}
	}

	/** 
     * Store index of shipping item when an order is created if WC < 2.2 is being used
     *
     * @since 4.4
     * @param (int) $order_id WooCommerce order ID
     */
	public static function maybe_store_shipping_index( $order_id ) {
		if ( version_compare( WOOCOMMERCE_VERSION, '2.2', '<' ) ) {
			$location_mapping = self::get_meta( $order_id, 'location_mapping_array' );
			$mapping          = self::get_meta( $order_id, 'mapping_array' );

			$location_id = isset( $location_mapping[ WT_SHIPPING_ITEM ] ) ? $location_mapping[ WT_SHIPPING_ITEM ] : 0;

			if ( isset( $mapping[ $location_id ][ WT_SHIPPING_ITEM ] ) ) {
				self::update_meta( $order_id, 'shipping_index', $mapping[ $location_id ][ WT_SHIPPING_ITEM ] );
			}
		}
	}

	/**
	 * Updates "taxes" meta for shipping items to include any tax added by WooTax
	 * Assumes that only one shipping method is used per order
	 *
	 * @since 4.2
	 * @param $order_id the id of the WooCommerce order
	 * @param $item_id the id of the item inserted into the database
	 * @param $shipping_rate the actual shipping rate (object)
	 */
	public static function add_shipping_tax( $order_id, $item_id, $shipping_rate )  {
		$taxes = array_map( 'wc_format_decimal', $shipping_rate->taxes );

		$taxes[ WT_RATE_ID ] = self::get_meta( $order_id, 'shipping_tax_total' );

		wc_update_order_item_meta( $item_id, 'taxes', $taxes );
	}


	/**
	 * Set tax total and shipping tax total for WooTax tax item
	 * Associated tax item ID with newly created order
	 *
	 * @since 4.2
	 * @param (int) $order_id WooCommerce order ID
	 * @param (int) $item_id a tax (order) item ID
	 * @param (int) $tax_rate_id rate id associated with tax item
	 */
	public static function add_order_tax_rate( $order_id, $item_id, $tax_rate_id ) {
		if( $tax_rate_id != WT_RATE_ID ) {
			return;
		}

		// Store tax item id
		self::update_meta( $order_id, 'tax_item_id', $item_id );

		// Update tax item with correct amounts
		$tax          = self::get_meta( $order_id, 'tax_total' );
		$shipping_tax = self::get_meta( $order_id, 'shipping_tax_total' );

		wc_add_order_item_meta( $item_id, 'tax_amount', wc_format_decimal( $tax ) );
		wc_add_order_item_meta( $item_id, 'shipping_tax_amount', wc_format_decimal( $shipping_tax ) );
	}

	/**
	 * Add WooTax meta to order cart items:
	 * - Location associated with item
	 * - Tax applied by WooTax
	 * - CartIndex of item in Lookup request
	 *
	 * @since 4.2
	 * @param $item_id order item id
	 * @param $values cart item data
	 * @param $cart_item_key cart item key
	 */
	public static function add_cart_item_meta( $item_id, $values, $cart_item_key ) {
		$order_id = self::$order_instance->order_id; // No order_id is passed for cart items :'(

		$location_mapping = self::get_meta( $order_id, 'location_mapping_array' );
		$mapping          = self::get_meta( $order_id, 'mapping_array' );
		$cart_taxes       = self::get_meta( $order_id, 'cart_taxes' );

		$location_id = isset( $location_mapping[ $cart_item_key ] ) ? $location_mapping[ $cart_item_key ] : 0;
		$tax_amount  = isset( $cart_taxes[ $cart_item_key ] ) ? $cart_taxes[ $cart_item_key ] : 0;
		$item_index  = isset( $mapping[ $location_id ][ $cart_item_key ] ) ? $mapping[ $location_id ][ $cart_item_key ] : 0;

		wc_add_order_item_meta( $item_id, '_wootax_location_id', $location_id );
		wc_add_order_item_meta( $item_id, '_wootax_tax_amount', $tax_amount );
		wc_add_order_item_meta( $item_id, '_wootax_index', $item_index );
	}

	/**
	 * Add WooTax meta to order fees:
	 * - Location associated with fee
	 * - Tax applied by WooTax
	 * - CartIndex of item in Lookup request
	 *
	 * @since 4.2
	 * @param $order_id WooCommerce order id
	 * @param $item_id order item id
	 * @param $fee fee data
	 * @param $fee_key fee key
	 */
	public static function add_fee_meta( $order_id, $item_id, $fee, $fee_key ) {
		$location_mapping = self::get_meta( $order_id, 'location_mapping_array' );
		$mapping          = self::get_meta( $order_id, 'mapping_array' );
		$cart_taxes       = self::get_meta( $order_id, 'cart_taxes' );

		$location_id = isset( $location_mapping[ $fee_key ] ) ? $location_mapping[ $fee_key ] : 0;
		$tax_amount  = isset( $cart_taxes[ $fee_key ] ) ? $cart_taxes[ $fee_key ] : 0;
		$item_index  = isset( $mapping[ $location_id ][ $fee_key ] ) ? $mapping[ $location_id ][ $fee_key ] : 0;

		wc_add_order_item_meta( $item_id, '_wootax_location_id', $location_id );
		wc_add_order_item_meta( $item_id, '_wootax_tax_amount', $tax_amount );
		wc_add_order_item_meta( $item_id, '_wootax_index', $item_index );
	}

	/**
	 * Add WooTax meta to order shipping methods:
	 * - Location associated with method
	 * - Tax applied by WooTax
	 * - CartIndex of item in Lookup request
	 *
	 * We will assume there is only one shipping method for each order.
	 *
	 * @since 4.2
	 * @param $order_id WooCommerce order id
	 * @param $item_id order item id
	 * @param $package_key shipping package key
	 */
	public static function add_shipping_meta( $order_id, $item_id, $package_key ) {
		$location_mapping = self::get_meta( $order_id, 'location_mapping_array' );
		$mapping          = self::get_meta( $order_id, 'mapping_array' );
		$cart_taxes       = self::get_meta( $order_id, 'cart_taxes' );

		$location_id = isset( $location_mapping[ WT_SHIPPING_ITEM ] ) ? $location_mapping[ WT_SHIPPING_ITEM ] : 0;
		$tax_amount  = isset( $cart_taxes[ WT_SHIPPING_ITEM ] ) ? $cart_taxes[ WT_SHIPPING_ITEM ] : 0;
		$item_index  = isset( $mapping[ $location_id ][ WT_SHIPPING_ITEM ] ) ? $mapping[ $location_id ][ WT_SHIPPING_ITEM ] : 0;

		wc_add_order_item_meta( $item_id, '_wootax_location_id', $location_id );
		wc_add_order_item_meta( $item_id, '_wootax_tax_amount', $tax_amount );
		wc_add_order_item_meta( $item_id, '_wootax_index', $item_index );
	}

	/**
	 * Update order taxes via AJAX
	 *
	 * @since 4.2
	 * @return JSON object with status (error | success) and status message
	 */
	public static function ajax_update_order_tax() {
		global $wpdb;

		$order_id = absint( $_POST['order_id'] );
		$country  = strtoupper( esc_attr( $_POST['country'] ) );
				
		// Get WC_WooTax_Order object
		$order = self::get_order( $order_id );
	
		if ( $country != 'US' && $country != 'United States' || !WT_CALC_TAXES ) {
			return; // Returning here allows WC_AJAX::calc_line_taxes to take over for non-US orders
		} else {
			// Build items array
			if ( version_compare( WOOCOMMERCE_VERSION, '2.2', '>=' ) ) {
			    parse_str( $_POST['items'], $items );
			} else if ( version_compare( WOOCOMMERCE_VERSION, '2.1.0', '>=' ) ) {
				$items = array(
					'order_item_id'      => array(),
					'order_item_qty'     => array(),
					'line_total'         => array(),
					'shipping_method_id' => array(),
					'shipping_cost'      => array(),
				);

				// Add cart items/fees
				foreach ( $_POST['items'] as $item_id => $item ) {
					$items['order_item_id'][] = $item_id;

					if ( get_post_type( $order->get_item_meta( $item_id, '_product_id' ) ) == 'product' ) {
						$items['order_item_qty'][$item_id] = isset( $item['quantity'] ) ? $item['quantity'] : 1;
					}

					$items['line_total'][$item_id] = $item['line_total'];
				}

				// Add item for shipping cost
				if ( isset( $_POST['shipping'] ) && $_POST['shipping'] != 0 ) {
					$items['shipping_cost'][ WT_SHIPPING_ITEM ] = $_POST['shipping'];
					$items['shipping_method_id'][]              = WT_SHIPPING_ITEM;
				}
			}

			$order_items = array();
			$final_items = array();

			// Add cart items and fees
			$order_items = array_merge( $items['order_item_id'], $order_items );

			// Add shipping items
			if ( isset( $items['shipping_method_id'] ) ) {
				$order_items = array_merge( $items['shipping_method_id'], $order_items );
			}

			// Construct items array from POST data
			foreach ( $order_items as $item_id ) {
				$qty = 1;

				if ( is_array( $items['shipping_method_id'] ) && in_array( $item_id, $items['shipping_method_id'] ) ) {
					// Shipping method
					$tic  = WT_SHIPPING_TIC;
					$cost = $items['shipping_cost'][$item_id];
					$type = 'shipping';
				} else if ( isset( $items['order_item_qty'][$item_id] ) ) {
					// Cart item
					$product_id   = $order->get_item_meta( $item_id, '_product_id' );
					$variation_id = $order->get_item_meta( $item_id, '_variation_id' );

					$tic  = wt_get_product_tic( $product_id, $variation_id );
					$cost = $items['line_total'][ $item_id ];
					$type = 'cart';
					$qty  = WC_WooTax::get_option('tax_based_on') == 'line-subtotal' ? 1 : $items['order_item_qty'][ $item_id ];
				} else {
					// Fee
					$tic  = WT_FEE_TIC;
					$cost = $items['line_total'][$item_id];
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

					if ( !empty( $tic ) && $tic ) {
						$item_data['TIC'] = $tic;
					}

					$final_items[] = $item_data;
				}
			}
			
			// Send lookup request using the generated items and mapping array
			$res = $order->do_lookup( $final_items, $type_array );

			// Convert response array to be sent back to client
			// @see WC_AJAX::calc_line_taxes()
			if ( is_array( $res ) ) {
				if ( version_compare( WOOCOMMERCE_VERSION, '2.2', '>=' ) ) {
					if ( !isset( $items['line_tax'] ) ) {
						$items['line_tax'] = array();
					}

					if ( !isset( $items['line_subtotal_tax'] ) ) {
						$items['line_subtotal_tax'] = array();
					}

					$items['order_taxes'] = array();

					foreach ( $res as $item )  {
						$id  = $item->ItemID;
						$tax = $item->TaxAmount; 

						if ( is_array( $items['shipping_method_id'] ) && in_array( $id, $items['shipping_method_id'] ) ) {
							$items['shipping_taxes'][ $id ][ WT_RATE_ID ] = $tax;
						} else {
							$items['line_tax'][ $id ][ WT_RATE_ID ] = $tax;
							$items['line_subtotal_tax'][ $id ][ WT_RATE_ID ] = $tax;
						}
					}

					// Added in 4.6: add new tax item if old item has been removed
					$order = $order->order;
					$taxes = $order->get_taxes();
					$tax_item_id = self::get_meta( $order_id, 'tax_item_id' );

					if ( empty( $tax_item_id ) || !in_array( $tax_item_id, array_keys( $taxes ) ) ) {
						$tax_item_id = $order->add_tax( WT_RATE_ID, $tax_total, $shipping_tax_total );
						self::update_meta( $order_id, 'tax_item_id', $tax_item_id );
					}

					$items['order_taxes'][ $tax_item_id ] = absint( WT_RATE_ID );

					// Save order items
					wc_save_order_items( $order_id, $items );

					// Return HTML items
					$data  = get_post_meta( $order_id );

					include( ABSPATH . '/'. PLUGINDIR . '/woocommerce/includes/admin/meta-boxes/views/html-order-items.php' );

					die();
				} else if ( version_compare( WOOCOMMERCE_VERSION, '2.1', '>=' ) ) {
					// We are going to send back a JSON response
					header( 'Content-Type: application/json; charset=utf-8' );

					$item_tax = $shipping_tax = 0;
					$tax_row_html = '';
					$item_taxes = array();

					// Update item taxes
					foreach ( $res as $item ) {
						$id  = $item->ItemID;
						$tax = $item->TaxAmount; 

						if ( $id == WT_SHIPPING_ITEM ) {
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

						$rate_id   = $data['rate_id'];
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
	 * Send AuthorizedWithCapture request to TaxCloud when an order is marked as completed
	 *
	 * @since 4.2
	 * @param (int) $order_id WooCommerce order ID
	 * @param (bool) $cron is this method being called from inside a WooTax cronjob?
	 */
	public static function capture_order( $order_id, $cron = false ) {
		// Exit if the order has already been captured
		if ( self::get_meta( $order_id, 'captured' ) ) {
			return;
		}

		// Loop through sub-orders and send request for each
		$taxcloud_ids = self::get_meta( $order_id, 'taxcloud_ids' );
		$customer_id  = self::get_meta( $order_id, 'customer_id' );

		foreach ( $taxcloud_ids as $address_key => $data ) {
			// Send captured request
			$authorized_date = date( 'c' );

			$req = array(
				'cartID'         => $data['cart_id'], 
				'customerID'     => $customer_id, 
				'orderID'        => $data['order_id'], 
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
	 * Send a Returned request to TaxCloud when a full or partial refund is processed
	 * - Full refund initiated when an order's status is set to "refunded"
	 * - Partial refund initiated when WooCommerce manual refund mechanism is used
	 *
	 * @since 4.4
	 * @param (int) $order_id ID of WooCommerce order
	 * @param (bool) $cron is this method being called from a WooTax cronjob?
	 * @param (array) $items array of items to refund for partial refunds
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
		} else if ( isset( $destination_address['Country'] ) && !in_array( $destination_address['Country'], array( 'United States', 'US' ) ) ) {
			return true;
		}

		// Set up item mapping array if this is a partial refund
		if ( !$full_refund ) {
			// Construct mapping array
			$mapping_array = self::get_meta( $order_id, 'mapping_array' );

			if ( count( $mapping_array ) == 0 ) {
				foreach ( $items as $location => $items ) {
					$mapping_array[ $location ] = array();

					foreach ( $items as $item ) {
						$mapping_array[ $location ][ $item['ItemID'] ] = $order->get_item_index( $item['ItemID'] );
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
					$item['Index']  = $mapping_array[ $address_key ][ $item['ItemID'] ];
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
		if ( $full_refund ) {
			$order->remove_tax();
		}
		
		self::update_meta( $order_id, 'refunded', true );
	
		return true;
	}

	/**
	 * Hides WooTax order item meta 
	 *
	 * @since 4.2
	 * @param (array) $to_hide of meta fields to hide
	 * @return (array) modified $to_hide array
	 */
	public static function hide_order_item_meta( $to_hide ) {
		$to_hide[] = '_wootax_tax_amount';
		$to_hide[] = '_wootax_location_id';
		$to_hide[] = '_wootax_index';

		return $to_hide;
	}

	/**
	 * Maybe capture order immediately after checkout
	 *
	 * @since 4.5
	 */
	public static function maybe_capture_order( $order_id ) {
		if ( WC_WooTax::get_option( 'capture_immediately' ) == 'yes' ) {
			$res = self::capture_order( $order_id, true );

			if ( $res !== true && self::$logger )
				self::$logger->add( 'wootax', 'Failed to capture order '. $order_id .' after checkout.' );
		}
	}

	/**
	 * Get WooTax meta, substituting default value if not set
	 *
	 * @since 4.4
	 * @param (int) $order_id a WooCommerce order/post ID
	 * @param (mixed) $key meta key
	 * @return (mixed) meta value or NULL
	 */
	public static function get_meta( $order_id, $key ) {
		$raw_value = get_post_meta( $order_id, self::$prefix . $key, true );

		if ( !$raw_value ) {
			return isset( self::$defaults[ $key ] ) ? self::$defaults[ $key ] : NULL;
		} else {
			return $raw_value;
		}
	}

	/**
	 * Update WooTax meta for order
	 * All WooTax meta keys are prefixed with self::$prefix
	 *
	 * @since 4.4
	 * @param (int) $order_id a WooCommerce order/post ID
	 * @param (mixed) $key meta key
	 * @param (mixed) $value meta value
	 */
	public static function update_meta( $order_id, $key, $value ) {
		update_post_meta( $order_id, self::$prefix . $key, $value );
	}
}

WT_Orders::init();