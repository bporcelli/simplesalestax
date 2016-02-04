<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; 
}

/**
 * WooCommerce Integration for WooTax
 *
 * @package WooTax
 */
 
if ( ! class_exists( 'WC_WooTax_Settings' ) ) :
 
class WC_WooTax_Settings extends WC_Integration { 
	/**
	 * Init and hook in the integration.
	 */
	public function __construct() {
		global $woocommerce;
 
		$this->id                 = 'wootax';
		$this->method_title       = __( 'WooTax', 'woocommerce-wootax' );
		$this->method_description = __( '<p>WooTax makes sales tax easy by connecting your store with <a href="https://taxcloud.net" target="_blank">TaxCloud</a>. If you have trouble with WooTax, please consult the <a href="https://wootax.com/#faq" target="_blank">FAQ</a> and the <a href="https://wootax.com/installation-guide/" target="_blank">Installation Guide</a> before contacting support.</p><p>Need help? <a href="http://wootax.com/contact-us/" target="_blank">Contact us</a>.</p>', 'woocommerce-wootax' );
 
		// Load the settings.
		$this->init_form_fields();
		$this->init_settings();
 
		// Define user set variables.
		$this->tc_id               = $this->get_option( 'tc_id' );
		$this->tc_key              = $this->get_option( 'tc_key' );
		$this->usps_id             = $this->get_option( 'usps_id' );
		$this->addresses           = $this->get_option( 'addresses' );
		$this->default_address     = $this->get_option( 'default_address' );
		$this->show_exempt         = $this->get_option( 'show_exempt' );
		$this->company_name        = $this->get_option( 'company_name' );
		$this->exempt_rols         = $this->get_option( 'exempt_roles' );
		$this->show_zero_tax       = $this->get_option( 'show_zero_tax' );
		$this->exemption_text      = $this->get_option( 'exemption_text' );
		$this->tax_based_on        = $this->get_option( 'tax_based_on' );
		$this->log_requests        = $this->get_option( 'log_requests', 'yes' );
		$this->notification_email  = $this->get_option( 'notification_email' );
		$this->capture_immediately = $this->get_option( 'capture_immediately', 'no' );

		$this->hooks();
	}

	/**
	 * Hook into WordPress actions/filters
	 *
	 * @since 4.5
	 */
	private function hooks() {
		// Processing/saving settings
		add_action( 'woocommerce_update_options_integration_' .  $this->id, array( $this, 'process_admin_options' ) );
 		add_filter( 'woocommerce_settings_api_sanitized_fields_' . $this->id, array( $this, 'sanitize_settings' ) );

 		// Maybe set wootax_rates_checked to "true" on POST
 		add_action( 'admin_init', array( __CLASS__, 'maybe_update_installation_progress' ) );

 		// Download log file if requested
		add_action( 'init', array( __CLASS__, 'maybe_download_log_file' ) );

		// AJAX actions
		add_action( 'wp_ajax_wootax-verify-taxcloud', array( __CLASS__, 'verify_taxcloud_settings' ) );
		add_action( 'wp_ajax_wootax-uninstall', array( __CLASS__, 'uninstall_wootax' ) );
		add_action( 'wp_ajax_wootax-delete-rates', array( __CLASS__, 'wootax_delete_tax_rates' ) );
	}

 	/**
 	 * Generate settings page HTML
 	 *
 	 * @since 4.5
 	 */
 	public function generate_settings_html( $form_fields = array() ) {
 		$rates_checked = get_option( 'wootax_rates_checked' );

		if ( !$rates_checked && wt_has_other_rates() ) {
			require WT_PLUGIN_PATH .'templates/admin/delete-rates.php';

			// Add WooTax tax rate if one is not present
			WC_WooTax::maybe_add_wootax_rate();
		} else {
			parent::generate_settings_html( $form_fields );
		}
 	}

	/**
	 * Initialize integration settings form fields.
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'taxcloud_settings' => array(
				'title'             => 'TaxCloud Settings',
				'type'              => 'section',
				'description' 		=> __( 'You must enter a valid TaxCloud API ID and API Key for WooTax to work properly. Use the "Verify Settings" button to test your settings.', 'woocommerce-wootax' )
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
			'usps_settings' => array(
				'title' 			=> 'USPS Settings',
				'type'              => 'section',
				'description'       => __( 'A USPS Web Tools ID is required for verifying customer addresses. If you do not have an ID, you can register for one <a href="https://wootax.com/usps/" target="_blank">here</a>. Your ID will be sent to you via email when your registration is complete.', 'woocommerce-wootax' )
			),
			'usps_id' => array(
				'title'             => __( 'USPS ID', 'woocommerce-wootax' ),
				'type'              => 'text',
				'description'       => __( 'Your USPS Web Tools User ID. Used for verifying customer addresses.', 'woocommerce-wootax' ),
				'desc_tip'          => true,
				'default'           => ''
			),
			'business_addresses_settings' => array(
				'title' 			=> 'Business Addresses',
				'type'              => 'section',
				'description'       => __( 'You must enter at least one business address for WooTax to work properly. <strong>Important:</strong> Any addresses you enter here should also be registered as <a href="https://wootax.com/taxcloud/locations/" target="_blank">locations</a> in TaxCloud.', 'woocommerce-wootax' )
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
				'class'             => version_compare( WOOCOMMERCE_VERSION, '2.3', '<' ) ? 'chosen_select' : 'wc-enhanced-select',
				'options'           => wootax_get_user_roles(),
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
				'description' => __( 'When selected, WooTax will log all requests sent to TaxCloud for debugging purposes.', 'woocommerce-wootax' ),
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
			'notification_email' => array(
				'title'				=> 'Error Notification Email',
				'type'				=> 'text',
				'default'			=> wootax_get_notification_email(),
				'description' 		=> __( 'If WooTax detects an error that needs attention, it will send a notification to this email address.', 'woocommerce-wootax' ),
				'desc_tip'			=> true
			),
			'uninstall_button' => array(
				'title'				=> 'Uninstall WooTax',
				'label'				=> 'Uninstall',
				'type'				=> 'button',
				'id'				=> 'wootax_uninstall',
				'description'		=> __( 'Click this button to uninstall WooTax. All of your settings will be erased.', 'woocommerce-wootax' ),
				'desc_tip'			=> true,
				'loader'            => true,
			), 
			'download_log_button' => array(
				'title'				=> 'Download Log File',
				'label'				=> 'Download Log',
				'type'				=> 'button',
				'id'				=> 'wootax_download_log',
				'description'		=> __( 'Click this button to download the WooTax log file for debugging purposes.', 'woocommerce-wootax' ),
				'desc_tip'			=> true,
			), 
		);
	}
 	
 	/**
 	 * Output section HTML
 	 */
 	public function generate_section_html( $key, $data ) {
 		ob_start();
 		?>
 		<tr valign="top">
 			<td colspan="2" style="padding-left: 0;">
 				<h4 style="margin-top: 0;"><?php echo $data['title']; ?></h4>
 				<p><?php echo $data['description']; ?></p>
 			</td>
 		</tr>
 		<?php
 		return ob_get_clean();
 	}

 	/**
 	 * Output HTML for button 
 	 */
 	public function generate_button_html( $key, $data ) {
 		$field = $this->plugin_id . $this->id . '_' . $key;

 		ob_start();
		?>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label for="<?php echo esc_attr( $field ); ?>"><?php echo wp_kses_post( $data['title'] ); ?></label>
				<?php echo $this->get_tooltip_html( $data ); ?>
			</th>
			<td class="forminp">
				<fieldset>
					<legend class="screen-reader-text"><span><?php echo wp_kses_post( $data['title'] ); ?></span></legend>
					<button class="wp-core-ui button button-secondary" type="button" id="<?php echo $data['id']; ?>"><?php echo wp_kses_post( $data['label'] ); ?></button>
					<?php 

						if ( isset( $data['loader'] ) ) {
							echo '<div id="wootax-loader"></div>';
						}

						echo $this->get_description_html( $data ); 

					?>
				</fieldset>
			</td>
		</tr>
		<?php
		return ob_get_clean();
 	}

 	/**
 	 * Output address table
 	 */
 	public function generate_address_table_html( $key, $data ) {
 		$woocommerce_path = plugin_dir_url('woocommerce/woocommerce.php');

 		ob_start();
 		?>
 		</table>
 		<table id="address_table" class="shippingrows widefat">
			<thead>
				<tr>
					<th><span>Address 1</span> <img class="help_tip" data-tip="Line 1 of your business address." src="<?php echo $woocommerce_path ; ?>/assets/images/help.png" height="16" width="16"></th>
					<th><span>Address 2</span> <img class="help_tip" data-tip="Line 2 of your business address." src="<?php echo $woocommerce_path ; ?>/assets/images/help.png" height="16" width="16"></th>
					<th><span>City</span> <img class="help_tip" data-tip="The city in which your business operates." src="<?php echo $woocommerce_path ; ?>/assets/images/help.png" height="16" width="16"></th>
					<th><span>State</span> <img class="help_tip" data-tip="The state where your business is located." src="<?php echo $woocommerce_path ; ?>/assets/images/help.png" height="16" width="16"></th>
					<th><span>ZIP Code</span> <img class="help_tip" data-tip="5 or 9-digit ZIP code of your business address." src="<?php echo $woocommerce_path ; ?>/assets/images/help.png" height="16" width="16"></th>
					<th><span>Make Default</span> <img class="help_tip" data-tip="Check this if you want an address to be used as the default 'Shipment Origin Address' for your products. If you only have one business address, it will be used as your default address automatically." src="<?php echo $woocommerce_path ; ?>/assets/images/help.png" height="16" width="16"></th>
					<th><span>Remove</span> <img class="help_tip" data-tip="Click the red X to remove a business address. Remember, at least one valid address is required for WooTax to work." src="<?php echo $woocommerce_path ; ?>/assets/images/help.png" height="16" width="16"></th> 
				</tr>
			</thead>
			<tfoot>
				<tr>
					<th colspan="7">
						<button class="wp-core-ui button-secondary add-address-row">Add Address</button>
					</th>
				</tr>
			</tfoot>
			<tbody>
			<?php
				$addresses = fetch_business_addresses();

				for($i = 0; $i < count($addresses); $i++) {
					$address = $addresses[$i];

					?>
					<tr>
						<td>
							<input type="text" name="wootax_address1[<?php echo $i; ?>]" class="wootax_address1" value="<?php echo $address['address_1']; ?>" />
						</td>
						<td>
							<input type="text" name="wootax_address2[<?php echo $i; ?>]" class="wootax_address2" value="<?php echo isset( $address['address_2'] ) ? $address['address_2'] : ''; ?>" placeholder="(Optional)" />
						</td>
						<td>
							<input type="text" name="wootax_city[<?php echo $i; ?>]" class="wootax_city" value="<?php echo $address['city']; ?>" />
						</td>
						<td>
							<select name="wootax_state[<?php echo $i; ?>]" class="wootax_state">
								<option value="">Select One</option>
								<option value="AL"<?php echo ($address['state'] == 'AL') ? ' selected' : ''; ?>>Alabama</option> 
								<option value="AK"<?php echo ($address['state'] == 'AK') ? ' selected' : ''; ?>>Alaska</option> 
								<option value="AZ"<?php echo ($address['state'] == 'AZ') ? ' selected' : ''; ?>>Arizona</option> 
								<option value="AR"<?php echo ($address['state'] == 'AR') ? ' selected' : ''; ?>>Arkansas</option> 
								<option value="CA"<?php echo ($address['state'] == 'CA') ? ' selected' : ''; ?>>California</option> 
								<option value="CO"<?php echo ($address['state'] == 'CO') ? ' selected' : ''; ?>>Colorado</option> 
								<option value="CT"<?php echo ($address['state'] == 'CT') ? ' selected' : ''; ?>>Connecticut</option> 
								<option value="DE"<?php echo ($address['state'] == 'DE') ? ' selected' : ''; ?>>Delaware</option> 
								<option value="DC"<?php echo ($address['state'] == 'DC') ? ' selected' : ''; ?>>District Of Columbia</option> 
								<option value="FL"<?php echo ($address['state'] == 'FL') ? ' selected' : ''; ?>>Florida</option> 
								<option value="GA"<?php echo ($address['state'] == 'GA') ? ' selected' : ''; ?>>Georgia</option> 
								<option value="HI"<?php echo ($address['state'] == 'HI') ? ' selected' : ''; ?>>Hawaii</option> 
								<option value="ID"<?php echo ($address['state'] == 'ID') ? ' selected' : ''; ?>>Idaho</option> 
								<option value="IL"<?php echo ($address['state'] == 'IL') ? ' selected' : ''; ?>>Illinois</option> 
								<option value="IN"<?php echo ($address['state'] == 'IN') ? ' selected' : ''; ?>>Indiana</option> 
								<option value="IA"<?php echo ($address['state'] == 'IA') ? ' selected' : ''; ?>>Iowa</option> 
								<option value="KS"<?php echo ($address['state'] == 'KS') ? ' selected' : ''; ?>>Kansas</option> 
								<option value="KY"<?php echo ($address['state'] == 'KY') ? ' selected' : ''; ?>>Kentucky</option> 
								<option value="LA"<?php echo ($address['state'] == 'LA') ? ' selected' : ''; ?>>Louisiana</option> 
								<option value="ME"<?php echo ($address['state'] == 'ME') ? ' selected' : ''; ?>>Maine</option> 
								<option value="MD"<?php echo ($address['state'] == 'MD') ? ' selected' : ''; ?>>Maryland</option> 
								<option value="MA"<?php echo ($address['state'] == 'MA') ? ' selected' : ''; ?>>Massachusetts</option> 
								<option value="MI"<?php echo ($address['state'] == 'MI') ? ' selected' : ''; ?>>Michigan</option> 
								<option value="MN"<?php echo ($address['state'] == 'MN') ? ' selected' : ''; ?>>Minnesota</option> 
								<option value="MS"<?php echo ($address['state'] == 'MS') ? ' selected' : ''; ?>>Mississippi</option> 
								<option value="MO"<?php echo ($address['state'] == 'MO') ? ' selected' : ''; ?>>Missouri</option> 
								<option value="MT"<?php echo ($address['state'] == 'MT') ? ' selected' : ''; ?>>Montana</option> 
								<option value="NE"<?php echo ($address['state'] == 'NE') ? ' selected' : ''; ?>>Nebraska</option> 
								<option value="NV"<?php echo ($address['state'] == 'NV') ? ' selected' : ''; ?>>Nevada</option> 
								<option value="NH"<?php echo ($address['state'] == 'NH') ? ' selected' : ''; ?>>New Hampshire</option> 
								<option value="NJ"<?php echo ($address['state'] == 'NJ') ? ' selected' : ''; ?>>New Jersey</option> 
								<option value="NM"<?php echo ($address['state'] == 'NM') ? ' selected' : ''; ?>>New Mexico</option> 
								<option value="NY"<?php echo ($address['state'] == 'NY') ? ' selected' : ''; ?>>New York</option> 
								<option value="NC"<?php echo ($address['state'] == 'NC') ? ' selected' : ''; ?>>North Carolina</option> 
								<option value="ND"<?php echo ($address['state'] == 'ND') ? ' selected' : ''; ?>>North Dakota</option> 
								<option value="OH"<?php echo ($address['state'] == 'OH') ? ' selected' : ''; ?>>Ohio</option> 
								<option value="OK"<?php echo ($address['state'] == 'OK') ? ' selected' : ''; ?>>Oklahoma</option> 
								<option value="OR"<?php echo ($address['state'] == 'OR') ? ' selected' : ''; ?>>Oregon</option> 
								<option value="PA"<?php echo ($address['state'] == 'PA') ? ' selected' : ''; ?>>Pennsylvania</option> 
								<option value="RI"<?php echo ($address['state'] == 'RI') ? ' selected' : ''; ?>>Rhode Island</option> 
								<option value="SC"<?php echo ($address['state'] == 'SC') ? ' selected' : ''; ?>>South Carolina</option> 
								<option value="SD"<?php echo ($address['state'] == 'SD') ? ' selected' : ''; ?>>South Dakota</option> 
								<option value="TN"<?php echo ($address['state'] == 'TN') ? ' selected' : ''; ?>>Tennessee</option> 
								<option value="TX"<?php echo ($address['state'] == 'TX') ? ' selected' : ''; ?>>Texas</option> 
								<option value="UT"<?php echo ($address['state'] == 'UT') ? ' selected' : ''; ?>>Utah</option> 
								<option value="VT"<?php echo ($address['state'] == 'VT') ? ' selected' : ''; ?>>Vermont</option> 
								<option value="VA"<?php echo ($address['state'] == 'VA') ? ' selected' : ''; ?>>Virginia</option> 
								<option value="WA"<?php echo ($address['state'] == 'WA') ? ' selected' : ''; ?>>Washington</option> 
								<option value="WV"<?php echo ($address['state'] == 'WV') ? ' selected' : ''; ?>>West Virginia</option> 
								<option value="WI"<?php echo ($address['state'] == 'WI') ? ' selected' : ''; ?>>Wisconsin</option> 
								<option value="WY"<?php echo ($address['state'] == 'WY') ? ' selected' : ''; ?>>Wyoming</option>
							</select>
						</td>
						<td>
							<input type="text" name="wootax_zip5[<?php echo $i; ?>]" class="wootax_zip5" value="<?php echo $address['zip5']; ?>" /> - <input type="text" name="wootax_zip4[<?php echo $i; ?>]" value="<?php echo $address['zip4']; ?>" placeholder="(Optional)" class="wootax_zip4" />
						</td>
						<td>
							<input type="radio" name="wootax_default_address" value="<?php echo $i; ?>"<?php echo (WC_WooTax::get_option('default_address') == $i || WC_WooTax::get_option('default_address') == '' && $i == 0) ? ' checked' : ''; ?> />
						</td>
						<td>
							<a class="remove_address<?php echo $i == 0 ? ' disabled' : ''; ?>">x</a>
						</td>
					</tr>
					<?php
				}
			?>
			</tbody>
		</table>
		<table class="form-table">
 		<?php
 		return ob_get_clean();
 	}

 	/**
 	 * Process address fields so they can be stored correctly
 	 */
 	public function sanitize_settings( $settings ) {
 		// Prevent this from running except on the main settings page
 		if ( isset( $_POST['wootax_address1'] ) ) {
	 		// Fetch all addresses and dump into array
	 		$new_addresses = array();
			$address_count = count( $_POST['wootax_address1'] );

			for( $i = 0; $i < $address_count; $i++ ) {
				$address = array(
					'address_1' => $_POST['wootax_address1'][$i],
					'address_2' => $_POST['wootax_address2'][$i],
					'country' 	=> 'United States', // hardcoded because this is the only option as of right now
					'state'		=> $_POST['wootax_state'][$i],
					'city' 		=> $_POST['wootax_city'][$i],
					'zip5'		=> $_POST['wootax_zip5'][$i],
					'zip4'		=> $_POST['wootax_zip4'][$i],
				);

				$new_addresses[] = $address;
			}

			$taxcloud_id  = trim( $_POST['woocommerce_wootax_tc_id'] );
			$taxcloud_key = trim( $_POST['woocommerce_wootax_tc_key'] );
			$usps_id      = trim( $_POST['woocommerce_wootax_usps_id'] );

			// Validate addresses using USPS Web Tools API if possible
			if ( $taxcloud_id && $taxcloud_key && $usps_id ) {
				$taxcloud = TaxCloud( $taxcloud_id, $taxcloud_key );
				
				foreach ( $new_addresses as $key => $address ) {
					$req = array(
						'uspsUserID' => $usps_id, 
						'address1'   => strtolower( $address['address_1'] ), 
						'address2'   => strtolower( $address['address_2'] ), 
						'city'       => $address['city'], 
						'state'      => $address['state'], 
						'zip5'       => $address['zip5'], 
						'zip4'       => $address['zip4'],
					);

					// Attempt to verify address 
					$response = $taxcloud->send_request( 'VerifyAddress', $req );

					if ( $response !== false ) {
						$new_address = array();

						$properties = array(
							'Address1' => 'address_1', 
							'Address2' => 'address_2',
							'City'     => 'city',
							'State'    => 'state',
							'Zip5'     => 'zip5',
							'Zip4'     => 'zip4'
						);

						foreach ( $properties as $property => $k ) {
							if ( isset( $response->$property ) ) {
								$new_address[ $k ] = $response->$property;
							}
						}

						// Reset country field
						$new_address['country'] = 'US';
						
						$new_addresses[ $key ] = $new_address;			
					} 
				}
			}

			// Set addresses option 
			$settings['addresses'] = $new_addresses;

			// Next, update the default address
			$settings['default_address'] = empty( $_POST['wootax_default_address'] ) ? 0 : $_POST['wootax_default_address'];

			// Set settings_changed flag to "true" so WooTax reloads settings array
			WC_WooTax::$settings_changed = true;
		}

		if ( !isset( $_POST['woocommerce_wootax_capture_immediately'] ) )
			$settings['capture_immediately'] = 'no'; 

		if ( !isset( $_POST['woocommerce_wootax_exempt_roles'] ) )
			$settings['exempt_roles'] = array( 'exempt-customer' );

		return $settings;
 	}

 	/**
 	 * Maybe update installation progress after rates are checked
 	 *
 	 * @since 4.5
 	 */
 	public static function maybe_update_installation_progress() {
 		if ( isset( $_POST['wt_rates_checked'] ) ) {
 			update_option( 'wootax_rates_checked', true );
 		}
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
		// TODO: Determine better way to remove WooTax tax rate
		global $wpdb;

		// Delete WooTax settings
		delete_option( 'woocommerce_wootax_settings' );

		// Delete WooTax options
		delete_option( 'wootax_license_key' );
		delete_option( 'wootax_rates_checked' );
		delete_option( 'wootax_version' );

		// Remove default TIC assignments
		$wpdb->query( "DELETE FROM {$wpdb->prefix}options WHERE option_name LIKE 'tic_%'" );

		die( json_encode( array( 'status' => 'success' ) ) );
	}
}
 
endif;