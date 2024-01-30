/**
 * External dependencies
 */
import { useCallback, useEffect } from '@wordpress/element';
import { useDispatch, useSelect } from '@wordpress/data';
import { CHECKOUT_STORE_KEY } from '@woocommerce/block-data';

const EXTENSION_NAMESPACE = 'simple-sales-tax';

export const useExtensionState = ( key, initialValue ) => {
	const { __internalSetExtensionData } = useDispatch( CHECKOUT_STORE_KEY );

	const value = useSelect( ( select ) => {
		const store = select( CHECKOUT_STORE_KEY );
		const extensionData = store.getExtensionData();
		return extensionData[ EXTENSION_NAMESPACE ]?.[ key ] ?? initialValue;
	} );

	const setValue = useCallback(
		( value ) => {
			__internalSetExtensionData( EXTENSION_NAMESPACE, {
				[ key ]: value,
			} );
		},
		[ __internalSetExtensionData ]
	);

	useEffect( () => {
		setValue( initialValue );
	}, [ initialValue, setValue ] );

	return [ value, setValue ];
};
