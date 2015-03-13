<?php

/**
 * Includes methods relevant to tax exemptions feature
 *
 * @package WooTax
 * @since 4.2
 */

require( WOOTAX_PATH .'classes/class-wc-wootax-exemption-certificate.php' );

/**
 * Enqueue scripts required for exemption management on the checkout page
 */
function enqueue_checkout_scripts() {
	if ( !is_admin() && is_checkout() || is_cart() ) {
		// Enqueue Magnific Popup
		wp_enqueue_style( 'mpop-css', WOOTAX_DIR_URL .'css/magnificPopup.css' );
		wp_enqueue_script( 'mpop-js', WOOTAX_DIR_URL .'js/magnificPopup.js', array( 'jquery' ), '1.0', true );
	} 
}

/**
 * Adds exemption code to the header on the checkout page and enqueues WooTax frontend CSS
 */
function add_exemption_javascript() {

	if ( is_checkout() ) {

		// Insert the exemption code (taken from Appendix C of the TaxCloud developer documentation)
		$merchant_name = wootax_get_option( 'company_name' );
		$dir_url = WOOTAX_DIR_URL;
		$home_path = ABSPATH;
		$allow_blanket_certificates = is_user_logged_in();

		echo "
        <script type='text/javascript'>
			// Set merchant name
			var merchantName = '$merchant_name';

			// Click target (triggers opening of lightbox)
			var clickTarget = 'wootax_exemption_link';

			// Clear URL (timestamp; avoids caching)
			var date = new Date();
			var clearUrl = '?t='+ date.getTime();

			// Path to lightboxes
			var lbPath = '{$dir_url}templates/lightbox';

			// Path to plugin dir (used when loading resources within lightboxes)
			var pluginPath = '$dir_url';

			// Should users be allowed to use 'blanket' certificates? This defaults to the value of is_user_logged_in()
			var useBlanket = '$allow_blanket_certificates';

			// Load certificate management script asynchronously
			(function () {
				var ts = document.createElement('script'); ts.type = 'text/javascript'; ts.async = true;
				ts.src = '{$dir_url}js/certificate-manager.js' + clearUrl; var t = document.getElementsByTagName('script')[0]; t.parentNode.insertBefore(ts, t);
			})();
		</script>";

	}

}

/**
 * Displays the tax exemption link on the checkout page
 *
 * @since 4.3
 */
function maybe_display_exemption_link() {

	global $woocommerce, $current_user;

	$restricted   = wootax_get_option( 'restrict_exempt' ) == 'yes' ? true : false;
	$exempt_roles = is_array( wootax_get_option( 'exempt_roles' ) ) ? wootax_get_option( 'exempt_roles' ) : array();

	if ( wootax_get_option( 'show_exempt' ) == 'true' ) {

		if ( $restricted === true && ( !is_user_logged_in() || count( array_intersect( $exempt_roles, $current_user->roles ) ) == 0 ) ) {
			return;
		}

		$raw_link_text = trim( wootax_get_option( 'exemption_text' ) );
		$link_text = empty( $raw_link_text ) ? 'Click here to add or apply an exemption certificate.' : $raw_link_text;

		$notice = 'Are you a tax exempt customer? <span id="wootax_exemption_link"><a href="#" style="text-decoration: none;">'. $link_text .'</a></span>';
	
		echo "<div class='woocommerce-info'>$notice</div>";
		echo "<div class='woocommerce-message' id='wooTaxApplied' style='". ( empty( $woocommerce->session->certificate_id ) ? 'display: none;' : '' ) ."'>Exemption certificate applied (<a href='#' id='removeCert'>Remove</a>)</div>";

	}

}

/**
 * Save or remove an exemption certificate via AJAX
 * Also handles setting the applied exemption certificate in the session 
 *
 * @since 4.2
 */
function ajax_update_exemption_certificate() {

	$action = esc_attr( $_POST['act'] );

	switch ( $action ) {

		case 'add':
			add_exemption_certificate();
			break;


		case 'remove':
			remove_exemption_certificate();
			break;

		case 'set': 
			set_exemption_certificate();
			break;

	}

}

/**
 * Add exemption certificate
 *
 * @since 4.2
 */
function add_exemption_certificate() {

	global $woocommerce;

	// Set up TaxCloud object
	$taxcloud = get_taxcloud();

	// Build WC_WooTax_Exemption_Certificate object
	$certificate = new WC_WooTax_Exemption_Certificate();

	$certificate->SinglePurchase     = ( $_POST['SinglePurchase'] == 'false' ) ? false : true;
	$certificate->PurchaserFirstName = esc_attr( $_POST['PurchaserFirstName'] );
	$certificate->PurchaserLastName	 = esc_attr( $_POST['PurchaserLastName'] );
	$certificate->PurchaserTitle     = esc_attr( $_POST['PurchaserTitle'] );
	$certificate->PurchaserAddress1  = esc_attr( $_POST['PurchaserAddress1'] );
	$certificate->PurchaserCity      = esc_attr( $_POST['PurchaserCity'] );
	$certificate->PurchaserState     = esc_attr( $_POST['PurchaserState'] );
	$certificate->PurchaserZip       = esc_attr( $_POST['PurchaserZip'] );

	$certificate->PurchaserTaxID = array(
		'TaxType'      => esc_attr( $_POST['TaxType'] ),
		'IDNumber'     => esc_attr( $_POST['IDNumber'] ),
		'StateOfIssue' => esc_attr( $_POST['StateOfIssue'] ),
	);

	$certificate->ExemptStates[0] = array( 
		'StateAbbr' => esc_attr( $_POST['ExemptState'] ),
	);

	$certificate->PurchaserBusinessType           = esc_attr( $_POST['PurchaserBusinessType'] );
	$certificate->PurchaserBusinessTypeOtherValue = isset( $_POST['PurchaserBusinessTypeOtherValue'] ) ? esc_attr( $_POST['PurchaserBusinessTypeOtherValue'] ) : NULL;
	$certificate->PurchaserExemptionReason        = esc_attr( $_POST['PurchaserExemptionReason'] );
	$certificate->PurchaserExemptionReasonValue   = esc_attr( $_POST['PurchaserExemptionReasonValue'] );
	
	// Get certificate in TaxCloud-friendly format
	$final_certificate = $certificate->get_formatted_certificate();

	// If this certificate will only be used for a single purchase, store it in the session; Else, send AddCertificate request to TaxCloud
	if ( true == $certificate->SinglePurchase ) {

		// Save cert to session for use during checkout
		$woocommerce->session->certificate_data = $final_certificate['exemptCert'];
		$woocommerce->session->save_data();

		// Send back success response; for single certificates, this should trigger the lightbox to close and cause cart totals to be recalculated
		die( json_encode( array( 
			'status' => 'success', 
			'message' => ''
		) ) );

	} else {

		// Send request
		$res = $taxcloud->send_request( 'AddExemptCertificate', $final_certificate );

		// Check for errors
		if ( $res !== false ) {
			$certificate_id = $res->CertificateID;

			// For blanket certificates, a success response should lead to a redirect to the "manage-certificates" lightbox
			die( json_encode( array( 
				'status'  => 'success', 
				'message' => 'Certificate ' . $certificate_id . ' saved successfully.' 
			) ) );
		} else {
			die( json_encode( array( 
				'status'  => 'error', 
				'message' => 'There was an error while saving this certificate: ' . $taxcloud->get_error_message() 
			) ) );
		}

	}

}

/**
 * Remove exemption certificate
 *
 * @since 4.2
 */
function remove_exemption_certificate() {

	global $woocommerce;

	// Set up TaxCloud object
	$taxcloud = get_taxcloud();

	// Collect vars
	$certificate_id = esc_attr( $_POST['certificateID'] );
	$single         = esc_attr( $_POST['single'] );

	// Fetch customer ID
	$customer_id = $woocommerce->session->wootax_customer_id;
	
	// If this is a "single purchase" cert, we need to remove all certificates with the same OrderID
	if ( $single == 'true' || intval( $single ) == 1 ) {
		
		$response = $taxcloud->send_request( 'GetExemptCertificates', array( 'customerID' => $customer_id ) );
		
		if ( $response !== false ) {

			$certificates = $response->ExemptCertificates;
			$duplicates = array();

			// Dump certificates into object to be returned to client
			if ( $certificates != NULL && is_object( $certificates ) ) {

				foreach ( $certificates->ExemptionCertificate as $certificate ) {
					// Add single purchase certificates to duplicate array
					if ( $certificate->Detail->SinglePurchase == 1 ) {
						$orderNum = $certificate->Detail->SinglePurchaseOrderNumber;
						
						if ( !isset( $duplicates[$orderNum] ) || !is_array( $duplicates[$orderNum] ) )
							$duplicates[$orderNum]   = array();
							$duplicates[$orderNum][] = $certificate->CertificateID;
						}
					}
				}

				// Loop through dupes array; delete all exemption certificates that share the orderID of cert with ID certificateID
				foreach ($duplicates as $dupes) {
					if ( in_array( $certificate_id, $dupes ) ) {
						foreach ($dupes as $certID) {
							// Send request
							$res = $taxcloud->send_request( 'DeleteExemptCertificate', array( 'certificateID' => $certID ) );
							
							// Check for errors
							if ( $res == false ) {
								die( json_encode( array( 
									'status'  => 'error', 
									'message' => 'There was an error while removing this certificate: ' . $taxcloud->get_error_message() 
								) ) );
							}
						}
					}
				}
				
				die( json_encode( array( 
					'status'  => 'success', 
					'message' => 'Certificate ' . $certificate_id . ' removed successfully.' 
				) ) );

		} else {
			die( json_encode( array( 
				'status'  => 'error', 
				'message' => 'There was an error while removing this certificate: ' . $taxcloud->get_error_message() 
			) ) );
		}

	} else {

		// Send request
		$res = $taxcloud->send_request( 'DeleteExemptCertificate', array( 'certificateID' => $certificate_id ) );

		// Check for errors
		if ( $res !== false ) {
			die( json_encode( array( 
				'status'  => 'success', 
				'message' => 'Certificate ' . $certificate_id . ' removed successfully.' 
			) ) );
		} else {
			die( json_encode( array( 
				'status'  => 'error', 
				'message' => 'There was an error while removing this certificate: ' . $taxcloud->get_error_message() 
			) ) );
		}

	}

}

/**
 * Set exemption certificate in session
 *
 * @since 4.2
 * @param $certID a certificate ID (optional)
 */
function set_exemption_certificate( $certID = null ) {

	global $woocommerce;

	$cert = !empty( $certID ) ? $certID : ( isset( $_POST['cert'] ) ? esc_attr( $_POST['cert'] ) : null );
	
	// Set certID (empty if we are removing the currently applied certificate)
	$woocommerce->session->certificate_id = $cert;

	// If we are removing the currently applied certificate, reset "certificate_data" and "certificate_applied" session variables
	// Also, set "cert_removed" to true (this way we dont auto-apply for exempt user if they happen to remove)
	if ( empty( $woocommerce->session->certificate_id ) ) {
		$woocommerce->session->certificate_data    = null;
		$woocommerce->session->certificate_applied = null;
		$woocommerce->session->cert_removed        = true;
	} 

	$woocommerce->session->save_data();

	// Returning true will trigger the totals to update so WooTax applies the certificate
	if ( empty( $certID ) ) {
		die( true );
	} else {
		return true;
	}

}

/**
 * Get all exemption certificates for a user given their username
 *
 * @since 4.3
 * @return an array of exemption certificates
 */
function get_user_exemption_certs( $user_login ) {

	if ( empty( $user_login ) ) 
		return array();

	// Set up TaxCloud object
	$taxcloud = get_taxcloud();

	// Send GetExemptCertificates request
	$response = $taxcloud->send_request( 'GetExemptCertificates', array( 'customerID' => $user_login ) );

	if ( $response !== false ) {

		$certificate_result = is_object( $response->ExemptCertificates ) ? $response->ExemptCertificates->ExemptionCertificate : '';

		// Convert response to array if only a single certificate is returned
		if ( !is_array( $certificate_result ) )
			$certificate_result = array( $certificate_result );

		// Dump certificates into object to be returned to client
		$certificates = $duplicates = array();

		if ( $certificate_result != NULL ) {
			if ( is_array( $certificate_result ) ) {
				foreach ( $certificate_result as $certificate ) {
					// Add this certificate to the cert_list array
					$certificates[] = $certificate;

					// Add single purchase certificates to duplicate array
					if ( $certificate->Detail->SinglePurchase == 1 ) {
						$order_number = $certificate->Detail->SinglePurchaseOrderNumber;

						if ( !isset( $duplicates[$order_number] ) || !is_array( $duplicates[$order_number] ) ) {
							$duplicates[$order_number] = array();
						}

						$duplicates[$order_number][] = $certificate->CertificateID;
					}
				}
			} 
		} 

		// Isolate single certificates that should be kept
		if ( count( $duplicates ) > 0 ) {
			foreach ( $duplicates as &$dupes ) {
				if ( count( $dupes ) > 1 ) {
					$x = count( $dupes );

					while( count( $dupes ) > 1 ) {
						unset( $dupes[$x] );
						$x--;
					}
				}
			}
		}

		// Loop through cert_list and construct filtered cert_list array (duplicate single certificates removed)
		$final_certificates = array();

		foreach ( $certificates as $cert ) {
			if ( !is_object( $cert ) )
				continue;

			$keep = false;

			if ( $cert->Detail->SinglePurchase == true && is_array( $duplicates[$cert->Detail->SinglePurchaseOrderNumber] ) && in_array( $cert->CertificateID, $duplicates[$cert->Detail->SinglePurchaseOrderNumber] ) ) {
				$keep = true;
			} elseif ( $cert->Detail->SinglePurchase == true && !is_array( $duplicates[$cert->Detail->SinglePurchaseOrderNumber] ) || $cert->Detail->SinglePurchase == false ) {
				$keep = true;
			} 

			if ( $keep ) {
				$final_certificates[] = $cert;
			}
		}

		return $final_certificates;

	} else {
		return array();
	}

}

/**
 * List exemption certificates for a given customer
 *
 * @since 4.2
 * @return JSONP object with exemption certificates
 */
function ajax_list_exemption_certificates() {

	global $current_user;
	
	get_currentuserinfo();

	$customer_id = is_user_logged_in() ? $current_user->user_login : '';

	if ( $customer_id ) {

		$certificates = get_user_exemption_certs( $customer_id );

		if ( count( $certificates ) > 0 ) {

			$final_certificates = new stdClass();
			$final_certificates->cert_list = $certificates;

			// Convert to JSON and return
			die( json_encode( $final_certificates ) );

		} else {
			die( '{cert_list:[]}' );
		}

	} else {
		die( '{cert_list:[]}' );
	}

}

/**
 * Apply exemption certificate automatically for customers marked as exempt
 *
 * @since 4.3
 */
function maybe_apply_exemption_certificate() {

	global $current_user, $woocommerce;

	get_currentuserinfo();

	$exempt_roles = wootax_get_option( 'exempt_roles' );

	if ( is_object( $woocommerce->session ) && !$woocommerce->session->certificate_id && !$woocommerce->session->cert_removed && in_array( site_url( $_SERVER['REQUEST_URI'] ), array( get_permalink( wc_get_page_id( 'cart' ) ), get_permalink( wc_get_page_id( 'checkout' ) ) ) ) ) {

		foreach ( $current_user->roles as $role ) {

			if ( is_array( $exempt_roles ) && in_array( $role, $exempt_roles ) ) {

				// Get all certs
				$certs = get_user_exemption_certs( $current_user->user_login );
		
				// Find ID of first blanket cert
				$first_id = -1;

				foreach ( $certs as $cert ) {
					if ( is_object( $cert ) && $cert->Detail->SinglePurchase !== true ) {
						$first_id = $cert->CertificateID;
						break;
					}
				}

				// Apply cert
				if ( $first_id != -1 ) {
					set_exemption_certificate( $first_id );
				}

			}

		}

	}

}

/**
 * Get user roles for Exempt Roles select
 *
 * @since 4.3
 * @return an array of all registered user roles
 */
function wootax_get_user_roles() {

	global $wp_roles;

	if ( ! isset( $wp_roles ) )
	    $wp_roles = new WP_Roles();

	return $wp_roles->get_names();

}

// Hooks into WordPress/WooCommerce
add_action( 'wp_enqueue_scripts', 'enqueue_checkout_scripts', 20 );
add_action( 'wp_footer', 'add_exemption_javascript', 21 );
add_action( 'woocommerce_before_checkout_form', 'maybe_display_exemption_link', 11 );
add_action( 'init', 'maybe_apply_exemption_certificate' );
add_action( 'wp_ajax_nopriv_wootax-update-certificate', 'ajax_update_exemption_certificate' );
add_action( 'wp_ajax_wootax-update-certificate', 'ajax_update_exemption_certificate' );
add_action( 'wp_ajax_nopriv_wootax-list-certificates', 'ajax_list_exemption_certificates' );
add_action( 'wp_ajax_wootax-list-certificates', 'ajax_list_exemption_certificates' );