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
	 * @var string Plugin version.
	 * @since 4.2
	 */
	public $version = 5.0;

	/**
	 * @var WC_Integration WooCommerce integration for Simple Sales Tax.
	 * @since 5.0
	 */
	public $settings = null;

	/**
	 * @var WC_WooTax The single plugin instance.
	 * @since 4.2
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
		// Used for all request types
		require 'includes/class-sst-compatibility.php';
		require 'includes/class-sst-install.php';
		require 'includes/class-wc-wootax-taxcloud.php';
		require 'includes/wc-wootax-functions.php';
		require 'includes/class-wt-exemption-certificate.php';
		require 'includes/order/class-wt-orders.php';
		require 'includes/WT_Plugin_Updater.php';

		// Only used when Subscriptions is active
		// if ( WT_SUBS_ACTIVE ) {
		// 	require 'includes/wc-wootax-subscriptions.php';
		// }

		// Used on frontend
		if ( $this->is_request( 'frontend' ) ) {

			$show_exempt = $this->get_option( 'show_exempt' ) == 'true';

			if ( $show_exempt ) {
				// todo: determine where to include this!
				require 'includes/class-wt-certificate-manager.php';
			}

			// if ( WT_SUBS_ACTIVE ) {
			// 	require 'includes/order/class-wc-wootax-subscriptions.php';
			// 	require 'includes/frontend/wc-wootax-subscriptions-frontend.php';
			// }
			
			require 'includes/frontend/class-wc-wootax-checkout.php';
		}

		// Strictly admin panel
		if ( $this->is_request( 'admin' ) ) {
			require 'includes/admin/class-wc-wootax-admin.php';
		}
	}

	/**
	 * Hook into WordPress/WooCommerce.
	 *
	 * @since 4.4
	 */
	private function hooks() {
		register_activation_hook( __FILE__, array( 'SST_Install', 'activate' ) );
		add_action( 'init', array( $this, 'initialize_settings' ) );
		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_action( 'init', array( $this, 'check_updates' ) );
		add_filter( 'woocommerce_rate_label', array( $this, 'get_rate_label' ), 15, 2 );
		add_filter( 'woocommerce_rate_code', array( $this, 'get_rate_code' ), 12, 2 );
	}

	/**
	 * Load plugin textdomain.
	 *
	 * @since 5.0
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'simplesalestax', plugin_basename( __FILE__ ) . '/languages' );
	}

	/**
	 * Initialize settings.
	 *
	 * @since 5.0
	 */
	public function initialize_settings() {
		$this->settings = new WC_WooTax_Settings();
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
	 * Check for updates.
	 *
	 * @since 5.0
	 */
	public function check_updates() {
		// Instantiate updater to trigger update check
		new WT_Plugin_Updater( 'https://simplesalestax.com', $this->plugin_file(), array( 
			'version' => $this->version, // current version number
		) );
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
