<?php

/**
 * WooTax cronjobs
 *
 * @package WooCommerce TaxCloud
 * @since 4.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Do not all direct access 
}

/**
 * Update recurring tax for subscriptions
 * Not executed if Subscriptions plugin is not active
 *
 * @since 4.4
 * @return void
 */
function wootax_update_recurring_tax() {
	global $wpdb;

	// Exit if subs is not active
	if ( !WT_SUBS_ACTIVE ) {
		return;
	}

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

	// Get all scheduled "scheduled_subscription_payment" actions with post_date <= $twelve_hours
	$scheduled = $wpdb->get_results( $wpdb->prepare( "SELECT ID, post_content FROM $wpdb->posts WHERE post_status = %s AND post_title = %s AND post_date <= %s", "pending", "scheduled_subscription_payment", $date ) );

	// Update recurring totals if necessary
	if ( count( $scheduled ) > 0 ) {
		// This will hold any warning messages that need to be sent to the admin
		$warnings = array();

		foreach ( $scheduled as $action ) {
			$temp_warnings = array();
			$show_warnings = false;

			// Run json_decode on post_content to extract user_id and subscription_key
			$args = json_decode( $action->post_content );

			// Parse subscription_key to get order_id/product_id (format: ORDERID_PRODUCTID)
			$subscription_key = $args->subscription_key;
			$key_parts        = explode( '_', $subscription_key );
			
			$order_id   = (int) $key_parts[0];
			$product_id = (int) $key_parts[1];

			if ( get_post_status( $order_id ) == false ) {
				continue; // Skip if the order no longer exists
			}

			// Determine if changes to subscription amounts are allowed by the current gateway
			$chosen_gateway    = WC_Subscriptions_Payment_Gateways::get_payment_gateway( get_post_meta( $order_id, '_recurring_payment_method', true ) );
			$manual_renewal    = WC_Subscriptions_Order::requires_manual_renewal( $order_id );
			$changes_supported = ( $chosen_gateway === false || $manual_renewal == 'true' || $chosen_gateway->supports( 'subscription_amount_changes' ) ) ? true : false;

			// Load order
			$order = WT_Orders::get_order( $order_id );

			// Collect data for Lookup request
			$item_data = $type_array = array();

			// Add subscription
			$product = WC_Subscriptions::get_product( $product_id );

			// Get order item ID
			$item_id = $wpdb->get_var( "SELECT i.order_item_id FROM {$wpdb->prefix}woocommerce_order_items i LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta im ON im.order_item_id = i.order_item_id WHERE im.meta_key = '_product_id' AND im.meta_value = $product_id AND i.order_id = $order_id" );

			// Get price
			$recurring_subtotal = $order->get_item_meta( $item_id, '_recurring_line_subtotal' );
			$regular_subtotal   = $order->get_item_meta( $item_id, '_line_subtotal' );

			$price = $recurring_subtotal === '0' || !empty( $recurring_subtotal ) ? $recurring_subtotal : $regular_subtotal;

			// Special case: If _subscription_sign_up_fee is set and $price is equal to its value, fall back to product price
			if ( $order->get_item_meta( $item_id, '_subscription_sign_up_fee') == $price ) {
				$price = $product->get_price();
			}

			$item_info = array(
				'Index'  => '',
				'ItemID' => $item_id, 
				'Qty'    => 1,
				'Price'  => $price,	
				'Type'   => 'cart',
			);	

			$tic = get_post_meta( $product_id, 'wootax_tic', true );

			if ( !empty( $tic ) && $tic ) {
				$item_info['TIC'] = $tic;
			}

			$item_data[] = $item_info;
			$type_array[ $item_id ] = 'cart';

			// Add recurring shipping items
			foreach ( $order->order->get_items( 'recurring_shipping' ) as $item_id => $shipping ) {
				$item_data[] = array(
					'Index'  => '',
					'ItemID' => $item_id, 
					'TIC'    => WT_SHIPPING_TIC,
					'Qty'    => 1, 
					'Price'  => $shipping['cost'],	
					'Type'   => 'shipping',
				);

				$type_array[ $item_id ] = 'shipping';
			}

			// Reset "captured" meta so lookup always sent
			$captured = WT_Orders::get_meta( $order_id, 'captured' );
			WT_Orders::update_meta( $order_id, 'captured', false );

			// Issue Lookup request
			$res = $order->do_lookup( $item_data, $type_array, true );

			// Set "captured" back to original value
			WT_Orders::update_meta( $order_id, 'captured', $captured );

			// If lookup is successful, use result to update recurring tax totals as described here: http://docs.woothemes.com/document/subscriptions/add-or-modify-a-subscription/#change-recurring-total
			if ( is_array ( $res ) ) {
				// Find recurring tax item and determine original tax/shipping tax totals
				$wootax_item_id = $wpdb->get_var( $wpdb->prepare( "SELECT i.order_item_id FROM {$wpdb->prefix}woocommerce_order_items i LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta im ON im.order_item_id = i.order_item_id WHERE im.meta_key = %s AND im.meta_value = %d AND i.order_id = %d AND i.order_item_type = %s", "rate_id", WT_RATE_ID, $order_id, "recurring_tax" ) );
				
				$old_tax          = empty( $wootax_item_id ) ? 0 : $order->get_item_meta( $wootax_item_id, 'tax_amount' );
				$old_shipping_tax = empty( $wootax_item_id ) ? 0 : $order->get_item_meta( $wootax_item_id, 'shipping_tax_amount' );

				// Find new tax/shipping tax totals
				// Update _recurring_line_tax meta for each item
				$tax = $shipping_tax = 0;

				foreach ( $res as $item ) {
					$item_id  = $item->ItemID;
					$item_tax = $item->TaxAmount;

					if ( $type_array[ $item_id ] == 'shipping' ) {
						$shipping_tax += $item_tax;
					} else {
						$tax += $item_tax;
					}

					if ( $changes_supported ) {
						wc_update_order_item_meta( $item_id, '_recurring_line_tax', $item_tax );
						wc_update_order_item_meta( $item_id, '_recurring_line_subtotal_tax', $item_tax );
					} else {
						$temp_warnings[] = 'Recurring tax for item #'. $item_id .' changed to '. wc_round_tax_total( $item_tax );
					}
				}

				// Update recurring tax if necessary
				if ( $old_tax != $tax || $old_shipping_tax != $shipping_tax ) {
					if ( $changes_supported ) {
						if ( !empty( $wootax_item_id ) ) {
							wc_update_order_item_meta( $wootax_item_id, 'tax_amount', $tax );
							wc_update_order_item_meta( $wootax_item_id, 'cart_tax', $tax );

							wc_update_order_item_meta( $wootax_item_id, 'shipping_tax_amount', $shipping_tax );
							wc_update_order_item_meta( $wootax_item_id, 'shipping_tax', $shipping_tax );
						}

						// Determine rounded difference in old/new tax totals
						$tax_diff         = ( $tax + $shipping_tax ) - ( $old_tax + $old_shipping_tax );
						$rounded_tax_diff = wc_round_tax_total( $tax_diff );

						// Set new recurring total by adding difference between old and new tax to existing total
						$new_recurring_total = get_post_meta( $order_id, '_order_recurring_total', true ) + $rounded_tax_diff;
						update_post_meta( $order_id, '_order_recurring_total',  $new_recurring_total );

						if ( $logger ) {
							$logger->add( 'wootax', 'Set recurring total for order '. $order_id .' to: '. $new_recurring_total );
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
	} else if ( $logger ) {
		$logger->add( 'wootax', 'Ending recurring tax update. No subscriptions due before '. $date .'.' );
	}
}

add_action( 'wootax_update_recurring_tax', 'wootax_update_recurring_tax' );