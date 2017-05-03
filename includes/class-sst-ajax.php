<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * SST Ajax.
 *
 * Ajax functions.
 *
 * @author 	Simple Sales Tax
 * @package SST
 * @since 	5.0
 */
class SST_Ajax {

	/**
	 * @var array Hooks.
	 * @since 5.0
	 */
	private static $hooks = array(
		'sst_verify_taxcloud'                            => false,
		'sst_delete_certificate'                         => false,
		'sst_add_certificate'                            => false,
		'woocommerce_subscriptions_calculate_line_taxes' => false,
		'woocommerce_calc_line_taxes'                    => false,
	);

	/**
	 * Initialize hooks.
	 *
	 * @since 5.0
	 */
	public static function init() {
		foreach ( self::$hooks as $hook => $nopriv ) {
			$function = str_replace( array( 'woocommerce_', 'sst_' ), '', $hook );

			/* If we are overriding a woo hook, give ours higher priority */
			if ( 0 === strpos( $hook, 'woocommerce_' ) )
				$priority = 1;
			else
				$priority = 10;

			add_action( "wp_ajax_$hook", array( __CLASS__, $function ), $priority );

			if ( $nopriv ) {
				add_action( "wp_ajax_nopriv_$hook", array( __CLASS__, $function ), $priority );
			}
		}
	}

	/**
	 * Verify the user's TaxCloud API Login ID and API Key.
	 *
	 * @since 5.0
	 */
	public static function verify_taxcloud() {
		$taxcloud_id  = sanitize_text_field( $_POST[ 'wootax_tc_id' ] );
		$taxcloud_key = sanitize_text_field( $_POST[ 'wootax_tc_key' ] );

		if ( empty( $taxcloud_id ) || empty( $taxcloud_key ) ) {
			wp_send_json_error();
		} else {
			$ping = new TaxCloud\Request\Ping( $taxcloud_id, $taxcloud_key );

			try {
				TaxCloud()->Ping( $ping );
				wp_send_json_success();
			} catch ( Exception $ex ) {
				wp_send_json_error( $ex->getMessage() );
			}
		}
	}

	/**
	 * Update recurring line taxes for WooCommerce Subscriptions <= 1.5.
	 *
	 * @since 5.0.
	 */
	public static function subscriptions_calculate_line_taxes() {
		global $wpdb;

		check_ajax_referer( 'woocommerce-subscriptions', 'security' );

		$order_id  = absint( $_POST[ 'order_id' ] );
		$country   = strtoupper( esc_attr( $_POST[ 'country' ] ) );

		// Step out of the way if the customer is not located in the US
		if ( $country != 'US' )
			return;

		$shipping      = $_POST[ 'shipping' ];
		$line_subtotal = isset( $_POST[ 'line_subtotal' ] ) ? esc_attr( $_POST['line_subtotal'] ) : 0;
		$line_total    = isset( $_POST[ 'line_total' ] ) ? esc_attr( $_POST['line_total'] ) : 0;

		// Set up WC_WooTax_Order object
		$order = wc_get_order( $order_id );
		
		$taxes = $shipping_taxes = array();
	    
	    $return     = array();
	 	$item_data  = array();
	 	$type_array = array();

	 	// Get product ID, and, if possible, variatian ID
		if ( isset( $_POST[ 'order_item_id' ] ) ) {
			$product_id   = woocommerce_get_order_item_meta( $_POST[ 'order_item_id' ], '_product_id' );
			$variation_id = woocommerce_get_order_item_meta( $_POST[ 'order_item_id' ], '_variation_id' );
		} elseif ( isset( $_POST[ 'product_id' ] ) ) {
			$product_id   = esc_attr( $_POST[ 'product_id' ] );
			$variation_id = '';
		}

		$final_id = $variation_id ? $variation_id : $product_id;

		if ( ! empty( $product_id ) && WC_Subscriptions_Product::is_subscription( $final_id ) ) {
			// Add product to items array
			$product = WC_Subscriptions::get_product( $final_id );

			$item_info = array(
				'Index'  => '',
				'ItemID' => isset( $_POST[ 'order_item_id' ] ) ? $_POST[ 'order_item_id' ] : $final_id, 
				'Qty'    => 1, 
				'Price'  => $line_subtotal > 0 ? $line_subtotal : $product->get_price(),	
				'Type'   => 'cart',
			);

			$tic = SST_Product::get_tic( $product_id, $variation_id );

			if ( ! empty( $tic ) && $tic )
				$item_info[ 'TIC' ] = $tic;

			$item_data[] = $item_info;

			$type_array[ $_POST[ 'order_item_id' ] ] = 'cart';

			// Add shipping to items array
			if ( $shipping > 0 ) {
				$item_data[] = array(
					'Index'  => '',
					'ItemID' => SST_SHIPPING_ITEM, 
					'TIC'    => apply_filters( 'wootax_shipping_tic', SST_DEFAULT_SHIPPING_TIC ),
					'Qty'    => 1, 
					'Price'  => $shipping,	
					'Type'   => 'shipping',
				);

				$type_array[ SST_SHIPPING_ITEM ] = 'shipping';
			}

			// Add fees to items array
			foreach ( $order->order->get_fees() as $item_id => $fee ) {
				if ( $fee[ 'recurring_line_total' ] == 0 )
					continue;

				$item_data[] = array(
					'Index'  => '',
					'ItemID' => $item_id, 
					'TIC'    => apply_filters( 'wootax_fee_tic', SST_DEFAULT_FEE_TIC ),
					'Qty'    => 1, 
					'Price'  => $fee[ 'recurring_line_total' ],	
					'Type'   => 'fee',
				);

				$type_array[ $item_id ] = 'fee';
			}

			// Issue Lookup request
			$res = $order->do_lookup( $item_data, $type_array, true );

			if ( is_array( $res ) ) {
				$return[ 'recurring_shipping_tax' ]      = 0;
				$return[ 'recurring_line_subtotal_tax' ] = 0;
				$return[ 'recurring_line_tax' ]          = 0;

				foreach ( $res as $item ) {

					$item_id  = $item->ItemID;
					$item_tax = $item->TaxAmount;

					if ( $item_id == SST_SHIPPING_ITEM ) {
						$return[ 'recurring_shipping_tax' ] += $item_tax;
					} else {
						$return[ 'recurring_line_subtotal_tax' ] += $item_tax;
						$return[ 'recurring_line_tax' ]          += $item_tax;
					}

				}

				$taxes[ SST_RATE_ID ]          = $return[ 'recurring_line_tax' ];
				$shipping_taxes[ SST_RATE_ID ] = $return[ 'recurring_shipping_tax' ];

			 	// Get tax rates
				$tax_codes = array( SST_RATE_ID => apply_filters( 'wootax_rate_code', 'WOOTAX-RATE-DO-NOT-REMOVE' ) );

				// Remove old tax rows
				$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}woocommerce_order_itemmeta WHERE order_item_id IN ( SELECT order_item_id FROM {$wpdb->prefix}woocommerce_order_items WHERE order_id = %d AND order_item_type = 'recurring_tax' )", $order_id ) );
				$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}woocommerce_order_items WHERE order_id = %d AND order_item_type = 'recurring_tax'", $order_id ) );

				// Now merge to keep tax rows
				ob_start();

				foreach ( array_keys( $taxes + $shipping_taxes ) as $key ) {
					$item = array(
						'rate_id'             => $key,
						'name'                => $tax_codes[ $key ],
						'label'               => WC_Tax::get_rate_label( $key ),
						'compound'            => WC_Tax::is_compound( $key ),
						'tax_amount'          => wc_round_tax_total( isset( $taxes[ $key ] ) ? $taxes[ $key ] : 0 ),
						'shipping_tax_amount' => wc_round_tax_total( isset( $shipping_taxes[ $key ] ) ? $shipping_taxes[ $key ] : 0 ),
					);

					if ( ! $item[ 'label' ] )
						$item[ 'label' ] = WC()->countries->tax_or_vat();

					// Add line item
					$item_id = woocommerce_add_order_item( $order_id, array(
						'order_item_name' => $item[ 'name' ],
						'order_item_type' => 'recurring_tax'
					) );

					// Add line item meta
					if ( $item_id ) {
						woocommerce_add_order_item_meta( $item_id, 'rate_id', $item[ 'rate_id' ] );
						woocommerce_add_order_item_meta( $item_id, 'label', $item[ 'label' ] );
						woocommerce_add_order_item_meta( $item_id, 'compound', $item[ 'compound' ] );
						woocommerce_add_order_item_meta( $item_id, 'tax_amount', $item[ 'tax_amount' ] );
						woocommerce_add_order_item_meta( $item_id, 'shipping_tax_amount', $item[ 'shipping_tax_amount' ] );
					}

					include( plugin_dir_path( WC_Subscriptions::$plugin_file ) . 'templates/admin/post-types/writepanels/order-tax-html.php' );
				}

				$return[ 'tax_row_html' ] = ob_get_clean();

				echo json_encode( $return );
			}
		}

		die();
	}

	/**
	 * Respond when user requests to delete a certificate.
	 *
	 * @since 5.0
	 */
	public static function delete_certificate() {
		if ( ! isset( $_POST[ 'nonce' ] ) || ! wp_verify_nonce( $_POST[ 'nonce' ], 'sst_delete_certificate' ) ) {
			return;
		}

		$certificate_id = esc_attr( $_POST[ 'certificate_id' ] );

		$request = new TaxCloud\Request\DeleteExemptCertificate(
			SST_Settings::get( 'tc_id' ),
			SST_Settings::get( 'tc_key' ),
			$certificate_id
		);

		try {
			TaxCloud()->DeleteExemptCertificate( $request );
			
			// Invalidate cached certificates
			SST_Certificates::delete_certificates();

			wp_send_json_success( array(
				'certificates' => SST_Certificates::get_certificates_formatted( false ), 
			) );
		} catch ( Exception $ex ) { /* Failed to delete */
			wp_send_json_error( $ex->getMessage() );
		}
	}

	/**
	 * Add an exemption certificate for the customer.
	 *
	 * NOTE: Single purchase exemption certificates not supported at this
	 * time.
	 *
	 * @since 5.0
	 */
	public static function add_certificate() {
		// Handle invalid requests
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'sst_add_certificate' ) ) {
			return;
		}

		if ( ! isset( $_POST['form_data'] ) || ! isset( $_POST['certificate'] ) ) {
			wp_send_json_error( __( 'Invalid request.', 'simplesalestax' ) );
		}

		// Get data
		$form_data = array();
		parse_str( $_POST['form_data'], $form_data );
		$form_data = array_merge( $_POST['certificate'], $form_data );

		// Construct certificate
		$exempt_state = new TaxCloud\ExemptState(
			$form_data['ExemptState'],
			$form_data['PurchaserExemptionReason'],
			$form_data['IDNumber'] 
		);

		$tax_id = new TaxCloud\TaxID(
			$form_data['TaxType'],
			$form_data['IDNumber'],
			$form_data['StateOfIssue']
		);

		$certificate = new TaxCloud\ExemptionCertificate(
			array( $exempt_state ),
			false,
			'',
			$form_data['billing_first_name'],
			$form_data['billing_last_name'],
			'',
			$form_data['billing_address_1'],
			$form_data['billing_address_2'],
			$form_data['billing_city'],
			$form_data['billing_state'],
			$form_data['billing_postcode'],
			$tax_id,
			$form_data['PurchaserBusinessType'],
			$form_data['PurchaserBusinessTypeOtherValue'],
			$form_data['PurchaserExemptionReason'],
			$form_data['PurchaserExemptionReasonValue']
		);

		// Add certificate
		$user = wp_get_current_user();

		$request = new TaxCloud\Request\AddExemptCertificate(
			SST_Settings::get( 'tc_id' ),
			SST_Settings::get( 'tc_key' ),
			$user->user_login,	// TODO: USE ID?
			$certificate
		);

		$certificate_id = '';

		try {
			$certificate_id = TaxCloud()->AddExemptCertificate( $request );
			SST_Certificates::delete_certificates();  // Invalidate cache
		} catch ( Exception $ex ) {
			wp_send_json_error( $ex->getMessage() );
		}

		$data = array(
			'certificate_id' => $certificate_id,
			'certificates'   => SST_Certificates::get_certificates_formatted( false ),
		);

		wp_send_json_success( $data );
	}

	/**
	 * Recalculate sales tax via AJAX.
	 *
	 * @since 4.2
	 */
	public static function calc_line_taxes() {
		check_ajax_referer( 'calc-totals', 'security' );

		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			wp_die( -1 );
		}

		$items        = array();
		$order_id     = absint( $_POST['order_id'] );
		$tax_based_on = get_option( 'woocommerce_tax_based_on' );
		$country      = strtoupper( esc_attr( $_POST['country'] ) );
		$state        = strtoupper( esc_attr( $_POST['state'] ) );
		$postcode     = strtoupper( esc_attr( $_POST['postcode'] ) );
		$city         = wc_clean( esc_attr( $_POST['city'] ) );
		$woo_3_0      = version_compare( WC_VERSION, '3.0', '>=' );

		/* Let Woo take the reins if the customer is international */
		if ( 'US' != $country )
			return;

		/* Parse jQuery serialized items */
		parse_str( $_POST['items'], $items );

		/* Set customer billing/shipping address if necessary */
		$order = new SST_Order( $order_id );

		if ( SST_Addresses::get_destination_address( $order ) == NULL ) {
			$address = array(
				'address_1' => '',
				'address_2' => '',
				'city'      => $city,
				'state'     => $state,
				'postcode'  => $postcode
			);

			if ( 'billing' === $tax_based_on )
				$order->set_address( $address, 'billing' );
			else
				$order->set_address( $address, 'shipping' );
		}

		/* Save items and recalc taxes */
		wc_save_order_items( $order_id, $items );

		try {
			$order->calculate_taxes();
			$order->calculate_totals( false );
		} catch ( Exception $ex ) {
			wp_die( $ex->getMessage() );
		}

		/* Send back response */
		if ( ! $woo_3_0 )
			$data = get_post_meta( $order_id );

		include WC()->plugin_path() . '/includes/admin/meta-boxes/views/html-order-items.php';
		wp_die();
	}

}

SST_Ajax::init();