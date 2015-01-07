<?php

// Prevent data leaks
if ( ! defined( 'ABSPATH' ) ) exit; 

/**
 * WC_WooTax_Checkout Object
 * Responsible for performing tax Lookups during checkout
 *
 * @package WooTax
 * @since 4.2
 */

class WC_WooTax_Checkout {
	/** 
	 * WC_Cart 
	 */
	public $cart;

	/**
	 * Customer destination address
	 */
	public $destination_address = array();

	/**
	 * Holds the ID of the WooTax tax rate
	 */
	public $wootax_rate_id = '';

	/**
	 * Holds a WC_WooTax_TaxCloud object
	 */	
	public $taxcloud = false;

	/**
	 * Holds an array of business address entered by the user
	 */
	public $addresses = array();

	/**
	 * Holds an integer representing the ID of the "default" business address
	 */
	public $default_address = 0;

	/**
	 * Applied taxes
	 */
	public $cart_taxes = array();

	/**
	 * OrderIDs/CartIDs associated with Lookup requests
	 */
	public $taxcloud_ids = array();

	/**
	 * Array that maps item identifiers to the ID of the location from which they are being sent
	 */
	public $location_mapping_array = array();

	/**
	 * Address key that fees and shipping costs are associated with
	 */
	public $first_found = 0;

	/**
	 * Array containing item IDs that are sent to TaxCloud
	 */
	public $identifiers = array();

	/**
	 * Tax totals
	 */
	public $shipping_tax_total = 0;
	public $tax_total          = 0;
	
	/**
	 * Constructor: Starts Lookup
	 *
	 * @since 4.2
	 */
	public function __construct() {
		$this->init();
	}

	/**
	 * Fetches information about the customer and initiates a tax lookup
	 *
	 * @since 4.2
	 */
	public function init() {

		global $woocommerce;

		// Give inner methods access to WC_Cart object and WooCommerce object
		$this->woo  = &$woocommerce;
		$this->cart = &$this->woo->cart;

		// Instantiate a WC_WooTax_TaxCloud for us to use
		$this->taxcloud = get_taxcloud();

		if ( !$this->taxcloud ) {
			return;
		}

		// Get WooTax tax rate id
		$this->wootax_rate_id = get_option( 'wootax_rate_id' );

		// Fetch configured business addresses and customer address
		$this->default_address     = wootax_get_option( 'default_address' );
		$this->addresses 		   = fetch_business_addresses();
		$this->destination_address = $this->get_destination_address();

		// Exit immediately if we do not have enough information to proceed with a lookup
		if( !$this->ready_for_lookup() ) {
			return;
		}

		// Load data about applied tax from session if possible
		$this->load_data_from_session();

		// Issue lookup request and update cart tax totals
		$this->do_lookup();

	}

	/**
	 * Load data about applied taxes and cartIDs/orderIDs associated with past Lookups if possible
	 *
	 * @since 4.2
	 */
	public function load_data_from_session() {

		if ( $this->woo->session instanceof WC_Session_Handler ) {

			if ( isset( $this->woo->session->wootax_cart_taxes ) ) {
				$this->cart_taxes = $this->woo->session->wootax_cart_taxes;
			}

			if ( isset( $this->woo->session->taxcloud_ids ) ) {
				$this->taxcloud_ids = $this->woo->session->taxcloud_ids;
			}

		} 

	}

	/**
	 * Saves session data after a lookup request is made
	 *
	 * @since 4.2
	 */
	public function save_session_data() {

		if ( $this->woo->session instanceof WC_Session_Handler ) {

			$this->woo->session->location_mapping_array    = $this->location_mapping_array;
			$this->woo->session->mapping_array             = $this->mapping_array;
			$this->woo->session->lookup_data               = $this->lookup_data;
			$this->woo->session->taxcloud_ids              = $this->taxcloud_ids;
			$this->woo->session->cart_taxes                = $this->cart_taxes;
			$this->woo->session->wootax_tax_total          = $this->tax_total;
			$this->woo->session->wootax_shipping_tax_total = $this->shipping_tax_total;
			$this->woo->session->item_ids                  = $this->identifiers;
			$this->woo->session->first_found_key           = $this->first_found;

		}

	}
	
	/**
	 * Removes the tax applied by WooTax from fees, cart items, and shipping
	 * 
	 * @since 4.2
	 */
	public function remove_tax() {

		// Remove item taxes
		$this->remove_item_taxes();

		// Remove fee taxes
		$this->remove_fee_taxes();

		// Update tax totals
		$this->update_tax_totals();

		// Clear applied taxes
		$this->cart_taxes = array();

	}

	/**
	 * Removes tax applied by WooTax from all cart items
	 *
	 * @since 4.2
	 */
	public function remove_item_taxes() {

		// Loop through cart items and deduct applied tax from each
		if ( is_array( $this->cart->cart_contents ) ) {
			foreach ( $this->cart->cart_contents as $key => $data ) {
				$this->remove_item_tax( $key );
			}
		}

		if ( isset( $this->cart->taxes[ $this->wootax_rate_id ] ) ) {
			unset( $this->cart->taxes[ $this->wootax_rate_id ] );
		}

	}

	/**
	 * Removes tax applied by WooTax from all fees
	 *
	 * @since 4.2
	 */
	public function remove_fee_taxes() {

		// Loop through fees and deduct tax from each
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
	 * @param item cart key of the item
	 * @return (int|float) zero if tax cannot be retrieved or tax amount
	 */
	public function get_item_tax( $key ) {

		// Exit if the taxes array is not defined
		if ( sizeof( $this->cart_taxes ) == 0 ) {
			return 0;
		}

		// Get tax amount if possible; otherwise, return zero by default
		if ( isset( $this->cart_taxes[$key] ) ) {
			$tax = $this->cart_taxes[$key];
		} else {
			$tax = 0;
		}

		return $tax;

	}
	
	/**
	 * Applies tax to the item with given key 
	 *
	 * @since 4.2
	 * @param $key cartItem key
	 * @param $amt the tax to apply 
	 */
	private function apply_item_tax( $key, $amt ) {

		// Update tax values
		$this->cart->cart_contents[$key]['line_tax'] += $amt;
		$this->cart->cart_contents[$key]['line_subtotal_tax'] += $amt;

		// Add the "tax_data" array if we are dealing with WooCommerce 2.2+
		if ( version_compare( WOOCOMMERCE_VERSION, '2.2', '>=' ) ) {

			$tax_data = $this->cart->cart_contents[$key]['line_tax_data'];

			if ( !isset( $tax_data['total'][$this->wootax_rate_id] ) ) {
				$tax_data['total'][$this->wootax_rate_id] = 0;
			}

			if ( !isset( $tax_data['subtotal'][$this->wootax_rate_id] ) ) {
				$tax_data['subtotal'][$this->wootax_rate_id] = 0;
			}

			$tax_data['subtotal'][$this->wootax_rate_id] += $amt;
			$tax_data['total'][$this->wootax_rate_id]    += $amt;

			$this->cart->cart_contents[$key]['line_tax_data'] = $tax_data;

		}

	}
	
	/**
	 * Remove tax applied by WooTax from an item
	 * 
	 * @since 4.2
	 * @param $key the cartItem key
	 */
	private function remove_item_tax( $key ) {

		// Exit if the taxes array is not defined
		if ( !$this->cart_taxes || sizeof( $this->cart_taxes ) == 0 ) {
			return;
		}
		
		// Fetch applied tax
		$applied_tax = $this->get_item_tax( $key );

		// Remove taxes during checkout
		if ( isset( $this->cart->cart_contents[$key] ) ) {

			// Calculate new tax values
			$line_tax = $this->cart->cart_contents[$key]['line_tax'] - $applied_tax;
			$line_subtotal_tax = $this->cart->cart_contents[$key]['line_subtotal_tax'] - $applied_tax;
			
			// Zero tax if the calculated value is negative
			$line_tax = $line_tax < 0 ? 0 : $line_tax;
			$line_subtotal_tax = $line_subtotal_tax < 0 ? 0 : $line_subtotal_tax;

			// Update tax
			$this->cart->cart_contents[$key]['line_tax'] = $line_tax;
			$this->cart->cart_contents[$key]['line_subtotal_tax'] = $line_subtotal_tax;

			// Update the "tax_data" array if we are dealing with WooCommerce 2.2+
			if ( version_compare( WOOCOMMERCE_VERSION, '2.2', '>=' ) ) {

				$tax_data = $this->cart->cart_contents[$key]['line_tax_data'];

				$tax_data['total'][$this->wootax_rate_id]    = $line_tax;
				$tax_data['subtotal'][$this->wootax_rate_id] = $line_subtotal_tax;

				$this->cart->cart_contents[$key]['line_tax_data'] = $tax_data;

			}

		}

	}
	
	/**
	 * Apply tax to a fee
	 *
	 * @since 4.2
	 * @param $key (int) the fee index
	 * @param $amt (float) the tax amount to be applied
	 */
	private function apply_fee_tax( $key, $amt ) {

		// Update tax value
		$this->cart->fees[ $key ]->tax += $amt;

		// Update tax_data array if we are dealing with WooCommerce 2.2+
		if ( version_compare( WOOCOMMERCE_VERSION, '2.2', '>=' ) ) {

			$tax_data = $this->cart->fees[ $key ]->tax_data;

			if ( !isset( $tax_data[ $this->wootax_rate_id ] ) ) {
				$tax_data[ $this->wootax_rate_id ] = 0;
			}

			$tax_data[ $this->wootax_rate_id ] += $amt;

			// Set new tax_data 
			$this->cart->fees[ $key ]->tax_data = $tax_data;

		}

	}
	
	/**
	 * Remove tax from a fee
	 *
	 * @since 4.2
	 * @param $key (int) the fee index
	 */
	private function remove_fee_tax( $key ) {

		// Exit if the taxes array is not defined
		if ( !$this->cart_taxes || sizeof( $this->cart_taxes ) == 0 ) {
			return;
		}
		
		// Fetch applied tax
		$applied_tax = $this->get_item_tax($key);
		
		// Remove taxes during checkout
		if ( isset( $this->cart->fees[$key] ) ) {

			// Calculate new tax values
			$line_tax = $this->cart->fees[$key]->tax - $applied_tax;
			
			// Zero tax if the calculated value is negative
			$line_tax = $line_tax < 0 ? 0 : $line_tax;
			
			// Update tax
			$this->cart->fees[$key]->tax = $line_tax;

			// Update tax_data array if we are dealing with WooCommerce 2.2+
			if ( version_compare( WOOCOMMERCE_VERSION, '2.2', '>=' ) ) {

				$tax_data = $this->cart->fees[$key]->tax_data;

				$tax_data[$this->wootax_rate_id] = $line_tax;

				// Set new tax_data 
				$this->cart->fees[$key]->tax_data = $tax_data;

			}

		}

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
					$address_found    = empty( $this->default_address ) ? 0 : $this->default_address;

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

					// Initialize CartItems array for this shipping location
					if ( !isset( $data[ $address_found ] ) || !is_array( $data[ $address_found ] ) ) {
						$data[ $address_found ] = array();
					}

					// Initialize counter for this location if necessary
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

					// Update item Index
					$item['Index'] = $counters_array[ $address_found ];

					// Unset "type" value
					unset( $item['Type'] );

					// Add formatted item data to the $data array
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
					$fee_indices[ $item_id ] = $fee_counter;

					// Update fee counter
					$fee_counter++;
				break;

			}

		}

		// Attach shipping items and fees to the first daughter order
		if ( $first_found !== false ) {

			foreach ( $shipping_items + $fee_items as $key => $item ) {

				// Get new item index
				$index   = $counters_array[ $first_found ];
				$item_id = $item['ItemID'];

				// Add to items array (Type index not included here)
				$data[ $first_found ][ $index ] = array(
					'Index'  => $index,
					'ItemID' => $item_id,
					'TIC'    => $item['TIC'],
					'Price'  => $item['Price'],
					'Qty'    => $item['Qty'],
				);

				// Determine if the item we are dealing with is a fee or shipping charge
				$type = isset( $fee_items[ $item_id ] ) ? 'fee' : 'shipping';

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
	 * Generate an order ID for each lookup
	 *
	 * @since 4.2
	 */
	private function generate_order_ids() {

		$taxcloud_ids = $this->taxcloud_ids;

		foreach ( $this->lookup_data as $location => $items ) {

			if ( !isset( $taxcloud_ids[ $location ] ) ) {
				$taxcloud_ids[ $location ] = array(
					'cart_id'  => '',
					'order_id' => generate_order_id(),
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
	 * @return array of items (see above)
	 */
	private function get_items_array() {

		$items       = $this->cart->cart_contents;
		$based_on    = wootax_get_option( 'tax_based_on' );
		$final_items = array();

		// Add cart items
		foreach ( $items as $item_key => $item ) {

			$product = $item['data'];

			if ( !$product->is_taxable() ) {
				continue;
			}

			// Get product TIC
			$tic_raw = get_post_meta( $product->id, 'wootax_tic', true );
			$tic     = $tic_raw == false ? '' : trim( $tic_raw );

			// Get product ID, Qty, and Price 
			$item_id       = $item_key;//!empty( $item['variation_id'] ) ? $item['variation_id'] : $item['product_id'];
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
					'TIC'    => WOOTAX_FEE_TIC,
					'Qty'    => 1, 
					'Price'  => $amt,
					'Type'   => 'fee',
				);

			}

		}

		// Add shipping costs
		$shipping_total = $this->cart->shipping_total;

		if ( $shipping_total > 0 ) {

			// Add a shipping item to the final items array (we assume that only one shipping method is being used per order)
			$final_items[ WOOTAX_SHIPPING_ITEM ] = array(
				'Index'  => NULL, 
				'ItemID' => WOOTAX_SHIPPING_ITEM, 
				'TIC'    => WOOTAX_SHIPPING_TIC, 
				'Qty'    => 1, 
				'Price'  => $shipping_total,
				'Type'   => 'shipping',
			);

		}

		return $final_items;

	}
	
	/**
	 * Perform a tax lookup for the users cart
	 *
	 * @since 4.2
	 */
	public function do_lookup() {

		// Remove currently applied taxes
		$this->remove_tax();

		// Generate lookup data
		$this->generate_lookup_data();

		// Retrieve validated destination addresses
		$destination_address = validate_address( $this->destination_address );

		// Define some variable used in every tax lookup
		$tax_array          = array();
		$lookup_data        = array();
		$tax_total          = 0;
		$shipping_tax_total = 0;

		// Fetch some information about this order
		$exempt_cert         = $this->get_exemption_certificate();
		$customer_id         = $this->get_customer_id();
		$delivered_by_seller = $this->is_delivered_by_seller();

		// Loop through locations in lookup_data array and send a Lookup request for each
		foreach ( $this->lookup_data as $location_key => $items ) {

			$lookup_data[ $location_key ] = $items;

			// Fetch cart_id
			$cart_id = isset( $this->taxcloud_ids[ $location_key ] ) ? $this->taxcloud_ids[ $location_key ]['cart_id'] : '';

			// Get the origin address
			$origin_address = validate_address( $this->get_origin_address( $location_key ) );

			// Build Lookup request (cartID empty for first request)
			$req = array(
				'customerID'        => $customer_id, 
				'cartID'            => $cart_id, 
				'cartItems'         => $items, 
				'origin'            => $origin_address, 
				'destination'       => $destination_address, 
				'deliveredBySeller' => $delivered_by_seller, 
			);		

			if ( $exempt_cert != NULL ) {
				$req['exemptCert'] = $exempt_cert;
			}

			// Send Lookup request 
			$res = $this->taxcloud->send_request( 'Lookup', $req );

			if ( $res !== false ) {

				// Initialize some vars
				$cart_items = $res->LookupResult->CartItemsResponse->CartItemResponse;

				// Store cartID to improve efficiency of subsequent lookup requests
				$this->taxcloud_ids[ $location_key ]['cart_id']  = $res->LookupResult->CartID;

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
							// Fetch appropriate item identifier (cart item key)
							$item_id = $this->mapping_array[ $location_key ][ $index ]['key'];

							// Apply tax to item
							$this->apply_item_tax( $item_id, $tax );

							// Increment order tax total
							$tax_total += $tax;
						break;

						case 'shipping':
							// Simply increment shipping tax total
							$item_id = WOOTAX_SHIPPING_ITEM;

							$shipping_tax_total += $tax;
						break;

						case 'fee':
							// Fetch appropriate fee identifier (cart fee index)
							$item_id = $this->mapping_array[ $location_key ][ $index ]['index'];

							// Apply tax to fees
							$this->apply_fee_tax( $item_id, $tax );

							// Increment order tax total
							$tax_total += $tax;
						break;

					}
					
					// Add item tax to cart_taxes array
					$this->cart_taxes[ $item_id ] = $tax;

					// Map item id to location
					$this->location_mapping_array[ $item_id ] = $location_key;

				}

			} else {
					
				if ( function_exists( 'wc_add_notice' ) ) {
					wc_add_notice( 'An error occurred while calculating the tax for this order: '. $this->taxcloud->get_error_message(), 'error' );
				} else {
					$this->woo->add_error( 'An error occurred while calculating the tax for this order: '. $this->taxcloud->get_error_message() );
				}

				return;

			}

		}

		// Update item identifiers
		$this->update_item_identifiers();

		// Save updated tax totals
		$this->update_tax_totals( $tax_total, $shipping_tax_total );

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
			$key    = sanitize_title( $data->name );
			$identifiers[ $key ] = $key;
		}

		// Add shipping
		$identifiers[ WOOTAX_SHIPPING_ITEM ] = WOOTAX_SHIPPING_ITEM;

		$this->identifiers = $identifiers;

	}

	/**
	 * Get information about the exemption certificate to be applied to this order (if one exists)
	 *
	 * @since 4.2
	 * @return an array of information about the certificate, or the certificate's ID
	 */
	private function get_exemption_certificate() {

		$certificate_data = NULL;
		
		if ( !empty( $this->woo->session->certificate_id ) ) {
			if ( $this->woo->session->certificate_id == 'true' ) {
				$certificate_data = $this->woo->session->certificate_data;
				$certificate_data['Detail']['SinglePurchaseOrderNumber'] = generate_order_id();
			} else {
				$certificate_data = array(
					'CertificateID' => $this->woo->session->certificate_id,
				);
			}
		}

		return $certificate_data;

	}
	
	/**
	 * Get origin address for order given location key
	 * 
	 * @since 4.2
	 * @param $location_key - the id of the location whose address information is desired
	 * @return an array containing information about the origin address
	 */
	public function get_origin_address( $location_key ) {

		// Initialize blank address array
		$address = array();
		
		// Populate array
		$address['Address1'] = $this->addresses[$location_key]['address_1'];
		$address['Address2'] = $this->addresses[$location_key]['address_2'];
		$address['Country']  = $this->addresses[$location_key]['country'];
		$address['State']    = $this->addresses[$location_key]['state'];
		$address['City']     = $this->addresses[$location_key]['city'];
		$address['Zip5']     = $this->addresses[$location_key]['zip5'];
		$address['Zip4']     = $this->addresses[$location_key]['zip4'];

		// Return the final address array
		return $address;

	}
	
	/** 
	 * Get destination address for order
	 *
	 * @since 4.2
	 * @return an array containing information about the customer's billing or shipping address (depending on store settings)
	 */
	public function get_destination_address() {

		// Retrieve "tax based on" option
		$tax_based_on = get_option( 'woocommerce_tax_based_on' );

		// Return origin address if this is a local pickup order
		if ( $this->is_local_pickup() || $tax_based_on == 'base' ) {
			return $this->get_origin_address( $this->default_address );
		}

		// Initialize blank address array
		$address = array();

		// Fetch BOTH billing and shipping address
		$addresses = array(
			'billing' => array(
				'address_1' => $this->woo->customer->get_address(),
				'address_2' => $this->woo->customer->get_address_2(),
				'country'   => $this->woo->customer->get_country(),
				'state'     => $this->woo->customer->get_state(),
				'city'      => $this->woo->customer->get_city(),
				'zip5'      => $this->woo->customer->get_postcode(),
			),
					
			'shipping' => array(
				'address_1' => $this->woo->customer->get_shipping_address(),
				'address_2' => $this->woo->customer->get_shipping_address_2(),
				'country'   => $this->woo->customer->get_shipping_country(),
				'state'     => $this->woo->customer->get_shipping_state(),
				'city'      => $this->woo->customer->get_shipping_city(),
				'zip5'      => $this->woo->customer->get_shipping_postcode(),
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
	 * Determines if an order is using the "local_pickup" shipping method
	 *
	 * @since 4.2
	 * @return true if the shipping method is "local_pickup.", else false
	 */
	private function is_local_pickup() {

		// Get current shipping method
		$shipping_method = $this->get_order_shipping_method();

		// Check if method is "local_pickup"
		if ( $shipping_method == 'local_pickup' ) {
			return true;
		} else {
			return false;
		}

	}


	/**
	 * Determines if an order is delivered by the seller
	 *
	 * @since 4.2
	 * @return boolean true if selected shipping method is "local_delivery"; else, false
	 */
	private function is_delivered_by_seller() {

		$shipping_method = $this->get_order_shipping_method();
		
		if ( $shipping_method == 'local_delivery' ) {
			return true;
		}
		
		// Return false by default
		return false;

	}
	
	/** 
	 * Determines if an order is ready for a Lookup request
	 * For an order to be "ready," two criteria must be met:
	 * - At least one origin address is added to the site
	 * - The customer's full address is available (and in the United States)
	 *
	 * @since 4.2
	 * @return boolean true if the order is ready for a tax lookup; otherwise, false
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
			$customer_id = $this->woo->session->get_customer_id();
		}

		$this->woo->session->wootax_customer_id = $customer_id;

		return $customer_id;

	}	

	/**
	 * Update the cart tax total for a given order
	 *
	 * @since 4.2
	 * @param $new_tax - new value for WooTax cart tax
	 */
	private function update_cart_tax_total( $new_tax ) {

		$this->cart->taxes[ $this->wootax_rate_id ] = $new_tax;

		if ( wootax_get_option( 'show_zero_tax' ) != 'true' && $new_tax == 0 ) {
			unset( $this->cart->taxes[ $this->wootax_rate_id ] );
		}

		// Use get_tax_total to set new tax total so we don't override other rates
		$this->cart->tax_total = $this->cart->tax->get_tax_total( $this->cart->taxes );

		$this->tax_total = $new_tax;

	}
	
	/**
	 * Update the shipping tax total for a given order
	 *
	 * @since 4.2
	 * @param $new_tax - new value for WooTax shipping tax
	 */
	private function update_shipping_tax_total( $new_tax ) {

		$this->cart->shipping_taxes[ $this->wootax_rate_id ] = $new_tax;

		if ( wootax_get_option( 'show_zero_tax' ) != 'true' && $new_tax == 0 ) {
			unset( $this->cart->shipping_taxes[ $this->wootax_rate_id ] );
		}

		// Use get_tax_total to set new tax total so we don't override other rates
		$this->cart->shipping_tax_total = $this->cart->tax->get_tax_total( $this->cart->shipping_taxes );

		// Update internal shipping tax total
		$this->shipping_tax_total = $new_tax;

	}

	/**
	 * Updates the cart tax/shipping tax totals for the order
	 * Triggers an update of the order tax item if we are in the backend
	 *
	 * @since 4.2
	 * @param $cart_tax - the total cart tax added by WooTax
	 * @param $shipping_tax - the total shipping tax added by WooTax
	 */
	private function update_tax_totals( $cart_tax = 0, $shipping_tax = 0 ) {
		
		// Update cart tax
		$this->update_cart_tax_total( $cart_tax );

		// Update shipping tax
		$this->update_shipping_tax_total( $shipping_tax );

	}
	
	/**
	 * Fetch order shipping method
	 * Chosen method is retrieved from the session var chosen_shipping_method (WC < 2.1) OR chosen_shipping_methods (WC 2.1+)
	 *
	 * @since 4.2
	 * @return chosen shipping method (string)
	 */
	private function get_order_shipping_method() {

		if ( isset( $this->woo->session->chosen_shipping_method ) ) {
			return $this->woo->session->chosen_shipping_method;
		} else if ( isset( $this->woo->session->chosen_shipping_methods ) ) {
			return $this->woo->session->chosen_shipping_methods[0];
		} else {
			return false;
		}

	}

}

// Instantiate WC_WooTax_Checkout object when cart totals are calculated
add_action( 'woocommerce_calculate_totals', 'wootax_initialize_checkout' );

function wootax_initialize_checkout() {
	$WC_WooTax_Checkout = new WC_WooTax_Checkout();
}