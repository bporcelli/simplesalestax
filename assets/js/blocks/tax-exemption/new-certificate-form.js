/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { useEffect, useState } from '@wordpress/element';
import { useSelect, useDispatch } from '@wordpress/data';
import { VALIDATION_STORE_KEY } from '@woocommerce/block-data';
import { CheckboxControl } from '@woocommerce/blocks-checkout';
import { RadioControl, ValidatedTextInput } from '@woocommerce/blocks-components';
import Combobox from '@woocommerce/base-components/combobox/index';
import { StateInput } from '@woocommerce/base-components/state-input/index';
import { ALLOWED_STATES } from '@woocommerce/block-settings';

/**
 * Internal dependencies
 */
import {
	BUSINESS_TYPE_OPTIONS,
	EXEMPTION_REASON_OPTIONS
} from './constants';

export const NewCertificateForm = ({ setExtensionData }) => {
	const [ isSinglePurchase, setIsSinglePurchase ] = useState( false );
	const [ exemptState, setExemptState ] = useState( '' );
	const [ taxIdType, setTaxIdType ] = useState( 'StateIssued' );
	const [ stateOfIssue, setStateOfIssue ] = useState( '' );
	const [ taxIDNumber, setTaxIDNumber ] = useState( '' );
	const [ businessType, setBusinessType ] = useState( '' );
	const [
		businessTypeOtherValue,
		setBusinessTypeOtherValue
	] = useState( '' );
	const [ exemptionReason, setExemptionReason ] = useState( '' );
	const [
		exemptionReasonOtherValue,
		setExemptionReasonOtherValue
	] = useState( '' );

	const {
		businessTypeOtherError,
		exemptionReasonOtherError,
	} = useSelect((select) => {
		const store = select( VALIDATION_STORE_KEY );
		return {
			businessTypeOtherError: store.getValidationError('business-type-other-error'),
			exemptionReasonOtherError: store.getValidationError('exemption-reason-other-error'),
		};
	});

	const showError = (error) => !!error?.message && !error?.hidden;

	// TODO: Set extension data as values change for use in checkout

	return (
		<div className="sst-tax-exemption__form">
			{/* TODO: Enable customization in block editor */}
			<p class="sst-tax-exemption__form-disclaimer">
				<strong>{ __( 'WARNING:', 'simple-sales-tax' ) }</strong>
				{ __(
					'You are responsible for knowing if you qualify to claim exemption from tax in the state that is due tax on this sale. You  will be held liable for any tax and interest, as well as civil and criminal penalties imposed by the member state, if you are not eligible to claim this exemption.',
					'simple-sales-tax'
				) }
			</p>
			<CheckboxControl
				id="is-single-purchase"
				checked={ isSinglePurchase }
				onChange={ setIsSinglePurchase }
			>
				{ __( 'This is a single-purchase exemption certificate', 'simple-sales-tax' ) }
			</CheckboxControl>
			<StateInput
				id="exempt-state"
				label={ __( 'Where does this exemption apply?', 'simple-sales-tax' ) }
				country="US"
				states={ ALLOWED_STATES }
				value={ exemptState }
				onChange={ setExemptState }
				required
			/>
			<div
				className="sst-radio-group"
				role="radiogroup"
				ariaLabelledBy="tax-id-type-label"
			>
				<label id="tax-id-type-label">
					{ __( 'Tax ID type', 'simple-sales-tax' ) }
				</label>
				<RadioControl
					id="tax-id-type"
	        selected={ taxIdType }
	        options={ [
	          {
	          	label: __( 'State Issued Exemption ID or Drivers License', 'simple-sales-tax' ),
	          	value: 'StateIssued'
	          },
	          {
	          	label: __( 'Federal Employer ID', 'simple-sales-tax' ),
	          	value: 'FEIN'
	          },
	        ] }
	        onChange={ ( value ) => setTaxIdType( value ) }
	    	/>
    	</div>
    	{taxIdType === 'StateIssued' && (
    		<StateInput
					id="issuing-state"
					label={ __( 'ID issued by...', 'simple-sales-tax' ) }
					country="US"
					states={ ALLOWED_STATES }
					value={ stateOfIssue }
					onChange={ setStateOfIssue }
					required
				/>
  		)}
  		<ValidatedTextInput
				id="tax-id-number"
				errorId="tax-id-number-error"
				label={ __( 'Tax ID', 'simple-sales-tax' ) }
				required
				type="text"
				value={ taxIDNumber }
				onChange={ setTaxIDNumber }
			/>
			<Combobox
				id="business-type"
				label={ __( 'Business type', 'simple-sales-tax' ) }
				onChange={ setBusinessType }
				options={ BUSINESS_TYPE_OPTIONS }
				value={ businessType }
				required
			/>
			{businessType === 'Other' && (
				<ValidatedTextInput
					id="business-type-other"
					errorId="business-type-other-error"
					label={ __( 'Explain the nature of your business', 'simple-sales-tax' ) }
					required
					type="text"
					value={ businessTypeOtherValue }
					onChange={ setBusinessTypeOtherValue }
					showError={ showError( businessTypeOtherError ) }
					errorMessage={
						__( 'Please explain the nature of your business', 'simple-sales-tax' )
					}
				/>
			)}
			<Combobox
				id="exemption-reason"
				label={ __( 'Reason for exemption', 'simple-sales-tax' ) }
				onChange={ setExemptionReason }
				options={ EXEMPTION_REASON_OPTIONS }
				value={ exemptionReason }
				required
			/>
			{exemptionReason === 'Other' && (
				<ValidatedTextInput
					id="exemption-reason-other"
					errorId="exemption-reason-other-error"
					label={ __( 'Please explain', 'simple-sales-tax' ) }
					required
					type="text"
					value={ exemptionReasonOtherValue }
					onChange={ setExemptionReasonOtherValue }
					showError={ showError( exemptionReasonOtherError ) }
					errorMessage={
						__( 'Please explain why you are tax exempt', 'simple-sales-tax' )
					}
				/>
			)}
		</div>
	);
};
