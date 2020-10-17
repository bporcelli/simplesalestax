<?php
/**
 * Marketplace Integration.
 *
 * Provides some common behaviors for SST marketplace integrations.
 *
 * @package simple-sales-tax
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class SST_Marketplace_Integration
 */
abstract class SST_Marketplace_Integration {

    /**
     * Constructor.
     */
    public function __construct() {
        if ( $this->should_split_packages_by_seller_id() ) {
            add_filter( 'wootax_origin_address', array( $this, 'filter_origin_address' ), 10, 2 );
        }
    }

    /**
     * Returns a boolean indicating whether SST should split the order by
     * seller ID. The integration should return true when the marketplace
     * plugin would split the WooCommerce cart shipping packages by seller
     * ID.
     *
     * @return bool
     */
    public function should_split_packages_by_seller_id() {
        return true;
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
    public function filter_origin_address( $origin, $item ) {
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
     * Saves the TIC for a product or variation.
     *
     * @param int $product_id Product ID.
     */
    public function save_tic( $product_id ) {
        $tic = null;

        // phpcs:disable
        if ( isset( $_REQUEST['wootax_tic'][0] ) ) { // New product
            $tic = $_REQUEST['wootax_tic'][0];
        } elseif ( isset( $_REQUEST['wootax_tic'][ $product_id ] ) ) {
            $tic = $_REQUEST['wootax_tic'][ $product_id ];
        }
        // phpcs:enable

        if ( ! is_null( $tic ) ) {
            update_post_meta( $product_id, 'wootax_tic', sanitize_text_field( $tic ) );
        }
    }

    /**
     * Checks whether a user is a seller.
     *
     * @param int $user_id User ID.
     *
     * @return bool
     */
    abstract public function is_user_seller( $user_id );

}
