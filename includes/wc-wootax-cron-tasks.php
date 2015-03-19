<?php

/**
 * WooTax cronjobs
 *
 * @since 4.4
 */

/**
 * Check WooTax Orders
 *
 * Checks to make sure that all orders are updated in TaxCloud
 * - Orders that are marked as refunded should have the TaxCloud status "Refunded"
 * - Orders that are completed should have the TaxCloud status "Completed"
 * - Orders that have more than one associated refund order should have TaxCloud status "Refunded"
 *
 * If orders are found that are not updated in TaxCloud, add them to a queue to be updated
 *
 * @since 4.4
 * @param $last_checked   int   the last checked order index
 * @return void
 */
function wootax_check_orders( $last_checked = 0 ) {
	global $wpdb, $woocommerce;

	if ( wootax_get_option( 'check_orders' ) == 'no' ) {
		return;
	}

	// Set up logger
	$logger = false;

	if ( wootax_get_option( 'log_requests' ) != 'no' ) {
		$logger = class_exists( 'WC_Logger' ) ? new WC_Logger() : $woocommerce->logger();

		if ( $last_checked == 0 ) {
			$logger->add( 'wootax', 'Starting order check.' );
		}
	}	

	// Get date/time for 12am n days ago
	$n_days     = 3;
	$n_days_ago = mktime( 00, 00, 00, date('n'), date('j') - $n_days, date('Y') );

	$date = new DateTime( date( 'c', $n_days_ago ) ); 
	$date = $date->format( 'Y-m-d H:i:s' );

	// Fetch $order_count posts that are less than n days old
	$order_count = 25;
	$orders      = $wpdb->get_results( $wpdb->prepare( "SELECT ID, post_status FROM $wpdb->posts WHERE post_date >= %s AND post_type = 'shop_order' LIMIT %d, %d", $date, $last_checked, $order_count ) );

	// Get order update queue (reset to empty array first time method is called)
	$needs_update = $last_checked == 0 ? array() : get_option( 'wootax_needs_update' );

	if ( count( $orders ) > 0 ) {
		foreach ( $orders as $order ) {
			// If the _wootax_taxcloud_ids meta key is an empty array, WooTax is not acting on this order. Skip it
			$taxcloud_ids = get_post_meta( $order->ID, '_wootax_taxcloud_ids', true );
			
			if ( !is_array( $taxcloud_ids ) || count( $taxcloud_ids ) == 0 ) {
				continue;
			}

			$woocommerce_status = $order->post_status;

			// For WooCommerce < 2.2, order statuses were stored in the shop_order_status taxonomy
			if ( version_compare( WOOCOMMERCE_VERSION, '2.2', '<' ) ) {
				$terms = wp_get_object_terms( $order->ID, 'shop_order_status', array( 'fields' => 'slugs' ) );
				$woocommerce_status = count( $terms > 0 ) ? $terms[0] : '';
			} 

			// Normalize status name
			$woocommerce_status = 'wc-' === substr( $woocommerce_status, 0, 3 ) ? substr( $woocommerce_status, 3 ) : $woocommerce_status;

			// Compare WooCommerce order status to TaxCloud status; add order to queue if the two do not correspond
			if ( ( $woocommerce_status == 'refunded' || $woocommerce_status == 'cancelled' || $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(ID) FROM $wpdb->posts WHERE post_type = 'shop_order_refund' AND post_parent = %d", $order->ID ) ) > 0 ) && get_post_meta( $order->ID, '_wootax_refunded', true ) != true ) {
				$needs_update[] = array( 'id' => $order->ID, 'type' => 'refund' );
			} else if ( $woocommerce_status == 'completed' && get_post_meta( $order->ID, '_wootax_captured', true ) != true ) {
				$needs_update[] = array( 'id' => $order->ID, 'type' => 'capture' );
			}

		}

		$last_checked += $order_count;

		// Update needs_update option
		update_option( 'wootax_needs_update', $needs_update );

		// Schedule processing of next batch of orders
		wp_schedule_single_event( time(), 'wootax_check_orders', array( $last_checked ) );
	} else {
		if ( $logger ) {
			$logger->add( 'wootax', 'Order check complete. '. count( $needs_update ) .' orders need to be synced.' );
		}

		// Schedule order synchronization
		wp_schedule_single_event( time(), 'wootax_sync_orders' );
	}
}

add_action( 'wootax_check_orders', 'wootax_check_orders' );

/**
 * Attempt to synchronize orders with TaxCloud
 * Keep track of orders that are synced and log results if necessary
 *
 * @since 4.4
 * @param $last_checked   int   the index of the last checked order
 * @return void
 */
function wootax_sync_orders( $last_checked = 0 ) {
	global $wpdb, $WC_WooTax_Order;

	$order_count = 10; // Number of orders to sync at one time; may need to be decreased in a low resource environment
	$refunded    = 0;
	$captured    = 0;

	// Fetch orders to sync
	$needs_sync = get_option( 'wootax_needs_update' );
	$needs_sync = !is_array ( $needs_sync ) ? array() : $needs_sync;

	// Fetch sync errors array (reset on first iteration)
	$sync_errors = $last_checked == 0 ? array() : get_option( 'wootax_sync_errors' );

	// Set up logger
	$logger = false;

	if ( wootax_get_option( 'log_requests' ) != 'no' ) {
		$logger = class_exists( 'WC_Logger' ) ? new WC_Logger() : $woocommerce->logger();
	}
	
	// Skip if we have no orders to sync
	if ( count ( $needs_sync ) == 0 ) {
		if ( $logger ) {
			$logger->add( 'wootax', 'Ending order synchronization: No more orders to sync.');
		}

		return;
	}

	// Mark start of sync process if $last_checked is 0
	if ( $last_checked == 0 && $logger ) {
		$logger->add( 'wootax', 'Starting order synchronization: '. count( $needs_sync ) .' orders to sync.' );
	} else if ( $last_checked > 0 && $logger ) {
		$logger->add( 'wootax', 'Syncing '. $order_count .' more orders. Last checked order index: '. $last_checked .'.' );
	}

	$order   = $WC_WooTax_Order;	
	$checked = 0; 
	$last    = $last_checked;

	while ( count( $needs_sync ) > 0 && $checked < $order_count ) {

		$order_data = $needs_sync[ $last ];

		$order_id = $order_data['id'];
		$type     = $order_data['type'];

		// Load order
		$order->load_order( $order_id );

		// Prepare order data for Lookup
		$final_items = $type_array = array();

		// Add items
		foreach ( $order->order->get_items() as $item_id => $item ) {
			$product_id = $order->get_item_meta( $item_id, '_product_id' );

			if ( get_post_type( $product_id ) == 'product' ) {
				$product = new WC_Product( $product_id );
					
				if ( !$product->is_taxable() ) {
					continue;
				}
			} 

			// Get price (MAKE SURE line_subtotal/qty EXIST FOR 2.1 - 2.3)
			$price = $item['line_subtotal'] / $item['qty'];

			// Get TIC
			$tic = get_post_meta( $product_id, 'wootax_tic', true );
			$tic = $tic == false ? '' : $tic;

			$item_data = array(
				'Index'  => '', // Leave Index blank because it is reassigned when WooTaxOrder::generate_lookup_data() is called
				'ItemID' => $item_id, 
				'Qty'    => $item['qty'], 
				'Price'  => $price,	
				'Type'   => 'cart',
			);

			if ( !empty( $tic ) ) {
				$item_data['TIC'] = $tic;
			}

			$final_items[] = $item_data;
			$type_array[ $item_id ] = 'cart';
		}

		// Add fees
		foreach ( $order->order->get_fees() as $item_id => $fee ) {
			$final_items[] = array(
				'Index'  => '', // Leave Index blank because it is reassigned when WooTaxOrder::generate_lookup_data() is called
				'ItemID' => $item_id, 
				'Qty'    => 1, 
				'Price'  => $fee['line_subtotal'],	
				'Type'   => 'fee',
				'TIC'    => WOOTAX_FEE_TIC,
			);

			$type_array[ $item_id ] = 'cart';
		}

		// Add shipping costs
		// MAKE SURE 'cost' INDEX IS SET IN 2.1+
		foreach ( $order->order->get_shipping_methods() as $item_id => $shipping ) {
			$final_items[] = array(
				'Index'  => '', // Leave Index blank because it is reassigned when WooTaxOrder::generate_lookup_data() is called
				'ItemID' => $item_id, 
				'Qty'    => 1, 
				'Price'  => $shipping['cost'],	
				'Type'   => 'shipping',
				'TIC'    => WOOTAX_SHIPPING_TIC,
			);

			$type_array[ $item_id ] = 'shipping';
		}

		// Update order destination address
		$order->destination_address = $order->get_destination_address();

		// Issue Lookup
		$res = $order->do_lookup( $final_items, $type_array );

		if ( is_array( $res ) ) {
			$order->order->calculate_totals( false ); // False flag prevents WooCommerce from calculating taxes

			// Send AuthorizeWithCapture or Returned request for each order, as is necessary
			if ( $type == 'capture' ) {

				$res = $order->complete( $order_id, true );

				if ( $res !== true ) {
					$error = 'Syncing order failed [Captured].';

					if ( $logger ) {
						$logger->add( 'wootax', $error );
					}

					$sync_errors[ $order_id ] = $error;
				} else {
					$captured++;
				}

			} else {

				// If order has not been captured, attempt to capture before refunding
				if ( !$order->captured ) {
					$res = $order->complete( $order_id, true );

					if ( $res !== true ) {
						$error = 'Syncing order failed [Refund]. Couldn\'t capture order.';

						if ( $logger ) {
							$logger->add( 'wootax', $error );
						}

						$sync_errors[ $order_id ] = $error;
					}
				}

				// Process full or partial refund if no errors have occurred
				if ( !isset( $sync_errors[ $order_id ] ) ) {
					$full_refund = true;

					if ( version_compare( WOOCOMMERCE_VERSION, '2.2', '>=' ) && $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(ID) FROM $wpdb->posts WHERE post_type = 'shop_order_refund' AND post_parent = %d", $order_id ) ) > 0 ) {
						$full_refund = $order->order->get_total_refunded() == $order->order->get_total();
					}

					if ( $full_refund ) {

						// Perform full refund
						$res = $order->refund( $order_id, true );

						if ( $res !== true ) {
							$error = 'Syncing order failed [Refund]. Couldn\'t refund order.';

							if ( $logger ) {
								$logger->add( 'wootax', $error );
							}

							$sync_errors[ $order_id ] = $error;
						} else {
							$refunded++;
						}

					} else {

						// Perform partial refund(s)
						$refunds = $order->order->get_refunds();

						if ( count( $refunds ) == 0 ) {
							$refunded++;
						} else {

							// Loop through refunds and send partial refund request for each
							foreach ( $refunds as $refund ) {
								$res = process_refund( $refund, true );

								if ( $res !== true ) {
									$error = 'Syncing order failed [Partial Refund]. Couldn\'t refund order.';

									if ( $logger ) {
										$logger->add( 'wootax', $error );
									}

									$sync_errors[ $order_id ] = $error;
								}
							}

							if ( !isset( $sync_errors[ $order_id ] ) ) {
								$refunded++;
							}

						}

					}

				}

			} 

		} else {
			$error = 'Syncing order failed [Lookup].';

			if ( $logger ) {
				$logger->add( 'wootax', $error );
			}

			$sync_errors[ $order_id ] = $error;
		}

		unset( $needs_sync[ $last ] );

		$last++;
		$checked++;

	}

	if ( $logger ) {
		$logger->add( 'wootax', $refunded .' orders refunded and '. $captured .' orders captured.');
	}

	// If no orders are remaining in the processing queue, notify the site admin of any errors that occurred during the sync process
	if ( count ( $needs_sync ) == 0 ) {

		if ( $logger ) {
			$logger->add( 'wootax', 'Sync complete.' );
		}

		if ( wootax_get_option( 'send_notifications' ) != 'no' && count( $sync_errors ) > 0 ) {
			$email = wootax_get_notification_email();

			if ( !empty( $email ) && is_email( $email ) ) {
				$subject = date( 'm/d/Y' ) . ': WooTax Order Synchronization Errors';
				$message = 'Hello,' ."\r\n\r\n" . 'You are receiving this message because you opted to receive WooTax error notifications at this email address. During the last order synchronization check, performed on '. date( 'm/d/Y' ) .', '. count( $sync_errors ) .' errors occurred. The specific errors are as follows:' ."\r\n\r\n";

				foreach ( $sync_errors as $order_id => $error ) {
					$message .= "- Order $order_id: $error\r\n";
				}

				$message .= "\r\n" . 'For assistance, please contact the WooTax support team at sales@wootax.com. To turn off these email notifications, set the "Email Error Notifications" setting to "No."';

				wp_mail( $email, $subject, $message );
			}

			// Reset sync errors
			$sync_errors = array();
		}

	} else {
		if ( $logger ) {
			$logger->add( 'wootax', 'Scheduling next sync.' );
		}

		// Schedule processing of next batch of orders
		wp_schedule_single_event( time(), 'wootax_sync_orders', array( $last ) );
	}

	// Update needs_sync array
	update_option( 'wootax_needs_update', $needs_sync );

	// Update sync_errors array 
	update_option( 'wootax_sync_errors', $sync_errors );
}

add_action( 'wootax_sync_orders', 'wootax_sync_orders' );

/**
 * Update recurring tax for subscriptions
 * Not executed if Subscriptions plugin is not active
 *
 * @since 4.4
 * @return void
 */
function wootax_update_recurring_tax() {
	global $wpdb, $WC_WooTax_Order;

	// Exit if subs is not active
	if ( !is_plugin_active( 'woocommerce-subscriptions/woocommerce-subscriptions.php' ) ) {
		return;
	}

	// Find date/time 12 hours from now
	$twelve_hours = mktime( date('H') + 12 );

	$date = new DateTime( date( 'c', $twelve_hours ) ); 
	$date = $date->format( 'Y-m-d H:i:s' );

	// Set up logger
	$logger = false;

	if ( wootax_get_option( 'log_requests' ) != 'no' ) {
		$logger = class_exists( 'WC_Logger' ) ? new WC_Logger() : $woocommerce->logger();
		$logger->add( 'wootax', 'Starting recurring tax update. Subscriptions with payments due before '. $date .' are being considered.' );
	}

	// Get all scheduled "scheduled_subscription_payment" actions with post_date <= $twelve_hours
	$scheduled = $wpdb->get_results( $wpdb->prepare( "SELECT ID, post_content FROM $wpdb->posts WHERE post_status = %s AND post_title = %s AND post_date <= %s", "pending", "scheduled_subscription_payment", $date ) );

	// Update recurring totals if necessary
	if ( count( $scheduled ) > 0 ) {

		$order = $WC_WooTax_Order;

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

			// Load order using global WC_WooTax_Order object
			$order->load_order( $order_id );
			$order->destination_address = $order->get_destination_address();

			// Collect data for Lookup request
			$item_data = $type_array = array();

			// Add subscription
			$product = WC_Subscriptions::get_product( $product_id );

			if ( $product->get_tax_status() == 'taxable' ) {
				// Get order item ID
				$item_id = $wpdb->get_var( "SELECT i.order_item_id FROM {$wpdb->prefix}woocommerce_order_items i LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta im ON im.order_item_id = i.order_item_id WHERE im.meta_key = '_product_id' AND im.meta_value = $product_id AND i.order_id = $order_id" );

				// Get price
				$recurring_subtotal = (float) $order->get_item_meta( $item_id, '_recurring_line_subtotal' );
				$regular_subtotal   = (float) $order->get_item_meta( $item_id, '_line_subtotal' );

				$price = !empty( $recurring_subtotal ) && $recurring_subtotal ? $recurring_subtotal : $regular_subtotal;

				$item_info = array(
					'Index'  => '', // Leave Index blank because it is reassigned when WooTaxOrder::generate_lookup_data() is called
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
			}

			// Add recurring shipping items
			foreach ( $order->order->get_items( 'recurring_shipping' ) as $item_id => $shipping ) {
				$item_data[] = array(
					'Index'  => '', // Leave Index blank because it is reassigned when WooTaxOrder::generate_lookup_data() is called
					'ItemID' => $item_id, 
					'TIC'    => WOOTAX_SHIPPING_TIC,
					'Qty'    => 1, 
					'Price'  => $shipping['cost'],	
					'Type'   => 'shipping',
				);

				$type_array[ $item_id ] = 'shipping';
			}

			// Reset "captured" meta so lookup always sent
			$captured = $order->captured;
			$order->captured = false;

			// Issue Lookup request
			$res = $order->do_lookup( $item_data, $type_array, true );

			// Set "captured" back to original value
			$order->captured = $captured;

			// If lookup is successful, use result to update recurring tax totals as described here: http://docs.woothemes.com/document/subscriptions/add-or-modify-a-subscription/#change-recurring-total
			if ( is_array ( $res ) ) {

				// Find recurring tax item and determine original tax/shipping tax totals
				$wootax_rate_id = get_option( 'wootax_rate_id' );
				$wootax_item_id = $wpdb->get_var( $wpdb->prepare( "SELECT i.order_item_id FROM {$wpdb->prefix}woocommerce_order_items i LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta im ON im.order_item_id = i.order_item_id WHERE im.meta_key = %s AND im.meta_value = %d AND i.order_id = %d AND i.order_item_type = %s", "rate_id", $wootax_rate_id, $order_id, "recurring_tax" ) );
				
				$old_tax          = empty( $wootax_item_id ) ? 0 : (float) $order->get_item_meta( $wootax_item_id, 'tax_amount' );
				$old_shipping_tax = empty( $wootax_item_id ) ? 0 : (float) $order->get_item_meta( $wootax_item_id, 'shipping_tax_amount' );

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

				// Update recurring tax item
				if ( ( $old_tax != $tax || $old_shipping_tax != $shipping_tax ) && $changes_supported ) {
					if ( !empty( $wootax_item_id ) ) {
						$rounded_tax          = wc_round_tax_total( $tax );
						$rounded_shipping_tax = wc_round_tax_total( $shipping_tax );

						wc_update_order_item_meta( $wootax_item_id, 'tax_amount', $rounded_tax );
						wc_update_order_item_meta( $wootax_item_id, 'cart_tax', $rounded_tax );

						wc_update_order_item_meta( $wootax_item_id, 'shipping_tax_amount', $rounded_shipping_tax );
						wc_update_order_item_meta( $wootax_item_id, 'shipping_tax', $rounded_shipping_tax );
					}

					// Determine rounded difference in old/new tax totals
					$tax_diff         = ( $tax + $shipping_tax ) - ( $old_tax + $old_shipping_tax );
					$rounded_tax_diff = wc_round_tax_total( $tax_diff );

					// Set new recurring total by adding difference between old and new tax to existing total
					$new_recurring_total = (float) get_post_meta( $order_id, '_order_recurring_total', true ) + $rounded_tax_diff;
					update_post_meta( $order_id, '_order_recurring_total',  $new_recurring_total );

					if ( $logger ) {
						$logger->add( 'wootax', 'Set recurring total for order '. $order_id .' to: '. $new_recurring_total );
					}
				} else if ( $old_tax != $tax || $old_shipping_tax != $shipping_tax ) {
					$temp_warnings[] = 'Total recurring tax changed from '. wc_round_tax_total( $old_tax ) .' to '. wc_round_tax_total( $tax );
					$temp_warnings[] = 'Total recurring shipping tax changed from '. wc_round_tax_total( $old_shipping_tax ) .' to '. wc_round_tax_total( $shipping_tax );
					
					$show_warnings = true;
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