<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * WooTax Checkout.
 *
 * Responsible for computing the sales tax due during checkout.
 *
 * @author 	Simple Sales Tax
 * @package SST
 * @since 	4.2
 */
class WC_WooTax_Checkout {

	/**
	 * @var string Customer ID (user login or ID from session).
	 * @since 5.0
	 */
	private $customer_id = "";

	/**
	 * @var array The partial orders that comprise the current order.
	 * @since 5.0
	 */
	private $order_parts = array();

	/**
	 * @var WT_Exemption_Certificate The exemption certificate applied to the
	 * current order, or NULL if no certificate is applied.
	 * @since 5.0
	 */
	private $exempt_cert = null;

	/**
	 * Constructor: Initialize hooks.
	 *
	 * @since 4.2
	 */
	public function __construct( $cart ) {
		// Load data from the session after WP is loaded
		add_action( 'wp_loaded', array( $this, 'init' ) );

		// Don't allow WooCommerce to hide zero taxes; we'll handle this
		add_filter( 'woocommerce_cart_hide_zero_taxes', '__return_false' );

		// Maybe hide Sales Tax line item if tax total is $0.00
		add_filter( 'woocommerce_cart_tax_totals', array( $this, 'maybe_hide_tax_total' ), 10, 1 );
		
		// Set cart/shipping tax totals when cart totals are calculated
		add_action( 'woocommerce_calculate_totals', array( $this, 'calculate_tax_totals' ), 10, 1 );
	
		// When an order is created or resumed, associate session data with it
		add_action( 'woocommerce_new_order', array( $this, 'add_order_meta' ), 10, 1 );
		add_action( 'woocommerce_resume_order', array( $this, 'add_order_meta' ), 10, 1 );
	}

	/**
	 * Load tax data from the session.
	 *
	 * @since 4.2
	 */
	public function init() {
		$this->order_parts = WC()->session->get( 'order_parts', array() );

		if ( ( $exempt_cert = WC()->session->get( 'exempt_cert', false ) ) ) {
			$this->exempt_cert = WT_Exemption_Certificate::fromArray( $exempt_cert );
		}

		$this->customer_id = $this->get_customer_id();
	}
	
	/**
	 * Calculate sales tax totals for the current cart.
	 *
	 * @since 5.0
	 *
	 * @param WC_Cart $cart The WooCommerce cart object.
	 */
	public function calculate_tax_totals( $cart ) {

		// Remove taxes appled by Simple Sales Tax, if necessary		
		// Generate order parts if necessary
		// Get/validate customer address
		// If lookup is needed for one of the parts, send Lookup request
		//  - Store lookup result as order part metadata
		// Set shipping/cart tax totals

		/**
		 * // Fetch cart_id
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
			} else {
				// We will not display ZIP mismatch errors or SoapFaults. These messages tend to be disruptive and there is not much the customer can do to resolve them.
				$error = $this->taxcloud->get_error_message();

				if ( strpos( $error, 'zip' ) === false && strpos( $error, 'SoapFault' ) === false ) {			
					wc_add_notice( 'An error occurred while calculating the tax for this order. '. $error, 'error' );
				}
				
				return;
			}

			// Save updated tax totals
			$this->update_tax_totals( $tax_total, $shipping_tax_total );
			*/
	}
	
	/**
	 * Sets the sales tax for the item with the given key.
	 *
	 * @since 4.2
	 *
	 * @param string $key Key of cart item.
	 * @param float $amt Sales tax for item.
	 */
	private function apply_item_tax( $key, $amt ) {
		// Update tax values
		$this->cart->cart_contents[ $key ][ 'line_tax' ] += $amt;
		$this->cart->cart_contents[ $key ][ 'line_subtotal_tax' ] += $amt;

		// Add the "tax_data" array if we are dealing with WooCommerce 2.2+
		if ( version_compare( WT_WOO_VERSION, '2.2', '>=' ) ) {
			$tax_data = $this->cart->cart_contents[ $key ][ 'line_tax_data' ];

			if ( ! isset( $tax_data[ 'total' ][ WT_RATE_ID ] ) ) {
				$tax_data['total'][ WT_RATE_ID ] = 0;
			}

			if ( ! isset( $tax_data[ 'subtotal' ][ WT_RATE_ID ] ) ) {
				$tax_data[ 'subtotal' ][ WT_RATE_ID ] = 0;
			}

			$tax_data[ 'subtotal' ][ WT_RATE_ID ] += $amt;
			$tax_data[ 'total' ][ WT_RATE_ID ]    += $amt;

			$this->cart->cart_contents[ $key ][ 'line_tax_data' ] = $tax_data;
		}
	}
	
	/**
	 * Set the sales tax for a given fee.
	 *
	 * @since 4.2
	 *
	 * @param int $key Fee index.
	 * @param float $amt Sales tax for fee.
	 */
	private function apply_fee_tax( $key, $amt ) {
		// Update tax value
		$this->cart->fees[ $key ]->tax += $amt;

		// Update tax_data array if we are dealing with WooCommerce 2.2+
		if ( version_compare( WT_WOO_VERSION, '2.2', '>=' ) ) {
			$tax_data = $this->cart->fees[ $key ]->tax_data;

			if ( ! isset( $tax_data[ WT_RATE_ID ] ) ) {
				$tax_data[ WT_RATE_ID ] = 0;
			}

			$tax_data[ WT_RATE_ID ] += $amt;

			// Set new tax_data 
			$this->cart->fees[ $key ]->tax_data = $tax_data;
		}
	}
	
	/**
	 * Generates an array of CartItems organized by location key. Stores the
	 * array in the lookup_data property.
	 *
	 * @since 4.2
	 */
	private function generate_lookup_data() {
		// Reset
		$this->lookup_data = $this->mapping_array = array();

		// Exit if we do not have any items
		$order_items = $this->cart->get_cart();

		if ( ! $order_items )
			return;

		$customer_state = $this->destination_address[ 'State' ];
		$counters_array = $lookup_data = $mapping_array = array();
		
		$tax_based_on = SST()->get_option( 'tax_based_on' );

		// Add cart items
		foreach ( $order_items as $item_id => $item ) {
			$final_item = array();

			// Get some information about the product being sold
			$product_id   = $item[ 'product_id' ];
			$variation_id = $item[ 'variation_id' ];

			// TIC
			$tic = wt_get_product_tic( $product_id, $variation_id );

			// Quantity and price
			$unit_price = $item[ 'line_total' ] / $item[ 'quantity' ];

			if ( $tax_based_on == 'item-price' || !$tax_based_on ) {
				$qty   = $item[ 'quantity' ];
				$price = $unit_price; 
			} else {
				$qty   = 1;
				$price = $unit_price * $item[ 'quantity' ]; 
			}

			// Attempt to find origin address to use for product; if possible, we use the origin address in the customer's state
			// Developers can adjust the final address used with the wootax_origin_address filter (see below)
			$origin_addresses = fetch_product_origin_addresses( $product_id );
			$address_found    = WT_DEFAULT_ADDRESS;

			if ( count( $origin_addresses ) == 1 ) {
				$address_found = $origin_addresses[0];				
			} else {
				foreach ( $origin_addresses as $key ) {
					if ( isset( $this->addresses[ $key ][ 'state' ] ) && $this->addresses[ $key ][ 'state' ] == $customer_state ) {
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
				$final_item[ 'TIC' ] = $tic; // Only add TIC if one other than default is being used

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
					'TIC'    => apply_filters( 'wootax_shipping_tic', WT_DEFAULT_SHIPPING_TIC ), 
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
						'TIC'    => apply_filters( 'wootax_fee_tic', WT_DEFAULT_FEE_TIC ),
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
	}
	
	/**
	 * Update the customer address in the session.
	 *
	 * WooCommerce does not update the address when totals are recomputed through
	 * an AJAX request.
	 *
	 * @since 4.8
	 */
	private function update_customer_address() {
		if ( isset( $_POST[ 'billing_country' ] ) ) {
			WC()->customer->set_country( $_POST[ 'billing_country' ] );
		}

		if ( isset( $_POST[ 'billing_state' ] ) ) {
			WC()->customer->set_state( $_POST[ 'billing_state' ] );
		}

		if ( isset( $_POST[ 'billing_postcode' ] ) ) {
			WC()->customer->set_postcode( $_POST[ 'billing_postcode' ] );
		}

		if ( isset( $_POST[ 'billing_city' ] ) ) {
			WC()->customer->set_city( $_POST[ 'billing_city' ] );
		}

		if ( isset( $_POST[ 'billing_address_1' ] ) ) {
			WC()->customer->set_address( $_POST[ 'billing_address_1' ] );
		}

		if ( isset( $_POST[ 'billing_address_2' ] ) ) {
			WC()->customer->set_address_2( $_POST[ 'billing_address_2' ] );
		}

		if ( wc_ship_to_billing_address_only() || ! isset( $_POST[ 'ship_to_different_address' ] ) ) {

			if ( isset( $_POST[ 'billing_country' ] ) ) {
				WC()->customer->set_shipping_country( $_POST[ 'billing_country' ] );
				WC()->customer->calculated_shipping( true );
			}

			if ( isset( $_POST[ 'billing_state' ] ) ) {
				WC()->customer->set_shipping_state( $_POST[ 'billing_state' ] );
			}

			if ( isset( $_POST[ 'billing_postcode' ] ) ) {
				WC()->customer->set_shipping_postcode( $_POST[ 'billing_postcode' ] );
			}

			if ( isset( $_POST[ 'billing_city' ] ) ) {
				WC()->customer->set_shipping_city( $_POST[ 'billing_city' ] );
			}

			if ( isset( $_POST[ 'billing_address_1' ] ) ) {
				WC()->customer->set_shipping_address( $_POST[ 'billing_address_1' ] );
			}

			if ( isset( $_POST[ 'billing_address_2' ] ) ) {
				WC()->customer->set_shipping_address_2( $_POST[ 'billing_address_2' ] );
			}
		} else {

			if ( isset( $_POST[ 'shipping_country' ] ) ) {
				WC()->customer->set_shipping_country( $_POST[ 'shipping_country' ] );
				WC()->customer->calculated_shipping( true );
			}

			if ( isset( $_POST[ 'shipping_state' ] ) ) {
				WC()->customer->set_shipping_state( $_POST[ 'shipping_state' ] );
			}

			if ( isset( $_POST[ 'shipping_postcode' ] ) ) {
				WC()->customer->set_shipping_postcode( $_POST[ 'shipping_postcode' ] );
			}

			if ( isset( $_POST[ 'shipping_city' ] ) ) {
				WC()->customer->set_shipping_city( $_POST[ 'shipping_city' ] );
			}

			if ( isset( $_POST[ 'shipping_address_1' ] ) ) {
				WC()->customer->set_shipping_address( $_POST[ 'shipping_address_1' ] );
			}

			if ( isset( $_POST[ 'shipping_address_2' ] ) ) {
				WC()->customer->set_shipping_address_2( $_POST[ 'shipping_address_2' ] );
			}
		}
	}

	/**
	 * Set the destination address for the current order.
	 *
	 * If the customer has selected a 'local pickup' shipping method, we will
	 * use one of the origin addresses configured by the admin. Otherwise,
	 * we will use the customer's address.
	 *
	 * @since 4.2
	 *
	 * @return array
	 */
	private function set_destination_address() {
		$tax_based_on = get_option( 'woocommerce_tax_based_on' );

		// Return origin address if this is a local pickup order
		if ( wt_is_local_pickup( $this->get_shipping_method() ) || $tax_based_on == 'base' ) {
			$this->destination_address = wootax_get_address( apply_filters( 'wootax_pickup_address', WT_DEFAULT_ADDRESS, $this->addresses, -1 ) );
			return;
		}

		if ( ! defined( 'DOING_AJAX' ) && $_POST ) { // If this is not an AJAX request...
			$this->update_customer_address();
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
		$parsed_zip = parse_zip( $address[ 'Zip5' ] );

		$address[ 'Zip5' ] = $parsed_zip[ 'zip5' ];
		$address[ 'Zip4' ] = $parsed_zip[ 'zip4' ];

		$this->destination_address = $address;
	}
	
	/**
	 * Return the customer ID to be used for the current order. If the customer
	 * is logged in, return their username. Otherwise, return the customer ID
	 * generated by WooCommerce.
	 *
	 * @since 4.2
	 *
	 * @return mixed
	 */
	private function get_customer_id() {
		global $current_user;

		if ( is_user_logged_in() ) {
			$customer_id = $current_user->user_login;
		} else {
			$customer_id = WC()->session->get_customer_id();
		}

		return $customer_id;
	}

	/**
	 * Update the cart or shipping tax total.
	 *
	 * @since 4.4
	 *
	 * @param string $total_type "shipping" or "cart"
	 * @param float $new_tax New tax total.
	 */
	private function update_tax_total( $total_type, $new_tax ) {
		$total_type = $total_type == 'cart' ? '' : $total_type .'_';
		
		$tax_key    = $total_type . 'taxes';
		$total_key  = $total_type . 'tax_total';

		$this->cart->{$tax_key}[ WT_RATE_ID ] = $new_tax;

		// Use get_tax_total to set new tax total so we don't override other rates
		$this->cart->$total_key = version_compare( WT_WOO_VERSION, '2.2', '>=' ) ? WC_Tax::get_tax_total( $this->cart->$tax_key ) : $this->cart->tax->get_tax_total( $this->cart->$tax_key );
		
		$this->$total_key = $new_tax;
	}

	/**
	 * Update the cart/shipping tax totals for the order.
	 *
	 * @since 4.2
	 *
	 * @param float $cart_tax Total cart tax.
	 * @param float $shipping_tax Total shipping tax.
	 */
	private function update_tax_totals( $cart_tax = 0, $shipping_tax = 0 ) {	
		$this->update_tax_total( 'cart', $cart_tax );
		$this->update_tax_total( 'shipping', $shipping_tax );
	}
	
	/**
	 * Get the shipping method for the current order.
	 *
	 * If WC 2.1+ is installed, get the method from the chosen_shipping_methods
	 * session variable. Otherwise, use chosen_shipping_method.
	 *
	 * If no method is selected, return false.
	 *
	 * @since 4.2
     *
	 * @return string|bool
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
	 * Return a hash of the current order.
	 *
	 * @since 4.4
	 *
	 * @return string
	 */
	private function get_order_hash() {
		return md5( json_encode( $this->get_customer_id() ) . json_encode( $this->destination_address ) . json_encode( $this->lookup_data ) . json_encode( $this->get_exemption_certificate() ) );
	}

	/**
	 * If the "Show Zero Tax" option is set to "No" and the tax total is
	 * $0.00, hide the Sales Tax line item.
	 *
	 * @since 5.0
	 *
	 * @param  array $tax_totals Array of tax totals (@see WC_Cart->calculate_totals()).
	 * @return array
	 */
	public function maybe_hide_tax_total( $tax_totals ) {
		$hide_zero_taxes = SST()->get_option( 'show_zero_tax' ) != 'true';

		if ( $hide_zero_taxes ) {
			$amounts    = array_filter( wp_list_pluck( $tax_totals, 'amount' ) );
			$tax_totals = array_intersect_key( $tax_totals, $amounts );
		}

		return $tax_totals;
	}

	/**
	 * Save metadata when a new order is created. Create a new log entry if
	 * logging is enabled.
	 *
	 * @since 4.2
	 *
	 * @param int $order_id ID of new order.
	 */
	public function add_order_meta( $order_id ) {

		WT_Orders::update_meta( $order_id, 'wt_customer_id', $this->customer_id );
		WT_Orders::update_meta( $order_id, 'wt_order_parts', $this->order_parts );

		$exempt_cert = $this->exempt_cert;
		if ( $exempt_cert instanceof WT_Exemption_Certificate ) {
			$exempt_cert = $this->exempt_cert->toArray();
		}

		WT_Orders::update_meta( $order_id, 'wt_exempt_cert',  $exempt_cert );

		if ( WT_LOG_REQUESTS ) {
			$logger = class_exists( 'WC_Logger' ) ? new WC_Logger() : WC()->logger();
			$logger->add( 'wootax', "New order with ID $order_id created." );
		}
		
		// TODO: maybe delete session data
	}

}

endif;