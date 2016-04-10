<?php

/**
 * WooTax Subscriptions cronjobs
 *
 * @package WooTax Subscriptions
 * @author Brett Porcelli
 * @since 1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Do not all direct access 
}

/**
 * Update recurring tax for subscriptions
 * Not executed if Subscriptions plugin is not active
 *
 * @return void
 * @since 1.0
 */
function wootax_update_recurring_tax() {
	global $wpdb;

	// Exit if subs is not active
	if ( ! WT_SUBS_ACTIVE )
		return;

	$subs_20_or_greater = wt_is_subs_2_0();

	// Find date/time 12 hours from now
	$twelve_hours = mktime( date('H') + 12 );

	$date = new DateTime( date( 'c', $twelve_hours ) ); 
	$date = $date->format( 'Y-m-d H:i:s' );

	// Set up logger
	$logger = false;

	if ( WT_LOG_REQUESTS ) {
		$logger = class_exists( 'WC_Logger' ) ? new WC_Logger() : WC()->logger();
		$logger->add( 'wootax', 'Starting recurring tax update. Subscriptions with payments due before '. $date .' are being considered.' );
	}

	// Get all scheduled "scheduled_subscription_payment" (1.5) or "woocommerce_scheduled_subscription_payment" actions with post_date <= $twelve_hours
	$action_hook = 'scheduled_subscription_payment';

	if ( $subs_20_or_greater ) {
		$action_hook = 'woocommerce_' . $action_hook;
	}

	$scheduled = $wpdb->get_results( $wpdb->prepare( "SELECT ID, post_content FROM $wpdb->posts WHERE post_status = %s AND post_title = %s AND post_date <= %s", "pending", $action_hook, $date ) );

	// Update recurring totals if necessary
	if ( count( $scheduled ) > 0 ) {
		$warnings = array();

		foreach ( $scheduled as $action ) {
			$temp_warnings = array();
			$show_warnings = false;

			$args = json_decode( $action->post_content );

			// Build order object 
			if ( $subs_20_or_greater ) {
				$order_id = $args->subscription_id;
			} else {
				$subscription_key = $args->subscription_key;
				$key_parts        = explode( '_', $subscription_key );
				
				$order_id = (int) $key_parts[0];
			}

			if ( false === get_post_type( $order_id ) ) {
				continue;
			}

			$wt_order = WT_Orders::get_order( $order_id );
			$order = $wt_order->order;

			// Determine whether or not selected payment gateway supports changes to recurring totals
			$changes_supported = wt_is_manual( $order ) || wt_can_change( $order );

			// Build and format order items array
			$order_items = $type_array = array();

			foreach ( $order->get_items() as $item_id => $item ) {
				if ( $subs_20_or_greater || WC_Subscriptions_Order::is_item_subscription( $order, $item ) ) {
					$tic = wt_get_product_tic( $item['product_id'], $item['variation_id'] );
					$qty = isset( $item['qty'] ) ? $item['qty'] : 1;

					$recurring_subtotal = isset( $item['item_meta']['_recurring_line_subtotal'] ) ? $item['item_meta']['_recurring_line_subtotal'][0] : 0;
					$regular_subtotal   = isset( $item['item_meta']['_line_total'] ) ? $item['item_meta']['_line_total'][0] : 0;

					$cost = $recurring_subtotal === '0' || ! empty( $recurring_subtotal ) ? $recurring_subtotal : $regular_subtotal;

					// Special case: If _subscription_sign_up_fee is set and $cost is equal to its value, fall back to product price
					$sign_up_fee = $subs_20_or_greater ? $order->get_items_sign_up_fee( $item ) : wc_get_order_item_meta( $item_id, '_subscription_sign_up_fee', true );

					if ( $sign_up_fee == $cost ) {
						$cost = $item['data']->get_price();
					}

					if ( $cost != 0 ) {
						$unit_price = $cost / $qty;

						if ( WC_WooTax::get_option( 'tax_based_on' ) != 'line-subtotal' ) {
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
						$item['TIC'] = $tic;
					}

					$type_array[ $item_id ] = 'cart';
					$order_items[] = $item;
				}
			}

			foreach ( $order->get_fees() as $fee_id => $fee ) {
				if ( $subs_20_or_greater || $fee['recurring_line_total'] > 0 ) {
					$item = array(
						'Index'  => '',
						'ItemID' => $fee_id,
						'Qty'    => 1,
						'Price'  => $fee['recurring_line_total'],
						'Type'   => 'cart',
						'TIC'    => WT_FEE_TIC,
					);

					$type_array[ $fee_id ] = 'fee';
					$order_items[] = $item;
				}
			}

			$shipping_methods = $subs_20_or_greater ? $order->get_shipping_methods() : WC_Subscriptions_Order::get_recurring_shipping_methods( $order );

			foreach ( $shipping_methods as $method_id => $method ) {
				$item = array(
					'Index'  => '',
					'ItemID' => $method_id,
					'Qty'    => 1,
					'Price'  => isset( $method['cost'] ) ? $method['cost'] : $method['line_total'], // 'cost' key used by shipping methods in 2.2
					'Type'   => 'shipping',
					'TIC'    => WT_SHIPPING_TIC,
				);

				$type_array[ $method_id ] = 'shipping';
				$order_items[] = $item;
			}

			// Set "captured" flag to false so a lookup is always sent
			$captured = WT_Orders::get_meta( $order_id, 'captured' );
			WT_Orders::update_meta( $order_id, 'captured', false );

			// Store old tax totals, then issue lookup request
			$old_tax = $old_shipping_tax = $wootax_item_id = 0;
			$taxes = $subs_20_or_greater ? $order->get_items( 'tax' ) : $order->get_items( 'recurring_tax' );

			foreach ( $taxes as $item_id => $item ) {
				if ( $item['rate_id'] == WT_RATE_ID ) {
					$wootax_item_id   = $item_id;
					$old_tax          = $item['tax_amount'];
					$old_shipping_tax = $item['shipping_tax_amount'];
				}
			}

			$res = $wt_order->do_lookup( $order_items, $type_array, $subs_20_or_greater && $changes_supported ? false : true );

			// Reset captured flag
			WT_Orders::update_meta( $order_id, 'captured', $captured );

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

					if ( $changes_supported && ! $subs_20_or_greater ) {
						wc_update_order_item_meta( $item_id, '_recurring_line_tax', $item_tax );
						wc_update_order_item_meta( $item_id, '_recurring_line_subtotal_tax', $item_tax );
					} else if ( ! $changes_supported ) {
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
						if ( ! $subs_20_or_greater ) {
							$new_recurring_total = get_post_meta( $order_id, '_order_recurring_total', true ) + $rounded_tax_diff;
							update_post_meta( $order_id, '_order_recurring_total',  $new_recurring_total );
						} else {
							$new_recurring_total = $order->get_total() + $rounded_tax_diff;
							$order->set_total( $new_recurring_total );
						}

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

		// Send out a single warning email to the admin if necessary
		// Ex: Email sent if a change in tax rates is detected and the gateway used by an order doesn't allow modification of sub. details
		if ( count( $warnings ) > 0 ) {
			$email = wootax_get_notification_email();

			if ( !empty( $email ) && is_email( $email ) ) {
				$subject = 'WooTax Warning: Recurring Tax Totals Need To Be Updated';
				$message = 'Hello,' ."\r\n\r\n" . 'During a routine check on '. date( 'm/d/Y') .', WooTax discovered '. count( $warnings ) .' subscription orders whose recurring tax totals need to be updated. Unfortunately, the payment gateway(s) used for these orders does not allow subscription details to be altered, so the required changes must be implemented manually. All changes are listed below for your convenience.' ."\r\n\r\n";

				foreach ( $warnings as $order_id => $errors ) {
					$message .= 'Order '. $order_id .': '. "\r\n\r\n";
						
					foreach ( $errors as $error ) {
						$message .= '- '. $error . "\r\n";
					}

					$message .= "\r\n\r\n";
				}

				$message .= 'For assistance, please contact the WooTax support team at sales@wootax.com.';

				wp_mail( $email, $subject, $message );
			}
		}

		if ( $logger ) {
			$logger->add( 'wootax', 'Ending recurring tax update.' );
		}
	} else if ( $logger ) {
		$logger->add( 'wootax', 'Ending recurring tax update. No subscriptions due before '. $date .'.' );
	}
}

add_action( 'wootax_update_recurring_tax', 'wootax_update_recurring_tax' );