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

		// Remove default hook for tax meta box so we can customize it.
		remove_action( 'sst_output_tax_meta_box', 'sst_render_tax_meta_box' );

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
		add_filter( 'wootax_marketplace_is_user_seller', array( $this, 'is_user_seller' ) );
		add_filter( 'wootax_cart_packages_before_split', array( $this, 'filter_wootax_cart_packages' ), 10, 2 );
		add_filter( 'wootax_order_packages_before_split', array( $this, 'filter_wootax_order_packages' ), 10, 2 );
		add_action( 'sst_output_tax_meta_box', array( $this, 'output_tax_meta_box' ) );
		add_action( 'dokan_checkout_update_order_meta', array( $this, 'recalculate_sub_order_taxes' ) );
		add_filter( 'sst_should_capture_order', array( $this, 'prevent_parent_order_processing' ), 10, 2 );
		add_filter( 'sst_should_refund_order', array( $this, 'prevent_parent_order_processing' ), 10, 2 );

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
	 * Checks whether a user is a seller.
	 *
	 * @param int $user_id User ID.
	 *
	 * @return bool
	 */
	public function is_user_seller( $user_id ) {
		return dokan_is_user_seller( $user_id );
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

		foreach ( $shipping_packages as $key => $package ) {
			$seller_id = isset( $package['seller_id'] ) ? $package['seller_id'] : 0;

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
		foreach ( $order->get_shipping_methods() as $item_id => $shipping_method ) {
			$seller_id                        = $shipping_method->get_meta( 'seller_id' );
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
	 * Get the origin address for a seller.
	 *
	 * @param int $seller_id Seller user ID.
	 *
	 * @return SST_Origin_Address
	 */
	public function get_seller_address( $seller_id ) {
		$store_info = dokan_get_store_info( $seller_id );
		$address    = array(
			'country'   => '',
			'address'   => '',
			'address_2' => '',
			'city'      => '',
			'state'     => '',
			'postcode'  => '',
		);

		if ( isset( $store_info['address'] ) ) {
			$store_address = $store_info['address'];
			$address       = array(
				'country'   => isset( $store_address['country'] ) ? $store_address['country'] : '',
				'address'   => isset( $store_address['street_1'] ) ? $store_address['street_1'] : '',
				'address_2' => isset( $store_address['street_2'] ) ? $store_address['street_2'] : '',
				'city'      => isset( $store_address['city'] ) ? $store_address['city'] : '',
				'state'     => isset( $store_address['state'] ) ? $store_address['state'] : '',
				'postcode'  => isset( $store_address['zip'] ) ? $store_address['zip'] : '',
			);
		}

		try {
			return new SST_Origin_Address(
				"S-{$seller_id}",
				false,
				$address['address'],
				$address['address_2'],
				$address['city'],
				$address['state'],
				$address['postcode']
			);
		} catch ( Exception $ex ) {
			SST_Logger::add( "Error encountered while constructing origin address for seller {$seller_id}: {$ex->getMessage()}. Falling back to default store origin." );

			return SST_Addresses::get_default_address();
		}
	}

	/**
	 * Renders the Sales Tax meta box. We show the default Sales Tax meta box
	 * for sub-orders and a warning message for parent orders.
	 *
	 * @param WP_Post $post The post being edited.
	 */
	public function output_tax_meta_box( $post ) {
		$has_sub_orders = get_post_meta( $post->ID, 'has_sub_order', true );

		if ( $has_sub_orders ) {
			echo '<p>';

			esc_html_e(
				'This order has sub-orders and will not be captured in TaxCloud. Please see the sub-orders for tax details.',
				'simple-sales-tax'
			);

			echo '</p>';
		} else {
			sst_render_tax_meta_box( $post );
		}
	}

	/**
	 * Recalculates the tax for a Dokan sub-order after it's created.
	 *
	 * @param int $order_id Order ID.
	 */
	public function recalculate_sub_order_taxes( $order_id ) {
		$order        = wc_get_order( $order_id );
		$is_sub_order = 'dokan' === $order->get_created_via();

		if ( $is_sub_order ) {
			sst_order_calculate_taxes( $order );
		}
	}

	/**
	 * Prevents parent orders from being captured and refunded in TaxCloud.
	 * If an order has sub-orders, only its sub-orders should be captured
	 * and refunded in TaxCloud.
	 *
	 * @param bool     $should_process Should the order be captured/refunded
	 *                                 in TaxCloud?
	 * @param WC_Order $order          WC order instance.
	 *
	 * @return bool
	 */
	public function prevent_parent_order_processing( $should_process, $order ) {
		$has_sub_orders = $order->get_meta( 'has_sub_order' );

		if ( $has_sub_orders ) {
			$should_process = false;
		}

		return $should_process;
	}

}

SST_Dokan::instance();
