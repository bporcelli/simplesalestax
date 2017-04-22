<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; 
}

/**
 * SST Integration.
 *
 * WooCommerce integration for Simple Sales Tax.
 *
 * @author 	Simple Sales Tax
 * @package SST
 * @since 	5.0
 */ 
class SST_Integration extends WC_Integration { 

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

		// Register action hooks.
		add_action( 'woocommerce_update_options_integration_' .  $this->id, array( $this, 'process_admin_options' ) );
 		add_filter( 'woocommerce_settings_api_sanitized_fields_' . $this->id, array( $this, 'sanitize_settings' ) );
	}

 	/**
 	 * Initialize form fields for integration settings.
 	 *
 	 * @since 4.5
 	 */
	public function init_form_fields() {
		$this->form_fields = SST_Settings::get_form_fields();
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
 	 * Output HTML for field of type 'anchor.'
 	 *
 	 * @since 5.0
	 */
 	public function generate_anchor_html( $key, $data ) {
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
					<a href="<?php echo esc_url( $data[ 'url' ] ); ?>" target="_blank" class="wp-core-ui button button-secondary" id="<?php echo $data[ 'id' ]; ?>"><?php echo wp_kses_post( $data[ 'label' ] ); ?></a>
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
					<th><span>Address 1</span> <?php sst_tip( "Line 1 of your business address." ) ?> 
					<th><span>Address 2</span> <?php sst_tip( "Line 2 of your business address." ) ?>
					<th><span>City</span> <?php sst_tip( "The city in which your business operates." ) ?>
					<th><span>State</span> <?php sst_tip( "The state where your business is located." ); ?>
					<th><span>ZIP Code</span> <?php sst_tip( "5 or 9-digit ZIP code of your business address." ); ?>
					<th><span>Make Default</span> <?php sst_tip( "Check this if you want an address to be used as the default 'Shipment Origin Address' for your products. If you only have one business address, it will be used as your default address automatically." ); ?>
					<th><span>Remove</span> <?php sst_tip( "Click the red X to remove a business address. Remember, at least one valid address is required for Simple Sales Tax to work." ); ?> 
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

				foreach ( SST_Addresses::get_origin_addresses() as $i => $address ) { ?>

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
 	 * Sanitize submitted settings.
 	 * 
 	 * Validate addresses before they are saved.
 	 *
 	 * @since 4.5
 	 *
 	 * @param  array $settings Array of submitted settings.
 	 * @return array
 	 */
 	public function sanitize_settings( $settings ) {
 		if ( isset( $_POST[ 'wootax_address1' ] ) ) {
	 		$addresses = array();
 			
 			$taxcloud_id  = sanitize_text_field( $_POST['woocommerce_wootax_tc_id'] );
			$taxcloud_key = sanitize_text_field( $_POST['woocommerce_wootax_tc_key'] );

			for ( $i = 0; $i < count( $_POST['wootax_address1'] ); $i++ ) {
				$address = new TaxCloud\Address(
					$_POST['wootax_address1'][ $i ],
					$_POST['wootax_address2'][ $i ],
					$_POST['wootax_city'][ $i ],
					$_POST['wootax_state'][ $i ],
					$_POST['wootax_zip5'][ $i ],
					$_POST['wootax_zip4'][ $i ]
				);

				$verify = new TaxCloud\Request\VerifyAddress( $taxcloud_id, $taxcloud_key, $address );
				try {
					$address = TaxCloud()->VerifyAddress( $verify );
				} catch ( Exception $ex ) {
					// Use original address
				}

				$addresses[] = array(
					'address_1' => $address->getAddress1(),
					'address_2' => $address->getAddress2(),
					'country'   => 'US', // US is the only supported country
					'city'      => $address->getCity(),
					'state'     => $address->getState(),
					'zip5'      => $address->getZip5(),
					'zip4'      => $address->getZip4()
				);
			}

			$settings['addresses'] = $addresses;
		}

		return $settings;
 	}
}
