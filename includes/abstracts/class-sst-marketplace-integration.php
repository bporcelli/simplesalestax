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
     * Key used to save seller ID in cart packages.
     *
     * @var string
     */
    protected $cart_seller_id_key = 'vendor_id';

    /**
     * Key used to save seller ID in order shipping items.
     *
     * @var string
     */
    protected $order_seller_id_key = 'vendor_id';

    /**
     * Flag to control whether package filters should be added.
     *
     * @var bool
     */
    protected $should_add_package_filters = true;

    /**
     * Constructor.
     */
    public function __construct() {
        if ( ! $this->should_add_package_filters ) {
            return;
        }

        add_filter( 'wootax_cart_packages_before_split', array( $this, 'filter_wootax_cart_packages' ), 10, 2 );
        add_filter( 'wootax_order_packages_before_split', array( $this, 'filter_wootax_order_packages' ), 10, 2 );
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
     * Filters the cart packages so that we execute one tax lookup per seller.
     *
     * @param array   $packages SST cart packages.
     * @param WC_Cart $cart     Cart instance.
     *
     * @return array
     */
    public function filter_wootax_cart_packages( $packages, $cart ) {
        $packages = array();

        // There should be one package per seller. We'll loop over each package
        // and add in any of the seller's virtual/downloadable products since
        // these aren't included in shipping packages by default.
        $shipping_packages = WC()->shipping->get_packages();
        $seller_id_key     = $this->cart_seller_id_key;

        foreach ( $shipping_packages as $key => $package ) {
            $seller_id = isset( $package[ $seller_id_key ] ) ? $package[ $seller_id_key ] : 0;

            // Add in virtual products not needing shipping.
            $virtual_products = array();

            foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
                if ( isset( $cart_item['data'] ) && ! $cart_item['data']->needs_shipping() ) {
                    $virtual_products[ $cart_item_key ] = $cart_item;
                }
            }

            if ( $virtual_products ) {
                $package['contents']      += $virtual_products;
                $package['contents_cost'] += array_sum( wp_list_pluck( $virtual_products, 'line_total' ) );
            }

            // Set origin address based on seller's store address if package is
            // for seller.
            if ( $this->is_user_seller( $seller_id ) ) {
                $package['origin'] = $this->get_seller_address( $seller_id );
            }

            $packages[ $key ] = $package;
        }

        return $packages;
    }

    /**
     * Filters the order packages so that we can execute one tax lookup per seller.
     *
     * @param array    $packages SST order packages.
     * @param WC_Order $order    Order instance.
     */
    public function filter_wootax_order_packages( $packages, $order ) {
        $packages           = array();
        $items_by_seller    = array();
        $shipping_by_seller = array();
        $order_items        = sst_format_order_items( $order->get_items() );

        foreach ( $order_items as $item_id => $item ) {
            $seller_id = get_post_field( 'post_author', $item['product_id'] );
            if ( ! isset( $items_by_seller[ $seller_id ] ) ) {
                $items_by_seller[ $seller_id ] = array();
            }
            $items_by_seller[ $seller_id ][ $item_id ] = $item;
        }

        // Assumes one shipping method per seller.
        $seller_id_key = $this->order_seller_id_key;

        foreach ( $order->get_shipping_methods() as $item_id => $shipping_method ) {
            $seller_id                        = $shipping_method->get_meta( $seller_id_key );
            $shipping_by_seller[ $seller_id ] = $shipping_method;
        }

        $seller_ids = array_keys( $shipping_by_seller + $items_by_seller );

        // Create one package per seller.
        foreach ( $seller_ids as $seller_id ) {
            $seller_package = array(
                'user' => array(
                    'ID' => $order->get_user_id(),
                ),
            );

            if ( isset( $items_by_seller[ $seller_id ] ) ) {
                $seller_package['contents'] = $items_by_seller[ $seller_id ];
            }

            if ( isset( $shipping_by_seller[ $seller_id ] ) ) {
                $shipping_method            = $shipping_by_seller[ $seller_id ];
                $seller_package['shipping'] = new WC_Shipping_Rate(
                    $shipping_method->get_id(),
                    '',
                    $shipping_method->get_total(),
                    array(),
                    $shipping_method->get_method_id()
                );
            }

            if ( $this->is_user_seller( $seller_id ) ) {
                $seller_package['origin'] = $this->get_seller_address( $seller_id );
            }

            $packages[] = sst_create_package( $seller_package );
        }

        return $packages;
    }

    /**
     * Checks whether a user is a seller.
     *
     * @param int $user_id User ID.
     *
     * @return bool
     */
    abstract public function is_user_seller( $user_id );

    /**
     * Get the origin address for a seller.
     *
     * @param int $seller_id Seller user ID.
     *
     * @return SST_Origin_Address
     */
    abstract public function get_seller_address( $seller_id );

}
