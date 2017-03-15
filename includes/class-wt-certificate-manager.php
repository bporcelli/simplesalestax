<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Certificate Manager.
 *
 * Used for creating, updating, and deleting customer exemption certificates.
 *
 * @author 	Simple Sales Tax
 * @package SST
 * @since 	5.0
 */
class WT_Certificate_Manager {

	/**
	 * @var string Meta key used to store certificates.
	 * @since 5.0
	 */
	const CERT_META_KEY = '_sst_certificates';

	/**
	 * @var WT_Exemption_Certificate[] Array of certificates for current user.
	 * @since 5.0
	 */
	private $certificates;

	/**
	 * @var bool Boolean flag denoting whether the certificates array has been
	 * modified.
	 * @since 5.0
	 */
	private $_dirty = false;

	/**
	 * __construct() method. Initialize hooks.
	 *
	 * @since 5.0
	 */
	public function __construct() {
		$this->hooks();
	}

	/**
	 * Register action/filter hooks.
	 *
	 * @since 5.0
	 */
	private function hooks() {
		// Load certificate list asynchronously
		add_action( 'wp_ajax_wootax-list-certificates', array( $this, 'ajax_list_certificates' ) );

		// Display a certificate asynchronously
		add_action( 'wp_ajax_wootax-view-certificate', array( $this, 'ajax_display_certificate' ) );

		// Display the tax exemption form
		add_action( 'woocommerce_checkout_after_customer_details', array( $this, 'output_tax_details_form' ) );

		// Display the "Saved Certificates" table on the My Account page
		add_action( 'woocommerce_account_dashboard', array( $this, 'output_saved_certificates' ) );
		
		// Enqueue styles and scripts used by the exemption form
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_checkout_scripts' ), 20 );

		// Check for certificate deletion request
		add_action( 'init', array( $this, 'maybe_delete_certificate' ) );

		// Update user certificates on shutdown
		add_action( 'shutdown', array( $this, 'save_certificates' ) );
	}

	/**
	 * Get the stored exemption certificates for the current user. This method
	 * first attempts to fetch the certificates associated with the current user's
	 * account. If no certificates are associated with the user, the 
	 * GetExemptCertificates API is called.
	 *
	 * @since 5.0
	 *
	 * @param  bool $include_single Should single use certificates be returned? (default: true)
	 * @return WT_Exemption_Certificate[]
	 */
	private function get_certificates( $include_single = true ) {
		if ( ! is_user_logged_in() )
			return array();

		$_customer_id = get_current_user_id();
		$certificates = $this->certificates;

		if ( ! is_array( $certificates ) ) {
			$certificates = get_user_meta( $_customer_id, self::CERT_META_KEY, true );

			if ( ! is_array( $certificates ) ) {
				$certificates = $this->fetch_certificates();

				update_user_meta( $_customer_id, self::CERT_META_KEY, $certificates );
			}

			$this->certificates = $certificates;
		}

		// Filter single certs and convert certificate arrays to
		// WT_Exemption_Certificate objects
		$final = array();

		foreach ( $certificates as $id => $certificate ) {
			if ( ! $include_single && $certificate->Detail->SinglePurchase )
				continue;
			
			$final[ $id ] = WT_Exemption_Certificate::fromArray( $certificate );
		}

		return $final;
	}

	/**
	 * Fetch the user's exemption certificates from TaxCloud.
	 *
	 * @since 5.0
	 *
	 * @return array
	 */
	private function fetch_certificates() {
		if ( ! is_user_logged_in() )
			return array();

		// For backwards compatibility, we use user logins (rather than user IDs) as
		// customerIDs when talking with TaxCloud
		$user = wp_get_current_user();

		$_request = array( 'customerID' => $user->user_login );
		$response = TaxCloud()->send_request( 'GetExemptCertificates', $_request );

		// If request succeeds...
		if ( $response !== false && is_object( $response->ExemptCertificates ) && isset( $response->ExemptCertificates->ExemptionCertificate ) ) {

			// Extract the certificate list from the response
			$certificates = $response->ExemptCertificates->ExemptionCertificate;

			if ( ! is_array( $certificates ) ) {
				// Single certificate returned; wrap it in an array so we can use the same logic
				$certificates = array( $certificates );
			}

			// Keep track of the single purchase order IDs we encounter. We will take
			// the first certificate for a given order to be the true one.
			$single_oids = array();

			// Filter out duplicate single purchase certificates
			$final_certs = array();

			foreach ( $certificates as $certificate ) {
				$certID  = $certificate->CertificateID;
				$orderID = $certificate->Detail->SinglePurchaseOrderNumber;

				if ( ! $certificate->Detail->SinglePurchase ) {
					// Keep all blanket certificates
					$final_certs[ $certID ] = $certificate;
				} else if ( ! in_array( $orderID, $single_oids ) ) {
					// Only keep single certificates with order numbers that haven't been 
					// encountered yet
					$final_certs[ $certID ] = $certificate;
					$single_oids[] = $orderID;
				}
			}

			return $final_certs;
		} else {
			return array();
		}
	}

	/**
	 * AJAX Handler: Output HTML for certificate list.
	 *
	 * @since 5.0
	 */
	public function ajax_list_certificates() {

		$certificates  = $this->get_certificates( false );
		$template_path = SST()->templates_path();

		if ( count( $certificates ) > 0 ) {
			wc_get_template( 'certificate-list.php', array_merge( array(
				'certificates'  => $certificates,
			), $certificate ), 'sst/checkout/', $template_path .'/checkout/' );
		} else {
			wc_get_template( 'certificate-list-empty.php', array(), 'sst/checkout/', $template_path . '/checkout/' );
		}

		die;
	}

	/**
	 * Respond when user requests to delete a certificate.
	 *
	 * @since 5.0
	 */
	public function maybe_delete_certificate() {
		if ( ! isset( $_POST[ 'delete_certificate' ] ) || ! wp_verify_nonce( $_POST[ '_wpnonce' ], 'delete_certificate' ) )
			return;

		$certificate_id = esc_attr( $_POST[ 'certificate_id' ] );

		if ( $this->delete( $certificate_id ) ) {
			wc_add_notice( 'Certificate deleted successfully.' );
		} else {
			wc_add_notice( 'Certificate deletion failed. Please try again.', 'error' );
		}
	}

	/**
	 * Delete a certificate.
	 *
	 * @since 5.0
	 *
	 * @param  string $certificate_id
	 * @return bool True if cert removed successfully, otherwise false.
	 */
	private function delete( $certificate_id ) {
		$certificates = $this->get_certificates();

		// Can't remove a nonexistent certificate!
		if ( ! array_key_exists( $certificate_id, $certificates ) )
			return false;

		$certificate = $certificates[ $certificate_id ];

		// Collect a list of certificate IDs to delete
		$to_remove = array( $certificate_id );

		// For single use certificates, we have to include the IDs of all certificates with the same order number
		// as the given certificate
		if ( $certificate->is_single() ) {
			$order_num = $certificate->SinglePurchaseOrderNumber;

			foreach ( $certificates as $id => $certificate ) {
				if ( ! $certificate->is_single() )
					continue;

				if ( $certificate->SinglePurchaseOrderNumber == $order_num && ! in_array( $id, $to_remove ) )
					$to_remove[] = $id;
			}
		}

		// Delete all certificates with IDs in $to_remove
		foreach ( $to_remove as $certificate_id ) {
			$res = TaxCloud()->send_request( 'DeleteExemptCertificate', array( 
				'certificateID' => $certificate_id 
			) );

			// Check for errors
			if ( $res !== false ) {
				unset( $this->certificates[ $certificate_id ] );

				// Set $_dirty flag so new certificates array is saved
				$this->_dirty = true;
			} else {
				return false;
			}
		}

		return true;
	}

	/**
	 * AJAX Handler: Display a certificate.
	 *
	 * @since 5.0
	 */
	public function ajax_display_certificate() {
		$certificates   = $this->get_certificates();
		$certificate_id = esc_attr( $_REQUEST[ 'certID' ] );
		
		// Can't view a certificate that doesn't exist!
		if ( ! $certificate_id || ! array_key_exists( $certificate_id, $certificates ) )
			die( 'Invalid request.' );

		wc_get_template( 'view-certificate.php', array( 
			'plugin_url'  => SST()->plugin_url(),
			'seller_name' => SST()->get_option( 'company_name' ),
			'certificate' => $certificates[ $certificate_id ],
		), 'sst/lightbox/', SST()->templates_path() . '/lightbox/' );

		die;
	}

	/**
	 * Save user certificates on shutdown. Note that certificates are only
	 * saved if the certificates array was modified and a user is logged in.
	 *
	 * @since 5.0
	 */
	public function save_certificates() {
		if ( ( $user_id = get_current_user_id() ) && $this->_dirty ) {
			update_user_meta( $user_id, self::CERT_META_KEY, $this->certificates );
		}
	}

	/**
	 * Output Tax Details section of checkout form.
	 *
	 * @since 5.0
	 */
	public function output_tax_details_form() {
		// Exit if exemptions are disabled...
		$show_exempt = SST()->get_option( 'show_exempt' ) == 'true';

		if ( ! $show_exempt )
			return;

		// ... or if the form should only be shown to exempt users and the current
		// user is not exempt
		$current_user = wp_get_current_user();

		$restricted = SST()->get_option( 'restrict_exempt' ) == 'yes';
		$exempt_roles = SST()->get_option( 'exempt_roles', array() );
		$user_roles = is_user_logged_in() ? $current_user->roles : array();
		$user_exempt = count( array_intersect( $exempt_roles, $user_roles ) ) > 0;

		if ( $restricted && ( ! is_user_logged_in() || ! $user_exempt ) )
			return;

		// Check the "Tax exempt" checkbox by default if:
		// - Checkout is being loaded for the first time (GET) and the customer
		// has a tax exempt user role, OR
		// - Checkout form has been submitted (POST) and the tax exempt box is
		// still checked
		$checked = $_GET && $user_exempt || $_POST && isset( $_POST[ 'tax_exempt' ] );

		echo '<h3>Tax exempt? <input type="checkbox" name="tax_exempt" id="tax_exempt_checkbox" class="input-checkbox" value="1"'. checked( $checked, true, false ) .'></h3>';

		echo '<div id="tax_details">';

		$template_path = SST()->templates_path();

		if ( is_user_logged_in() ) {
			wc_get_template( 'form-tax-exempt.php', array(
				'certificates'  => $this->get_certificates( false ),
				'template_path' => $template_path,
			), 'sst/checkout/', $template_path .'/checkout/' );
		} else {
			wc_get_template( 'form-tax-exempt-logged-out.php', array(), 'sst/checkout/', $template_path .'/checkout/' );
		}

		echo '</div>';
	}

	/**
	 * Output list of saved certificates.
	 *
	 * @since 5.0
	 */
	public function output_saved_certificates() {
		// Exit if exemptions are disabled...
		$show_exempt = SST()->get_option( 'show_exempt' ) == 'true';

		if ( ! $show_exempt )
			return;

		$template_path = SST()->templates_path();

		echo "<h2 id='saved-certificates'>Saved certificates</h2>";
		
		echo "<form action='' method='POST'>";
		wc_get_template( 'certificate-table.php', array(
			'certificates'  => $this->get_certificates(),
			'template_path' => $template_path,
			'checkout'      => false
		), 'sst/checkout/', $template_path .'/checkout/' );
		echo "</form>";
	}

	/**
	 * Enqueue JS/CSS for exemption management interface.
	 *
	 * @since 5.0
	 */
	public static function enqueue_checkout_scripts() {
		if ( is_admin() )
			return;

		$_assets_url = SST()->plugin_url() . '/assets';

		if ( is_checkout() || is_account_page() ) {
			// Magnific Popup
			wp_enqueue_style( 'mpop', "$_assets_url/css/magnific-popup.css" );
			wp_enqueue_script( 'mpop', "$_assets_url/js/magnific-popup.js", array( 'jquery' ), '1.0', true );

			// Certificate table JS
			wp_enqueue_script( 'certificate-table', "$_assets_url/js/certificate-table.js", array( 'mpop' ) );
		}

		if ( is_checkout() ) {
			// Checkout CSS
			wp_enqueue_style( 'sst-checkout', "$_assets_url/css/checkout.css" );

			// Checkout JS
			wp_enqueue_script( 'sst-checkout', "$_assets_url/js/checkout.js", array( 'jquery', 'mpop' ), '1.0', true );
			wp_localize_script( 'sst-checkout', 'WT', array(
				'ajaxURL' => admin_url( 'admin-ajax.php' ) 
			) );
		}
	}

}

new WT_Certificate_Manager();