<?php

// Prevent direct access to script
if ( ! defined( 'ABSPATH' ) ) exit; 

if ( is_admin() ):

// Load integration class
require( 'class-wc-wootax-settings.php' );

/**
 * WC_WooTax_Admin class
 * Contains all methods relevant to the admin interface and actions performed within the WordPress admin panel
 *
 * @package WooTax
 * @since 4.2
 */
class WC_WooTax_Admin {
	/**
	 * Class constructor
	 *
	 * @since 4.2
	 */
	public function __construct() {

		// Give access to taxcloud object
		$this->taxcloud = get_taxcloud();

		// Hook WordPress/WooCommerce
		$this->hook_wordpress();
		$this->hook_woocommerce();

	}
	
	/**
	 * Hook into WordPress
	 * 
	 * @since 4.2
	 */
	private function hook_wordpress() {
		
		// Register WooTax integration to build settings page
		add_filter( 'woocommerce_integrations', array( $this, 'add_integration' ) );

		// Update installation progress
		add_action( 'admin_init', array( $this, 'update_installation_progress' ) );

		// Enqueue admin scripts 
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ), 20 );
		
		// Enqueue admin styles
		add_action( 'admin_print_styles', array( $this, 'enqueue_admin_styles' ) );
		
		// Display warning about coupon configuration if appropriate
		add_action( 'admin_head', array( $this, 'display_coupon_warning_message' ) );
		
		// Register meta boxes
		add_action( 'add_meta_boxes', array( $this, 'register_admin_metaboxes' ) );
		
		// Save custom product meta (TIC/shipping origin addresses)
		add_action( 'save_post', array( $this, 'save_product_meta' ) );

		// Add "settings" link to plugins page
		add_filter( 'plugin_action_links_' . plugin_basename( WOOTAX_PATH . '/woocommerce-wootax.php' ), array( $this, 'add_settings_link' ) );

		// Verify TaxCloud settings via AJAX
		add_action( 'wp_ajax_wootax-verify-taxcloud', array( $this, 'verify_taxcloud_settings' ) );

		// Verify origin addressses via AJAX
		add_action( 'wp_ajax_wootax-verify-address', array( $this, 'verify_origin_addresses' ) );

		// Reset WooTax settings
		add_action( 'wp_ajax_wootax-clear-settings', array( $this, 'clear_wootax_settings' ) );

		// Deactivate license
		add_action( 'wp_ajax_wootax-deactivate-license', array( $this, 'wootax_deactivate_license' ) );

		// Delete tax rates from specified tax classes
		add_action( 'wp_ajax_wootax-delete-rates', array($this, 'wootax_delete_tax_rates') );
		
	}
	
	/**
	 * Hook into WooCommerce
	 * 
	 * @since 4.2
	 */
	private function hook_woocommerce() {

		// Allow for bulk editing of TICs
		add_action( 'woocommerce_product_bulk_edit_start', array( $this, 'bulk_edit_tic_field' ) );
		add_action( 'woocommerce_product_bulk_edit_save', array( $this, 'bulk_edit_save_tic' ) );
		
		// Add "taxes" tab on reports page
		add_action( 'woocommerce_reports_charts', array( $this, 'add_reports_tax_tab' ) );

	}

	/**
	 * Update progress of installation on init
	 *
	 * @since 4.2
	 */
	public function update_installation_progress() {

		if( isset( $_POST['wootax_license_key'] ) ) {

			$license = trim( $_POST['wootax_license_key'] );
		
			if ( !empty( $license ) ) {
				// data to send in our API request
				$api_params = array( 
					'edd_action'=> 'activate_license', 
					'license' 	=> $license, 
					'item_name' => urlencode( 'WooTax Plugin for WordPress' ), // the name of our product in EDD
					'url' 		=> home_url(),
				);
				
				// Call the custom API.
				$response = wp_remote_get( add_query_arg( $api_params, 'http://wootax.com' ), array( 'timeout' => 15, 'sslverify' => false ) );
		
				// make sure the response came back okay
				if ( is_wp_error( $response ) ) {
					return false;
				}
		
				// decode the license data
				$license_data = json_decode( wp_remote_retrieve_body( $response ) );
				
				// $license_data->license will be either "valid" or "invalid"
				if ($license_data->license == "valid") {
					update_option( 'wootax_license_key', $license );
				} else {
					update_option( 'wootax_license_key', false );

					// Display message
					wootax_add_flash_message( 'The license key you entered is invalid. Please try again.' );
				}
			} else {
				wootax_add_flash_message( 'Please enter your license key.' );
			}

		} else if ( isset( $_GET['rates'] ) ) {
			update_option( 'wootax_rates_checked', true );
		}

	}
	
	/**
	 * Enqueue WooTax admin scripts
	 *
	 * @since 4.2
	 */
	public function enqueue_admin_scripts() {

		global $post;

		// WooTax admin JS
		wp_enqueue_script( 'wootax-admin', WOOTAX_DIR_URL .'js/admin.js', array( 'jquery', 'jquery-tiptip' ), '1.0' );

		// JavaScript for TIC selector
		wp_enqueue_script( 'jquery-tic', WOOTAX_DIR_URL .'js/jquery-tic.js', array( 'jquery', 'wootax-admin' ) );

		// We need to let our admin script access some important data...
		$admin_data = array();

		// URL to send AJAX requests to
		$admin_data['ajaxURL'] = admin_url( 'admin-ajax.php' );

		// Current order ID and tax item ID (if applicable)
		if ( is_object( $post ) && get_post_type( $post->ID ) == 'shop_order' ) {

			$admin_data['orderID']   = $post->ID;
			$admin_data['taxItemID'] = (int) get_post_meta( $admin_data['orderID'], '_wootax_tax_item_id', true );

		}

		// WooCommerce version (if we are dealing with a version greater than 2.2)
		if ( version_compare( WOOCOMMERCE_VERSION, '2.2', '>=' ) ) {
			$admin_data['woo22'] = true;
		}

		wp_localize_script( 'wootax-admin', 'MyAjax', $admin_data );

	}
	
	/**
	 * Enqueue WooTax admin styles
	 * 
	 * @since 4.2
	 */
	public function enqueue_admin_styles() {
		wp_enqueue_style( 'wootax-admin-style', WOOTAX_DIR_URL .'css/admin.css' );
	}
	
	/**
	 * Display warning on coupon page if the "Apply before tax" setting is set to "No"
	 *
	 * @since 4.2
	 */
	public function display_coupon_warning_message() {

		global $post;
		
		if ( is_object( $post ) && $post->post_type == 'shop_coupon' && get_post_meta( $post->ID, 'apply_before_tax', true ) != 'yes' ) {
			wootax_add_flash_message( '<strong>WARNING:</strong> "Apply before tax" must be selected for WooTax to work properly.' );
		}

	}
	
	/**
	 * Builds HTML for bulk TIC editor 
	 *
	 * @since 4.2
	 */
	public function bulk_edit_tic_field() {

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
	 * @param $product a WC_Product object or WP_Post object
	 */
	public function bulk_edit_save_tic( $product ) {

		$id = $product->id;

		if ( $id == NULL || $_REQUEST['wootax_set_tic'] == '' ) {
			return;
		}

		update_post_meta( $id, 'wootax_tic', $_REQUEST['wootax_set_tic'] );
		update_post_meta( $id, 'wootax_tic_desc', $_REQUEST['wootax_tic_desc'] );

	}
	
	
	/**
	 * Registers admin metaboxes
	 *
	 * @since 4.2
	 */
	public function register_admin_metaboxes() {

		add_meta_box( 'tic_meta', 'Taxibility Information Code (TIC)', array( $this, 'output_tic_metabox' ), 'product' );
		
		if ( is_super_admin() ) {
			add_meta_box( 'shipping_meta', 'Shipping Origin Addresses', array( $this, 'output_shipping_metabox' ), 'product', 'side', 'high' );
		}

		add_meta_box( 'sales_tax_meta', 'WooTax', array( $this, 'output_tax_metabox' ), 'shop_order', 'side', 'high' );

	}
	
	/**
	 * Builds HTML for TIC metabox
	 *
	 * @since 4.2
	 * @param $post a WP_Post object
	 */
	public function output_tic_metabox( $post ) {

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
	 * @param $post_id - the ID of the post/product being saved
	 */
	public function save_product_meta( $product_id ) {

		if ( get_post_type($product_id) != 'product' )  {
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
	 * @param $post a WP_Post object
	 */
	public function output_tax_metabox( $post ) {

		global $WC_WooTax_Order;

		// Get wootax_order ID from shop_order post ID
		$id = $post->ID;

		// Load order
		$order = $WC_WooTax_Order;
		$order->load_order( $id );

		// Display tax totals
		?>
		<p>The status of this order in TaxCloud is displayed below. There are three possible values for the order status: "Pending Capture," "Captured," and "Refunded."</p>
		<p>Eventually, all of your orders should have a status of "Captured." To mark an order as captured, set its status to "Completed" and save it.</p>
		<p><strong><em>Please note that tax can only be recalculated using the "Calculate Taxes" button if the status below is "Pending Capture."</em></strong></p>
        <p>
        	<strong>TaxCloud Status:</strong> <?php echo $order->get_status(); ?><br />
        </p>
        <?php
		
		// Display a "calculate tax" button if the order has not been captured yet
		$captured = $order->captured;
		$refunded = $order->refunded;

		if ( !$captured && !$refunded ) {
			
			// Display special message for users of WooCommerce Subscriptions
			if ( class_exists( 'WC_Subscriptions' ) && WC_Subscriptions_Order::order_contains_subscription( $order->order ) ) {
				echo '<p><strong>Note: Recalculating taxes will only update the tax amount for the initial subscription payment. Recurring tax totals will be updated when the subscription is renewed.</strong></p>';
			}

		}

	}

	/**
	 * Output origin address select metabox
	 *
	 * @since 4.2
	 * @param $post a WP_Post object
	 */
	public function output_shipping_metabox( $post ) {

		global $current_user;

		$user_id         = isset( $post->post_author ) ? $post->post_author : $current_user->ID;
		$this->addresses = fetch_business_addresses( $user_id );

		echo '<p>Use the box below to search for and add "Shipping Origin Addresses" for this product. These are the locations from which this
		item will be shipped. Most merchants <em><strong>will not</strong></em> need to adjust this setting.</p>';

		echo '<p>If this item can be shipped from multiple locations, WooTax will assume that it will be sent from the business location in the customer\'s 
		home state. If this is not suitable for your business needs, contact a WooTax support agent for assistance.</p>';

		// Fetch addresses for this product
		$origin_addresses = fetch_product_origin_addresses( $post->ID );

		// Output addresses
		echo '<select class="chosen_select" name="_wootax_origin_addresses[]" multiple>';

		if ( is_array( $this->addresses ) && count( $this->addresses ) > 0 ) {
			foreach ( $this->addresses as $key => $address ) {
				echo '<option value="'. $key .'"'. ( in_array( $key, $origin_addresses ) ? " selected" : "") .'>'. $this->get_formatted_address( $address ) .'</option>';
			}
		} else {
			echo '<option value="">There are no addresses to select.</option>';
		}

		echo '</select>';

	}

	/**
	 * Converts Address array into a formatted address string
	 *
	 * @since 4.2
	 * @param $address an Address array
	 * @return the input address as a string
	 */
	private function get_formatted_address( $address ) {
		return $address['address_1'] .', '. $address['city'] .', '. $address['state'] .' '. $address['zip5'];
	}

	/**
	 * Adds a "Taxes" tab to the WooCommerce reports page
	 *
	 * @since 4.2
	 * @param $charts an array of charts to be rendered on the reports page
	 * @return modified $charts array
	 */
	public function add_reports_tax_tab( $charts ) {

		$charts['taxes'] = array(
			'title'  => __( 'Taxes', 'woocommerce-wootax' ),
			'charts' => array(
				"overview" => array(
					'title'       => __( 'Overview', 'woocommerce-wootax' ),
					'description' => '',
					'hide_title'  => true,
					'function'    => array( $this, 'output_tax_report_button' )
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
	public function output_tax_report_button() {

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
	 * @param $links (array) the existing links for this plugin
	 * @return a modified $links array
	 */
	public function add_settings_link( $links ) { 

	 	$settings_link = '<a href="admin.php?page=wc-settings&tab=integration&section=wootax">Settings</a>'; 

	  	array_unshift($links, $settings_link); 

	  	return $links; 

	}

	/**
	 * Validates the user's TaxCloud settings by sending a Ping request to the TaxCloud API
	 * Called via AJAX hook "verify-taxcloud-settings"
	 *
	 * @since 1.0
	 * @return boolean true or an error message on failure
	 */
	public function verify_taxcloud_settings() {

		$taxcloud = $this->taxcloud;
		$taxcloud_id = $_POST['wootax_tc_id'];
		$taxcloud_key = $_POST['wootax_tc_key'];

		if ( empty($taxcloud_id) || empty($taxcloud_key) ) {

			die(false);

		} else {

			// If the user entered their TaxCloud credentials and hasn't saved their settings yet, the $taxcloud global will not contain a TaxCloud object
			if ( !is_object( $taxcloud ) ) {
				$taxcloud = new WC_WooTax_TaxCloud( $taxcloud_id, $taxcloud_key );
			} 

			$taxcloud->setID($taxcloud_id);
			$taxcloud->setKey($taxcloud_key);

			// Send ping request and check for errors
			$res = $taxcloud->Ping();

			if ( !$taxcloud->isError( $res->PingResult ) ) {
				die(true);
			} else {
				die( $taxcloud->getErrorMessage() );
			}

		}
	}

	/**
	 * Validates each of the user's entered origin addresses
	 * 
	 * @since 1.0
	 * @return JSON object with status (success | error) and message (array of validated addresses | error message)
	 */
	function verify_origin_addresses() {

		$taxcloud = $this->taxcloud;

		// Collect TaxCloud credentials and USPS ID
		$taxcloud_id = trim( $_POST['wootax_tc_id'] );
		$taxcloud_key = trim( $_POST['wootax_tc_key'] );
		$usps_id = trim( $_POST['wootax_usps_id'] );
		$addresses = array();

		if ( !empty($taxcloud_id) && !empty($taxcloud_key) && !empty($usps_id) ) {

			// Dump address data into an array
			$address_count = count($_POST['wootax_address1']);

			for($i = 0; $i < $address_count; $i++) {
				$address = array(
					'address_1' => $_POST['wootax_address1'][$i],
					'address_2' => $_POST['wootax_address2'][$i],
					'country' 	=> 'United States', // hardcoded because this is the only option as of right now
					'state'		=> $_POST['wootax_state'][$i],
					'city' 		=> $_POST['wootax_city'][$i],
					'zip5'		=> $_POST['wootax_zip5'][$i],
					'zip4'		=> $_POST['wootax_zip4'][$i],
				);

				$addresses[] = $address;
			}

			// Before the user saves their settings, the global $taxcloud object will not be set; if we need to, initialize it here
			if ( !is_object( $taxcloud ) ) {
				$taxcloud = new WC_WooTax_TaxCloud( $taxcloud_id, $taxcloud_key );
			} else {
				$taxcloud->setID( $taxcloud_id );
				$taxcloud->setKey( $taxcloud_key );
			}

			// Loop through addresses and attempt to verify each
			foreach ($addresses as $key => $address) {

				$req = array(
					'uspsUserID' => $usps_id, 
					'Address1' => strtolower($address['address_1']), 
					'Address2' => strtolower($address['address_2']), 
					'Country' => 'US', 
					'City' => $address['city'], 
					'State' => $address['state'], 
					'Zip5' => $address['zip5'], 
					'Zip4' => $address['zip4'],
				);

				// Attempt to verify address using VerifyAddress API call
				$res = $taxcloud->VerifyAddress( $req );

				if ( !$taxcloud->isError( $res->VerifyAddressResult ) ) {
					$new_address = array();

					$properties = array(
						'Address1' => 'address_1', 
						'Address2' => 'address_2',
						'Country' => 'country',
						'City' => 'city',
						'State' => 'state',
						'Zip5' => 'zip5',
						'Zip4' => 'zip4'
					);

					foreach ($properties as $property => $k) {
						if ( isset( $res->VerifyAddressResult->$property ) ) {
							$new_address[$k] = $res->VerifyAddressResult->$property;
						}
					}

					$addresses[$key] = $new_address;			
				} 

			}

			die( json_encode( array('status' => 'success', 'message' => $addresses) ) ); 

		} else {
			die( json_encode( array('status' => 'error', 'message' => 'A valid API Login ID, API Key, and USPS ID are required.') ) );
		}

	}

	/**
	 * Reset all WooTax options
	 * Called on ajax action "wootax-clear-settings"
	 *
	 * @since 3.0
	 * @return JSON object with status (success) and blank message
	 */
	function clear_wootax_settings() {

		delete_option( 'woocommerce_wootax_settings' );

		// Return success message
		die( json_encode( array('status' => 'success', 'message' => '') ) );

	}

	/**
	 * Deactivate the user's license on this domain
	 * Called on AJAX action: wootax-deactivate-license
	 *
	 * @since 3.8
	 * @return JSON object with succcess message
	 */
	function wootax_deactivate_license() {

		$current_key = get_option( 'wootax_license_key' );

		if ( !$current_key ) {
			die();
		}

		// data to send in our API request
		$api_params = array( 
			'edd_action'=> 'deactivate_license', 
			'license' 	=> $current_key, 
			'item_name' => urlencode( 'WooTax Plugin for WordPress' ), // the name of our product in EDD
			'url'		=> home_url(),
		);
		
		// Call the custom API.
		$response = wp_remote_get( add_query_arg( $api_params, 'http://wootax.com' ), array( 'timeout' => 15, 'sslverify' => false ) );

		// make sure the response came back okay
		if ( is_wp_error( $response ) ) {
			die('There was an error while deactivating your license. Please try again.');
		}

		// Delete license option
		delete_option( 'wootax_license_key' );

		// We're good!
		die( json_encode( array('status' => 'success') ) );

	}

	/**
	 * Delete tax rates from specified tax classes ("rates" POST param)
	 * Ignore WooTax's own tax rate
	 *
	 * @since 3.5
	 * @return boolean true on success; error message on failure
	 */
	function wootax_delete_tax_rates() {

		global $wpdb;

		$rate_classes = explode( ',', $_POST['rates'] );
		$wootax_rate_id = get_option( 'wootax_rate_id' ) == false ? 999999 : get_option( 'wootax_rate_id' );

		foreach ($rate_classes as $rate_class) {

			$res = $wpdb->query( $wpdb->prepare( "
				DELETE FROM
					{$wpdb->prefix}woocommerce_tax_rates 
				WHERE 
					tax_rate_class = %s
				AND
					tax_rate_id != $wootax_rate_id
				",
				($rate_class == 'standard-rate' ? '' : $rate_class)
			) );

			if ( $res === false ) {
				die('There was an error while deleting your tax rates. Please try again.');
			}

		}

		die( true );

	}

	/**
	 * Register WooTax WooCommerce Integration
	 *
	 * @since 4.2
	 */
	public function add_integration( $integrations ) {

		$integrations[] = 'WC_WooTax_Settings';
		return $integrations;

	}
}

// Set up admin
$WC_WooTax_Admin = new WC_WooTax_Admin();

endif;