<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * SST Install.
 *
 * Handles plugin installation and upgrades.
 *
 * @author 	Simple Sales Tax
 * @package SST
 * @since 	5.0
 */
class SST_Install {

	/**
	 * @var array Callbacks that need to run for each update.
	 * @since 5.0
	 */
	private static $update_hooks = array(
		'2.6' => array(
			'sst_update_26_remove_shipping_taxable_option',
		),
		'3.8' => array(
			'sst_update_38_update_addresses',
		),
		'4.2' => array(
			'sst_update_42_migrate_settings',
			'sst_update_42_migrate_order_data',
		),
		'4.5' => array(
			'sst_update_45_remove_license_option'
		),
	);

	// TODO: MAKE A PLACE FOR THE UPDATER TO LIVE; INIT UPDATER (SEE WOO CODE)
	// MAYBE WE CAN USE THE WOO UPDATER?

	/**
	 * Initialize installer.
	 *
	 * @since 4.4
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'check_version' ) );
		add_filter( 'plugin_action_links_' . SST_PLUGIN_BASENAME, array( __CLASS__, 'add_action_links' ) );
	}

	/**
	 * Compares the current version of the plugin against the version stored
	 * in the database and runs the installer if necessary.
	 *
	 * @since 4.4
	 */
	public static function check_version() {
		if ( get_option( 'wootax_version' ) !== SST()->version ) {
			self::install();
		}
	}

	/**
	 * Install Simple Sales Tax.
	 *
	 * @since 5.0
	 */
	public static function install() {
		// If any dependencies are missing, display a message and die.
		if ( ( $missing = $this->get_missing_dependencies() ) ) {
			deactivate_plugins( SST_PLUGIN_BASENAME );
			$missing_list = implode( ', ', $missing );
			$message = sprintf( _( 'Simple Sales Tax needs the following to run: %s. Please ensure that all requirements are met and try again.', 'simplesalestax' ), $missing_list );
			wp_die( $message );
		}

		// Include required classes
		if ( ! class_exists( 'WC_Admin_Notices' ) ) {
			require WC()->plugin_path() . '/admin/class-wc-admin-notices.php';
		}

		// Install
		self::add_roles();
		self::add_tax_rate();
		self::configure_woocommerce();
		self::schedule_events();

		// Queue updates if needed (if db version not set, use default value of 1.0)
		$db_version = get_option( 'wootax_version', '1.0' );

		if ( version_compare( $db_version, max( array_keys( self::$update_hooks ) ), '<' ) ) {
			// TODO: queue updates, notify admin
			// TODO: DISPLAY SUCCESS NOTICE AFTER UPDATES RUN
		}
		
		// Update plugin version
		update_option( 'wootax_version', SST()->version );
	}

	/**
	 * Add custom user roles.
	 *
	 * @since 5.0
	 */
	public static function add_roles() {
		add_role( 'exempt-customer', __( 'Exempt Customer', 'simplesalestax' ), array(
			'read' 			=> true,
			'edit_posts' 	=> false,
			'delete_posts' 	=> false,
		) );
	}

	/**
	 * Remove custom user roles.
	 *
	 * @since 5.0
	 */
	public static function remove_roles() {
		remove_role( 'exempt-customer' );
	}

	/**
	 * Add plugin action links.
	 *
	 * @since 4.2
	 *
	 * @param  array $links Existing action links for plugin.
	 * @return array
	 */
	public static function add_settings_link( $links ) { 
	 	$settings_link = '<a href="admin.php?page=wc-settings&tab=integration&section=wootax">Settings</a>'; 
	  	array_unshift( $links, $settings_link ); 
	  	return $links; 
	}

	/**
	 * Schedule cronjobs (clear them first).
	 *
	 * @since 4.4
	 */
	private static function schedule_events() {
		wp_clear_scheduled_hook( 'wootax_update_recurring_tax' );

		// Ripped from WooCommerce: allows us to schedule an event starting at 00:00 tomorrow local time
		$ve = get_option( 'gmt_offset' ) > 0 ? '+' : '-';

		wp_schedule_event( strtotime( '00:00 tomorrow ' . $ve . get_option( 'gmt_offset' ) . ' HOURS' ), 'twicedaily', 'wootax_update_recurring_tax' );
	}

	/**
	 * Set WooCommerce options for ideal plugin performance.
	 *
	 * @since 4.2
	 */
 	private static function configure_woocommerce() {
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
	 * Add a tax rate so we can persist calculate tax totals after checkout.
	 *
	 * @since 5.0
	 */
	private static function add_tax_rate() {
		// TODO: REFACTOR
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
	private static function has_tax_rate() {
		// TODO: STILL NEEDED?
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
	 * Return a list of strings describing missing dependencies.
	 *
	 * @since 5.0
	 *
	 * @return string[]
	 */
	// TODO: WORK ON THE METHODS BELOW!
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

}