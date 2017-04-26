<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Checkout.
 *
 * Responsible for computing the sales tax due during checkout.
 *
 * @author 	Simple Sales Tax
 * @package SST
 * @since 	5.0
 */
class SST_Checkout {

	/**
	 * @var Address Destination address.
	 * @since 5.0
	 */
	protected $destination_address;
	
	/**
	 * @var int Customer ID.
	 * @since 5.0
	 */
	protected $customer_id;

	/**
	 * @var string TaxCloud API Login ID.
	 * @since 5.0
	 */
	protected $taxcloud_id;

	/**
	 * @var string TaxCloud API key.
	 * @since 5.0
	 */
	protected $taxcloud_key;

	/**
	 * Constructor: Initialize hooks.
	 *
	 * @since 5.0
	 */
	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'woocommerce_checkout_after_customer_details', array( $this, 'output_exemption_form' ) );
		add_action( 'woocommerce_calculate_totals', array( $this, 'calculate_tax_totals' ) );
		add_filter( 'woocommerce_cart_hide_zero_taxes', array( $this, 'hide_zero_taxes' ) );
		add_action( 'woocommerce_new_order', array( $this, 'add_order_meta' ) );
		add_action( 'woocommerce_resume_order', array( $this, 'add_order_meta' ) );
		// add_action( 'woocommerce_checkout_update_order_meta', array( __CLASS__, 'maybe_capture_order' ), 15, 1 );
	}

	/**
	 * Should the Sales Tax line item be hidden if no tax is due?
	 *
	 * @since 5.0
	 *
	 * @return bool
	 */
	public function hide_zero_taxes() {
		return SST_Settings::get( 'show_zero_tax' ) != 'true';
	}

	/**
	 * Initialize class properties.
	 *
	 * @since 5.0
	 */
	protected function init() {
		$this->destination_address = SST_Addresses::get_destination_address();
		$this->customer_id         = WC()->session->get_customer_id();
		$this->taxcloud_id         = SST_Settings::get( 'tc_id' );
		$this->taxcloud_key        = SST_Settings::get( 'tc_key' );
	}

	/**
	 * Ready for a lookup?
	 *
	 * @since 5.0
	 *
	 * @return bool
	 */
	protected function ready() {
		// TODO: MODIFY TO ACCOUNT FOR MULTIPLE DESTINATIONS
		// IDEA: SKIP PACKAGE IF DEST ADDRESS IS INVALID.
		if ( is_null( $this->destination_address ) || ! SST_Addresses::is_valid( $this->destination_address ) )
			return false;
		if ( empty( $this->taxcloud_id ) || empty( $this->taxcloud_key ) )
			return false;
		return true;
	}

	/**
	 * Set the tax total for a cart item.
	 *
	 * @since 5.0
	 *
	 * @param string $key Cart item key.
	 * @param float $amt Sales tax for item.
	 */
	protected function set_item_tax( $key, $amt ) {
		// Update tax data
		$tax_data = WC()->cart->cart_contents[ $key ][ 'line_tax_data' ];

		$tax_data['subtotal'][ SST_RATE_ID ] = $amt;
		$tax_data['total'][ SST_RATE_ID ]    = $amt;

		WC()->cart->cart_contents[ $key ][ 'line_tax_data' ] = $tax_data;

		// Update totals
		WC()->cart->cart_contents[ $key ]['line_subtotal_tax'] = array_sum( $tax_data['subtotal'] );
		WC()->cart->cart_contents[ $key ]['line_tax']          = array_sum( $tax_data['total'] );
	}
	
	/**
	 * Set the sales tax total for a fee.
	 *
	 * @since 5.0
	 *
	 * @param int $key Fee key.
	 * @param float $amt Sales tax for fee.
	 */
	protected function set_fee_tax( $key, $amt ) {
		WC()->cart->fees[ $key ]->tax_data[ SST_RATE_ID ] = $amt;
		WC()->cart->fees[ $key ]->tax = array_sum( WC()->cart->fees[ $key ]->tax_data );
	}

	/**
	 * Calculate sales tax totals for the current cart.
	 *
	 * @since 5.0
	 */
	public function calculate_tax_totals() {
		$this->init();

		if ( ! $this->ready() ) {
			return;
		}

		/* Reset */
		$this->reset_taxes();

		$totals = array(
			'shipping' => 0,
			'cart'     => 0,
		);

		/* Perform tax lookup(s) */
		$packages = WC()->session->get( 'sst_packages', array() );

		foreach ( $this->get_packages() as $key => $package ) {
			/* Get tax for each cart item, using cached result if possible */
			$response = array();

			if ( array_key_exists( $key, $packages ) ) {
				$package = $packages[ $key ];
			} else {
				try {
					$package['request']  = $this->get_lookup_for_package( $package );
					$package['response'] = TaxCloud()->Lookup( $package['request'] );
					$package['cart_id']  = key( $package['response'] );

					/* Add to cache */
					$packages[ $key ] = $package;
				} catch ( Exception $ex ) {
					wc_add_notice( $ex->getMessage(), 'error' );
					return;
				}
			}

			/* Set tax for each cart item */
			$cart_items = current( $package['response'] );

			foreach ( $cart_items as $index => $tax_total ) {
				$info = $package['map'][ $index ];
				
				if ( 'shipping' == $info['type'] ) {
					$totals['shipping'] += $tax_total;
				} else {
					if ( 'cart' == $info['type'] )
						$this->set_item_tax( $info['cart_id'], $tax_total );
					else
						$this->set_fee_tax( $info['cart_id'], $tax_total );
					
					$totals['cart'] += $tax_total;
				}
			}
		}

		WC()->session->set( 'sst_packages', $packages );

		/* Set tax totals */
		$this->set_tax_totals( $totals['cart'], $totals['shipping'] );
	}
	
	/**
	 * Reset sales tax totals.
	 *
	 * @since 5.0
	 */
	protected function reset_taxes() {
		foreach ( WC()->cart->get_cart() as $cart_key => $item ) {
			$this->set_item_tax( $cart_key, 0 );
		}

		foreach ( WC()->cart->get_fees() as $key => $fee ) {
			$this->set_fee_tax( $key, 0 );
		}

		$this->set_tax_totals();
	}

	/**
	 * Filter items not needing shipping callback.
	 *
	 * @since 5.0
	 *
	 * @param  array $item
	 * @return bool
	 */
	protected function filter_items_not_needing_shipping( $item ) {
		$product = $item['data'];
		return $product && ! $product->needs_shipping();
	}

	/**
	 * Get only items that don't need shipping.
	 *
	 * @since 5.0
	 *
	 * @return array
	 */
	protected function get_items_not_needing_shipping() {
		return array_filter( WC()->cart->get_cart(), array( $this, 'filter_items_not_needing_shipping' ) );
	}

	/**
	 * Get the hash of a shipping package.
	 *
	 * @since 5.0
	 *
	 * @param  array $package
	 * @return string
	 */
	protected function get_package_hash( $package ) {
		$package_to_hash = $package;

		// Remove data objects so hashes are consistent
		foreach ( $package_to_hash['contents'] as $item_id => $item ) {
			unset( $package_to_hash['contents'][ $item_id ]['data'] );
		}

		return 'wc_ship_' . md5( json_encode( $package_to_hash ) . WC_Cache_Helper::get_transient_version( 'shipping' ) );
	}

	/**
	 * Get the shipping packages for this order. One Lookup will be issued for
	 * each package.
	 *
	 * We set a default origin address for each package by examining its contents.
	 * Developers can use the provided filter to change the origin address.
	 *
	 * @since 5.0
	 *
	 * @return array
	 */
	protected function get_packages() {
		$raw_packages = WC()->shipping->get_packages();

		if ( ! is_array( $raw_packages ) ) {
			return array();
		}

		$chosen_methods = WC()->session->get( 'chosen_shipping_methods' );
		$fees_added     = false;
		$packages       = array();

		foreach ( $raw_packages as $key => $package ) {
			/* Skip packages shipping outside of the US */
			if ( $package['destination']['country'] !== 'US' )
				continue;

			/* Convert destination address to Address object */
			try {
				$destination = SST_Addresses::verify_address( new TaxCloud\Address(
					$package['destination']['address'],
					$package['destination']['address_2'],
					$package['destination']['city'],
					$package['destination']['state'],
					substr( $package['destination']['postcode'], 0, 5)
				) );

				$package['destination'] = $destination;
			} catch ( Exception $ex ) {
				wc_add_notice( sprintf( __( "Can't calculate tax for order: %s", 'simplesalestax' ), $ex->getMessage() ), 'error' );
				return array();
			}

			/* Get default origin address. We use the most frequently occurring address
			 * by default. */
			$origins = array();

			foreach ( $package['contents'] as $cart_key => $item ) {
				$item_origins = SST_Product::get_origin_addresses( $item['product_id'] );

				foreach ( $item_origins as $origin ) {
					if ( ! isset( $origins[ $origin->getID() ] ) )
						$origins[ $origin->getID() ] = 1;
					else
						$origins[ $origin->getID() ]++;
				}
			}

			arsort( $origins );

			$origins = array_keys( $origins );
			
			/* Let devs change origin address */
			$origin = apply_filters( 'wootax_origin_address', SST_Addresses::get_address( array_pop( $origins ) ), $package );

			// Ew... should use proxy pattern to avoid this
			$package['origin'] = new TaxCloud\Address(
				$origin->getAddress1(),
				$origin->getAddress2(),
				$origin->getCity(),
				$origin->getState(),
				$origin->getZip5(),
				$origin->getZip4()
			);

			/* Add all fees to first package (devs can change with wootax_packages) */
			if ( apply_filters( 'wootax_add_fees', true ) && ! $fees_added ) {
				$package['fees'] = WC()->cart->get_fees();
				$fees_added      = true;
			} else {
				$package['fees'] = array();
			}

			/* Set shipping method */
			if ( isset( $chosen_methods[ $key ], $package['rates'][ $chosen_methods[ $key ] ] ) ) {
				$package['shipping'] = $package['rates'][ $chosen_methods[ $key ] ];
				unset( $package['rates'] );
			}

			/* Set certificate */
			$package['certificate'] = $this->get_certificate();

			$packages[ $this->get_package_hash( $package ) ] = $package;
		}

		/**
		 * Create a special package for all of the items that don't need shipping.
		 * These items will be excluded from the packages above.
		 */
		$digital_items = $this->get_items_not_needing_shipping();

		if ( ! empty( $digital_items ) ) {
			$package = array(
				'contents'      => $digital_items,
				'fees'          => array(),
				'contents_cost' => array_sum( wp_list_pluck( $digital_items, 'line_total' ) ),
				'certificate'   => $this->get_certificate(),
				'origin'        => null,
				'destination'   => null,
				'user'          => array(
					'ID' => get_current_user_id(),
				),
			);

			$origin = apply_filters( 'wootax_origin_address', SST_Addresses::get_default_address(), $package );
			
			$package['origin'] = new TaxCloud\Address(
				$origin->getAddress1(),
				$origin->getAddress2(),
				$origin->getCity(),
				$origin->getState(),
				$origin->getZip5(),
				$origin->getZip4()
			);

			try {
				/* Use billing address as destination for digital items */
				$package['destination'] = SST_Addresses::verify_address( new TaxCloud\Address(
					WC()->customer->get_billing_address(),
					WC()->customer->get_billing_address_2(),
					WC()->customer->get_billing_city(),
					WC()->customer->get_billing_state(),
					substr( WC()->customer->get_billing_postcode(), 0, 5)
				) );

				$packages[ $this->get_package_hash( $package ) ] = $package;
			} catch ( Exception $ex ) {
				wc_add_notice( sprintf( __( "Can't calculate tax for order: %s", 'simplesalestax' ), $ex->getMessage() ), 'error' );
				return array();
			}
		}

		/* Give developers a final opportunity to change the packages */
		$packages = apply_filters( 'wootax_packages', $packages );

		return $packages;
	}
	
	/**
	 * Generate a Lookup request for a given package.
	 *
	 * @since 5.0
	 *
	 * @param  array $package Package returned by get_packages().
	 * @return Lookup
	 */
	protected function get_lookup_for_package( &$package ) {
		// Info about items indexed by CartItem Index
		$package['map'] = array();

		$items = array();

		$tax_based_on = SST_Settings::get( 'tax_based_on' );

		// Add cart items
		foreach ( $package['contents'] as $cart_key => $item ) {
			$index   = sizeof( $items );
			$item_id = $item['variation_id'] ? $item['variation_id'] : $item['product_id'];
			$price   = apply_filters( 'wootax_taxable_price', $item['data']->get_price(), true, $item_id );
			$qty     = $item['quantity'];

			if ( 'line-subtotal' == $tax_based_on ) {
				$price = $price * $qty;
				$qty   = 1;
			}

			$items[] = new TaxCloud\CartItem(
				$index,
				$item_id,
				SST_Product::get_tic( $item['product_id'], $item['variation_id'] ),
				$price,
				$qty
			);

			$package['map'][ $index ] = array(
				'type'    => 'cart',
				'id'      => $item_id,
				'cart_id' => $cart_key
			);
		}

		// Add fees
		foreach ( $package['fees'] as $fee_index => $fee ) {
			$index   = sizeof( $items );
			$item_id = $fee->id;

			$items[] = new TaxCloud\CartItem(
				$index,
				$item_id,
				apply_filters( 'wootax_fee_tic', SST_DEFAULT_FEE_TIC ),
				apply_filters( 'wootax_taxable_price', $fee->amount, true, $item_id ),
				1
			);

			$package['map'][ $index ] = array(
				'type'    => 'fee',
				'id'      => $item_id,
				'cart_id' => $fee_index
			);
		}

		// Add shipping
		$shipping_rate = isset( $package['shipping'] ) ? $package['shipping'] : null;
		$shipping_id   = '';

		if ( ! is_null( $shipping_rate ) ) {
			$shipping_id     = $shipping_rate->method_id;
			$shipping_total  = apply_filters( 'wootax_taxable_price', $shipping_rate->cost, true, SST_SHIPPING_ITEM );

			if ( $shipping_total > 0 ) {
				$index = sizeof( $items );
				
				$items[] = new TaxCloud\CartItem(
					$index,
					SST_SHIPPING_ITEM,
					apply_filters( 'wootax_shipping_tic', SST_DEFAULT_SHIPPING_TIC ),
					$shipping_total,
					1
				);

				$package['map'][ $index ] = array(
					'type'    => 'shipping',
					'id'      => SST_SHIPPING_ITEM,
					'cart_id' => SST_SHIPPING_ITEM
				);
			}
		}

		// Construct Lookup
		$request = new TaxCloud\Request\Lookup(
			$this->taxcloud_id,
			$this->taxcloud_key,
			$package['user']['ID'],
			NULL,
			$items,
			$package['origin'],
			$package['destination'],
			SST_Shipping::is_local_delivery( $shipping_id ),
			$package['certificate']
		);

		return $request;
	}
	
	/**
	 * Set the cart and shipping tax totals.
	 *
	 * @since 5.0
	 *
	 * @param float $cart_tax (default: 0.0)
	 * @param float $shipping_tax (default: 0.0)
	 */
	protected function set_tax_totals( $cart_tax = 0.0, $shipping_tax = 0.0 ) {
		WC()->cart->taxes[ SST_RATE_ID ] = $cart_tax;
		WC()->cart->tax_total = WC_Tax::get_tax_total( WC()->cart->taxes );
		WC()->cart->shipping_taxes[ SST_RATE_ID ] = $shipping_tax;
		WC()->cart->shipping_tax_total = WC_Tax::get_tax_total( WC()->cart->shipping_taxes );
	}

	/**
	 * Get the customer exemption certificate.
	 *
	 * @since 5.0
	 *
	 * @return ExemptionCertificateBase|NULL
	 */
	protected function get_certificate() {
		if ( ! $_POST || ! isset( $_POST['post_data'] ) ) {
			return NULL;
		}

		$post_data = array();
		parse_str( $_POST['post_data'], $post_data );
		
		if ( isset( $post_data['tax_exempt'] ) && isset( $post_data['certificate_id'] ) ) {
			$certificate_id = sanitize_text_field( $post_data['certificate_id'] );
			return new TaxCloud\ExemptionCertificateBase( $certificate_id );
		}

		return NULL;
	}

	/**
	 * Reset session.
	 *
	 * @since 5.0
	 */
	protected function reset_session() {
		WC()->session->set( 'sst_packages', array() );
	}

	/**
	 * Save metadata when a new order is created. Create a new log entry if
	 * logging is enabled.
	 *
	 * @since 4.2
	 *
	 * @param int $order_id ID of new order.
	 */
	public function add_order_meta( $order_id ) { // TODO: TEST
		$order = new SST_Order( $order_id );

		$order->update_meta_data( 'tax_total', WC()->cart->get_tax_amount( SST_RATE_ID ) );
		$order->update_meta_data( 'shipping_tax_total', WC()->cart->get_shipping_tax_amount( SST_RATE_ID ) );
		$order->update_meta_data( 'customer_id', $this->customer_id );
		$order->update_meta_data( 'packages', json_encode( WC()->session->get( 'sst_packages' ) ) );

		if ( ( $exempt_cert = $this->get_certificate() ) ) {
			$order->update_meta_data( 'exempt_cert', json_encode( $exempt_cert ) );
		}

		$order->save();

		$this->reset_session();
	}

	/**
	 * Enqueue JS/CSS for exemption management interface.
	 *
	 * @since 5.0
	 */
	public function enqueue_scripts() {
		// CSS
		wp_enqueue_style( 'sst-modal', SST()->plugin_url() . '/assets/css/modal.css' );

		// JS
		wp_register_script( 'sst-backbone-modal', SST()->plugin_url() . '/assets/js/backbone-modal.js', array( 'underscore', 'backbone', 'wp-util' ), SST()->version );
		wp_register_script( 'sst-checkout', SST()->plugin_url() . '/assets/js/checkout.js', array( 'jquery', 'wp-util', 'underscore', 'backbone', 'sst-backbone-modal', 'jquery-blockui' ), SST()->version );
	}

	/**
	 * Does the customer have an exempt user role?
	 *
	 * @since 5.0
	 *
	 * @return bool
	 */
	protected function is_user_exempt() {
		$current_user = wp_get_current_user();
		$restricted   = SST_Settings::get( 'restrict_exempt' ) == 'yes';
		$exempt_roles = SST_Settings::get( 'exempt_roles', array() );
		$user_roles   = is_user_logged_in() ? $current_user->roles : array();
		$user_exempt  = count( array_intersect( $exempt_roles, $user_roles ) ) > 0;
		return ! $restricted || $user_exempt;
	}

	/**
	 * Should the exemption form be displayed?
	 *
	 * @since 5.0
	 */
	protected function show_exemption_form() {
		if ( SST_Settings::get( 'show_exempt' ) != 'true' ) {
			return false;
		}
		return $this->is_user_exempt();
	}

	/**
	 * Output Tax Details section of checkout form.
	 *
	 * @since 5.0
	 */
	public function output_exemption_form() {
		if ( ! $this->show_exemption_form() ) {
			return;
		}

		wp_localize_script( 'sst-checkout', 'SSTCertData', array(
			'certificates'             => SST_Certificates::get_certificates_formatted( false ),
			'add_certificate_nonce'    => wp_create_nonce( 'sst_add_certificate' ),
			'delete_certificate_nonce' => wp_create_nonce( 'sst_delete_certificate' ),
			'ajaxurl'                  => admin_url( 'admin-ajax.php' ),
			'seller_name'              => SST_Settings::get( 'company_name' ),
			'images'                   => array(
				'single_cert'  => SST()->plugin_url() . '/assets/img/sp_exemption_certificate750x600.png',
				'blanket_cert' => SST()->plugin_url() . '/assets/img/exemption_certificate750x600.png',
			),
			'strings'                  => array(
				'delete_failed'      => __( 'Failed to delete certificate', 'simplesalestax' ),
				'add_failed'         => __( 'Failed to add certificate', 'simplesalestax' ),
				'delete_certificate' => __( 'Are you sure you want to delete this certificate? This action is irreversible.', 'simplesalestax' ), 
			),
		) );

		wp_enqueue_script( 'sst-checkout' );

		wc_get_template( 'html-certificate-table.php', array(
			'checked'  => $_GET && $this->is_user_exempt() || $_POST && isset( $_POST[ 'tax_exempt' ] ),
			'selected' => isset( $_POST['certificate_id'] ) ? $_POST['certificate_id'] : '',
		), 'sst/checkout/', SST()->plugin_path() . '/includes/frontend/views/' );
	}

	/**
	 * If "Capture Orders Immediately" is enabled, capture newly created orders
	 * immediately after checkout.
	 *
	 * @since 5.0
	 *
	 * @param int $order_id ID of new order.
	 */
	public static function maybe_capture_order( $order_id ) {
		if ( SST_Settings::get( 'capture_immediately' ) == 'yes' ) {
			// TODO: ENSURE ORDERS ARE CAPTURED ONLY AFTER PAYMENT IS RECEIVED
			$order = new SST_Order( $order_id );

			if ( ! $order->capture() ) {
				// TODO: LOG FAILED REQUEST
			}
		}
	}
}

new SST_Checkout();