<?php
/**
 * Simple Sales Tax WC Vendors Pro Integration.
 *
 * Integrates Simple Sales Tax with WC Vendors Pro.
 *
 * @package simple-sales-tax
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once dirname( SST_FILE ) . '/includes/abstracts/class-sst-marketplace-integration.php';

/**
 * Class SST_WC_Vendors
 */
class SST_WC_Vendors extends SST_Marketplace_Integration {

	/**
	 * Singleton instance.
	 *
	 * @var SST_WC_Vendors
	 */
	protected static $instance = null;

	/**
	 * Minimum supported version of WC Vendors Pro.
	 *
	 * @var string
	 */
	protected $min_version = '1.5.8';

	/**
	 * Returns the singleton instance of this class.
	 *
	 * @return SST_WC_Vendors
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * SST_WC_Vendors constructor.
	 */
	private function __construct() {
		// Bail if WC Vendors Pro is not installed and activated.
		if ( ! defined( 'WCV_PRO_VERSION' ) ) {
			return;
		}

		// Allow developers to disable this integration.
		if ( ! apply_filters( 'wootax_wc_vendors_integration_enabled', true ) ) {
			return;
		}

		// Print an error if the WC Vendors Pro version is not supported.
		if ( version_compare( WCV_PRO_VERSION, $this->min_version, '<' ) ) {
			add_action( 'admin_notices', array( $this, 'wcv_pro_version_notice' ) );
			return;
		}

		add_action( 'wcv_after_product_details', array( $this, 'output_tic_select' ) );
		add_action( 'wcv_product_variation_before_tax_class', array( $this, 'output_tic_select_for_variation' ), 10, 2 );
		add_action( 'wcvendors_before_product_form', array( $this, 'hide_tax_fields' ) );
		add_action( 'wcv_save_product', array( $this, 'save_tic' ) );
		add_action( 'wcv_save_product_variation', array( $this, 'save_tic' ) );
		add_filter( 'wootax_marketplace_is_user_seller', array( $this, 'is_user_seller' ) );

		parent::__construct();
	}

	/**
	 * Admin notice displayed when an unsupported version of WC Vendors Pro
	 * is detected.
	 */
	public function wcv_pro_version_notice() {
		?>
		<div class="notice notice-error">
			<p>
				<?php
				printf(
					/* translators: minimum supported WC Vendors Pro version */
					esc_html__(
						'Simple Sales Tax does not support the installed version of WC Vendors Pro. WC Vendors Pro %s+ is required.',
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
	 * @param int $loop         Variation loop index.
	 * @param int $variation_id Variation ID.
	 */
	public function output_tic_select_for_variation( $loop, $variation_id ) {
		?>
		<div class="wcv-cols-group wcv-horizontal-gutters">
			<div class="all-100">
				<?php $this->output_tic_select( $variation_id ); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Outputs the TIC select field for a product.
	 *
	 * @param int $product_id ID of post being edited.
	 */
	public function output_tic_select( $product_id ) {
		$select_args = array(
			'button_class' => 'wcv-button',
			'product_id'   => $product_id,
		);

		wp_enqueue_script( 'sst-wcv-tic-select' );

		?>
		<div class="control-group sst-tic-select-wrap">
			<label for="wootax_tic[<?php echo esc_attr( $product_id ); ?>]">
				<?php esc_html_e( 'Taxability Information Code (TIC)', 'simple-sales-tax' ); ?>
			</label>
			<div class="control">
				<?php sst_output_tic_select_field( $select_args ); ?>
			</div>
			<p class="tip">
				<?php echo esc_html( sst_get_tic_select_help_text() ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Adds some filters to hide the tax fields in the product form.
	 */
	public function hide_tax_fields() {
		add_filter( 'pre_option_wcvendors_hide_product_variations_tax_class', array( $this, 'return_yes' ) );
		add_filter( 'pre_option_wcvendors_hide_product_general_tax', array( $this, 'return_yes' ) );
	}

	/**
	 * Returns yes. Used to hide the Tax Class and Tax Status fields
	 * by setting the value for the corresponding "hide" options to
	 * 'yes'.
	 *
	 * @return string
	 */
	public function return_yes() {
		return 'yes';
	}

	/**
	 * Checks whether a user is a seller.
	 *
	 * @param int $user_id User ID.
	 *
	 * @return bool
	 */
	public function is_user_seller( $user_id ) {
		return WCV_Vendors::is_vendor( $user_id );
	}

}

SST_WC_Vendors::instance();
