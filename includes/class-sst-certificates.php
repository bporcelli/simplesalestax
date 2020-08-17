<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Certificates.
 *
 * Used for creating, updating, and deleting customer exemption certificates.
 *
 * @author  Simple Sales Tax
 * @package SST
 * @since   5.0
 */
class SST_Certificates {

	/**
	 * Prefix for certificate transients.
	 *
	 * @var string
	 * @since 5.0
	 */
	const TRANS_PREFIX = '_sst_certificates_';

	/**
	 * Get saved exemption certificates for the current customer.
	 *
	 * @param int $user_id WordPress user ID for customer (default: 0).
	 *
	 * @return TaxCloud\ExemptionCertificate[]
	 * @since 5.0
	 */
	public static function get_certificates( $user_id = 0 ) {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		if ( ! $user_id ) {
			return array();
		}

		// Get certificates, using cached certificates if possible.
		$trans_key    = self::get_transient_name( $user_id );
		$raw_certs    = get_transient( $trans_key );
		$certificates = array();

		if ( false !== $raw_certs ) {
			$certificates = json_decode( $raw_certs, true );

			foreach ( $certificates as $key => $certificate ) {
				$certificates[ $key ] = TaxCloud\ExemptionCertificate::fromArray( $certificate );
			}
		} else {
			$certificates = self::fetch_certificates( $user_id );
			self::set_certificates( $user_id, $certificates );
		}

		return $certificates;
	}

	/**
	 * Get a certificate by ID.
	 *
	 * @param string $id      Certificate ID.
	 * @param int    $user_id WordPress user ID (default: 0).
	 *
	 * @return TaxCloud\ExemptionCertificate|NULL
	 * @since 5.0
	 */
	public static function get_certificate( $id, $user_id = 0 ) {
		$certificates = self::get_certificates( $user_id );

		if ( isset( $certificates[ $id ] ) ) {
			return $certificates[ $id ];
		} else {
			return null;
		}
	}

	/**
	 * Get a certificate and return it formatted for display.
	 *
	 * @param string $id      Certificate ID.
	 * @param int    $user_id WordPress user ID (default: 0).
	 *
	 * @return array|NULL
	 * @since 5.0
	 */
	public static function get_certificate_formatted( $id, $user_id = 0 ) {
		$certificate = self::get_certificate( $id, $user_id );

		if ( ! is_null( $certificate ) ) {
			$certificate = self::format_certificate( $certificate );
		}

		return $certificate;
	}

	/**
	 * Format a certificate for display.
	 *
	 * @param TaxCloud\ExemptionCertificate $certificate Exemption certificate to display.
	 *
	 * @return array
	 * @since 5.0
	 */
	protected static function format_certificate( $certificate ) {
		$detail    = $certificate->getDetail();
		$formatted = array(
			'CertificateID'              => $certificate->getCertificateID(),
			'PurchaserName'              => $detail->getPurchaserFirstName() . ' ' . $detail->getPurchaserLastName(),
			'CreatedDate'                => date( 'm/d/Y', strtotime( $detail->getCreatedDate() ) ),
			'PurchaserAddress'           => $detail->getPurchaserAddress1(),
			'PurchaserState'             => sst_prettify( $detail->getPurchaserState() ),
			'PurchaserExemptionReason'   => sst_prettify( $detail->getPurchaserExemptionReason() ),
			'SinglePurchase'             => $detail->getSinglePurchase(),
			'SinglePurchaserOrderNumber' => $detail->getSinglePurchaseOrderNumber(),
			'TaxType'                    => sst_prettify( $detail->getPurchaserTaxID()->getTaxType() ),
			'IDNumber'                   => $detail->getPurchaserTaxID()->getIDNumber(),
			'PurchaserBusinessType'      => sst_prettify( $detail->getPurchaserBusinessType() ),
		);

		return $formatted;
	}

	/**
	 * Get saved exemption certificates for a customer, formatted for display
	 * in the certificate table.
	 *
	 * @param int $user_id WordPress user ID for customer (default: 0).
	 *
	 * @return array
	 * @since 5.0
	 */
	public static function get_certificates_formatted( $user_id = 0 ) {
		$certificates = array();
		foreach ( self::get_certificates( $user_id ) as $id => $raw_cert ) {
			$certificates[ $id ] = self::format_certificate( $raw_cert );
		}

		return $certificates;
	}

	/**
	 * Set saved exemption certificates for a customer.
	 *
	 * @param int                             $user_id      WordPress user ID (default: 0).
	 * @param TaxCloud\ExemptionCertificate[] $certificates Saved certificates for user (default: array()).
	 *
	 * @since 5.0
	 */
	public static function set_certificates( $user_id = 0, $certificates = array() ) {
		set_transient( self::get_transient_name( $user_id ), wp_json_encode( $certificates ), 3 * DAY_IN_SECONDS );
	}

	/**
	 * Get the customer's saved exemption certificates from TaxCloud.
	 *
	 * @param int $user_id WordPress user ID (default: 0).
	 *
	 * @return array
	 * @since 5.0
	 */
	private static function fetch_certificates( $user_id = 0 ) {
		if ( ! $user_id ) {
			$user = wp_get_current_user();
		} else {
			$user = new WP_User( $user_id );
		}

		if ( ! isset( $user->ID ) ) {
			return array(); /* Invalid user ID. */
		}

		try {
			$request = new TaxCloud\Request\GetExemptCertificates(
				SST_Settings::get( 'tc_id' ),
				SST_Settings::get( 'tc_key' ),
				$user->user_login
			);

			$certificates = TaxCloud()->GetExemptCertificates( $request );

			$final_certs = array();

			foreach ( $certificates as $certificate ) {
				$detail = $certificate->getDetail();
				if ( ! $detail->getSinglePurchase() ) { /* Skip single certs */
					$final_certs[ $certificate->getCertificateID() ] = $certificate;
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
	 * @param int $user_id WordPress user ID (default: 0).
	 *
	 * @since 5.0
	 */
	public static function delete_certificates( $user_id = 0 ) {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		delete_transient( self::get_transient_name( $user_id ) );
	}

	/**
	 * Get name of transient where certificates are stored.
	 *
	 * @param int $user_id WordPress user ID.
	 *
	 * @return string
	 * @since 5.0
	 */
	private static function get_transient_name( $user_id ) {
		return self::TRANS_PREFIX . $user_id;
	}

}
