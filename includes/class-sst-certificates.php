<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Certificates.
 *
 * Used for creating, updating, and deleting customer exemption certificates.
 *
 * @author 	Simple Sales Tax
 * @package SST
 * @since 	5.0
 */
class SST_Certificates {

	/**
	 * @var string Transient prefix.
	 * @since 5.0
	 */
	const TRANS_PREFIX = '_sst_certificates_';

	/**
	 * Get saved exemption certificates for the current customer.
	 *
	 * @since 5.0
	 *
	 * @param  bool $include_single Should single use certificates be returned? (default: true)
	 * @return ExemptionCertificate[]
	 */
	public static function get_certificates( $include_single = true ) {
		if ( ! is_user_logged_in() ) {
			return array();
		}
		
		// Get certificates, using cached certificates if possible
		$trans_key    = self::get_transient_name();
		$raw_certs    = get_transient( $trans_key );
		$certificates = array();

		if ( false !== $raw_certs ) {
			$certificates = json_decode( $raw_certs, true );
			
			foreach ( $certificates as $key => $certificate ) {
				$certificates[ $key ] = TaxCloud\ExemptionCertificate::fromArray( $certificate );
			}
		} else {
			$certificates = self::fetch_certificates();
			self::set_certificates( $certificates );
		}

		// Filter single certs if necessary
		foreach ( $certificates as $id => $certificate ) {
			if ( ! $include_single && $certificate->getDetail()->getSinglePurchase() )
				unset( $certificates[ $id ] );
		}

		return $certificates;
	}

	/**
	 * Get a certificate by ID.
	 *
	 * @since 5.0
	 *
	 * @param  string $id Certificate ID.
	 * @return ExemptionCertificate|NULL
	 */
	public static function get_certificate( $id ) {
		$certificates = self::get_certificates();

		if ( isset( $certificates[ $id ] ) ) {
			return $certificates[ $id ];
		} else {
			return NULL;
		}
	}
	
	/**
	 * Get saved exemption certificates for the current customer, formatted
	 * for display in the certificate table.
	 *
	 * @since 5.0
	 *
	 * @param  bool $include_single Should single use certificates be returned? (default: true)
	 * @return array()
	 */
	public static function get_certificates_formatted( $include_single = true ) {
		$certificates = array();

		foreach ( self::get_certificates( $include_single ) as $id => $raw_cert ) {
			$detail              = $raw_cert->getDetail();
			$certificates[ $id ] = array(
				'CertificateID'              => $id,
				'PurchaserName'              => $detail->getPurchaserFirstName() . ' ' . $detail->getPurchaserLastName(),
				'CreatedDate'                => date( 'm/d/Y', strtotime( $detail->getCreatedDate() ) ),
				'PurchaserAddress'           => $detail->getPurchaserAddress1(),
				'PurchaserState'             => sst_prettify( $detail->getPurchaserState() ),
				'PurchaserExemptionReason'   => sst_prettify( $detail->getPurchaserExemptionReason() ),
				'SinglePurchase'             => $detail->getSinglePurchase(),
				'SinglePurchaserOrderNumber' => $detail->getSinglePurchaseOrderNumber(),
				'TaxType'                    => sst_prettify( $detail->getPurchaserTaxID()->getTaxType() ),
				'IDNumber'                   => $detail->getPurchaserTaxID()->getIDNumber(),
				'PurchaserBusinessType'      => sst_prettify( $detail->getPurchaserBusinessType() )
			);
		}

		return $certificates;
	}

	/**
	 * Set saved exemption certificates for the current customer.
	 *
	 * @since 5.0
	 *
	 * @param ExemptionCertificate[] $certificates (default: array()).
	 */
	public static function set_certificates( $certificates = array() ) {
		set_transient( self::get_transient_name(), json_encode( $certificates ), 3 * DAY_IN_SECONDS );
	}

	/**
	 * Get the customer's saved exemption certificates from TaxCloud.
	 *
	 * @since 5.0
	 *
	 * @return array
	 */
	private static function fetch_certificates() {
		$user = wp_get_current_user();
	
		$request = new TaxCloud\Request\GetExemptCertificates(
			SST_Settings::get( 'tc_id' ),
			SST_Settings::get( 'tc_key' ),
			$user->user_login
		);
		
		try {
			$certificates = TaxCloud()->GetExemptCertificates( $request );

			$final_certs = array();
			
			foreach ( $certificates as $certificate ) {
				$detail = $certificate->getDetail();
				if ( ! $detail->getSinglePurchase() )
					$final_certs[ $certificate->getCertificateID() ] = $certificate;
			 	else
					$final_certs[ $detail->getSinglePurchaseOrderNumber() ] = $certificate;
			}

			foreach ( $final_certs as $key => $cert ) {
				if ( $key !== $cert->getCertificateID() ) {
					$final_certs[ $cert->getCertificateID() ] = $cert;
					unset( $final_certs[ $key ] );
				}
			}

			return $final_certs;
		} catch ( Exception $ex ) {
			return array();
		}
	}

	/**
	 * Delete the customer's cached certificates.
	 *
	 * @since 5.0
	 */
	public static function delete_certificates() {
		delete_transient( self::get_transient_name() );
	}

	/**
	 * Get name of transient where certificates are stored.
	 *
	 * @since 5.0
	 *
	 * @return string
	 */
	private static function get_transient_name() {
		return self::TRANS_PREFIX . get_current_user_id();
	}
}

new SST_Certificates();