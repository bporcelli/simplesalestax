<?php

/**
 * Contains all methods for actions performed within the WordPress admin panel
 *
 * @package WooTax
 * @since 4.2
 */

if ( ! defined( 'ABSPATH' ) )  {
	exit; // Prevent direct access to script
}

class WC_WooTax_Admin {
	/**
	 * Hook into WordPress actions/filters
	 *
	 * @since 4.4
	 */
	public static function init() {
		// Register WooTax integration to build settings page
		add_filter( 'woocommerce_integrations', array( __CLASS__, 'add_integration' ) );

		// Enqueue admin scripts/styles
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_scripts_and_styles' ), 20 );

		// Register WooTax meta boxes
		add_action( 'add_meta_boxes', array( __CLASS__, 'register_admin_metaboxes' ) );
		
		// Display Shipping Origin Addresses select box under "Shipping" tab in Product Data metabox
		add_action( 'woocommerce_product_options_shipping', array( __CLASS__, 'display_shipping_origin_field' ) );

		// Maybe hide "Tax Class" and "Tax Status" options
		add_filter( 'admin_body_class', array( __CLASS__, 'set_body_class' ), 10, 1 );

		// Save custom product meta
		add_action( 'save_post', array( __CLASS__, 'save_product_meta' ) );

		// Add "settings" link to plugins page
		add_filter( 'plugin_action_links_' . plugin_basename( WT_PLUGIN_PATH . '/woocommerce-wootax.php' ), array( __CLASS__, 'add_settings_link' ) );

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

	}

	/**
	 * Register WooTax WooCommerce Integration
	 *
	 * @since 4.2
	 */
	public static function add_integration( $integrations ) {
		$integrations[] = 'WC_WooTax_Settings';
		
		return $integrations;
	}
	
	/**
	 * Enqueue WooTax admin scripts
	 *
	 * @since 4.2
	 */
	public static function enqueue_scripts_and_styles() {
		// WooTax admin JS
		wp_enqueue_script( 'wootax-admin', WT_PLUGIN_DIR_URL .'js/admin.js', array( 'jquery', 'jquery-tiptip' ), '1.1' );
		
		wp_localize_script( 'wootax-admin', 'WT', array( 
			'ajaxURL'  => admin_url( 'admin-ajax.php' ),
			'rateCode' => apply_filters( 'wootax_rate_code', 'WOOTAX-RATE-DO-NOT-REMOVE' ),
		) );

		// WooTax admin CSS
		wp_enqueue_style( 'wootax-admin-style', WT_PLUGIN_DIR_URL .'css/admin.css' );

		// WooCommerce scripts and styles we need
		$assets_path = str_replace( array( 'http:', 'https:' ), '', WC()->plugin_url() ) . '/assets/';

		// Select2 on Edit Product / Settings pages
		if ( ! wp_script_is( 'select2', 'registered' ) ) {
			wp_enqueue_style( 'select2-css', '//cdnjs.cloudflare.com/ajax/libs/select2/3.5.2/select2.min.css' );
			wp_enqueue_script( 'select2-js', '//cdnjs.cloudflare.com/ajax/libs/select2/3.5.2/select2.min.js', array( 'jquery' ) );
		} else if ( ! wp_script_is( 'wc-enhanced-select', 'enqueued' ) ) {
			wp_enqueue_style( 'wc-enhanced-css', $assets_path . 'css/select2.css' );
			wp_enqueue_script( 'wc-enhanced-select' );
		}
	}
	
	/**
	 * Builds HTML for bulk TIC editor 
	 *
	 * @since 4.2
	 */
	public static function output_bulk_edit_fields() {
		?>
        <label class="alignleft">
			<span class="title">TIC</span>
			<span class="input-text-wrap">
            	<input name="wootax_set_tic" id="wootax_set_tic" value="" />
            	<input type="hidden" name="wootax_tic_desc" value="" />
           	</span>
		</label>
		<script type="text/javascript">
			window.initializeSelect();
		</script>
        <?php
	}
	
	/**
	 * Saves TIC when bulk editor is used
	 *
	 * @since 4.2
	 * @param (WC_Product) $product a WC_Product object
	 */
	public static function save_bulk_edit_fields( $product ) {
		if ( $product->id && isset( $_REQUEST['wootax_set_tic'] ) && !in_array( $_REQUEST['wootax_set_tic'], array( '', '[ - Select - ]') ) ) {
			update_post_meta( $product->id, 'wootax_tic', $_REQUEST['wootax_set_tic'] );
			update_post_meta( $product->id, 'wootax_tic_desc', trim( $_REQUEST['wootax_tic_desc'] ) );
		}
	}
	
	/**
	 * Registers admin metaboxes
	 *
	 * @since 4.2
	 */
	public static function register_admin_metaboxes() {
		// Sales Tax metabox on "Edit Order" screen
		add_meta_box( 'sales_tax_meta', 'WooTax', array( __CLASS__, 'output_tax_metabox' ), 'shop_order', 'side', 'high' );
	}
	
	/**
	 * Save TIC and Shipping Origin Addresses when product is saved
	 * As of 4.5, TIC will be set to first category default if not set
	 *
	 * @since 4.2
	 * @param (int) $post_id the ID of the post/product being saved
	 */
	public static function save_product_meta( $product_id ) {
		if ( get_post_type( $product_id ) != 'product' )
			return;

		if ( isset( $_REQUEST['_inline_edit'] ) || isset( $_REQUEST['bulk_edit'] ) && $_REQUEST['wootax_set_tic'] == '' )
			return;

		// Save product TIC
		$parent_tic = isset( $_REQUEST['bulk_edit'] ) ? $_REQUEST['wootax_set_tic'] : $_REQUEST["wootax_set_tic_{$product_id}"];

		if ( !empty( $parent_tic ) ) {
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
	 * Outputs HTML for Sales Tax metabox
	 *
	 * @since 4.2
	 * @param (WP_Post) $post WordPress post object
	 */
	public static function output_tax_metabox( $post ) {
		// Load order
		$order = WT_Orders::get_order( $post->ID );

		// Display tax totals
		?>
		<p>The status of this order in TaxCloud is displayed below. There are three possible values for the order status: "Pending Capture," "Captured," and "Refunded."</p>
		<p>Eventually, all of your orders should have a status of "Captured." To mark an order as captured, set its status to "Completed" and save it.</p>
		<p><strong>Please note that tax can only be calculated using the "Calculate Taxes" button if the status below is "Pending Capture."</strong></p>
		<p><strong>TaxCloud Status:</strong> <?php echo $order->get_status(); ?><br /></p>
        <?php
	}

	/**
	 * Output origin address select box
	 *
	 * @since 4.6
	 */
	public static function display_shipping_origin_field() {
		global $post;

		$addresses = fetch_business_addresses();

		// Do not display if there is less than 2 origin addresses to select from
		if ( !is_array( $addresses ) || count( $addresses ) <= 1 ) {
			return;
		}

		// Close "Shipping Class" options group
		echo '</div>';

		// Open Shipping Origin options group
		echo '<div class="options_group">';
		echo '<p class="form-field" id="shipping_origin_field"><label for="_wootax_origin_addresses[]">Origin addresses</label>';

		// Output select box
		$origin_addresses = fetch_product_origin_addresses( $post->ID );

		echo '<select class="'. ( version_compare( WOOCOMMERCE_VERSION, '2.3', '<' ) ? 'chosen_select' : 'wc-enhanced-select' ) .'" name="_wootax_origin_addresses[]" multiple>';

		if ( is_array( $addresses ) && count( $addresses ) > 0 ) {
			foreach ( $addresses as $key => $address ) {
				echo '<option value="'. $key .'"'. ( in_array( $key, $origin_addresses ) ? " selected" : "") .'>'. get_formatted_address( $address ) .'</option>';
			}
		} else {
			echo '<option value="">There are no addresses to select.</option>';
		}

		echo '</select>';

		// Output help tooltip
		wootax_tip(__('Used by WooTax for tax calculations. These are the addresses from which this product will be shipped.'));
	}

	/**
	 * Adds a "Taxes" tab to the WooCommerce reports page
	 *
	 * @since 4.2
	 * @param (array) $charts an array of charts to be rendered on the reports page
	 * @return (array) modified $charts array
	 */
	public static function add_reports_tax_tab( $charts ) {
		$charts['taxes'] = array(
			'title'  => __( 'Taxes', 'woocommerce-wootax' ),
			'charts' => array(
				"overview" => array(
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
	 * Link to the TaxCloud reports page from the "Taxes" tab in the reports section
	 *
	 * @since 4.2
	 */
	public static function output_tax_report_button() {
		?>
		<div id="poststuff" class="wootax-reports-page">
			<a target="_blank" href="https://taxcloud.net/res/" class="wp-core-ui button button-primary">Go to TaxCloud Reports Page</a>
		</div>
		<?php
	}

	/**
	 * Adds "settings" link to the "plugins" page
	 * 
	 * @since 4.2
	 * @param (array) $links the existing links for this plugin
	 * @return (array) a modified $links array
	 */
	public static function add_settings_link( $links ) { 
	 	$settings_link = '<a href="admin.php?page=wc-settings&tab=integration&section=wootax">Settings</a>'; 

	  	array_unshift( $links, $settings_link ); 

	  	return $links; 
	}

	/**
	 * Add field for TIC to Product Category "Add New" screen
	 *
	 * @since 4.5
	 */
	public static function custom_add_cat_field() {
		global $tic_list, $is_edit;

		$is_edit = false;
		$tic_list = self::get_tic_list();
		
		require WT_PLUGIN_PATH .'templates/admin/tic-select-cat.php';
	}

	/**
	 * Add field for TIC to Product Category "Edit" screen
	 *
	 * @param (Object) $term - WP Term object
	 * @since 4.5
	 */
	public static function custom_edit_cat_field( $term ) {
		global $current_tic, $tic_list, $is_edit;

		$current_tic = get_option( 'tic_'. $term->term_id );
		$current_tic = is_array( $current_tic ) ? $current_tic['tic'] : $current_tic;

		$tic_list = self::get_tic_list();
		$is_edit = true;

		require WT_PLUGIN_PATH .'templates/admin/tic-select-cat.php';
	}

	/**
	 * Save TIC field when Product Category is created or edited
	 *
	 * @param (int) $term_id - ID of term being saved
	 * @since 4.5
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
	 * Register our debug tool for deleting cached tax rates
	 * 
	 *
	 * @since 4.4
	 * @param (array) $tools array of debug tools
	 * @return (array) modified array of debug tools
	 * @see WC_Tax::find_rates()
	 */ 
	public static function register_tax_rate_tool( $tools ) {
		$tools['wootax_rate_tool'] = array(
			'name'		=> __( 'Delete cached tax rates',''),
			'button'	=> __( 'Clear cache','woocommerce-wootax' ),
			'desc'		=> __( 'This tool will remove any tax rates cached by WooCommerce.', '' ),
			'callback'  => array( __CLASS__, 'remove_rate_transients' ),
		);

		return $tools;
	}

	/**
	 * Delete transients holding cached tax rates
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
	 * Add "hide-tax-options" body class if "Tax Class" and "Tax Status" option should be hidden
	 *
	 * @param (array) $classes - classes to add to body element
	 * @since 4.6
	 */
	public static function set_body_class( $classes ) {
		if ( apply_filters( 'wootax_hide_tax_options', true ) === true ) {
			$classes .= ' hide-tax-options';

			$version = str_replace( '.', '-', WOOCOMMERCE_VERSION );
			$classes .= ' wc-' . substr( $version, 0, 3 );
		}

		return $classes;
	}

	/**
     * Process TIC list and convert into format usable with Select2
     *
     * @param (array) $tic_list array of TICs to output
     * @since 4.6
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
	 * Get list of TaxCloud TICs
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
	 * Output TIC select box for a product or variation
	 *
	 * @param (int) $loop - loop counter; used by WooCommerce when displaying variation attributes
	 * @param (array) $variation_data - data for variation if a variation is being displayed
	 * @param (array) $variation - variation if a variation is being displayed
	 * @since 4.6
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

		require WT_PLUGIN_PATH .'templates/admin/tic-select.php';
	}

	/**
	 * Update variation TICs when saved via AJAX
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

}

WC_WooTax_Admin::init();