<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Exemption Certificate.
 *
 * Represents a TaxCloud exemption certificate.
 *
 * @author 	Simple Sales Tax
 * @package SST
 * @since 	5.0
 */
class WT_Exemption_Certificate {
	
	/**
	 * @var array String names of "ugly" keywords that need to be prettified.
	 * @since 5.0
	 */
	private static $ugly_keywords = array( 
		'PurchaserState', 
		'PurchaserExemptionReason', 
		'PurchaserBusinessType' 
	);

	/**
	 * @var array Map from ugly keywords to pretty keywords.
	 * @since 5.0
	 */
	private static $pretty_keywords = array(
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

	/**
	 * @var string The certificate's ID.
	 * @since 5.0
	 */
	private $CertificateID = NULL;

	/**
	 * @var array Certificate detail.
	 * @since 5.0
	 */
	private $Detail = array(
		'ExemptStates'                    => array(),
		'SinglePurchase'                  => false,
		'SinglePurchaseOrderNumber'       => '',
		'PurchaserFirstName'              => '',
		'PurchaserLastName'               => '',
		'PurchaserTitle'                  => '',
		'PurchaserAddress1'               => '',
		'PurchaserAddress2'               => '',
		'PurchaserCity'                   => '',
		'PurchaserState'                  => '',
		'PurchaserZip'                    => '',
		'PurchaserTaxID'                  => array(),
		'PurchaserBusinessType'           => '',
		'PurchaserBusinessTypeOtherValue' => '',
		'PurchaserExemptionReason'        => '',
		'PurchaserExemptionReasonValue'   => '',
		'CreatedDate'                     => '',
	);

	/**
	 * Constructor.
	 *
	 * @since 4.2
	 */
	public function __construct() {
		$this->CreatedDate = date( DateTime::ATOM );
	}

	/**
	 * Constructs a WT_Exemption_Certificate given a stdClass object.
	 *
	 * @since 5.0
	 *
	 * @param  object $obj stdClass object representing an exemption certificate.
	 * @return WT_Exemption_Certificate
	 */
	public static function fromArray( $obj ) {
		$cert = new self();

		// Set Certificate ID
		$cert->CertificateID = $obj->CertificateID;

		// Set all other fields
		$detail = (array) $obj->Detail;
		
		foreach ( $detail as $name => $value )
			$cert->$name = $value;

		return $cert;
	}

	/**
	 * Returns this WT_Exemption_Certificate cast to an array.
	 *
	 * @since 4.2
	 *
	 * @return array 
	 */
	public function toArray() {
		return array(
			'CertificateID' => $this->CertificateID,
			'Detail'        => $this->Detail,
		);
	}

	/**
	 * Is this a single purchase certificate?
	 *
	 * @since 5.0
	 *
	 * @return bool
	 */
	public function is_single() {
		return $this->SinglePurchase;
	}

	/**
	 * Return the "view" URL for the certificate. This URL is accessed to
	 * display a preview of the certificate.
	 *
	 * @since 5.0
	 *
	 * @return string
	 */
	public function get_view_url() {
		$view_format = add_query_arg( array(
			'action' => 'wootax-view-certificate',
			'certID' => '%s',
		), admin_url( 'admin-ajax.php' ) );

		return sprintf( $view_format, $this->CertificateID );
	}

	/**
	 * Get the pretty keyword corresponding to a given ugly keyword.
	 *
	 * @since 5.0
	 *
	 * @param  string $ugly The ugly keyword.
	 * @return string The pretty keyword corresponding to the ugly keyword, or
	 * the ugly keyword if no pretty keyword exists.
	 */
	private static function get_pretty_word( $ugly ) {
		if ( array_key_exists( $ugly, self::$pretty_keywords ) )
			return self::$pretty_keywords[ $ugly ];
		else
			return $ugly;
	}

	/**
	 * Setter.
	 *
	 * @since 4.6
	 * @param mixed $key
	 * @param mixed $value
	 */
	public function __set( $key, $value ) {
		switch ( $key ) {
			case 'CertificateID':
				$this->CertificateID = $value;
				break;
			default:
				if ( array_key_exists( $key, $this->Detail ) )
					$this->Detail[ $key ] = $value;
		}
	}

	/**
	 * Getter.
	 * 
	 * @since 4.6
	 * @param mixed $key
	 */
	public function __get( $key ) {
		switch ( $key ) {
			case 'PurchaserName':
				return $this->PurchaserFirstName . " " . $this->PurchaserLastName;
			case 'CertificateID':
				return $this->CertificateID;
			default:
				if ( isset( $this->Detail[ $key ] ) )
					return in_array( $key, self::$ugly_keywords ) ? self::get_pretty_word( $this->Detail[ $key ] ) : $this->Detail[ $key ];
		}

		return null;
	}

}