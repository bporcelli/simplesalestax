<p>If you experience issues with WooTax, be sure to consult the <a href="http://wootax.com/frequently-asked-questions/">FAQ</a> and the <a href="http://wootax.com/installation-guide/" target="_blank">Installation Guide</a> before contacting a support agent. Most likely, your question or concern has already been addressed.</p>
<p>Have questions about a particular setting? Hover over the (?) next to it to learn more.</p>
<p><em>Fields marked with a <span class="required">*</span> are required</em></p>

<form id="wootax_form" action="" method="POST">
	<h3>TaxCloud Settings</h3>
	<p>You must enter a valid TaxCloud API ID and API Key for WooTax to work properly. Use the "Verify Settings" button to make sure that your settings are correct.</p>
	<table class="form-table" id="taxcloud">
		<tr>
			<th>TaxCloud API ID <span class="required">*</span> <img class="help_tip" data-tip='Your TaxCloud API ID. This can be found in your TaxCloud account on the "Websites" page.' src="<?php echo plugin_dir_url('woocommerce/woocommerce.php'); ?>/assets/images/help.png" height="16" width="16"></th>
			<td>
				<input type="text" id="wootax_tc_id" name="wootax_tc_id" class="required" value="<?php echo get_option('wootax_tc_id'); ?>" />
			</td>
		</tr>
		<tr>
			<th>TaxCloud API Key <span class="required">*</span> <img class="help_tip" data-tip='Your TaxCloud API Key. This can be found in your TaxCloud account on the "Websites" page.' src="<?php echo plugin_dir_url('woocommerce/woocommerce.php'); ?>/assets/images/help.png" height="16" width="16"></th>
			<td>
				<input type="text" id="wootax_tc_key" name="wootax_tc_key" class="required" value="<?php echo get_option('wootax_tc_key'); ?>" />
			</td>
		</tr>
        <tr>
        	<th>Verify TaxCloud Settings <img class="help_tip" data-tip='Use this button to verify that your site can communicate with TaxCloud successfully.' src="<?php echo plugin_dir_url('woocommerce/woocommerce.php'); ?>/assets/images/help.png" height="16" width="16"></th>
            <td>
            	<button class="wp-ui-core button button-secondary" id="verifySettings">Verify Settings</button>
           	</td>
       	</tr>
	</table>

	<h3>USPS Settings</h3>
	<p>A valid USPS Web Tools ID is required for verifying customer addresses. If you do not have an ID, you can register for one for <strong><em>free</em></strong> <a href="https://secure.shippingapis.com/registration/" target="_blank">here</a>. Your ID will be sent to you via email when your registration is complete.</p>
	<table class="form-table" id="usps">
		<tr>
			<th>USPS ID <span class="required">*</span> <img class="help_tip" data-tip='Your USPS Web Tools User ID. Used for verifying customer addresses.' src="<?php echo plugin_dir_url('woocommerce/woocommerce.php'); ?>/assets/images/help.png" height="16" width="16"></th>
			<td>
				<input type="text" id="wootax_usps_id" name="wootax_usps_id" class="required" value="<?php echo get_option('wootax_usps_id'); ?>" />
			</td>
		</tr>
   	</table>

    <h3>Business Addresses</h3>
    <p>You must enter at least one business address for WooTax to work properly. After adding an address, remember to click the "Validate Addresses" button below.</p>
	<table class="shippingrows widefat">
		<thead>
			<tr>
				<th><span>Address 1</span> <img class="help_tip" data-tip="Line 1 of your business address." src="<?php echo plugin_dir_url('woocommerce/woocommerce.php'); ?>/assets/images/help.png" height="16" width="16"></th>
				<th><span>Address 2</span> <img class="help_tip" data-tip="Line 2 of your business address." src="<?php echo plugin_dir_url('woocommerce/woocommerce.php'); ?>/assets/images/help.png" height="16" width="16"></th>
				<th><span>City</span> <img class="help_tip" data-tip="The city in which your business operates." src="<?php echo plugin_dir_url('woocommerce/woocommerce.php'); ?>/assets/images/help.png" height="16" width="16"></th>
				<th><span>State</span> <img class="help_tip" data-tip="The state where your business is located." src="<?php echo plugin_dir_url('woocommerce/woocommerce.php'); ?>/assets/images/help.png" height="16" width="16"></th>
				<th><span>ZIP Code</span> <img class="help_tip" data-tip="5 or 9-digit ZIP code of your business address." src="<?php echo plugin_dir_url('woocommerce/woocommerce.php'); ?>/assets/images/help.png" height="16" width="16"></th>
				<th><span>Make Default</span> <img class="help_tip" data-tip="Check this if you want an address to be used as the default 'Shipment Origin Address' for your products. If you only have one business address, it will be used as your default address automatically." src="<?php echo plugin_dir_url('woocommerce/woocommerce.php'); ?>/assets/images/help.png" height="16" width="16"></th>
				<th><span>Remove</span> <img class="help_tip" data-tip="Click the red X to remove a business address. Remember, at least one valid address is required for WooTax to work." src="<?php echo plugin_dir_url('woocommerce/woocommerce.php'); ?>/assets/images/help.png" height="16" width="16"></th> 
			</tr>
		</thead>
		<tfoot>
			<tr>
				<th colspan="7"><strong>IMPORTANT:</strong> Any addresses you enter here should also be registered as <a href="https://taxcloud.net/account/locations/" target="_blank">locations</a> through TaxCloud.</th>
			</tr>
			<tr>
				<th colspan="7">
					<button class="wp-core-ui button-secondary add">Add Address</button>
					<button id="verifyAddress" class="wp-ui-core button-primary">Validate Addresses</button>
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
						<input type="text" name="wootax_address2[<?php echo $i; ?>]" class="wootax_address2" value="<?php echo $address['address_2']; ?>" />
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
						<input type="text" name="wootax_zip5[<?php echo $i; ?>]" class="wootax_zip5" value="<?php echo $address['zip5']; ?>" /> - <input type="text" name="wootax_zip4[<?php echo $i; ?>]" value="<?php echo $address['zip4']; ?>" class="wootax_zip4" />
					</td>
					<td>
						<input type="radio" name="wootax_default_address" value="<?php echo $i; ?>"<?php echo (get_option('wootax_default_address') == $i || get_option('wootax_default_address') == '' && $i == 0) ? ' checked' : ''; ?> />
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

    <h3>Exemption Settings</h3>
    <p>If you have tax exempt customers, be sure to enable tax exemptions and enter your company name.</p>
    <table class="form-table">
    	<tr>
        	<th>Enable Tax Exemptions? <img class="help_tip" data-tip='Set this to "Yes" if you have tax exempt customers.' src="<?php echo plugin_dir_url('woocommerce/woocommerce.php'); ?>/assets/images/help.png" height="16" width="16"></th>
            <td>
          		<select id="wootax_show_exempt" name="wootax_show_exempt">
                    <option value="false"<?php echo (get_option('wootax_show_exempt') == false || get_option('wootax_show_exempt') == "false") ? ' selected' : ''; ?>>No</option>
                	<option value="true"<?php echo (get_option('wootax_show_exempt') == "true") ? ' selected' : ''; ?>>Yes</option>
                </select>
            </td>
        </tr>
    	<tr>
        	<th>Company Name <img class="help_tip" data-tip='Enter your company name as it should be displayed on exemption certificates.' src="<?php echo plugin_dir_url('woocommerce/woocommerce.php'); ?>/assets/images/help.png" height="16" width="16"></th>
            <td>
            	<input type="text" id="wootax_company_name" name="wootax_company_name" value="<?php echo get_option('wootax_company_name'); ?>" />
           	</td>
      	</tr>
    </table>

    <h3>Display Settings</h3>
    <p>Control some visual aspects of WooTax.</p>
    <table class="form-table">
    	<tr>
        	<th>Show Zero Tax? <img class="help_tip" data-tip='When the sales tax due is zero, should the "Sales Tax" line be shown?' src="<?php echo plugin_dir_url('woocommerce/woocommerce.php'); ?>/assets/images/help.png" height="16" width="16"></th>
            <td>	
            	<select id="wootax_show_zero_tax" name="wootax_show_zero_tax">
                	<option value="false"<?php echo (get_option('wootax_show_zero_tax') != 'true') ? ' selected' : ''; ?>>No</option>
                    <option value="true"<?php echo (get_option('wootax_show_zero_tax') == 'true') ? ' selected' : ''; ?>>Yes</option>
                </select>
           	</td>
        </tr>
      	<tr>
        	<th>Exemption Link Text <img class="help_tip" data-tip='This text is displayed on the link that opens the exemption management interface. Defaults to "Click here to add or apply an exemption certificate."' src="<?php echo plugin_dir_url('woocommerce/woocommerce.php'); ?>/assets/images/help.png" height="16" width="16"></th>
            <td>
            	<input type="text" id="wootax_exemption_text" name="wootax_exemption_text" value="<?php echo get_option('wootax_exemption_text'); ?>" />
           	</td>
      	</tr>
    </table>

    <h3>Advanced Settings</h3>
    <p>For advanced users only. Leave these settings untouched if you are not sure how to use them.</p>

    <table class="form-table">
    	<tr>
    		<th>Tax Based On <img class="help_tip" data-tip='When set to "Item Price," the taxable amount for each line item is determined by <code>ITEM_PRICE * ITEM_QUANTITY</code>. Otherwise, it is given by the line subtotal. Useful for correcting rounding errors.' src="<?php echo plugin_dir_url('woocommerce/woocommerce.php'); ?>/assets/images/help.png" height="16" width="16"></th>
    		<td>
	    		<select id="wootax_tax_based_on" name="wootax_tax_based_on">
	    			<option value="item-price"<?php echo (get_option('wootax_tax_based_on') == false || get_option('wootax_tax_based_on') == 'item-price') ? ' selected' : ''; ?>>Item Price</option>
	    			<option value="line-subtotal"<?php echo (get_option('wootax_tax_based_on') == 'line-subtotal') ? ' selected' : ''; ?>>Line Subtotal</option>
	    		</select>
	    	</td>
    	</tr>
        <tr>
			<td colspan="2">
				<input type="hidden" name="wootax_settings_updating" value="true" />
            	<button class="wp-ui-core button button-primary" type="submit">Save Settings</button>
                <button class="wp-ui-core button button-secondary" type="button" id="resetSettings">Clear Settings</button>
          		<button class="wp-ui-core button button-secondary" type="button" id="wootax_deactivate_license">Deactivate License</button>
          	</td>
		</tr>
    </table>
</form>