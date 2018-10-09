<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Checkout.
 *
 * Responsible for computing the sales tax due during checkout.
 *
 * @author  Simple Sales Tax
 * @package SST
 * @since   5.0
 */
class SST_Checkout extends SST_Abstract_Cart {

    /**
     * @var WC_Cart The cart we are calculating taxes for.
     * @since 5.0
     */
    private $cart = null;

    /**
     * Constructor: Initialize hooks.
     *
     * @since 5.0
     */
    public function __construct() {
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );
        add_action( 'woocommerce_calculate_totals', array( $this, 'calculate_tax_totals' ), 15 );
        add_filter( 'woocommerce_calculated_total', array( $this, 'filter_calculated_total' ) );
        add_filter( 'woocommerce_cart_hide_zero_taxes', array( $this, 'hide_zero_taxes' ) );
        add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'add_order_meta' ) );
        add_action( 'woocommerce_cart_emptied', array( $this, 'clear_package_cache' ) );

        if ( sst_storefront_active() ) {
            add_action( 'woocommerce_checkout_shipping', array( $this, 'output_exemption_form' ), 15 );
        } else {
            add_action( 'woocommerce_checkout_after_customer_details', array( $this, 'output_exemption_form' ) );
        }

        add_action( 'woocommerce_checkout_create_order_shipping_item', array( $this, 'add_shipping_meta' ), 10, 3 );
    }

    /**
     * Perform a tax lookup and update the sales tax for all items.
     *
     * @since 5.0
     *
     * @param WC_Cart $cart
     */
    public function calculate_tax_totals( $cart ) {
        $this->cart = new SST_Cart_Proxy( $cart );
        parent::calculate_taxes();
    }

    /**
     * Add the calculated sales tax to the cart total (WC 3.2+)
     *
     * @since 5.6
     *
     * @param float $total total calculated by WooCommerce (excludes tax)
     *
     * @return float
     */
    public function filter_calculated_total( $total ) {
        if ( sst_woocommerce_gte_32() ) {
            $total += $this->cart->get_cart_contents_tax() + $this->cart->get_fee_tax() + $this->cart->get_shipping_tax(
                );
        }
        return $total;
    }

    /**
     * Should the Sales Tax line item be hidden if no tax is due?
     *
     * @since 5.0
     *
     * @return bool
     */
    public function hide_zero_taxes() {
        return SST_Settings::get( 'show_zero_tax' ) != 'true';
    }

    /**
     * Get saved packages for this cart.
     *
     * @since 5.0
     *
     * @return array
     */
    protected function get_packages() {
        return $this->cart->get_packages();
    }

    /**
     * Set saved packages for this cart.
     *
     * @since 5.0
     *
     * @param $packages array (default: array())
     */
    protected function set_packages( $packages = array() ) {
        $this->cart->set_packages( $packages );
    }

    /**
     * Filter items not needing shipping callback.
     *
     * @since 5.0
     *
     * @param array $item
     *
     * @return bool
     */
    protected function filter_items_not_needing_shipping( $item ) {
        return $item['data'] && ! $item['data']->needs_shipping();
    }

    /**
     * Get only items that don't need shipping.
     *
     * @since 5.0
     *
     * @return array
     */
    protected function get_items_not_needing_shipping() {
        return array_filter( $this->cart->get_cart(), array( $this, 'filter_items_not_needing_shipping' ) );
    }

    /**
     * Get the shipping rate for a package.
     *
     * @since 5.0
     *
     * @param int $key
     * @param array $package
     *
     * @return WC_Shipping_Rate | NULL
     */
    protected function get_package_shipping_rate( $key, $package ) {
        $chosen_methods = WC()->session->get( 'chosen_shipping_methods' );

        /* WC Multiple Shipping doesn't use chosen_shipping_methods -_- */
        if ( function_exists( 'wcms_session_isset' ) && wcms_session_isset( 'shipping_methods' ) ) {
            $chosen_methods = array();

            foreach ( wcms_session_get( 'shipping_methods' ) as $package_key => $method ) {
                $chosen_methods[ $package_key ] = $method['id'];
            }
        }

        if ( isset( $chosen_methods[ $key ], $package['rates'][ $chosen_methods[ $key ] ] ) ) {
            return $package['rates'][ $chosen_methods[ $key ] ];
        }

        return null;
    }

    /**
     * Get the base shipping packages for this cart.
     *
     * @since 5.5
     *
     * @return array
     */
    protected function get_base_packages() {
        /* Start with the packages returned by Woo */
        $packages = WC()->shipping->get_packages();

        /* After WooCommerce 3.0, items that do not need shipping are excluded
         * from shipping packages. To ensure that these products are taxed, we
         * create a special package for them. */
        if ( ( $virtual_package = $this->create_virtual_package() ) ) {
            $packages[] = $virtual_package;
        }

        /* Set the shipping method for each package, replacing the destination
         * address with a local pickup address if appropriate. */
        foreach ( $packages as $key => $package ) {
            $method = $this->get_package_shipping_rate( $key, $package );

            if ( is_null( $method ) ) {
                continue;
            }

            $method     = clone $method;    /* IMPORTANT: preserve original */
            $method->id = $key;

            $packages[ $key ]['shipping'] = $method;

            if ( SST_Shipping::is_local_pickup( array( $method->method_id ) ) ) {
                $pickup_address = apply_filters( 'wootax_pickup_address', SST_Addresses::get_default_address(), null );

                $packages[ $key ]['destination'] = array(
                    'country'   => 'US',
                    'address'   => $pickup_address->getAddress1(),
                    'address_2' => $pickup_address->getAddress2(),
                    'city'      => $pickup_address->getCity(),
                    'state'     => $pickup_address->getState(),
                    'postcode'  => $pickup_address->getZip5(),
                );
            }
        }

        return $packages;
    }

    /**
     * Creates a virtual shipping package for all items that don't need shipping.
     *
     * @return array|false Package or false if all cart items need shipping.
     */
    protected function create_virtual_package() {
        $digital_items = $this->get_items_not_needing_shipping();

        if ( ! empty( $digital_items ) ) {
            return sst_create_package(
                [
                    'contents'    => $digital_items,
                    'destination' => [
                        'country'   => WC()->customer->get_billing_country(),
                        'address'   => WC()->customer->get_billing_address(),
                        'address_2' => WC()->customer->get_billing_address_2(),
                        'city'      => WC()->customer->get_billing_city(),
                        'state'     => WC()->customer->get_billing_state(),
                        'postcode'  => WC()->customer->get_billing_postcode(),
                    ],
                    'user'        => [
                        'ID' => get_current_user_id(),
                    ],
                ]
            );
        }

        return false;
    }

    /**
     * Create shipping packages for this cart.
     *
     * @since 5.0
     *
     * @return array
     */
    protected function create_packages() {
        $packages = array();

        /* Let devs change the packages before we split them. */
        $raw_packages = apply_filters(
            'wootax_cart_packages_before_split',
            $this->get_filtered_packages(),
            $this->cart
        );

        /* Split packages by origin address. */
        foreach ( $raw_packages as $raw_package ) {
            $packages = array_merge( $packages, $this->split_package( $raw_package ) );
        }

        /* Add fees to first package. */
        if ( ! empty( $packages ) && apply_filters( 'wootax_add_fees', true ) ) {
            $packages[ key( $packages ) ]['fees'] = $this->cart->get_fees();
        }

        return apply_filters( 'wootax_cart_packages', $packages, $this->cart );
    }

    /**
     * Reset sales tax totals.
     *
     * @since 5.0
     */
    protected function reset_taxes() {
        foreach ( $this->cart->get_cart() as $cart_key => $item ) {
            $this->set_product_tax( $cart_key, 0 );
        }

        foreach ( $this->cart->get_fees() as $key => $fee ) {
            $this->set_fee_tax( $key, 0 );
        }

        $this->cart->reset_shipping_taxes();

        $this->update_taxes();
    }

    /**
     * Update sales tax totals.
     *
     * @since 5.0
     */
    protected function update_taxes() {
        $cart_tax_total = 0;

        foreach ( $this->cart->get_cart() as $item ) {
            $tax_data = $item['line_tax_data'];

            if ( isset( $tax_data['total'], $tax_data['total'][ SST_RATE_ID ] ) ) {
                $cart_tax_total += $tax_data['total'][ SST_RATE_ID ];
            }
        }

        foreach ( $this->cart->get_fees() as $fee ) {
            if ( isset( $fee->tax_data[ SST_RATE_ID ] ) ) {
                $cart_tax_total += $fee->tax_data[ SST_RATE_ID ];
            }
        }

        $shipping_tax_total = WC_Tax::get_tax_total( $this->cart->sst_shipping_taxes );

        $this->cart->set_tax_amount( SST_RATE_ID, $cart_tax_total );
        $this->cart->set_shipping_tax_amount( SST_RATE_ID, $shipping_tax_total );

        $this->cart->update_tax_totals();
    }

    /**
     * Set the tax for a product.
     *
     * @since 5.0
     *
     * @param mixed $id Product ID.
     * @param float $tax Sales tax for product.
     */
    protected function set_product_tax( $id, $tax ) {
        $this->cart->set_cart_item_tax( $id, $tax );
    }

    /**
     * Set the tax for a shipping package.
     *
     * @since 5.0
     *
     * @param mixed $id Package key.
     * @param float $tax Sales tax for package.
     */
    protected function set_shipping_tax( $id, $tax ) {
        $this->cart->set_package_tax( $id, $tax );
    }

    /**
     * Set the tax for a fee.
     *
     * @since 5.0
     *
     * @param mixed $id Fee ID.
     * @param float $tax Sales tax for fee.
     */
    protected function set_fee_tax( $id, $tax ) {
        $this->cart->set_fee_item_tax( $id, $tax );
    }

    /**
     * Get the customer exemption certificate.
     *
     * @since 5.0
     *
     * @return TaxCloud\ExemptionCertificateBase|NULL
     */
    public function get_certificate() {
        if ( ! isset( $_POST['post_data'] ) ) {
            $post_data = $_POST;
        } else {
            $post_data = array();
            parse_str( $_POST['post_data'], $post_data );
        }

        if ( isset( $post_data['tax_exempt'] ) && isset( $post_data['certificate_id'] ) ) {
            $certificate_id = sanitize_text_field( $post_data['certificate_id'] );
            return new TaxCloud\ExemptionCertificateBase( $certificate_id );
        }

        return null;
    }

    /**
     * Display an error message to the user.
     *
     * @since 5.0
     *
     * @param string $message Message describing the error.
     */
    protected function handle_error( $message ) {
        wc_add_notice( $message, 'error' );

        SST_Logger::add( $message );
    }

    /**
     * Save metadata when a new order is created.
     *
     * @since 4.2
     *
     * @param int $order_id ID of new order.
     */
    public function add_order_meta( $order_id ) {
        // Make sure we're saving the data from the 'main' cart
        $this->cart = new SST_Cart_Proxy( WC()->cart );

        $order = new SST_Order( $order_id );

        $order->update_meta( 'packages', $this->get_packages() );
        $order->update_meta( 'exempt_cert', $this->get_certificate() );

        $order->save();
    }

    /**
     * Given a package key, return the shipping tax for the package.
     *
     * @since 5.0
     *
     * @param string $package_key
     *
     * @return float -1 if no shipping tax, otherwise shipping tax.
     */
    protected function get_package_shipping_tax( $package_key ) {
        $cart          = WC()->cart;
        $package_index = $package_key;
        $cart_key      = '';

        if ( sst_subs_active() ) {
            $last_underscore_i = strrpos( $package_key, '_' );

            if ( $last_underscore_i !== false ) {
                $cart_key      = substr( $package_key, 0, $last_underscore_i );
                $package_index = substr( $package_key, $last_underscore_i + 1 );
            }
        }

        if ( ! empty( $cart_key ) ) {
            $cart = WC()->cart->recurring_carts[ $cart_key ];
        }

        if ( isset( $cart->sst_shipping_taxes[ $package_index ] ) ) {
            return $cart->sst_shipping_taxes[ $package_index ];
        } else {
            return -1;
        }
    }

    /**
     * Add shipping meta for newly created shipping items.
     *
     * @since 5.0
     *
     * @param WC_Order_Item_Shipping $item
     * @param int $package_key
     * @param array $package
     *
     * @throws WC_Data_Exception
     */
    public function add_shipping_meta( $item, $package_key, $package ) {
        $shipping_tax = $this->get_package_shipping_tax( $package_key );

        if ( $shipping_tax >= 0 ) {
            $taxes                         = $item->get_taxes();
            $taxes['total'][ SST_RATE_ID ] = $shipping_tax;
            $item->set_taxes( $taxes );
        }
    }

    /**
     * Enqueues the CSS for the exemption management interface.
     *
     * @since 5.0
     */
    public function enqueue_styles() {
        SST()->assets->enqueue( 'style', 'simplesalestax.modal' );
        SST()->assets->enqueue( 'style', 'simplesalestax.certificate-modal' );
    }

    /**
     * Does the customer have an exempt user role?
     *
     * @since 5.0
     *
     * @return bool
     */
    protected function is_user_exempt() {
        $current_user = wp_get_current_user();
        $exempt_roles = SST_Settings::get( 'exempt_roles', [] );
        $user_roles   = is_user_logged_in() ? $current_user->roles : [];

        return count( array_intersect( $exempt_roles, $user_roles ) ) > 0;
    }

    /**
     * Should the exemption form be displayed?
     *
     * @since 5.0
     */
    protected function show_exemption_form() {
        $restricted = SST_Settings::get( 'restrict_exempt' ) == 'yes';
        $enabled    = SST_Settings::get( 'show_exempt' ) == 'true';

        return $enabled && ( ! $restricted || $this->is_user_exempt() );
    }

    /**
     * Output Tax Details section of checkout form.
     *
     * @since 5.0
     */
    public function output_exemption_form() {
        if ( ! $this->show_exemption_form() ) {
            return;
        }

        SST()->assets->enqueue( 'script', 'simplesalestax.checkout' );

        wc_get_template(
            'html-certificate-table.php',
            [
                'checked'  => ! $_POST && $this->is_user_exempt() || $_POST && isset( $_POST['tax_exempt'] ),
                'selected' => isset( $_POST['certificate_id'] ) ? $_POST['certificate_id'] : '',
            ],
            'sst/checkout/',
            SST()->path( 'includes/frontend/views/' )
        );
    }

    /**
     * Clear cached shipping packages when the cart is emptied.
     *
     * @since 5.7
     */
    public function clear_package_cache() {
        WC()->session->set( 'sst_packages', array() );
    }

}

new SST_Checkout();
