<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; 
}

if ( ! class_exists( 'WC_WooTax_Settings' ) ) :

/**
 * WooTax Integration
 *
 * WooCommerce integration for WooTax.
 *
 * @author 	Simple Sales Tax
 * @package SST
 * @since 	4.5
 */ 
class WC_WooTax_Settings extends WC_Integration { 

	/**
	 * Constructor. Initialize the integration.
	 *
	 * @since 4.5
	 */
	public function __construct() {
		$this->id                 = 'wootax';
		$this->method_title       = __( 'Simple Sales Tax', 'woocommerce-wootax' );
		$this->method_description = __( '<p>Simple Sales Tax makes sales tax easy by connecting your store with <a href="https://taxcloud.net" target="_blank">TaxCloud</a>. If you have trouble with Simple Sales Tax, please consult the <a href="https://simplesalestax.com/#faq" target="_blank">FAQ</a> and the <a href="https://simplesalestax.com/installation-guide/" target="_blank">Installation Guide</a> before contacting support.</p><p>Need help? <a href="https://simplesalestax.com/contact-us/" target="_blank">Contact us</a>.</p>', 'woocommerce-wootax' );
 
		// Load the settings.
		$this->init_form_fields();
		// $this->init_settings();

		$this->hooks();
	}

	/**
	 * Register action/filter hooks.
	 *
	 * @since 4.5
	 */
	private function hooks() {
		// Processing/saving settings
		add_action( 'woocommerce_update_options_integration_' .  $this->id, array( $this, 'process_admin_options' ) );
 		add_filter( 'woocommerce_settings_api_sanitized_fields_' . $this->id, array( $this, 'sanitize_settings' ) );

 		// Download log file if requested
		add_action( 'init', array( __CLASS__, 'maybe_download_log_file' ) );

		// AJAX actions
		add_action( 'wp_ajax_wootax-verify-taxcloud', array( __CLASS__, 'verify_taxcloud_settings' ) );
		add_action( 'wp_ajax_wootax-delete-rates', array( __CLASS__, 'wootax_delete_tax_rates' ) );
	}

 	/**
 	 * Generate settings page HTML.
 	 *
 	 * @since 4.5
 	 */
 	public function generate_settings_html( $form_fields = array(), $echo = true ) {
 		$rates_checked = get_option( 'wootax_rates_checked' );

		if ( ! $rates_checked && wt_has_other_rates() ) {
			require SST()->plugin_path() . '/templates/admin/delete-rates.php';
		} else {
			if ( version_compare( SST_WOO_VERSION, '2.6', '>=' ) ) {
				echo '<table class="form-table">'; // In 2.6, settings pages not wrapped in table by default
			}
			parent::generate_settings_html( $form_fields );
		}
 	}

 	/**
 	 * Initialize form fields for integration settings.
 	 *
 	 * @since 4.5
 	 */
	public function init_form_fields() {
		$this->form_fields = array(
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
			'usps_settings' => array(
				'title' 			=> 'USPS Settings',
				'type'              => 'section',
				'description'       => __( 'A USPS Web Tools ID is required for verifying customer addresses. If you do not have an ID, you can register for one <a href="https://simplesalestax.com/usps/" target="_blank">here</a>. Your ID will be sent to you via email when your registration is complete.', 'woocommerce-wootax' )
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
				'class'             => version_compare( SST_WOO_VERSION, '2.3', '<' ) ? 'chosen_select' : 'wc-enhanced-select',
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
			'notification_email' => array(
				'title'				=> 'Error Notification Email',
				'type'				=> 'text',
				'default'			=> wootax_get_notification_email(),
				'description' 		=> __( 'If Simple Sales Tax detects an error that needs attention, it will send a notification to this email address.', 'woocommerce-wootax' ),
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
				'type'				=> 'button',
				'id'				=> 'wootax_download_log',
				'description'		=> __( 'Click this button to download the Simple Sales Tax log file for debugging purposes.', 'woocommerce-wootax' ),
				'desc_tip'			=> true,
			), 
		);
	}
 	
 	/**
 	 * Output HTML for field of type 'section.'
 	 *
 	 * @since 4.5
	 */
 	public function generate_section_html( $key, $data ) {
 		ob_start();
 		?>
 		<tr valign="top">
 			<td colspan="2" style="padding-left: 0;">
 				<h4 style="margin-top: 0;"><?php echo $data[ 'title' ]; ?></h4>
 				<p><?php echo $data[ 'description' ]; ?></p>
 			</td>
 		</tr>
 		<?php
 		return ob_get_clean();
 	}

 	/**
 	 * Output HTML for field of type 'button.'
 	 *
 	 * @since 4.5
	 */
 	public function generate_button_html( $key, $data ) {
 		$field = $this->plugin_id . $this->id . '_' . $key;

 		ob_start();
		?>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label for="<?php echo esc_attr( $field ); ?>"><?php echo wp_kses_post( $data[ 'title' ] ); ?></label>
				<?php echo $this->get_tooltip_html( $data ); ?>
			</th>
			<td class="forminp">
				<fieldset>
					<legend class="screen-reader-text"><span><?php echo wp_kses_post( $data[ 'title' ] ); ?></span></legend>
					<button class="wp-core-ui button button-secondary" type="button" id="<?php echo $data[ 'id' ]; ?>"><?php echo wp_kses_post( $data[ 'label' ] ); ?></button>
					<?php echo $this->get_description_html( $data ); ?>
				</fieldset>
			</td>
		</tr>
		<?php
		return ob_get_clean();
 	}

 	/**
 	 * Output HTML for 'address_table' field.
 	 *
 	 * @since 4.5
 	 */
 	public function generate_address_table_html( $key, $data ) {
 		ob_start();
 		?>
 		</table>
 		<table id="address_table" class="wp-list-table striped widefat">
			<thead>
				<tr>
					<th><span>Address 1</span> <?php wootax_tip( "Line 1 of your business address." ) ?> 
					<th><span>Address 2</span> <?php wootax_tip( "Line 2 of your business address." ) ?>
					<th><span>City</span> <?php wootax_tip( "The city in which your business operates." ) ?>
					<th><span>State</span> <?php wootax_tip( "The state where your business is located." ); ?>
					<th><span>ZIP Code</span> <?php wootax_tip( "5 or 9-digit ZIP code of your business address." ); ?>
					<th><span>Make Default</span> <?php wootax_tip( "Check this if you want an address to be used as the default 'Shipment Origin Address' for your products. If you only have one business address, it will be used as your default address automatically." ); ?>
					<th><span>Remove</span> <?php wootax_tip( "Click the red X to remove a business address. Remember, at least one valid address is required for Simple Sales Tax to work." ); ?> 
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
				$default_address = (int) $this->get_option( 'default_address', 0 );

				foreach ( fetch_business_addresses() as $i => $address ) { ?>

					<tr>
						<td>
							<input type="text" name="wootax_address1[]" class="wootax_address1" value="<?php echo $address['address_1']; ?>" />
						</td>
						<td>
							<input type="text" name="wootax_address2[]" class="wootax_address2" value="<?php echo isset( $address['address_2'] ) ? $address['address_2'] : ''; ?>" placeholder="(Optional)" />
						</td>
						<td>
							<input type="text" name="wootax_city[]" class="wootax_city" value="<?php echo $address['city']; ?>" />
						</td>
						<td>
							<select name="wootax_state[]" class="wootax_state">
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
							<input type="text" name="wootax_zip5[]" class="wootax_zip5" value="<?php echo $address['zip5']; ?>" /> - <input type="text" name="wootax_zip4[]" value="<?php echo $address['zip4']; ?>" placeholder="(Optional)" class="wootax_zip4" />
						</td>
						<td>
							<input type="radio" name="wootax_default_address" value="<?php echo $i; ?>"<?php checked( $default_address == $i ); ?> />
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
 	 * Process form submissions.
 	 *
 	 * If the rate removal form is submitted, update wootax_rates_checked flag
 	 * accordingly. If the settings form is submitted, save plugin settings.
 	 *
 	 * @since 5.0
 	 */
 	public function process_admin_options() {
 		if ( isset( $_REQUEST[ 'wt_rates_checked' ] ) ) {
 			update_option( 'wootax_rates_checked', true );
 			return;
 		}

 		// TODO: nonce checking?
 		parent::process_admin_options();

 		// Force settings update
 		$this->init_settings();
 	}

 	/**
 	 * Sanitize submitted settings.
 	 * 
 	 * Validate addresses before they are saved and enforce
 	 * defaults for 'exempt_role' and 'default_address.'
 	 *
 	 * @since 4.5
 	 *
 	 * @param  array $settings Array of submitted settings.
 	 * @return array
 	 */
 	public function sanitize_settings( $settings ) {

 		if ( isset( $_POST[ 'wootax_address1' ] ) ) {
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
			$settings['default_address'] = $_POST[ 'wootax_default_address' ];

			// Set settings_changed flag to "true" so WooTax reloads settings array
			SST()->settings_changed();
		}

		// Force exempt-customer role to be an exempt role
		if ( empty( $settings[ 'exempt_roles' ] ) )
			$settings[ 'exempt_roles' ] = array( 'exempt-customer' );

		// Make the default for default_address 0
		if ( empty ( $settings[ 'default_address' ] ) )
			$settings[ 'default_address' ] = 0;

		return $settings;
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

	/**
	 * AJAX handler. Validates the entered TaxCloud API ID/Key by sending a 
	 * Ping request.
	 *
	 * @since 1.0
	 */
	public static function verify_taxcloud_settings() {
		$taxcloud_id  = $_POST[ 'wootax_tc_id' ];
		$taxcloud_key = $_POST[ 'wootax_tc_key' ];

		if ( empty( $taxcloud_id ) || empty( $taxcloud_key ) ) {
			wp_send_json_error();
		} else {
			$taxcloud = TaxCloud( $taxcloud_id, $taxcloud_key );
	
			// Send ping request and check for errors
			$response = $taxcloud->send_request( 'Ping' );

			if ( $response == false ) {
				wp_send_json_error( $taxcloud->get_error_message() );
			} else {
				wp_send_json_success();
			}
		}
	}

	/**
	 * Delete tax rates from the POSTed tax classes. Don't remove
	 * our tax rate.
	 *
	 * @since 3.5
	 */
	public static function wootax_delete_tax_rates() {
		global $wpdb;

		$rate_classes   = explode( ',', $_POST['rates'] );
		$wootax_rate_id = SST_RATE_ID == false ? 999999 : SST_RATE_ID;

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
				wp_send_json_error( 'There was an error while deleting your tax rates. Please try again.' );
			}
		}

		wp_send_json_success();
	}
}
 
endif;