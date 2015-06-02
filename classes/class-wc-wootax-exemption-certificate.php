<?php

/**
 * Represents a TaxCloud exemption certificate
 *
 * @package WooCommerce TaxCloud
 * @since 4.2
 */

class WC_WooTax_Exemption_Certificate {
	public $ExemptStates = array();
	public $CertificateID = NULL;
	public $SinglePurchase;
	public $SinglePurchaseOrderNumber = NULL;
	public $PurchaserFirstName;
	public $PurchaserLastName;
	public $PurchaserTitle;
	public $PurchaserAddress1;
	public $PurchaserAddress2 = NULL;
	public $PurchaserCity;
	public $PurchaserState;
	public $PurchaserZip;
	public $PurchaserTaxID;
	public $PurchaserBusinessType;
	public $PurchaserBusinessTypeOtherValue;
	public $PurchaserExemptionReason;
	public $PurchaserExemptionReasonValue;
	public $CreatedDate;

	// Set up CreatedDate when object is constructed
	public function __construct() {
		$this->CreatedDate = new DateTime( 'NOW' );
		$this->CreatedDate = $this->CreatedDate->format( DateTime::ATOM );
	}

	// Return formatted certificate ready to be sent to TaxCloud
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