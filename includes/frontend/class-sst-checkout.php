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
class SST_Checkout extends SST_Abstract_Cart {

	/**
	 * @var WC_Cart The cart we are calculating taxes for.
	 * @since 5.0
	 */
	private $cart = null;

	/**
	 * Constructor: Initialize hooks.
	 *
	 * @since 5.0
	 */
	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'woocommerce_checkout_after_customer_details', array( $this, 'output_exemption_form' ) );
		add_action( 'woocommerce_calculate_totals', array( $this, 'calculate_tax_totals' ), 1 );
		add_filter( 'woocommerce_cart_hide_zero_taxes', array( $this, 'hide_zero_taxes' ) );
		add_action( 'woocommerce_new_order', array( $this, 'add_order_meta' ) );
		add_action( 'woocommerce_resume_order', array( $this, 'add_order_meta' ) );

		if ( version_compare( WC_VERSION, '3.0', '<' ) )
			add_action( 'woocommerce_add_shipping_order_item', array( $this, 'add_shipping_tax_old' ), 10, 3 );
		else
			add_action( 'woocommerce_checkout_create_order_shipping_item', array( $this, 'add_shipping_tax' ), 10, 4 );
	}

	/**
	 * Perform a tax lookup and update the sales tax for all items.
	 *
	 * @since 5.0
	 *
	 * @return bool True on success, false on error.
	 */
	public function calculate_tax_totals( $cart ) {
		$this->cart = $cart;
		parent::calculate_taxes();
		$this->cart = null;
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
	 * Get saved packages for this cart.
	 *
	 * @since 5.0
	 *
	 * @return array
	 */
	protected function get_packages() {
		return $this->cart->sst_packages;
	}

	/**
	 * Set saved packages for this cart.
	 *
	 * @since 5.0
	 *
	 * @param $packages array (default: array())
	 */
 	protected function set_packages( $packages = array() ) {
 		$this->cart->sst_packages = $packages;
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
		return $item['data'] && ! $item['data']->needs_shipping();
	}

	/**
	 * Get only items that don't need shipping.
	 *
	 * @since 5.0
	 *
	 * @return array
	 */
	protected function get_items_not_needing_shipping() {
		return array_filter( $this->cart->get_cart(), array( $this, 'filter_items_not_needing_shipping' ) );
	}

 	/**
	 * Create shipping packages for this cart.
	 *
	 * @since 5.0
	 *
	 * @return array
	 */
	protected function create_packages() {
		/* Start with the packages returned by Woo */
		$packages = WC()->shipping->get_packages();

		/* After WooCommerce 3.0, items that do not need shipping are excluded 
		 * from shipping packages. To ensure that these products are taxed, we
		 * create a special package for them. */
		if ( version_compare( WC_VERSION, '3.0', '>=' ) ) {
			$digital_items = $this->get_items_not_needing_shipping();

			if ( ! empty( $digital_items ) ) {
				$packages[] = sst_create_package( array(
					'contents'    => $digital_items,
					'user'        => array(
						'ID' => get_current_user_id(),
					),
					'destination' => array(
						'address'   => SST_Customer::get_billing_address(),
						'address_2' => SST_Customer::get_billing_address_2(),
						'city'      => SST_Customer::get_billing_city(),
						'state'     => SST_Customer::get_billing_state(),
						'postcode'  => SST_Customer::get_billing_postcode(),
					),
				) );
			}
		}

		/* Let devs change the packages before we split them. */
		$packages = apply_filters( 'wootax_cart_packages_before_split', $packages, $this->cart );

		/* Split packages by origin address */
		$split_packages = array();
		$chosen_methods = WC()->session->get( 'chosen_shipping_methods' );

		foreach ( $packages as $key => $package ) {
			/* For local pickups, substitute destination address with pickup
			 * address. */
			$method = null;

			if ( isset( $chosen_methods[ $key ], $package['rates'][ $chosen_methods[ $key ] ] ) ) {
				$method = $package['rates'][ $chosen_methods[ $key ] ];
				
				if ( SST_Shipping::is_local_pickup( array( $method->method_id ) ) ) {
					$pickup_address         = apply_filters( 'wootax_pickup_address', SST_Addresses::get_default_address(), null );
					$package['destination'] = array(
						'address'   => $pickup_address->getAddress1(),
						'address_2' => $pickup_address->getAddress2(),
						'city'      => $pickup_address->getCity(),
						'state'     => $pickup_address->getState(),
						'postcode'  => $pickup_address->getZip5(),
					);
				}
			}

			$subpackages = $this->split_package( $package );

			/* Add shipping to first subpackage */
			if ( ! empty( $subpackages ) && ! is_null( $method ) ) {

				/* Set id so we can distinguish tax amount for each shipping
				 * method. */
				$method->id = $key;

				$subpackages[ key( $subpackages ) ]['shipping'] = $method;
			}

			$split_packages = array_merge( $split_packages, $subpackages );
		}

		$packages = $split_packages;

		/* Add fees to first package */
		if ( ! empty( $packages ) && apply_filters( 'wootax_add_fees', true ) ) {
			$packages[ key( $packages ) ]['fees'] = $this->cart->get_fees();
		}

		return apply_filters( 'wootax_cart_packages', $packages, $this->cart );
	}

	/**
	 * Reset sales tax totals.
	 *
	 * @since 5.0
	 */
	protected function reset_taxes() {
		/* Reset tax totals for all items + shipping */
		foreach ( $this->cart->get_cart() as $cart_key => $item ) {
			$this->set_product_tax( $cart_key, 0 );
		}

		foreach ( $this->cart->get_fees() as $key => $fee ) {
			$this->set_fee_tax( $key, 0 );
		}

		$this->cart->sst_shipping_taxes = array();

		/* Initialize sst_packages array if need be */
		if ( ! is_array( $this->cart->sst_packages ) ) {
			$this->cart->sst_packages = array();
		}

		$this->update_taxes();
	}

	/**
	 * Update sales tax totals.
	 *
	 * @since 5.0
	 */
 	protected function update_taxes() {
 		/* Compute the total cart tax added by us */
 		$cart_tax_total = 0;

 		foreach ( $this->cart->get_cart() as $item ) {
 			$tax_data = $item['line_tax_data'];
 			if ( isset( $tax_data['total'], $tax_data['total'][ SST_RATE_ID ] ) ) {
 				$cart_tax_total += $tax_data['total'][ SST_RATE_ID ];
 			}
 		}

 		foreach ( $this->cart->get_fees() as $fee ) {
 			if ( isset( $fee->tax_data[ SST_RATE_ID ] ) ) {
 				$cart_tax_total += $fee->tax_data[ SST_RATE_ID ];
 			}
 		}

 		/* Set total cart tax/shipping tax */
 		$this->cart->taxes[ SST_RATE_ID ] = $cart_tax_total;
		$this->cart->tax_total = WC_Tax::get_tax_total( $this->cart->taxes );
		$this->cart->shipping_taxes[ SST_RATE_ID ] = WC_Tax::get_tax_total( $this->cart->sst_shipping_taxes );
		$this->cart->shipping_tax_total = WC_Tax::get_tax_total( $this->cart->shipping_taxes );
 	}

	/**
	 * Set the tax for a product.
	 *
	 * @since 5.0
	 *
	 * @param mixed $id Product ID.
	 * @param float $tax Sales tax for product.
	 */
	protected function set_product_tax( $id, $tax ) {
		// Update tax data
		$tax_data = $this->cart->cart_contents[ $id ][ 'line_tax_data' ];

		$tax_data['subtotal'][ SST_RATE_ID ] = $tax;
		$tax_data['total'][ SST_RATE_ID ]    = $tax;

		$this->cart->cart_contents[ $id ][ 'line_tax_data' ] = $tax_data;

		// Update totals
		$this->cart->cart_contents[ $id ]['line_subtotal_tax'] = array_sum( $tax_data['subtotal'] );
		$this->cart->cart_contents[ $id ]['line_tax']          = array_sum( $tax_data['total'] );
	}
	
	/**
	 * Set the tax for a shipping package.
	 *
	 * @since 5.0
	 *
	 * @param mixed $id Package key.
	 * @param float $tax Sales tax for package.
	 */
	protected function set_shipping_tax( $id, $tax ) {
		$this->cart->sst_shipping_taxes[ $id ] = $tax;
	}

	/**
	 * Set the tax for a fee.
	 *
	 * @since 5.0
	 *
	 * @param mixed $id Fee ID.
	 * @param float $tax Sales tax for fee.
	 */
	protected function set_fee_tax( $id, $tax ) {
		$this->cart->fees[ $id ]->tax_data[ SST_RATE_ID ] = $tax;
		$this->cart->fees[ $id ]->tax = array_sum( $this->cart->fees[ $id ]->tax_data );
	}

	/**
	 * Get the customer exemption certificate.
	 *
	 * @since 5.0
	 *
	 * @return ExemptionCertificateBase|NULL
	 */
	public function get_certificate() {
		if ( ! isset( $_POST['post_data'] ) ) {
			$post_data = $_POST;
		} else {
			$post_data = array();
			parse_str( $_POST['post_data'], $post_data );
		}
		
		if ( isset( $post_data['tax_exempt'] ) && isset( $post_data['certificate_id'] ) ) {
			$certificate_id = sanitize_text_field( $post_data['certificate_id'] );
			return new TaxCloud\ExemptionCertificateBase( $certificate_id );
		}

		return NULL;
	}

	/**
	 * Display an error message to the user.
	 *
	 * @since 5.0
	 * 
     * @param string $message Message describing the error.
     */
	protected function handle_error( $message ) {
		wc_add_notice( $message, 'error' );
		SST_Logger::add( $message );
	}

	/**
	 * Save metadata when a new order is created.
	 *
	 * @since 4.2
	 *
	 * @param int $order_id ID of new order.
	 */
	public function add_order_meta( $order_id ) {
		$this->cart = WC()->cart; /* Save data from 'main' cart */

		$order = new SST_Order( $order_id );

		$order->update_meta( 'packages', $this->get_packages() );
		$order->update_meta( 'exempt_cert', $this->get_certificate() );
		$order->save();
	}

	/**
	 * Set the shipping tax for newly created shipping items.
	 *
	 * @since 5.0
	 *
	 * @param int $order_id
	 * @param int $item_id
	 * @param int $package_key
	 */
	public function add_shipping_tax_old( $order_id, $item_id, $package_key ) {
		$shipping_taxes = WC()->cart->sst_shipping_taxes;

		if ( isset( $shipping_taxes[ $package_key ] ) ) {
			$taxes = wc_get_order_item_meta( $item_id, 'taxes', true );
			$taxes[ SST_RATE_ID ] = $shipping_taxes[ $package_key ];
			wc_update_order_item_meta( $item_id, 'taxes', $taxes );
		}
	}

	/**
	 * Set the shipping tax for newly created shipping items (Woo 3.x).
	 *
	 * @since 5.0
	 *
	 * @param WC_Order_Item_Shipping $item
	 * @param int $package_key
	 * @param array $package
	 * @param WC_Order $order
	 */
	public function add_shipping_tax( $item, $package_key, $package, $order ) {
		$shipping_taxes = WC()->cart->sst_shipping_taxes;

		if ( isset( $shipping_taxes[ $package_key ] ) ) {
			$taxes = $item->get_taxes();
			$taxes['total'][ SST_RATE_ID ] = $shipping_taxes[ $package_key ];
			$item->set_taxes( $taxes );
		}
	}

	/**
	 * Enqueue JS/CSS for exemption management interface.
	 *
	 * @since 5.0
	 */
	public function enqueue_scripts() {
		// CSS
		wp_enqueue_style( 'sst-modal', SST()->plugin_url() . '/assets/css/modal.css' );
		wp_enqueue_style( 'sst-certificate-modal', SST()->plugin_url() . '/assets/css/certificate-modal.css' );

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
		$exempt_roles = SST_Settings::get( 'exempt_roles', array() );
		$user_roles   = is_user_logged_in() ? $current_user->roles : array();
		return count( array_intersect( $exempt_roles, $user_roles ) ) > 0;
	}

	/**
	 * Should the exemption form be displayed?
	 *
	 * @since 5.0
	 */
	protected function show_exemption_form() {
		$restricted = SST_Settings::get( 'restrict_exempt' ) == 'yes';
		$enabled    = SST_Settings::get( 'show_exempt' ) == 'true';
		return $enabled && ( ! $restricted || $this->is_user_exempt() );
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
			'checked'  => ! $_POST && $this->is_user_exempt() || $_POST && isset( $_POST[ 'tax_exempt' ] ),
			'selected' => isset( $_POST['certificate_id'] ) ? $_POST['certificate_id'] : '',
		), 'sst/checkout/', SST()->plugin_path() . '/includes/frontend/views/' );
	}
}

new SST_Checkout();