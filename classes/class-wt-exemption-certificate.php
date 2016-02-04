<?php

/**
 * Represents a TaxCloud exemption certificate
 *
 * @author Brett Porcelli
 * @since 1.0
 */

class WT_Exemption_Certificate {
	/** Certificate ID */
	private $CertificateID = NULL;

	/** States where the certificate applies */
	private $ExemptStates = array();

	/** Is this a single use certificate? */
	private $SinglePurchase = false;

	/** If single use, the order number that the certificate was used for */
	private $SinglePurchaseOrderNumber = NULL;

	/** First name of certificate holder */
	private $PurchaserFirstName;

	/** Last name of certificate holder */
	private $PurchaserLastName;

	/** Title of certificate holder */
	private $PurchaserTitle;

	/** Street address */
	private $PurchaserAddress1;

	/** Optional apartment/suite number */
	private $PurchaserAddress2 = NULL;

	/** City of certificate holder */
	private $PurchaserCity;

	/** State of certificate holder */
	private $PurchaserState;

	/** ZIP code (5-digit) of certificate holder */
	private $PurchaserZip;

	/** Tax ID of certificate holder */
	private $PurchaserTaxID;

	/** Business type */
	private $PurchaserBusinessType;
	private $PurchaserBusinessTypeOtherValue;

	/** Reason for exemption */
	private $PurchaserExemptionReason;
	private $PurchaserExemptionReasonValue;

	/** Date certificate was created */
	private $CreatedDate;

	/**
	 * Constructor
	 * Set CreatedDate property
	 *
	 * @since 4.2
	 */
	public function __construct() {
		$this->CreatedDate = date( DateTime::ATOM );
	}

	/**
	 * Setter
	 *
	 * @since 4.6
	 */
	public function __set( $key, $value ) {
		if ( $key == 'PurchaserZip' && strlen( $value ) > 5 ) {
			$value = substr( $value, 0, 5 );
		}

		$this->$key = $value;
	}

	/**
	 * Getter
	 * 
	 * @since 4.6
	 */
	public function __get( $key ) {
		return isset( $this->$key ) ? $this->$key : NULL;
	}

	/**
	 * Returns certificate in TaxCloud-friendly format
	 *
	 * @since 4.2
	 */
	public function get_formatted_certificate() {
		global $woocommerce, $current_user;

		$customer_id = is_user_logged_in() ? $current_user->user_login : $woocommerce->session->get_customer_id();

		return array(
			'customerID' => $customer_id,
			'exemptCert' => array(
				'CertificateID' => $this->CertificateID,
				'Detail'        => array(
					'ExemptStates'                    => $this->ExemptStates,
					'SinglePurchase'                  => $this->SinglePurchase,
					'SinglePurchaseOrderNumber'       => $this->SinglePurchaseOrderNumber,
					'PurchaserFirstName'              => $this->PurchaserFirstName,
					'PurchaserLastName'               => $this->PurchaserLastName,
					'PurchaserTitle'                  => $this->PurchaserTitle,
					'PurchaserAddress1'               => $this->PurchaserAddress1,
					'PurchaserAddress2'               => $this->PurchaserAddress2,
					'PurchaserCity'                   => $this->PurchaserCity,
					'PurchaserState'                  => $this->PurchaserState,
					'PurchaserZip'                    => $this->PurchaserZip,
					'PurchaserTaxID'                  => $this->PurchaserTaxID,
					'PurchaserBusinessType'           => $this->PurchaserBusinessType,
					'PurchaserBusinessTypeOtherValue' => $this->PurchaserBusinessTypeOtherValue,
					'PurchaserExemptionReason'        => $this->PurchaserExemptionReason,
					'PurchaserExemptionReasonValue'   => $this->PurchaserExemptionReasonValue,
					'CreatedDate'                     => $this->CreatedDate,
				),
			),
		);
	}
}