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
		'woocommerce_calc_line_taxes'                    => false,
		'woocommerce_subscriptions_calculate_line_taxes' => false,
	);

	/**
	 * Initialize hooks.
	 *
	 * @since 5.0
	 */
	public static function init() {
		foreach ( self::$hooks as $hook => $nopriv ) {
			$function = str_replace( array( 'woocommerce_', 'sst_' ), '', $hook );
			add_action( "wp_ajax_$hook", array( __CLASS__, $function ) );
			if ( $nopriv ) {
				add_action( "wp_ajax_nopriv_$hook", array( __CLASS__, $function ) );
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

		// Save to session
		WC()->session->set( 'sst_cert_id', $certificate_id );

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
		// TODO: update to ensure back compatibility
		// USE woocommerce_ajax_after_calc_line_taxes FILTER INSTEAD?
		global $wpdb;

		$order_id = absint( $_POST[ 'order_id' ] );
		$country  = strtoupper( esc_attr( $_POST[ 'country' ] ) );
				
		// Get WC_WooTax_Order object
		$order = self::get_order( $order_id );
	
		if ( $country != 'US' && $country != 'United States' || ! SST_Compatibility::taxes_enabled() ) {
			return; // Returning here allows WC_AJAX::calc_line_taxes to take over for non-US orders
		} else {
			// Build items array
			parse_str( $_POST[ 'items' ], $items );

			$order_items = array();
			$final_items = array();

			// Add cart items and fees
			$order_items = array_merge( $items[ 'order_item_id' ], $order_items );

			// Add shipping items
			if ( isset( $items[ 'shipping_method_id' ] ) ) {
				$order_items = array_merge( $items[ 'shipping_method_id' ], $order_items );
			}

			// Construct items array from POST data
			foreach ( $order_items as $item_id ) {
				$qty = 1;

				if ( is_array( $items[ 'shipping_method_id' ] ) && in_array( $item_id, $items[ 'shipping_method_id' ] ) ) {
					// Shipping method
					$tic  = apply_filters( 'wootax_shipping_tic', SST_DEFAULT_SHIPPING_TIC );
					$cost = $items[ 'shipping_cost' ][$item_id];
					$type = 'shipping';
				} else if ( isset( $items[ 'order_item_qty' ][$item_id] ) ) {
					// Cart item
					$product_id   = $order->get_item_meta( $item_id, '_product_id' );
					$variation_id = $order->get_item_meta( $item_id, '_variation_id' );

					$tic  = SST_Product::get_tic( $product_id, $variation_id );
					$cost = $items[ 'line_total' ][ $item_id ];
					$type = 'cart';
					$qty  = SST_Settings::get( 'tax_based_on' ) == 'line-subtotal' ? 1 : $items[ 'order_item_qty' ][ $item_id ];
				} else {
					// Fee
					$tic  = apply_filters( 'wootax_fee_tic', SST_DEFAULT_FEE_TIC );
					$cost = $items[ 'line_total' ][$item_id];
					$type = 'fee';
				}

				// Calculate unit price
				$unit_price = $cost / $qty;

				// Add item to final items array
				if ( $unit_price != 0 ) {
					// Map item_id to item type 
					$type_array[ $item_id ] = $type == 'shipping' ? 'shipping' : 'cart';
									
					// Add to items array 
					$item_data = array(
						'Index'  => '', // Leave index blank. It is assigned later.
						'ItemID' => $item_id, 
						'Qty'    => $qty, 
						'Price'  => $unit_price,	
						'Type'   => $type,
					);	

					if ( ! empty( $tic ) && $tic )
						$item_data['TIC'] = $tic;

					$final_items[] = $item_data;
				}
			}
			
			// Send lookup request using the generated items and mapping array
			$res = $order->do_lookup( $final_items, $type_array );

			// Convert response array to be sent back to client
			// @see WC_AJAX::calc_line_taxes()
			if ( is_array( $res ) ) {
					
				if ( ! isset( $items[ 'line_tax' ] ) )
					$items[ 'line_tax' ] = array();
				if ( ! isset( $items[ 'line_subtotal_tax' ] ) )
					$items[ 'line_subtotal_tax' ] = array();

				$items[ 'order_taxes' ] = array();

				foreach ( $res as $item )  {
					$id  = $item->ItemID;
					$tax = $item->TaxAmount; 

					if ( is_array( $items[ 'shipping_method_id' ] ) && in_array( $id, $items[ 'shipping_method_id' ] ) ) {
						$items[ 'shipping_taxes' ][ $id ][ SST_RATE_ID ] = $tax;
					} else {
						$items[ 'line_tax' ][ $id ][ SST_RATE_ID ] = $tax;
						$items[ 'line_subtotal_tax' ][ $id ][ SST_RATE_ID ] = $tax;
					}
				}

				// Added in 4.6: add new tax item if old item has been removed
				$order = $order->order;
				$taxes = $order->get_taxes();
				$tax_item_id = self::get_meta( $order_id, 'tax_item_id' );

				if ( empty( $tax_item_id ) || ! in_array( $tax_item_id, array_keys( $taxes ) ) ) {
					$tax_item_id = $order->add_tax( SST_RATE_ID, $tax_total, $shipping_tax_total );
					self::update_meta( $order_id, 'tax_item_id', $tax_item_id );
				}

				$items[ 'order_taxes' ][ $tax_item_id ] = absint( SST_RATE_ID );

				// Save order items
				wc_save_order_items( $order_id, $items );

				// Return HTML items
				$data  = get_post_meta( $order_id );

				include( WC()->plugin_path() . '/includes/admin/meta-boxes/views/html-order-items.php' );

				die();
			} else {
				die( 'Could not update order taxes. It is possible that the order has already been "completed," or that the customer\'s shipping address is unavailable. Please refresh the page and try again.' ); 
			}
		}
	}
}

SST_Ajax::init();