<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
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
		add_action( 'woocommerce_before_settings_tax', array( __CLASS__, 'tax_based_on_notice' ) );
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
	 * @param array $integrations Registered WooCommerce integrations.
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
		// Admin JS.
		wp_enqueue_script( 'sst-admin-js' );

		// Admin CSS.
		wp_enqueue_style( 'sst-admin-css' );
		wp_enqueue_style( 'sst-certificate-modal-css' );
	}

	/**
	 * Register "Sales Tax" metabox.
	 *
	 * @since 4.2
	 */
	public static function add_metaboxes() {
		add_meta_box(
			'sales_tax_meta',
			__( 'Simple Sales Tax', 'simple-sales-tax' ),
			array( __CLASS__, 'output_tax_metabox' ),
			'shop_order',
			'side',
			'high'
		);
	}

	/**
	 * Output HTML for "Sales Tax" metabox.
	 *
	 * @param WP_Post $post WP_Post object for product being edited.
	 *
	 * @since 4.2
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

		wp_enqueue_script( 'sst-view-certificate' );
		wp_localize_script(
			'sst-view-certificate',
			'SSTCertData',
			array(
				'certificate' => $certificate,
				'seller_name' => SST_Settings::get( 'company_name' ),
				'images'      => array(
					'single_cert'  => SST()->url( 'assets/img/sp_exemption_certificate750x600.png' ),
					'blanket_cert' => SST()->url( 'assets/img/exemption_certificate750x600.png' ),
				),
			)
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
			   class="wp-core-ui button button-primary">
				<?php esc_html_e( 'Go to TaxCloud Reports Page', 'simple-sales-tax' ); ?>
			</a>
		</div>
		<?php
	}

	/**
	 * Add a "Taxes" tab to the WooCommerce reports page.
	 *
	 * @param array $charts Array of charts to be rendered on the reports page.
	 *
	 * @return array
	 * @since 4.2
	 */
	public static function add_reports_tab( $charts ) {
		$charts['taxes'] = array(
			'title'  => __( 'Taxes', 'simple-sales-tax' ),
			'charts' => array(
				'overview' => array(
					'title'       => __( 'Overview', 'simple-sales-tax' ),
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
		wc_deprecated_function( __METHOD__, '6.1', __CLASS__ . '::invalidate_wc_tax_rates_cache' );
		self::invalidate_wc_tax_rates_cache();
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
	 * @param array $tools Array of registered debug tools.
	 *
	 * @return array
	 * @since 4.4
	 */
	public static function register_debug_tool( $tools ) {
		$tools['wootax_rate_tool'] = array(
			'name'     => __( 'Delete cached tax rates', 'simple-sales-tax' ),
			'button'   => __( 'Clear tax rate cache', 'simple-sales-tax' ),
			'desc'     => __( 'This tool will remove any tax rates cached by WooCommerce.', 'simple-sales-tax' ),
			'callback' => array( __CLASS__, 'invalidate_wc_tax_rates_cache' ),
		);

		return $tools;
	}

	/**
	 * Invalidates the WooCommerce tax rate cache.
	 *
	 * @return string Output from debug tool
	 */
	public static function invalidate_wc_tax_rates_cache() {
		if ( method_exists( 'WC_Cache_Helper', 'invalidate_cache_group' ) ) {  // WC 3.9+.
			WC_Cache_Helper::invalidate_cache_group( 'taxes' );
		} else {
			WC_Cache_Helper::incr_cache_prefix( 'taxes' );
		}

		return __( 'Tax rate cache cleared.', 'simple-sales-tax' );
	}

	/**
	 * Add a "Default TIC" field to the "Add Category" and "Edit Category" screens.
	 *
	 * @param WP_Term|string $term_or_taxonomy (default: NULL).
	 *
	 * @since 5.0
	 */
	public static function output_category_tic_select( $term_or_taxonomy = null ) {
		$is_edit     = is_a( $term_or_taxonomy, 'WP_Term' );
		$current_tic = '';

		if ( $is_edit ) {
			$current_tic = get_term_meta( $term_or_taxonomy->term_id, 'tic', true );
		}

		wp_localize_script(
			'sst-tic-select',
			'ticSelectLocalizeScript',
			array(
				'tic_list' => sst_get_tics(),
				'strings'  => array(
					'default' => __( 'Using site default', 'simple-sales-tax' ),
				),
			)
		);

		wp_enqueue_script( 'sst-tic-select' );

		include __DIR__ . '/views/html-select-tic-category.php';
	}

	/**
	 * Save Default TIC for category.
	 *
	 * @param int $term_id ID of category being saved.
	 *
	 * @since 4.5
	 */
	public static function save_category_tic( $term_id ) {
		$tic = isset( $_REQUEST['wootax_tic'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['wootax_tic'] ) ) : ''; // phpcs:ignore WordPress.CSRF.NonceVerification

		update_term_meta( $term_id, 'tic', $tic );
	}

	/**
	 * Outputs a notice under WooCommerce > Settings > Tax to indicate that the
	 * WooCommerce "Calculate tax based on" setting is not respected.
	 */
	public static function tax_based_on_notice() {
		$section = isset( $_GET['section'] ) ? sanitize_text_field( wp_unslash( $_GET['section'] ) ) : ''; // phpcs:ignore WordPress.CSRF.NonceVerification

		if ( in_array( $section, array( '', 'tax' ), true ) ) {
			?>
			<div class="notice notice-warning">
				<p>
					<?php
					printf(
						'<strong>%1$s</strong> %2$s',
						esc_html__( 'Heads up!', 'simple-sales-tax' ),
						esc_html__(
							'The WooCommerce "Calculate tax based on" setting is not respected by Simple Sales Tax. The customer billing address will only be used for tax calculations when the shipping address is not provided (e.g. for sales of digital goods).',
							'simple-sales-tax'
						)
					);
					?>
				</p>
			</div>
			<?php
		}
	}

}

new SST_Admin();
