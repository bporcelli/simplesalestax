/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

export const BUSINESS_TYPE_OPTIONS = [
	{
		value: '',
		label: __( 'Select a business type', 'simple-sales-tax' ),
		disabled: true,
	},
	{
		value: 'AccommodationAndFoodServices',
		label: __( 'Accommodation And Food Services', 'simple-sales-tax' ),
	},
	{
		value: 'Agricultural_Forestry_Fishing_Hunting',
		label: __(
			'Agricultural/Forestry/Fishing/Hunting',
			'simple-sales-tax'
		),
	},
	{
		value: 'Construction',
		label: __( 'Construction', 'simple-sales-tax' ),
	},
	{
		value: 'FinanceAndInsurance',
		label: __( 'Finance or Insurance', 'simple-sales-tax' ),
	},
	{
		value: 'Information_PublishingAndCommunications',
		label: __(
			'Information Publishing and Communications',
			'simple-sales-tax'
		),
	},
	{
		value: 'Manufacturing',
		label: __( 'Manufacturing', 'simple-sales-tax' ),
	},
	{
		value: 'Mining',
		label: __( 'Mining', 'simple-sales-tax' ),
	},
	{
		value: 'RealEstate',
		label: __( 'Real Estate', 'simple-sales-tax' ),
	},
	{
		value: 'RentalAndLeasing',
		label: __( 'Rental and Leasing', 'simple-sales-tax' ),
	},
	{
		value: 'RetailTrade',
		label: __( 'Retail Trade', 'simple-sales-tax' ),
	},
	{
		value: 'TransportationAndWarehousing',
		label: __( 'Transportation and Warehousing', 'simple-sales-tax' ),
	},
	{
		value: 'Utilities',
		label: __( 'Utilities', 'simple-sales-tax' ),
	},
	{
		value: 'WholesaleTrade',
		label: __( 'Wholesale Trade', 'simple-sales-tax' ),
	},
	{
		value: 'BusinessServices',
		label: __( 'Business Services', 'simple-sales-tax' ),
	},
	{
		value: 'ProfessionalServices',
		label: __( 'Professional Services', 'simple-sales-tax' ),
	},
	{
		value: 'EducationAndHealthCareServices',
		label: __( 'Education and Health Care Services', 'simple-sales-tax' ),
	},
	{
		value: 'NonprofitOrganization',
		label: __( 'Nonprofit Organization', 'simple-sales-tax' ),
	},
	{
		value: 'Government',
		label: __( 'Government', 'simple-sales-tax' ),
	},
	{
		value: 'NotABusiness',
		label: __( 'Not a Business', 'simple-sales-tax' ),
	},
	{
		value: 'Other',
		label: __( 'Other', 'simple-sales-tax' ),
	},
];

export const EXEMPTION_REASON_OPTIONS = [
	{
		value: '',
		label: __( 'Select a reason', 'simple-sales-tax' ),
		disabled: true,
	},
	{
		value: 'FederalGovernmentDepartment',
		label: __( 'Federal Government Department', 'simple-sales-tax' ),
	},
	{
		value: 'StateOrLocalGovernmentName',
		label: __( 'State Or Local Government', 'simple-sales-tax' ),
	},
	{
		value: 'TribalGovernmentName',
		label: __( 'Tribal Government', 'simple-sales-tax' ),
	},
	{
		value: 'ForeignDiplomat',
		label: __( 'Foreign Diplomat', 'simple-sales-tax' ),
	},
	{
		value: 'CharitableOrganization',
		label: __( 'Charitable Organization', 'simple-sales-tax' ),
	},
	{
		value: 'ReligiousOrEducationalOrganization',
		label: __(
			'Religious or Educational Organization',
			'simple-sales-tax'
		),
	},
	{
		value: 'Resale',
		label: __( 'Resale', 'simple-sales-tax' ),
	},
	{
		value: 'AgriculturalProduction',
		label: __( 'Agricultural Production', 'simple-sales-tax' ),
	},
	{
		value: 'IndustrialProductionOrManufacturing',
		label: __(
			'Industrial Production or Manufacturing',
			'simple-sales-tax'
		),
	},
	{
		value: 'DirectPayPermit',
		label: __( 'Direct Pay Permit', 'simple-sales-tax' ),
	},
	{
		value: 'DirectMail',
		label: __( 'Direct Mail', 'simple-sales-tax' ),
	},
	{
		value: 'Other',
		label: __( 'Other', 'simple-sales-tax' ),
	},
];
