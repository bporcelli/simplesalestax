<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Order.
 *
 * Extends WC_Order to add extra functionality.
 *
 * @author 	Simple Sales Tax
 * @package SST
 * @since 	5.0
 */
class SST_Order extends WC_Order {

	/**
	 * @var string Prefix for meta keys.
	 * @since 5.0
	 */
	protected static $prefix = '_wootax_';

	/**
	 * @var array Default values for order meta fields.
	 * @since 4.4
	 */
	protected static $defaults = array(
		'customer_id'        => 0,
		'packages'           => array(),
		'tax_total'          => 0,
		'shipping_tax_total' => 0,
		'exempt_cert'        => '',
		'status'             => 'pending'
	);

	/**
	 * Update order meta.
	 *
	 * @since 5.0
	 *
	 * @param string $key Meta key.
	 * @param mixed $value Meta value.
	 */
	public function update_meta_data( $key, $value ) {
		$key = self::$prefix . $key;

		if ( version_compare( WC_VERSION, '3.0', '>=' ) ) {
			parent::update_meta_data( $key, $value );
		} else {
			update_post_meta( $this->id, $key, $value );
		}
	}

	/**
	 * Get meta value.
	 *
	 * @since 5.0
	 *
	 * @param  string $key
	 * @return mixed empty string if key doesn't exist, otherwise value.
	 */
	public function get_meta( $key ) {
		$value = '';

		if ( version_compare( WC_VERSION, '3.0', '>=' ) ) {
			$value = parent::get_meta( self::$prefix . $key );
		} else {
			$value = get_post_meta( $this->id, self::$prefix . $key, true );
		}

		if ( empty( $value ) && array_key_exists( $key, self::$defaults ) ) {
			$value = self::$defaults[ $key ];
		}

		return $value;
	}

	/**
	 * Reset meta data.
	 *
	 * @since 5.0
	 */
	public function reset_meta_data() {
		foreach ( self::$defaults as $key => $value ) {
			$this->update_meta_data( $key, $value );
		}
	}

	/**
	 * Save order.
	 *
	 * @since 5.0
	 */
	public function save() {
		if ( version_compare( WC_VERSION, '3.0', '>=' ) ) {
			parent::save();
		}
	}

	/**
	 * Issue a Lookup request to compute the sales tax for the order.
	 *
	 * @since 4.2
	 *
	 * @param  array $items Array of CartItems.
	 * @param  array $type_array Array mapping item IDs to item types (cart/shipping).
	 * @param  boolean $subscription Lookup for subscription order?
	 * @return string|array
	 */
	public function do_lookup( $items, $type_array, $subscription = false ) {
		// TODO: REWRITE

		// Fire request if we are ready to do so
		// if ( $this->ready_for_lookup() ) {
		// 	$taxcloud_ids = $lookup_data = array();

		// 	if ( ! $subscription ) {
		// 		// Remove currently applied taxes (delete order item instead?)
		// 		$this->remove_tax();

		// 		// Generate lookup data
		// 		$lookup_data = $this->generate_lookup_data( $items );
		// 		WT_Orders::update_meta( $this->order_id, 'lookup_data', $lookup_data );

		// 		// Generate TaxCloud Cart/Order IDs
		// 		$taxcloud_ids = $this->generate_order_ids();
		// 		WT_Orders::update_meta( $this->order_id, 'taxcloud_ids', $taxcloud_ids );
		// 	} else {
		// 		// Only generate lookup data
		// 		$lookup_data = $this->generate_lookup_data( $items );
		// 	}

		// 	$mapping_array = WT_Orders::get_meta( $this->order_id, 'mapping_array' );

		// 	// Retrieve validated destination addresses
		// 	$destination_address = SST_Addresses::get_destination_address( $this->order );
			
		// 	// Used in every tax lookup
		// 	$all_cart_items = array();
		// 	$tax_total      = $shipping_tax_total = 0;

		// 	// Fetch some information about the order
		// 	$exempt_cert         = $this->get_exemption_certificate();
		// 	$customer_id         = $this->get_customer_id();
		// 	$delivered_by_seller = SST_Shipping::is_local_delivery( $this->get_shipping_method() );

		// 	// Loop through locations in lookup_data array and send a Lookup request for each
		// 	foreach ( $lookup_data as $location_key => $items ) {
		// 		// Fetch cart_id
		// 		$cart_id = isset( $taxcloud_ids[ $location_key ][ 'cart_id' ] ) ? $taxcloud_ids[ $location_key ][ 'cart_id' ] : '';

		// 		// Get the origin address
		// 		$origin_address = wootax_get_address( $location_key );

		// 		// Build request array 
		// 		$req = array(
		// 			'customerID'        => $customer_id, 
		// 			'cartID'            => $cart_id, 
		// 			'cartItems'         => $items, 
		// 			'origin'            => $origin_address, 
		// 			'destination'       => $destination_address, 
		// 			'deliveredBySeller' => $delivered_by_seller,
		// 		);	

		// 		if ( !empty( $exempt_cert ) )
		// 			$req[ 'exemptCert' ] = $exempt_cert;

		// 		// Send Lookup request 
		// 		$res = TaxCloud()->send_request( 'Lookup', $req );

		// 		if ( $res !== false ) {
		// 			// Initialize some vars
		// 			$cart_items = $res->CartItemsResponse->CartItemResponse;

		// 			// Store the returned CartID for later use
		// 			$taxcloud_ids[ $location_key ][ 'cart_id' ] = $res->CartID;

		// 			// If cart_items only contains one item, it will not be an array. 
		// 			// In that case, convert to an array 
		// 			if ( !is_array( $cart_items ) )
		// 				$cart_items = array( $cart_items );

		// 			// Loop through items and update tax amounts
		// 			foreach ( $cart_items as &$cart_item ) {

		// 				// Fetch item info
		// 				$index   = $cart_item->CartItemIndex;
		// 				$item_id = $mapping_array[ $location_key ][ $index ];
		// 				$type    = isset( $type_array[ $item_id ] ) ? $type_array[ $item_id ] : 'cart';
		// 				$tax     = $cart_item->TaxAmount;

		// 				// Update item tax
		// 				if ( !$subscription ) {
		// 					$this->apply_item_tax( $item_id, $tax );
		// 				}

		// 				// Keep track of cart tax/shipping tax totals
		// 				if ( $type == 'cart' ) {
		// 					$tax_total += $tax;
		// 				} else {
		// 					$shipping_tax_total += $tax;
		// 				}

		// 				// Add ItemID property so @see $this->ajax_update_order_tax() can work as expected
		// 				$cart_item->ItemID = $item_id;
		// 			}
		// 		} else {
		// 			// Return error
		// 			return TaxCloud()->get_error_message();
		// 		}

		// 		// Add cart_items to all_cart_items array
		// 		$all_cart_items[] = $cart_items;
		// 	}

		// 	if ( ! $subscription ) {
		// 		// Save updated tax totals
		// 		$this->update_tax_totals( $tax_total, $shipping_tax_total );

		// 		// Update TaxCloud IDs array
		// 		WT_Orders::update_meta( $this->order_id, 'taxcloud_ids', $taxcloud_ids );

		// 		// Reset identifiers array (only useful before first lookup from "edit order" screen)
		// 		WT_Orders::update_meta( $this->order_id, 'identifiers', array() );

		// 		// Store mapping array in reverse order (map item ids to item indexes)
		// 		$new_mapping_array = array();

		// 		foreach ( $mapping_array as $location => $mappings ) {
		// 			foreach ( $mappings as $item_index => $item_id ) {
		// 				if ( !isset( $new_mapping_array[ $location ] ) ) {
		// 					$new_mapping_array[ $location ] = array();
		// 				}

		// 				$new_mapping_array[ $location ][ $item_id ] = $item_index;
		// 			}
		// 		}

		// 		WT_Orders::update_meta( $this->order_id, 'mapping_array', $new_mapping_array );

		// 		// Update index/location mappings
		// 		foreach ( $lookup_data as $address_key => $items ) {
		// 			foreach ( $items as $item ) {
		// 				$item_id = $item['ItemID'];

		// 				if ( $item_id == SST_SHIPPING_ITEM ) { // WC 2.1 shipping
		// 					WT_Orders::update_meta( $this->order_id, 'shipping_index', $item[ 'Index' ] );
		// 				} else {
		// 					wc_update_order_item_meta( $item_id, '_wootax_location_id', $address_key );
		// 					wc_update_order_item_meta( $item_id, '_wootax_index', $item[ 'Index' ] );
		// 				}
		// 			}
		// 		}
		// 	}

		// 	// Return CartItems
		// 	$return_arr = array();				

		// 	foreach ( $all_cart_items as $cart_items ) {
		// 		$return_arr = array_merge( $return_arr, $cart_items );
		// 	}

		// 	return $return_arr;
		// } else {
		// 	return 'An error occurred while calculating order taxes. It is possible that the order has already been "completed" or that no customer shipping address is available. Please try again.';
		// }
	}

	/**
	 * Send AuthorizedWithCapture request to capture order in TaxCloud.
	 *
	 * @since 5.0
	 */
	public function capture() {
		// TODO: REWRITE; RETURN BOOL
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
					SST_Admin_Notices::add( 'capture_error', 'There was an error while marking the order as Captured. '. TaxCloud()->get_error_message(), false, 'error' );
					return;
				} else {
					return TaxCloud()->get_error_message();
				}
			}
		}

		self::update_meta( $order_id, 'captured', true );
	}

	/**
	 * Get the customer ID for this order.
	 *
	 * @since 5.0
	 *
	 * @return int Customer ID.
	 */
	protected function get_customer_id() {
		return absint( $this->get_meta( 'customer_id' ) );
	}

	/**
	 * Returns the exemption certificate applied to the order, or NULL if no
	 * certificate was applied.
	 *
	 * @since 5.0
	 *
	 * @return ExemptionCertificate|null
	 */
	protected function get_certificate() {
		$exempt_cert = $this->get_meta( 'exempt_cert' );
		
		if ( ! empty( $exempt_cert ) ) {
			return TaxCloud\ExemptionCertificate::fromArray( json_decode( $exempt_cert, true ) );
		}

		return null;
	}

	/**
	 * Get TaxCloud status.
	 *
	 * @since 5.0
	 *
	 * @param  string $context (default: 'edit')
	 * @return string
	 */
	public function get_status( $context = 'edit' ) {
		$status = $this->get_meta( 'status' );

		if ( 'view' == $context ) {
			$status = ucfirst( $status );
		}

		return $status;
	}

	/**
	 * Return the ID of the Simple Sales Tax tax item.
	 *
	 * @since 4.6
	 *
	 * @return int|null
	 */
	protected function find_tax_item() {
		$tax_item_id = NULL;

		// Find first rate with matching rate id; set $tax_item_id accordingly
		foreach ( $this->order->get_taxes() as $key => $data ) {
			if ( $data[ 'rate_id' ] == SST_RATE_ID ) {
				$tax_item_id = $key;
				break;
			}
		}

		return $tax_item_id;
	}

	/**
	 * Update tax totals for our tax item.
	 *
	 * @since 4.2
	 *
	 * @param float $cart_tax Cart tax total.
	 * @param float $shipping_tax Shipping tax total.
	 */
	private function update_tax_item( $cart_tax, $shipping_tax ) {
		global $wpdb;

		// Add a new tax item if necessary
		$tax_item_id = $this->get_meta( 'tax_item_id' );;

		if ( $tax_item_id == 0 || $tax_item_id == NULL ) {
			$tax_item_id = $this->find_tax_item();

			if ( ! $tax_item_id ) {
				$wpdb->insert( "{$wpdb->prefix}woocommerce_order_items", array(
					'order_item_type' => 'tax', 
					'order_item_name' => apply_filters( 'wootax_rate_code', 'SALES-TAX' ), 
					'order_id'        => $this->order_id,
				) );

				$tax_item_id = $wpdb->insert_id;
			}

			$this->update_meta_data( 'tax_item_id', $tax_item_id );
			$this->save();
		}

		// Update tax item meta
		wc_update_order_item_meta( $tax_item_id, 'rate_id', SST_RATE_ID );
		wc_update_order_item_meta( $tax_item_id, 'label', SST()->get_rate_label( SST_RATE_ID ) );
		wc_update_order_item_meta( $tax_item_id, 'name', SST()->get_rate_label( SST_RATE_ID ) );
		wc_update_order_item_meta( $tax_item_id, 'compound', true );
		wc_update_order_item_meta( $tax_item_id, 'tax_amount', $cart_tax );
		wc_update_order_item_meta( $tax_item_id, 'shipping_tax_amount', $shipping_tax );
		wc_update_order_item_meta( $tax_item_id, 'cart_tax', $cart_tax );
		wc_update_order_item_meta( $tax_item_id, 'shipping_tax', $shipping_tax );
	}

	/**
	 * Reset sales tax to zero.
	 *
	 * @since 5.0
	 */
	public function remove_tax() {		
		$items = $this->get_items() + $this->get_fees() + $this->get_shipping_methods();

		// Remove all taxes
		foreach ( $items as $item_id => $data ) {
			$this->remove_item_tax( $item_id );
		}

		// TODO: UPDATE TAX TOTALS
	}

	/**
	 * Set the sales tax for the given item.
	 *
	 * @since 4.2
	 *
	 * @param int $item_id 
	 * @param float $amt Sales tax for item.
	 */
	private function apply_item_tax( $item_id, $amt ) {
		// Calculate new tax values
		$line_subtotal_tax = $this->get_item_meta( $item_id, '_line_subtotal_tax' ) + $amt;
		$line_tax 		   = $this->get_item_meta( $item_id, '_line_tax' ) + $amt;

		// Save new tax values
		wc_update_order_item_meta( $item_id, '_line_tax', $line_tax );
		wc_update_order_item_meta( $item_id, '_line_subtotal_tax', $line_subtotal_tax );
		wc_update_order_item_meta( $item_id, '_wootax_tax_amount', $amt ); 

		// Update the tax_data array
		$tax_data = $this->get_item_meta( $item_id, '_line_tax_data' );
		$taxes    = $this->get_item_meta( $item_id, 'taxes' );

		if ( $taxes ) {
			// Shipping item
			if ( isset( $taxes[ SST_RATE_ID ] ) ) {
				$taxes[ SST_RATE_ID ] = $amt;
			}

			wc_update_order_item_meta( $item_id, 'taxes', $taxes );
		} else {
			if ( isset( $tax_data['total'] ) ) {
				// Cart items
				$tax_data['subtotal'][ SST_RATE_ID ] = $amt;
				$tax_data['total'][ SST_RATE_ID ]    = $amt;
			} else {
				// Fee
				$tax_data[ SST_RATE_ID ] = $amt;
			}

			wc_update_order_item_meta( $item_id, '_line_tax_data', $tax_data );
		}
	}
	
	/**
	 * Get item metadata. Return false if given meta key is not set.
	 *
	 * @since 4.2
	 *
	 * @param  int $item_id
	 * @param  mixed $meta_key Meta key.
	 * @param  bool $single Retrieve a single meta value?
	 * @return mixed
	 */
	public function get_item_meta( $item_id, $meta_key, $single = true ) {
		if ( ! $this->order instanceof WC_Order ) {
			return false;
		} else {
			return $this->order->get_item_meta( $item_id, $meta_key, $single );
		}
	}

	/**
	 * Reset sales tax for a given item to zero.
	 *
	 * @since 4.2
	 *
	 * @param $item_id
	 */
	private function remove_item_tax( $item_id ) {		
		// Fetch applied tax
		$applied_tax = $this->get_item_tax( $item_id );

		if ( ! $applied_tax ) {
			return;
		}

		// Calculate new tax values
		$line_subtotal_tax = $this->get_item_meta( $item_id, '_line_subtotal_tax' ) - $applied_tax;
		$line_tax          = $this->get_item_meta( $item_id, '_line_tax' ) - $applied_tax;

		// Zero out tax if the calculated value is negative
		$line_subtotal_tax = $line_subtotal_tax < 0 ? 0 : $line_subtotal_tax;
		$line_tax          = $line_tax < 0 ? 0 : $line_tax;

		// Save new tax values
		wc_update_order_item_meta( $item_id, '_line_tax', $line_tax );
		wc_update_order_item_meta( $item_id, '_line_subtotal_tax', $line_subtotal_tax );
		wc_update_order_item_meta( $item_id, '_wootax_tax_amount', 0 );

		// Update the tax_data array
		$tax_data = $this->get_item_meta( $item_id, '_line_tax_data' );
		$taxes    = $this->get_item_meta( $item_id, 'taxes' );

		if ( $taxes ) {
			// Shipping item
			if ( isset( $taxes[ SST_RATE_ID ] ) ) {
				$taxes[ SST_RATE_ID ] = 0;
			}

			wc_update_order_item_meta( $item_id, 'taxes', $taxes );
		} else {
			if ( isset( $tax_data['total'] ) ) {
				// Cart items
				$tax_data['subtotal'][ SST_RATE_ID ] = 0;
				$tax_data['total'][ SST_RATE_ID ]    = 0;
			} else {
				// Fee
				$tax_data[ SST_RATE_ID ] = 0;
			}

			wc_update_order_item_meta( $item_id, '_line_tax_data', $tax_data );
		}					
	}

}