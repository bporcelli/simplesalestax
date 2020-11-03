<?php
/**
 * Simple Sales Tax WCFM Integration.
 *
 * Integrates Simple Sales Tax with WooCommerce Frontend Manager.
 *
 * @package simple-sales-tax
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once dirname( SST_FILE ) . '/includes/abstracts/class-sst-marketplace-integration.php';

/**
 * Class SST_WCFM
 */
class SST_WCFM extends SST_Marketplace_Integration {

	/**
	 * Singleton instance.
	 *
	 * @var SST_WCFM
	 */
	protected static $instance = null;

	/**
	 * Minimum supported version of WCFM.
	 *
	 * @var string
	 */
	protected $min_version = '6.5.0';

	/**
	 * Returns the singleton instance of this class.
	 *
	 * @return SST_WCFM
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * SST_WCFM constructor.
	 */
	private function __construct() {
		// Bail if WCFM is not installed and activated.
		if ( ! defined( 'WCFM_VERSION' ) ) {
			return;
		}

		// Allow developers to disable this integration.
		if ( ! apply_filters( 'wootax_wcfm_integration_enabled', true ) ) {
			return;
		}

		// Print an error if the Dokan version is not supported.
		if ( version_compare( WCFM_VERSION, $this->min_version, '<' ) ) {
			add_action( 'admin_notices', array( $this, 'wcfm_version_notice' ) );
			return;
		}

		// Hide the default WCFM tax fields.
		add_filter( 'wcfm_product_simple_fields_tax', '__return_empty_array' );
		add_action( 'wcfm_products_manage_tax_end', array( $this, 'output_tic_select' ) );
		add_filter( 'wcfm_variation_edit_data', array( $this, 'add_tic_to_variation_edit_data' ), 10, 3 );
		add_filter( 'wcfm_product_manage_fields_variations', array( $this, 'filter_variation_form_fields' ), 20, 2 );
		add_filter( 'wootax_tic_select_init_events', array( $this, 'filter_tic_select_init_events' ) );
		add_action( 'after_wcfm_products_manage_meta_save', array( $this, 'save_product_tic' ), 10, 2 );
		add_action( 'after_wcfm_product_variation_meta_save', array( $this, 'save_variation_tic' ), 10, 3 );
		add_filter( 'wootax_marketplace_is_user_seller', array( $this, 'is_user_seller' ) );
		add_action( 'after_wcfm_orders_edit', array( $this, 'recalculate_order_taxes' ), 10, 2 );
		add_filter( 'wootax_order_packages_before_split', array( $this, 'filter_wootax_order_packages' ), 10, 2 );

		parent::__construct();
	}

	/**
	 * Admin notice displayed when an unsupported version of WCFM is detected.
	 */
	public function wcfm_version_notice() {
		?>
		<div class="notice notice-error">
			<p>
				<?php
				printf(
					/* translators: minimum supported WCFM version */
					esc_html__(
						'Simple Sales Tax does not support the installed version of WCFM. WCFM %s+ is required.',
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
	 * Adds the variation TIC to the variation data for the product edit form.
	 *
	 * @param array $variations    Variation data.
	 * @param int   $variation_id  Variation post ID.
	 * @param int   $variation_key Variation key.
	 *
	 * @return array
	 */
	public function add_tic_to_variation_edit_data( $variations, $variation_id, $variation_key ) {
		$variations[ $variation_key ]['wootax_tic'] = get_post_meta( $variation_id, 'wootax_tic', true );

		return $variations;
	}

	/**
	 * Registers the TIC field for variations and removes the Tax Class field.
	 *
	 * @param array $variation_fields Registered WCFM variation form fields.
	 * @param array $variations       Variations.
	 *
	 * @return array
	 */
	public function filter_variation_form_fields( $variation_fields, $variations ) {
		unset( $variation_fields['tax_class'] );

		$select_args = array(
			'button_class' => 'wcfm_submit_button',
			'field_class'  => 'multi_input_block_element',
			'default_text' => __( 'Same as parent', 'simple-sales-tax' ),
		);

		ob_start();
		sst_output_tic_select_field( $select_args );
		$select_html = ob_get_clean();

		$variation_fields['wootax_tic'] = array(
			'label'       => __( 'Taxability Information Code', 'simple-sales-tax' ),
			'hints'       => sst_get_tic_select_help_text(),
			'type'        => 'html',
			'class'       => 'wcfm-html wcfm_ele wcfm-sst-html variable variable-subscription pw-gift-card',
			'label_class' => 'wcfm_title html_title',
			'value'       => $select_html,
		);

		// Load script to set values and names for variation TIC inputs on load.
		wp_enqueue_script( 'sst-wcfm-tic-select' );
		$tic_select_data = array(
			'variation_tics' => wp_list_pluck( $variations, 'wootax_tic' ),
		);
		wp_localize_script( 'sst-wcfm-tic-select', 'wcfm_tic_select_data', $tic_select_data );

		return $variation_fields;
	}

	/**
	 * Outputs the TIC select field for a product.
	 *
	 * @param int $product_id ID of the product being edited.
	 */
	public function output_tic_select( $product_id ) {
		global $WCFM;

		$select_args = array(
			'button_class' => 'wcfm_submit_button',
			'product_id'   => $product_id,
		);

		if ( isset( $_REQUEST['wootax_tic'][ $product_id ] ) ) {
			$select_args['value'] = sanitize_text_field(
				$_REQUEST['wootax_tic'][ $product_id ]
			);
		}

		ob_start();
		sst_output_tic_select_field( $select_args );
		$select_html = ob_get_clean();

		$field_args = array(
			'label'       => __( 'Taxability Information Code', 'simple-sales-tax' ),
			'hints'       => sst_get_tic_select_help_text(),
			'type'        => 'html',
			'class'       => 'wcfm-html wcfm-sst-html',
			'label_class' => 'wcfm_title html_title',
			'value'       => $select_html,
		);

		$WCFM->wcfm_fields->wcfm_generate_form_field( array( 'wootax_tic' => $field_args ) );
	}

	/**
	 * Checks whether a user is a seller.
	 *
	 * @param int $user_id User ID.
	 *
	 * @return bool
	 */
	public function is_user_seller( $user_id ) {
		return wcfm_is_vendor( $user_id );
	}

	/**
	 * Adds wcfm_variation_added to the list of JS events that trigger TIC
	 * select initialization.
	 *
	 * @param string $events JS events that trigger TIC select initialization.
	 *
	 * @return string
	 */
	public function filter_tic_select_init_events( $events ) {
		return trim( "{$events} wcfm_variation_added" );
	}

	/**
	 * Saves the TIC for a product.
	 *
	 * @param int   $product_id ID of product being saved.
	 * @param array $form_data  Product form data.
	 */
	public function save_product_tic( $product_id, $form_data ) {
		if ( isset( $form_data['wootax_tic'] ) ) {
			$_REQUEST['wootax_tic'] = $form_data['wootax_tic'];
		}

		$this->save_tic( $product_id );
	}

	/**
	 * Saves the TIC for a variation.
	 *
	 * @param int   $product_id     ID of product being saved.
	 * @param int   $variation_id   Variation ID.
	 * @param array $variation_data Variation data.
	 *
	 * @return array
	 */
	public function save_variation_tic( $product_id, $variation_id, $variation_data ) {
		if ( isset( $variation_data['wootax_tic'] ) ) {
			$_REQUEST['wootax_tic'][ $variation_id ] = $variation_data['wootax_tic'];
		}

		$this->save_tic( $variation_id );
	}

	/**
	 * Recalculates the tax for an order after it's edited by the admin or a vendor.
	 *
	 * @param int      $order_id Order ID.
	 * @param WC_Order $order    Order object.
	 */
	public function recalculate_order_taxes( $order_id, $order ) {
		sst_order_calculate_taxes( $order );
	}

	/**
	 * Splits the SST order packages by seller ID.
	 * Assumes no fees in the order.
	 *
	 * @param array    $packages SST order packages.
	 * @param WC_Order $order    WooCommerce order object.
	 *
	 * @return array
	 */
	public function filter_wootax_order_packages( $packages, $order ) {
		global $WCFMmp;

		if ( empty( $WCFMmp->wcfmmp_shipping ) ) {
			return $packages;
		}

		$vendor_shipping = $WCFMmp->wcfmmp_shipping->get_order_vendor_shipping( $order );

		if ( empty( $vendor_shipping ) ) {
			return $packages;
		}

		$raw_packages = array();
		$order_items  = sst_format_order_items( $order->get_items() );

		foreach ( $vendor_shipping as $vendor_id => $shipping_info ) {
			$vendor_items = array();

			foreach ( $order_items as $key => $item ) {
				if ( (int) $vendor_id === (int) wcfm_get_vendor_id_by_post( $item['product_id'] ) ) {
					$vendor_items[ $key ] = $item;
				}
			}

			if ( empty( $vendor_items ) ) {
				continue;
			}

			$shipping_item_id = $shipping_info['shipping_item_id'];
			$shipping_item    = new WC_Order_Item_Shipping( $shipping_item_id );

			// Create base package with vendor's items.
			$package = sst_create_package(
				array(
					'contents' => $vendor_items,
					'shipping' => new WC_Shipping_Rate(
						$shipping_item_id,
						'',
						$shipping_item->get_total(),
						array(),
						$shipping_item->get_method_id()
					),
					'user'     => array(
						'ID' => $order->get_user_id(),
					),
				)
			);

			// Set destination based on shipping method.
			if ( SST_Shipping::is_local_pickup( array( $package['shipping']->method_id ) ) ) {
				$pickup_address = apply_filters(
					'wootax_pickup_address',
					SST_Addresses::get_default_address(),
					$order
				);

				$package['destination'] = array(
					'country'   => 'US',
					'address'   => $pickup_address->getAddress1(),
					'address_2' => $pickup_address->getAddress2(),
					'city'      => $pickup_address->getCity(),
					'state'     => $pickup_address->getState(),
					'postcode'  => $pickup_address->getZip5(),
				);
			} else {
				$package['destination'] = sst_get_order_shipping_address( $order );
			}

			$raw_packages[] = $package;
		}

		// todo: move this?
		if ( ! has_filter( 'wootax_marketplace_should_split_packages', '__return_true' ) ) {
			add_filter( 'wootax_marketplace_should_split_packages', '__return_true' );
		}

    	return $packages;
	}

}

SST_WCFM::instance();
