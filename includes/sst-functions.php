<?php
/**
 * SST functions.
 *
 * Utility functions used throughout the plugin.
 *
 * @author  Simple Sales Tax
 * @package SST
 * @since   5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Output HTML for a help tip.
 *
 * @param string $tip Tooltip content.
 *
 * @since 5.0
 */
function sst_tip( $tip ) {
	if ( function_exists( 'wc_help_tip' ) ) {
		echo wc_help_tip( $tip ); // phpcs:ignore WordPress.Security.EscapeOutput
	} else {
		$img_path = WC()->plugin_url() . '/assets/images/help.png';
		$format   = '<img class="help_tip" data-tip="%s" src="%s" height="16" width="16" />';
		printf( $format, esc_attr( $tip ), esc_url( $img_path ) ); // phpcs:ignore WordPress.Security.EscapeOutput
	}
}

/**
 * Given an "ugly" string, return the corresponding "pretty" string.
 *
 * @param string $ugly Ugly string to get pretty equivalent for.
 *
 * @return string Pretty string if found, otherwise original string.
 * @since 5.0
 */
function sst_prettify( $ugly ) {
	// Map from ugly string to pretty strings.
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

	if ( array_key_exists( $ugly, $ugly_strings ) ) {
		return $ugly_strings[ $ugly ];
	} else {
		return $ugly;
	}
}

/**
 * Create a new shipping package from the given array, using default values
 * for all keys that are omitted.
 *
 * @param array $package Initial values for package.
 *
 * @return array
 * @since 5.0
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
 * @param string $value Value to strip slashes from.
 *
 * @return string
 * @since 5.4
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
 * @return \TaxCloud\Client
 * @since 5.0
 */
function TaxCloud() {
	return new TaxCloud\Client();
}

/**
 * Returns a list of all available TICs. The list will be updated if it is more
 * than one week old.
 *
 * @return SST_TIC[]
 * @since 5.9
 */
function sst_get_tics() {
	$tics = get_transient( 'sst_tics' );

	if ( false === $tics ) {
		$tics = array();

		try {
			$tics = TaxCloud()->GetTICs(
				new \TaxCloud\Request\GetTICs( SST_Settings::get( 'tc_id' ), SST_Settings::get( 'tc_key' ) )
			);

			set_transient( 'sst_tics', $tics, WEEK_IN_SECONDS );
		} catch ( Exception $ex ) {
			wc_get_logger()->error( "Failed to update TaxCloud TICs: {$ex->getMessage()}" );
		}
	}

	foreach ( $tics as $id => $description ) {
		$tics[ $id ] = new SST_TIC( $id, $description );
	}

	return $tics;
}

/**
 * Calculates the taxes for an order using the TaxCloud API.
 *
 * @param WC_Order|int $order Order object or order ID.
 *
 * @return bool|WP_Error True on success, WP_Error instance on failure.
 */
function sst_order_calculate_taxes( $order ) {
	if ( is_numeric( $order ) ) {
		$order = wc_get_order( $order );
	}

	if ( ! is_a( $order, 'WC_Order' ) ) {
		return new WP_Error( 'invalid_order', 'Invalid order.' );
	}

	$_order = new SST_Order( $order );

	try {
		$_order->calculate_taxes();
		$_order->calculate_totals( false );
	} catch ( Exception $ex ) {
		return new WP_Error( 'calculate_error', $ex->getMessage() );
	}

	return true;
}

/**
 * Transforms a list of WooCommerce order items into a format that the SST tax
 * calculation logic can understand.
 *
 * @param WC_Order_Item[] $items Order items.
 *
 * @return array Order items formatted for tax calculations
 */
function sst_format_order_items( $items ) {
	$new_items = array();

	foreach ( $items as $item_id => $item ) {
		$product_id = $item['variation_id'] ? $item['variation_id'] : $item['product_id'];
		$product    = wc_get_product( $product_id );
		if ( $product ) {
			$new_items[ $item_id ] = array(
				'key'           => $item_id,
				'product_id'    => $item['product_id'],
				'variation_id'  => $item['variation_id'],
				'quantity'      => $item['qty'],
				'line_total'    => $item['line_total'],
				'line_subtotal' => $item['line_subtotal'],
				'data'          => $product,
			);
		}
	}

	return $new_items;
}
