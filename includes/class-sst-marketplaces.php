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
			add_filter( 'woocommerce_cart_shipping_packages', array( $this, 'check_for_split_packages' ), PHP_INT_MAX );
			add_filter( 'wootax_origin_address', array( $this, 'maybe_filter_origin_address' ), 10, 2 );
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

	/**
     * Inspects the cart shipping packages to see whether they've been split
     * by seller ID.
     *
     * @param array $packages WooCommerce cart shipping packages.
     *
     * @return array
     */
    public function check_for_split_packages( $packages ) {
        // If the keys are nonsequential (WCFM) or the 'vendor_id' or 'seller_id'
        // keys appear in a package, we assume the packages were split by seller.
        $keys_non_sequential = 0 !== (int) current( array_keys( $packages ) );
        $seller_ids_set      = isset( $packages[0]['seller_id'] ) || isset( $packages[0]['vendor_id'] );

        $this->should_split_packages = $keys_non_sequential || $seller_ids_set;

        return $packages;
    }

    /**
     * Checks whether we should split the shipping packages by seller ID.
     *
     * @return bool
     */
    protected function should_split_packages() {
    	return apply_filters( 'wootax_marketplace_should_split_packages', $this->should_split_packages );
    }

    /**
     * Filters the product origin address to append the seller's user ID
     * to the origin address ID. This is required for Simple Sales Tax to
     * split the cart packages into shipments in the same way that Dokan
     * does.
     *
     * @todo check with john to see whether seller origin address should be used 
     * @todo figure out a better way to handle this
     *
     * @param SST_Origin_Address $origin Origin address for product.
     * @param array              $item   Array with info about cart item.
     *
     * @return SST_Origin_Address
     */
    public function maybe_filter_origin_address( $origin, $item ) {
    	if ( ! $this->should_split_packages() ) {
    		return $origin;
    	}

        if ( ! isset( $item['product_id'] ) ) {
            return $origin;
        }

        $origin_id = $origin->getID();
        $seller_id = get_post_field( 'post_author', $item['product_id'] );

        if ( ! $this->is_user_seller( $seller_id ) ) {
            $seller_id = 0;
        }

        return new SST_Origin_Address(
            "{$origin_id}-{$seller_id}",
            false,
            $origin->getAddress1(),
            $origin->getAddress2(),
            $origin->getCity(),
            $origin->getState(),
            $origin->getZip5()
        );
    }
    
    /**
     * Checks whether a user is a seller.
     *
     * @param int $user_id User ID.
     *
     * @return bool
     */
    public function is_user_seller( $user_id ) {
    	return apply_filters( 'wootax_marketplace_is_user_seller', $user_id );
    }

}

SST_Marketplaces::instance();
