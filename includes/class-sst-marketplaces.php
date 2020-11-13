<?php
/**
 * SST Marketplaces.
 *
 * Handles integration with marketplace plugins like Dokan and WCFM.
 *
 * @package simple-sales-tax
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SST_Marketplaces.
 *
 * @package simple-sales-tax
 */
class SST_Marketplaces {

	/**
	 * Whether a marketplace integration was loaded.
	 *
	 * @var bool
	 */
	protected $integration_loaded = false;

	/**
     * Flag to indicate whether SST should split the order by seller ID
     * before calculating taxes. Should be true whenever the marketplace
     * plugin would normally split the WooCommerce cart packages.
     *
     * @var bool
     */
    protected $should_split_packages = false;

	/**
	 * Singleton instance.
	 *
	 * @var SST_Marketplaces
	 */
	protected static $_instance = null;

	/**
	 * Singleton instance accessor.
	 *
	 * @return SST_Marketplaces
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	/**
	 * SST_Marketplaces constructor.
	 */
	protected function __construct() {
		$this->load_integration();

		// todo: warn if giving tax to vendors?
		if ( $this->integration_loaded ) {
		}
	}

	/**
	 * Loads the appropriate marketplace integration based on the active
	 * plugins.
	 */
	protected function load_integration() {
		$integrations_dir = __DIR__ . '/integrations';

		// WC Vendors Pro.
		if ( defined( 'WCV_PRO_VERSION' ) ) {
			require_once $integrations_dir . '/class-sst-wc-vendors.php';
			$this->integration_loaded = true;
		}

		// Dokan.
		if ( defined( 'DOKAN_PLUGIN_VERSION' ) ) {
			require_once $integrations_dir . '/class-sst-dokan.php';
			$this->integration_loaded = true;
		}

		// WooCommerce Frontend Manager.
		if ( defined( 'WCFM_VERSION' ) ) {
			require_once $integrations_dir . '/class-sst-wcfm.php';
			$this->integration_loaded = true;
		}
	}

}

SST_Marketplaces::instance();
