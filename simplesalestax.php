<?php

/**
 * Plugin Name: Simple Sales Tax
 * Plugin URI: https://simplesalestax.com
 * Description: Harness the power of TaxCloud to accurately calculate sales tax for your WooCommerce store.
 * Version: 4.7
 * Author: Simple Sales Tax
 * Author URI: https://simplesalestax.com
 *
 * Copyright 2015 The WooTax Corporation (email: support@simplesalestax.com)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Do not all allow direct access
}

// We need access to is_plugin_active, even on the frontend. Make sure it is available.
if( !function_exists( 'is_plugin_active' ) ) {
	require ABSPATH . 'wp-admin/includes/plugin.php';
}

// Seems weird to include this here... try to think of a better way in the future
require 'includes/wc-wootax-messages.php';

if ( ! class_exists( 'WC_WooTax' ) ):

/**
 * The main WooTax class
 * Handles plugin activation/deactivation routines and a few miscellaneous tasks
 * 
 * @package Simple Sales Tax
 * @author  Brett Porcelli
 * @since 4.2
 */
final class WC_WooTax {
	/**
	 * @var Plugin version
	 */
	public $version = 4.7;

	/**
	 * @var Key of option where plugin settings are stored
	 */
	private static $settings_key = 'woocommerce_wootax_settings';

	/**
	 * @var Array containing plugin settings 
	 */
	private $settings = array();

	/**
	 * @var When this is true, the get_option method reloads the WooTax settings array
	 */
	private $settings_changed = false;

	/**
	 * @var WooTax The single instance of the WooTax class
	 */
	protected static $_instance = null;

	/**
	 * Return the single WooTax instance
	 * @since 1.0
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * Cloning is forbidden.
	 * @since 4.7
	 */
	public function __clone() {
		_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?', 'wootax' ), '4.7' );
	}

	/**
	 * Unserializing instances of this class is forbidden.
	 * @since 4.7
	 */
	public function __wakeup() {
		_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?', 'wootax' ), '4.7' );
	}

	/**
	 * Plugin constructor
	 * @since 4.7
	 */
	public function __construct() {
		$this->define_constants();
		$this->hooks();
		$this->includes();
	}

	/**
	 * Define WooTax constants
	 *
	 * @since 4.4
	 */
	private function define_constants() {
		$this->define( 'WT_DEFAULT_SHIPPING_TIC', 11010 );
		$this->define( 'WT_SHIPPING_ITEM', 'SHIPPING' );
		$this->define( 'WT_DEFAULT_FEE_TIC', 10010 );
		$this->define( 'WT_RATE_ID', get_option( 'wootax_rate_id' ) );
		$this->define( 'WT_CALC_TAXES', $this->should_calc_taxes() );
		$this->define( 'WT_DEFAULT_ADDRESS', $this->get_option( 'default_address' ) == false ? 0 : $this->get_option( 'default_address' ) );
		$this->define( 'WT_SUBS_ACTIVE', class_exists( 'WC_Subscriptions' ) );
		$this->define( 'WT_LOG_REQUESTS', $this->get_option( 'log_requests' ) == 'no' ? false : true );
	}

	/**
	 * Define a constant if it hasn't been defined already
	 *
	 * @since 4.4
	 */
	private function define( $name, $value ) {
		if ( ! defined( $name ) ){
			define( $name, $value );
		}
	}
	
	/**
	 * Hook into WordPress/WooCommerce
	 *
	 * @since 4.4
	 */
	private function hooks() {
		// Activation routine
		register_activation_hook( __FILE__, array( $this, 'activate' ) );

		// Deactivation routine
		register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );

		// Display correct rate label for older versions of WooCommerce
		add_filter( 'woocommerce_rate_label', array( $this, 'get_rate_label' ), 15, 2 );

		// Return correct rate code for WooTax tax rate
		add_filter( 'woocommerce_rate_code', array( $this, 'get_rate_code' ), 12, 2 );
	}

	/**
	 * What type of request is this?
	 * string $type ajax, frontend or admin
	 *
	 * @since 4.4
	 * @return bool
	 * @see WooCommerce->is_request()
	 */
	private function is_request( $type ) {
		switch ( $type ) {
			case 'admin' :
				return is_admin();
			case 'ajax' :
				return defined( 'DOING_AJAX' );
			case 'cron' :
				return defined( 'DOING_CRON' );
			case 'frontend' :
				return ( ! is_admin() || defined( 'DOING_AJAX' ) ) && ! defined( 'DOING_CRON' );
		}
	}

	/**
	 * Includes
	 * @since 4.4
	 */
	private function includes() {
		// Used for all request types
		require_once 'includes/wc-wootax-functions.php';
		require_once 'classes/class-wc-wootax-taxcloud.php';
		require_once 'includes/wc-wootax-exemptions.php';

		if ( WT_SUBS_ACTIVE ) {
			require_once 'includes/wc-wootax-subscriptions.php';
		}

		// Used on frontend
		if ( $this->is_request( 'frontend' ) && ! $this->is_request( 'ajax' ) ) {
			$this->frontend_includes();
		}

		// For cron requests
		if ( $this->is_request( 'cron' ) ) {
			require_once 'includes/wc-wootax-cron-tasks.php';			
		}

		// Strictly admin panel
		if ( $this->is_request( 'admin' ) ) {
			require_once 'classes/class-wc-wootax-admin.php';
		}
	}

	/**
	 * Frontend includes
	 * @since 4.7
	 */
	private function frontend_includes() {
		if ( $this->get_option( 'show_exempt' ) == 'true' ) {
			require_once 'includes/wc-wootax-exemptions-frontend.php';
		}

		if ( WT_SUBS_ACTIVE ) {
			require_once 'includes/wc-wootax-subscriptions-frontend.php';
		}
		
		require_once 'classes/class-wc-wootax-checkout.php';
	}

	/**
	 * Determine whether all WooTax dependencies are present in the environment.
	 * @since 4.7
	 */
	private function has_dependencies() {
		if ( ! class_exists( 'SoapClient' ) ) {
			return false;
		} else if ( ! is_plugin_active( 'woocommerce/woocommerce.php' ) || ! version_compare( WOOCOMMERCE_VERSION, '2.1', '>=' ) ) {
			return false;
		}

		return true;
	}

	/**
	 * WooTax activation routine.
	 *
	 * @since 4.7
	 */
	public function activate() {
		// If all required dependencies are present...
		if ( $this->has_dependencies() ) {
			// Install WooTax and display a message
			$this->configure_woocommerce();
			$this->add_wootax_rate();
			$this->add_exempt_user_role();
			$this->schedule_events();

			wootax_add_message( '<strong>Success!</strong> Simple Sales Tax was activated. Your WooCommerce tax settings have been adjusted for optimal plugin performance.', 'updated', 'activate-success', true, true );
		} else {
			wootax_add_message( '<strong>Simple Sales Tax could not be activated.</strong> Please ensure that PHP SOAP is supported by your server, and check that WooCommerce 2.1 or greater is installed.', 'error', 'activate-error', true, true );

			deactivate_plugins( plugin_basename( __FILE__ ) );
		}
	}

	/**
	 * WooTax deactivation routine.
	 *
	 * @since 4.7
	 */
	public function deactivate() {
		$this->unschedule_events();
	}

	/**
	 * Configure WooCommerce tax settings to work with WooTax.
	 *
	 * @since 4.2
	 */
 	private function configure_woocommerce() {
		update_option( 'woocommerce_calc_taxes', 'yes' );
		update_option( 'woocommerce_prices_include_tax', 'no' );
		update_option( 'woocommerce_tax_based_on', 'shipping' );
		update_option( 'woocommerce_default_customer_address', 'base' );
		update_option( 'woocommerce_shipping_tax_class', '' );
		update_option( 'woocommerce_tax_round_at_subtotal', false );
		update_option( 'woocommerce_tax_display_shop', 'excl' );
		update_option( 'woocommerce_tax_display_cart', 'excl' );
		update_option( 'woocommerce_tax_total_display', 'itemized' );
	}
	
	/**
	 * Add a tax rate for WooTax if one doesn't exist.
	 * @since 4.7
	 */
	private function add_wootax_rate() {
		if ( ! $this->has_tax_rate() ) {
			global $wpdb;

			// Add new rate 
			$_tax_rate = array(
				'tax_rate_id'       => 0,
				'tax_rate_country'  => 'WOOTAX',
				'tax_rate_state'    => 'RATE',
				'tax_rate'          => 0,
				'tax_rate_name'     => 'DO-NOT-REMOVE',
				'tax_rate_priority' => 0,
				'tax_rate_compound' => 1,
				'tax_rate_shipping' => 1,
				'tax_rate_order'    => 0,
				'tax_rate_class'    => 'standard',
			);

			$wpdb->insert( $wpdb->prefix . 'woocommerce_tax_rates', $_tax_rate );

			$tax_rate_id = $wpdb->insert_id;

			update_option( 'wootax_rate_id', $tax_rate_id );
		}
	}

	/**
	 * Determines if WooTax has added a tax rate
	 *
	 * @since 4.2
	 * @return bool true/false
	 */
	private function has_tax_rate() {
		global $wpdb;

		$wootax_rate_id = get_option( 'wootax_rate_id' ); // WT_RATE_ID is not defined yet when this method is executed

		if ( ! $wootax_rate_id ) {
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
	 * Adds a user role for tax exempt customers
	 * Role is an exact copy of the "Customer" role
	 *
	 * @since 4.3
	 */
	private function add_exempt_user_role() {
		add_role( 'exempt-customer', __( 'Exempt Customer', 'woocommerce' ), array(
			'read' 			=> true,
			'edit_posts' 	=> false,
			'delete_posts' 	=> false,
		) );
	}

	/**
	 * Schedule events for the WooTax order checker and recurring payments updater
	 *
	 * @since 4.4
	 */
	private function schedule_events() {
		// Updates recurring tax amounts if necessary
		wp_schedule_event( time(), 'twicedaily', 'wootax_update_recurring_tax' );
	}

	/**
	 * Unschedule events for the WooTax order checker and recurring payments updater
	 * Hooks to be cleared are wootax_update_recurring_tax
	 *
	 * @since 4.4
	 */
	private function unschedule_events() {
		wp_clear_scheduled_hook( 'wootax_update_recurring_tax' );
	}

	/**
	 * Get appropriate label for tax rate (should be Sales Tax for the rate applied by WooTax)
	 *
	 * @param $name - the name of the tax (fetched from db; won't be populated in our case)
	 * @param $key - the tax key (we want to return the appropriate name for the wootax rate)
	 */
	public function get_rate_label( $name, $key = NULL ) {
		if ( $name == WT_RATE_ID || $key == WT_RATE_ID ) {
			return apply_filters( 'wootax_rate_label', 'Sales Tax' );
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
		if ( $key == WT_RATE_ID ) {
			return apply_filters( 'wootax_rate_code', 'WOOTAX-RATE-DO-NOT-REMOVE' );
		} else {
			return $code;
		}
	}

	/**
	 * Return true if taxes are enabled
	 *
	 * @since 4.6
	 * @return bool
	 */
	private static function should_calc_taxes() {
		if ( function_exists( 'wc_taxes_enabled' ) ) {
			return wc_taxes_enabled();
		} else {
			return apply_filters( 'wc_tax_enabled', get_option( 'woocommerce_calc_taxes' ) == 'yes' );
		}
	}

	/**
	 * Get the value of a WooTax option
	 *
	 * @since 4.2
	 * @param (mixed) $key the key of the option to be fetched
	 * @return (mixed) requested option or boolean false if it isn't set
	 */
	public function get_option( $key ) {
		if ( count( $this->settings ) == 0 || $this->settings_changed ) {
			$this->settings = get_option( self::$settings_key );
			$this->settings_changed = false;
		}

		if ( !isset( $this->settings[ $key ] ) || !$this->settings[ $key ] ) {
			return false;
		} else {
			return $this->settings[ $key ];
		}
	}

	/**
	 * Set the value of a WooTax option
	 *
	 * @since 4.2
	 * @param (mixed) $key the key of the option to be updated
	 * @param (mixed) $value the new value of the option
	 */
	public function set_option( $key, $value ) {
		if ( count( $this->settings ) == 0 ) {
			$this->settings = get_option( self::$settings_key );
		}

		$this->settings[ $key ] = $value;

		update_option( self::$settings_key, $this->settings );
	}

	/**
	 * Mark settings as changed
	 * @since 4.7
	 */
	public function settings_changed() {
		$this->settings_changed = true;
	}

	/**
	 * Get the plugin url.
	 * @since 4.7
	 * @return string
	 */

	public function plugin_url() {
		return untrailingslashit( plugins_url( '/', __FILE__ ) );
	}

	/**
	 * Get the plugin path.
	 * @since 4.7
	 * @return string
	 */
	public function plugin_path() {
		return untrailingslashit( plugin_dir_path( __FILE__ ) );
	}

	/**
	 * Get the templates path
	 * @since 4.7
	 * @return string
	 */
	public function templates_path() {
		return $this->plugin_path() .'/templates';
	}
}

endif;

/**
 * Return the main Simple Sales Tax instance
 * @since 1.0
 */
function SST() {
	return WC_WooTax::instance();
}

add_action( 'plugins_loaded', 'SST', 20 );