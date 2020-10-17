<?php
/**
 * Simple Sales Tax Dokan Integration.
 *
 * Integrates Simple Sales Tax with Dokan.
 *
 * @package simple-sales-tax
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once dirname( SST_FILE ) . '/includes/abstracts/class-sst-marketplace-integration.php';

use TaxCloud\Address;

/**
 * Class SST_Dokan
 */
class SST_Dokan extends SST_Marketplace_Integration {

	/**
	 * Singleton instance.
	 *
	 * @var SST_Dokan
	 */
	protected static $instance = null;

	/**
	 * Minimum supported version of Dokan.
	 *
	 * @var string
	 */
	protected $min_version = '2.9.11';

	/**
	 * Flag to track whether we've outputted a TIC select button.
	 *
	 * @var bool
	 */
	protected $printed_tic_select = false;

	/**
	 * Returns the singleton instance of this class.
	 *
	 * @return SST_Dokan
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * SST_Dokan constructor.
	 */
	private function __construct() {
		// Bail if Dokan is not installed and activated.
		if ( ! defined( 'DOKAN_PLUGIN_VERSION' ) ) {
			return;
		}

		// Allow developers to disable this integration.
		if ( ! apply_filters( 'wootax_dokan_integration_enabled', true ) ) {
			return;
		}

		// Print an error if the Dokan version is not supported.
		if ( version_compare( DOKAN_PLUGIN_VERSION, $this->min_version, '<' ) ) {
			add_action( 'admin_notices', array( $this, 'dokan_version_notice' ) );
			return;
		}

		add_action( 'dokan_new_product_after_product_tags', array( $this, 'output_tic_select' ) );
		add_action( 'dokan_product_edit_after_product_tags', array( $this, 'output_tic_select' ) );
		add_action( 'dokan_product_after_variable_attributes', array( $this, 'output_tic_select_for_variation' ), 10, 3 );
		add_action( 'dokan_new_product_added', array( $this, 'save_tic' ) );
        add_action( 'dokan_product_updated', array( $this, 'save_tic' ) );
		add_action( 'dokan_save_product_variation', array( $this, 'save_tic' ) );
		add_action( 'woocommerce_save_product_variation', array( $this, 'save_tic' ) );
		add_action( 'dokan_variation_options_pricing', array( $this, 'disable_wc_taxes' ) );
		add_action( 'dokan_product_after_variable_attributes', array( $this, 'reenable_wc_taxes' ) );
		add_action( 'wp_footer', array( $this, 'print_styles_to_hide_tax_class_field' ) );
		add_filter( 'wootax_tic_select_init_events', array( $this, 'filter_tic_select_init_events' ) );

		parent::__construct();
	}

	/**
	 * Admin notice displayed when an unsupported version of Dokan is detected.
	 */
	public function dokan_version_notice() {
		?>
		<div class="notice notice-error">
			<p>
				<?php
				printf(
					/* translators: minimum supported Dokan version */
					esc_html__(
						'Simple Sales Tax does not support the installed version of Dokan. Dokan %s+ is required.',
						'simple-sales-tax'
					),
					esc_html( $this->min_version )
				);
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Outputs the TIC select field for a variation.
	 *
	 * @param int     $loop           Variation loop index.
	 * @param array   $variation_data Variation data.
	 * @param WP_Post $variation      Variation.
	 */
	public function output_tic_select_for_variation( $loop, $variation_data, $variation ) {
		$this->output_tic_select( $variation );
	}

	/**
	 * Outputs the TIC select field for a product.
	 *
	 * @param WP_Post $post The post being edited.
	 */
	public function output_tic_select( $post = null ) {
		$product_id  = $post ? $post->ID : 0;
		$select_args = array(
			'button_class' => 'dokan-btn',
			'product_id'   => $product_id,
		);

		if ( isset( $_REQUEST['wootax_tic'][ $product_id ] ) ) {
			$select_args['value'] = sanitize_text_field(
				$_REQUEST['wootax_tic'][ $product_id ]
			);
		}

		$label_class = 'form-label';

		if ( $product_id && 'product_variation' === get_post_type( $product_id ) ) {
			$label_class = '';
		}

		?>
		<div class="dokan-form-group sst-tic-select-wrap">
			<label for="wootax_tic[<?php echo esc_attr( $product_id ); ?>]" class="<?php echo esc_attr( $label_class ); ?>">
				<?php esc_html_e( 'Taxability Information Code (TIC)', 'simple-sales-tax' ); ?>
			</label>
			<div class="control">
				<?php sst_output_tic_select_field( $select_args ); ?>
			</div>
			<p class="help-block">
				<?php echo esc_html( sst_get_tic_select_help_text() ); ?>
			</p>
		</div>
		<?php

		$this->printed_tic_select = true;
	}

	/**
	 * Print an inline style rule to hide Dokan's Tax Class and Tax Status fields.
	 */
	public function print_styles_to_hide_tax_class_field() {
		if ( $this->printed_tic_select ) {
			?>
			<style type="text/css">
				.dokan-tax-container {
					display: none !important;
				}
			</style>
			<?php
		}
	}

	/**
	 * Adds a filter to force `wc_taxes_enabled` to return false.
	 * This is used to hide the Tax Class field Dokan outputs in
	 * the html-product-variation.php template.
	 */
	public function disable_wc_taxes() {
		add_filter( 'wc_tax_enabled', '__return_false' );
	}

	/**
	 * Removes the filter to force `wc_taxes_enabled` to return false.
	 */
	public function reenable_wc_taxes() {
		remove_filter( 'wc_tax_enabled', '__return_false' );
	}

	/**
	 * Adds dokan_variations_loaded and dokan_variation_added to the list of
	 * JS events that trigger TIC select initialization.
	 *
	 * @param string $events JS events that trigger TIC select initialization.
	 *
	 * @return string
	 */
	public function filter_tic_select_init_events( $events ) {
		return trim( "{$events} dokan_variations_loaded dokan_variation_added" );
	}

	/**
	 * Returns a boolean indicating whether SST should split the order by
	 * seller ID.
	 *
	 * @return bool
	 */
	public function should_split_packages_by_seller_id() {
		return has_filter( 'woocommerce_cart_shipping_packages', 'dokan_custom_split_shipping_packages' );
	}

	/**
	 * Checks whether a user is a seller.
	 *
	 * @param int $user_id User ID.
	 *
	 * @return bool
	 */
	public function is_user_seller( $user_id ) {
		return dokan_is_user_seller( $user_id );
	}

}

SST_Dokan::instance();
