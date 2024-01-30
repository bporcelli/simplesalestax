/**
 * External dependencies
 */
import {
	useBlockProps,
} from '@wordpress/block-editor';
import { Disabled } from '@wordpress/components';

/**
 * Internal dependencies
 */
import './style.scss';
import Block from './block';

export const Edit = () => {
	const blockProps = useBlockProps();
	const checkoutExtensionData = { setExtensionData: () => undefined };
	return (
		<div {...blockProps}>
			<Disabled>
				<Block checkoutExtensionData={checkoutExtensionData} />
			</Disabled>
		</div>
	);
};

export const Save = () => {
	return (
		<div data-block-name="simple-sales-tax/tax-exemption" {...useBlockProps.save()} />
	);
};
