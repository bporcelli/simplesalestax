<?php

/**
 * Responsible for determining the sales tax due during checkout and updating order totals accordingly
 *
 * @package WooCommerce TaxCloud
 * @since 4.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Prevent data leaks
}

class WC_WooTax_Checkout {
	/** WC_Cart */
	private $cart;

	/** Customer destination address */
	private $destination_address = array();

	/** WC_WooTax_TaxCloud object */	
	private $taxcloud;

	/** Array of business addresses entered by the store owner */
	private $addresses;

	/** Integer index of the "default" business address */
	private $default_address = 0;

	/** Applied taxes */
	private $cart_taxes     = array();
	private $shipping_taxes = array();

	/** OrderIDs/CartIDs associated with Lookup requests */
	private $taxcloud_ids = array();

	/** Array mapping item identifiers to the index of the location from which they are being sent */
	private $location_mapping_array = array();

	/** Address key that fees and shipping costs are associated with */
	private $first_found = 0;

	/** Array containing item IDs sent to TaxCloud */
	private $identifiers = array();

	/** Tax totals */
	private $shipping_tax_total = 0;
	private $tax_total          = 0;

	/** Empty string if Lookup has not been sent yet; otherwise, combined hash of address info and item info */
	private $lookup_sent = "";

	/** Are we handling a renewal order? If so, use unique TaxCloud IDs */
	private $is_renewal = false;

	/** Are we handling a order containing a subscription? */
	private $is_subscription = false;

	/**
	 * Constructor: Starts Lookup and hooks into WooCommerce
	 *
	 * @since 4.2
	 */
	public function __construct() {
		if ( WT_SUBS_ACTIVE && WC_Subscriptions_Cart::cart_contains_subscription() ) {
			$this->is_subscription = true;

			// Restore shipping taxes array for orders containing subscriptions
			add_filter( 'woocommerce_calculated_total', array( $this, 'store_shipping_taxes' ), 10, 2 );
			add_action( 'woocommerce_cart_updated', array( $this, 'restore_shipping_taxes' ) ); // Hook for 2.3: woocommerce_after_calculate_totals

			// Set is_renewal flag if subscriptions is calculating the recurring order total
			if ( WC_Subscriptions_Cart::get_calculation_type() == 'recurring_total' ) {
				$this->is_renewal = true;
			} else {
				$this->is_renewal = false;
			}
		}

		$this->cart      = WC()->cart;
		$this->taxcloud  = TaxCloud();
		$this->addresses = fetch_business_addresses();

		$this->init();
	}

	/**
	 * Initiates a tax lookup
	 *
	 * @since 4.2
	 */
	private function init() {
		// Set customer destination address
		$this->destination_address = $this->get_destination_address();

		// Exit immediately if we do not have enough information to proceed with a lookup
		if( !$this->ready_for_lookup() ) {
			return;
		} else {
			// Load data about applied tax from session if possible
			$this->load_data_from_session();

			// Generate lookup data
			$this->generate_lookup_data();

			if ( $this->needs_lookup() ) {
				// Issue lookup request and update cart tax totals
				$this->do_lookup();
			} else {
				// We need to manually restore the item taxes when lookup is skipped; WooCommerce removes them
				$this->restore_order_taxes();
			}
		}
	}

	/**
	 * Load data about applied taxes and cartIDs/orderIDs associated with past Lookups if possible
	 *
	 * @since 4.2
	 */
	public function load_data_from_session() {
		if ( WC()->session instanceof WC_Session_Handler ) {
			if ( isset( WC()->session->cart_taxes ) ) {
				$this->cart_taxes = WC()->session->cart_taxes;
			}

			if ( isset( WC()->session->shipping_taxes ) ) {
				$this->shipping_taxes = WC()->session->shipping_taxes;
			}

			if ( isset( WC()->session->taxcloud_ids ) ) {
				$this->taxcloud_ids = WC()->session->taxcloud_ids;
			}

			if ( isset( WC()->session->wootax_lookup_sent ) ) {
				$this->lookup_sent = WC()->session->wootax_lookup_sent;
			}

			if ( isset( WC()->session->mapping_array ) ) {
				$this->mapping_array = WC()->session->mapping_array;
			}

			if ( isset( WC()->session->lookup_data ) ) {
				$this->lookup_data = WC()->session->lookup_data;
			}
		} 
	}

	/**
	 * Saves session data after a lookup request is made
	 *
	 * @since 4.2
	 */
	public function save_session_data() {
		// For a renewal order lookup, do not update session data
		if ( WC()->session instanceof WC_Session_Handler && !$this->is_renewal ) {
			WC()->session->location_mapping_array    = $this->location_mapping_array;
			WC()->session->mapping_array             = $this->mapping_array;
			WC()->session->lookup_data               = $this->lookup_data;
			WC()->session->taxcloud_ids              = $this->taxcloud_ids;
			WC()->session->cart_taxes                = $this->cart_taxes;
			WC()->session->shipping_taxes            = $this->shipping_taxes;
			WC()->session->wootax_tax_total          = $this->tax_total;
			WC()->session->wootax_shipping_tax_total = $this->shipping_tax_total;
			WC()->session->item_ids                  = $this->identifiers;
			WC()->session->first_found_key           = $this->first_found;
			WC()->session->wootax_lookup_sent        = $this->lookup_sent;
		}
	}
	
	/**
	 * Removes the tax applied by WooTax from fees, cart items, and shipping
	 * 
	 * @since 4.2
	 */
	public function remove_tax() {
		$this->remove_item_taxes();
		$this->remove_fee_taxes();
		$this->update_tax_totals();

		$this->cart_taxes = array();
	}

	/**
	 * Removes tax applied by WooTax from all cart items
	 *
	 * @since 4.2
	 */
	public function remove_item_taxes() {
		if ( is_array( $this->cart->cart_contents ) ) {
			foreach ( $this->cart->cart_contents as $key => $data ) {
				$this->remove_item_tax( $key );
			}
		}

		if ( isset( $this->cart->taxes[ WT_RATE_ID ] ) ) {
			unset( $this->cart->taxes[ WT_RATE_ID ] );
		}
	}

	/**
	 * Removes tax applied by WooTax from all fees
	 *
	 * @since 4.2
	 */
	public function remove_fee_taxes() {
		if ( is_array( $this->cart->fees ) ) {
			foreach ( $this->cart->fees as $ind => $fee ) {
				$this->remove_fee_tax( $ind );
			}
		}
	}
	
	/**
	 * Gets the total tax applied to a single item 
	 *
	 * @since 4.2
	 * @param (mixed) item cart key of the item
	 * @return (float) zero if tax cannot be retrieved or tax amount
	 */
	public function get_item_tax( $key ) {
		// Exit if the taxes array is not defined
		if ( sizeof( $this->cart_taxes ) == 0 ) {
			return 0.0;
		}

		// Get tax amount if possible; otherwise, return zero by default
		if ( isset( $this->cart_taxes[ $key ] ) ) {
			$tax = $this->cart_taxes[ $key ];
		} else {
			$tax = 0.0;
		}

		return $tax;
	}
	
	/**
	 * Applies tax to the item with given key 
	 *
	 * @since 4.2
	 * @param (string) $key cartItem key
	 * @param (float) $amt the tax to apply 
	 */
	private function apply_item_tax( $key, $amt ) {
		// Update tax values
		$this->cart->cart_contents[ $key ]['line_tax'] += $amt;
		$this->cart->cart_contents[ $key ]['line_subtotal_tax'] += $amt;

		// Add the "tax_data" array if we are dealing with WooCommerce 2.2+
		if ( version_compare( WOOCOMMERCE_VERSION, '2.2', '>=' ) ) {
			$tax_data = $this->cart->cart_contents[ $key ]['line_tax_data'];

			if ( !isset( $tax_data['total'][ WT_RATE_ID ] ) ) {
				$tax_data['total'][ WT_RATE_ID ] = 0;
			}

			if ( !isset( $tax_data['subtotal'][ WT_RATE_ID ] ) ) {
				$tax_data['subtotal'][ WT_RATE_ID ] = 0;
			}

			$tax_data['subtotal'][ WT_RATE_ID ] += $amt;
			$tax_data['total'][ WT_RATE_ID ]    += $amt;

			$this->cart->cart_contents[ $key ]['line_tax_data'] = $tax_data;
		}
	}
	
	/**
	 * Remove tax applied by WooTax from an item
	 * 
	 * @since 4.2
	 * @param (string) $key the cartItem key
	 */
	private function remove_item_tax( $key ) {
		// Exit if the taxes array is not defined
		if ( !$this->cart_taxes || sizeof( $this->cart_taxes ) == 0 ) {
			return;
		}

		// Remove taxes during checkout
		if ( isset( $this->cart->cart_contents[ $key ] ) ) {
			$applied_tax = $this->get_item_tax( $key );

			// Calculate new tax values
			$line_tax = $this->cart->cart_contents[ $key ]['line_tax'] - $applied_tax;
			$line_subtotal_tax = $this->cart->cart_contents[ $key ]['line_subtotal_tax'] - $applied_tax;
			
			// Zero tax if the calculated value is negative
			$line_tax = $line_tax < 0 ? 0 : $line_tax;
			$line_subtotal_tax = $line_subtotal_tax < 0 ? 0 : $line_subtotal_tax;

			// Update tax
			$this->cart->cart_contents[ $key ]['line_tax'] = $line_tax;
			$this->cart->cart_contents[ $key ]['line_subtotal_tax'] = $line_subtotal_tax;

			// Update the "tax_data" array if we are dealing with WooCommerce 2.2+
			if ( version_compare( WOOCOMMERCE_VERSION, '2.2', '>=' ) ) {
				$tax_data = $this->cart->cart_contents[ $key ]['line_tax_data'];

				$tax_data['total'][ WT_RATE_ID ]    = $line_tax;
				$tax_data['subtotal'][ WT_RATE_ID ] = $line_subtotal_tax;

				$this->cart->cart_contents[ $key ]['line_tax_data'] = $tax_data;
			}
		}
	}
	
	/**
	 * Apply tax to a fee
	 *
	 * @since 4.2
	 * @param (int) $key the fee index
	 * @param (float) $amt the tax amount to be applied
	 */
	private function apply_fee_tax( $key, $amt ) {
		// Update tax value
		$this->cart->fees[ $key ]->tax += $amt;

		// Update tax_data array if we are dealing with WooCommerce 2.2+
		if ( version_compare( WOOCOMMERCE_VERSION, '2.2', '>=' ) ) {
			$tax_data = $this->cart->fees[ $key ]->tax_data;

			if ( !isset( $tax_data[ WT_RATE_ID ] ) ) {
				$tax_data[ WT_RATE_ID ] = 0;
			}

			$tax_data[ WT_RATE_ID ] += $amt;

			// Set new tax_data 
			$this->cart->fees[ $key ]->tax_data = $tax_data;
		}
	}
	
	/**
	 * Remove tax from a fee
	 *
	 * @since 4.2
	 * @param (int) $key the fee index
	 */
	private function remove_fee_tax( $key ) {
		// Exit if the taxes array is not defined
		if ( !$this->cart_taxes || sizeof( $this->cart_taxes ) == 0 ) {
			return;
		}
		
		// Remove tax if necessary
		if ( isset( $this->cart->fees[ $key ] ) ) {
			$applied_tax = $this->get_item_tax( $key );

			// Calculate new tax value
			$line_tax = $this->cart->fees[ $key ]->tax - $applied_tax;
			$line_tax = $line_tax < 0 ? 0 : $line_tax;
			
			// Update tax
			$this->cart->fees[ $key ] ->tax = $line_tax;

			// Update tax_data array if we are dealing with WooCommerce 2.2+
			if ( version_compare( WOOCOMMERCE_VERSION, '2.2', '>=' ) ) {
				$tax_data = $this->cart->fees[ $key ]->tax_data;

				$tax_data[ WT_RATE_ID ] = $line_tax;

				$this->cart->fees[ $key ]->tax_data = $tax_data;
			}
		}
	}

	/**
	 * Generate an order ID for each lookup
	 *
	 * @since 4.2
	 */
	private function generate_order_ids() {
		$taxcloud_ids = $this->taxcloud_ids;

		foreach ( $this->lookup_data as $location => $items ) {
			// Always generate order IDs for a renewal order
			if ( !isset( $taxcloud_ids[ $location ] ) || $this->is_renewal ) {
				$taxcloud_ids[ $location ] = array(
					'cart_id'  => '',
					'order_id' => wootax_generate_order_id(),
				);
			}
		}

		$this->taxcloud_ids = $taxcloud_ids;
	}

	/**
	 * Returns an array of order items in a TaxCloud friendly format
	 *
	 * array(
	 *   [item_key] => array (
	 *		[TIC] => TIC or empty string
	 * 	   	[ItemID] => Product ID
	 * 		[Index] => Item index (can be left blank for our purposes; re-assigned in generate_lookup_data)
	 * 		[Type] => Item type (Values: cart, fee, shipping)
	 * 		[Price] => The item price
	 * 		[Qty] => The quantity of the item being purchased 
	 *	)
	 * )
	 *
	 * @since 4.2
	 * @return (array) array of items (see above for structure)
	 */
	private function get_items_array() {
		$items       = $this->cart->cart_contents;
		$based_on    = WC_WooTax::get_option( 'tax_based_on' );
		$final_items = array();

		// Add cart items
		foreach ( $items as $item_key => $item ) {
			$product = $item['data'];

			// Get product TIC
			$tic_raw = get_post_meta( $product->id, 'wootax_tic', true );
			$tic     = $tic_raw == false ? '' : trim( $tic_raw );

			// Get product ID, Qty, and Price 
			$item_id       = $item_key;
			$product_price = $item['line_total'] / $item['quantity'];

			if ( $based_on == 'item-price' || !$based_on ) {
				$qty   = $item['quantity'];
				$price = $product_price; 
			} else {
				$qty   = 1;
				$price = $product_price * $item['quantity']; 
			}

			// Add to final items array
			$final_items[ $item_key ] = array(
				'Index'  => NULL,
				'ItemID' => $item_id,
				'Price'  => $price,
				'Qty'    => $qty,
				'Type'   => 'cart',
			);

			// Only add TIC if it is set
			if ( !empty( $tic ) ) {
				$final_items[ $item_key ]['TIC'] = $tic;
			}
		}

		// Add fees
		$fees = $this->cart->get_fees();

		if ( is_array( $fees ) ) {
			foreach ( $fees as $ind => $fee ) {
				// Skip if not taxable (TODO: phase this out too?)
				if ( isset( $fee->taxable ) && !$fee->taxable ) {
					continue;
				}
				
				// Get fee identifier (fee index since we are in checkout)
				$fee_id = $fee->id;

				// Get fee amount $fee->amount
				$amt = $fee->amount;

				// Add to final items array
				$final_items[ $fee_id ] = array(
					'Index'  => NULL, 
					'ItemID' => $fee_id, 
					'TIC'    => WT_FEE_TIC,
					'Qty'    => 1, 
					'Price'  => $amt,
					'Type'   => 'fee',
				);
			}
		}

		// Add shipping costs
		$shipping_total = $this->cart->shipping_total;

		if ( $this->is_subscription ) {
			if ( !WC_Subscriptions_Cart::charge_shipping_up_front() && ( WC_Subscriptions_Cart::get_calculation_type() == 'sign_up_fee_total' || WC_Subscriptions_Cart::get_calculation_type() == 'free_trial_total' ) ) {
				$shipping_total = 0;
			}
		}

		if ( $shipping_total > 0 ) {
			// Add a shipping item to the final items array (we assume that only one shipping method is being used per order)
			$final_items[ WT_SHIPPING_ITEM ] = array(
				'Index'  => NULL, 
				'ItemID' => WT_SHIPPING_ITEM, 
				'TIC'    => WT_SHIPPING_TIC, 
				'Qty'    => 1, 
				'Price'  => $shipping_total,
				'Type'   => 'shipping',
			);
		}

		return $final_items;
	}
	
	/**
	 * Stores an array of items in TaxCloud-friendly format and organized by location key in the lookup_data property
	 *
	 * @since 4.2
	 */
	private function generate_lookup_data() {
		// Fetch order items
		$order_items = $this->get_items_array();

		// Exit if we do not have any items
		if ( count( $order_items ) == 0 ) {
			$this->lookup_data = $this->mapping_array = array();
			return;
		}

		// Determine the state where the customer is located
		$customer_state = $this->destination_address['State'];

		// Initialize some vars that we need below
		$data = $mapping_array = $counters_array = $fee_items = $fee_indices = $shipping_items = array();
		$fee_counter = 0;

		// This will hold the ID of the first found origin address/location for this order; Fees and shipping charges will be attached to it
		$first_found = false;

		// Loop through order items; group items by their shipping origin address and format data for tax lookup
		foreach ( $order_items as $item_key => $item ) {
			$item_id = $item['ItemID'];
			$type    = $item['Type'];

			switch( $type ) {
				case 'cart':
					// Fetch shipping origin addresses for this product
					$product          = $this->cart->cart_contents[ $item_id ]['data'];
					$product_id       = $product->id;
					$origin_addresses = fetch_product_origin_addresses( $product_id );
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
							if ( isset( $this->addresses[ $key ]['state'] ) && $this->addresses[ $key ]['state'] == $customer_state ) {
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

					// Update mapping array
					$mapping_array[ $address_found ][] = array(
						'id'    => $item_id, 
						'index' => $counters_array[ $address_found ], 
						'type'  => 'cart',
						'key'   => $item_key,
					);

					// Update item data before storing in $data array
					$item['Index'] = $counters_array[ $address_found ];
					$item['Price'] = apply_filters( 'wootax_taxable_price', $item['Price'], true, $product->id );
					
					unset( $item['Type'] );

					// Add item to lookup data arary
					$data[ $address_found ][] = $item;

					// Increment counter
					$counters_array[ $address_found ]++;
				break;

				case 'shipping':
					// Push this item the shipping array; the cost of shipping will be attached to the first daughter order later on
					$shipping_items[ $item_id ] = $item;
				break;

				case 'fee':
					// Push this item to the fee array; it will be attached to the first daughter order later on
					$fee_items[ $item_id ] = $item;

					// Map item id to fee index 
					$fee_indices[ $item_id ] = $fee_counter++;
				break;
			}
		}

		// Attach shipping items and fees to the first daughter order
		if ( $first_found !== false ) {
			foreach ( $shipping_items + $fee_items as $key => $item ) {
				// Get new item index
				$index   = $counters_array[ $first_found ];
				$item_id = $item['ItemID'];

				// Determine if the item we are dealing with is a fee or shipping charge
				$type = isset( $fee_items[ $item_id ] ) ? 'fee' : 'shipping';

				// Add to items array (Type index not included here)
				$data[ $first_found ][ $index ] = array(
					'Index'  => $index,
					'ItemID' => $item_id,
					'TIC'    => $item['TIC'],
					'Price'  => apply_filters( 'wootax_taxable_price', $item['Price'], true, $item_id ),
					'Qty'    => $item['Qty'],
				);

				// Update mapping array
				$mapping_array[ $first_found ][ $index ] = array(
					'id'    => $item_id, 
					'index' => $index, 
					'type'  => $type,
				);

				// Add "index" property with fee index if necessary
				if ( $type == 'fee' ) {
					$mapping_array[ $first_found ][ $index ]['index'] = $fee_indices[ $item_id ];
				}

				// Increment counter
				$counters_array[ $first_found ]++;
			}
		}

		// Store data and save
		$this->lookup_data   = $data;
		$this->mapping_array = $mapping_array;
		$this->first_found   = $first_found;

		$this->generate_order_ids();
	}

	/**
	 * Perform a tax lookup for the user's cart
	 *
	 * @since 4.2
	 */
	public function do_lookup() {
		// Remove currently applied taxes
		$this->remove_tax();

		// Retrieve validated destination addresses
		$destination_address = maybe_validate_address( $this->destination_address );

		// Define some variable used in every tax lookup
		$tax_array          = array();
		$lookup_data        = array();
		$tax_total          = 0;
		$shipping_tax_total = 0;

		// Fetch some information about this order
		$exempt_cert         = $this->get_exemption_certificate();
		$customer_id         = $this->get_customer_id();
		$delivered_by_seller = wt_is_local_delivery( $this->get_shipping_method() );

		// Loop through locations in lookup_data array and send a Lookup request for each
		foreach ( $this->lookup_data as $location_key => $items ) {
			$lookup_data[ $location_key ] = $items;

			// Fetch cart_id
			$cart_id = isset( $this->taxcloud_ids[ $location_key ] ) ? $this->taxcloud_ids[ $location_key ]['cart_id'] : '';

			// Get the origin address
			$origin_address = wootax_get_address( $location_key );

			// Build Lookup request (cartID empty for first request)
			$req = array(
				'customerID'        => $customer_id, 
				'cartID'            => $cart_id, 
				'cartItems'         => $items, 
				'origin'            => $origin_address, 
				'destination'       => $destination_address, 
				'deliveredBySeller' => $delivered_by_seller,
			);

			if ( !empty( $exempt_cert ) ) {
				$req['exemptCert'] = $exempt_cert;
			}

			// Send Lookup request 
			$res = $this->taxcloud->send_request( 'Lookup', $req );

			if ( $res !== false ) {
				// Initialize some vars
				$cart_items = $res->CartItemsResponse->CartItemResponse;

				// Store cartID to improve efficiency of subsequent lookup requests
				$this->taxcloud_ids[ $location_key ]['cart_id']  = $res->CartID;

				// If cart_items only contains one item, it will not be an array.
				// In that case, convert to an array to avoid the need for extra code
				if ( !is_array( $cart_items ) ) {
					$cart_items = array( $cart_items );
				}

				// Loop through items and update tax amounts
				foreach ( $cart_items as &$cart_item ) {
					// Fetch item info
					$index = $cart_item->CartItemIndex;
					$tax   = $cart_item->TaxAmount;
					$type  = $this->mapping_array[ $location_key ][ $index ]['type'];

					// Update item tax
					switch ( $type ) {
						case 'cart':
							$item_id = $this->mapping_array[ $location_key ][ $index ]['key']; // Identifier is cart item key
							$this->apply_item_tax( $item_id, $tax );
							$tax_total += $tax;
							break;

						case 'shipping':
							$item_id = WT_SHIPPING_ITEM; // Identifier is SHIPPING
							$shipping_tax_total += $tax;
							break;

						case 'fee':
							$item_id = $this->mapping_array[ $location_key ][ $index ]['index']; // Identifier is cart fee index
							$this->apply_fee_tax( $item_id, $tax );
							$tax_total += $tax;
							break;
					}
					
					// Add item tax to cart_taxes array
					$this->cart_taxes[ $item_id ] = $tax;

					// Map item id to location
					$this->location_mapping_array[ $item_id ] = $location_key;
				}
			} else {
				// We will not display ZIP mismatch errors or SoapFaults. These messages tend to be disruptive and there is not much the customer can do to resolve them.
				$error = $this->taxcloud->get_error_message();

				if ( strpos( $error, 'zip' ) === false && strpos( $error, 'SoapFault' ) === false ) {			
					wc_add_notice( 'An error occurred while calculating the tax for this order. '. $error, 'error' );
				}
				
				return;
			}
		}

		// Update item identifiers
		$this->update_item_identifiers();

		// Save updated tax totals
		$this->update_tax_totals( $tax_total, $shipping_tax_total );

		// Set value of lookup_sent to prevent duplicate lookups
		$this->lookup_sent = $this->get_order_hash();
		
		// Save checkout data in session
		$this->save_session_data();
	}
	
	/**
	 * Update item identifiers
	 *
	 * @since 4.2
	 */
	private function update_item_identifiers() {
		$identifiers = array();

		// Add cart items
		foreach ( $this->cart->cart_contents as $cart_key => $data ) {
			$product_id = !empty( $data['variation_id'] ) ? $data['variation_id'] : $data['product_id'];
			$identifiers[ $product_id ] = $cart_key;
		}

		// Add any fees
		foreach ( $this->cart->fees as $fee_index => $data ) {
			$key = sanitize_title( $data->name );
			$identifiers[ $key ] = $key;
		}

		// Add shipping
		$identifiers[ WT_SHIPPING_ITEM ] = WT_SHIPPING_ITEM;

		$this->identifiers = $identifiers;
	}

	/**
	 * Gets information about applied exemption certificate from session
	 * Returns NULL if no exemption is applied
	 *
	 * @since 4.2
	 * @return (mixed) array of cert data for single certs, ID for blanket certs, or NULL if no cert is applied
	 */
	private function get_exemption_certificate() {
		$certificate_data = NULL;
		
		if ( !empty( WC()->session->certificate_id ) ) {
			if ( WC()->session->certificate_id == 'true' ) {
				$certificate_data = WC()->session->certificate_data;

				if ( !isset( $certificate_data['Detail']['SinglePurchaseOrderNumber'] ) ) {
					$certificate_data['Detail']['SinglePurchaseOrderNumber'] = wootax_generate_order_id();
				}
				
				WC()->session->certificate_data = $certificate_data;
			} else {
				$certificate_data = array(
					'CertificateID' => WC()->session->certificate_id,
				);
			}
		}

		return $certificate_data;
	}
	
	/** 
	 * Get destination address for order
	 *
	 * @since 4.2
	 * @return (array) associative array containing customer address
	 */
	public function get_destination_address() {
		// Retrieve "tax based on" option
		$tax_based_on = get_option( 'woocommerce_tax_based_on' );

		// Return origin address if this is a local pickup order
		if ( wt_is_local_pickup( $this->get_shipping_method() ) || $tax_based_on == 'base' ) {
			return wootax_get_address( apply_filters( 'wootax_pickup_address', WT_DEFAULT_ADDRESS, $this->addresses, -1 ) );
		}

		// Initialize blank address array
		$address = array();

		// Fetch BOTH billing and shipping address
		$addresses = array(
			'billing' => array(
				'address_1' => WC()->customer->get_address(),
				'address_2' => WC()->customer->get_address_2(),
				'country'   => WC()->customer->get_country(),
				'state'     => WC()->customer->get_state(),
				'city'      => WC()->customer->get_city(),
				'zip5'      => WC()->customer->get_postcode(),
			),
					
			'shipping' => array(
				'address_1' => WC()->customer->get_shipping_address(),
				'address_2' => WC()->customer->get_shipping_address_2(),
				'country'   => WC()->customer->get_shipping_country(),
				'state'     => WC()->customer->get_shipping_state(),
				'city'      => WC()->customer->get_shipping_city(),
				'zip5'      => WC()->customer->get_shipping_postcode(),
			)
		);
		
		// Attempt to fetch address preferred according to "tax based on" option; otherwise, return billing address
		$address_1 = $addresses['billing']['address_1'];
		$address_2 = $addresses['billing']['address_2'];
		$country   = $addresses['billing']['country'];
		$state     = $addresses['billing']['state'];
		$city      = $addresses['billing']['city'];
		$zip5      = $addresses['billing']['zip5'];
				
		if ( $tax_based_on ) {
			$address_1 = !empty( $addresses[$tax_based_on]['address_1'] ) ? $addresses[$tax_based_on]['address_1'] : $address_1;
			$address_2 = !empty( $addresses[$tax_based_on]['address_2'] ) ? $addresses[$tax_based_on]['address_2'] : $address_2;
			$country   = !empty( $addresses[$tax_based_on]['country'] ) ? $addresses[$tax_based_on]['country'] : $country;
			$state     = !empty( $addresses[$tax_based_on]['state'] ) ? $addresses[$tax_based_on]['state'] : $state;
			$city      = !empty( $addresses[$tax_based_on]['city'] ) ? $addresses[$tax_based_on]['city'] : $city;
			$zip5      = !empty( $addresses[$tax_based_on]['zip5'] ) ? $addresses[$tax_based_on]['zip5'] : $zip5;
		} 
		
		// Build/return Address array
		$parsed_zip = parse_zip( $zip5 );

		$address['Address1'] = $address_1;
		$address['Address2'] = $address_2;
		$address['Country']  = $country;
		$address['State']    = $state;
		$address['City']     = $city;
		$address['Zip5']     = $parsed_zip['zip5'];
		$address['Zip4']     = $parsed_zip['zip4']; 

		return $address;
	}
	
	/** 
	 * Determines if an order is ready for a Lookup request
	 * For an order to be "ready," two criteria must be met:
	 * - At least one origin address is added to the site
	 * - The customer's full address is available (and in the United States)
	 *
	 * @since 4.2
	 * @return (boolean) true if the order is ready for a tax lookup; otherwise, false
	 */
	private function ready_for_lookup() {
		// Verify that one origin address (at least) is available for use
		if ( !is_array( $this->addresses ) || count( $this->addresses ) == 0 ) {
			return false;
		}

		// Check for a valid destinaton address
		if ( !$this->taxcloud->is_valid_address( $this->destination_address, true ) ) {
			return false;
		}
		
		return true;
	}

	/**
	 * Get customerID for order and store it in the session
	 * 
	 * @since 4.2
	 */
	private function get_customer_id() {
		global $current_user;

		if ( is_user_logged_in() ) {
			$customer_id = $current_user->user_login;
		} else {
			$customer_id = WC()->session->get_customer_id();
		}

		WC()->session->wootax_customer_id = $customer_id;

		return $customer_id;
	}

	/**
	 * Update order shipping/cart tax total
	 *
	 * @since 4.4
	 * @param (string) $type "shipping" to update shipping tax total; "cart" to update cart tax total
	 * @param (float) $new_tax new value for tax
	 */
	private function update_tax_total( $type, $new_tax ) {
		if ( $type == 'shipping' ) {
			$tax_key   = 'shipping_taxes';
			$total_key = 'shipping_tax_total';
		} else {
			$tax_key   = 'taxes';
			$total_key = 'tax_total';
		}

		$this->cart->{$tax_key}[ WT_RATE_ID ] = $new_tax;

		if ( !WT_SUBS_ACTIVE || !WC_Subscriptions_Cart::cart_contains_subscription() ) { // Removing zero tax row causes display issues for subscription orders
			if ( WC_WooTax::get_option( 'show_zero_tax' ) != 'true' && $new_tax == 0 ) {
				unset( $this->cart->{$tax_key}[ WT_RATE_ID ] );
			}
		}

		// Use get_tax_total to set new tax total so we don't override other rates
		$this->cart->$total_key = version_compare( WOOCOMMERCE_VERSION, '2.2', '>=' ) ? WC_Tax::get_tax_total( $this->cart->$tax_key ) : $this->cart->tax->get_tax_total( $this->cart->$tax_key );

		$this->$total_key = $new_tax;
	}

	/**
	 * Updates the cart tax/shipping tax totals for the order
	 *
	 * @since 4.2
	 * @param (float) $cart_tax the total cart tax added by WooTax
	 * @param (float) $shipping_tax the total shipping tax added by WooTax
	 */
	private function update_tax_totals( $cart_tax = 0, $shipping_tax = 0 ) {	
		$this->update_tax_total( 'cart', $cart_tax );
		$this->update_tax_total( 'shipping', $shipping_tax );
	}
	
	/**
	 * Fetch order shipping method
	 * Chosen method is retrieved from the session var chosen_shipping_method (WC < 2.1) OR chosen_shipping_methods (WC 2.1+)
	 *
	 * @since 4.2
	 * @return chosen shipping method (string)
	 */
	private function get_shipping_method() {
		if ( isset( WC()->session->chosen_shipping_method ) ) {
			return WC()->session->chosen_shipping_method;
		} else if ( isset( WC()->session->chosen_shipping_methods ) ) {
			return WC()->session->chosen_shipping_methods[0];
		} else {
			return false;
		}
	}

	/**
	 * Set order taxes from session if a Lookup is skipped
	 *
	 * @since 4.4
	 */
	private function restore_order_taxes() {
		$tax_total = $shipping_tax_total = 0;

		foreach ( $this->lookup_data as $location_key => $items ) {
			foreach ( $items as $index => $item ) {
				$type = $this->mapping_array[ $location_key ][ $index ]['type'];

				// Update item taxes
				switch ( $type ) {
					case 'cart':
						$item_id = $this->mapping_array[ $location_key ][ $index ]['key']; // Identifier is cart item key
						
						$tax = $this->cart_taxes[ $item_id ];

						$this->apply_item_tax( $item_id, $tax );
						$tax_total += $tax;
						break;

					case 'shipping':
						$item_id = WT_SHIPPING_ITEM; // Identifier is SHIPPING
						
						$tax = $this->cart_taxes[ $item_id ];

						$shipping_tax_total += $tax;
						break;

					case 'fee':
						$item_id = $this->mapping_array[ $location_key ][ $index ]['index']; // Identifier is cart fee index
						
						$tax = $this->cart_taxes[ $item_id ];

						$this->apply_fee_tax( $item_id, $tax );
						$tax_total += $tax;
						break;
				}
			}
		}

		// Update tax totals
		$this->update_tax_totals( $tax_total, $shipping_tax_total );
	}

	/**
	 * Determines whether or not a lookup needs to be sent by checking the
	 * value of the wootax_lookup_sent session parameter
	 *
	 * @since 4.4
	 * @return (boolean)
	 */
	private function needs_lookup() {
		if ( empty( $this->lookup_sent ) || $this->is_renewal ) {
			return true;
		} else {
			return !( $this->get_order_hash() == $this->lookup_sent );
		}
	}

	/**
	 * Gets hash representing this unique order
	 * Order is considered unique if it has different items (Lookup data) or destination address
	 *
	 * @since 4.4
	 * @return String
	 */
	private function get_order_hash() {
		return md5( json_encode( $this->get_customer_id() ) . json_encode( $this->destination_address ) . json_encode( $this->lookup_data ) . json_encode( $this->get_exemption_certificate() ) );
	}

	/**
	 * Store cart shipping taxes so they can be restored after Subscriptions does its work
	 *
	 * @since 4.4
	 * @param (double) $total the current cart total
	 * @param (WC_Cart) $cart WC_Cart object
	 */
	public function store_shipping_taxes( $total, $cart ) {
		$this->shipping_taxes = $cart->shipping_taxes;
		return $total;
	}

	/**
	 * Restores the shipping_taxes property of the cart object on woocommerce_after_calculate_totals
	 * Only executed when WooCommerce Subscriptions is active
	 *
	 * @since 4.4
	 * @param (double) $total the current cart total
	 * @param (WC_Cart) $cart a WC_Cart object
	 */
	public function restore_shipping_taxes() {
		// Restore taxes to given cart object
		//$cart->shipping_taxes = $this->shipping_taxes;

		// Restore taxes for global cart object
		WC()->cart->shipping_taxes = $this->shipping_taxes;
	}
}

// Set up checkout object when totals are calculated
function wootax_start_lookup() {
	$checkout = new WC_WooTax_Checkout();
}

add_action( 'woocommerce_calculate_totals', 'wootax_start_lookup' );