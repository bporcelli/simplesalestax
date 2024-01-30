/**
 * External dependencies
 */
import { useBlockProps } from '@wordpress/block-editor';
import { Disabled } from '@wordpress/components';

/**
 * Internal dependencies
 */
import './style.scss';
import Block from './block';

export const Edit = () => {
	const blockProps = useBlockProps();
	return (
		<div { ...blockProps }>
			<Disabled>
				<Block />
			</Disabled>
		</div>
	);
};

export const Save = () => {
	return (
		<div
			data-block-name="simple-sales-tax/tax-exemption"
			{ ...useBlockProps.save() }
		/>
	);
};
