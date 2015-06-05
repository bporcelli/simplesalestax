<?php

/**
 * Contains all methods for actions performed within the WordPress admin panel
 *
 * @package WooCommerce TaxCloud
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
		add_action( 'woocommerce_product_quick_edit_end', array( __CLASS__, 'add_quick_edit_field' ) );
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

		// JavaScript for TIC selector
		wp_enqueue_script( 'jquery-tic', WT_PLUGIN_DIR_URL .'js/jquery-tic.js', array( 'jquery', 'wootax-admin' ) );

		// WooTax admin CSS
		wp_enqueue_style( 'wootax-admin-style', WT_PLUGIN_DIR_URL .'css/admin.css' );
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
		// TIC assignment box
		add_meta_box( 'tic_meta', 'Taxibility Information Code (TIC)', array( __CLASS__, 'output_tic_metabox' ), 'product' );
		
		// Shipping Origin Addresses select box: Only show this when number of registered business addresses > 1
		$addresses = fetch_business_addresses();
		if ( is_array( $addresses ) && count( $addresses ) > 1 ) {
			add_meta_box( 'shipping_meta', 'Shipping Origin Addresses', array( __CLASS__, 'output_shipping_metabox' ), 'product', 'side', 'high' );
		}

		// Order metaboxes
		add_meta_box( 'sales_tax_meta', 'WooTax', array( __CLASS__, 'output_tax_metabox' ), 'shop_order', 'side', 'high' );
	}
	
	/**
	 * Builds HTML for TIC metabox
	 *
	 * @since 4.2
	 * @param (WP_Post) $post a WP_Post object
	 */
	public static function output_tic_metabox( $post ) {
		$description = get_post_meta( $post->ID, 'wootax_tic_desc', true );
		$tic         = get_post_meta( $post->ID, 'wootax_tic', true );
		
		?>
        <p>Here you can select the Taxability Information Code (TIC) for this product. Try to select the category which corresponds best to the product or service you are selling. If none of the given categories apply, select "General Goods and Services." For a 
        more detailed description of the TICs and the items they apply to, look <a href="https://taxcloud.net/tic/default.aspx" target="_blank">here</a>.<p />
        <p><strong>Current TIC:</strong> <span><?php echo ( $tic == '' ? 'Using Site Default' : $tic ) . ( ($description != false && !empty( $description ) ) ? ' ('. $description .')' : '' ) . ( $tic != '' && $tic != false ? ' <a href="#" id="wootax-remove-tic">(Reset TIC)</a>' : '' ); ?></span></p>
        
        <input type="text" name="wootax_set_tic" id="wootax_set_tic" value="" />
        <input type="hidden" name="wootax_tic_desc" value="<?php echo $description; ?>" />
        <input type="hidden" name="wootax_tic" value="<?php echo $tic; ?>" />
        <?php
	}
	
	
	/**
	 * Save TIC and Shipping Origin Addresses when product is saved
	 * As of 4.5, TIC will be set to first category default if not set
	 *
	 * @since 4.2
	 * @param (int) $post_id the ID of the post/product being saved
	 */
	public static function save_product_meta( $product_id ) {
		if ( get_post_type( $product_id ) != 'product' )  {
			return;
		}

		if ( isset( $_POST['wootax_set_tic'] ) && $_POST['wootax_set_tic'] != '[ - Select - ]' ) {
			// Set TIC according to user selection
			update_post_meta( $product_id, 'wootax_tic', $_POST['wootax_set_tic'] );
			
			if ( isset( $_POST['wootax_tic_desc'] ) ) {
				update_post_meta( $product_id, 'wootax_tic_desc', trim( $_POST['wootax_tic_desc'] ) );
			}
		} else if ( isset( $_POST['wootax_tic'] ) && !empty( $_POST['wootax_tic'] ) ) {
			// TIC has already been set; do nothing
		} else {
			// Attempt to set TIC according to first category default
			$cats = isset( $_POST['tax_input']['product_cat'] ) ? $_POST['tax_input']['product_cat'] : array();

			if ( count( $cats ) > 0 ) {
				$default = array(
					'tic'  => '',
					'desc' => '',
				);

				foreach ( $cats as $term_id ) {
					$temp_def = get_option( 'tic_'. $term_id );
					if ( $temp_def ) {
						$default = $temp_def;
						break;
					}
				}

				update_post_meta( $product_id, 'wootax_tic', $default['tic'] );
				update_post_meta( $product_id, 'wootax_tic_desc', trim( $default['desc'] ) );
			}
		}

		if ( isset( $_POST['_wootax_origin_addresses'] ) ) {
			update_post_meta( $product_id, '_wootax_origin_addresses', $_POST['_wootax_origin_addresses'] );
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
	 * Output origin address select metabox
	 *
	 * @since 4.2
	 * @param (WP_Post) $post post/product being edited
	 */
	public static function output_shipping_metabox( $post ) {
		$addresses = fetch_business_addresses();

		echo '<p>Use the box below to search for and add "Shipping Origin Addresses" for this product. These are the locations from which this
		item will be shipped.</p>';

		echo '<p>When an item can be shipped from multiple locations, WooTax will assume that it is sent from the business location in the customer\'s state.</p>';

		// Fetch addresses for this product
		$origin_addresses = fetch_product_origin_addresses( $post->ID );

		// Output addresses
		echo '<select class="'. ( version_compare( WOOCOMMERCE_VERSION, '2.3', '<' ) ? 'chosen_select' : 'wc-enhanced-select' ) .'" name="_wootax_origin_addresses[]" multiple>';

		if ( is_array( $addresses ) && count( $addresses ) > 0 ) {
			foreach ( $addresses as $key => $address ) {
				echo '<option value="'. $key .'"'. ( in_array( $key, $origin_addresses ) ? " selected" : "") .'>'. get_formatted_address( $address ) .'</option>';
			}
		} else {
			echo '<option value="">There are no addresses to select.</option>';
		}

		echo '</select>';
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
		?>
		<div class="form-field">
			<label>TIC (Taxability Information Code)</label>
			<input type="text" name="wootax_set_tic" id="wootax_set_tic" value="" />
	        <input type="hidden" name="wootax_tic_desc" value="" />
	        <p class="description">This TIC will be assigned to all products added to this category.</p>
		</div>
		<script type="text/javascript">
			window.initializeSelect();
		</script>
		<?php
	}

	/**
	 * Add field for TIC to Product Category "Edit" screen
	 *
	 * @param (Object) $term - WP Term object
	 * @since 4.5
	 */
	public static function custom_edit_cat_field( $term ) {
		$tic_data = get_option( 'tic_'. $term->term_id );
		$tic_desc = is_array( $tic_data ) ? $tic_data['desc'] : '';
		$tic      = is_array( $tic_data ) ? $tic_data['tic'] : '';

		?>
		<tr class="form-field">
			<th>TIC (Taxability Information Code)</th>
			<td>
				<p><strong>Current TIC: </strong><?php echo !empty( $tic ) ? $tic . " ($tic_desc)" : "Using site default"; ?></p>
				<div class="form-field">
					<input type="text" name="wootax_set_tic" id="wootax_set_tic" value="<?php echo $tic; ?>" />
			        <input type="hidden" name="wootax_tic_desc" value="<?php echo $tic_desc; ?>" />
			        <p class="description">This TIC will be assigned to all products in this category.</p>
				</div>
				<script type="text/javascript">
					window.initializeSelect();
				</script>
			</td>
		</tr>
		<?php
	}

	/**
	 * Save TIC field when Product Category is created or edited
	 *
	 * @param (int) $term_id - ID of term being saved
	 * @since 4.5
	 */
	public static function save_cat_tic( $term_id ) {
		if ( isset( $_POST['wootax_set_tic'] ) && !in_array( $_POST['wootax_set_tic'], array( '', '[ - Select - ]' ) ) ) {
			$new_tic = array(
				'tic'  => sanitize_text_field( $_POST['wootax_set_tic'] ),
				'desc' => sanitize_text_field( $_POST['wootax_tic_desc'] ), 
			);

			// Compare new TIC to old TIC; only update products if they are not equal
			$old_tic = get_option( 'tic_'. $term_id );

			if ( !is_array( $old_tic ) || $old_tic['tic'] != $new_tic['tic'] ) {
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

						update_post_meta( $products->post->ID, 'wootax_tic', $new_tic['tic'] );
						update_post_meta( $products->post->ID, 'wootax_tic_desc', $new_tic['desc'] );
					}
				}
			}

			// Store new TIC
			update_option( 'tic_' . $term_id, $new_tic );
		}
	}

	/**
	 * Add hidden wootax_tic field to Quick Edit screen. This is necessary to prevent overriding of TIC when product
	 * is in category with default TIC
	 *
	 * @since 4.5
	 */
	public static function add_quick_edit_field() {
		echo "<input type='hidden' name='wootax_tic' value='DO_NOT_CHANGE' />";
	}
}

WC_WooTax_Admin::init();