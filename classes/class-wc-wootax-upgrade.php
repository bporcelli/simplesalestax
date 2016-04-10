<?php

/**
 * Scripts for updating WooTax data
 *
 * @since 4.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Do not allow direct access 
}

class WC_WooTax_Upgrade {
	/** Stored plugin version */
	private static $db_version;

	/**
	 * Initialize updater
	 *
	 * @since 4.4
	 */
	public static function init() {
		self::$db_version = get_option( 'wootax_version' );

		self::hooks();
		self::maybe_update_wootax();
	}

	/**
	 * Hooks into WordPress actions/filters
	 *
	 * @since 4.4
	 */
	private static function hooks() {
		// Add admin page for data update process to occur on
		add_action( 'admin_menu', array( __CLASS__, 'add_admin_page' ) );

		// AJAX hook for data update process
		add_action( 'wp_ajax_wootax-update-data', array( __CLASS__, 'update_post_data' ) );
	}

	/**
	 * Add admin page for data update process to occur on
	 *
	 * @since 4.4
	 */
	public static function add_admin_page() {
		add_submenu_page( '_nonexistent', 'WooTax Data Update', 'WooTax Data Update', 'manage_options', 'wt-update', array( __CLASS__, 'display_admin_page' ) );
	}

	/**
	 * Dismiss update nag message when admin completes the data update process
	 *
	 * @since 4.4
	 */
	private static function dismiss_update_message() {
		wootax_remove_message( 'upgrade-message' );
	}

	/**
	 * Display data update interface
	 *
	 * @since 4.4
	 */
	public static function display_admin_page() {
		include WT_PLUGIN_PATH .'templates/admin/data-update.php';
	}

	/**
	 * Determines whether or not WooTax needs to be updated
	 * If a data update is necessary, initiates update by warning admin
	 *
	 * @since 4.4
	 */
	public static function maybe_update_wootax() {
		global $wpdb;

		if ( version_compare( self::$db_version, WT_VERSION, '=' ) ) {
			return;
		} else {
			self::maybe_update_addresses();
			self::maybe_update_settings();

			if ( self::needs_data_update() ) {
				wootax_add_message( '<strong>WooTax data update required.</strong> Please backup your database, then click "Complete Update" to run the updater. <a class="button button-primary" href="'. admin_url( 'admin.php?page=wt-update' ) .'">Complete Update</a>', 'update-nag', 'upgrade-message', true, false );
			} else {
				update_option( 'wootax_version', WT_VERSION );
			}
		}
	}

	/**
	 * Maybe update address system from old, single address system to newer multi-address system
	 *
	 * @since 4.4
	 */
	private static function maybe_update_addresses() {
		if ( get_option( 'wootax_address1' ) ) {
			// Set new address array 
			WC_WooTax::set_option( 'wootax_addresses', fetch_business_addresses() );

			// Delete old options
			delete_option( 'wootax_address1' );
			delete_option( 'wootax_address2' );
			delete_option( 'wootax_state' );
			delete_option( 'wootax_city' );
			delete_option( 'wootax_zip5' );
			delete_option( 'wootax_zip4' );
		}
	}

	/**
	 * Transfer settings from old option fields to new system based on WC Settings API
	 *
	 * @since 4.4
	 */
	private static function maybe_update_settings() {
		// Delete deprecated "wootax_shipping_taxable" option if it still exists
		if ( get_option( 'wootax_shipping_taxable' ) ) {
			delete_option( 'wootax_shipping_taxable' );
		}

		// Transfer settings so they can be used with WooCommerce settings API
		$options = array(
			'tc_id',
			'tc_key',
			'usps_id',
			'show_exempt',
			'exemption_text',
			'company_name',
			'show_zero_tax',
			'tax_based_on',
			'addresses',
			'default_address',
		);
		
		foreach ( $options as $option ) {
			if ( get_option( 'wootax_' . $option ) ) {
				WC_WooTax::set_option( $option, get_option( 'wootax_' . $option ) );
				delete_option( 'wootax_' . $option );
			}
		}

		// wootax_license_key option was deprecated in 4.5; remove it
		if ( get_option( 'wootax_license_key' ) ) {
			delete_option( 'wootax_license_key' );
		}
	}

	/** 
	 * Determine if a data update is necessary
	 *
	 * @return (bool) 
	 */
	private static function needs_data_update() {
		$needs_update = false;

		// In version 4.2, major changes were made to how data is stored; wootax_order post type was eliminated
		if ( version_compare( self::$db_version, '4.2', '<' ) || !self::$db_version ) {
			$needs_update = count( get_posts( 'post_type=wootax_order&posts_per_page=1&post_status=any' ) ) == 1;
		}

		return $needs_update;
	}

	/**
	 * Delete remaining wootax_order posts from database
	 * Used in upgrade to 4.2
	 */
	private static function remove_order_posts() {
		global $wpdb;

		// Delete wootax_order posts and associated meta
		$wpdb->query( "DELETE p, pm FROM $wpdb->posts p LEFT JOIN $wpdb->postmeta pm ON pm.post_id = p.ID WHERE p.post_type = 'wootax_order'" );
	}

	/**
	 * Perform data update
	 *
	 * @return JSON object with information about number of posts remaining, current update status
	 */
	public static function update_post_data() {
		global $wpdb;

		// Number of posts to process at once
		$posts_per_page = 10;

		// Index of last processed post
		$last_post = $_POST['last_post'];

		// Page counters
		$total_pages  = $last_post == 0 ? 0 : $_POST['total_pages'];
		$current_page = $last_post == 0 ? 1 : $_POST['current_page'];

		// On first run, determine $total_count/$total_pages
		if ( $last_post == 0 ) {
			$total_count = $wpdb->get_var( "SELECT COUNT(ID) FROM $wpdb->posts WHERE post_type = 'wootax_order'" );
	
			if ( $total_count == 0 ) {
				update_option( 'wootax_version', WT_VERSION );

				self::dismiss_update_message();

				die ( json_encode( array( 
					'status'   => 'done', 
					'message'  => 'No more posts to update. Redirecting...',
					'redirect' => get_admin_url( 'plugins.php' ),
				) ) );
			}
			
			$total_pages = ceil( $total_count / $posts_per_page );
		}

		// Select posts from index $last_post to $posts_per_page for processing
		$posts = $wpdb->get_results( "SELECT p.ID AS WTID, pm.meta_value AS WCID FROM $wpdb->posts p LEFT JOIN $wpdb->postmeta pm ON pm.post_id = p.ID WHERE p.post_type = 'wootax_order' AND pm.meta_key = '_wootax_wc_order_id' ORDER BY p.ID ASC LIMIT $last_post, $posts_per_page" );

		if ( count ( $posts ) == 0 ) {
			update_option( 'wootax_version', WT_VERSION );

			self::dismiss_update_message();
			self::remove_order_posts();

			die ( json_encode( array( 
				'status'   => 'done', 
				'message'  => 'No more posts to update. Redirecting...',
				'redirect' => get_admin_url( 'plugins.php' ),
			) ) );
		}

		// Loop through posts and update
		foreach ( $posts as $post ) {
			$wt_order_id = $post->WTID;
			$wc_order_id = $post->WCID;

			// Transfer meta that doesn't need to be changed
			$direct_meta_keys = array( 'tax_total', 'shipping_tax_total', 'captured', 'refunded', 'customer_id', 'tax_item_id', 'exemption_applied' );

			foreach ( $direct_meta_keys as $key ) {
				update_post_meta( $wc_order_id, '_wootax_' . $key, get_post_meta( $wt_order_id, '_wootax_' . $key, true ) );
			}

			// WooTax order item meta and mapping array structure was changed drastically in 4.2; update accordingly
			$lookup_data = get_post_meta( $wt_order_id, '_wootax_lookup_data', true );
			$cart_taxes  = get_post_meta( $wt_order_id, '_wootax_cart_taxes', true );

			$new_mapping_array = array();
			$new_tc_ids        = array();
			$identifiers       = array();

			if ( is_array( $lookup_data ) ) {
				$wc_order = new WC_Order( $wc_order_id );

				$order_items = $wc_order->get_items();
				$order_fees  = $wc_order->get_fees();

				foreach ( $lookup_data as $location_key => $items ) {
					if ( !isset( $new_mapping_array[ $location_key ] ) ) {
						$new_mapping_array[ $location_key ] = array();
					}

					foreach ( $items as $index => $item ) {
						if ( !is_array( $item ) ) {
							continue;
						}

						$tax_amount = isset( $cart_taxes[ $location_key ][ $index ] ) ? $cart_taxes[ $location_key ][ $index ] : 0;
						$item_ident = $item['ItemID'];

						if ( $item_ident == 99999 ) {
							$shipping_item_id = -1;

							// Shipping
							if ( version_compare( WOOCOMMERCE_VERSION, '2.2', '<' ) ) {
								$shipping_item_id = WT_SHIPPING_ITEM;

								update_post_meta( $wc_order_id, '_wootax_first_found', $location_key );
								update_post_meta( $wc_order_id, '_wootax_shipping_index', $index );
							} else {
								$shipping_methods = $wc_order->get_items( 'shipping' );

								foreach ( $shipping_methods as $item_id => $method ) {
									if ( $shipping_item_id == -1 ) {
										$shipping_item_id = $item_id;

										wc_update_order_item_meta( $item_id, '_wootax_index', $index );
										wc_update_order_item_meta( $item_id, '_wootax_tax_amount', $tax_amount );
										wc_update_order_item_meta( $item_id, '_wootax_location_id', $location_key );
									}									
								}
							}	

							if ( $shipping_item_id != -1 ) {
								$new_mapping_array[ $location_key ][ $item_ident ] = $index;

								$identifiers[ WT_SHIPPING_ITEM ] = $item_ident;
							}
						} else if ( in_array( get_post_type( $item_ident ), array( 'product', 'product-variation' ) ) ) {
							// Cart item
							$cart_item_id = -1;

							if ( get_post_type( $item_ident ) == 'product' ) {
								$product_id   = $item_ident;
								$variation_id = '';
							} else if ( get_post_type( $item_ident ) == 'product-variation' ) {
								$variation_id = $item_ident;
								$product_id   = wp_get_post_parent_id( $variation_id );
							}

							foreach ( $order_items as $item_id => $item_data ) {
								if ( !empty( $item_data['variation_id'] ) && $item_data['variation_id'] == $variation_id || $item_data['product_id'] == $product_id ) {
									$cart_item_id = $item_id;
									break;
								}
							}

							if ( $cart_item_id != -1 ) {
								wc_update_order_item_meta( $cart_item_id, '_wootax_index', $index );
								wc_update_order_item_meta( $cart_item_id, '_wootax_tax_amount', $tax_amount );
								wc_update_order_item_meta( $cart_item_id, '_wootax_location_id', $location_key );

								$new_mapping_array[ $location_key ][ $item_ident ] = $index;

								$identifiers[ $item_ident ] = $item_ident;
							} 
						} else {
							// Fee
							$fee_id = -1;

							foreach ( $order_fees as $item_id => $item_data ) {
								if ( sanitize_title( $item_data['name'] ) == $item_ident ) {
									$fee_id = $item_id;
								}
							}

							if ( $fee_id != -1 ) {
								wc_update_order_item_meta( $fee_id, '_wootax_index', $index );
								wc_update_order_item_meta( $fee_id, '_wootax_tax_amount', $tax_amount );
								wc_update_order_item_meta( $fee_id, '_wootax_location_id', $location_key );

								$new_mapping_array[ $location_key ][ $item_ident ] = $index;

								$identifiers[ $item_ident ] = $item_ident;
							}
						}
					}

					$new_tc_ids[ $location_key ]['cart_id']  = $items['cart_id'];
					$new_tc_ids[ $location_key ]['order_id'] = $items['order_id'];
				}
			}

			// Update TaxCloud Ids
			update_post_meta( $wc_order_id, '_wootax_taxcloud_ids', $new_tc_ids );

			// Update mapping array
			update_post_meta( $wc_order_id, '_wootax_mapping_array', $new_mapping_array );

			// Update item identifiers
			update_post_meta( $wc_order_id, '_wootax_identifiers', $identifiers );
		}

		// Notify client that processing has succeeded and continue processing
		if ( $current_page < $total_pages ) {
			$last_post += $posts_per_page;
			$current_page++;
		} else {
			$last_post += count( $posts );
		}

		die( json_encode( array( 
			'status'       => 'working', 
			'last_post'    => $last_post, 
			'current_page' => $current_page, 
			'total_pages'  => $total_pages,
		) ) );
	}
}

WC_WooTax_Upgrade::init();