/**
 * External dependencies
 */
import { useDispatch } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { getSetting } from '@woocommerce/settings';
import { Select } from '@woocommerce/base-components/select/index';
import { Title } from '@woocommerce/blocks-components';
import { extensionCartUpdate } from '@woocommerce/blocks-checkout';
import { CHECKOUT_STORE_KEY } from '@woocommerce/block-data';

const {
	showExemptionForm,
	certificateOptions,
	selectedCertificate,
	isUserLoggedIn,
	myAccountEndpointUrl,
} = getSetting( 'simple-sales-tax_data', '' );

const options = Object.entries( certificateOptions ).map(
	( [ value, label ] ) => ( {
		value,
		label,
	} )
);

/**
 * Internal dependencies
 */
import { NewCertificateForm } from './new-certificate-form';
import { useExtensionState } from './use-extension-state';

const Block = ( { className, children } ) => {
	const [ certificateId, setCertificateId ] = useExtensionState(
		'certificate_id',
		selectedCertificate
	);

	const { __internalIncrementCalculating, __internalDecrementCalculating } =
		useDispatch( CHECKOUT_STORE_KEY );

	const onChangeCertificate = ( value ) => {
		// Update value
		setCertificateId( value );

		// Recalculate taxes
		__internalIncrementCalculating();

		extensionCartUpdate( {
			namespace: 'simple-sales-tax',
			data: {
				action: 'recalculate',
				certificate_id: value === 'none' ? '' : value,
			},
		} ).finally( () => {
			__internalDecrementCalculating();
		} );
	};

  // TODO: Save cert ID in session or send in request when updating shipping
  // methods so tax isn't re-applied when shipping method or customer info
  // changes.
  // TODO: Pre-order validation and checkout processing (ideally reuse what
  // we have today)

	return (
		<div className={ className }>
			<Title headingLevel="2">
				{ __( 'Tax exemption', 'simple-sales-tax' ) }
			</Title>

			{ ! isUserLoggedIn && (
				<p>
					{ __(
						'Please log in or register to apply an exemption certificate.',
						'simple-sales-tax'
					) }
				</p>
			) }

			{ isUserLoggedIn && (
				<>
					<Select
						id="exemption-certificate-input"
						label={ __(
							'Exemption certificate',
							'simple-sales-tax'
						) }
						onChange={ onChangeCertificate }
						options={ options }
						value={ certificateId }
					/>
					<a href={ myAccountEndpointUrl } target="_blank">
						{ __(
							'Manage exemption certificates →',
							'simple-sales-tax'
						) }
					</a>
					{ certificateId === 'new' && <NewCertificateForm /> }
				</>
			) }
		</div>
	);
};

export default Block;
