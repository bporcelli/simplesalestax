<?php

/**
 * SST functions.
 *
 * Utility functions used throughout the plugin.
 *
 * @author 	Simple Sales Tax
 * @package SST
 * @since 	5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Output HTML for a help tip.
 *
 * @since 5.0
 *
 * @param string $tip Tooltip content.
 */
function sst_tip( $tip ) {
	if ( function_exists( 'wc_help_tip' ) ) {
		echo wc_help_tip( $tip );
	} else {
		$img_path = WC()->plugin_url() . '/assets/images/help.png';
		$format = '<img class="help_tip" data-tip="%s" src="%s" height="16" width="16" />';
		printf( $format, $tip, $img_path );
	}
}

/**
 * Given an "ugly" string, return the corresponding "pretty" string.
 *
 * @since 5.0
 *
 * @param  string $ugly
 * @return Pretty string if found, otherwise original string.
 */
function sst_prettify( $ugly ) {
	// Map from ugly string to pretty strings
	$ugly_strings = array(
		'AccommodationAndFoodServices'            => 'Accommodation and Food Services',
	    'Agricultural_Forestry_Fishing_Hunting'   => 'Agricultural/Forestry/Fishing/Hunting',
	    'FinanceAndInsurance'                     => 'Finance and Insurance',
	    'Information_PublishingAndCommunications' => 'Information Publishing and Communications',
	    'RealEstate'                              => 'Real Estate',
	    'RentalAndLeasing'                        => 'Rental and Leasing',
	    'RetailTrade'                             => 'Retail Trade',
	    'TransportationAndWarehousing'            => 'Transportation and Warehousing',
	    'WholesaleTrade'                          => 'Wholesale Trade',
	    'BusinessServices'                        => 'Business Services',
	    'ProfessionalServices'                    => 'Professional Services',
	    'EducationAndHealthCareServices'          => 'Education and Health Care Services',
	    'NonprofitOrganization'                   => 'Nonprofit Organization',
	    'NotABusiness'                            => 'Not a Business',
	    'FederalGovernmentDepartment'             => 'Federal Government Department',
	    'StateOrLocalGovernmentName'              => 'State or Local Government',
	    'TribalGovernmentName'                    => 'Tribal Government',
	    'ForeignDiplomat'                         => 'Foreign Diplomat',
	    'CharitableOrganization'                  => 'Charitable Organization',
	    'ReligiousOrEducationalOrganization'      => 'Religious or Educational Organization',
	    'AgriculturalProduction'                  => 'Agricultural Production',
	    'IndustrialProductionOrManufacturing'     => 'Industrial Production or Manufacturing',
	    'DirectPayPermit'                         => 'Direct Pay Permit',
	    'DirectMail'                              => 'Direct Mail',
	    'AL'                                      => 'Alabama',
	    'AK'                                      => 'Alaska',
	    'AZ'                                      => 'Arizona',
	    'AR'                                      => 'Arkansas',
	    'CA'                                      => 'California',
	    'CO'                                      => 'Colorado',
	    'CT'                                      => 'Connecticut',
	    'DE'                                      => 'Delaware',
	    'FL'                                      => 'Florida',
	    'GA'                                      => 'Georgia',
	    'HI'                                      => 'Hawaii',
	    'ID'                                      => 'Idaho',
	    'IL'                                      => 'Illinois',
	    'IN'                                      => 'Indiana',
	    'IA'                                      => 'Iowa',
	    'KS'                                      => 'Kansas',
	    'KY'                                      => 'Kentucky',
	    'LA'                                      => 'Louisiana',
	    'ME'                                      => 'Maine',
	    'MD'                                      => 'Maryland',
	    'MA'                                      => 'Massachusetts',
	    'MI'                                      => 'Michigan',
	    'MN'                                      => 'Minnesota',
	    'MS'                                      => 'Mississippi',
	    'MO'                                      => 'Missouri',
	    'MT'                                      => 'Montana',
	    'NE'                                      => 'Nebraska',
	    'NV'                                      => 'Nevada',
	    'NH'                                      => 'New Hampshire',
	    'NJ'                                      => 'New Jersey',
	    'NM'                                      => 'New Mexico',
	    'NY'                                      => 'New York',
	    'NC'                                      => 'North Carolina',
	    'ND'                                      => 'North Dakota',
	    'OH'                                      => 'Ohio',
	    'OK'                                      => 'Oklahoma',
	    'OR'                                      => 'Oregon',
	    'PA'                                      => 'Pennsylvania',
	    'RI'                                      => 'Rhode Island',
	    'SC'                                      => 'South Carolina',
	    'SD'                                      => 'South Dakota',
	    'TN'                                      => 'Tennessee',
	    'TX'                                      => 'Texas',
	    'UT'                                      => 'Utah',
	    'VT'                                      => 'Vermont',
	    'VA'                                      => 'Virginia',
	    'WA'                                      => 'Washington',
	    'DC'                                      => 'Washington DC',
	    'WV'                                      => 'West Virginia',
	    'WI'                                      => 'Wisconsin',
	    'WY'                                      => 'Wyoming',
	);

	if ( array_key_exists( $ugly, $ugly_strings ) )
		return $ugly_strings[ $ugly ];
	else
		return $ugly;
}

/**
 * Create a new shipping package from the given array, using default values
 * for all keys that are omitted.
 *
 * @since 5.0
 *
 * @param  array $package
 * @return array
 */
function sst_create_package( $package = array() ) {
	$defaults = array(
		'contents'    => array(),
		'fees'        => array(),
		'shipping'    => null,
		'map'         => array(),
		'user'        => array(),
		'request'     => null,
		'response'    => null,
		'origin'      => null,
		'destination' => null,
		'certificate' => null,
	);

	return wp_parse_args( $package, $defaults );
}

/**
 * Strip all slashes from a given value.
 *
 * @since 5.4
 *
 * @param  string $value
 * @return string
 */
function sst_unslash( $value ) {
	while ( strstr( $value, '\\\\' ) ) {
		$value = stripslashes( $value );
	}
	return $value;
}

/**
 * Return an API client instance.
 *
 * @since 5.0
 *
 * @return Client
 */
function TaxCloud() {
	return new TaxCloud\Client();
}