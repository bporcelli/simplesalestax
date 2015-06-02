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
	 * Initialize class
	 *
	 * @since 4.4
	 */
	public static function init() {
		self::hooks();
	}
	
	/**
	 * Hook into WordPress actions/filters
	 * 
	 * @since 4.2
	 */
	private static function hooks() {		
		// Register WooTax integration to build settings page
		add_filter( 'woocommerce_integrations', array( __CLASS__, 'add_integration' ) );

		// Add "Install WooTax" menu item
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ), 20 );

		// Download log file if requested
		add_action( 'init', array( __CLASS__, 'maybe_download_log_file' ) );

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

		// AJAX actions
		add_action( 'wp_ajax_wootax-verify-taxcloud', array( __CLASS__, 'verify_taxcloud_settings' ) );
		add_action( 'wp_ajax_wootax-verify-address', array( __CLASS__, 'verify_origin_addresses' ) );
		add_action( 'wp_ajax_wootax-uninstall', array( __CLASS__, 'uninstall_wootax' ) );
		add_action( 'wp_ajax_wootax-delete-rates', array(__CLASS__, 'wootax_delete_tax_rates') );		
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
	 * Force download of log file if $_GET['download_log'] is set
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

			if ( !file_exists( $log_path ) ) {
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
		if ( $product->id == NULL || $_REQUEST['wootax_set_tic'] == '' || $_REQUEST['wootax_set_tic'] == '[ - Select - ]' ) {
			return;
		}

		update_post_meta( $product->id, 'wootax_tic', $_REQUEST['wootax_set_tic'] );
		update_post_meta( $product->id, 'wootax_tic_desc', $_REQUEST['wootax_tic_desc'] );
	}
	
	
	/**
	 * Registers admin metaboxes
	 *
	 * @since 4.2
	 */
	public static function register_admin_metaboxes() {
		// Product metaboxes
		add_meta_box( 'tic_meta', 'Taxibility Information Code (TIC)', array( __CLASS__, 'output_tic_metabox' ), 'product' );
		add_meta_box( 'shipping_meta', 'Shipping Origin Addresses', array( __CLASS__, 'output_shipping_metabox' ), 'product', 'side', 'high' );
		
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
	 * Saves custom product meta
	 *
	 * @since 4.2
	 * @param (int) $post_id the ID of the post/product being saved
	 */
	public static function save_product_meta( $product_id ) {
		if ( get_post_type( $product_id ) != 'product' )  {
			return;
		}

		if ( isset( $_POST['wootax_set_tic'] ) && isset( $_POST['wootax_tic'] ) ) {
			update_post_meta( $product_id, 'wootax_tic', ( $_POST['wootax_set_tic'] != '[ - Select - ]' ) ? $_POST['wootax_set_tic'] : $_POST['wootax_tic'] );
		}

		if ( isset( $_POST['wootax_tic_desc'] ) ) {
			update_post_meta( $product_id, 'wootax_tic_desc', $_POST['wootax_tic_desc'] );
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
		item will be shipped. Most merchants <em><strong>will not</strong></em> need to adjust this setting.</p>';

		echo '<p>If this item can be shipped from multiple locations, WooTax will assume that it will be sent from the business location in the customer\'s 
		home state. If this is not suitable for your business needs, contact a WooTax support agent for assistance.</p>';

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
	 * Validates the user's TaxCloud API ID/API Key by sending a Ping request to the TaxCloud API
	 *
	 * @since 1.0
	 * @return (boolean) true or an error message on failure
	 */
	public static function verify_taxcloud_settings() {
		$taxcloud_id  = $_POST['wootax_tc_id'];
		$taxcloud_key = $_POST['wootax_tc_key'];

		if ( empty( $taxcloud_id ) || empty( $taxcloud_key ) ) {
			die( false );
		} else {
			$taxcloud = TaxCloud( $taxcloud_id, $taxcloud_key );
	
			// Send ping request and check for errors
			$response = $taxcloud->send_request( 'Ping' );

			if ( $response == false ) {
				die( $taxcloud->get_error_message() );
			} else {
				die( true );
			}
		}
	}

	/**
	 * Delete tax rates from specified tax classes ("rates" POST param)
	 * Ignore WooTax's own tax rate
	 *
	 * @since 3.5
	 * @return (mixed) boolean true on success; string error message on failure
	 */
	public static function wootax_delete_tax_rates() {
		global $wpdb;

		$rate_classes   = explode( ',', $_POST['rates'] );
		$wootax_rate_id = WT_RATE_ID == false ? 999999 : WT_RATE_ID;

		foreach ( $rate_classes as $rate_class ) {
			$res = $wpdb->query( $wpdb->prepare( "
				DELETE FROM
					{$wpdb->prefix}woocommerce_tax_rates 
				WHERE 
					tax_rate_class = %s
				AND
					tax_rate_id != $wootax_rate_id
				",
				( $rate_class == 'standard-rate' ? '' : $rate_class )
			) );

			if ( $res === false ) {
				die( 'There was an error while deleting your tax rates. Please try again.' );
			}
		}

		die( true );
	}

	/**
	 * Uninstall WooTax:
	 * - Remove WooTax tax rate
	 * - Delete WooTax settings
	 *
	 * @since 4.2
	 */
	public static function uninstall_wootax() {
		global $wpdb;

		// Remove WooTax tax rate
		$wootax_rate_id = WT_RATE_ID;

		if ( !empty( $wootax_rate_id ) ) {
			$wpdb->query( "DELETE FROM {$wpdb->prefix}woocommerce_tax_rates WHERE tax_rate_id = $wootax_rate_id" );
		}

		// Delete WooTax settings
		delete_option( 'woocommerce_wootax_settings' );

		// Delete WooTax options
		delete_option( 'wootax_license_key' );
		delete_option( 'wootax_rates_checked' );
		delete_option( 'wootax_rate_id' );
		delete_option( 'wootax_version' );

		// Done!
		die( json_encode( array( 'status' => 'success' ) ) );
	}

	/**
	 * Add installation page if WooTax is not installed
	 *
	 * @since 4.2
	 */
	public static function admin_menu() {
		$rates_checked = get_option( 'wootax_rates_checked' );

		if ( !$rates_checked ) {
			add_submenu_page( 'woocommerce', 'WooTax Installation', 'Install WooTax', 'manage_options', 'wootax_install', array( __CLASS__, 'render_installation_page' ) );
		}
	}

	/**
	 * Render installation page
	 *
	 * @since 4.2
	 */
	public static function render_installation_page() {
		include( WT_PLUGIN_PATH .'templates/admin/delete-rates.php' );
	}

	/**
 	 * Display rate removal table during installation
 	 */
 	public static function display_class_table() {
 		global $wpdb;

		// Get readable tax classes
		$readable_classes = array( 'Standard Rate' );
		$raw_classes = get_option( 'woocommerce_tax_classes' );
		
		if ( !empty( $raw_classes ) && $raw_classes ) {
			$readable_classes = array_map( 'trim', array_merge( $readable_classes, explode( PHP_EOL, $raw_classes ) ) );
		}

		// Get the count for each tax class
		$rate_counts = array_keys( $readable_classes );

		foreach ( $rate_counts as $key ) {
			$array_key = sanitize_title( $readable_classes[$key] );

			$count = $wpdb->get_var( $wpdb->prepare("
				SELECT COUNT(tax_rate_id) FROM
					{$wpdb->prefix}woocommerce_tax_rates 
				WHERE 
					tax_rate_class = %s
				",
				( $array_key == 'standard-rate' ? '' : $array_key )
			) );

			if ( $count != false && !empty( $count ) ) {
				$rate_counts[ $array_key ] = array( 'name' => $readable_classes[ $key ], 'count' => $count );
			} 

			unset( $rate_counts[ $key ] );
		}

		// Show message if now classes or rates are added
		if ( count( $readable_classes ) == 0 || count( $rate_counts ) == 0 ) {
			echo '<p><strong>You do not have any tax rates added. Click "Complete Installation" to complete the installation process.</strong></p>';
			return;
		} 

		include( WT_PLUGIN_PATH .'/templates/admin/rate-table-header.php' );

		foreach ( $rate_counts as $rate => $data ) {
			$GLOBALS['rate'] = $rate;
			$GLOBALS['data'] = $data;

			include( WT_PLUGIN_PATH .'/templates/admin/rate-table-row.php' );
		}
	
		echo '</tbody>';
		echo '</table>';
	}
}

WC_WooTax_Admin::init();