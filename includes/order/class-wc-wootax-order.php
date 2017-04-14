<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * WooTax Order.
 *
 * Represents an order and contains all methods used to manipulate order taxes. 
 *
 * @author 	Simple Sales Tax
 * @package SST
 * @since 	4.2
 */
class WC_WooTax_Order {
	
	/**
	 * @var int Order ID.
	 * @since 4.2
	 */
	public $order_id;
	
	/**
	 * @var WC_Order Order object.
	 * @since 4.2
	 */
	public $order;

	/**
	 * @var array Destination address for order.
	 * @since 4.2
	 */
	public $destination_address = array();

	/**
	 * Load order with given ID.
	 *
	 * @since 4.2
	 *
	 * @param int $order_id ID of order to load. 
	 */
	public function load( $order_id = -1 ) {
		if ( $order_id > 0 ) {
			$this->order_id            = $order_id;
			$this->order               = wc_get_order( $this->order_id );
			$this->destination_address = SST_Addresses::get_destination_address( $this->order );
		}
	}

	/**
	 * Given an item ID, return the CartItem index used for the Lookup request.
	 *
	 * @since 4.2
	 *
	 * @param  int $item_id
	 * @return int
	 */
	public function get_item_index( $item_id ) {
		$mapping_array = WT_Orders::get_meta( $this->order_id, 'mapping_array' );

		if ( isset( $mapping_array[ $item_id ] ) ) {
			return $mapping_array[ $item_id ];
		} else {
			if ( $item_id == SST_SHIPPING_ITEM ) { // WC 2.1 shipping
				return WT_Orders::get_meta( $this->order_id, 'shipping_index' );
			} else {
				return $this->get_item_meta( $item_id, '_wootax_index' );
			}
		}
	}
	
	/**
	 * Reset sales tax to zero.
	 *
	 * @since 4.2
	 */
	public function remove_tax() {		
		if ( version_compare( SST_WOO_VERSION, '2.2', '<' ) ) {
			$items = $this->order->get_items() + $this->order->get_fees();
		} else {
			$items = $this->order->get_items() + $this->order->get_fees() + $this->order->get_shipping_methods();
		}

		// Remove all taxes
		foreach ( $items as $item_id => $data ) {
			$this->remove_item_tax( $item_id );
		}

		// Update tax totals
		$this->update_tax_totals();
	}

	/**
	 * Get the tax total for a given order item.
	 *
	 * @since 4.2
	 *
	 * @param  $item_id
	 * @return float
	 */
	public function get_item_tax( $item_id ) {
		$tax = 0.0;

		if ( $item_id == SST_SHIPPING_ITEM ) { // This will occur for WooCommerce 2.1.x
			$tax = WT_Orders::get_meta( $this->order_id, 'shipping_tax_total' );
		} else if ( $this->get_item_meta( $item_id, '_wootax_tax_amount' ) ) {
			$tax = $this->get_item_meta( $item_id, '_wootax_tax_amount' );
		}

		return $tax;
	}
	
	/**
	 * Applies tax to the item with given key 
	 *
	 * @since 4.2
	 */
	/**
	 * Set the sales tax for the given item.
	 *
	 * @since 4.2
	 *
	 * @param int $item_id 
	 * @param float $amt Sales tax for item.
	 */
	private function apply_item_tax( $item_id, $amt ) {
		if ( $item_id == SST_SHIPPING_ITEM ) { // WooCommerce 2.1.x shipping 
			WT_Orders::update_meta( $this->order_id, 'shipping_tax_total', WT_Orders::get_meta( $this->order_id, 'shipping_tax_total' ) + $amt );
		} else {
			// Calculate new tax values
			$line_subtotal_tax = $this->get_item_meta( $item_id, '_line_subtotal_tax' ) + $amt;
			$line_tax 		   = $this->get_item_meta( $item_id, '_line_tax' ) + $amt;

			// Save new tax values
			wc_update_order_item_meta( $item_id, '_line_tax', $line_tax );
			wc_update_order_item_meta( $item_id, '_line_subtotal_tax', $line_subtotal_tax );
			wc_update_order_item_meta( $item_id, '_wootax_tax_amount', $amt ); 

			// Update the "tax_data" array if we are dealing with WooCommerce 2.2+
			if ( version_compare( SST_WOO_VERSION, '2.2', '>=' ) ) {
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

		if ( $applied_tax != 0 ) {
			if ( $item_id == SST_SHIPPING_ITEM ) { // WooCommerce 2.1.x shipping charges
				WT_Orders::update_meta( $this->order_id, 'shipping_tax_total', 0 );
			} else {
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

				// Update the "tax_data" array if we are dealing with WooCommerce 2.2+
				if ( version_compare( SST_WOO_VERSION, '2.2', '>=' ) ) {
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
		}
	}

	/**
	 * Generates an array of CartItems organized by location key.
	 *
	 * @since 4.2
	 *
	 * @return array
	 */
	private function generate_lookup_data( $order_items = array() ) {
		// Exit if we do not have any items
		if ( count( $order_items ) == 0 ) {
			WT_Orders::update_meta( $this->order_id, 'lookup_data', array() );
			WT_Orders::update_meta( $this->order_id, 'mapping_array', array() );

			return;
		}

		// Determine the state where the customer is located
		$customer_state = $this->destination_address['State'];

		// Initialize some vars that we need for the foreach loop below
		$data = $mapping_array = $counters_array = $fee_items = $shipping_items = array();

		// This will hold the ID of the first found origin address/location for this order; Fees and shipping chars will be attached to it
		$first_found = false;

		// Loop through order items; group items by their shipping origin address and format data for tax lookup
		foreach ( $order_items as $item_key => $item ) {
			$item_id = $item['ItemID'];
			$type    = $item['Type'];

			switch( $type ) {
				case 'cart':
					// Fetch shipping origin addresses for this product_id
					$item_ids = array( 
						'product_id'   => $this->order->get_item_meta( $item_id, '_product_id', true),
						'variation_id' => $this->order->get_item_meta( $item_id, '_variation_id', true ),
					);

					$product          = $this->order->get_product_from_item( $item_ids );
					$origin_addresses = SST_Product::get_origin_addresses( $product->id );
					$address_found    = WT_DEFAULT_ADDRESS;

					/**
					 * Attempt to find proper origin address
					 * If there is more than one address available, we will use the first address that occurs in the customer's state 
					 * If there no shipping location in the customer's state, we will use the default origin address
					 * Developers can modify the selected shipping origin address using the wootax_origin_address filter
					 */
					if ( count( $origin_addresses ) == 1 ) {
						// There is only one address ID to fetch, with index 0
						$address_found = $origin_addresses[0];				
					} else {
						// Find an address in the customer's state if possible
						foreach ( $origin_addresses as $key ) {
							if ( isset( WT_Orders::$addresses[ $key ]['state'] ) && WT_Orders::$addresses[ $key ]['state'] == $customer_state ) {
								$address_found = $key;
								break;
							}
						}
					}

					// Allow developers to use their own logic to determine the appropriate shipment origin for a product
					$address_found = apply_filters( 'wootax_origin_address', $address_found, $customer_state, $this );

					// Store the id of the first shipping location we find for the order so we can attach shipping items and fees later on
					if ( $first_found === false ) {
						$first_found = $address_found;
					}

					// Initialize arrays to avoid PHP notices
					if ( !isset( $data[ $address_found ] ) || !is_array( $data[ $address_found ] ) ) {
						$data[ $address_found ] = array();
					}

					if ( !isset( $counters_array[ $address_found ] ) ) {
						$counters_array[ $address_found ] = 0;
					}

					if ( !isset( $mapping_array[ $address_found ] ) ) {
						$mapping_array[ $address_found ] = array();
					} 

					// Update mapping array
					$mapping_array[ $address_found ][] = $item_id;

					// Update item data before storing in $data array
					$item['Index'] = $counters_array[ $address_found ];
					$item['Price'] = apply_filters( 'wootax_taxable_price', $item['Price'], false, $item_id );

					unset( $item['Type'] );

					// Add formatted item data to the $data array
					$data[ $address_found ][] = $item;

					// Increment counter
					$counters_array[ $address_found ]++;
				break;

				case 'shipping':
					// Push this item to the shipping array; the cost of shipping will be attached to the first daughter order later on
					$shipping_items[ $item_id ] = $item;
				break;

				case 'fee':
					// Push this item to the fee array; it will be attached to the first daughter order later on
					$fee_items[ $item_id ] = $item;
				break;
			}
		}

		// Attach shipping items and fees to the first daughter order
		if ( $first_found !== false ) {
			foreach ( $shipping_items + $fee_items as $key => $item ) {

				// Get new item index
				$index = $counters_array[ $first_found ];

				// Add to items array (Type index not included here)
				$data[ $first_found ][] = array(
					'Index'  => $index,
					'ItemID' => $item['ItemID'],
					'TIC'    => $item['TIC'],
					'Price'  => apply_filters( 'wootax_taxable_price', $item['Price'], false, $item['ItemID'] ),
					'Qty'    => $item['Qty'],
				);

				// Update mapping array
				$mapping_array[ $first_found ][ $index ] = $key;

				// Increment counter
				$counters_array[ $first_found ]++;

			}
		}

		// Save mapping array/first found and return lookup data
		WT_Orders::update_meta( $this->order_id, 'mapping_array', $mapping_array );
		WT_Orders::update_meta( $this->order_id, 'first_found', $first_found );

		return $data;
	}

	/**
	 * Generate an order ID for each lookup
	 *
	 * @since  4.2
	 */
	/**
	 * Generate TaxCloud order/cart IDs for the Lookup.
	 *
	 * @since 4.2
	 *
	 * @return array Array of TaxCloud Cart/Order IDs, one for each location key.
	 */
	private function generate_order_ids() {		
		$taxcloud_ids = WT_Orders::get_meta( $this->order_id, 'taxcloud_ids' );

		foreach ( WT_Orders::get_meta( $this->order_id, 'lookup_data' ) as $location => $items ) {
			if ( ! isset( $taxcloud_ids[ $location ] ) ) {
				$taxcloud_ids[ $location ] = array(
					'cart_id'  => '',
					'order_id' => md5( $_SERVER['REMOTE_ADDR'] . microtime() ),
				);
			}
		}

		return $taxcloud_ids;
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
		// Fire request if we are ready to do so
		if ( $this->ready_for_lookup() ) {
			$taxcloud_ids = $lookup_data = array();

			if ( ! $subscription ) {
				// Remove currently applied taxes (delete order item instead?)
				$this->remove_tax();

				// Generate lookup data
				$lookup_data = $this->generate_lookup_data( $items );
				WT_Orders::update_meta( $this->order_id, 'lookup_data', $lookup_data );

				// Generate TaxCloud Cart/Order IDs
				$taxcloud_ids = $this->generate_order_ids();
				WT_Orders::update_meta( $this->order_id, 'taxcloud_ids', $taxcloud_ids );
			} else {
				// Only generate lookup data
				$lookup_data = $this->generate_lookup_data( $items );
			}

			$mapping_array = WT_Orders::get_meta( $this->order_id, 'mapping_array' );

			// Retrieve validated destination addresses
			$destination_address = SST_Addresses::get_destination_address( $this->order );
			
			// Used in every tax lookup
			$all_cart_items = array();
			$tax_total      = $shipping_tax_total = 0;

			// Fetch some information about the order
			$exempt_cert         = $this->get_exemption_certificate();
			$customer_id         = $this->get_customer_id();
			$delivered_by_seller = SST_Shipping::is_local_delivery( $this->get_shipping_method() );

			// Loop through locations in lookup_data array and send a Lookup request for each
			foreach ( $lookup_data as $location_key => $items ) {
				// Fetch cart_id
				$cart_id = isset( $taxcloud_ids[ $location_key ][ 'cart_id' ] ) ? $taxcloud_ids[ $location_key ][ 'cart_id' ] : '';

				// Get the origin address
				$origin_address = wootax_get_address( $location_key );

				// Build request array 
				$req = array(
					'customerID'        => $customer_id, 
					'cartID'            => $cart_id, 
					'cartItems'         => $items, 
					'origin'            => $origin_address, 
					'destination'       => $destination_address, 
					'deliveredBySeller' => $delivered_by_seller,
				);	

				if ( !empty( $exempt_cert ) )
					$req[ 'exemptCert' ] = $exempt_cert;

				// Send Lookup request 
				$res = TaxCloud()->send_request( 'Lookup', $req );

				if ( $res !== false ) {
					// Initialize some vars
					$cart_items = $res->CartItemsResponse->CartItemResponse;

					// Store the returned CartID for later use
					$taxcloud_ids[ $location_key ][ 'cart_id' ] = $res->CartID;

					// If cart_items only contains one item, it will not be an array. 
					// In that case, convert to an array 
					if ( !is_array( $cart_items ) )
						$cart_items = array( $cart_items );

					// Loop through items and update tax amounts
					foreach ( $cart_items as &$cart_item ) {

						// Fetch item info
						$index   = $cart_item->CartItemIndex;
						$item_id = $mapping_array[ $location_key ][ $index ];
						$type    = isset( $type_array[ $item_id ] ) ? $type_array[ $item_id ] : 'cart';
						$tax     = $cart_item->TaxAmount;

						// Update item tax
						if ( !$subscription ) {
							$this->apply_item_tax( $item_id, $tax );
						}

						// Keep track of cart tax/shipping tax totals
						if ( $type == 'cart' ) {
							$tax_total += $tax;
						} else {
							$shipping_tax_total += $tax;
						}

						// Add ItemID property so @see $this->ajax_update_order_tax() can work as expected
						$cart_item->ItemID = $item_id;
					}
				} else {
					// Return error
					return TaxCloud()->get_error_message();
				}

				// Add cart_items to all_cart_items array
				$all_cart_items[] = $cart_items;
			}

			if ( ! $subscription ) {
				// Save updated tax totals
				$this->update_tax_totals( $tax_total, $shipping_tax_total );

				// Update TaxCloud IDs array
				WT_Orders::update_meta( $this->order_id, 'taxcloud_ids', $taxcloud_ids );

				// Reset identifiers array (only useful before first lookup from "edit order" screen)
				WT_Orders::update_meta( $this->order_id, 'identifiers', array() );

				// Store mapping array in reverse order (map item ids to item indexes)
				$new_mapping_array = array();

				foreach ( $mapping_array as $location => $mappings ) {
					foreach ( $mappings as $item_index => $item_id ) {
						if ( !isset( $new_mapping_array[ $location ] ) ) {
							$new_mapping_array[ $location ] = array();
						}

						$new_mapping_array[ $location ][ $item_id ] = $item_index;
					}
				}

				WT_Orders::update_meta( $this->order_id, 'mapping_array', $new_mapping_array );

				// Update index/location mappings
				foreach ( $lookup_data as $address_key => $items ) {
					foreach ( $items as $item ) {
						$item_id = $item['ItemID'];

						if ( $item_id == SST_SHIPPING_ITEM ) { // WC 2.1 shipping
							WT_Orders::update_meta( $this->order_id, 'shipping_index', $item[ 'Index' ] );
						} else {
							wc_update_order_item_meta( $item_id, '_wootax_location_id', $address_key );
							wc_update_order_item_meta( $item_id, '_wootax_index', $item[ 'Index' ] );
						}
					}
				}
			}

			// Return CartItems
			$return_arr = array();				

			foreach ( $all_cart_items as $cart_items ) {
				$return_arr = array_merge( $return_arr, $cart_items );
			}

			return $return_arr;
		} else {
			return 'An error occurred while calculating order taxes. It is possible that the order has already been "completed" or that no customer shipping address is available. Please try again.';
		}
	}
	
	/**
	 * Returns the exemption certificate applied to the order, or NULL if no
	 * certificate was applied.
	 *
	 * @since 4.2
	 *
	 * @return WT_Exemption_Certificate|null
	 */
	public function get_exemption_certificate() {
		$exemption_applied = WT_Orders::get_meta( $this->order_id, 'exemption_applied' );
		
		if ( $exemption_applied instanceof WT_Exemption_Certificate )
			return $exemption_applied;
		else
			return null;
	}
	
	/**
	 * Can we issue a Lookup for this order?
	 *
	 * Three conditions must be met:
	 * - At least one origin address is added to the site
	 * - The customer's full address is available
	 * - The order has not already been captured
	 *
	 * @since 4.2
	 *
	 * @return bool
	 */
	private function ready_for_lookup() {		
		// Check for orders that are already captured
		if ( WT_Orders::get_meta( $this->order_id, 'captured' ) ) {
			return false;
		}

		// Verify that one origin address (at least) is available for use
		if ( !is_array( WT_Orders::$addresses ) || count( WT_Orders::$addresses ) == 0 ) {
			return false;
		}

		// Check for a valid destinaton address
		if ( !SST_Addresses::is_valid( $this->destination_address ) ) {
			return false;
		}
		
		return true;
	}
	
	/**
	 * Get the customer ID for this order.
	 *
	 * @since 4.2
	 *
	 * @return mixed
	 */
	private function get_customer_id() {
		global $current_user;

		$customer_id = WT_Orders::get_meta( $this->order_id, 'customer_id' );

		// Fetch and/or generate customerID
		if ( $customer_id === false || empty( $customer_id ) ) {
			// Generate new customer id if one isn't associated with the order already
			$current_user_id = $this->order->user_id;

			if ( $current_user_id === false || empty( $current_user_id ) ) {
				$customer_id = wp_generate_password( 32, false );
			} else {
				$user        = get_userdata( $current_user_id );
				$customer_id = $user->user_login;
			}
			
			// Save generated ID
			WT_Orders::update_meta( $this->order_id, 'customer_id', $customer_id );
			
			return $customer_id;
		} else {
			return $customer_id;
		}	
	}
	
	/**
	 * Update shipping/cart tax total for order.
	 *
	 * @since 4.4
	 *
	 * @param string $type "shipping" or "cart."
	 * @param float $new_tax New tax value.
	 */
	private function update_tax_total( $type, $new_tax ) {
		$type = $type == 'cart' ? 'tax' : 'shipping_tax';

		$wootax_key      = $type .'_total';
		$woocommerce_key = '_order_'. $type;

		// Get current tax/tax total
		$tax       = WT_Orders::get_meta( $this->order_id, $wootax_key );
		$tax_total = WT_Orders::get_meta( $this->order_id, $woocommerce_key );

		// Calculate new tax total
		$new_tax_total = $tax_total == 0 ? $new_tax : ( $tax_total - $tax ) + $new_tax;
		$new_tax_total = $new_tax_total < 0 ? 0 : $new_tax_total;

		WT_Orders::update_meta( $this->order_id, $woocommerce_key, $new_tax_total );
		WT_Orders::update_meta( $this->order_id, $wootax_key, $new_tax );
	}

	/**
	 * Updates the cart tax/shipping tax totals for the order.
	 * 
	 * @since 4.2
	 *
	 * @param double $cart_tax Cart tax total.
	 * @param double $shipping_tax Shipping tax total.
	 */
	private function update_tax_totals( $cart_tax = 0.0, $shipping_tax = 0.0 ) {
		$this->update_tax_total( 'cart', $cart_tax );
		$this->update_tax_total( 'shipping', $shipping_tax );
		$this->update_tax_item( $cart_tax, $shipping_tax );		
	}
	
	/**
	 * Return the ID of the WooTax tax item.
	 *
	 * @since 4.6
	 *
	 * @return int|null
	 */
	private function find_tax_item() {
		$tax_item_id = NULL;

		// Find first rate with matching rate id; set $tax_item_id accordingly
		foreach ( $this->order->get_taxes() as $key => $data ) {
			if ( $data[ 'rate_id' ] == SST_RATE_ID ) {
				$tax_item_id = $key;
				break;
			}
		}

		WT_Orders::update_meta( $this->order_id, 'tax_item_id', $tax_item_id );
		return $tax_item_id;
	}

	/**
	 * Update cart/shipping tax totals for our tax item.
	 *
	 * @since 4.2
	 *
	 * @param double $cart_tax Cart tax total.
	 * @param double $shipping_tax Shipping tax total.
	 */
	private function update_tax_item( $cart_tax, $shipping_tax ) {
		global $wpdb;

		// Add a new tax item if necessary
		$tax_item_id = WT_Orders::get_meta( $this->order_id, 'tax_item_id' );

		if ( $tax_item_id == 0 || $tax_item_id == NULL ) {
			$tax_item_id = $this->find_tax_item();

			if ( ! $tax_item_id ) {
				$wpdb->insert( "{$wpdb->prefix}woocommerce_order_items", array(
					'order_item_type' => 'tax', 
					'order_item_name' => apply_filters( 'wootax_rate_code', 'WOOTAX-RATE-DO-NOT-REMOVE' ), 
					'order_id'        => $this->order_id,
				) );

				$tax_item_id = $wpdb->insert_id;
			}

			WT_Orders::update_meta( $this->order_id, 'tax_item_id', $tax_item_id );
		}

		// Update tax item meta
		wc_update_order_item_meta( $tax_item_id, 'rate_id', SST_RATE_ID );
		wc_update_order_item_meta( $tax_item_id, 'label', SST()->get_rate_label( SST_RATE_ID ) );
		wc_update_order_item_meta( $tax_item_id, 'name', SST()->get_rate_label( SST_RATE_ID ) );
		wc_update_order_item_meta( $tax_item_id, 'compound', true );
		wc_update_order_item_meta( $tax_item_id, 'tax_amount', $cart_tax );
		wc_update_order_item_meta( $tax_item_id, 'shipping_tax_amount', $shipping_tax );

		do_action( 'wt_tax_item_updated', $tax_item_id, $cart_tax, $shipping_tax );
	}
	
	/**
	 * Fetch shipping method for order.
	 *
	 * @since 4.2
	 *
	 * @return string
	 */
	private function get_shipping_method() {
		if ( isset( $_POST[ 'shipping_methods' ] ) && !empty( $_POST[ 'shipping_methods' ] ) ) {
			$shipping_methods = !strstr( $_POST[ 'shipping_methods' ], ',' ) ? $_POST[ 'shipping_methods' ] : explode( ',', $_POST[ 'shipping_methods' ] );

			// If there are multiple methods, return the first
			return is_array( $shipping_methods ) ? $shipping_methods[0] : $shipping_methods;
		} else {
			return $this->order->get_shipping_method();
		}
	}
	
	/**
	 * Get order status (Pending Capture, Captured, Returned).
	 *
	 * @since 4.2
	 *
	 * @return string
	 */
	public function get_status() {
		if ( WT_Orders::get_meta( $this->order_id, 'refunded' ) == true ) {
			return 'Refunded';
		}

		if ( WT_Orders::get_meta( $this->order_id, 'captured' ) == false ) {
			return 'Pending Capture';
		} else {
			return 'Captured';
		}
	}
}