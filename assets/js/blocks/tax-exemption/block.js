/**
 * External dependencies
 */
import { useEffect, useState, useRef } from '@wordpress/element';
import { useDispatch } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { getSetting } from '@woocommerce/settings';
import Combobox from '@woocommerce/base-components/combobox/index';
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
	} ) );

/**
 * Internal dependencies
 */
import { NewCertificateForm } from './new-certificate-form';

const Block = ( { className, children, checkoutExtensionData } ) => {
	const [ value, setValue ] = useState( selectedCertificate );
	const { setExtensionData } = checkoutExtensionData;
	const initialized = useRef( false );

	const {
		__internalIncrementCalculating,
		__internalDecrementCalculating
	} = useDispatch( CHECKOUT_STORE_KEY );

	useEffect( () => {
		setExtensionData( 'simple-sales-tax', 'certificate_id', value );

		// Taxes will already be calculated on initial render, so we
		// only recalc if value is changed therefater.
		if ( initialized.current ) {
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
		}

		initialized.current = true;
	}, [ setExtensionData, value, initialized ] );

	const onChangeCertificate = ( value ) => {
		setValue( value );
	};

	return (
		<div className={ className }>
			<Title headingLevel="2">
				{ __( 'Tax exemption', 'simple-sales-tax' ) }
			</Title>

			{ !isUserLoggedIn && (
				<p>{ __( 'Please log in or register to apply an exemption certificate.', 'simple-sales-tax' ) }</p>
			) }

			{ isUserLoggedIn && (
				<>
					<Combobox
						id="exemption-certificate-input"
						label={ __( 'Exemption certificate', 'simple-sales-tax' ) }
						onChange={ onChangeCertificate }
						options={ options }
						value={ value }
					/>
					<a href={ myAccountEndpointUrl } target="_blank">
						{ __( 'Manage exemption certificates →', 'simple-sales-tax' ) }
					</a>
					{ value === 'new' && <NewCertificateForm /> }
				</>
			) }
		</div>
	);
};

export default Block;
