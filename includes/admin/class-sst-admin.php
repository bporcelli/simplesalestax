<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Admin.
 *
 * Handles the admin interface.
 *
 * @author 	Simple Sales Tax
 * @package SST
 * @since 	4.2
 */
final class SST_Admin {

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
		
		// Display Shipping Origin Addresses select box under "Shipping" tab in Product Data metabox
		add_action( 'woocommerce_product_options_shipping', array( __CLASS__, 'display_shipping_origin_field' ) );

		// Maybe hide "Tax Class" and "Tax Status" options
		add_filter( 'admin_body_class', array( __CLASS__, 'set_body_class' ), 10, 1 );

		// Save custom product meta
		add_action( 'save_post', array( __CLASS__, 'save_product_meta' ) );

		// Allow for bulk editing of TICs
		add_action( 'woocommerce_product_bulk_edit_start', array( __CLASS__, 'output_bulk_edit_fields' ) );
		add_action( 'woocommerce_product_bulk_edit_save', array( __CLASS__, 'save_bulk_edit_fields' ) );
		
		// Add "taxes" tab on reports page
		add_action( 'woocommerce_reports_charts', array( __CLASS__, 'add_reports_tax_tab' ) );

		// Allow for TIC assignment at the category level
		add_action( 'product_cat_add_form_fields', array( __CLASS__, 'custom_add_cat_field' ) );
		add_action( 'product_cat_edit_form_fields', array( __CLASS__, 'custom_edit_cat_field' ), 10, 1 );
		add_action( 'create_product_cat', array( __CLASS__, 'save_cat_tic' ), 10, 1 );
		add_action( 'edited_product_cat', array( __CLASS__, 'save_cat_tic' ), 10, 1 );

		// Add debug tool to allow user to delete cached tax rates
		add_filter( 'woocommerce_debug_tools', array( __CLASS__, 'register_tax_rate_tool' ), 10, 1 );

		// Add "TIC" option to product General Settings tab
		add_action( 'woocommerce_product_options_tax', array( __CLASS__, 'display_tic_field' ) );

		// Add "TIC" option to variation settings tab
		add_action( 'woocommerce_product_after_variable_attributes', array( __CLASS__, 'display_tic_field' ), 10, 3 );

		// Update variation TICs via AJAX
		add_action( 'woocommerce_ajax_save_product_variations', array( __CLASS__, 'ajax_save_variation_tics' ), 10 );

		add_action( 'init', array( __CLASS__, 'maybe_download_log_file' ) );
	}

	/**
	 * Include required files.
	 *
	 * @since 5.0
	 */
	public function includes() {
		include_once 'class-sst-admin-notices.php';
		include_once 'class-sst-integration.php';
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
		wp_register_script( 'sst-backbone-modal', SST()->plugin_url() . '/assets/js/backbone-modal.js', array( 'underscore', 'backbone', 'wp-util' ), SST()->version );

		wp_register_script( 'sst-view-certificate', SST()->plugin_url() . '/assets/js/view-certificate.js', array( 'jquery', 'sst-backbone-modal' ), SST()->version );

		wp_enqueue_script( 'sst-admin', SST()->plugin_url() . '/assets/js/admin.js', array( 'jquery' ), SST()->version );
		
		wp_localize_script( 'sst-admin', 'SST', array(
			'strings' => array(
				'enter_id_and_key' => __( 'Please enter your API Login ID and API Key.', 'simplesalestax' ),
				'settings_valid'   => __( 'Success! Your TaxCloud settings are valid.', 'simplesalestax' ),
				'verify_failed'    => __( 'Connection to TaxCloud failed.', 'simplesalestax' ),
			),
		) );

		// Admin CSS
		wp_enqueue_style( 'sst-admin-style', SST()->plugin_url() . '/assets/css/admin.css' );
		wp_enqueue_style( 'sst-certificate-modal', SST()->plugin_url() . '/assets/css/certificate-modal.css' );
	}
	
	/**
	 * Output HTML for bulk TIC editor.
	 *
	 * @since 4.2
	 */
	public static function output_bulk_edit_fields() {
		$GLOBALS[ 'tic_list' ] = self::get_tic_list();
		require_once SST()->plugin_path() . '/includes/admin/views/html-tic-select-bulk.php';
	}
	
	/**
	 * Handle bulk TIC updates.
	 *
	 * @since 4.2
	 *
	 * @param WC_Product $product The product being saved.
	 */
	public static function save_bulk_edit_fields( $product ) {
		if ( ! $product->id || ! isset( $_REQUEST[ 'wootax_set_tic' ] ) ) {
			return;
		}

		$tic = sanitize_text_field( $_REQUEST[ 'wootax_set_tic' ] );

		if ( ! empty( $tic ) ) {
			update_post_meta( $product->id, 'wootax_tic', $tic == 'default' ? '' : $tic );
		}
	}
	
	/**
	 * Register "Sales Tax" metabox.
	 *
	 * @since 4.2
	 */
	public static function add_metaboxes() {
		add_meta_box( 'sales_tax_meta', 'Simple Sales Tax', array( __CLASS__, 'output_tax_metabox' ), 'shop_order', 'side', 'high' );
	}
	
	/**
	 * Save TIC and Shipping Origin Addresses when a product is saved.
	 *
	 * Since 4.5, TIC will be set to default for first product category
	 * if no TIC is selected.
	 *
	 * @since 4.2
	 *
	 * @param int $product_id The ID of the product being saved.
	 */
	public static function save_product_meta( $product_id ) {
		if ( get_post_type( $product_id ) != 'product' )
			return;

		if ( isset( $_REQUEST['_inline_edit'] ) || isset( $_REQUEST['bulk_edit'] ) && $_REQUEST['wootax_set_tic'] == '' )
			return;

		// Save product TIC
		if ( isset( $_REQUEST[ 'bulk_edit' ] ) ) {
			$parent_tic = isset( $_REQUEST[ 'wootax_set_tic' ] ) ? $_REQUEST[ 'wootax_set_tic' ] : '';
		} else {
			$parent_tic = isset( $_REQUEST[ "wootax_set_tic_{$product_id}" ] ) ? $_REQUEST[ "wootax_set_tic_{$product_id}" ] : '';
		}

		if ( ! empty( $parent_tic ) ) {
			// Set TIC according to user selection
			update_post_meta( $product_id, 'wootax_tic', $parent_tic == 'default' ? '' : $parent_tic );
		} else {
			// Attempt to set TIC to the default for the product category
			$cats = isset( $_REQUEST['tax_input']['product_cat'] ) ? $_REQUEST['tax_input']['product_cat'] : array();

			$default = false;
			if ( count( $cats ) > 0 ) {
				foreach ( $cats as $term_id ) {
					$temp_def = get_option( 'tic_'. $term_id );
					if ( $temp_def ) {
						$default = $temp_def;
						break;
					}
				}
			}

			if ( false !== $default ) {
				update_post_meta( $product_id, 'wootax_tic', $default );
			} else {
				update_post_meta( $product_id, 'wootax_tic', '' );
			}
		}

		if ( isset( $_REQUEST["wootax_set_tic_{$product_id}"] ) )
			unset( $_REQUEST["wootax_set_tic_{$product_id}"] );

		// Save variation TICs
		foreach ( $_REQUEST as $key => $value ) {
			if ( strpos( $key, 'wootax_set_tic' ) !== false ) {
				$product = str_replace( 'wootax_set_tic_', '', $key );

				// Set TIC according to user selection
				update_post_meta( $product, 'wootax_tic', $value );
			}
		}

		// Save origin addresses
		if ( isset( $_REQUEST['_wootax_origin_addresses'] ) ) {
			update_post_meta( $product_id, '_wootax_origin_addresses', $_POST['_wootax_origin_addresses'] );
		} else {
			// If we set _wootax_origin_addresses to a blank array, WooTax will assume the default origin address
			update_post_meta( $product_id, '_wootax_origin_addresses', array() );
		}
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
				$order->get_customer_id()
			);
		}

		wp_localize_script( 'sst-view-certificate', 'SSTCertData', array(
			'certificate' => $certificate,
			'seller_name' => SST_Settings::get( 'company_name' ),
			'images'      => array(
				'single_cert'  => SST()->plugin_url() . '/assets/img/sp_exemption_certificate750x600.png',
				'blanket_cert' => SST()->plugin_url() . '/assets/img/exemption_certificate750x600.png',
			),
		) );

		wp_enqueue_script( 'sst-view-certificate' );

		include SST()->plugin_path() . '/includes/admin/views/html-meta-box.php';
		include SST()->plugin_path() . '/includes/frontend/views/html-view-certificate.php';
	}

	/**
	 * Output "Shipping Origin Addresses" select box.
	 *
	 * @since 4.6
	 */
	public static function display_shipping_origin_field() {
		global $post;

		$addresses = SST_Addresses::get_origin_addresses();

		// Do not display if there is less than 2 origin addresses to select from
		if ( ! is_array( $addresses ) || count( $addresses ) <= 1 ) {
			return;
		}

		// Close "Shipping Class" options group
		echo '</div>';

		// Open Shipping Origin options group
		echo '<div class="options_group">';
		echo '<p class="form-field" id="shipping_origin_field"><label for="_wootax_origin_addresses[]">Origin addresses</label>';

		// Output select box
		$origin_addresses = SST_Product::get_origin_addresses( $post->ID );
		$selected_ids     = array_keys( $origin_addresses );

		echo '<select class="wc-enhanced-select" name="_wootax_origin_addresses[]" multiple>';

		if ( is_array( $addresses ) && count( $addresses ) > 0 ) {
			foreach ( $addresses as $id => $address ) {
				echo '<option value="'. $id .'" '. selected( in_array( $id, $selected_ids ) ) .'>'. SST_Addresses::format( $address ) .'</option>';
			}
		} else {
			echo '<option value="">There are no addresses to select.</option>';
		}

		echo '</select>';

		// Output help tooltip
		sst_tip( __( 'Used by Simple Sales Tax for tax calculations. These are the addresses from which this product will be shipped.', 'simplesalestax' ) );
	}

	/**
	 * Add a "Taxes" tab to the WooCommerce reports page.
	 *
	 * @since 4.2
	 *
	 * @param  array $charts Array of charts to be rendered on the reports page.
	 * @return array
	 */
	public static function add_reports_tax_tab( $charts ) {
		$charts[ 'taxes' ] = array(
			'title'  => __( 'Taxes', 'woocommerce-wootax' ),
			'charts' => array(
				'overview' => array(
					'title'       => __( 'Overview', 'woocommerce-wootax' ),
					'description' => '',
					'hide_title'  => true,
					'function'    => array( __CLASS__, 'output_tax_report_button' )
				),
			)
		);

		return $charts;
	}
	
	/**
	 * Link to TaxCloud "Reports" page from "Taxes" tab.
	 *
	 * @since 4.2
	 */
	public static function output_tax_report_button() {
		?>
		<div id="poststuff" class="wootax-reports-page">
			<a target="_blank" href="https://simplesalestax.com/taxcloud/reports/" class="wp-core-ui button button-primary">Go to TaxCloud Reports Page</a>
		</div>
		<?php
	}

	/**
	 * Add Default TIC field to "Add New Category" screen.
	 *
	 * @since 4.5
	 */
	public static function custom_add_cat_field() {
		global $tic_list, $is_edit;

		$is_edit = false;
		$tic_list = self::get_tic_list();
		
		require SST()->plugin_path() . '/includes/admin/views/html-tic-select-cat.php';
	}

	/**
	 * Add Default TIC field to "Edit Category" screen.
	 *
	 * @since 4.5
	 *
	 * @param WP_Term $term Term object for category being edited.
	 */
	public static function custom_edit_cat_field( $term ) {
		global $current_tic, $tic_list, $is_edit;

		$current_tic = get_option( 'tic_'. $term->term_id );
		$current_tic = is_array( $current_tic ) ? $current_tic['tic'] : $current_tic;

		$tic_list = self::get_tic_list();
		$is_edit = true;

		require SST()->plugin_path() . '/includes/admin/views/html-tic-select-cat.php';
	}

	/**
	 * Save Default TIC for category.
	 *
	 * @since 4.5
	 *
	 * @param int $term_id ID of category being saved.
	 */
	public static function save_cat_tic( $term_id ) {
		if ( isset( $_POST['wootax_set_tic'] ) ) {
			$new_tic = sanitize_text_field( $_POST['wootax_set_tic'] );

			// Compare new TIC to old TIC; only update products if they are not equal
			$old_tic = get_option( 'tic_'. $term_id );

			if ( !$old_tic || $old_tic != $new_tic ) {
				$products = new WP_Query( array( 
					'post_type' => 'product', 
					'posts_per_page' => -1,
					'tax_query' => array(
						array( 
							'taxonomy' => 'product_cat', 
							'terms'    => $term_id, 
							'field'    => 'term_id',
						),
					),
				) );

				if ( $products->have_posts() ) {
					while ( $products->have_posts() ) { 
						$products->the_post();

						update_post_meta( $products->post->ID, 'wootax_tic', $new_tic );
					}
				}
			}

			// Store new TIC
			update_option( 'tic_' . $term_id, $new_tic );
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
	 * @return array
	 */
	public static function register_tax_rate_tool( $tools ) {
		$tools[ 'wootax_rate_tool' ] = array(
			'name'		=> __( 'Delete cached tax rates',''),
			'button'	=> __( 'Clear cache','woocommerce-wootax' ),
			'desc'		=> __( 'This tool will remove any tax rates cached by WooCommerce.', '' ),
			'callback'  => array( __CLASS__, 'remove_rate_transients' ),
		);

		return $tools;
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

			$rate_transients = $wpdb->get_results( "SELECT option_name as name FROM $wpdb->options WHERE option_name LIKE '_transient_{$prefix}_%'" );

			if ( !$rate_transients ) {
				echo "<div class='updated'><p>There are no cached rates to remove.</p></div>";
				return;
			}

			foreach ( $rate_transients as $transient ) {
				$trans_key = substr( $transient->name, strpos( $transient->name, 'wc' ) );
				delete_transient( $trans_key );
			}

			echo "<div class='updated'><p>". count( $rate_transients ) ." cached tax rates removed.</p></div>";
		}
	}

	/**
	 * The presence of existing tax options like "Tax Class" and "Tax Status" can
	 * be confusing to users. Therefore, we provide a mechanism to hide these options.
	 *
	 * This method adds the class "hide-tax-options" to the body element when the tax
	 * options should be hidden.
	 *
	 * @since 4.6
	 *
	 * @param array $classes Classes for body element.
	 */
	public static function set_body_class( $classes ) {
		if ( apply_filters( 'wootax_hide_tax_options', true ) === true ) {
			$classes .= ' hide-tax-options';

			$version = str_replace( '.', '-', WC_VERSION );
			$classes .= ' wc-' . substr( $version, 0, 3 );
		}

		return $classes;
	}

	/**
	 * Process TIC list and convert into format usable with select2.
	 *
	 * @since 4.6
	 *
     * @param  array $tic_list TIC list retrieved through TaxCloud API.
	 * @return array
	 */
	public static function process_tic_list( $tic_list, $data = array() ) {
		foreach ( $tic_list as $tic ) {
			$number = $tic->tic->id;
			$ssuta  = $tic->tic->ssuta == 'true';
			$label  = $tic->tic->label . ( $ssuta ? ' (' . $number . ')' : '' );

			$new_data = array(
				'id'   => $number,
				'name' => $label,
			);

			if ( isset( $tic->tic->children ) && is_array( $tic->tic->children ) && count( $tic->tic->children ) > 0 ) {
				$new_data['children'] = self::process_tic_list( $tic->tic->children, array() );
			}

			if ( !$ssuta ) {
				$new_data['id'] = 'disallowed';  // Do not allow non-SSUTA TICs to be selected
			}

			$data[] = $new_data;
		}

		return $data;
	}

	/**
	 * Get a list of TaxCloud TICs.
	 *
	 * If we have a cached version of the TIC list, return it. Otherwise,
	 * use the TaxCloud API to fetch the list anew.
	 *
	 * @since 4.6
	 */
	private static function get_tic_list() {
		if ( false !== get_transient( 'wootax_tic_list' ) ) {
			$tic_list = get_transient( 'wootax_tic_list' );
		} else {
			$ch = curl_init( 'https://taxcloud.net/tic/?format=json' );
			curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
			$res = json_decode( curl_exec( $ch ) );
			curl_close( $ch );

			$tic_list = self::process_tic_list( $res->tic_list );

			// Cache TIC list for two weeks
			set_transient( 'wootax_tic_list', $tic_list, 2 * WEEK_IN_SECONDS );
		}

		return $tic_list;
	}

	/**
	 * Output TIC select box.
	 *
	 * @since 4.6
	 *
	 * @param int $loop Loop counter used by WooCommerce when displaying variation attributes (default: null).
	 * @param array $variation_data Unused parameter (default: null).
	 * @param WP_Post $variation WP_Post for variation if variation is being displayed (default: null).
	 */
	public static function display_tic_field( $loop = null, $variation_data = null, $variation = null ) {
		global $post, $tic_list, $current_tic, $product_id, $is_variation;

		$is_variation = !empty( $variation );

		if ( $is_variation ) {
			$product_id = $variation->ID;
		} else {
			$product_id = $post->ID;
		}

		$tic_list = self::get_tic_list();
		$current_tic = get_post_meta( $product_id, 'wootax_tic', true );

		require SST()->plugin_path() . '/includes/admin/views/html-tic-select.php';
	}

	/**
	 * Update variation TICs.
	 *
	 * @since 4.6
	 */
	public static function ajax_save_variation_tics() {
		$variable_post_id = $_POST['variable_post_id'];
		$max_loop         = max( array_keys( $_POST['variable_post_id'] ) );

		for ( $i = 0; $i <= $max_loop; $i ++ ) {
			if ( ! isset( $variable_post_id[ $i ] ) )
				continue;

			$id = $variable_post_id[ $i ];

			if ( isset( $_POST['wootax_set_tic_'. $id] ) ) {
				// Set TIC according to user selection
				update_post_meta( $id, 'wootax_tic', $_POST['wootax_set_tic_'. $id] );
			}
		}
	}

	/**
 	 * Force download log file if "Download Log" was clicked.
 	 *
 	 * @since 4.4
 	 */
	public static function maybe_download_log_file() {
		if ( isset( $_GET['download_log'] ) ) {
			// If file doesn't exist, create it
			$handle = 'wootax';

			if ( function_exists( 'wc_get_log_file_path' ) ) {
				$log_path = wc_get_log_file_path( $handle );
			} else {
				$log_path = WC()->plugin_path() . '/logs/' . $handle . '-' . sanitize_file_name( wp_hash( $handle ) ) . '.txt';
			}

			if ( ! file_exists( $log_path ) ) {
				$fh = @fopen( $log_path, 'a' );
				fclose( $fh );
			}

			// Force download
			header( 'Content-Description: File Transfer' );
		    header( 'Content-Type: application/octet-stream' );
		    header( 'Content-Disposition: attachment; filename=' . basename( $log_path ) );
		    header( 'Expires: 0' );
		    header( 'Cache-Control: must-revalidate' );
		    header( 'Pragma: public' );
		    header( 'Content-Length: ' . filesize( $log_path ) );

		    readfile( $log_path );

		    exit;
		}
	}

}

new SST_Admin();