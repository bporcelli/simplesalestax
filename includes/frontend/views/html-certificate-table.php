<?php

/**
 * Template for tax exemption form. You may override this template by copying it
 * to THEME_PATH/sst/checkout/html-certificate-table.php.
 *
 * @since 5.0
 * @author Brett Porcelli
 * @package Simple Sales Tax
 * @version 1.0
 */ 

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
} ?>

<h3><?php esc_html_e( 'Tax exempt?', 'simplesalestax' ); ?> <input type="checkbox" name="tax_exempt" id="tax_exempt_checkbox" class="input-checkbox" value="1"<?php checked( $checked ); ?>></h3>

<div id="tax_details">

	<noscript>
		<p><strong><?php _e( 'Warning', 'simplesalestax' ); ?>:</strong> <?php _e( 'This interface will not function properly with JavaScript disabled. Please enable JavaScript to continue.', 'simplesalestax' ); ?></p>
	</noscript>

	<?php if ( is_user_logged_in() ): ?>
	
	<p><?php esc_html_e( 'Select an exemption certificate from the table below, or click "Add Certificate" and fill out the provided form.', 'simplesalestax' ); ?></p>

	<table id="sst-certificates" class="shop_table">
		<thead>
			<tr>
				<th><!-- Radio button column --></th>
				<th><?php _e( 'ID', 'simplesalestax' ); ?></th>
				<th><?php _e( 'Issued To', 'simplesalestax' ); ?></th>
				<th><?php _e( 'Date', 'simplesalestax' ); ?></th>
				<th><?php _e( 'Actions', 'simplesalestax' ); ?></th>
			</tr>
		</thead>
		<tfoot>
			<tr>
				<td colspan="5">
					<a href="#" class="button sst-certificate-add"><?php _e( 'Add Certificate', 'simpleslaestax' ); ?></a> 
				</td>
			</tr>
		</tfoot>
		<tbody></tbody>
	</table>
	
	<?php else: ?>
	<p><?php esc_html_e( 'Please log in or register.' ); ?></p>
	<?php endif; ?>

</div>

<script type="text/html" id="tmpl-sst-certificate-row-blank">
	<tr>
		<td colspan="5">
			<span><?php _e( "There are no certificates to display. Click 'Add Certificate' to add one.", 'simplesalestax' ); ?></span>
		</td>
	</tr>
</script>

<script type="text/html" id="tmpl-sst-certificate-row">
	<tr data-id="{{ data.CertificateID }}">
		<td>
			<input type="radio" name="certificate_id" value="{{ data.CertificateID }}">
		</td>
		<td>{{ data.Index }}</td>
	    <td>{{ data.PurchaserName }}</td>
	    <td>{{ data.CreatedDate }}</td>
	    <td>
	    	<a href="#" class="sst-certificate-view">View</a> | <a href="#" class="sst-certificate-delete">Delete</a>
	    </td>
	</tr>
</script>

<script type="text/html" id="tmpl-sst-modal-add-certificate">
	<div class="wc-backbone-modal">
		<div class="wc-backbone-modal-content sst-certificate-modal-content woocommerce">
			<section class="wc-backbone-modal-main" role="main">
				<header class="wc-backbone-modal-header">
					<h1><?php _e( 'Add certificate', 'simplesalestax' ); ?></h1>
					<button class="modal-close modal-close-link dashicons dashicons-no-alt">
						<span class="screen-reader-text"><?php _e( 'Close modal panel', 'simplesalestax' ); ?></span>
					</button>
				</header>
				<article>
					<form action="" method="post">
						<p><strong><?php esc_html_e( 'Warning', 'simplesalestax' ); ?>:</strong> <?php esc_html_e( 'You are responsible for knowing if you qualify to claim exemption from tax in the state that is due tax on this sale. You  will be held liable for any tax and interest, as well as civil and criminal penalties imposed by the member state, if you are not eligible to claim this exemption.', 'simplesalestax' ); ?></p>

						<?php woocommerce_form_field( 'ExemptState', array(
							'type'     => 'state',
							'label'    => __( 'Where does this exemption apply?', 'simplesalestax' ),
							'required' => true,
							'class'    => array( 'sst-input' ),
						) ); ?>

						<?php woocommerce_form_field( 'TaxType', array(
							'type'     => 'select',
							'label'    => __( 'Tax ID Type', 'simplesalestax' ),
							'required' => true,
							'class'    => array( 'sst-input' ),
							'options'  => array(
								''            => __( 'Select one', 'simplesalestax' ),
								'FEIN'        => __( 'Federal Employer ID', 'simplesalestax' ),
								'StateIssued' => __( 'State Issued Exemption ID or Drivers License', 'simplesalestax' ),
							),
						) ); ?>
					    
					    <?php woocommerce_form_field( 'IDNumber', array(
					    	'type'        => 'text',
					    	'label'       => __( 'Tax ID', 'simplesalestax' ),
					    	'placeholder' => '123-4567-89',
					    	'required'    => true,
					    	'class'       => array( 'sst-input' ),
					    ) ); ?>

					    <?php woocommerce_form_field( 'StateOfIssue', array(
					    	'type'        => 'state',
					    	'label'       => __( 'ID issued by...', 'simplesalestax' ),
					    	'placeholder' => __( 'Select if your ID is state issued.', 'simplesalestax' ),
					    	'id'          => 'issuing-state',
					    	'class'       => array( 'sst-hidden-field', 'sst-input' ),
					    ) ); ?>

					    <?php woocommerce_form_field( 'PurchaserBusinessType', array(
					    	'type'     => 'select',
					    	'label'    => __( 'Business Type', 'simplesalestax' ),
					    	'required' => true,
					    	'class'    => array( 'sst-input' ),
					    	'options'  => array(
					    		''                                        => __( 'Select one', 'simplesalestax' ),
					    		'AccommodationAndFoodServices'            => __( 'Accommodation And Food Services', 'simplesalestax' ),
					    		'Agricultural_Forestry_Fishing_Hunting'   => __( 'Agricultural/Forestry/Fishing/Hunting', 'simplesalestax' ),
					    		'Construction'                            => __( 'Construction', 'simplesalestax' ),
					    		'FinanceAndInsurance'                     => __( 'Finance or Insurance', 'simplesalestax' ),
					    		'Information_PublishingAndCommunications' => __( 'Information Publishing and Communications', 'simplesalestax' ),
					    		'Manufacturing'                           => __( 'Manufacturing', 'simplesalestax' ),
					    		'Mining'                                  => __( 'Mining', 'simplesalestax' ),
					    		'RealEstate'                              => __( 'Real Estate', 'simplesalestax' ),
					    		'RentalAndLeasing'                        => __( 'Rental and Leasing', 'simplesalestax' ),
					    		'RetailTrade'                             => __( 'Retail Trade', 'simplesalestax' ),
					    		'TransportationAndWarehousing'            => __( 'Transportation and Warehousing', 'simplesalestax' ),
					    		'Utilities'                               => __( 'Utilities', 'simplesalestax' ),
					    		'WholesaleTrade'                          => __( 'Wholesale Trade', 'simplesalestax' ),
					    		'BusinessServices'                        => __( 'Business Services', 'simplesalestax' ),
					    		'ProfessionalServices'                    => __( 'Professional Services', 'simplesalestax' ),
					    		'EducationAndHealthCareServices'          => __( 'Education and Health Care Services', 'simplesalestax' ),
					    		'NonprofitOrganization'                   => __( 'Nonprofit Organization', 'simplesalestax' ),
					    		'Government'                              => __( 'Government', 'simplesalestax' ),
					    		'NotABusiness'                            => __( 'Not a Business', 'simplesalestax' ),
					    		'Other'                                   => __( 'Other', 'simplesalestax' ),
					    	),
					    ) ); ?>

					    <?php woocommerce_form_field( 'PurchaserBusinessTypeOtherValue', array(
					    	'type'        => 'text',
					    	'label'       => __( 'Please explain', 'simplesalestax' ),
					    	'placeholder' => __( 'Explain the nature of your business.', 'simplesalestax' ),
					    	'id'          => 'business-type-other',
					    	'class'       => array( 'sst-hidden-field', 'sst-input' ),
					    ) ); ?>

					   	<?php woocommerce_form_field( 'PurchaserExemptionReason', array(
					   		'type'     => 'select',
					   		'label'    => __( 'Reason for Exemption', 'simplesalestax' ),
					   		'required' => true,
					   		'class'    => array( 'sst-input' ),
					   		'options'  => array(
					   			''                                    => __( 'Select one', 'simplesalestax' ),
					   			'FederalGovernmentDepartment'         => __( 'Federal Government Department', 'simplesalestax' ),
					   			'StateOrLocalGovernmentName'          => __( 'State Or Local Government', 'simplesalestax' ),
					   			'TribalGovernmentName'                => __( 'Tribal Government', 'simplesalestax' ),
					   			'ForeignDiplomat'                     => __( 'Foreign Diplomat', 'simplesalestax' ),
					   			'CharitableOrganization' 			  => __( 'Charitable Organization', 'simplesalestax' ),
					   			'ReligiousOrEducationalOrganization'  => __( 'Religious or Educational Organization', 'simplesalestax' ),
					   			'Resale' 							  => __( 'Resale', 'simplesalestax' ),
					   			'AgriculturalProduction' 			  => __( 'Agricultural Production', 'simplesalestax' ),
					   			'IndustrialProductionOrManufacturing' => __( 'Industrial Production or Manufacturing', 'simplesalestax' ),
					   			'DirectPayPermit' 					  => __( 'Direct Pay Permit', 'simplesalestax' ),
					   			'DirectMail' 						  => __( 'Direct Mail', 'simplesalestax' ),
					   			'Other' 							  => __( 'Other', 'simplesalestax' ),
					   		),
					   	) ); ?>

					   	<?php woocommerce_form_field( 'PurchaserExemptionReasonValue', array(
					   		'type'  => 'text',
					   		'label' => __( 'Please explain', 'simplesalestax' ),
					   		'id'    => 'exempt-other-reason',
					   		'class' => array( 'sst-hidden-field', 'sst-input' ),
					   	) ); ?>

						<input type="hidden" name="CertificateID" value="{{{ data.CertificateID }}}">
					</form>
				</article>
				<footer>
					<div class="inner">
						<button id="btn-ok" class="button alt"><?php _e( 'Add certificate', 'simplesalestax' ); ?></button>
					</div>
				</footer>
			</section>
		</div>
	</div>
	<div class="wc-backbone-modal-backdrop modal-close"></div>
</script>

<?php include SST()->plugin_path() . '/includes/frontend/views/html-view-certificate.php'; ?>