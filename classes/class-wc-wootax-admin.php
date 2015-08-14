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
	/* WC_WooTax_Settings object */
	private static $settings;

	/**
	 * Initialize WooTax admin; hook into WP
	 *
	 * @since 4.4
	 */
	public static function init() {
		// Register WooTax menu item
		add_action( 'admin_menu', array( __CLASS__, 'register_menu_item' ) );

		// Remove "WooTax" item from top level menu
		add_action( 'admin_head', array( __CLASS__, 'remove_wootax_item' ) );

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

		// Add debug tool to allow user to delete cached tax rates
		add_filter( 'woocommerce_debug_tools', array( __CLASS__, 'register_tax_rate_tool' ), 10, 1 );

		// Add "TIC" option to product General Settings tab
		add_action( 'woocommerce_product_options_tax', array( __CLASS__, 'output_tic_setting' ) );

		// Add "TIC" option to variation settings tab
		add_action( 'woocommerce_product_after_variable_attributes', array( __CLASS__, 'output_tic_setting' ), 10, 3 );

		// Maybe hide "Tax Class" and "Tax Status" options
		add_filter( 'admin_body_class', array( __CLASS__, 'set_body_class' ), 10, 1 );

		// Update variation TICs via AJAX
		add_action( 'woocommerce_ajax_save_product_variations', array( __CLASS__, 'ajax_save_variation_tics' ), 10 );

		// Maybe update WooTax settings on POST
 		add_action( 'admin_init', array( __CLASS__, 'maybe_update_wootax_settings' ) );

 		// Download log file if requested
		add_action( 'init', array( __CLASS__, 'maybe_download_log_file' ) );

		// AJAX actions
		add_action( 'wp_ajax_wootax-verify-taxcloud', array( __CLASS__, 'verify_taxcloud_settings' ) );
		add_action( 'wp_ajax_wootax-uninstall', array( __CLASS__, 'uninstall_wootax' ) );
		add_action( 'wp_ajax_wootax-delete-rates', array( __CLASS__, 'wootax_delete_tax_rates' ) );

		// Validate "start trial" form and queue WooTax Plus installer if appropriate
		add_action( 'admin_init', array( __CLASS__, 'maybe_start_plus_trial' ) );
		add_action( 'admin_init', array( __CLASS__, 'maybe_display_plus_notice' ) );
		add_filter( 'plugins_api', array( __CLASS__, 'maybe_queue_plus_installer' ), 10, 3 );
	}

	/**
	 * Return an instance of the WooTax Settings object
	 *
	 * @since 4.6
	 */
	private static function get_settings_object() {
		if ( !isset( self::$settings ) ) {
			self::$settings = new WC_WooTax_Settings();
		}

		return self::$settings;
	}
	
	/**
	 * Register WooTax menu item
	 *
	 * @since 4.6
	 */
	public static function register_menu_item() {
		add_menu_page( 'WooTax', 'WooTax', 'manage_options', 'wootax', array( __CLASS__, 'generate_settings_html' ) );

		add_submenu_page( 'wootax', 'WooTax Settings', 'Settings', 'manage_options', 'wootax-settings', array( __CLASS__, 'generate_settings_html' ) );
		
		if ( ! self::plus_active() ) {
			add_submenu_page( 'wootax', 'Get WooTax Plus', 'WooTax Plus', 'manage_options', 'wootax-plus', array( __CLASS__, 'generate_plus_html' ) );
		}
	}

	/**
	 * Determine if WooTax Plus is active
	 *
	 * @since 4.6
	 */
	private static function plus_active() {
		return is_plugin_active( 'wootax-plus/wootax-plus.php' );
	}

	/**
	 * Determine if WooTax Plus is installed
	 *
	 * @since 4.6
	 */
	private static function plus_installed() {
		$plugins = get_plugins();
		
		foreach ( $plugins as $slug => $plugin ) {
			if ( strpos( $slug, 'wootax-plus.php' ) !== false ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Return the user's WooTax Plus ID
	 *
	 * @since 4.6
	 */
	private static function get_plus_id() {
		return get_option( 'wootax_plus_member_id' );
	}

	/**
	 * Generate HTML for "Get WooTax Plus" page
	 *
	 * @since 4.6
	 */
	public static function generate_plus_html() {
		global $trial_duration;

		$trial_duration = '30 day';

		if ( self::eligible_for_extended_trial() ) {
			$trial_duration = '6 month';
		}

		if ( self::plus_installed() ) {
			require WT_PLUGIN_PATH . 'templates/admin/plus-activate.php';
		} else if ( self::plus_trial_started() ) {
			require WT_PLUGIN_PATH . 'templates/admin/plus-install.php';
		} else if ( isset( $_REQUEST['action'] ) && $_REQUEST['action'] == 'start-trial' ) {
			require WT_PLUGIN_PATH . 'templates/admin/plus-start-trial.php';
		} else {
			require WT_PLUGIN_PATH . 'templates/admin/get-plus.php';
		}
	}

	/**
	 * Validate "start trial" form and initiate trial by acquiring a plus member ID
	 *
	 * @since 4.6
	 */
	public static function maybe_start_plus_trial() {
		if ( isset( $_REQUEST['wootax-start-trial'] ) && wp_verify_nonce( $_REQUEST['_wpnonce'], 'wootax-start-trial' ) ) {
			$first_name = isset( $_REQUEST['first_name'] ) ? sanitize_text_field( $_REQUEST['first_name'] ) : '';
			$last_name  = isset( $_REQUEST['last_name'] ) ? sanitize_text_field( $_REQUEST['last_name'] ) : '';
			$email      = isset( $_REQUEST['email'] ) ? sanitize_text_field( $_REQUEST['email'] ) : '';

			if ( ! $first_name || ! $last_name || ! $email ) {
				wootax_add_message( 'All fields are required.' );
			} else if ( ! is_email( $email ) ) {
				wootax_add_message( 'Please enter a valid email address.' );
			} else {
				try {
					$api = WT_API();

					$extended  = self::eligible_for_extended_trial();
					$member_id = $api->request_member_id( $first_name, $last_name, $email, $extended );

					update_option( 'wootax_plus_member_id', $member_id );
					set_transient( 'wootax_plus_trial', true );
				} catch ( WC_WooTax_API_Error $e ) {
					wootax_add_message( $e->getMessage() );
				}
			}
		}
	}

	/**
	 * Determine if the admin has indicated interest in a WooTax plus trial
	 *
	 * @return bool
	 * @since 4.6
	 */
	private static function plus_trial_started() {
		return get_transient( 'wootax_plus_trial' ) ? true : false;
	}

	/**
	 * Determine if the admin is eligible for an extended 6 month trial.
	 * All long time users of WooTax are eligible for up to a month after upgrading to 4.6.
	 *
	 * @return bool
	 * @since 4.6
	 */
	private static function eligible_for_extended_trial() {
		return get_transient( 'wootax_long_time_user' ) ? true : false;
	}

	/**
	 * Queue the WooTax Plus installer if the user's trial has begun and Plus is not installed. @see woothemes_updater_install method
	 *
	 * @return Object $api
	 * @since 4.6
	 */
	public static function maybe_queue_plus_installer( $api, $action, $args ) {
		if ( ! self::plus_active() && ( $member_id = self::get_plus_id() ) ) {
			$download_url = 'https://wootax.com/?wt_api=download_plugin&slug=wootax-plus&member_id='. $member_id;

			if ( 'plugin_information' != $action ||
				false !== $api ||
				! isset( $args->slug ) ||
				'wootax-plus' != $args->slug
			) return $api;

			$api = new stdClass();
			$api->name = 'WooTax Plus';
			$api->version = '1.0.0';
			$api->download_link = esc_url( $download_url );

			return $api;
		}
	}

	/**
	 * Generate installation URL for WooTax Plus plugin
	 *
	 * @return string
	 * @since 4.6
	 */
	public static function plus_installation_url() {
		return wp_nonce_url( self_admin_url( 'update.php?action=install-plugin&plugin=wootax-plus' ), 'install-plugin_wootax-plus' );
	}

	/**
	 * Generate activation URL for WooTax Plus plugin
	 *
	 * @return string
	 * @since 4.6
	 */
	public static function plus_activation_url() {
		return wp_nonce_url( self_admin_url( 'plugins.php?action=activate&plugin='. urlencode( "wootax-plus/wootax-plus.php" ) .'&plugin_status=all&paged=1&s' ), 'activate-plugin_wootax-plus/wootax-plus.php' );
	}

	/**
	 * Determine whether or not any installed plugins depend on WooTax extensions to work properly.
	 * 
	 * @return bool
	 * @since 4.6
	 */
	public static function plus_required() {
		$settings_require = $plugins_require = false;

		// Required by plugins?
		$plugins_list = get_transient( 'wootax_plus_plugins_list' );

		if ( ! $plugins_list ) {
			try {
				$api = WT_API();

				$plugins_list = $api->get_plugins_list();
				
				set_transient( 'wootax_plus_plugins_list', $plugins_list, DAY_IN_SECONDS ); // Update list once per day
			} catch ( WC_WooTax_API_Error $e ) {
				wootax_add_message( $e->getMessage() );
				return false;
			}
		}

		if ( 0 < count( $plugins_list ) ) {
			$all_plugins = array_keys( get_plugins() );

			foreach ( $plugins_list as $plugin ) {
				foreach ( $all_plugins as $slug ) {
					if ( strpos( $slug, $plugin['slug'] ) !== false && is_plugin_active( $slug ) ) {
						$plugins_require = true;
						break 2;
					}
				}
			}
		}

		// Required based on settings?
		$settings_require = WC_WooTax::get_option( 'show_exempt' ) == 'true';

		return $settings_require || $plugins_require;
	}

	/**
	 * Determine whether or not the wootax-plus page is being viewed
	 *
	 * @since 4.6
	 * @return bool
	 */
	private static function is_plus_page() {
		return isset( $_REQUEST['page'] ) && $_REQUEST['page'] == 'wootax-plus';
	}

	/**
	 * Display a notice if the user needs to install or activate the WooTax plus plugin
	 *
	 * @since 4.6
	 */
	public static function maybe_display_plus_notice() {
		$get_plus_url  = admin_url( 'admin.php?page=wootax-plus' );
		$plus_info_url = 'https://wootax.com/plus'; // TODO: UPDATE INFO URL!

		if ( ! self::is_plus_page() ) {
			if ( self::plus_required() ) {
				if ( ! self::plus_installed() ) {
					$duration = '30 day';

					if ( self::eligible_for_extended_trial() ) {
						$duration = '6 month';
					}

					$get_plus_url = add_query_arg( 'action', 'start-trial', $get_plus_url ); // Link direct to Start Trial form from this notice
					wootax_add_message( '<strong>WooTax Plus membership required.</strong></p><p>One or more premium WooTax extensions is required for your website to function properly. To access these extensions, you must become a WooTax Plus member. Click below to learn more or start your <strong>free '. $duration .' trial</strong></p><p><a href="'. $get_plus_url .'" class="wp-core-ui button-primary">Become a Member</a> <a href="'. $plus_info_url .'" target="_blank" class="wp-core-ui button-secondary" style="margin-left: .5em;">Learn More</a>', 'error' );
				} else if ( ! self::plus_active() ) {
					wootax_add_message( '<strong>WooTax Plus membership required.</strong> <a href="'. self::plus_activation_url() .'">Activate</a> the WooTax Plus plugin to enable required extensions and take full advantage of your Plus membership.', 'error' );
				}
			} else {
				if ( ! self::plus_installed() && ! self::plus_trial_started() ) {
					$expiration = '';
					$duration   = '30 days';

					if ( self::eligible_for_extended_trial() ) {
						$expiration = ' expiring ' . date( 'F jS, Y', get_transient( 'wootax_long_time_user' ) + 4 * WEEK_IN_SECONDS );
						$duration   = '6 months';
					}

					wootax_add_message( '<strong>Special offer'. $expiration .':</strong> Try WooTax Plus <strong>free</strong> for '. $duration .' and gain access to expedited support and premium WooTax extensions. Learn more <a href="'. $get_plus_url .'">here</a>.', 'updated', true, 'wootax-plus-offer', !empty( $expiration ) ? WEEK_IN_SECONDS : 4 * WEEK_IN_SECONDS );
				} else if ( ! self::plus_active() && self::plus_trial_started() ) {
					wootax_add_message( '<strong>Your WooTax Plus trial has started</strong>. <a href="'. self::plus_activation_url() .'">Activate</a> the WooTax Plus plugin to take full advantage of your Plus membership.', 'updated' );
				}
			}
		}

		if ( self::plus_active() && self::plus_installed() ) {
			wootax_remove_message( 'wootax-plus-offer' ); // Remove "Special Offer" notice; it is no longer relevant once WooTax Plus is installed
		}
	}

	/**
	 * Remove "WooTax" item from top level menu
	 *
	 * @since 4.6
	 */
	public static function remove_wootax_item() {
		global $submenu;

		if ( isset( $submenu['wootax'] ) ) {
			unset( $submenu['wootax'][0] );
		}
	}

	/**
	 * Generate HTML for the WooTax settings page. Based on @see WC_Settings_API->admin_options()
	 *
	 * @since 4.6
	 */
	public static function generate_settings_html() { 
		$settings = self::get_settings_object(); ?>

		<h3><?php echo ( ! empty( $settings->method_title ) ) ? $settings->method_title : __( 'Settings', 'woocommerce' ) ; ?></h3>

		<?php echo ( ! empty( $settings->method_description ) ) ? wpautop( $settings->method_description ) : ''; ?>

		<form action="" method="POST">
			<table class="form-table wootax-settings">
				<?php $settings->generate_settings_html(); ?>
			</table>
			<p class="submit">
				<input type="submit" name="wt-save-settings" class="wp-core-ui button-primary" value="Save changes" />
				<input type="hidden" name="_wpnonce" value="<?php echo wp_create_nonce( 'wt-save-settings' ); ?>" />
			</p>
		</form><?php
	}

	/**
 	 * Updates settings on admin_init. Takes care of marking WooTax installation as complete.
 	 *
 	 * @since 4.5
 	 */
 	public static function maybe_update_wootax_settings() {
 		$settings = self::get_settings_object();

 		if ( isset( $_REQUEST['wt-save-settings'] ) && wp_verify_nonce( $_REQUEST['_wpnonce'], 'wt-save-settings' ) ) {
 			do_action( "woocommerce_update_options_integration_{$settings->id}" );
 			add_action( 'admin_notices', array( __CLASS__, 'display_settings_notice' ) );
 		} else if ( isset( $_REQUEST['wt_rates_checked'] ) ) {
 			update_option( 'wootax_rates_checked', true );
 		}
 	}

 	/**
 	 * Display a notice indicating that the user's settings were saved
 	 *
 	 * @since 4.6
 	 */
 	public static function display_settings_notice() { ?>
 		<div class="wrap">
	 		<div class="updated">
	 			<p>Changes saved successfully.</p>
	 		</div>
	 	</div><?php
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
		$assets_path    = str_replace( array( 'http:', 'https:' ), '', WC()->plugin_url() ) . '/assets/';
		$current_screen = get_current_screen();
		$wt_screen_ids  = array( 'wootax_page_wootax-settings', 'wootax_page_wootax-plus' ); // unique ID of WooTax settings screen

		// Select2 on Edit Product / Settings pages
		if ( $current_screen->post_type == 'product' || in_array( $current_screen->id, $wt_screen_ids ) ) {
			if ( ! wp_script_is( 'select2', 'registered' ) ) {
				wp_enqueue_style( 'select2-css', '//cdnjs.cloudflare.com/ajax/libs/select2/3.5.2/select2.min.css' );
				wp_enqueue_script( 'select2-js', '//cdnjs.cloudflare.com/ajax/libs/select2/3.5.2/select2.min.js', array( 'jquery' ) );
			} else if ( ! wp_script_is( 'wc-enhanced-select', 'enqueued' ) ) {
				wp_enqueue_style( 'wc-enhanced-css', $assets_path . 'css/select2.css' );
				wp_enqueue_script( 'wc-enhanced-select' );
			}
		}

		// tipTip styles on the settings page
		if ( in_array( $current_screen->id, $wt_screen_ids ) ) {
			if ( ! wp_style_is( 'woocommerce_admin_styles', 'enqueued' ) ) {
				wp_enqueue_style( 'wootax-admin-tips', WT_PLUGIN_DIR_URL .'css/tiptip.css' );
			}
		}	
	}
	
	/**
	 * Builds HTML for bulk TIC editor 
	 *
	 * @since 4.2
	 */
	public static function output_bulk_edit_fields() {
		global $tic_list;

		$tic_list = self::get_tic_list();

		require WT_PLUGIN_PATH .'/templates/admin/tic-select-bulk.php';
	}
	
	/**
	 * Saves TIC when bulk editor is used
	 *
	 * @param (WC_Product) $product a WC_Product object
	 * @since 4.2
	 */
	public static function save_bulk_edit_fields( $product ) {
		if ( $product->id && isset( $_REQUEST['wootax_set_tic'] ) && !empty( $_REQUEST['wootax_set_tic'] ) ) {
			$new_tic = $_REQUEST['wootax_set_tic'] == 'default' ? '' : $_REQUEST['wootax_set_tic'];
			update_post_meta( $product->id, 'wootax_tic', $new_tic );
		}
	}
	
	
	/**
	 * Registers admin metaboxes
	 *
	 * @since 4.2
	 */
	public static function register_admin_metaboxes() {
		// Shipping Origin Addresses select box: Only show this when number of registered business addresses > 1
		$addresses = fetch_business_addresses();
		if ( is_array( $addresses ) && count( $addresses ) > 1 ) {
			add_meta_box( 'shipping_meta', 'Shipping Origin Addresses', array( __CLASS__, 'output_shipping_metabox' ), 'product', 'side', 'high' );
		}

		// Order metaboxes
		add_meta_box( 'sales_tax_meta', 'WooTax', array( __CLASS__, 'output_tax_metabox' ), 'shop_order', 'side', 'high' );
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
	 	$settings_link = '<a href="admin.php?page=wootax-settings">Settings</a>'; 

	  	array_unshift( $links, $settings_link ); 

	  	return $links; 
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
	public static function output_tic_setting( $loop = null, $variation_data = null, $variation = null ) {
		global $post, $tic_list, $current_tic, $product_id, $is_variation;

		$is_variation = !empty( $variation );

		if ( $is_variation ) {
			$product_id = $variation->ID;
		} else {
			$product_id = $post->ID;
		}

		$tic_list = self::get_tic_list();
		$current_tic = get_post_meta( $product_id, 'wootax_tic', true );

		require WT_PLUGIN_PATH .'/templates/admin/tic-select.php';
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

	/**
	 * Save TIC and Shipping Origin Addresses when product is saved
	 * As of 4.5, TIC will be set to first category default if not set
	 *
	 * @param (int) $post_id the ID of the post/product being saved
	 * @since 4.2
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
		}
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
		
		require WT_PLUGIN_PATH .'/templates/admin/tic-select-cat.php';
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

		require WT_PLUGIN_PATH .'/templates/admin/tic-select-cat.php';
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
	 * - Remove all default TIC options
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

		// Remove default TIC assignments
		$wpdb->query( "DELETE FROM {$wpdb->prefix}options WHERE option_name LIKE 'tic_%'" );

		die( json_encode( array( 'status' => 'success' ) ) );
	}
}

WC_WooTax_Admin::init();