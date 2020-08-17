<?php

/**
 * Template for tax exemption form. You may override this template by copying it
 * to THEME_PATH/sst/checkout/html-certificate-table.php.
 *
 * @since   5.0
 * @author  Brett Porcelli
 * @package Simple Sales Tax
 * @version 1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
?>

	<h3>
		<?php esc_html_e( 'Tax exempt?', 'simple-sales-tax' ); ?>
		<input type="checkbox" name="tax_exempt" id="tax_exempt_checkbox" class="input-checkbox" value="1" <?php checked( $checked ); ?>>
	</h3>

	<div id="tax_details">

		<noscript>
			<p>
				<?php
				printf(
					'<strong>%s</strong> %s',
					__( 'Warning:', 'simple-sales-tax' ),
					__(
						'This interface will not function properly with JavaScript disabled. Please enable JavaScript to continue.',
						'simple-sales-tax'
					)
				);
				?>
			</p>
		</noscript>

		<?php if ( is_user_logged_in() ) : ?>

			<p>
				<?php
				esc_html_e(
					'Select an exemption certificate from the table below, or click "Add Certificate" and fill out the provided form.',
					'simple-sales-tax'
				);
				?>
			</p>

			<table id="sst-certificates" class="shop_table">
				<thead>
				<tr>
					<th><!-- Radio button column --></th>
					<th><?php _e( 'ID', 'simple-sales-tax' ); ?></th>
					<th><?php _e( 'Issued To', 'simple-sales-tax' ); ?></th>
					<th><?php _e( 'Date', 'simple-sales-tax' ); ?></th>
					<th><?php _e( 'Actions', 'simple-sales-tax' ); ?></th>
				</tr>
				</thead>
				<tfoot>
				<tr>
					<td colspan="5">
						<a href="#" class="button sst-certificate-add">
							<?php _e( 'Add Certificate', 'simple-sales-tax' ); ?>
						</a>
					</td>
				</tr>
				</tfoot>
				<tbody></tbody>
			</table>

		<?php else : ?>
			<p><?php esc_html_e( 'Please log in or register.' ); ?></p>
		<?php endif; ?>

	</div>

	<script type="text/html" id="tmpl-sst-certificate-row-blank">
		<tr>
			<td colspan="5">
				<span>
					<?php
					_e(
						"There are no certificates to display. Click 'Add Certificate' to add one.",
						'simple-sales-tax'
					);
					?>
				</span>
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
				<a href="#" class="sst-certificate-view">View</a> | <a href="#"
																	   class="sst-certificate-delete">Delete</a>
			</td>
		</tr>
	</script>

	<script type="text/html" id="tmpl-sst-modal-add-certificate">
		<div class="wc-backbone-modal">
			<div class="wc-backbone-modal-content sst-certificate-modal-content woocommerce">
				<section class="wc-backbone-modal-main" role="main">
					<header class="wc-backbone-modal-header">
						<h1><?php _e( 'Add certificate', 'simple-sales-tax' ); ?></h1>
						<button class="modal-close modal-close-link dashicons dashicons-no-alt">
							<span class="screen-reader-text">
								<?php _e( 'Close modal panel', 'simple-sales-tax' ); ?></span>
						</button>
					</header>
					<article>
						<form action="" method="post">
							<?php
							printf(
								'<strong>%s</strong> %s',
								esc_html__( 'Warning', 'simple-sales-tax' ),
								esc_html__(
									'You are responsible for knowing if you qualify to claim exemption from tax in the state that is due tax on this sale. You  will be held liable for any tax and interest, as well as civil and criminal penalties imposed by the member state, if you are not eligible to claim this exemption.',
									'simple-sales-tax'
								)
							);
							?>

							<?php
							woocommerce_form_field(
								'ExemptState',
								array(
									'type'     => 'state',
									'label'    => __( 'Where does this exemption apply?', 'simple-sales-tax' ),
									'required' => true,
									'class'    => array( 'sst-input' ),
								)
							);
							?>

							<?php
							woocommerce_form_field(
								'TaxType',
								array(
									'type'     => 'select',
									'label'    => __( 'Tax ID Type', 'simple-sales-tax' ),
									'required' => true,
									'class'    => array( 'sst-input' ),
									'options'  => array(
										''            => __( 'Select one', 'simple-sales-tax' ),
										'FEIN'        => __( 'Federal Employer ID', 'simple-sales-tax' ),
										'StateIssued' => __(
											'State Issued Exemption ID or Drivers License',
											'simple-sales-tax'
										),
									),
								)
							);
							?>

							<?php
							woocommerce_form_field(
								'IDNumber',
								array(
									'type'        => 'text',
									'label'       => __( 'Tax ID', 'simple-sales-tax' ),
									'placeholder' => '123-4567-89',
									'required'    => true,
									'class'       => array( 'sst-input' ),
								)
							);
							?>

							<?php
							woocommerce_form_field(
								'StateOfIssue',
								array(
									'type'        => 'state',
									'label'       => __( 'ID issued by...', 'simple-sales-tax' ),
									'placeholder' => __( 'Select if your ID is state issued.', 'simple-sales-tax' ),
									'id'          => 'issuing-state',
									'class'       => array( 'sst-hidden-field', 'sst-input' ),
								)
							);
							?>

							<?php
							woocommerce_form_field(
								'PurchaserBusinessType',
								array(
									'type'     => 'select',
									'label'    => __( 'Business Type', 'simple-sales-tax' ),
									'required' => true,
									'class'    => array( 'sst-input' ),
									'options'  => array(
										''                 => __(
											'Select one',
											'simple-sales-tax'
										),
										'AccommodationAndFoodServices' => __(
											'Accommodation And Food Services',
											'simple-sales-tax'
										),
										'Agricultural_Forestry_Fishing_Hunting' => __(
											'Agricultural/Forestry/Fishing/Hunting',
											'simple-sales-tax'
										),
										'Construction'     => __(
											'Construction',
											'simple-sales-tax'
										),
										'FinanceAndInsurance' => __(
											'Finance or Insurance',
											'simple-sales-tax'
										),
										'Information_PublishingAndCommunications' => __(
											'Information Publishing and Communications',
											'simple-sales-tax'
										),
										'Manufacturing'    => __(
											'Manufacturing',
											'simple-sales-tax'
										),
										'Mining'           => __( 'Mining', 'simple-sales-tax' ),
										'RealEstate'       => __(
											'Real Estate',
											'simple-sales-tax'
										),
										'RentalAndLeasing' => __(
											'Rental and Leasing',
											'simple-sales-tax'
										),
										'RetailTrade'      => __(
											'Retail Trade',
											'simple-sales-tax'
										),
										'TransportationAndWarehousing' => __(
											'Transportation and Warehousing',
											'simple-sales-tax'
										),
										'Utilities'        => __(
											'Utilities',
											'simple-sales-tax'
										),
										'WholesaleTrade'   => __(
											'Wholesale Trade',
											'simple-sales-tax'
										),
										'BusinessServices' => __(
											'Business Services',
											'simple-sales-tax'
										),
										'ProfessionalServices' => __(
											'Professional Services',
											'simple-sales-tax'
										),
										'EducationAndHealthCareServices' => __(
											'Education and Health Care Services',
											'simple-sales-tax'
										),
										'NonprofitOrganization' => __(
											'Nonprofit Organization',
											'simple-sales-tax'
										),
										'Government'       => __(
											'Government',
											'simple-sales-tax'
										),
										'NotABusiness'     => __(
											'Not a Business',
											'simple-sales-tax'
										),
										'Other'            => __( 'Other', 'simple-sales-tax' ),
									),
								)
							);
							?>

							<?php
							woocommerce_form_field(
								'PurchaserBusinessTypeOtherValue',
								array(
									'type'        => 'text',
									'label'       => __( 'Please explain', 'simple-sales-tax' ),
									'placeholder' => __( 'Explain the nature of your business.', 'simple-sales-tax' ),
									'id'          => 'business-type-other',
									'class'       => array( 'sst-hidden-field', 'sst-input' ),
								)
							);
							?>

							<?php
							woocommerce_form_field(
								'PurchaserExemptionReason',
								array(
									'type'     => 'select',
									'label'    => __( 'Reason for Exemption', 'simple-sales-tax' ),
									'required' => true,
									'class'    => array( 'sst-input' ),
									'options'  => array(
										''                => __( 'Select one', 'simple-sales-tax' ),
										'FederalGovernmentDepartment' => __(
											'Federal Government Department',
											'simple-sales-tax'
										),
										'StateOrLocalGovernmentName' => __(
											'State Or Local Government',
											'simple-sales-tax'
										),
										'TribalGovernmentName' => __(
											'Tribal Government',
											'simple-sales-tax'
										),
										'ForeignDiplomat' => __(
											'Foreign Diplomat',
											'simple-sales-tax'
										),
										'CharitableOrganization' => __(
											'Charitable Organization',
											'simple-sales-tax'
										),
										'ReligiousOrEducationalOrganization' => __(
											'Religious or Educational Organization',
											'simple-sales-tax'
										),
										'Resale'          => __( 'Resale', 'simple-sales-tax' ),
										'AgriculturalProduction' => __(
											'Agricultural Production',
											'simple-sales-tax'
										),
										'IndustrialProductionOrManufacturing' => __(
											'Industrial Production or Manufacturing',
											'simple-sales-tax'
										),
										'DirectPayPermit' => __(
											'Direct Pay Permit',
											'simple-sales-tax'
										),
										'DirectMail'      => __( 'Direct Mail', 'simple-sales-tax' ),
										'Other'           => __( 'Other', 'simple-sales-tax' ),
									),
								)
							);
							?>

							<?php
							woocommerce_form_field(
								'PurchaserExemptionReasonValue',
								array(
									'type'  => 'text',
									'label' => __( 'Please explain', 'simple-sales-tax' ),
									'id'    => 'exempt-other-reason',
									'class' => array( 'sst-hidden-field', 'sst-input' ),
								)
							);
							?>

							<input type="hidden" name="CertificateID" value="{{{ data.CertificateID }}}">
						</form>
					</article>
					<footer>
						<div class="inner">
							<button id="btn-ok" class="button alt">
								<?php _e( 'Add certificate', 'simple-sales-tax' ); ?>
							</button>
						</div>
					</footer>
				</section>
			</div>
		</div>
		<div class="wc-backbone-modal-backdrop modal-close"></div>
	</script>

<?php require __DIR__ . '/html-view-certificate.php'; ?>
