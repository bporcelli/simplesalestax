<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Subscriptions.
 *
 * Enables WooCommerce Subscriptions support.
 *
 * @author 	Simple Sales Tax
 * @package SST
 * @since 	5.0
 */
class SST_Subscriptions {

	/**
	 * Constructor.
	 *
	 * @since 5.0
	 */
	public function __construct() {
		add_filter( 'wootax_taxable_price', array( $this, 'change_shipping_price' ), 10, 2 );
		add_filter( 'wootax_add_fees', array( $this, 'exclude_fees' ) );
		add_filter( 'woocommerce_calculated_total', array( $this, 'store_shipping_taxes' ), 10, 2 );
		add_action( 'woocommerce_cart_updated', array( $this, 'restore_shipping_taxes' ) );
		add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'destroy_session' ) );
		add_action( 'woocommerce_new_order_item', array( $this, 'save_taxes' ), 10, 3 );
		add_action( 'woocommerce_order_add_tax', array( $this, 'save_taxes_2_6' ), 12, 2 );
		add_action( 'wootax_update_recurring_tax', array( $this, 'cron_update_recurring_tax' ) );
		// TODO: TEST/REFACTOR THE BELOW HOOKS
		// add_filter( 'woocommerce_get_order_item_totals', array( $this, 'get_order_item_totals' ), 5, 2 );
		// add_filter( 'wcs_renewal_order_created', array( $this, 'issue_renewal_lookup' ), 10, 2 ); // 2.0.x
		// add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'fix_recurring_taxes' ), 15, 2 );
		// add_action( 'woocommerce_subscriptions_renewal_order_created', array( $this, 'remove_duplicate_renewal_taxes' ), 10, 2 );
	}

	/**
	 * Set taxable shipping price to zero when charge_shipping_up_front is false.
	 *
	 * @since 5.0
	 *
	 * @param  float $price Taxable price.
	 * @param  string $item_id Product ID.
	 */
	public function change_shipping_price( $price, $item_id ) {
		if ( SST_SHIPPING_ITEM == $item_id && WC_Subscriptions_Cart::get_calculation_type() != 'recurring_total' && ! WC_Subscriptions_Cart::charge_shipping_up_front() ) {
			return 0;
		}

		return $price;
	}

	/**
	 * Exclude fees from tax lookups when subscription there is no subscription
	 * sign up fee and all subscriptions qualify for a free trial.
	 *
	 * @since 5.0
	 *
	 * @param bool $add_fees Should fees be included in the lookup?
	 */
	public function exclude_fees( $add_fees ) {
		if ( WC_Subscriptions_Cart::get_calculation_type() != 'recurring_total' && 0 == WC_Subscriptions_Cart::get_cart_subscription_sign_up_fee() && WC_Subscriptions_Cart::all_cart_items_have_free_trial() ) {
			return false;
		}

		return $add_fees;
	}

	/**
	 * Set key of WooTax item in order totals to 'sales-tax.'
	 *
	 * @since 4.2
	 *
	 * @param  array $total_rows
	 * @param  WC_Order $order
	 * @return array
	 */
	public function get_order_item_totals( $total_rows, $order ) {
		$contains_sub = wcs_order_contains_subscription( $order );

		if ( $contains_sub && 'incl' !== $order->tax_display_cart ) {				
			$new_total_rows = array();

			foreach ( $total_rows as $row_key => $data ) {
				if ( $row_key == strtolower( apply_filters( 'wootax_rate_code', 'WOOTAX-RATE-DO-NOT-REMOVE' ) ) ) {
					$row_key = 'sales-tax';
				}

				$new_total_rows[ $row_key ] = $data;
			}
			return $new_total_rows;
		}

		return $total_rows;
	}
	
	/**
	 * Issue a Lookup when a new renewal order is created.
	 *
	 * @since 1.0
	 *
	 * @param WC_Order $renewal_order
	 * @param mixed $order_or_subscription WC_Order or WC_Subscription for
	 * original order.
	 */
	public function issue_renewal_lookup( $renewal_order, $order_or_subscription ) {
		$renewal_order_id = $renewal_order->id;

		$order = new SST_Order( $renewal_order_id );

		// Find parent order object
		$parent_order = $order_or_subscription;
			
		if ( class_exists( 'WC_Subscription' ) && $parent_order instanceof WC_Subscription ) {
			if ( $parent_order->is_manual() ) {
				return $renewal_order; // If subscription requires manual renewal, a lookup will occur when the renewal is processed
			} else {
				$parent_order = $order_or_subscription->order;
			}
		}

		// Get customer address from original order/subscription if necessary
		if ( ! SST_Addresses::is_valid( $order->destination_address ) ) {
			$order->destination_address = $this->get_destination_address( $parent_order );
		}

		// Reset WooTax meta values
		$order->reset_meta_data();

		// Build and format items array
		$order_items = $order->order->get_items() + $order->order->get_fees() + $order->order->get_shipping_methods();

		$final_items = $type_array = array();
		$tax_based_on = SST_Settings::get( 'tax_based_on' );

		foreach ( $order_items as $item_id => $item ) {
			$type = $item[ 'type' ];
			$qty  = isset( $item[ 'qty' ] ) ? $item[ 'qty' ] : 1;
			$cost = isset( $item[ 'cost' ] ) ? $item[ 'cost' ] : $item[ 'line_total' ]; // 'cost' key used by shipping methods in 2.2

			switch ( $type ) {
				case 'shipping':
					$tic = apply_filters( 'wootax_shipping_tic', SST_DEFAULT_SHIPPING_TIC );
					break;
				case 'fee':
					$tic = apply_filters( 'wootax_fee_tic', SST_DEFAULT_FEE_TIC );
					break;
				case 'line_item':
					$tic  = SST_Product::get_tic( $item[ 'product_id' ], $item[ 'variation_id' ] );
					$type = 'cart';
					break;
			}

			// Only add an item if its cost is nonzero
			if ( $cost != 0 ) {
				$unit_price = $cost / $qty;

				if ( $tax_based_on == 'item-price' || !$tax_based_on ) {
					$price = $unit_price; 
				} else {
					$qty   = 1;
					$price = $cost; 
				}

				$item_data = array(
					'Index'  => '',
					'ItemID' => $item_id,
					'Qty'    => $qty,
					'Price'  => $price,
					'Type'   => $type,
				);	

				if ( $tic )
					$item_data[ 'TIC' ] = $tic;

				$final_items[] = $item_data;

				$type_array[ $item_id ] = $type;
			}
		}

		// Perform lookup
		$result = $order->do_lookup( $final_items, $type_array );

		if ( ! is_array( $result ) ) {
			$parent_order->add_order_note( sprintf( __( 'Tax lookup for renewal order %s failed. Reason: '. $result, 'woocommerce-subscriptions' ), $renewal_order_id ) );
		} else {
			$parent_order->add_order_note( sprintf( __( 'Tax lookup for renewal order %s successful.', 'woocommerce-subscriptions' ), $renewal_order_id ) );
		}

		return $renewal_order;
	}

	/**
	 * Update cart/shipping tax totals for recurring tax items.
	 *
	 * @since 4.2
	 *
	 * @param int $order_id Order for which taxes should be updated.
	 * @param array $posted
	 */
	public function fix_recurring_taxes( $order_id, $posted ) {
		$order = new WC_Order( $order_id );

		$contains_sub = wcs_order_contains_subscription( $order );

		if ( $contains_sub && 'incl' !== $order->tax_display_cart ) {			
			if ( count( $order->get_items( 'recurring_tax' ) ) > 0 ) {
				foreach ( $order->get_items( 'recurring_tax' ) as $item_id => $item ) {
					wc_update_order_item_meta( $item_id, 'cart_tax', $item[ 'tax_amount' ] );
					wc_update_order_item_meta( $item_id, 'shipping_tax', $item[ 'shipping_tax_amount' ] );
					wc_update_order_item_meta( $item_id, 'compound', true );
				}
			}
		}
	}

	/**
	 * Get destination address from original order.
	 *
	 * @since 4.2
	 *
	 * @return array
	 */
	public function get_destination_address( $order ) {
		return SST_Addresses::get_destination_address( $order );
	}

	/**
	 * Remove duplicate tax column from renewal orders.
	 *
	 * @since 4.2
	 *
	 * @param WC_Order $renewal_order
	 * @param WC_Order $original_order First order in series.
	 */
	public function remove_duplicate_renewal_taxes( $renewal_order, $original_order ) {
		global $wpdb;

		$original_taxes = $original_order->get_items( 'recurring_tax' );
		$new_taxes      = $renewal_order->get_taxes();
		$to_remove      = array();

		foreach ( $original_taxes as $tax_item_id => $data ) {
			if ( $data[ 'rate_id' ] != SST_RATE_ID ) {
				continue;
			}

			foreach ( $new_taxes as $tax_id => $tax_data ) {
				if ( $tax_data[ 'tax_amount' ] == $data[ 'tax_amount' ] && $tax_data[ 'rate_id' ] == $data[ 'rate_id' ] ) {
					$to_remove[] = $tax_id;
				}
			}
		}	

		foreach ( $to_remove as $tax_item_id ) {
			wc_delete_order_item( $tax_item_id );
		}
	}

	/**
	 * Save calculated shipping taxes before recurring tax totals are updated.
	 *
	 * @since 5.0
	 *
	 * @param  double $total Current cart total.
	 * @param  WC_Cart $cart Cart object.
	 * @return double
	 */
	public function store_shipping_taxes( $total, $cart ) {
		$calc_type = WC_Subscriptions_Cart::get_calculation_type();

		if ( in_array( $calc_type, array( 'none', 'recurring_total' ) ) ) {
			$shipping_taxes_back = WC()->session->get( 'shipping_taxes_back' );
			if ( ! is_array( $shipping_taxes_back ) ) {
				$shipping_taxes_back = array();
			}
			$shipping_taxes_back[ $calc_type ] = $cart->shipping_taxes;		
			WC()->session->set( 'shipping_taxes_back', $shipping_taxes_back );
		}

		return $total;
	}

	/**
	 * Restore shipping taxes after recurring order totals are updated.
	 *	
	 * @since 5.0
	 */
	public function restore_shipping_taxes() {
		$calc_type = WC_Subscriptions_Cart::get_calculation_type();

		if ( in_array( $calc_type, array( 'none', 'recurring_total' ) ) ) {
			$shipping_taxes_back = WC()->session->get( 'shipping_taxes_back' );
			if ( is_array( $shipping_taxes_back ) && array_key_exists( $calc_type, $shipping_taxes_back ) ) {
				WC()->cart->shipping_taxes = $shipping_taxes_back[ $calc_type ];
			}
		}
	}

	/**
	 * Delete session data post-checkout.
	 *
	 * @since 5.0
	 */
	public function destroy_session() {
		WC()->session->set( 'shipping_taxes_back', array() );
	}

	/**
	 * Set cart_tax/shipping_tax meta for our tax item (WooCommerce 2.6.x).
	 *
	 * @since 5.0
	 *
	 * @param int $order_id
	 * @param int $item_id
	 */
	public function save_taxes_2_6( $order_id, $item_id ) { // TODO: 3.0 COMPAT?
		$order = new SST_Order( $order_id );
		wc_add_order_item_meta( $item_id, 'cart_tax', wc_format_decimal( $order->get_meta( 'tax_total' ) ) );
		wc_add_order_item_meta( $item_id, 'shipping_tax', wc_format_decimal( $order->get_meta( 'shipping_tax_total' ) ) );
	}

	/**
	 * Set cart_tax/shipping_tax meta for our tax item (WooCommerce 3.0+).
	 *
	 * @since 5.0
	 *
	 * @param int $item_id
     * @param WC_Order_Item $item
	 * @param int $order_id
	 */
	public function save_taxes( $item_id, $item, $order_id ) {
		if ( 'tax' === $item->get_type() ) {
			$this->save_taxes_2_6( $order_id, $item_id );
		}
	}

	/**
	 * TODO: NEEDED?
	 *
	 * Update recurring tax for subscriptions.
	 *
	 * This method is executed ~2 times per day and recomputes the sales tax
	 * for all subscription orders with a payment due in 12 hours or less. It
	 * is intended to ensure that the sales tax total is updated accordingly
	 * if a customer's address changes during the duration of their subscription.
	 *
	 * @since 5.0
	 */
	public function cron_update_recurring_tax() {
		global $wpdb;

		// Find date/time 12 hours from now
		$twelve_hours = mktime( date('H') + 12 );

		$date = new DateTime( date( 'c', $twelve_hours ) ); 
		$date = $date->format( 'Y-m-d H:i:s' );

		// Set up logger
		$logger = false;

		if ( SST_LOG_REQUESTS ) {
			$logger = class_exists( 'WC_Logger' ) ? new WC_Logger() : WC()->logger();
			$logger->add( 'wootax', 'Starting recurring tax update. Subscriptions with payments due before '. $date .' are being considered.' );
		}

		// Get all scheduled "woocommerce_scheduled_subscription_payment" actions with post_date <= $twelve_hours
		$scheduled = $wpdb->get_results( $wpdb->prepare( "SELECT ID, post_content FROM $wpdb->posts WHERE post_status = %s AND post_title = %s AND post_date <= %s", "pending", "woocommerce_scheduled_subscription_payment", $date ) );

		// Update recurring totals if necessary
		if ( count( $scheduled ) > 0 ) {
			$warnings = array();

			foreach ( $scheduled as $action ) {
				$temp_warnings = array();
				$show_warnings = false;
				$args = json_decode( $action->post_content );
				
				// Build order object 
				$order_id = $args->subscription_id;
				
				if ( false === get_post_type( $order_id ) ) {
					continue;
				}

				$order = new SST_Order( $order_id );

				// Determine whether or not selected payment gateway supports changes to recurring totals
				$changes_supported = $order->is_manual() || $order->payment_method_supports( 'subscription_amount_changes' );

				// Build and format order items array
				$order_items = $type_array = array();

				foreach ( $order->get_items() as $item_id => $item ) {
					$tic = SST_Product::get_tic( $item[ 'product_id' ], $item[ 'variation_id' ] );
					$qty = isset( $item[ 'qty' ] ) ? $item[ 'qty' ] : 1;

					$recurring_subtotal = isset( $item[ 'item_meta' ][ '_recurring_line_subtotal' ] ) ? $item[ 'item_meta' ][ '_recurring_line_subtotal' ][0] : 0;
					$regular_subtotal   = isset( $item[ 'item_meta' ][ '_line_total' ] ) ? $item[ 'item_meta' ][ '_line_total' ][0] : 0;

					$cost = $recurring_subtotal === '0' || ! empty( $recurring_subtotal ) ? $recurring_subtotal : $regular_subtotal;

					// Special case: If _subscription_sign_up_fee is set and $cost is equal to its value, fall back to product price
					$sign_up_fee = $order->get_items_sign_up_fee( $item );

					if ( $sign_up_fee == $cost ) {
						$cost = $item[ 'data' ]->get_price();
					}

					if ( $cost != 0 ) {
						$unit_price = $cost / $qty;

						if ( SST_Settings::get( 'tax_based_on' ) != 'line-subtotal' ) {
							$price = $unit_price; 
						} else {
							$qty   = 1;
							$price = $cost; 
						}
					}

					$item = array(
						'Index'  => '',
						'ItemID' => $item_id,
						'Qty'    => $qty,
						'Price'  => $price,
						'Type'   => 'cart',
					);

					if ( $tic ) {
						$item[ 'TIC' ] = $tic;
					}

					$type_array[ $item_id ] = 'cart';
					$order_items[] = $item;
				}

				foreach ( $order->get_fees() as $fee_id => $fee ) {
					$item = array(
						'Index'  => '',
						'ItemID' => $fee_id,
						'Qty'    => 1,
						'Price'  => $fee[ 'recurring_line_total' ],
						'Type'   => 'cart',
						'TIC'    => apply_filters( 'wootax_fee_tic', SST_DEFAULT_FEE_TIC ),
					);

					$type_array[ $fee_id ] = 'fee';
					$order_items[] = $item;
				}

				$shipping_methods = $order->get_shipping_methods();

				foreach ( $shipping_methods as $method_id => $method ) {
					$item = array(
						'Index'  => '',
						'ItemID' => $method_id,
						'Qty'    => 1,
						'Price'  => isset( $method[ 'cost' ] ) ? $method[ 'cost' ] : $method[ 'line_total' ], // 'cost' key used by shipping methods in 2.2
						'Type'   => 'shipping',
						'TIC'    => apply_filters( 'wootax_shipping_tic', SST_DEFAULT_SHIPPING_TIC ),
					);

					$type_array[ $method_id ] = 'shipping';
					$order_items[] = $item;
				}

				// Set status to pending so a lookup is always sent
				$status = $order->get_meta( 'status' );
				$order->update_meta_data( 'status', 'pending' );

				// Store old tax totals, then issue lookup request
				$old_tax = $old_shipping_tax = $wootax_item_id = 0;
				$taxes = $order->get_items( 'tax' );

				foreach ( $taxes as $item_id => $item ) {
					if ( $item['rate_id'] == SST_RATE_ID ) {
						$wootax_item_id   = $item_id;
						$old_tax          = $item[ 'tax_amount' ];
						$old_shipping_tax = $item[ 'shipping_tax_amount' ];
					}
				}

				$res = $order->do_lookup( $order_items, $type_array, ! $changes_supported );

				// Reset status
				$order->update_meta_data( 'status', $status );

				// Update recurring tax totals as described here: http://docs.woothemes.com/document/subscriptions/add-or-modify-a-subscription/#change-recurring-total
				if ( is_array ( $res ) ) {
					$tax = $shipping_tax = 0;

					foreach ( $res as $item ) {
						$item_id  = $item->ItemID;
						$item_tax = $item->TaxAmount;

						if ( $type_array[ $item_id ] == 'shipping' ) {
							$shipping_tax += $item_tax;
						} else {
							$tax += $item_tax;
						}

						if ( ! $changes_supported ) {
							$temp_warnings[] = 'Recurring tax for item #'. $item_id .' changed to '. wc_round_tax_total( $item_tax );
						}
					}

					// Only update if old and new tax totals do not correspond
					if ( $old_tax != $tax || $old_shipping_tax != $shipping_tax ) {
						if ( $changes_supported ) {
							if ( ! empty( $wootax_item_id ) ) {
								wc_update_order_item_meta( $wootax_item_id, 'tax_amount', $tax );
								wc_update_order_item_meta( $wootax_item_id, 'cart_tax', $tax );

								wc_update_order_item_meta( $wootax_item_id, 'shipping_tax_amount', $shipping_tax );
								wc_update_order_item_meta( $wootax_item_id, 'shipping_tax', $shipping_tax );
							}

							// Determine rounded difference in old/new tax totals
							$tax_diff         = ( $tax + $shipping_tax ) - ( $old_tax + $old_shipping_tax );
							$rounded_tax_diff = wc_round_tax_total( $tax_diff );

							// Set new recurring total by adding difference between old and new tax to existing total
							$new_recurring_total = $order->get_total() + $rounded_tax_diff;
							$order->set_total( $new_recurring_total );

							if ( $logger ) {
								$logger->add( 'wootax', 'Set recurring total for order '. $order_id .' to '. $new_recurring_total .'. Change: '. $tax_diff );
							}
						} else {
							$temp_warnings[] = 'Total recurring tax changed from '. wc_round_tax_total( $old_tax ) .' to '. wc_round_tax_total( $tax );
							$temp_warnings[] = 'Total recurring shipping tax changed from '. wc_round_tax_total( $old_shipping_tax ) .' to '. wc_round_tax_total( $shipping_tax );
							
							$show_warnings = true;
						}
					}

					// Add to warnings array if necessary
					if ( $show_warnings ) {
						$warnings[ $order_id ] = $temp_warnings;
					}
				}
			}

			if ( $logger ) {
				$logger->add( 'wootax', 'Ending recurring tax update.' );
			}
		} else if ( $logger ) {
			$logger->add( 'wootax', 'Ending recurring tax update. No subscriptions due before '. $date .'.' );
		}
	}
}

new SST_Subscriptions();