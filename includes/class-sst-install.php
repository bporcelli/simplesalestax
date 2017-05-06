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
			'sst_update_45_remove_license_option',
		),
		'5.0' => array(
			'sst_update_50_origin_addresses',
			'sst_update_50_category_tics',
			'sst_update_50_order_data'
		),
	);

	/**
	 * @var SST_Updater Background updater.
	 * @since 5.0
	 */
	private static $background_updater;

	/**
	 * Initialize installer.
	 *
	 * @since 4.4
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'check_version' ), 5 );
		add_action( 'init', array( __CLASS__, 'init_background_updater' ), 5 );
		add_action( 'admin_init', array( __CLASS__, 'trigger_update' ) );
		add_action( 'admin_init', array( __CLASS__, 'trigger_rate_removal' ) );
		add_filter( 'plugin_action_links_' . SST_PLUGIN_BASENAME, array( __CLASS__, 'add_action_links' ) );
		add_filter( 'woocommerce_rate_code', array( __CLASS__, 'get_rate_code' ), 10, 2 );
		add_filter( 'woocommerce_rate_label', array( __CLASS__, 'get_rate_label' ), 10, 2 );
	}

	/**
	 * Initialize the background updater.
	 *
	 * @since 5.0
	 */
	public static function init_background_updater() {
		include_once 'class-sst-updater.php';
		self::$background_updater = new SST_Updater();
	}

	/**
	 * Compares the current version of the plugin against the version stored
	 * in the database and runs the installer if necessary.
	 *
	 * @since 4.4
	 */
	public static function check_version() {
		if ( ! defined( 'IFRAME_REQUEST' ) && get_option( 'wootax_version' ) !== SST()->version ) {
			self::install();
		}
	}

	/**
	 * Install Simple Sales Tax.
	 *
	 * @since 5.0
	 */
	public static function install() {
		// Include required classes
		if ( ! class_exists( 'WC_Admin_Notices' ) ) {
			require WC()->plugin_path() . '/admin/class-wc-admin-notices.php';
		}

		// Install
		self::add_roles();
		self::add_tax_rate();
		self::configure_woocommerce();
		self::create_tables();

		// Remove existing notices, if any
		WC_Admin_Notices::remove_notice( 'sst_update' );

		// Queue updates if needed (if db version not set, use default value of 1.0)
		$db_version = get_option( 'wootax_version', '1.0' );

		if ( version_compare( $db_version, max( array_keys( self::$update_hooks ) ), '<' ) ) {
			$update_url    = esc_url( add_query_arg( 'do_sst_update', true ) );
			$update_notice = sprintf( __( 'A Simple Sales Tax data update is required. <a href="%s">Click here</a> to start the update.', 'simplesalestax' ), $update_url );
			WC_Admin_Notices::add_custom_notice( 'sst_update', $update_notice );
		} else {
			update_option( 'wootax_version', SST()->version );
		}

		// Prompt user to remove rates if any are present
		if ( 'yes' !== get_option( 'wootax_keep_rates' ) && self::has_other_rates() ) {
			$keep_url   = esc_url( add_query_arg( 'sst_keep_rates', 'yes' ) );
			$delete_url = esc_url( add_query_arg( 'sst_keep_rates', 'no' ) );
			$notice     = sprintf( __( 'Simple Sales Tax found extra rates in your tax tables. Please choose to <a href="%s">keep the rates</a> or <a href="%s">delete them</a>.', 'simplesalestax' ), $keep_url, $delete_url );
			WC_Admin_Notices::add_custom_notice( 'sst_rates', $notice );
		}
	}

	/**
	 * Start update when a user clicks the "Update" button in the dashboard.
	 *
	 * @since 5.0
	 */
	public static function trigger_update() {
		if ( ! empty( $_GET[ 'do_sst_update'] ) ) {
			self::update();

			// Remove "update required" notice
			WC_Admin_Notices::remove_notice( 'sst_update' );
			
			// Add "update in progress" notice
			$notice = __( 'Simple Sales Tax is updating. This notice will disappear when the update is complete.', 'simplesalestax' );
			WC_Admin_Notices::add_custom_notice( 'sst_updating', $notice );
		}
	}

	/**
	 * Remove rates when user clicks 'keep the rates' or 'delete them.'
	 *
	 * @since 5.0
	 */
	public static function trigger_rate_removal() {
		global $wpdb;

		if ( ! empty( $_GET[ 'sst_keep_rates'] ) ) {
			if ( 'no' === $_GET[ 'sst_keep_rates' ] ) {
				$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}woocommerce_tax_rates WHERE tax_rate_id != %d", SST_RATE_ID ) );
			} else {
				update_option( 'wootax_keep_rates', 'yes' );
			}
			WC_Admin_Notices::remove_notice( 'sst_rates' );
		}
	}

	/**
	 * Queue all required updates to run in the background. Ripped from
	 * WooCommerce core.
	 *
	 * @since 5.0
	 */
	private static function update() {
		$current_db_version = get_option( 'wootax_version', '1.0' );
		$logger             = new WC_Logger();
		$update_queued      = false;

		foreach ( self::$update_hooks as $version => $update_callbacks ) {
			if ( version_compare( $current_db_version, $version, '<' ) ) {
				foreach ( $update_callbacks as $update_callback ) {
					$logger->add( 'sst_db_updates', sprintf( 'Queuing %s - %s', $version, $update_callback ) );
					self::$background_updater->push_to_queue( $update_callback );
					$update_queued = true;
				}
			}
		}

		if ( $update_queued ) {
			self::$background_updater->save()->dispatch();
		}
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
	 * Add plugin action links.
	 *
	 * @since 4.2
	 *
	 * @param  array $links Existing action links for plugin.
	 * @return array
	 */
	public static function add_action_links( $links ) {
		$link_text     = __( 'Settings', 'simplesalestax' );
	 	$settings_link = '<a href="admin.php?page=wc-settings&tab=integration&section=wootax">'. $link_text .'</a>'; 
	  	array_unshift( $links, $settings_link );
	  	return $links; 
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
		global $wpdb;

		$tax_rates_table = $wpdb->prefix . 'woocommerce_tax_rates';

		// Get existing rate, if any
		$rate_id  = get_option( 'wootax_rate_id', 0 );
		$existing = $wpdb->get_row( $wpdb->prepare( "
			SELECT * FROM $tax_rates_table WHERE tax_rate_id = %d;
		", $rate_id ) );

		// Add or update tax rate
		$_tax_rate = array(
			'tax_rate_country'  => 'WT',
			'tax_rate_state'    => 'RATE',
			'tax_rate'          => 0,
			'tax_rate_name'     => 'DO-NOT-REMOVE',
			'tax_rate_priority' => 0,
			'tax_rate_compound' => 1,
			'tax_rate_shipping' => 1,
			'tax_rate_order'    => 0,
			'tax_rate_class'    => 'standard',
		);

		if ( is_null( $existing ) ) {
			$wpdb->insert( $tax_rates_table, $_tax_rate );
			update_option( 'wootax_rate_id', $wpdb->insert_id );
		} else {
			$where = array( 'tax_rate_id' => $rate_id );
			$wpdb->update( $tax_rates_table, $_tax_rate, $where );
		}
	}

	/**
	 * Create database tables.
	 *
	 * @since 5.0
	 *
	 * @return bool
	 */
	private static function create_tables() {
		global $wpdb;

		$table_name      = $wpdb->prefix . 'sst_tics';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
			id int(5),
			ssuta int(1) NOT NULL DEFAULT '1',
			parent int(5) NULL,
			title varchar(255) NOT NULL,
			label varchar(255) NOT NULL,
			PRIMARY KEY  (id)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Are any extra tax rates present in the tax tables?
	 *
	 * @since 4.5
	 *
	 * @return bool
	 */
	private static function has_other_rates() {
		global $wpdb;
		$rate_count = $wpdb->get_var( $wpdb->prepare( 
			"SELECT COUNT(*) FROM {$wpdb->prefix}woocommerce_tax_rates WHERE tax_rate_id != %d",
			SST_RATE_ID
		) );
		return $rate_count > 0;
	}

	/**
	 * Return correct rate code for our tax rate (SALES-TAX).
	 *
	 * @since 5.0
	 *
	 * @param  string $code Rate code.
	 * @param  int $key Tax rate ID.
	 * @return string
	 */
	public static function get_rate_code( $code, $key ) {
		if ( $key == SST_RATE_ID ) {
			return apply_filters( 'wootax_rate_code', 'SALES-TAX' );
		} else {
			return $code;
		}
	}

	/**
	 * Return correct label for our tax rate ("Sales Tax").
	 *
	 * @since 5.0
	 *
	 * @param  string $label Original label.
	 * @param  int $key Tax rate id.
	 * @return string
	 */
	public static function get_rate_label( $label, $key ) {
		if ( $key == SST_RATE_ID ) {
			return apply_filters( 'wootax_rate_label', __( 'Sales Tax', 'simplesalestax' ) );
		} else {
			return $name;
		}
	}

}

SST_Install::init();