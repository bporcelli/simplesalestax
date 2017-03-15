<?php

/**
 * Plugin Name: Simple Sales Tax
 * Plugin URI: https://simplesalestax.com
 * Description: Harness the power of TaxCloud to accurately calculate sales tax for your WooCommerce store.
 * Version: 5.0
 * Author: Simple Sales Tax
 * Author URI: https://simplesalestax.com
 *
 * Copyright 2017 The WooTax Corporation (email: support@simplesalestax.com)
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
	exit; // Exit if accessed directly
}

// Seems weird to include this here... try to think of a better way in the future
require 'includes/wc-wootax-messages.php';

/**
 * WooTax.
 *
 * Main plugin class. Handles plugin activation/activation, dependency checking,
 * and a few other tasks.
 *
 * @author 	Simple Sales Tax
 * @package SST
 * @since 	4.2
 */
final class WC_WooTax {

	/**
	 * @var Plugin version
	 */
	public $version = 5.0;

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
	 * @var The single instance of the WC_WooTax class
	 */
	protected static $_instance = null;

	/**
	 * Return the single WooTax instance.
	 *
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
	 *
	 * @since 4.7
	 */
	public function __clone() {
		_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?', 'wootax' ), '4.7' );
	}

	/**
	 * Unserializing instances of this class is forbidden.
	 *
	 * @since 4.7
	 */
	public function __wakeup() {
		_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?', 'wootax' ), '4.7' );
	}

	/**
	 * __construct() method. Sets up activation, deactivation, and initialization
	 * hooks.
	 *
	 * @since 4.7
	 */
	public function __construct() {
		// Register activation/deactivation routines
		register_activation_hook( __FILE__, array( $this, 'activate' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );

		// Wait for WooCommerce to load, then run plugin init method
		add_action( 'woocommerce_loaded', array( $this, 'init' ) );
	}

	/**
	 * Plugin initialization method. Sets up WooCommerce hooks and loads plugin
	 * files.
	 *
	 * @since 5.0
	 */
	public function init() {
		$this->define_constants();
		$this->hooks();
		$this->includes();
	}

	/**
	 * Define WooTax constants.
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
		$this->define( 'WT_SUBS_ACTIVE', $this->is_plugin_active( 'woocommerce-subscriptions/woocommerce-subscriptions.php' ) );
		$this->define( 'WT_LOG_REQUESTS', $this->get_option( 'log_requests' ) == 'no' ? false : true );
		$this->define( 'WT_WOO_VERSION', $this->woocommerce_version() );
	}

	/**
	 * Define a constant if it hasn't been defined already.
	 *
	 * @since 4.4
	 */
	private function define( $name, $value ) {
		if ( ! defined( $name ) ){
			define( $name, $value );
		}
	}
	
	/**
	 * Hook into WordPress/WooCommerce.
	 *
	 * @since 4.4
	 */
	private function hooks() {
		// Display correct rate label for older versions of WooCommerce
		add_filter( 'woocommerce_rate_label', array( $this, 'get_rate_label' ), 15, 2 );

		// Return correct rate code for WooTax tax rate
		add_filter( 'woocommerce_rate_code', array( $this, 'get_rate_code' ), 12, 2 );
	}

	/**
	 * What type of request is this?
	 *
	 * @since 4.4
     *
	 * @param  string $type ajax, frontend or admin
	 * @return bool
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
	 * Include required plugin files.
	 *
	 * @since 4.4
	 */
	private function includes() {
		// Used for all request types
		require_once 'includes/class-wc-wootax-taxcloud.php';
		require_once 'includes/wc-wootax-functions.php';
		require_once 'includes/class-wt-exemption-certificate.php';
		require_once 'includes/order/class-wt-orders.php';

		// Only used when Subscriptions is active
		if ( WT_SUBS_ACTIVE ) {
			require_once 'includes/wc-wootax-subscriptions.php';
		}

		// Used on frontend
		if ( $this->is_request( 'frontend' ) ) {

			$show_exempt = $this->get_option( 'show_exempt' ) == 'true';

			if ( $show_exempt ) {
				// todo: determine where to include this!
				require_once 'includes/class-wt-certificate-manager.php';
			}

			if ( WT_SUBS_ACTIVE ) {
				require_once 'includes/order/class-wc-wootax-subscriptions.php';
				require_once 'includes/frontend/wc-wootax-subscriptions-frontend.php';
			}
			
			require_once 'includes/frontend/class-wc-wootax-checkout.php';
		}

		// Strictly admin panel
		if ( $this->is_request( 'admin' ) ) {
			require_once 'includes/admin/class-wc-wootax-admin.php';
		}
	}

	/**
	 * Return a list of strings describing missing dependencies.
	 *
	 * @since 5.0
	 *
	 * @return string[]
	 */
	private function get_missing_dependencies() {
		$missing = array();

		if ( ! class_exists( 'SoapClient' ) )
			$missing[] = 'PHP SOAP Extension';

		if ( ! $this->woocommerce_active() || version_compare( $this->woocommerce_version(), '2.2', '<' ) )
			$missing[] = 'WooCommerce 2.2+';

		return $missing;
	}

	/**
	 * Is WooCommerce active?
	 *
	 * @since 5.0
	 *
	 * @return bool
	 */
	private function woocommerce_active() {
		return $this->is_plugin_active( 'woocommerce/woocommerce.php' );
	}

	/**
	 * Is the plugin with the given slug active?
	 *
	 * @since 5.0
	 *
	 * @param  string $slug Plugin slug.
	 * @return bool
	 */
	private function is_plugin_active( $slug ) {
		$active_plugins = (array) get_option( 'active_plugins', array() );

		if ( is_multisite() )
			$active_plugins = array_merge( $active_plugins, get_site_option( 'active_sitewide_plugins', array() ) );

		return in_array( $slug, $active_plugins ) || array_key_exists( $slug, $active_plugins );
	}

	/**
	 * Return the version number for WooCommerce.
	 *
	 * @since 5.0
	 *
	 * @return string
	 */
	public function woocommerce_version() {
		// Favor the WC_VERSION constant in 2.1+
		if ( defined( 'WC_VERSION' ) ) {
			return WC_VERSION;
		} else {
			return WOOCOMMERCE_VERSION;
		}

		return '';
	}

	/**
	 * WooTax activation routine.
	 *
	 * @since 4.7
	 */
	public function activate() {
		// If any dependencies are missing, display a message and die.
		if ( ( $missing = $this->get_missing_dependencies() ) ) {
			deactivate_plugins( plugin_basename( __FILE__ ) );
			$missing_list = implode( ', ', $missing );
			wp_die( "Simple Sales Tax needs the following to run: $missing_list. Please ensure that all requirements are met and try again." );
		}

		// Run the activation routine and display a success message
		$this->configure_woocommerce();
		$this->add_wootax_rate();
		$this->add_exempt_user_role();
		$this->schedule_events();

		wootax_add_message( '<strong>Success!</strong> Simple Sales Tax was activated. Your WooCommerce tax settings have been adjusted for optimal plugin performance.', 'updated', 'activate-success', true, true );
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
	 *
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
	 * Have we added our own tax rate yet?
	 *
	 * @since 4.2
	 *
	 * @return bool
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
	 * Adds a user role for tax exempt customers. This role is an exact copy of
	 * the default "Customer" role.
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
	 * Schedule recurring events.
	 *
	 * @since 4.4
	 */
	private function schedule_events() {
		// Updates recurring tax amounts if necessary
		wp_schedule_event( time(), 'twicedaily', 'wootax_update_recurring_tax' );
	}

	/**
	 * Unschedule recurring events.
	 *
	 * @since 4.4
	 */
	private function unschedule_events() {
		wp_clear_scheduled_hook( 'wootax_update_recurring_tax' );
	}

	/**
	 * Ensures that the label for our tax rate is "Sales Tax."
	 *
	 * @since 1.0
	 *
	 * @param  string $name Name of the tax, won't be populated in our case.
	 * @param  int $key Tax key (default: null).
	 * @return string
	 */
	public function get_rate_label( $name, $key = NULL ) {
		if ( $name == WT_RATE_ID || $key == WT_RATE_ID ) {
			return apply_filters( 'wootax_rate_label', 'Sales Tax' );
		} else {
			return $name;
		}
	}

	/**
	 * Return correct rate code for our tax rate. Should be WOOTAX-RATE-DO-NOT-REMOVE.
	 *
	 * @param  string $code Rate code generated by Woo @see WC_Tax->get_rate_code().
	 * @param  int $key Tax rate ID.
	 * @return string
	 */
	public function get_rate_code( $code, $key ) {
		if ( $key == WT_RATE_ID ) {
			return apply_filters( 'wootax_rate_code', 'WOOTAX-RATE-DO-NOT-REMOVE' );
		} else {
			return $code;
		}
	}

	/**
	 * Are taxes enabled?
	 *
	 * @since 4.6
	 *
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
	 * Get the value of an option. Return the provided default value if
	 * the option is not set.
	 *
	 * @since 4.2
	 *
	 * @param  mixed $key the key of the option to be fetched
	 * @param  mixed $default default value for option (default: false)
	 * @return mixed
	 */
	public function get_option( $key, $default = false ) {
		if ( count( $this->settings ) == 0 || $this->settings_changed ) {
			$this->settings = get_option( self::$settings_key );
			$this->settings_changed = false;
		}

		if ( ! isset( $this->settings[ $key ] ) || ! $this->settings[ $key ] ) {
			return $default;
		} else {
			return $this->settings[ $key ];
		}
	}

	/**
	 * Set the value of an option.
	 *
	 * @since 4.2
	 * @param mixed $key Option key.
	 * @param mixed $value Option value.
	 */
	public function set_option( $key, $value ) {
		if ( count( $this->settings ) == 0 ) {
			$this->settings = get_option( self::$settings_key );
		}

		$this->settings[ $key ] = $value;

		update_option( self::$settings_key, $this->settings );
	}

	/**
	 * Mark settings as changed.
	 *
	 * @since 4.7
	 */
	public function settings_changed() {
		$this->settings_changed = true;
	}

	/**
	 * Get the plugin url.
	 *
	 * @since 4.7
	 *
	 * @return string
	 */
	public function plugin_url() {
		return untrailingslashit( plugins_url( '/', __FILE__ ) );
	}

	/**
	 * Get the plugin path.
	 *
	 * @since 4.7
	 *
	 * @return string
	 */
	public function plugin_path() {
		return untrailingslashit( plugin_dir_path( __FILE__ ) );
	}

	/**
	 * Get path to main plugin file
	 *
	 * @since 4.8
	 *
	 * @return string
	 */
	public function plugin_file() {
		return plugin_dir_path( __FILE__ ) . basename( __FILE__ );
	}

	/**
	 * Get the templates path
	 *
	 * @since 4.7
	 *
	 * @return string
	 */
	public function templates_path() {
		return $this->plugin_path() . '/templates';
	}
}

/**
 * Get the singleton WC_WooTax instance.
 *
 * @since 4.2
 *
 * @return WC_WooTax
 */
function SST() {
	return WC_WooTax::instance();
}

SST();
