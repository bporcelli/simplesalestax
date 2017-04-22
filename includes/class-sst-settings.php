<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; 
}

/**
 * SST Settings.
 *
 * Contains methods for getting/setting plugin options.
 *
 * @author 	Simple Sales Tax
 * @package SST
 * @since 	5.0
 */ 
class SST_Settings { 

	/**
	 * @var string Options key.
	 * @since 5.0
	 */
	private static $options_key = 'woocommerce_wootax_settings';

	/**
	 * @var array Plugin settings.
	 * @since 5.0
	 */
	private static $settings = array();

	/**
	 * Initialize the settings array.
	 *
	 * @since 5.0
	 */
	private static function init_settings() {
		self::$settings = get_option( self::$options_key, array() );
	}

	/**
	 * Get a list of plugin options and their default values.
	 *
	 * @since 5.0
	 */
	public static function get_form_fields() {
		// TODO: ADD HIDDEN FIELD FOR DEFAULT_ADDRESS?
		return array(
			'taxcloud_settings' => array(
				'title'             => 'TaxCloud Settings',
				'type'              => 'section',
				'description' 		=> __( 'You must enter a valid TaxCloud API ID and API Key for Simple Sales Tax to work properly. Use the "Verify Settings" button to test your settings.', 'woocommerce-wootax' )
			),
			'tc_id' => array(
				'title'             => __( 'TaxCloud API ID', 'woocommerce-wootax' ),
				'type'              => 'text',
				'description'       => __( 'Your TaxCloud API ID. This can be found in your TaxCloud account on the "Websites" page.', 'woocommerce-wootax' ),
				'desc_tip'          => true,
				'default'           => ''
			),
			'tc_key' => array(
				'title'             => __( 'TaxCloud API Key', 'woocommerce-wootax' ),
				'type'              => 'text',
				'description'       => __( 'Your TaxCloud API Key. This can be found in your TaxCloud account on the "Websites" page.', 'woocommerce-wootax' ),
				'desc_tip'          => true,
				'default'           => ''
			),
			'verify_settings' => array(
				'title'             => __( 'Verify TaxCloud Settings', 'woocommerce-wootax' ),
				'label'             => __( 'Verify Settings', 'woocommerce-wootax' ),
				'type'              => 'button',
				'description'       => __( 'Use this button to verify that your site can communicate with TaxCloud.', 'woocommerce-integration-demo' ),
				'desc_tip' 			=> true,
				'default'			=> '',
				'id' 				=> 'verifySettings'
			),
			'business_addresses_settings' => array(
				'title' 			=> 'Business Addresses',
				'type'              => 'section',
				'description'       => __( 'You must enter at least one business address for Simple Sales Tax to work properly. <strong>Important:</strong> Any addresses you enter here should also be registered as <a href="https://simplesalestax.com/taxcloud/locations/" target="_blank">locations</a> in TaxCloud.', 'woocommerce-wootax' )
			),
			'addresses' => array(
				'type' => 'address_table'
			),
			'exemption_settings' => array(
				'title' 			=> 'Exemption Settings',
				'type'              => 'section',
				'description'       => __( 'If you have tax exempt customers, be sure to enable tax exemptions and enter your company name.', 'woocommerce-wootax' ),
			),
			'show_exempt' => array(
				'title' 			=> 'Enable Tax Exemptions?',
				'type' 				=> 'select',
				'options'			=> array(
					'true'  => 'Yes',
					'false' => 'No',
				),
				'default' 			=> 'false',
				'description' 		=> __( 'Set this to "Yes" if you have tax exempt customers.', 'woocommerce-wootax' ),
				'desc_tip'			=> true
			),
			'company_name' => array(
				'title'				=> 'Company Name',
				'type'				=> 'text',
				'default'			=> '',
				'description' 		=> __( 'Enter your company name as it should be displayed on exemption certificates.', 'woocommerce-wootax' ),
				'desc_tip'			=> true
			),
			'exemption_text' => array(
				'title'				=> 'Exemption Link Text',
				'type'				=> 'text',
				'default'			=> 'Click here to add or apply an exemption certificate',
				'description' 		=> __( 'This text is displayed on the link that opens the exemption management interface. Defaults to "Click here to add or apply an exemption certificate."', 'woocommerce-wootax' ),
				'desc_tip'			=> true
			),
			'exempt_roles' => array(
				'title'             => 'Exempt User Roles',
				'type'              => 'multiselect',
				'class'             => version_compare( WC_VERSION, '2.3', '<' ) ? 'chosen_select' : 'wc-enhanced-select',
				'options'           => self::get_user_roles(),
				'default'           => array( 'exempt-customer' ),
				'description'       => 'When a user with one of these roles shops on your site, WooTax will automatically find and apply the first exemption certificate associated with their account. Convenient if you have repeat exempt customers.',
				'desc_tip'          => true,
			),
			'restrict_exempt' => array(
				'title'             => 'Restrict to Exempt Roles',
				'type'              => 'select',
				'default'           => 'no',
				'description'       => 'Set this to "Yes" to restrict users aside from those specified above from seeing the exemption form during checkout.',
				'desc_tip'          => true,
				'options'           => array(
					'yes' => 'Yes',
					'no'  => 'No',
				),
			),
			'display_settings' => array(
				'title' 			=> 'Display Settings',
				'type'              => 'section',
				'description'       => __( 'Control how taxes are displayed during checkout.', 'woocommerce-wootax' )
			),
			'show_zero_tax' => array(
				'title' 			=> 'Show Zero Tax?',
				'type' 				=> 'select',
				'options'			=> array(
					'true'  => 'Yes',
					'false' => 'No',
				),
				'default' 			=> 'false',
				'description' 		=> __( 'When the sales tax due is zero, should the "Sales Tax" line be shown?', 'woocommerce-wootax' ),
				'desc_tip'			=> true
			),
			'advanced_settings' => array(
				'title' 			=> 'Advanced Settings',
				'type'              => 'section',
				'description'       => __( 'For advanced users only. Leave these settings untouched if you are not sure how to use them.', 'woocommerce-wootax' )
			),
			'log_requests' => array(
				'title' 	  => 'Log Requests',
				'type' 		  => 'checkbox',
				'label'       => ' ',
				'default'     => 'yes',
				'description' => __( 'When selected, Simple Sales Tax will log all requests sent to TaxCloud for debugging purposes.', 'woocommerce-wootax' ),
				'desc_tip'    => true
			),
			'capture_immediately' => array(
				'title' 	  => 'Capture Orders Immediately',
				'label'       => ' ',
				'type' 		  => 'checkbox',
				'default'     => 'no',
				'description' => __( 'By default, orders are marked as Captured in TaxCloud when they are shipped. Select this option to mark orders as Captured immediately after checkout. Useful for stores that have items with long lead times.', 'woocommerce-wootax' ),
				'desc_tip'    => true,
			),
			'tax_based_on' => array(
				'title' 			=> 'Tax Based On',
				'type' 				=> 'select',
				'options'			=> array(
					'item-price'    => 'Item Price',
					'line-subtotal' => 'Line Subtotal',
				),
				'default' 			=> 'item-price',
				'description' 		=> __( '"Item Price": TaxCloud determines the taxable amount for a line item by multiplying the item price by its quantity. "Line Subtotal": the taxable amount is determined by the line subtotal. Useful in instances where rounding becomes an issue.', 'woocommerce-wootax' ),
				'desc_tip'			=> true
			),
			'remove_all_data' => array(
				'title' 	  => 'Remove All Data',
				'label'       => ' ',
				'type' 		  => 'checkbox',
				'default'     => 'no',
				'description' => __( 'When this feature is enabled, all Simple Sales Tax options and data will be removed when you click deactivate and delete the plugin.', 'woocommerce-wootax' ),
				'desc_tip'    => true,
			),
			'download_log_button' => array(
				'title'				=> 'Download Log File',
				'label'				=> 'Download Log',
				'type'				=> 'anchor',
				'url'               => add_query_arg( 'download_log', true ),
				'id'				=> 'download_log_button',
				'description'		=> __( 'Click this button to download the Simple Sales Tax log file for debugging purposes.', 'woocommerce-wootax' ),
				'desc_tip'			=> true,
			), 
		);
	}

	/**
	 * get function.
	 *
	 * Gets an option from the settings API, using defaults if necessary to prevent undefined notices.
	 *
	 * @param  string $key
	 * @param  mixed  $empty_value
	 * @return string The value specified for the option or a default value for the option.
	 */
	public static function get( $key, $empty_value = null ) {
		if ( empty( self::$settings ) ) {
			self::init_settings();
		}

		// Get option default if unset.
		if ( ! isset( self::$settings[ $key ] ) ) {
			$form_fields            = self::get_form_fields();
			self::$settings[ $key ] = isset( $form_fields[ $key ] ) ? $form_fields[ $key ][ 'default' ] : '';
		}

		if ( ! is_null( $empty_value ) && '' === self::$settings[ $key ] ) {
			self::$settings[ $key ] = $empty_value;
		}

		return self::$settings[ $key ];
	}

	/**
	 * set function. Sets an option.
	 *
	 * @param string $key
	 * @param mixed  $value
	 */
	public static function set( $key, $value ) {
		if ( empty( self::$settings ) ) {
			self::init_settings();
		}
		self::$settings[ $key ] = $value;
		update_option( self::$options_key, self::$settings );
	}

	/**
	 * Get a list of user roles.
	 *
	 * @since 5.0
	 *
	 * @return array
	 */
	protected static function get_user_roles() {
		global $wp_roles;
		if ( ! isset( $wp_roles ) ) {
		    $wp_roles = new WP_Roles();
		}
		return $wp_roles->get_names();
	}
}
