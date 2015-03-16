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
 *
 * If orders are found that are not updated in TaxCloud, add them to a queue to be updated
 *
 * @since 4.4
 * @param $last_checked   int   last checked order index
 * @return void
 */
function wootax_check_orders( $last_checked = 0 ) {
	global $wpdb;

	if ( wootax_get_option( 'check_orders' ) == 'no' )
		return;

	$order_count = 50; // Number of posts to check at one time
	$orders      = $wpdb->get_results( "SELECT ID, post_status FROM $wpdb->posts WHERE ID IN ( SELECT DISTINCT(post_id) FROM $wpdb->postmeta WHERE meta_key = '_wootax_pass_check' AND meta_value != 1 ) LIMIT $last_checked, $order_count ORDER BY ID DESC" );
	
	// Get order update queue
	$needs_update = get_option( 'wootax_needs_update' );
	$needs_update = !is_array( $needs_update ) ? array() : $needs_update;

	if ( count( $orders ) > 0 ) {
		foreach ( $orders as $order ) {
			// Skip processing if order is already in queue (could this happen?)
			if ( in_array( $order->ID, $needs_update ) )
				continue;

			// If the _wootax_taxcloud_ids meta key is an empty array, WooTax is not acting on this order. Skip it
			$taxcloud_ids = get_post_meta( $order->ID, '_wootax_taxclou_ids', true );
			
			if ( !is_array( $taxcloud_ids ) || count( $taxcloud_ids ) == 0 )
				continue;

			$woocommerce_status = $order->post_status;

			// For WooCommerce < 2.2, order statuses were stored in the shop_order_status taxonomy
			if ( version_compare( WOOCOMMERCE_VERSION, '2.2', '<' ) ) {
				$terms = wp_get_object_terms( $order->ID, 'shop_order_status', array( 'fields' => 'slugs' ) );
				$woocommerce_status = count( $terms > 0 ) ? $terms[0] : '';
			} 

			// Normalize status name
			$woocommerce_status = 'wc-' === substr( $woocommerce_status, 0, 3 ) ? substr( $woocommerce_status, 3 ) : $woocommerce_status;

			// Compare WooCommerce order status to TaxCloud status; add order to queue if the two do not correspond
			if ( ( $woocommerce_status == 'refunded' || $woocommerce_status == 'cancelled' ) && get_post_meta( $order->ID, '_wootax_refunded', true ) !== true ) {
				$needs_update[] = $order->ID;
			} else if ( $woocommerce_status == 'completed' && get_post_meta( $order->ID, '_wootax_captured', true ) !== true ) {
				$needs_update[] = $order->ID;
			} else {
				// Set _wootax_pass_check to 1 to avoid checking this order again
				update_post_meta( $order->ID, '_wootax_pass_check', 1 );
			}
		}

		$last_checked += $order_count;

		// Update needs_update option
		update_option( 'wootax_needs_update', $needs_update );

		// Schedule processing of next batch of orders
		wp_schedule_single_event( time(), 'wootax_check_orders', array( $last_checked ) );
	} else {
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

	$order_count = 20; // Number of orders to sync at one time; may need to be decreased in a low resource environment
	$refunded    = 0;
	$captured    = 0;

	// Fetch orders to sync
	$needs_sync = get_option( 'wootax_needs_update' );
	$needs_sync = !is_array ( $needs_sync ) ? array() : $needs_sync;

	// Fetch sync errors array
	$sync_errors = get_option( 'wootax_sync_errors' );
	$sync_errors = !is_array( $sync_errors ) ? array() : $sync_errors;

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
		$logger->add( 'wootax', 'Syncing '. $order_count .' more orders.' );
	}

	$order = $WC_WooTax_Order;

	for ( $i = $last_checked; $i < $last_checked + $order_count && $i < count( $needs_sync ); $i++ ) {
		$order_id = $needs_sync[$i];

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

			// Get price
			$price = $item['line_subtotal'] / $item['qty'];

			// Get TIC
			$tic = get_post_meta( $product_id, 'wootax_tic', true );
			$tic = $tic == false ? '' : $tic;

			// Get quantity
			$qty = $item['qty'];

			$item_data = array(
				'Index'  => '', // Leave Index blank because it is reassigned when WooTaxOrder::generate_lookup_data() is called
				'ItemID' => $item_id, 
				'Qty'    => $qty, 
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

		// Add shipping cost
		if ( $order->order->get_total_shipping() > 0 ) {
			$final_items[] = array(
				'Index'  => '', // Leave Index blank because it is reassigned when WooTaxOrder::generate_lookup_data() is called
				'ItemID' => WOOTAX_SHIPPING_ITEM, 
				'Qty'    => 1, 
				'Price'  => $order->order->get_total_shipping(),	
				'Type'   => 'shipping',
				'TIC'    => WOOTAX_SHIPPING_TIC,
			);

			$type_array[ WOOTAX_SHIPPING_ITEM ] = 'shipping';
		}

		// Issue Lookup
		$res = $order->do_lookup( $final_items, $type_array );

		if ( is_array( $res ) ) {

			$order->order->calculate_totals(); 
			
			// Determine normalized order (post) status
			$order_status = get_post_status( $order_id );

			if ( version_compare( WOOCOMMERCE_VERSION, '2.2', '<' ) ) {
				$terms = wp_get_object_terms( $order->ID, 'shop_order_status', array( 'fields' => 'slugs' ) );
				$order_status = count( $terms > 0 ) ? $terms[0] : '';
			} 

			// Normalize status name
			$order_status = 'wc-' === substr( $order_status, 0, 3 ) ? substr( $order_status, 3 ) : $order_status;

			// If status is "refunded," issue a full or partial refund
			// Otherwise, call WC_WooTax_Order->complete() to capture the order
			if ( $order_status == 'refunded' ) {

				// If order has not been captured, attempt to capture before refunding
				if ( !$order->captured ) {
					$res = $order->complete( $order_id, true );

					if ( $res !== true ) {
						$error = 'Syncing order '. $order_id .' failed [Refund]. Couldn\'t capture order. TaxCloud said: '. $res;

						if ( $logger ) {
							$logger->add( 'wootax', $error );
						}

						$sync_errors[ $order_id ] = $error;

						continue;
					}
				}

				$full_refund = true;

				if ( version_compare( WOOCOMMERCE_VERSION, '2.2', '>=' ) ) {
					$full_refund = $order->order->get_total_refunded() == $order->order->get_total();
				}

				if ( $full_refund ) {

					// Perform full refund
					$res = $order->refund( $order_id, true );

					if ( $res !== true ) {
						$error = 'Syncing order '. $order_id .' failed [Refund]. Couldn\'t refund order. TaxCloud said: '. $res;

						if ( $logger ) {
							$logger->add( 'wootax', $error );
						}

						$sync_errors[ $order_id ] = $error;

						continue;
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
								$error = 'Syncing order '. $order_id .' failed [Partial Refund]. Couldn\'t refund order. TaxCloud said: '. $res;

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

			} else {
				$res = $order->complete( $order_id, true );

				if ( $res !== true ) {
					$error = 'Syncing order '. $order_id .' failed [Captured]. TaxCloud said: '. $res;

					if ( $logger ) {
						$logger->add( 'wootax', $error );
					}

					$sync_errors[ $order_id ] = $error;
				} else {
					$captured++;
				}
			}

		} else {
			$error = 'Syncing order '. $order_id .' failed [Lookup]. TaxCloud said: '. $res;

			if ( $logger ) {
				$logger->add( 'wootax', $error );
			}

			$sync_errors[ $order_id ] = $error;
		}

		unset( $needs_sync[ $i ] );
	}

	if ( $logger ) {
		$logger->add( 'wootax', 'Done. '. $refunded .' orders refunded and '. $captured .' orders captured. Scheduling next sync.');
	}

	// If no orders are remaining in the processing queue, notify the site admin of any errors that occurred during the sync process
	if ( count ( $needs_sync ) == 0 ) {

		if ( wootax_get_option( 'send_notifications' ) != 'no' ) {

			$email = wootax_get_notification_email();

			if ( !empty( $email ) && is_email( $email ) ) {
				$subject = date( 'm/d/Y' ) . ': WooTax Order Synchronization Errors';
				$message = 'Hello,' ."\r\n\r\n" . 'You are receiving this message because you opted to receive WooTax error notifications at this email address. During the last order synchronization check, performed on '. date( 'm/d/Y' ) .', '. count( $sync_errors ) .' errors occurred. The specific errors are as follows:' ."\r\n\r\n";

				foreach ( $sync_errors as $order_id => $error ) {
					$message .= "- <strong>Order $order_id</strong>: ". $error ."\r\n";
				}

				$message .= "\r\n" . 'For assistance, please contact the WooTax support team at sales@wootax.com. To turn off these email notifications, deselect "Send me error notifications" under "Email Settings" on the WooTax settings page.';
			
				wp_mail( $email, $subject, $message );
			}

		}

	} else {
		// Schedule processing of next batch of orders
		wp_schedule_single_event( time(), 'wootax_sync_orders', array( $last_checked + $order_count ) );
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

}

add_action( 'wootax_update_recurring_tax', 'wootax_update_recurring_tax' );