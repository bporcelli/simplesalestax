<?php

/**
 * Responsible for determining the sales tax due during checkout and updating order totals accordingly
 *
 * @package WooTax
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
	 * @param $cart WC_Cart object on which we are operating
	 */
	public function __construct( $cart ) {
		do_action( 'wt_start_lookup_checkout', $this );

		$this->is_renewal      = apply_filters( 'wt_cart_is_renewal', false );
		$this->is_subscription = apply_filters( 'wt_cart_is_subscription', false );
		$this->cart            = &$cart;
		$this->taxcloud        = TaxCloud();
		$this->addresses       = fetch_business_addresses();

		$this->init();

		do_action( 'wt_end_lookup_checkout', $this );
	}

	/**
	 * Initiates a tax lookup
	 *
	 * @since 4.2
	 */
	private function init() {
		// Set customer destination address
		$this->set_destination_address();

		// Exit immediately if we do not have enough information to proceed with a lookup
		if( !$this->ready_for_lookup() ) {
			return;
		} else {
			// Load data about applied tax from session if possible
			$this->load_data_from_session();

			// Generate lookup data
			$this->generate_lookup_data();

			// Either issue a new lookup or set cart taxes from stored values
			if ( $this->needs_lookup() ) {
				$this->do_lookup();
			} else {
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
			$this->cart_taxes = WC()->session->get( 'cart_taxes', array() );
			$this->shipping_taxes = WC()->session->get( 'shipping_taxes', array() );
			$this->taxcloud_ids = WC()->session->get( 'taxcloud_ids', array() );
			$this->lookup_sent = WC()->session->get( 'wootax_lookup_sent', '' );
			$this->mapping_array = WC()->session->get( 'mapping_array', array() );
			$this->lookup_data = WC()->session->get( 'lookup_data', array() );
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
			WC()->session->set( 'location_mapping_array', $this->location_mapping_array );
			WC()->session->set( 'mapping_array', $this->mapping_array );
			WC()->session->set( 'lookup_data', $this->lookup_data );
			WC()->session->set( 'taxcloud_ids', $this->taxcloud_ids );
			WC()->session->set( 'cart_taxes', $this->cart_taxes );
			WC()->session->set( 'shipping_taxes', $this->shipping_taxes );
			WC()->session->set( 'wootax_tax_total', $this->tax_total );
			WC()->session->set( 'wootax_shipping_tax_total', $this->shipping_tax_total );
			WC()->session->set( 'item_ids', $this->identifiers );
			WC()->session->set( 'first_found_key', $this->first_found );
			WC()->session->set( 'wootax_lookup_sent', $this->lookup_sent );
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
	 * Generates an array of items in TaxCloud-friendly format and organized by location key
	 * Stores generated array in lookup_data property
	 *
	 * @since 4.2
	 */
	private function generate_lookup_data() {
		// Reset
		$this->lookup_data = $this->mapping_array = array();

		// Exit if we do not have any items
		$order_items = $this->cart->get_cart();

		if ( !$order_items )
			return;

		$customer_state = $this->destination_address['State'];
		$counters_array = $lookup_data = $mapping_array = array();
		
		$tax_based_on = WC_WooTax::get_option( 'tax_based_on' );

		// Add cart items
		foreach ( $order_items as $item_id => $item ) {
			$final_item = array();

			// Get some information about the product being sold
			$product_id   = $item['product_id'];
			$variation_id = $item['variation_id'];

			// TIC
			$tic = wt_get_product_tic( $product_id, $variation_id );

			// Quantity and price
			$unit_price = $item['line_total'] / $item['quantity'];

			if ( $tax_based_on == 'item-price' || !$tax_based_on ) {
				$qty   = $item['quantity'];
				$price = $unit_price; 
			} else {
				$qty   = 1;
				$price = $unit_price * $item['quantity']; 
			}

			// Attempt to find origin address to use for product; if possible, we use the origin address in the customer's state
			// Developers can adjust the final address used with the wootax_origin_address filter (see below)
			$origin_addresses = fetch_product_origin_addresses( $product_id );
			$address_found    = WT_DEFAULT_ADDRESS;

			if ( count( $origin_addresses ) == 1 ) {
				$address_found = $origin_addresses[0];				
			} else {
				foreach ( $origin_addresses as $key ) {
					if ( isset( $this->addresses[ $key ]['state'] ) && $this->addresses[ $key ]['state'] == $customer_state ) {
						$address_found = $key;
						break;
					}
				}
			}

			$address_found = apply_filters( 'wootax_origin_address', $address_found, $customer_state, $this );

			// Avoid PHP notices by initializing arrays
			if ( !isset( $lookup_data[ $address_found ] ) || !is_array( $lookup_data[ $address_found ] ) )
				$lookup_data[ $address_found ] = array();
			if ( !isset( $counters_array[ $address_found ] ) )
				$counters_array[ $address_found ] = 0;

			// Update mapping array and lookup data array
			$index = $counters_array[ $address_found ];

			$final_item = array(
				'Index'  => $index,
				'ItemID' => $item_id,
				'Price'  => apply_filters( 'wootax_taxable_price', $price, true, $product_id ),
				'Qty'    => $qty,
			);

			if ( $tic !== false )
				$final_item['TIC'] = $tic; // Only add TIC if one other than default is being used

			$lookup_data[ $address_found ][] = $final_item;

			$mapping_array[ $address_found ][] = array(
				'id'    => $item_id,
				'index' => $index,
				'type'  => 'cart',
				'key'   => $item_id,
			);

			$counters_array[ $address_found ]++;
		}

		// The ID of the first found location for this order; all shipping charges and fees will be grouped under this location
		$temp_lookup = array_keys( $lookup_data );
		$first_location = array_shift( $temp_lookup );

		if ( $first_location !== false ) {

			// Add shipping item (we assume one per order)
			$shipping_total = $this->cart->shipping_total;

			// Set shipping tax to zero if we are calculating the initial total for a subscription and shipping should not be charged up front
			if ( $this->is_subscription && WC_Subscriptions_Cart::get_calculation_type() != 'recurring_total' && !WC_Subscriptions_Cart::charge_shipping_up_front() ) {
				$shipping_total = 0;
			}

			if ( $shipping_total > 0 ) {
				$index   = $counters_array[ $first_location ];
				$item_id = WT_SHIPPING_ITEM;

				$lookup_data[ $first_location ][] = array(
					'Index'  => $index,
					'ItemID' => $item_id, 
					'TIC'    => WT_SHIPPING_TIC, 
					'Qty'    => 1, 
					'Price'  => apply_filters( 'wootax_taxable_price', $shipping_total, true, $item_id ),
				);

				$mapping_array[ $first_location ][] = array(
					'id'    => $item_id, 
					'index' => $index,
					'type'  => 'shipping',
				);

				$counters_array[ $first_location ]++;
			}

			// Add fees, as long as we aren't calculating tax for cart where all subs have free trial and there is no sign up fee
			$fee_index = 0;
			$add_fees = true;

			if ( $this->is_subscription ) {
				if ( WC_Subscriptions_Cart::get_calculation_type() != 'recurring_total' && 0 == WC_Subscriptions_Cart::get_cart_subscription_sign_up_fee() && WC_WooTax_Subscriptions::all_cart_items_have_free_trial() ) {
					$add_fees = false;
				}
			}

			if ( $add_fees ) {
				foreach ( $this->cart->get_fees() as $ind => $fee ) {
					// TODO: Phase this out?
					if ( isset( $fee->taxable ) && !$fee->taxable )
						continue;

					$item_id = $fee->id;
					$index   = $counters_array[ $first_location ];

					$lookup_data[ $first_location ][] = array(
						'Index'  => $index,
						'ItemID' => $item_id, 
						'TIC'    => WT_FEE_TIC,
						'Qty'    => 1, 
						'Price'  => apply_filters( 'wootax_taxable_price', $fee->amount, true, $item_id ),
					);

					// Update mapping array
					$mapping_array[ $first_location ][ $index ] = array(
						'id'    => $item_id, 
						'index' => $fee_index, 
						'type'  => 'fee',
					);

					$counters_array[ $first_location ]++;
					$fee_index++;
				}
			}
		}

		// Store data and save
		$this->lookup_data   = $lookup_data;
		$this->mapping_array = $mapping_array;
		$this->first_found   = $first_location;

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
		if ( function_exists( 'wt_get_exemption_certificate' ) ) {
			return wt_get_exemption_certificate();
		} else {
			return NULL;
		}
	}
	
	/** 
	 * Set destination address for order
	 *
	 * @since 4.2
	 * @return (array) associative array containing customer address
	 */
	private function set_destination_address() {
		// Retrieve "tax based on" option
		$tax_based_on = get_option( 'woocommerce_tax_based_on' );

		// Return origin address if this is a local pickup order
		if ( wt_is_local_pickup( $this->get_shipping_method() ) || $tax_based_on == 'base' ) {
			return wootax_get_address( apply_filters( 'wootax_pickup_address', WT_DEFAULT_ADDRESS, $this->addresses, -1 ) );
		}

		// Attempt to fetch correct address
		if ( $tax_based_on == 'billing' ) {
			$address = array(
				'Address1' => WC()->customer->get_address(),
				'Address2' => WC()->customer->get_address_2(),
				'Country'  => WC()->customer->get_country(),
				'State'    => WC()->customer->get_state(),
				'City'     => WC()->customer->get_city(),
				'Zip5'     => WC()->customer->get_postcode(),
			);
		} else {
			$address = array(
				'Address1' => WC()->customer->get_shipping_address(),
				'Address2' => WC()->customer->get_shipping_address_2(),
				'Country'  => WC()->customer->get_shipping_country(),
				'State'    => WC()->customer->get_shipping_state(),
				'City'     => WC()->customer->get_shipping_city(),
				'Zip5'     => WC()->customer->get_shipping_postcode(),
			);
		}
		
		// Parse ZIP to get ZIP +4 if possible
		$parsed_zip = parse_zip( $address['Zip5'] );

		$address['Zip5'] = $parsed_zip['zip5'];
		$address['Zip4'] = $parsed_zip['zip4'];

		$this->destination_address = $address;
	}
	
	/** 
	 * Determines if an order is ready for a Lookup request
	 * For an order to be "ready," two criteria must be met:
	 * - At least one origin address is added to the site
	 * - The customer's full address is available (and in the United States)
	 *
	 * @since 4.2
	 * @return bool
	 */
	private function ready_for_lookup() {
		if ( !is_array( $this->addresses ) || count( $this->addresses ) == 0 ) {
			return false;
		} else if ( !wootax_is_valid_address( $this->destination_address, true ) ) {
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

		WC()->session->set( 'wootax_customer_id', $customer_id );

		return $customer_id;
	}

	/**
	 * Update order shipping/cart tax total
	 *
	 * @since 4.4
	 * @param (string) $total_type "shipping" to update shipping tax total; "cart" to update cart tax total
	 * @param (float) $new_tax new value for tax
	 */
	private function update_tax_total( $total_type, $new_tax ) {
		$total_type = $total_type == 'cart' ? '' : $total_type .'_';
		
		$tax_key    = $total_type . 'taxes';
		$total_key  = $total_type . 'tax_total';

		$this->cart->{$tax_key}[ WT_RATE_ID ] = $new_tax;

		// Maybe remove sales tax row if tax due is zero and the cart does not contain a subscription (removing the zero tax for subscription orders causes issues)
		if ( !$this->is_subscription && WC_WooTax::get_option( 'show_zero_tax' ) != 'true' && $new_tax == 0 ) {
			unset( $this->cart->{$tax_key}[ WT_RATE_ID ] );
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
		if ( WC()->session->get( 'chosen_shipping_method', false ) ) {
			return WC()->session->chosen_shipping_method;
		} else if ( WC()->session->get( 'chosen_shipping_methods', array() ) ) {
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
			return $this->get_order_hash() != $this->lookup_sent;
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
}

// Set up checkout object when totals are calculated
// @param $cart WC_Cart object
function wt_maybe_do_lookup( $cart ) {
	if ( WT_CALC_TAXES ) {
		$checkout = new WC_WooTax_Checkout( $cart );
	}
}

add_action( 'woocommerce_calculate_totals', 'wt_maybe_do_lookup', 10, 1 );