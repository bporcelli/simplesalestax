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
	 * Constructor: Initialize hooks.
	 *
	 * @since 5.0
	 */
	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'woocommerce_checkout_after_customer_details', array( $this, 'output_exemption_form' ) );
		add_action( 'woocommerce_calculate_totals', array( $this, 'calculate_taxes' ) );
		add_filter( 'woocommerce_cart_hide_zero_taxes', array( $this, 'hide_zero_taxes' ) );
		add_action( 'woocommerce_new_order', array( $this, 'add_order_meta' ) );
		add_action( 'woocommerce_resume_order', array( $this, 'add_order_meta' ) );
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
		$packages = WC()->session->get( 'sst_packages', '' );
		if ( ! empty( $packages ) )
			return json_decode( $packages, true );
		return array();
	}

	/**
	 * Set saved packages for this cart.
	 *
	 * @since 5.0
	 *
	 * @param $packages array (default: array())
	 */
 	protected function set_packages( $packages = array() ) {
 		WC()->session->set( 'sst_packages', json_encode( $packages ) );
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
	 * Add fees to the given package.
	 *
	 * @since 5.0
	 *
	 * @param array $package
	 * @param array $fees
	 */
	protected function add_fees_to_package( &$package, $fees ) {
		foreach ( $fees as $index => $fee ) {
			$package['fees'][] = $fee;
			$package['map'][]  = array(
				'type'    => 'fee',
				'id'      => $fee->id,
				'cart_id' => $index,
			);
		}
	}

 	/**
	 * Create shipping packages for this cart.
	 *
	 * @since 5.0
	 *
	 * @return array
	 */
	protected function create_packages() {
		$raw_packages = WC()->shipping->get_packages();

		if ( ! is_array( $raw_packages ) ) {
			return array();
		}

		$chosen_methods = WC()->session->get( 'chosen_shipping_methods' );
		$packages       = array();
		$fees_added     = false;

		foreach ( $raw_packages as $key => $package ) {
			/* Skip packages shipping outside of the US */
			if ( $package['destination']['country'] !== 'US' )
				continue;
			
			/* If package will be picked up, substitute destination addr. */
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

			/* Split package by origin address */
			$subpackages = $this->split_package( $package );

			if ( empty( $subpackages ) )
				return array();

			/* Add fees and taxes to first subpackage */
			$first_key = key( $subpackages );

			if ( ! $fees_added && apply_filters( 'wootax_add_fees', true ) ) {
				$this->add_fees_to_package( $subpackages[ $first_key ], WC()->cart->get_fees() );
				$fees_added = true;
			}

			if ( ! is_null( $method ) ) {
				$subpackages[ $first_key ]['shipping'] = $method;
				$subpackages[ $first_key ]['map'][]    = array(
					'type'    => 'shipping',
					'id'      => SST_SHIPPING_ITEM,
					'cart_id' => SST_SHIPPING_ITEM,
				);
			}

			foreach ( $subpackages as $new_package ) {
				$packages[ $this->get_package_hash( $new_package ) ] = $new_package;
			}	
		}

		/* Digital items won't be included in the packages we generate above.
		 * Therefore, we add a special package for these items here. */
		$digital_items = $this->get_items_not_needing_shipping();

		if ( ! empty( $digital_items ) ) {
			$package = array(
				'contents'    => $digital_items,
				'user'        => array(
					'ID' => get_current_user_id(),
				),
				'destination' => array(
					'address'   => WC()->customer->get_billing_address(),
					'address_2' => WC()->customer->get_billing_address_2(),
					'city'      => WC()->customer->get_billing_city(),
					'state'     => WC()->customer->get_billing_state(),
					'postcode'  => WC()->customer->get_billing_postcode(),
				),
			) + $this->new_package();

			/* Split package by origin address */
			$subpackages = $this->split_package( $package );

			if ( empty( $subpackages ) )
				return array();

			/* If fees weren't added already, add to first subpackage */
			$first_key = key( $subpackages );

			if ( ! $fees_added && apply_filters( 'wootax_add_fees', true ) ) {
				$this->add_fees_to_package( $subpackages[ $first_key ], WC()->cart->get_fees() );
			}

			foreach ( $subpackages as $new_package ) {
				$packages[ $this->get_package_hash( $new_package ) ] = $new_package;
			}
		}

		/* Give developers a final opportunity to change the packages */
		$packages = apply_filters( 'wootax_packages', $packages );

		return $packages;
	}

	/**
	 * Reset sales tax totals.
	 *
	 * @since 5.0
	 */
	protected function reset_taxes() {
		foreach ( WC()->cart->get_cart() as $cart_key => $item ) {
			$this->set_product_tax( $cart_key, 0 );
		}

		foreach ( WC()->cart->get_fees() as $key => $fee ) {
			$this->set_fee_tax( $key, 0 );
		}

		$this->set_tax_totals();
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
		$tax_data = WC()->cart->cart_contents[ $id ][ 'line_tax_data' ];

		$tax_data['subtotal'][ SST_RATE_ID ] = $tax;
		$tax_data['total'][ SST_RATE_ID ]    = $tax;

		WC()->cart->cart_contents[ $id ][ 'line_tax_data' ] = $tax_data;

		// Update totals
		WC()->cart->cart_contents[ $id ]['line_subtotal_tax'] = array_sum( $tax_data['subtotal'] );
		WC()->cart->cart_contents[ $id ]['line_tax']          = array_sum( $tax_data['total'] );
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
		WC()->cart->fees[ $id ]->tax_data[ SST_RATE_ID ] = $tax;
		WC()->cart->fees[ $id ]->tax = array_sum( WC()->cart->fees[ $id ]->tax_data );
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
	public function get_certificate() {
		if ( ! $_POST ) {
			return NULL;
		} else if ( ! isset( $_POST['post_data'] ) && ! isset( $_POST['certificate_id'] ) ) {
			return NULL;
		}

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
	}

	/**
	 * Reset session.
	 *
	 * @since 5.0
	 */
	protected function reset_session() {
		WC()->session->set( 'sst_packages', '' );
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
		$order = new SST_Order( $order_id );

		$order->update_meta( 'packages', json_encode( WC()->session->get( 'sst_packages' ) ) );

		if ( ( $exempt_cert = $this->get_certificate() ) ) {
			$order->update_meta( 'exempt_cert', json_encode( $exempt_cert ) );
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