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
	 * Simple Sales Tax constructor.
	 *
	 * @since 4.7
	 */
	public function __construct() {
		$this->includes();
		$this->define_constants();
		$this->hooks();
	}

	/**
	 * Define constants.
	 *
	 * @since 4.4
	 */
	private function define_constants() {
		$this->define( 'SST_DEFAULT_SHIPPING_TIC', 11010 );
		$this->define( 'SST_SHIPPING_ITEM', 'SHIPPING' );
		$this->define( 'SST_DEFAULT_FEE_TIC', 10010 );
		$this->define( 'SST_RATE_ID', get_option( 'wootax_rate_id' ) );
		$this->define( 'SST_LOG_REQUESTS', $this->get_option( 'log_requests' ) !== 'no' );
		$this->define( 'SST_WOO_VERSION', SST_Compatibility::woocommerce_version() );
		$this->define( 'SST_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
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
		// TODO: INCLUDE UPDATE FUNCTIONS
		// TODO: INCLUDE INSTALL/UPDATER/WOOCOMPAT CLASS
		// Used for all request types
		require_once 'includes/class-wc-wootax-taxcloud.php';
		require_once 'includes/wc-wootax-functions.php';
		require_once 'includes/class-wt-exemption-certificate.php';
		require_once 'includes/order/class-wt-orders.php';

		// Only used when Subscriptions is active
		// if ( WT_SUBS_ACTIVE ) {
		// 	require_once 'includes/wc-wootax-subscriptions.php';
		// }

		// Used on frontend
		if ( $this->is_request( 'frontend' ) ) {

			$show_exempt = $this->get_option( 'show_exempt' ) == 'true';

			if ( $show_exempt ) {
				// todo: determine where to include this!
				require_once 'includes/class-wt-certificate-manager.php';
			}

			// if ( WT_SUBS_ACTIVE ) {
			// 	require_once 'includes/order/class-wc-wootax-subscriptions.php';
			// 	require_once 'includes/frontend/wc-wootax-subscriptions-frontend.php';
			// }
			
			require_once 'includes/frontend/class-wc-wootax-checkout.php';
		}

		// Strictly admin panel
		if ( $this->is_request( 'admin' ) ) {
			require_once 'includes/admin/class-wc-wootax-admin.php';
		}
	}

	/**
	 * Hook into WordPress/WooCommerce.
	 *
	 * @since 4.4
	 */
	private function hooks() {
		// TODO: LOAD TEXTDOMAIN
		register_activation_hook( __FILE__, array( 'SST_Install', 'activate' ) );
		add_filter( 'woocommerce_rate_label', array( $this, 'get_rate_label' ), 15, 2 );
		add_filter( 'woocommerce_rate_code', array( $this, 'get_rate_code' ), 12, 2 );
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
		if ( $name == SST_RATE_ID || $key == SST_RATE_ID ) {
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
		if ( $key == SST_RATE_ID ) {
			return apply_filters( 'wootax_rate_code', 'WOOTAX-RATE-DO-NOT-REMOVE' );
		} else {
			return $code;
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
