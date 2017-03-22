<?php

/**
 * Template for tax exemption form. You may override this template by copying it
 * to THEME_PATH/sst/checkout/form-tax-exempt.php.
 *
 * @since 5.0
 * @author Brett Porcelli
 * @package Simple Sales Tax
 * @version 1.0
 */ 

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
} ?>

<p>Select an exemption certificate from the table below, or select "Add new certificate" and fill out
the provided form.</p>

<h4>Use existing certificate</h4>

<?php 
wc_get_template( 'certificate-table.php', array(
	'certificates'  => $certificates,
	'template_path' => $template_path,
	'checkout'      => true
), 'sst/checkout/', $template_path .'/checkout/' );

$new_checked = count( $certificates ) == 0 || ( $_POST && $_POST[ 'certificate_id' ] == 'new' );
?>

<h4>Add new certificate <input type="radio" class="input-radio" name="certificate_id" value="new" <?php checked( $new_checked ); ?>></h4>

<div id="new_certificate_form">
	<?php
		// For each POSTed field FIELD, make $FIELD available below
		$defaults = array(
			'SinglePurchase' 		          => '',
			'ExemptState'    		          => '',
			'TaxType'        		          => '',
			'IDNumber'       		          => '',
			'StateOfIssue'   		          => '',
			'PurchaserBusinessType'           => '',
			'PurchaserBusinessTypeOtherValue' => '',
			'PurchaserExemptionReason'        => '',
			'PurchaserExemptionReasonValue'   => '',
		);

		$values = array_map( 'wc_clean', array_merge( $defaults, $_POST ) );
		
		extract( $values );
	?>

	<p><strong>Warning:</strong> You are responsible for knowing if you qualify to claim exemption from tax in the state that is due tax on this sale. You  will be held liable for any tax and interest, as well as civil and criminal penalties imposed by the member state, if you are not eligible to claim this exemption.</p>

    <p class="form-row validate-required">
      	<label for="SinglePurchase">This certificate will be used for... <abbr class="required" title="required">*</abbr></label> 
      	
      	<select name="SinglePurchase">
	        <option value="true" <?php selected( $SinglePurchase, "true" ); ?>>This purchase only</option>
		 	<option value="false" <?php selected( $SinglePurchase, "false" ); ?>>Multiple purchases</option>
      	</select>
    </p>

    <p class="form-row validate-state validate-required">
      	<label for="ExemptState">State <abbr class="required" title="required">*</abbr></label> 
      	
      	<select name="ExemptState">
        	<option value="" <?php selected( $ExemptState, "" ); ?>>Where does this exemption apply?</option>
			<option value="AL" <?php selected( $ExemptState, "AL" ); ?>>Alabama</option>
			<option value="AK" <?php selected( $ExemptState, "AK" ); ?>>Alaska</option>
			<option value="AZ" <?php selected( $ExemptState, "AZ" ); ?>>Arizona</option>
			<option value="AR" <?php selected( $ExemptState, "AR" ); ?>>Arkansas</option>
			<option value="CA" <?php selected( $ExemptState, "CA" ); ?>>California</option>
			<option value="CO" <?php selected( $ExemptState, "CO" ); ?>>Colorado</option>
			<option value="CT" <?php selected( $ExemptState, "CT" ); ?>>Connecticut</option>
			<option value="DE" <?php selected( $ExemptState, "DE" ); ?>>Delaware</option>
			<option value="FL" <?php selected( $ExemptState, "FL" ); ?>>Florida</option>
			<option value="GA" <?php selected( $ExemptState, "GA" ); ?>>Georgia</option>
			<option value="HI" <?php selected( $ExemptState, "HI" ); ?>>Hawaii</option>
			<option value="ID" <?php selected( $ExemptState, "ID" ); ?>>Idaho</option>
			<option value="IL" <?php selected( $ExemptState, "IL" ); ?>>Illinois</option>
			<option value="IN" <?php selected( $ExemptState, "IN" ); ?>>Indiana</option>
			<option value="IA" <?php selected( $ExemptState, "IA" ); ?>>Iowa</option>
			<option value="KS" <?php selected( $ExemptState, "KS" ); ?>>Kansas</option>
			<option value="KY" <?php selected( $ExemptState, "KY" ); ?>>Kentucky</option>
			<option value="LA" <?php selected( $ExemptState, "LA" ); ?>>Louisiana</option>
			<option value="ME" <?php selected( $ExemptState, "ME" ); ?>>Maine</option>
			<option value="MD" <?php selected( $ExemptState, "MD" ); ?>>Maryland</option>
			<option value="MA" <?php selected( $ExemptState, "MA" ); ?>>Massachusetts</option>
			<option value="MI" <?php selected( $ExemptState, "MI" ); ?>>Michigan</option>
			<option value="MN" <?php selected( $ExemptState, "MN" ); ?>>Minnesota</option>
			<option value="MS" <?php selected( $ExemptState, "MS" ); ?>>Mississippi</option>
			<option value="MO" <?php selected( $ExemptState, "MO" ); ?>>Missouri</option>
			<option value="MT" <?php selected( $ExemptState, "MT" ); ?>>Montana</option>
			<option value="NE" <?php selected( $ExemptState, "NE" ); ?>>Nebraska</option>
			<option value="NV" <?php selected( $ExemptState, "NV" ); ?>>Nevada</option>
			<option value="NH" <?php selected( $ExemptState, "NH" ); ?>>New Hampshire</option>
			<option value="NJ" <?php selected( $ExemptState, "NJ" ); ?>>New Jersey</option>
			<option value="NM" <?php selected( $ExemptState, "NM" ); ?>>New Mexico</option>
			<option value="NY" <?php selected( $ExemptState, "NY" ); ?>>New York</option>
			<option value="NC" <?php selected( $ExemptState, "NC" ); ?>>North Carolina</option>
			<option value="ND" <?php selected( $ExemptState, "ND" ); ?>>North Dakota</option>
			<option value="OH" <?php selected( $ExemptState, "OH" ); ?>>Ohio</option>
			<option value="OK" <?php selected( $ExemptState, "OK" ); ?>>Oklahoma</option>
			<option value="OR" <?php selected( $ExemptState, "OR" ); ?>>Oregon</option>
			<option value="PA" <?php selected( $ExemptState, "PA" ); ?>>Pennsylvania</option>
			<option value="RI" <?php selected( $ExemptState, "RI" ); ?>>Rhode Island</option>
			<option value="SC" <?php selected( $ExemptState, "SC" ); ?>>South Carolina</option>
			<option value="SD" <?php selected( $ExemptState, "SD" ); ?>>South Dakota</option>
			<option value="TN" <?php selected( $ExemptState, "TN" ); ?>>Tennessee</option>
			<option value="TX" <?php selected( $ExemptState, "TX" ); ?>>Texas</option>
			<option value="UT" <?php selected( $ExemptState, "UT" ); ?>>Utah</option>
			<option value="VT" <?php selected( $ExemptState, "VT" ); ?>>Vermont</option>
			<option value="VA" <?php selected( $ExemptState, "VA" ); ?>>Virginia</option>
			<option value="WA" <?php selected( $ExemptState, "WA" ); ?>>Washington</option>
			<option value="DC" <?php selected( $ExemptState, "DC" ); ?>>Washington DC</option>
			<option value="WV" <?php selected( $ExemptState, "WV" ); ?>>West Virginia</option>
			<option value="WI" <?php selected( $ExemptState, "WI" ); ?>>Wisconsin</option>
			<option value="WY" <?php selected( $ExemptState, "WY" ); ?>>Wyoming</option>
      	</select>
    </p>
    
    <div class="form-row validate-required">
    	<label for="TaxType">Tax ID Type <abbr class="required" title="required">*</abbr></label> 

		<select name="TaxType" class="toggle-visibility" data-toggle-class="id-field">
			<option value="" <?php selected( $TaxType, "" ); ?>>Select one</option>
			<option value="FEIN" <?php selected( $TaxType, "FEIN" ); ?>>Federal Employer ID</option>
			<option value="StateIssued" data-show="issuing-state" <?php selected( $TaxType, "StateIssued" ); ?>>State Issued Exemption ID or Drivers License</option>
		</select>
    </div>

    <div class="form-row validate-required">
      	<label for="IDNumber">Tax ID <abbr class="required" title="required">*</abbr></label> 
      	<input type="text" name="IDNumber" class="input-text" placeholder="123-4567-89" value="<?php echo $IDNumber; ?>">
    </div>

    <p class="form-row validate-state id-field" id="issuing-state">
      	<label for="StateOfIssue">ID issued by...</label> 
      	
      	<select name="StateOfIssue">
        	<option value="" <?php selected( $StateOfIssue, "" ); ?>>Select if your ID is state issued</option>
			<option value="AL" <?php selected( $StateOfIssue, "AL" ); ?>>Alabama</option>
			<option value="AK" <?php selected( $StateOfIssue, "AK" ); ?>>Alaska</option>
			<option value="AZ" <?php selected( $StateOfIssue, "AZ" ); ?>>Arizona</option>
			<option value="AR" <?php selected( $StateOfIssue, "AR" ); ?>>Arkansas</option>
			<option value="CA" <?php selected( $StateOfIssue, "CA" ); ?>>California</option>
			<option value="CO" <?php selected( $StateOfIssue, "CO" ); ?>>Colorado</option>
			<option value="CT" <?php selected( $StateOfIssue, "CT" ); ?>>Connecticut</option>
			<option value="DE" <?php selected( $StateOfIssue, "DE" ); ?>>Delaware</option>
			<option value="FL" <?php selected( $StateOfIssue, "FL" ); ?>>Florida</option>
			<option value="GA" <?php selected( $StateOfIssue, "GA" ); ?>>Georgia</option>
			<option value="HI" <?php selected( $StateOfIssue, "HI" ); ?>>Hawaii</option>
			<option value="ID" <?php selected( $StateOfIssue, "ID" ); ?>>Idaho</option>
			<option value="IL" <?php selected( $StateOfIssue, "IL" ); ?>>Illinois</option>
			<option value="IN" <?php selected( $StateOfIssue, "IN" ); ?>>Indiana</option>
			<option value="IA" <?php selected( $StateOfIssue, "IA" ); ?>>Iowa</option>
			<option value="KS" <?php selected( $StateOfIssue, "KS" ); ?>>Kansas</option>
			<option value="KY" <?php selected( $StateOfIssue, "KY" ); ?>>Kentucky</option>
			<option value="LA" <?php selected( $StateOfIssue, "LA" ); ?>>Louisiana</option>
			<option value="ME" <?php selected( $StateOfIssue, "ME" ); ?>>Maine</option>
			<option value="MD" <?php selected( $StateOfIssue, "MD" ); ?>>Maryland</option>
			<option value="MA" <?php selected( $StateOfIssue, "MA" ); ?>>Massachusetts</option>
			<option value="MI" <?php selected( $StateOfIssue, "MI" ); ?>>Michigan</option>
			<option value="MN" <?php selected( $StateOfIssue, "MN" ); ?>>Minnesota</option>
			<option value="MS" <?php selected( $StateOfIssue, "MS" ); ?>>Mississippi</option>
			<option value="MO" <?php selected( $StateOfIssue, "MO" ); ?>>Missouri</option>
			<option value="MT" <?php selected( $StateOfIssue, "MT" ); ?>>Montana</option>
			<option value="NE" <?php selected( $StateOfIssue, "NE" ); ?>>Nebraska</option>
			<option value="NV" <?php selected( $StateOfIssue, "NV" ); ?>>Nevada</option>
			<option value="NH" <?php selected( $StateOfIssue, "NH" ); ?>>New Hampshire</option>
			<option value="NJ" <?php selected( $StateOfIssue, "NJ" ); ?>>New Jersey</option>
			<option value="NM" <?php selected( $StateOfIssue, "NM" ); ?>>New Mexico</option>
			<option value="NY" <?php selected( $StateOfIssue, "NY" ); ?>>New York</option>
			<option value="NC" <?php selected( $StateOfIssue, "NC" ); ?>>North Carolina</option>
			<option value="ND" <?php selected( $StateOfIssue, "ND" ); ?>>North Dakota</option>
			<option value="OH" <?php selected( $StateOfIssue, "OH" ); ?>>Ohio</option>
			<option value="OK" <?php selected( $StateOfIssue, "OK" ); ?>>Oklahoma</option>
			<option value="OR" <?php selected( $StateOfIssue, "OR" ); ?>>Oregon</option>
			<option value="PA" <?php selected( $StateOfIssue, "PA" ); ?>>Pennsylvania</option>
			<option value="RI" <?php selected( $StateOfIssue, "RI" ); ?>>Rhode Island</option>
			<option value="SC" <?php selected( $StateOfIssue, "SC" ); ?>>South Carolina</option>
			<option value="SD" <?php selected( $StateOfIssue, "SD" ); ?>>South Dakota</option>
			<option value="TN" <?php selected( $StateOfIssue, "TN" ); ?>>Tennessee</option>
			<option value="TX" <?php selected( $StateOfIssue, "TX" ); ?>>Texas</option>
			<option value="UT" <?php selected( $StateOfIssue, "UT" ); ?>>Utah</option>
			<option value="VT" <?php selected( $StateOfIssue, "VT" ); ?>>Vermont</option>
			<option value="VA" <?php selected( $StateOfIssue, "VA" ); ?>>Virginia</option>
			<option value="WA" <?php selected( $StateOfIssue, "WA" ); ?>>Washington</option>
			<option value="DC" <?php selected( $StateOfIssue, "DC" ); ?>>Washington DC</option>
			<option value="WV" <?php selected( $StateOfIssue, "WV" ); ?>>West Virginia</option>
			<option value="WI" <?php selected( $StateOfIssue, "WI" ); ?>>Wisconsin</option>
			<option value="WY" <?php selected( $StateOfIssue, "WY" ); ?>>Wyoming</option>
      	</select>
    </p>

    <p class="form-row validate-required">
		<label for="PurchaserBusinessType">Business Type <abbr class="required" title="required">*</abbr></label> 

		<select name="PurchaserBusinessType" class="toggle-visibility" data-toggle-class="type-field">
			<option value="" <?php selected( $PurchaserBusinessType, "" ); ?>>Select one</option>
			<option value="AccommodationAndFoodServices" <?php selected( $PurchaserBusinessType, "AccommodationAndFoodServices" ); ?>>Accommodation And Food Services</option>
			<option value="Agricultural_Forestry_Fishing_Hunting" <?php selected( $PurchaserBusinessType, "Agricultural_Forestry_Fishing_Hunting" ); ?>>Agricultural/Forestry/Fishing/Hunting</option>
			<option value="Construction" <?php selected( $PurchaserBusinessType, "Construction" ); ?>>Construction</option>
			<option value="FinanceAndInsurance" <?php selected( $PurchaserBusinessType, "FinanceAndInsurance" ); ?>>Finance or Insurance</option>
			<option value="Information_PublishingAndCommunications" <?php selected( $PurchaserBusinessType, "Information_PublishingAndCommunications" ); ?>>Information Publishing and Communications</option>
			<option value="Manufacturing" <?php selected( $PurchaserBusinessType, "Manufacturing" ); ?>>Manufacturing</option>
			<option value="Mining" <?php selected( $PurchaserBusinessType, "Mining" ); ?>>Mining</option>
			<option value="RealEstate" <?php selected( $PurchaserBusinessType, "RealEstate" ); ?>>Real Estate</option>
			<option value="RentalAndLeasing" <?php selected( $PurchaserBusinessType, "RentalAndLeasing" ); ?>>Rental and Leasing</option>
			<option value="RetailTrade" <?php selected( $PurchaserBusinessType, "RetailTrade" ); ?>>Retail Trade</option>
			<option value="TransportationAndWarehousing" <?php selected( $PurchaserBusinessType, "TransportationAndWarehousing" ); ?>>Transportation and Warehousing</option>
			<option value="Utilities" <?php selected( $PurchaserBusinessType, "Utilities" ); ?>>Utilities</option>
			<option value="WholesaleTrade" <?php selected( $PurchaserBusinessType, "WholesaleTrade" ); ?>>Wholesale Trade</option>
			<option value="BusinessServices" <?php selected( $PurchaserBusinessType, "BusinessServices" ); ?>>Business Services</option>
			<option value="ProfessionalServices" <?php selected( $PurchaserBusinessType, "ProfessionalServices" ); ?>>Professional Services</option>
			<option value="EducationAndHealthCareServices" <?php selected( $PurchaserBusinessType, "EducationAndHealthCareServices" ); ?>>Education and Health Care Services</option>
			<option value="NonprofitOrganization" <?php selected( $PurchaserBusinessType, "NonprofitOrganization" ); ?>>Nonprofit Organization</option>
			<option value="Government" <?php selected( $PurchaserBusinessType, "Government" ); ?>>Government</option>
			<option value="NotABusiness" <?php selected( $PurchaserBusinessType, "NotABusiness" ); ?>>Not a Business</option>
			<option value="Other" data-show="business-type-other" <?php selected( $PurchaserBusinessType, "Other" ); ?>>Other</option>
		</select>
    </p>

    <p class="form-row type-field" id="business-type-other">
    	<label for="PurchaserBusinessTypeOtherValue">Please explain</label> 
		<textarea name="PurchaserBusinessTypeOtherValue" placeholder="Explain the type of your business if you selected 'Other'" class="input-text"><?php echo $PurchaserBusinessTypeOtherValue; ?></textarea>
    </p>

    <p class="form-row validate-required">
		<label for="PurchaserExemptionReason">Reason for Exemption <abbr class="required" title="required">*</abbr></label> 
		
		<select name="PurchaserExemptionReason" class="toggle-visibility" data-toggle-class="reason-field">
			<option value="" <?php selected( $PurchaserExemptionReason, "" ); ?>>Select one</option>
			<option value="FederalGovernmentDepartment" <?php selected( $PurchaserExemptionReason, "FederalGovernmentDepartment" ); ?>>Federal Government Department</option>
			<option value="StateOrLocalGovernmentName" <?php selected( $PurchaserExemptionReason, "StateOrLocalGovernmentName" ); ?>>State Or Local Government</option>
			<option value="TribalGovernmentName" <?php selected( $PurchaserExemptionReason, "TribalGovernmentName" ); ?>>Tribal Government</option>
			<option value="ForeignDiplomat" <?php selected( $PurchaserExemptionReason, "ForeignDiplomat" ); ?>>Foreign Diplomat</option>
			<option value="CharitableOrganization" <?php selected( $PurchaserExemptionReason, "CharitableOrganization" ); ?>>Charitable Organization</option>
			<option value="ReligiousOrEducationalOrganization" <?php selected( $PurchaserExemptionReason, "ReligiousOrEducationalOrganization" ); ?>>Religious or Educational Organization</option>
			<option value="Resale" <?php selected( $PurchaserExemptionReason, "Resale" ); ?>>Resale</option>
			<option value="AgriculturalProduction" <?php selected( $PurchaserExemptionReason, "AgriculturalProduction" ); ?>>Agricultural Production</option>
			<option value="IndustrialProductionOrManufacturing" <?php selected( $PurchaserExemptionReason, "IndustrialProductionOrManufacturing" ); ?>>Industrial Production or Manufacturing</option>
			<option value="DirectPayPermit" <?php selected( $PurchaserExemptionReason, "DirectPayPermit" ); ?>>Direct Pay Permit</option>
			<option value="DirectMail" <?php selected( $PurchaserExemptionReason, "DirectMail" ); ?>>Direct Mail</option>
			<option value="Other" data-show="exempt-other-reason" <?php selected( $PurchaserExemptionReason, "Other" ); ?>>Other</option>
		</select>
    </p>

    <p class="form-row reason-field" id="exempt-other-reason">
      	<label for="PurchaserExemptionReasonValue">Please explain</label>
      	<textarea name="PurchaserExemptionReasonValue" placeholder="Explain why you are exempt if you selected 'Other'" class="input-text"><?php echo $PurchaserExemptionReasonValue; ?></textarea>
    </p>

</div>