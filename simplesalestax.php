<?php

/**
 * Plugin Name: Simple Sales Tax
 * Plugin URI: https://simplesalestax.com
 * Description: Harness the power of TaxCloud to accurately calculate sales tax for your WooCommerce store.
 * Version: 5.5
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

/**
 * Required functions.
 */
require_once 'includes/sst-compatibility-functions.php';

/**
 * Check that WooCommerce is active and at the minimum required version. If not,
 * display a notice and bail.
 */
if ( ! sst_woocommerce_active() || version_compare( get_option( 'woocommerce_db_version' ), '2.6', '<' ) ) {
	add_action( 'admin_notices', array( 'SST', 'plugin_inactive_notice' ) );
	return;
}

/**
 * Simple Sales Tax.
 *
 * @author 	Simple Sales Tax
 * @package SST
 * @since 	4.2
 */
final class SST {

	/**
	 * @var string Plugin version.
	 * @since 4.2
	 */
	public $version = '5.5';

	/**
	 * @var SST The single plugin instance.
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
		_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?', 'simplesalestax' ), '4.7' );
	}

	/**
	 * Unserializing instances of this class is forbidden.
	 *
	 * @since 4.7
	 */
	public function __wakeup() {
		_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?', 'simplesalestax' ), '4.7' );
	}

	/**
	 * Simple Sales Tax constructor.
	 *
	 * @since 4.7
	 */
	public function __construct() {
		// Define constants
		define( 'SST_DEFAULT_SHIPPING_TIC', 11010 );
		define( 'SST_SHIPPING_ITEM', 'SHIPPING' );
		define( 'SST_DEFAULT_FEE_TIC', 10010 );
		define( 'SST_RATE_ID', get_option( 'wootax_rate_id' ) );
		define( 'SST_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
		
		// Activation hook
		register_activation_hook( __FILE__, array( $this, 'configure_woocommerce' ) );

		// Hook woocommerce_loaded to bootstrap plugin
		add_action( 'woocommerce_loaded', array( $this, 'initialize' ) );
	}

	/**
	 * Initialize.
	 *
	 * @since 5.0
	 */
	public function initialize() {
		$this->includes();
		$this->load_textdomain();
		$this->check_updates();
	}

	/**
	 * Runs on plugin activation. Adjusts WooCommerce settings for optimal
	 * plugin performance.
	 *
	 * @since 5.0
	 */
	public function configure_woocommerce() {
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
	 * Include required plugin files.
	 *
	 * @since 4.4
	 */
	private function includes() {
		/**
		 * Autoloader.
		 */
		include_once 'includes/vendor/autoload.php';

		/**
		 * Abstract classes.
		 */
		include_once 'includes/abstracts/class-sst-abstract-cart.php';
		
		/**
		 * Core classes.
		 */
		include_once 'includes/sst-functions.php';
		include_once 'includes/class-sst-install.php';
		include_once 'includes/class-sst-settings.php';
		include_once 'includes/class-sst-logger.php';
		include_once 'includes/class-sst-plugin-updater.php';
		include_once 'includes/class-sst-customer.php';
		include_once 'includes/class-sst-ajax.php';
		include_once 'includes/class-sst-tic.php';
		include_once 'includes/class-sst-tics.php';
		include_once 'includes/class-sst-product.php';
		include_once 'includes/class-sst-shipping.php';
		include_once 'includes/class-sst-addresses.php';
		include_once 'includes/class-sst-origin-address.php';
		include_once 'includes/class-sst-certificates.php';
		include_once 'includes/class-sst-order.php';
		include_once 'includes/class-sst-order-controller.php';

		/**
		 * Subscriptions support.
		 */
		if ( sst_subs_active() ) {
			include_once 'includes/class-sst-subscriptions.php';
		}

		/**
		 * Admin only.
		 */
		if ( $this->is_request( 'admin' ) ) {
			include_once 'includes/admin/class-sst-admin.php';
		}

		/**
		 * Frontend only.
		 */
		if ( $this->is_request( 'frontend' ) ) {
			include_once 'includes/frontend/class-sst-checkout.php';
		}
	}

	/**
	 * Load plugin textdomain.
	 *
	 * @since 5.0
	 */
	private function load_textdomain() {
		load_plugin_textdomain( 'simplesalestax', false, basename( dirname( __FILE__ ) ) . '/languages' );
	}

	/**
	 * Check for updates.
	 *
	 * @since 5.0
	 */
	private function check_updates() {
		new SST_Plugin_Updater( 'https://simplesalestax.com', $this->plugin_file(), array( 
			'version' => $this->version, // current version number
		) );
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
	 * Display a notice when WooCommerce is inactive or out-of-date.
	 *
	 * @since 5.0
	 */
	public static function plugin_inactive_notice() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		
		$notice = '<strong>' . __( 'Simple Sales Tax is inactive.', 'simplesalestax' ) . '</strong> ' . __( 'WooCommerce 2.6 or greater is required for Simple Sales Tax to work.', 'simplesalestax' );

		printf( "<div class='error'><p>%s</p></div>", $notice );
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
 * Get the singleton SST instance.
 *
 * @since 4.2
 *
 * @return SST
 */
function SST() {
	return SST::instance();
}

SST();
