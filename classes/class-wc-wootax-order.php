<?php

// Prevent data leaks
if ( ! defined( 'ABSPATH' ) ) exit; 

/**
 * WC_WooTax_Order Object
 * Contains all methods for manipulating order taxes 
 *
 * @since 4.2
 * @version 2.0
 */

class WC_WooTax_Order {
	/**
	 * The ID of the order
	 */
	public $order_id;
	
	/**
	 * WC_Order object
	 */
	public $order;
	
	/**
	 * Global WooCommerce object
	 */
	private $woo;

	/**
	 * Array of default values for some fields
	 */
	private $defaults = array(
		'tax_total'              => 0,
		'shipping_tax_total'     => 0,
		//'cart_taxes'             => array(),
		'customer_id'            => 0,
		//'wc_order_id'            => 0,
		'imported_order'         => false,
		'exemption_applied'      => false,
		'captured'               => false,
		'is_renewal'             => false,
		'tax_item_id'            => 0,
		'mapping_array'          => array(),
		'location_mapping_array' => array(),
		'refunded'               => false,
		//'refunded_items'         => array(),
		'lookup_data'            => array(),
		'taxcloud_ids'			 => array(),
		'identifiers'            => array(),
	);

	/**
	 * Customer destination address
	 */
	private $destination_address = array();

	/**
	 * Holds the ID of the WooTax tax rate
	 */
	private $wootax_rate_id = '';

	/**
	 * Holds a WC_WooTax_TaxCloud object
	 */	
	private $taxcloud = false;

	/**
	 * Holds an array of business address entered by the user
	 */
	private $addresses = array();

	/**
	 * Holds an integer representing the ID of the "default" business address
	 */
	private $default_address = -1;
	
	/**
	 * Class constructor: Hook into WooCommerce
	 *
	 * @since 4.2
	 */
	public function __construct() {

		global $woocommerce;

		// Set up class properties
		$this->woo = &$woocommerce;

		// Set rate ID
		$this->wootax_rate_id = get_option( 'wootax_rate_id' );

		// Hook into WooCommerce
		$this->hook_woocommerce();

	}
	
	/**
	 * Hook into WooCommerce
	 *
	 * @since 4.2c
	 */
	public function hook_woocommerce() {

		// Sends AuthorizedWithCapture request to TaxCloud when order is marked as completed via the Woo interface 
		add_action( 'woocommerce_order_status_completed', array( $this, 'complete' ) );
		
		// Marks order as Refunded within TaxCloud when order status is changed to refunded
		add_action( 'woocommerce_order_status_refunded', array( $this, 'refund' ) );

		// Add meta to order items
		add_action( 'woocommerce_add_order_item_meta', array( $this, 'add_cart_item_meta' ), 10, 3 );
		add_action( 'woocommerce_add_order_fee_meta', array( $this, 'add_fee_meta' ), 10, 4 );
		add_action( 'woocommerce_add_shipping_order_item', array( $this, 'add_shipping_meta' ), 10, 3 );

		// Hide meta from users
		//add_filter( 'woocommerce_hidden_order_itemmeta', array( $this, 'hide_order_item_meta' ), 10, 1 );

		// Hook into WooCommerce calculate tax ajax action so we can add WooTax taxes
		//remove_action( 'wp_ajax_woocommerce_calc_line_taxes', array( 'WC_AJAX', 'calc_line_taxes' ) );
		add_action( 'wp_ajax_woocommerce_calc_line_taxes', array( $this, 'ajax_update_order_tax' ), 1 );

		// Needed for compatibility with WooCommerce 2.2+
		if ( version_compare( WOOCOMMERCE_VERSION, '2.2', '>=' ) ) {

			// Adds shipping tax
			add_action( 'woocommerce_order_add_shipping', array( $this, 'add_shipping_tax' ), 12, 3 );
		
			// Makes sure appropriate tax is recorded for WooTax rate
			add_action( 'woocommerce_order_add_tax', array( $this, 'add_order_tax_rate' ), 12, 3 );

		}

		// Add order meta
		add_action( 'woocommerce_new_order', array( $this, 'add_order_meta' ), 10, 1 );

	}

	/**
	 * Loads data from an order with given ID
	 *
	 * @since 4.2
	 * @param $order_id (int) the ID of a shop_order post 
	 */
	public function load_order( $order_id = -1 ) {
		
		// Display an error message if no order ID is passed
		if ( $order_id == -1 ) {
			wootax_add_flash_message( 'There was an error while updating the order. No order ID was passed to WooTax. Please try again.' );
		} else {

			// Set order id
			$this->order_id = $order_id;

			// Other important properties
			$this->addresses 	   = fetch_business_addresses();
			$this->default_address = wootax_get_option( 'default_address' );

			// Load WC_Order object
			$this->order = new WC_Order( $this->order_id );

		}

	}

	/**
	 * Add WooTax meta data to a newly created order
	 * Also, determine the ID of the WooTax tax item if WC 2.1.x
	 *
	 * @since 4.2
	 * @param $order_id ID of newly created order
	 */
	public function add_order_meta( $order_id ) {

		// Load order/session data
		$this->load_order( $order_id );
		$this->store_session_data();

		// WC 2.1.x support
		if ( version_compare( WOOCOMMERCE_VERSION, '2.2', '<' ) ) {

			// Store ID of WooTax tax item
			$this->store_tax_item_id( $order_id );

			// Store shipping item index 
			$location_id = isset( $this->location_mapping_array[ WOOTAX_SHIPPING_ITEM ] ) ? $this->location_mapping_array[ WOOTAX_SHIPPING_ITEM ] : 0;

			if ( isset( $this->mapping_array[ $location_id ][ WOOTAX_SHIPPING_ITEM ] ) ) {
				update_post_meta( $order_id, '_wootax_shipping_index', $this->mapping_array[ $location_id ][ WOOTAX_SHIPPING_ITEM ] );
			}

		}

	}

	/**
	 * Add WooTax meta to order cart items:
	 * - Location associated with item
	 * - Tax applied by WooTax
	 *
	 * @since 4.2
	 * @param $item_id order item id
	 * @param $values cart item data
	 * @param $cart_item_key cart item key
	 */
	public function add_cart_item_meta( $item_id, $values, $cart_item_key ) {

		$location_id = isset( $this->location_mapping_array[ $cart_item_key ] ) ? $this->location_mapping_array[ $cart_item_key ] : 0;
		$tax_amount  = isset( $this->cart_taxes[ $cart_item_key ] ) ? $this->cart_taxes[ $cart_item_key ] : 0;
		$item_index  = isset( $this->mapping_array[ $location_id ][ $cart_item_key ] ) ? $this->mapping_array[ $location_id ][ $cart_item_key ] : 0;

		wc_add_order_item_meta( $item_id, '_wootax_location_id', $location_id );
		wc_add_order_item_meta( $item_id, '_wootax_tax_amount', $tax_amount );
		wc_add_order_item_meta( $item_id, '_wootax_index', $item_index );

	}

	/**
	 * Add WooTax meta to order fees:
	 * - Location associated with fee
	 * - Tax applied by WooTax
	 *
	 * @since 4.2
	 * @param $order_id WooCommerce order id
	 * @param $item_id order item id
	 * @param $fee fee data
	 * @param $fee_key fee key
	 */
	public function add_fee_meta( $order_id, $item_id, $fee, $fee_key ) {

		$location_id = isset( $this->location_mapping_array[ $fee_key ] ) ? $this->location_mapping_array[ $fee_key ] : 0;
		$tax_amount  = isset( $this->cart_taxes[ $fee_key ] ) ? $this->cart_taxes[ $fee_key ] : 0;
		$item_index  = isset( $this->mapping_array[ $location_id ][ $fee_key ] ) ? $this->mapping_array[ $location_id ][ $fee_key ] : 0;

		wc_add_order_item_meta( $item_id, '_wootax_location_id', $location_id );
		wc_add_order_item_meta( $item_id, '_wootax_tax_amount', $tax_amount );
		wc_add_order_item_meta( $item_id, '_wootax_index', $item_index );

	}

	/**
	 * Add WooTax meta to order shipping methods:
	 * - Location associated with method
	 * - Tax applied by WooTax
	 *
	 * We will assume there is only one shipping method for each order.
	 *
	 * @since 4.2
	 * @param $order_id WooCommerce order id
	 * @param $item_id order item id
	 * @param $package_key shipping package key
	 */
	public function add_shipping_meta( $order_id, $item_id, $package_key ) {

		$location_id = isset( $this->location_mapping_array[ WOOTAX_SHIPPING_ITEM ] ) ? $this->location_mapping_array[ WOOTAX_SHIPPING_ITEM ] : 0;
		$tax_amount  = isset( $this->cart_taxes[ WOOTAX_SHIPPING_ITEM ] ) ? $this->cart_taxes[ WOOTAX_SHIPPING_ITEM ] : 0;
		$item_index  = isset( $this->mapping_array[ $location_id ][ WOOTAX_SHIPPING_ITEM ] ) ? $this->mapping_array[ $location_id ][ WOOTAX_SHIPPING_ITEM ] : 0;

		wc_add_order_item_meta( $item_id, '_wootax_location_id', $location_id );
		wc_add_order_item_meta( $item_id, '_wootax_tax_amount', $tax_amount );
		wc_add_order_item_meta( $item_id, '_wootax_index', $item_index );

	}

	/**
	 * Store the ID of the first tax item with a rate ID matching WooTax's rate ID
	 *
	 * @since 4.2
	 * @param $order_id ID of newly created order
	 */
	private function store_tax_item_id( $order_id ) {

		$tax_items   = $this->order->get_taxes();
		$tax_item_id = 0;

		if ( count( $tax_items ) > 0 ) {

			// Find first rate with matching rate id
			foreach ( $tax_items as $key => $data ) {
				if ( $data['rate_id'] == $this->wootax_rate_id ) {
					$tax_item_id = $key;
					break;
				}
			}

		}

		$this->tax_item_id = $tax_item_id;

	}

	/**
	 * Hide order item meta 
	 *
	 * @since 4.2
	 * @param $to_hide array of meta fields to hide
	 * @return array of meta fields to hide
	 */
	public function hide_order_item_meta( $to_hide ) {

		$to_hide[] = '_wootax_tax_amount';
		$to_hide[] = '_wootax_location_id';

		return $to_hide;

	}

	/**
	 * Store session data added by WooTax during checkout. TaxCloud IDs, cart taxes, customer ID, applied exemption certificate, tax totals most important
	 * Afterward, erase data
	 * 
	 * @since 4.2
	 */
	public function store_session_data() {

		if ( $this->woo->session instanceof WC_Session_Handler ) {

			$this->exemption_applied      = $this->get_exemption_certificate();
			$this->taxcloud_ids           = $this->woo->session->taxcloud_ids;
			$this->cart_taxes             = $this->woo->session->cart_taxes;
			$this->location_mapping_array = $this->woo->session->location_mapping_array;
			$this->customer_id            = $this->woo->session->wootax_customer_id;
			$this->tax_total              = $this->woo->session->wootax_tax_total;
			$this->shipping_tax_total     = $this->woo->session->wootax_shipping_tax_total;
			$this->identifiers            = $this->woo->session->item_ids;
			
			// Store mapping array in flipped order
			$mapping_array = array();

			if ( isset( $this->woo->session->mapping_array ) ) {

				foreach ( $this->woo->session->mapping_array as $address_key => $mappings ) {

					$new_mappings = array();

					foreach ( $mappings as $index => $item ) {
						$new_mappings[ $item['id'] ] = $index;
					}

					$mapping_array[ $address_key ] = $new_mappings;

				}

			}

			$this->mapping_array = $mapping_array;

			$this->remove_session_data();

		}

	}

	/**
	 * Removes all WooTax data from the session post-checkout
	 *
	 * @since 4.2
	 */
	public function remove_session_data() {

		if ( $this->woo->session instanceof WC_Session_Handler ) {

			$this->woo->session->certificate_id            = '';
			$this->woo->session->certificate_applied       = '';
			$this->woo->session->certificate_data          = '';
			$this->woo->session->exemption_applied         = '';
			$this->woo->session->cart_taxes                = array();
			$this->woo->session->backend_cart_taxes        = array();
			$this->woo->session->taxcloud_ids              = array();
			$this->woo->session->backend_location_mapping  = array();
			$this->woo->session->wootax_tax_total          = 0;
			$this->woo->session->wootax_shipping_tax_total = 0;

			$this->woo->session->save_data();

		}

	}

	/**
	 * Get ID last sent to TaxCloud given item ID
	 *
	 * @since 4.2
	 * @param $key (mixed) a key corresponding to a fee or cart item
	 */
	/*public function get_item_identifier( $key ) {

		$identifiers = $this->identifiers;

		if ( isset( $identifiers[ $key ] ) ) {
			return $identifiers[ $key ];
		}

		return $key;

	}*/

	/**
	 * Get index of item as sent to TaxCloud
	 *
	 * @since 4.2
	 * @param $item_id (int) an item id
	 */
	public function get_item_index( $item_id ) {

		if ( isset( $this->mapping_array[ $item_id ] ) ) {
			return $this->mapping_array[ $item_id ];
		} else {
			if ( $item_id == WOOTAX_SHIPPING_ITEM && version_compare( WOOCOMMERCE_VERSION, '2.2', '<' ) ) {
				return get_post_meta( $this->order_id, '_wootax_shipping_index', true );
			} else {
				return $this->order->get_item_meta( $item_id, '_wootax_index', true );
			}
		}

	}
	
	/**
	 * Removes the tax applied by WooTax from fees, cart items, and shipping
	 * 
	 * @since 4.2
	 */
	public function remove_tax() {
		
		//TODO: TEST WITH 2.1.x
		if ( version_compare( WOOCOMMERCE_VERSION, '2.2', '<' ) ) {
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
	 * Gets the total tax applied to an order item (fee, cart item, shipping)
	 *
	 * @since 4.2
	 * @param $item_id ID of the item
	 * @return int 0 if tax amount cannot be retrieved; otherwise, the tax amount as a float
	 */
	public function get_item_tax( $item_id ) {

		$tax = 0;

		if ( $item_id == WOOTAX_SHIPPING_ITEM ) { // This will occur for WooCommerce 2.1.x
			$tax = $this->shipping_tax_total;
		} else if ( $this->order->get_item_meta( $item_id, '_wootax_tax_amount', true ) ) {
			$tax = $this->order->get_item_meta( $item_id, '_wootax_tax_amount', true );
		}

		return $tax;

	}
	
	/**
	 * Applies tax to the item with given key 
	 *
	 * @since 4.2
	 * @param $item_id (int) ID of the item 
	 * @param $amt (float) the tax amount to apply
	 */
	private function apply_item_tax( $item_id, $amt ) {

		if ( $item_id == WOOTAX_SHIPPING_ITEM ) { // WooCommerce 2.1.x shipping 

			$this->shipping_tax_total = $this->shipping_tax_total + $amt;

		} else {

			// Calculate new tax values
			$line_subtotal_tax = get_metadata( 'order_item', $item_id, '_line_subtotal_tax', true ) + $amt;
			$line_tax 		   = get_metadata( 'order_item', $item_id, '_line_tax', true ) + $amt;
				
			// Save new tax values
			update_metadata( 'order_item', $item_id, '_line_tax', $line_tax );
			update_metadata( 'order_item', $item_id, '_line_subtotal_tax', $line_subtotal_tax );
			update_metadata( 'order_item', $item_id, '_wootax_tax_amount', $amt ); 

			// Update the "tax_data" array if we are dealing with WooCommerce 2.2+
			if ( version_compare( WOOCOMMERCE_VERSION, '2.2', '>=' ) ) {

				$tax_data = get_metadata( 'order_item', $item_id, '_line_tax_data', true );

				if ( isset( $tax_data['total'] ) ) {
					// Cart items/shipping
					$tax_data['subtotal'][$this->wootax_rate_id] = $amt;
					$tax_data['total'][$this->wootax_rate_id]    = $amt;
				} else {
					// Fee
					$tax_data[$this->wootax_rate_id] = $amt;
				}
				

				// Store update tax data
				update_metadata( 'order_item', $item_id, '_line_tax_data', $tax_data );

			}

		}

	}
	
	/**
	 * Remove tax applied by WooTax from an item
	 * 
	 * @since 4.2
	 * @param $item_id (int) the ID of the item
	 */
	private function remove_item_tax( $item_id ) {
		
		// Fetch applied tax
		$applied_tax = $this->get_item_tax( $item_id );

		if ( $applied_tax != 0 ) {

			if ( $item_id == WOOTAX_SHIPPING_ITEM ) { // WooCommerce 2.1.x shipping charges

				$this->shipping_tax_total = 0;

			} else {

				// Calculate new tax values
				$line_subtotal_tax = get_metadata( 'order_item', $item_id, '_line_subtotal_tax', true ) - $applied_tax;
				$line_tax          = get_metadata( 'order_item', $item_id, '_line_tax', true ) - $applied_tax;

				// Zero out tax if the calculated value is negative
				$line_subtotal_tax = $line_subtotal_tax < 0 ? 0 : $line_subtotal_tax;
				$line_tax          = $line_tax < 0 ? 0 : $line_tax;

				// Save new tax values
				update_metadata( 'order_item', $item_id, '_line_tax', $line_tax );
				update_metadata( 'order_item', $item_id, '_line_subtotal_tax', $line_subtotal_tax );
				update_metadata( 'order_item', $item_id, '_wootax_tax_amount', 0 );

				// Update the "tax_data" array if we are dealing with WooCommerce 2.2+
				if ( version_compare( WOOCOMMERCE_VERSION, '2.2', '>=' ) ) {

					$tax_data = get_metadata( 'order_item', $item_id, '_line_tax_data', true );

					if ( isset( $tax_data['total'] ) ) {
						// Cart items/shipping
						$tax_data['subtotal'][$this->wootax_rate_id] = 0;
						$tax_data['total'][$this->wootax_rate_id]    = 0;
					} else {
						// Fee
						$tax_data[$this->wootax_rate_id] = 0;
					}

					// Store update tax data
					update_metadata( 'order_item', $item_id, '_line_tax_data', $tax_data );

				}

			}

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
	 * Stores an array of items in TaxCloud-friendly format and organized by location key in the lookup_data property
	 *
	 * @since 4.2
	 */
	private function generate_lookup_data( $items = NULL ) {

		// Fetch order items
		$order_items = $items;

		// Exit if we do not have any items
		if ( count( $order_items ) == 0 ) {
			$this->lookup_data = $this->mapping_array = array();

			return;
		}

		// Determine the state where the customer is located
		$customer_state = $this->destination_address['State'];

		// Initialize some vars that we need for the foreach loop below
		$data = $mapping_array = $counters_array = $fee_items = $shipping_items = $cart_ids = $order_ids = array();
		$fee_counter = 0;
		$taxcloud_id = $this->taxcloud_ids;

		// This will hold the ID of the first found origin address/location for this order; Fees and shipping chars will be attached to it
		$first_found = false;

		// Loop through order items; group items by their shipping origin address and format data for tax lookup
		foreach ( $order_items as $item_key => $item ) {

			$item_id = $item['ItemID'];
			$type    = $item['Type'];

			switch( $type ) {

				case 'cart':
					// Fetch shipping origin addresses for this product
					$origin_addresses = fetch_product_origin_addresses( $item_id );
					$address_found = empty( $this->default_address ) ? 0 : $this->default_address;
					
					/**
					 * Attempt to find proper origin address
					 * If there is more than one address available, we will use the first address that occurs in the customer's state 
					 * If there no shipping location in the customer's state, we will use the default origin address
					 * Developers can modify the selected shipping origin address using the wootax_origin_address filter
					 */
					if ( count($origin_addresses) == 1 ) {
						// There is only one address ID to fetch, with index 0
						$address_found = $origin_addresses[0];				
					} else {
						// Find an address in the customer's state if possible
						foreach ( $origin_addresses as $key ) {
							if ( isset( $this->addresses[$key]['state'] ) && $this->addresses[$key]['state'] == $customer_state ) {
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
					if ( !isset( $data[$address_found] ) || !is_array( $data[$address_found] ) ) {
						$data[$address_found] = array();
					}

					// Initialize counter for this location if necessary
					if ( !isset( $counters_array[$address_found] ) ) {
						$counters_array[$address_found] = 0;
					}

					// Initialize mapping array for this location if necessary
					if ( !isset( $mapping_array[$address_found] ) ) {
						$mapping_array[$address_found] = array();
					}

					// Update mapping array
					$mapping_array[$address_found][] = $item_id;/*array(
						'id'    => $item_id, 
						'index' => $counters_array[$address_found], 
						'type'  => 'cart',
						'key'   => $item_key,
					);*/

					// Update item Index
					$item['Index'] = $counters_array[$address_found];

					// Unset "type" value
					unset( $item['Type'] );

					// Add formatted item data to the $data array
					$data[$address_found][] = $item;

					// Increment counter
					$counters_array[$address_found]++;
				break;

				case 'shipping':
					// Push this item to the shipping array; the cost of shipping will be attached to the first daughter order later on
					$shipping_items[$item_id] = $item;
				break;

				case 'fee':
					// Push this item to the fee array; it will be attached to the first daughter order later on
					$fee_items[$item_id] = $item;

					// Update fee counter
					$fee_counter++;
				break;

			}

		}

		// Attach shipping items and fees to the first daughter order
		if ( $first_found !== false ) {
			foreach ( $shipping_items + $fee_items as $key => $item ) {

				// Get new item index
				$index = $counters_array[$first_found];

				// Add to items array (Type index not included here)
				$data[$first_found][] = array(
					'Index' => $index,
					'ItemID' => $item['ItemID'],
					'TIC' => $item['TIC'],
					'Price' => $item['Price'],
					'Qty' => $item['Qty'],
				);

				// Update mapping array
				$mapping_array[$first_found][$index] = $key;/*array(
					'id'    => $key, 
					'key'   => $key,
					'index' => $index, 
					'type'  => $item['Type'],
				);*/

				// Increment counter
				$counters_array[$first_found]++;

			}
		}

		// Store data and save
		$this->lookup_data   = $data;
		$this->mapping_array = $mapping_array;
		//$this->taxcloud_ids  = $taxcloud_ids;

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
	 * Perform a lookup for the order and update tax totals
	 *
	 * @since 4.2
	 * @param $items an array of CartItems
	 * @param $type_array an array mapping item IDs to item types (cart/shipping)
	 */
	public function do_lookup( $items, $type_array ) {
		
		// Fire request if we are ready to do so
		if ( $this->ready_for_lookup() ) {

			// Remove currently applied taxes (delete order item instead?)
			$this->remove_tax();

			// Generate lookup data
			$this->generate_lookup_data( $items );

			// Retrieve validated destination addresses
			$destination_address = validate_address( $this->destination_address );

			// Used in every tax lookup
			$tax_array    = $all_cart_items = $lookup_data = $location_mapping_array = $new_mapping_array = array();
			$tax_total    = $shipping_tax_total = 0;
			$taxcloud_ids = $this->taxcloud_ids;

			// Fetch some information about the order
			$exempt_cert         = $this->get_exemption_certificate();
			$customer_id         = $this->get_customer_id();
			$delivered_by_seller = $this->is_delivered_by_seller();
			
			// Loop through locations in lookup_data array and send a Lookup request for each
			foreach ( $this->lookup_data as $location_key => $items ) {

				$lookup_data[$location_key] = $items;

				// Fetch cart_id
				$cart_id = isset( $this->taxcloud_ids[$location_key]['cart_id'] ) ? $this->taxcloud_ids[$location_key]['cart_id'] : '';

				// Get the origin address
				$origin_address = validate_address( $this->get_origin_address( $location_key ) );

				// Build request array 
				$req = array(
					'customerID'        => $customer_id, 
					'cartID'            => $cart_id, 
					'cartItems'         => $items, 
					'origin'            => $origin_address, 
					'destination'       => $destination_address, 
					'deliveredBySeller' => $delivered_by_seller, 
					'exemptCert'        => $exempt_cert,
				);	

				// Send Lookup request 
				$res = $this->taxcloud->Lookup( $req );

				if ( !$this->taxcloud->isError( $res->LookupResult ) ) {

					// Initialize some vars
					$cart_items = $res->LookupResult->CartItemsResponse->CartItemResponse;

					// Store the returned CartID for later use
					$taxcloud_ids[$location_key]['cart_id'] = $res->LookupResult->CartID;

					// If cart_items only contains one item, it will not be an array. In that case, convert to an array to avoid 
					// the need for separate code to handle this case
					if ( !is_array( $cart_items ) ) {
						$cart_items = array( $cart_items );
					}

					// Loop through items and update tax amounts
					foreach ( $cart_items as &$cart_item ) {

						// Fetch item info
						$index   = $cart_item->CartItemIndex;
						$item_id = $this->mapping_array[$location_key][$index];
						$type    = isset( $type_array[ $item_id ] ) ? $type_array[ $item_id ] : 'cart';
						$tax     = $cart_item->TaxAmount;

						// Update item tax
						$this->apply_item_tax( $item_id, $tax );

						// Keep track of cart tax/shipping tax totals
						if ( $type == 'cart' ) {
							$tax_total += $tax;
						} else {
							$shipping_tax_total += $tax;
						}

						// Add ItemID property $this->ajax_update_order_tax() can assign tax values as expected
						$cart_item->ItemID = $item_id;
						
						// Build tax array
						//$tax_array[$location_key][$mapping_id] = $tax;

						// Map itemID to location key
						$location_mapping_array[$item_id] = $location_key;

					}

				} else {
					// Return error
					return 'An error occurred while performing tax lookup. TaxCloud said: '. $this->taxcloud->getErrorMessage();
				}

				// Add cart_items to all_cart_items array
				$all_cart_items[] = $cart_items;

			}

			// Save updated tax totals
			$this->update_tax_totals( $tax_total, $shipping_tax_total );

			// Update location mapping array
			$this->location_mapping_array = $location_mapping_array;

			// Store lookup data with updated cart ids
			$this->lookup_data = $lookup_data;

			// Update TaxCloud IDs array
			$this->taxcloud_ids = $taxcloud_ids;

			// Reset identifiers array (only useful before first lookup from "edit order" screen)
			$this->identifiers = array();

			// Store mapping array in reverse order (map item ids to item indexes)
			$new_mapping_array = array();

			foreach ( $this->mapping_array as $location => $mappings ) {
				foreach ( $mappings as $item_index => $item_id ) {

					if ( !isset( $new_mapping_array[ $location ] ) ) {
						$new_mapping_array[ $location ] = array();
					}

					$new_mapping_array[ $location ][ $item_id ] = $item_index;

				}
			}

			$this->mapping_array = $new_mapping_array;

			// Return array of CartItems
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
	 * Get information about the exemption certificate to be applied to this order (if one exists)
	 *
	 * @since 4.2
	 * @return an array of information about the certificate, the certificate's ID, or null
	 */
	public function get_exemption_certificate() {

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
		} else if ( !is_bool( $this->exemption_applied ) ) {
			$certificate_data = $this->exemption_applied;
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
		$address['Address1'] = $this->addresses[ $location_key ]['address_1'];
		$address['Address2'] = $this->addresses[ $location_key ]['address_2'];
		$address['Country']  = $this->addresses[ $location_key ]['country'];
		$address['State']    = $this->addresses[ $location_key ]['state'];
		$address['City']     = $this->addresses[ $location_key ]['city'];
		$address['Zip5']     = $this->addresses[ $location_key ]['zip5'];
		$address['Zip4']     = $this->addresses[ $location_key ]['zip4'];

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
		$tax_based_on = wootax_get_option( 'tax_based_on' );

		// Return origin address if this is a local pickup order
		if ( $this->is_local_pickup() || $tax_based_on == 'base' ) {
			return $this->get_origin_address( $this->default_address );
		}

		// Initialize blank address array
		$address = array();
		
		// Construct final address arraya
		$parsed_zip = parse_zip( $_POST['postcode'] );

		$address['Address1'] = '';//$address_1;
		$address['Address2'] = '';//$address_2;
		$address['Country']  = $_POST['country'];
		$address['State']    = $_POST['state'];
		$address['City']     = $_POST['city'];
		$address['Zip5']     = $parsed_zip['zip5'];
		$address['Zip4']     = $parsed_zip['zip4']; 

		// Return final address
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
		if ( $shipping_method == 'local_pickup' || $shipping_method == 'Local Pickup' ) {
			return true;
		} else {
			return false;
		}

	}
	
	/** 
	 * Determines if an order is ready for a lookup request
	 * For an order to be "ready," three criteria must be met:
	 * - At least one origin address is added to the site
	 * - The customer's full address is available
	 * - The order has not already been captured
	 *
	 * @since 4.2
	 * @return boolean true if the order is ready for a tax lookup; otherwise, false
	 */
	private function ready_for_lookup() {

		// Check for orders that are already captured
		if ( $this->captured ) {
			return false;
		}

		// Verify that one origin address (at least) is available for use
		if ( !is_array( $this->addresses ) || count( $this->addresses ) == 0 ) {
			return false;
		}

		// Check for a valid destinaton address
		if ( !$this->taxcloud->isValidAddress( $this->destination_address, true ) ) {
			return false;
		}
		
		return true;

	}
	
	/**
	 * Get customerID for order
	 */
	private function get_customer_id() {

		global $current_user;

		// Fetch and/or generate customerID
		if ( $this->customer_id == false ) {
			// Generate new customer id if one isn't associated with the order already
			$current_user_id = $this->order->user_id;

			if ( $current_user_id == 0 ) {
				$customer_id = wp_generate_password( 32, false );
			} else {
				$customer_id = $current_user_id;
			}
			
			// Save generated ID
			$this->customer_id = $customer_id;
			
			return $customer_id;
		} else {
			return $this->customer_id;
		}
	
	}
	
	/**
	 * Completes an order by sending an AuthorizeWithCapture request to TaxCloud
	 *
	 * @since 4.2
	 * @param $order_id the WooCommerce order ID
	 */
	public function complete( $order_id ) {

		// Load order
		$this->load_order( $order_id );

		// Exit if the order has already been captured or contains no taxable items 
		if ( $this->captured || !is_array( $this->lookup_data ) ) { 
			return;
		}

		// Instantiate WC_WooTax_TaxCloud object
		$this->taxcloud = get_taxcloud();
		
		// Loop orders and mark as captured
		foreach ( $this->taxcloud_ids as $address_key => $data ) {

			// Get cart id/order_id
			$cart_id  = $data['cart_id'];
			$order_id = $data['order_id'];

			// Send captured request
			$authorized_date = date( 'c' );

			$req = array(
				'cartID' => $cart_id, 
				'customerID' => $this->customer_id, 
				'orderID' => $order_id, 
				'dateAuthorized' => $authorized_date, 
				'dateCaptured' => $authorized_date,
			);

			$res = $this->taxcloud->AuthorizedWithCapture( $req );

			// Check for errors
			if ( $this->taxcloud->isError( $res->AuthorizedWithCaptureResult ) ) {
				wootax_add_flash_message( 'There was an error while marking the order as Captured. TaxCloud said: '. $this->taxcloud->getErrorMessage() );
				return;
			}

		}

		// Mark order as captured
		$this->captured = true;

	}
	
	/** 
	 * Refunds an entire order
	 * Resets order meta and sends Refunded request to TaxCloud
	 *
	 * @since 4.2
	 * @param $order_id the WooCommerce order ID
	 */
	public function refund( $order_id ) {

		// Load order
		$this->load_order( $order_id );

		// Exit if the order has already been refunded or has not yet been captured
		if ( $this->refunded ) {
			return;
		} else if( !$this->captured ) {
			wootax_add_flash_message('<strong>WARNING:</strong> The order was not refunded through TaxCloud because it has not been captured yet. To fix this issue, save the order as completed before marking it as refunded.', 'update-nag');
			return;
		}
		
		// Instantiate WC_WooTax_TaxCloud object
		$this->taxcloud = get_taxcloud();

		// Loop through daughter orders and mark as refunded
		foreach ( $this->taxcloud_ids as $address_key => $ids ) {

			// Get order ID
			$order_id = $ids['order_id'];

			// Send Returned request
			$date = new DateTime( 'NOW' );
			
			$req = array(
				'retCartItems' => NULL, 
				'returnedDate' => $date->format( DateTime::ATOM ), 
				'orderID'      => $order_id,
			);
			
			$res = $this->taxcloud->Returned( $req );
			
			// Check for errors
			if ( $this->taxcloud->isError( $res->ReturnedResult ) ) {
				wootax_add_flash_message( 'There was an error while refunding the order. TaxCloud said: '. $this->taxcloud->getErrorMessage() );
				break;
			}

		}

		// Remove applied tax completely
		$this->remove_tax();
		
		// Reset meta values to default 
		foreach ( $this->defaults as $key => $val ) {
			$this->$key = $val;
		}
		
		// Mark order as refunded
		$this->refunded = true;

	}

	/**
	 * Refunds specific order items
	 * 
	 * @since 4.2
	 * @param Array $refund_items: an array of CartItems
	 * @return bool true or error message
	 */
	public function refund_items( $refund_items ) {
		
		if ( !$this->captured ) {
			return 'You must set this order\'s status to "completed" before refunding any items.';
		}

		if ( !is_array( $refund_items ) ) {
			return 'Error: No items to refund. Please try again.';
		}

		if ( count( $refund_items ) == 0 ) {
			return true; // Bypasses the entire refund attempt when there are no items to refund
		}

		// Instantiate WC_WooTax_TaxCloud object
		$this->taxcloud = get_taxcloud();
		$locations      = array();

		// Construct mapping array
		$mapping_array = $this->mapping_array;

		if ( count( $mapping_array ) == 0 ) {

			foreach ( $refund_items as $location => $items ) {

				$mapping_array[ $location ] = array();

				foreach ( $items as $item ) {
					$mapping_array[ $location ][ $item['ItemID'] ] = $this->get_item_index( $item['ItemID'] );
				}

			}

		} 

		// Refund items as necessary
		foreach ( $this->taxcloud_ids as $address_key => $ids ) {

			if ( isset( $refund_items[ $address_key ] ) ) {

				$items = array();

				// Get items in appropriate format
				foreach ( $refund_items [ $address_key ] as $item ) {

					$item_id = $item['ItemID'];
					$item['Index'] = $mapping_array[ $address_key ][ $item_id ];
					$items[] = $item;

				}

				// Get oID
				$order_id = $this->taxcloud_ids[ $address_key ]['order_id'];

				// Send Returned request
				$date = new DateTime('NOW');

				$req = array(
					'cartItems'    => $items, 
					'returnedDate' => $date->format( DateTime::ATOM ), 
					'orderID'      => $order_id,
				);	

				// Send request
				$res = $this->taxcloud->Returned( $req );

				// Check for errors
				if ( $this->taxcloud->isError( $res->ReturnedResult ) ) {
					
					return $this->taxcloud->getErrorMessage();
					
				} else {
					
					// Set the order status to refunded so the user cannot calculate the tax due anymore
					$this->refunded = true;

					// Save refunded items array
					$this->refunded_items = $refunded_items;

					return true;
					
				}

			}
			
		}
		
	}
	
	/**
	 * Update the cart tax total for a given order
	 *
	 * @since 4.2
	 * @param $new_tax - new value for WooTax cart tax
	 */
	private function update_cart_tax_total( $new_tax ) {

		// Fetch cart tax added by WooTax
		$current_tax = $this->tax_total;

		// Get current cart tax value as float
		$cart_tax_total = (float) get_post_meta( $this->order->id, '_order_tax', true );

		// Calculate new cart tax value
		$new_cart_tax_total = $cart_tax_total == 0 ? $new_tax : ( $cart_tax_total - $current_tax ) + $new_tax;

		// Zero tax total if we have a negative result
		$new_cart_tax_total = $new_cart_tax_total < 0 ? 0 : $new_cart_tax_total;

		// Update order meta to reflect changes
		update_post_meta( $this->order->id, '_order_tax', $new_cart_tax_total );

		// Update internal "tax_total" property
		$this->tax_total = $new_tax;

	}
	
	/**
	 * Update the shipping tax total for a given order
	 *
	 * @since 4.2
	 * @param $new_tax - new value for WooTax shipping tax
	 */
	private function update_shipping_tax_total( $new_tax ) {

		// Fetch shipping tax added by WooTax
		$current_tax = $this->shipping_tax_total;
		
		// Get current shipping tax total as float
		$shipping_tax_total = (float) get_post_meta( $this->order->id, '_order_shipping_tax', true );

		// Calculate new tax 
		$new_shipping_tax_total = $shipping_tax_total == 0 ? $new_tax : ($shipping_tax_total - $current_tax) + $new_tax;

		// Zero tax if we have a negative result
		$new_shipping_tax_total = $new_shipping_tax_total < 0 ? 0 : $new_shipping_tax_total;

		// Update order meta to reflect changes
		update_post_meta( $this->order->id, '_order_shipping_tax', $new_shipping_tax_total );

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
	private function update_tax_totals( $cart_tax  = 0, $shipping_tax  = 0 ) {
		
		// Update cart tax
		$this->update_cart_tax_total( $cart_tax );

		// Update shipping tax
		$this->update_shipping_tax_total( $shipping_tax );

		// Update tax item if necessary
		$this->update_tax_item();
		
	}
	
	/**
	 * Updates tax item added by WooTax to a Woo order
	 * Adds one if we haven't already done so
	 *
	 * @since 4.2
	 */
	private function update_tax_item() {

		global $wpdb;

		// Add a new tax item if we haven't already added one
		if ( $this->tax_item_id == 0 || $this->tax_item_id == NULL ) {

			// Add new tax item
			$wpdb->insert( "{$wpdb->prefix}woocommerce_order_items", array(
				'order_item_type' => 'tax', 
				'order_item_name' => 'WOOTAX-RATE-DO-NOT-REMOVE', 
				'order_id'        => $this->order->id
			) );

			// Update tax item id
			$this->tax_item_id = $wpdb->insert_id;

		}
		
		// Update tax item meta
		$item_id = $this->tax_item_id;

		update_metadata( 'order_item', $item_id, 'rate_id', $this->wootax_rate_id );
		update_metadata( 'order_item', $item_id, 'label', 'Sales Tax' );
		update_metadata( 'order_item', $item_id, 'name', 'Sales Tax' );
		update_metadata( 'order_item', $item_id, 'compound', true );
		update_metadata( 'order_item', $item_id, 'tax_amount', $this->tax_total );
		update_metadata( 'order_item', $item_id, 'shipping_tax_amount', $this->shipping_tax_total );

		// Added for WooCommerce Subscriptions support
		if ( class_exists( 'WC_Subscriptions' ) ) {
			update_metadata( 'order_item', $item_id, 'cart_tax', $this->tax_total );
			update_metadata( 'order_item', $item_id, 'shipping_tax', $this->shipping_tax_total );
		}

	}
	
	/**
	 * Fetch order shipping method
	 * The current shipping method is either retrieved via POST or WC_Order::get_shipping_method
	 *
	 * @since 4.2
	 * @return chosen shipping method (string)
	 */
	private function get_order_shipping_method() {

		if ( isset( $_POST['shipping_methods'] ) && !empty( $_POST['shipping_methods'] ) ) {
			$shipping_methods = !strstr( $_POST['shipping_methods'], ',' ) ? $_POST['shipping_methods'] : explode( ',', $_POST['shipping_methods'] );

			// If there are multiple methods, return the first
			return is_array( $shipping_methods ) ? $shipping_methods[0] : $shipping_methods;
		} else {
			return $this->order->get_shipping_method();
		}

	}
	
	/**
	 * Get order status
	 * Four return values: Invalid Order, Pending Capture, Captured, Returned
	 *
	 * @since 4.2
	 * @return order status (string)
	 */
	public function get_status() {

		// Return 'Invalid Order' if the ID is not defined
		if ( !$this->order_id ) {
			return 'Invalid Order';
		}
			
		// Order has been returned
		if ( $this->refunded == true ) {
			return 'Refunded';
		}
			
		// Return 'Pending Capture' if captured is set to bool false
		if ( $this->captured == false ) {
			return 'Pending Capture';
		}
		
		// Return 'Captured' if captured is set to bool true
		if ( $this->captured == true )  {
			return 'Captured';
		}

	}

	/**
	 * Added for WooCommerce 2.2 support
	 * Updates the "taxes" meta value for shipping items
	 * Assumes that only one shipping method is used per order
	 *
	 * @since 4.2
	 * @param $order_id the id of the WooCommerce order
	 * @param $item_id the id of the item inserted into the database
	 * @param $shipping_rate the actual shipping rate (object)
	 */
	public function add_shipping_tax( $order_id, $item_id, $shipping_rate )  {

		$this->load_order( $order_id );

		$shipping_tax = $this->shipping_tax_total;

		// Fetch taxes array
		$taxes = array_map( 'wc_format_decimal', $shipping_rate->taxes );

		// Add WooTax tax rate
		$taxes[ $this->wootax_rate_id ] = $shipping_tax;

		// Update meta
		update_metadata( 'order_item', $item_id, 'taxes', $taxes );

	}

	/**
	 * Update order tax via AJAX
	 * Called on hook 'wootax-update-tax'
	 *
	 * @since 4.2
	 * @return JSON object with status (error | success) and status message
	 */
	public function ajax_update_order_tax() {

		global $wpdb;

		$order_id = absint( $_POST['order_id'] );
		$country  = strtoupper( esc_attr( $_POST['country'] ) );

		// Create a new order if need be (this will happen when orders are added manually in the backend)
		/*if ( get_wootax_oid( $order_id ) == '' ) {
			$this->create_new_order( $order_id );
			$order_id = get_wootax_oid( $order_id );
		} else if ( $order_id != get_wootax_oid( $order_id ) ) {
			$order_id = get_wootax_oid( $order_id );
		}*/

		// Set up WC_WooTax_Order object
		$this->load_order( $order_id );

		// Instantiate WC_WooTax_TaxCloud object
		$this->taxcloud = get_taxcloud();
		
		// Update customer address
		$this->destination_address = $this->get_destination_address();
		$initial_tax_item_id       = $this->tax_item_id;

		// Use default tax calculation mechanism for international orders; otherwise, use WooTax
		if ( $country != 'US' ) {

			return; // Returning here allows WC_AJAX::calc_line_taxes to execute.

		} else {

			if ( version_compare( WOOCOMMERCE_VERSION, '2.2', '>=' ) ) {
				
				// Parse items array from JSON
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

					if ( get_post_type( $this->order->get_item_meta( $item_id, '_product_id', true ) ) == 'product' ) {
						$items['order_item_qty'][$item_id] = isset( $item['quantity'] ) ? $item['quantity'] : 1;
					}

					$items['line_total'][$item_id] = $item['line_total'];

				}

				// Add item for shipping cost
				if ( isset( $_POST['shipping'] ) && $_POST['shipping'] != 0 ) {

					$items['shipping_cost'][WOOTAX_SHIPPING_ITEM] = $_POST['shipping'];
					$items['shipping_method_id'][]                = WOOTAX_SHIPPING_ITEM;

				}

			}

			$order_items = array();
			$final_items = array();

			// Add cart items and fees
			$order_items = array_merge( $items['order_item_id'], $order_items );

			// Add shipping items
			$order_items = array_merge( $items['shipping_method_id'], $order_items );

			// Construct items array from POST data
			foreach ( $order_items as $item_id ) {

				$product_id = $this->order->get_item_meta( $item_id, '_product_id', true );

				if ( get_post_type( $product_id ) == 'product' ) {

					$product = new WC_Product( $product_id );
						
					if ( !$product->is_taxable() ) {
						continue;
					}

				} 

				$qty = 1;

				if ( isset( $items['order_item_qty'][$item_id] ) ) {
					$qty = $items['order_item_qty'][$item_id];
				}

				if ( in_array( $item_id, $items['shipping_method_id'] ) ) {
					// Shipping method
					$tic  = WOOTAX_SHIPPING_TIC;
					$cost = $items['shipping_cost'][$item_id];
					$type = 'shipping';
				} else if ( isset( $items['order_item_qty'][$item_id] ) ) {
					// Cart item
					$tic  = get_post_meta( $product_id, 'wootax_tic', true );
					$cost = $items['line_total'][$item_id];
					$type = 'cart';
				} else {
					// Fee
					$tic  = WOOTAX_FEE_TIC;
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
					$old_taxes[ $item_id ] = $this->get_item_tax( $item_id );
					
					// Add to items array 
					$item_data = array(
						'Index'  => '', // Leave Index blank because it is reassigned when WooTaxOrder::generate_lookup_data() is called
						'ItemID' => $item_id, 
						'Qty'    => $qty, 
						'Price'  => $unit_price,	
						'Type' => $type,
					);	

					if ( !empty( $tic ) && $tic ) {
						$item_data['TIC'] = $tic;
					}

					$final_items[] = $item_data;

				}

			}

		}
		
		// Send lookup request using the generated items and mapping array
		$res = $this->do_lookup( $final_items, $type_array );

		// Convert response array to be sent back to client
		// @see WC_AJAX::calc_line_taxes() for inspiration
		// TODO: UPDATE SO OTHER TAX RATES ARE INCLUDED IN CALCS?

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

					if ( in_array( $id, $items['shipping_method_id'] ) ) {
						$items['shipping_taxes'][$id][$this->wootax_rate_id] = $tax;
					} else {
						$items['line_tax'][$id][$this->wootax_rate_id] = $tax;
						$items['line_subtotal_tax'][$id][$this->wootax_rate_id] = $tax;
					}

				}

				$items['order_taxes'][$this->tax_item_id] = absint($this->wootax_rate_id); // Correct?

				wc_save_order_items( $this->order->id, $items );

				// Return HTML items
				$order = $this->order;
				$data  = get_post_meta( $order->id );
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

					if ( $id == WOOTAX_SHIPPING_ITEM ) {
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
				$taxes     = $this->order->get_taxes();

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
			die( json_encode( array( 
				'status'  => 'error', 
				'message' => $res,
			) ) ); 
		}

	}

	/**
	 * Add correct tax to order post-checkout; store tax item ID
	 *
	 * @since 4.2
	 * @param $order_id (int) the order ID, $item_id (tax item id), $tax_rate_id (tax rate id; look for wootax_rate_id)
	 */
	public function add_order_tax_rate( $order_id, $item_id, $tax_rate_id ) {

		if( $tax_rate_id != get_option( 'wootax_rate_id' ) ) {
			return;
		}

		// Store tax item id
		$this->tax_item_id = $item_id;

		// Update tax amount to match that for this order
		wc_add_order_item_meta( $item_id, 'tax_amount', wc_format_decimal( $this->tax_total ) );
		wc_add_order_item_meta( $item_id, 'shipping_tax_amount', wc_format_decimal( $this->shipping_tax_total ) );

	}

	/**
	 * Setter: Directly updates order meta values
	 * 
	 * @since 4.2
	 */
	public function __set( $key, $value ) {

		update_post_meta( $this->order_id, '_wootax_'. $key, $value );

	}
	
	/**
	 * Getter: Returns meta key OR default value if the option has not been set
	 *
	 * @since 4.2
	 */
	public function __get( $key ) {

		if ( !isset( $this->order_id ) ) {
			return NULL;
		}
		
		$meta_value = get_post_meta( $this->order_id, '_wootax_' . $key, true );
		$meta_value = ( $meta_value != false ) ? $meta_value : ( isset( $this->defaults[$key] ) ? $this->defaults[$key] : NULL );
			
		return $meta_value;

	}

}

// Initialize WC_WooTax_Order object 
add_action( 'init', 'initialize_order_object' );

function initialize_order_object() {
	global $WC_WooTax_Order;

	$WC_WooTax_Order = new WC_WooTax_Order();
}