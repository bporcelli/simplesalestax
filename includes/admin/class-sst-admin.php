<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Admin.
 *
 * Handles the admin interface.
 *
 * @author  Simple Sales Tax
 * @package SST
 * @since   4.2
 */
class SST_Admin {

    /**
     * Class constructor.
     *
     * @since 4.7
     */
    public function __construct() {
        $this->includes();
        $this->hooks();
    }

    /**
     * Bootstraps the admin class.
     *
     * @since 4.7
     */
    private function hooks() {
        add_filter( 'woocommerce_integrations', array( __CLASS__, 'add_integration' ) );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_scripts_and_styles' ) );
        add_action( 'add_meta_boxes', array( __CLASS__, 'add_metaboxes' ) );
        add_action( 'woocommerce_reports_charts', array( __CLASS__, 'add_reports_tab' ) );
        add_filter( 'woocommerce_debug_tools', array( __CLASS__, 'register_debug_tool' ) );
        add_action( 'product_cat_add_form_fields', array( __CLASS__, 'output_category_tic_select' ) );
        add_action( 'product_cat_edit_form_fields', array( __CLASS__, 'output_category_tic_select' ) );
        add_action( 'create_product_cat', array( __CLASS__, 'save_category_tic' ) );
        add_action( 'edited_product_cat', array( __CLASS__, 'save_category_tic' ) );
    }

    /**
     * Include required files.
     *
     * @since 5.0
     */
    public function includes() {
        include_once dirname( __DIR__ ) . '/sst-message-functions.php';
        include_once __DIR__ . '/class-sst-integration.php';
    }

    /**
     * Register our WooCommerce integration.
     *
     * @since 4.2
     */
    public static function add_integration( $integrations ) {
        $integrations[] = 'SST_Integration';
        return $integrations;
    }

    /**
     * Enqueue admin scripts and styles.
     *
     * @since 4.2
     */
    public static function enqueue_scripts_and_styles() {
        // Admin JS
        SST()->assets->enqueue( 'script', 'simplesalestax.admin' );

        // Admin CSS
        SST()->assets->enqueue( 'style', 'simplesalestax.admin' );
        SST()->assets->enqueue( 'style', 'simplesalestax.certificate-modal' );
    }

    /**
     * Register "Sales Tax" metabox.
     *
     * @since 4.2
     */
    public static function add_metaboxes() {
        add_meta_box(
            'sales_tax_meta',
            __( 'Simple Sales Tax', 'simplesalestax' ),
            array( __CLASS__, 'output_tax_metabox' ),
            'shop_order',
            'side',
            'high'
        );
    }

    /**
     * Output HTML for "Sales Tax" metabox.
     *
     * @since 4.2
     *
     * @param WP_Post $post WP_Post object for product being edited.
     */
    public static function output_tax_metabox( $post ) {
        $order           = new SST_Order( $post->ID );
        $status          = $order->get_taxcloud_status( 'view' );
        $raw_certificate = $order->get_certificate();
        $certificate     = '';

        if ( ! is_null( $raw_certificate ) ) {
            $certificate = SST_Certificates::get_certificate_formatted(
                $raw_certificate->getCertificateID(),
                $order->get_user_id()
            );
        }

        SST()->assets->enqueue(
            'script',
            'simplesalestax.view-certificate',
            [
                'deps'     => [ 'jquery', 'simplesalestax.backbone-modal' ],
                'localize' => [
                    'SSTCertData' => [
                        'certificate' => $certificate,
                        'seller_name' => SST_Settings::get( 'company_name' ),
                        'images'      => [
                            'single_cert'  => SST()->url( '/assets/img/sp_exemption_certificate750x600.png' ),
                            'blanket_cert' => SST()->url( '/assets/img/exemption_certificate750x600.png' ),
                        ],
                    ],
                ],
            ]
        );

        include __DIR__ . '/views/html-meta-box.php';
        include dirname( __DIR__ ) . '/frontend/views/html-view-certificate.php';
    }

    /**
     * Link to TaxCloud "Reports" page from "Taxes" tab.
     *
     * @since 4.2
     */
    public static function output_tax_report_button() {
        ?>
        <div id="poststuff" class="wootax-reports-page">
            <a target="_blank" href="https://simplesalestax.com/taxcloud/reports/"
               class="wp-core-ui button button-primary"><?php _e(
                    'Go to TaxCloud Reports Page',
                    'simplesalestax'
                ); ?></a>
        </div>
        <?php
    }

    /**
     * Add a "Taxes" tab to the WooCommerce reports page.
     *
     * @since 4.2
     *
     * @param  array $charts Array of charts to be rendered on the reports page.
     *
     * @return array
     */
    public static function add_reports_tab( $charts ) {
        $charts['taxes'] = array(
            'title'  => __( 'Taxes', 'simplesalestax' ),
            'charts' => array(
                'overview' => array(
                    'title'       => __( 'Overview', 'simplesalestax' ),
                    'description' => '',
                    'hide_title'  => true,
                    'function'    => array( __CLASS__, 'output_tax_report_button' ),
                ),
            ),
        );

        return $charts;
    }

    /**
     * Delete cached tax rates.
     *
     * @since 4.5
     */
    public static function remove_rate_transients() {
        global $wpdb;

        if ( ! empty( $_REQUEST['_wpnonce'] ) && wp_verify_nonce( $_REQUEST['_wpnonce'], 'debug_action' ) ) {
            $prefix = "wc_tax_rates";

            $rate_transients = $wpdb->get_results(
                "SELECT option_name as name FROM $wpdb->options WHERE option_name LIKE '_transient_{$prefix}_%'"
            );

            if ( ! $rate_transients ) {
                sst_add_message( __( 'There are no cached rates to remove.', 'simplesalestax' ), 'updated' );
                return;
            }

            foreach ( $rate_transients as $transient ) {
                $trans_key = substr( $transient->name, strpos( $transient->name, 'wc' ) );
                delete_transient( $trans_key );
            }

            sst_add_message(
                sprintf( __( '%d cached tax rates removed.', 'simplesalestax' ), count( $rate_transients ) ),
                'updated'
            );
        }
    }

    /**
     * WooCommerce will cache matched tax rates for a particular address. This can
     * be problematic if the user had existing tax rates at the time SST was installed.
     *
     * To handle problem cases, we provide a "debug tool" that removes all cached
     * tax rates. This method registers that debug tool with WooCommerce.
     *
     * Note that our tool can be accessed from WooCommerce -> System Status -> Tools.
     *
     * @since 4.4
     *
     * @param  array $tools Array of registered debug tools.
     *
     * @return array
     */
    public static function register_debug_tool( $tools ) {
        $tools['wootax_rate_tool'] = array(
            'name'     => __( 'Delete cached tax rates', 'simplesalestax' ),
            'button'   => __( 'Clear cache', 'simplesalestax' ),
            'desc'     => __( 'This tool will remove any tax rates cached by WooCommerce.', 'simplesalestax' ),
            'callback' => array( __CLASS__, 'remove_rate_transients' ),
        );

        return $tools;
    }

    /**
     * Add a "Default TIC" field to the "Add Category" and "Edit Category" screens.
     *
     * @since 5.0
     *
     * @param WP_Term|string $term_or_taxonomy (default: NULL).
     */
    public static function output_category_tic_select( $term_or_taxonomy = null ) {
        $is_edit     = is_a( $term_or_taxonomy, 'WP_Term' );
        $current_tic = '';

        if ( $is_edit ) {
            $current_tic = get_term_meta( $term_or_taxonomy->term_id, 'tic', true );
        }

        wp_localize_script(
            'simplesalestax.tic-select',
            'ticSelectLocalizeScript',
            array(
                'tic_list' => sst_get_tics(),
                'strings'  => array(
                    'default' => __( 'Using site default', 'simplesalestax' ),
                ),
            )
        );

        SST()->assets->enqueue( 'script', 'simplesalestax.tic-select' );

        include __DIR__ . '/views/html-select-tic-category.php';
    }

    /**
     * Save Default TIC for category.
     *
     * @since 4.5
     *
     * @param int $term_id ID of category being saved.
     */
    public static function save_category_tic( $term_id ) {
        update_term_meta( $term_id, 'tic', isset( $_REQUEST['wootax_tic'] ) ? $_REQUEST['wootax_tic'] : '' );
    }
}

new SST_Admin();