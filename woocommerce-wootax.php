<?php

//error_reporting(E_ALL);

/*
 * Plugin Name: WooTax
 * Plugin URI: http://wootax.com
 * Description: Harness the power of TaxCloud to accurately calculate sales tax for your WooCommerce store.
 * Version: 4.2
 * Author: Brett Porcelli
 */

// Prevent data leaks from direct access
if ( ! defined( 'ABSPATH' ) ) exit; 

// Load plugin functions; stop execution if WooCommerce not active
if( !function_exists( 'is_plugin_active' ) ) {
	require ABSPATH . 'wp-admin/includes/plugin.php';
}

if ( !is_plugin_active( 'woocommerce/woocommerce.php' ) ) {
	return;
}

// WooTax constants
define( 'WOOTAX_PATH', plugin_dir_path( __FILE__ ) );
define( 'WOOTAX_DIR_URL', plugin_dir_url( __FILE__ ) );
define( 'WOOTAX_SHIPPING_TIC', 11010 );
define( 'WOOTAX_SHIPPING_ITEM', 'SHIPPING' );
define( 'WOOTAX_FEE_TIC', 10010 );
define( 'WOOTAX_VERSION', '4.2' );

require 'includes/wc-wootax-functions.php';
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

			// Called in WC_Cart::remove_taxes; allows us to control the display of the "zero tax" row
			add_filter( 'woocommerce_cart_remove_taxes_apply_zero_rate', array( $this, 'apply_zero_rate' ) );
			
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
		
		// Add custom post type for wootax orders
		add_action( 'init', array( $this, 'add_post_type' ) );

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

		// Loop through all coupons; make sure "apply_before_tax" is set to "yes"
		$coupons = new WP_Query( array(
			'post_type' => 'shop_coupon', 
			'post_status' => 'any', 
			'posts_per_page' => '-1'
		) );
		
		if ( $coupons->have_posts() ): while( $coupons->have_posts() ): $coupons->the_post();
			update_post_meta( $coupons->post->ID, 'apply_before_tax', 'yes' );
		endwhile;
		endif;
		
		// Add flash message so the user can review the changes to their tax settings
		wootax_add_flash_message( 'Your WooCommerce settings have been automatically adjusted to work with WooTax. You can review the alterations under WooCommerce -> Settings -> Tax. For help installing or configuring WooTax, please consult the <a href="http://wootax.com/installation-guide/" target="_blank">Installation Guide</a> and <a href="http://wootax.com/frequently-asked-questions/" target="_blank">FAQ</a>.', 'updated' );

	}

	/**
	 * Handles updates
	 * Only runs if the value of the wootax_version option does not match the current plugin version
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
			$shipping_field = get_option( 'wootax_shipping_taxable' );

			if ( $shipping_field ) {
				delete_option( 'wootax_shipping_taxable' );
			}

			// Add custom tax rate code for WooTax if one doesn't exist already
			$wootax_rate_id = get_option( 'wootax_rate_id' );

			if ( !$wootax_rate_id ) {
				$this->add_rate_code();
			} else {
				$name = $wpdb->get_var( "SELECT tax_rate_name FROM {$wpdb->prefix}woocommerce_tax_rates WHERE tax_rate_id = $wootax_rate_id" );

				if ( empty( $name ) ) {
					$this->add_rate_code();
				}	
			}

			// Transfer settings so they can be used with WooCommerce settings API
			$options = array(
				'tc_id',
				'tc_key',
				'usps_id',
				'show_exempt',
				'company_name',
				'show_zero_tax',
				'tax_based_on',
				'addresses',
			);
			
			foreach ( $options as $option ) {
				if ( get_option( 'wootax_' . $option ) ) {
					wootax_set_option( $option, get_option( 'wootax_' . $option ) );
					delete_option( 'wootax_' . $option );
				}
			}

			// Update current version to avoid running the upgrade routine multiple times
			update_option( 'wootax_version', WOOTAX_VERSION );

			// Add flash message
			wootax_add_flash_message( 'WooTax has been updated to version '. WOOTAX_VERSION .' successfully.' );

		}

	}

	/**
	 * Prevent WooCommerce from adding its own zero tax row
	 *
	 * @since 4.2
	 */
	public function apply_zero_rate() {
		return false;
	}

	/*
	 * Returns the tax label that should be displayed
	 * When WooTax is being applied, this is "Sales Tax." Else, the original value
	 * Implemented for WC 2.1+ support
	 *
	 * @since 3.0
	 * @param $original_label the current tax label 
	 * @return "Sales Tax" or the original value of $original_label
	
 	public function get_tax_label( $original_label ) {\
		$wootax_tax_id = get_option( 'wootax_rate_id' );

		if ( is_object( $this->cart ) && in_array( $wootax_tax_id, array_keys( $this->cart->taxes ) ) ) {
			return 'Sales Tax';
		}

		return $original_label;
	}*/
	
	/**
	 * Adds a custom post type for WooTax orders (wootax_order)
	 *
	 * @since 4.2
	 */
	public function add_post_type() {

		register_post_type( 'wootax_order', array(
			'public' => false, 
			'show_ui' => false, 
			'supports' => array( 'title', 'editor', 'custom-fields' ),
		) );

	}
	
	/**
	 * Get appropriate label for tax rate (should be Sales Tax for the tax rate applied by WooTax)
	 *
	 * @param $name the name of the tax (fetched from db; won't be populated in our instance)
	 * @param $key the tax key (we want to return the appropriate name for the rate with ID returned by getRateID())
	 */
	public function get_rate_label( $name, $key ) {

		if ( $key == get_option( 'wootax_rate_id' ) ) {
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
		require( 'includes/wc-wootax-exemptions.php' );
		require( 'includes/wc-wootax-subscriptions.php' );
		require( 'includes/wc-wootax-debug-tools.php' );
		require( 'classes/class-wc-wootax-order.php' );
		require( 'classes/class-wc-wootax-checkout.php' );
		require( 'classes/class-wc-wootax-admin.php' );
		require( 'classes/class-wc-wootax-refund.php' );

		$WC_WooTax = new WC_WooTax();	
	} else {
		wootax_add_flash_message( '<strong>Warning: WooTax has been disabled.</strong> This version of WooTax requires WooCommerce 2.1 or newer.' );
	}
	
}

add_action( 'plugins_loaded', 'load_wootax' );

// Configure WooCommerce settings on activation
function update_woocommerce_settings() {
	WC_WooTax::configure_woocommerce();
}

register_activation_hook( __FILE__, 'update_woocommerce_settings' );