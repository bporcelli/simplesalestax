<?php

/**
 * Plugin Name: WooTax
 * Plugin URI: http://wootax.com
 * Description: Harness the power of TaxCloud to accurately calculate sales tax for your WooCommerce store.
 * Version: 4.4
 * Author: Brett Porcelli
 */

// Prevent data leaks from direct access
if ( ! defined( 'ABSPATH' ) ) 
	exit; 

// Load plugin functions; stop execution if WooCommerce not active
if( !function_exists( 'is_plugin_active' ) ) 
	require ABSPATH . 'wp-admin/includes/plugin.php';

if ( !is_plugin_active( 'woocommerce/woocommerce.php' ) ) 
	return;

// WooTax constants
define( 'WOOTAX_PATH', plugin_dir_path( __FILE__ ) );
define( 'WOOTAX_DIR_URL', plugin_dir_url( __FILE__ ) );
define( 'WOOTAX_SHIPPING_TIC', 11010 );
define( 'WOOTAX_SHIPPING_ITEM', 'SHIPPING' );
define( 'WOOTAX_FEE_TIC', 10010 );
define( 'WOOTAX_VERSION', '4.4' );

require 'includes/wc-wootax-functions.php';

// Include TaxCloud functions if the PHP SOAP module is activated; otherwise, halt plugin execution
if ( class_exists( 'SoapClient' ) ) {
	require 'includes/wc-wootax-taxcloud-functions.php';
} else {
	wootax_add_flash_message( '<strong>Warning! WooTax has been disabled.</strong> The SoapClient class is required by WooTax, but it is not activated on your server. Please see <a href="#" target="_blank">this article</a> for advice on what to do next.' );
	return;
}

if ( !class_exists( 'EDD_SL_Plugin_Updater' ) ) 
	require 'classes/EDD_SL_Plugin_Updater.php';

/**
 * The main WooTax class
 * 
 * @since 4.2
 */
class WC_WooTax {
	// Set up class properties
	private $woo;
	
	/**
	 * Class constructor; Hooks into WordPress and WooCommerce
	 *
	 * @since 4.2
	 */
	public function __construct() {	
		global $woocommerce;

		// Give class methods access to WooCommerce global
		$this->woo = &$woocommerce;

		// Hook into WooCommerce and WordPress
		$this->hook_wordpress();
		$this->hook_woocommerce();
	}
	
	/**
	 * Hooks into WooCommerce
	 *
	 * @since 4.2
	 */
	private function hook_woocommerce() {
		if ( get_option( 'wootax_license_key' ) != false && wootax_get_option( 'tc_key' ) != false && wootax_get_option( 'tc_id' ) != false ) {
			
			// Display correct rate label for older versions of WooCommerce
			add_filter( 'woocommerce_rate_label', array( $this, 'get_rate_label' ), 15, 2 );

			// Return correct rate code for WooTax tax rate
			add_filter( 'woocommerce_rate_code', array( $this, 'get_rate_code' ), 12, 2 );

		} 
	}
	
	/**
	 * Hooks into WordPress actions/filters
	 *
	 * @since 4.2
	 */
	private function hook_wordpress() {
		// Run update routine if necessary
		add_action( 'admin_init', array( $this, 'update_wootax' ) );

		// Maybe show activation message
		add_action( 'admin_init', array( $this, 'maybe_show_activation_success' ) );
	}
	
	/**
	 * Configures WooCommerce tax settings to work with WooTax
	 * Executed upon plugin activation
	 *
	 * @since 4.2
	 */
 	public static function configure_woocommerce() {
		// Enable tax calculations
		update_option( 'woocommerce_calc_taxes', 'yes' );
		
		// Set exclusive 
		update_option( 'woocommerce_prices_include_tax', 'no' );
			
		// Set "Tax based on" option to "Customer shipping address"
		update_option( 'woocommerce_tax_based_on', 'shipping' );

		// Set default customer address
		update_option( 'woocommerce_default_customer_address', 'base' );

		// Set shipping tax class to "Based on cart items"
		update_option( 'woocommerce_shipping_tax_class', '' );

		// Set "Round at subtotal level" to false
		update_option( 'woocommerce_tax_round_at_subtotal', 0 );

		// Make sure prices are displayed excluding tax 
		update_option( 'woocommerce_tax_display_shop', 'excl' );
		update_option( 'woocommerce_tax_display_cart', 'excl' );
		
		// Display taxes in "itemized" form 
		update_option( 'woocommerce_tax_total_display', 'itemized' );
		
		// Set cookie so we know to show activation success message on the next page load
		// This needs to be done because WordPress redirects immediately after activation (thereby causing the wootax flash message to be erased)
		setcookie( 'wootax_activated', true, time() + 3600, '/' );
	}

	/**
	 * Maybe show activation success message 
	 *
	 * @since 4.3
	 */
	public function maybe_show_activation_success() {
		if ( isset( $_COOKIE['wootax_activated'] ) ) {
			wootax_add_flash_message( '<strong>Success!</strong> Your WooCommerce tax settings have been automatically adjusted to work with WooTax.', 'updated' );
			setcookie( 'wootax_activated', '', time() - 3600, '/' );
		}
	}

	/**
	 * Handles updates
	 * Only runs if the value of the wootax_version option does not match the current plugin version OR no WooTax tax rate is detected
	 *
	 * @since 4.2
	 */
	public function update_wootax() {
		global $wpdb;

		$version = get_option( 'wootax_version' );

		if ( !$version || version_compare( $version, WOOTAX_VERSION, '<' ) ) {

			// Upgrade old addresses to use new multi-address system
			$old_address_field = get_option( 'wootax_address1' );

			if ( $old_address_field ) {

				// Set new address array 
				wootax_set_option( 'wootax_addresses', fetch_business_addresses() );

				// Delete old options
				delete_option( 'wootax_address1' );
				delete_option( 'wootax_address2' );
				delete_option( 'wootax_state' );
				delete_option( 'wootax_city' );
				delete_option( 'wootax_zip5' );
				delete_option( 'wootax_zip4' );

			}

			// Delete deprecated "wootax_shipping_taxable" option if it still exists
			if ( get_option( 'wootax_shipping_taxable' ) )
				delete_option( 'wootax_shipping_taxable' );

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
					wootax_set_option( $option, get_option( 'wootax_' . $option ) );
					delete_option( 'wootax_' . $option );
				}
			}

			// Loop through all wootax_order posts; transfer metadata to associated shop_order
			// This completes the upgrade from WooTax 4.1 to WooTax 4.2
			$wootax_orders = new WP_Query( array(
				'post_type'      => 'wootax_order',
				'post_status'    => 'any',
				'posts_per_page' => -1,
			) );

			if ( $wootax_orders->have_posts() ) {

				while ( $wootax_orders->have_posts() ) { 

					$wootax_orders->the_post();

					// Get info about original order
					$wt_order_id = $wootax_orders->post->ID;
					$wc_order_id = get_post_meta( $wt_order_id, '_wootax_wc_order_id', true );

					if ( !$wc_order_id ) 
						continue;

					$wc_order = new WC_Order( $wc_order_id );

					// Transfer meta that doesn't need to be changed
					$direct_meta_keys = array( 'tax_total', 'shipping_tax_total', 'captured', 'refunded', 'customer_id', 'tax_item_id', 'exemption_applied' );

					foreach ( $direct_meta_keys as $key ) {
						update_post_meta( $wc_order_id, '_wootax_' . $key, get_post_meta( $wt_order_id, '_wootax_' . $key, true ) );
					}

					// Update _wootax_index, _wootax_location_id, _wootax_tax_amount meta for order items, fees, and shipping methods (in 2.2.x)
					// Also, update mapping array and taxcloud_ids array
					$lookup_data = get_post_meta( $wt_order_id, '_wootax_lookup_data', true );
					$cart_taxes  = get_post_meta( $wt_order_id, '_wootax_cart_taxes', true );

					$new_mapping_array = array();
					$new_tc_ids        = array();
					$identifiers       = array();

					if ( is_array( $lookup_data ) ) {

						$order_items = $wc_order->get_items();
						$order_fees  = $wc_order->get_fees();

						foreach ( $lookup_data as $location_key => $items ) {

							if ( !isset( $new_mapping_array[ $location_key ] ) ) {
								$new_mapping_array[ $location_key ] = array();
							}

							foreach ( $items as $index => $item ) {

								if ( !is_array( $item ) ) 
									continue;

								$tax_amount = isset( $cart_taxes[ $location_key ][ $index ] ) ? $cart_taxes[ $location_key ][ $index ] : 0;
								$item_ident = $item['ItemID'];

								if ( $item_ident == 99999 ) {

									$shipping_item_id = -1;

									// Shipping
									if ( version_compare( WOOCOMMERCE_VERSION, '2.2', '<' ) ) {

										$shipping_item_id = WOOTAX_SHIPPING_ITEM;

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
										$identifiers[ WOOTAX_SHIPPING_ITEM ] = $item_ident;
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

						}

					}

					// Update TaxCloud Ids
					$new_tc_ids[ $location_key ]['cart_id']  = $items['cart_id'];
					$new_tc_ids[ $location_key ]['order_id'] = $items['order_id'];

					update_post_meta( $wc_order_id, '_wootax_taxcloud_ids', $new_tc_ids );

					// Update mapping array
					update_post_meta( $wc_order_id, '_wootax_mapping_array', $new_mapping_array );

					// Update item identifiers
					update_post_meta( $wc_order_id, '_wootax_identifiers', $identifiers );

				}

				// Delete all wootax_order posts and any associated meta
				$wpdb->query( "DELETE p, pm FROM {$wpdb->posts} p LEFT JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID WHERE p.post_type = 'wootax_order'" );
			
			}

			// Update current version to avoid running the upgrade routine multiple times
			update_option( 'wootax_version', WOOTAX_VERSION );

		}

		if ( !$this->has_tax_rate() ) 
			$this->add_rate_code();
	}
	
	/**
	 * Determines if WooTax has added a tax rate
	 *
	 * @since 4.2
	 * @return bool true/false
	 */
	private function has_tax_rate() {
		global $wpdb;

		$wootax_rate_id = get_option( 'wootax_rate_id' );

		if ( !$wootax_rate_id ) {
			return false;
		} else {
			$name = $wpdb->get_var( "SELECT tax_rate_name FROM {$wpdb->prefix}woocommerce_tax_rates WHERE tax_rate_id = $wootax_rate_id" );

			if ( empty( $name ) ) {
				return false;
			}	
		}

		return true;
	}

	/**
	 * Get appropriate label for tax rate (should be Sales Tax for the tax rate applied by WooTax)
	 *
	 * @param $name the name of the tax (fetched from db; won't be populated in our instance)
	 * @param $key the tax key (we want to return the appropriate name for the wootax rate)
	 */
	public function get_rate_label( $name, $key = NULL ) {
		$rate_id = get_option( 'wootax_rate_id' );

		if ( $name == $rate_id || $key == $rate_id ) {
			return 'Sales Tax';
		} else {
			return $name;
		}
	}

	/**
	 * Return correct rate code for WooTax tax rate
	 *
	 * @param $code -the code WooCommerce generates @see WC_Tax->get_rate_code()
	 * @param $key - the tax rate id; compare to stored wootax rate id and return 'WOOTAX-RATE-DO-NOT-REMOVE' if match is found
	 */
	public function get_rate_code( $code, $key ) {
		if ( $key == get_option( 'wootax_rate_id' ) ) {
			return 'WOOTAX-RATE-DO-NOT-REMOVE';
		} else {
			return $code;
		}
	}

	/**
	 * Adds a tax rate code for WooTax
	 *
	 * @since 4.0
	 */
	private function add_rate_code() {
		global $wpdb;

		// Add new rate 
		$_tax_rate = array(
			'tax_rate_country'  => 'WOOTAX',
			'tax_rate_state'    => 'RATE',
			'tax_rate'          => 0,
			'tax_rate_name'     => 'DO-NOT-REMOVE',
			'tax_rate_priority' => NULL,
			'tax_rate_compound' => true,
			'tax_rate_shipping' => NULL,
			'tax_rate_order'    => NULL,
			'tax_rate_class'    => 'standard',
		);

		$wpdb->insert( $wpdb->prefix . 'woocommerce_tax_rates', $_tax_rate );

		$tax_rate_id = $wpdb->insert_id;

		update_option( 'wootax_rate_id', $tax_rate_id );
	}

	/**
	 * Adds a user role for tax exempt customers
	 * Role is an exact copy of the "Customer" role
	 *
	 * @since 4.3
	 */
	public static function add_exempt_user_role() {
		add_role( 'exempt-customer', __( 'Exempt Customer', 'woocommerce' ), array(
			'read' 						=> true,
			'edit_posts' 				=> false,
			'delete_posts' 				=> false,
		) );
	}

	/**
	 * Schedule events for the WooTax order checker and recurring payments updater
	 *
	 * @since 4.4
	 */
	public static function schedule_wootax_events() {
		// Ensure that all orders are properly synced with TaxCloud
		wp_schedule_event( time(), 'daily', 'wootax_check_orders' ); 

		// Update recurring tax amounts if necessary
		wp_schedule_event( time(), 'twicedaily', 'wootax_update_recurring_tax' );
	}

	/**
	 * Unschedule events for the WooTax order checker and recurring payments updater
	 * Hooks to be cleared are wootax_check_orders and wootax_update_recurring_tax
	 *
	 * @since 4.4
	 */
	public static function unschedule_wootax_events() {
		wp_clear_scheduled_hook( 'wootax_check_orders' );
		wp_clear_scheduled_hook( 'wootax_update_recurring_tax' );
	}
	
}

// Check for plugin updates

$license_key = trim( get_option( 'wootax_license_key' ) );
		
if ($license_key != false) {
	$edd_updater = new EDD_SL_Plugin_Updater( 'http://wootax.com', __FILE__, array( 
		'version' 	=> WOOTAX_VERSION, 		 // current version number
		'license' 	=> $license_key, 		 // license key (used get_option above to retrieve from DB)
		'item_name' => 'WooTax Plugin for WordPress', // name of this plugin
		'author' 	=> 'Brett Porcelli',  	 // author of this plugin
		'url' 		=> home_url(), 			 // url where plugin is being activated
	) );
}

// Load plugin
function load_wootax() {
	if ( version_compare( WOOCOMMERCE_VERSION, '2.1', '>=' ) ) {
		if ( wootax_get_option( 'show_exempt' ) == 'true' ) 
			require 'includes/wc-wootax-exemptions.php';
		
		require 'classes/class-wc-wootax-order.php';
		require 'classes/class-wc-wootax-checkout.php';
		require 'classes/class-wc-wootax-admin.php';
		require 'classes/class-wc-wootax-refund.php';
		require 'includes/wc-wootax-cron-tasks.php';

		if ( is_plugin_active( 'woocommerce-subscriptions/woocommerce-subscriptions.php' ) ) 
			require( 'classes/class-wc-wootax-subscriptions.php' );

		$WC_WooTax = new WC_WooTax();		
	} else {
		wootax_add_flash_message( '<strong>Warning: WooTax has been disabled.</strong> This version of WooTax requires WooCommerce 2.1 or newer.' );
	}
}

add_action( 'plugins_loaded', 'load_wootax' );

// WooTax activation routine
function activate_wootax() {
	WC_WooTax::configure_woocommerce();
	WC_WooTax::add_exempt_user_role();
	WC_WooTax::schedule_wootax_events();
}

register_activation_hook( __FILE__, 'activate_wootax' );

// WooTax deactivation routine
function deactivate_wootax() {
	WC_WooTax::unschedule_wootax_events();
}

register_deactivation_hook( __FILE__, 'deactivate_wootax' );