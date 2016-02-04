<?php

/**
 * Includes methods relevant to tax exemptions feature
 *
 * @package WooTax
 * @since 4.2
 */

/**
 * Retrieve the exemption certificate associated with an order
 *
 * @param (int) $order_id - order ID, if applicable
 * @return (mixed) array representing certificate for single certs, certificate ID for blanket certs, or NULL
 */
function wt_get_exemption_certificate( $order_id = null ) {
	$certificate = NULL;
		
	if ( !$order_id && WC()->session->get( 'certificate_id' ) ) {
		if ( WC()->session->get( 'certificate_id' ) == 'true' ) {
			$certificate = WC()->session->get( 'certificate_data' );

			if ( !isset( $certificate['Detail']['SinglePurchaseOrderNumber'] ) ) {
				$certificate['Detail']['SinglePurchaseOrderNumber'] = wootax_generate_order_id();
			}
			
			WC()->session->set( 'certificate_data', $certificate );
		} else {
			$certificate = array(
				'CertificateID' => WC()->session->get( 'certificate_id' ),
			);
		}
	} else if ( $order_id ) {
		$order_cert = WT_Orders::get_meta( $order_id, 'exemption_applied' );
		
		if ( !is_bool( $order_cert ) ) {
			$certificate = $order_cert;
		}
	}

	return $certificate;
}