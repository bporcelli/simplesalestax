<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Account.
 *
 * Controller for Saved Certificates table on My Account page.
 *
 * @author 	Simple Sales Tax
 * @package SST
 * @since 	5.0
 */
class SST_Account {

	/**
	 * Constructor.
	 *
	 * @since 5.0
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'delete_certificate' ) );
		add_action( 'woocommerce_account_dashboard', array( $this, 'output_certificates' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	/**
	 * Enqueue scripts and styles.
	 *
	 * @since 5.0
	 */
	public function enqueue_scripts() {
		// Magnific Popup
		wp_enqueue_style( 'mpop', SST()->plugin_url() . '/assets/css/magnific-popup.css' );
		wp_enqueue_script( 'mpop', SST()->plugin_url() . '/assets/js/magnific-popup.js', array( 'jquery' ), '1.0', true );

		// Certificate table JS
		wp_enqueue_script( 'certificate-table', SST()->plugin_url() . '/assets/js/certificate-table.js', array( 'mpop' ) );
	}

	/**
	 * Output "Saved Certificates" table.
	 *
	 * @since 5.0
	 */
	public function output_certificates() {
		// Exit if exemptions are disabled...
		$show_exempt = SST_Settings::get( 'show_exempt' ) == 'true';

		if ( ! $show_exempt )
			return;

		$template_path = SST()->plugin_path() . '/templates';

		echo "<h2 id='saved-certificates'>Saved certificates</h2>";
		
		echo "<form action='' method='POST'>";
		wc_get_template( 'certificate-table.php', array(
			'certificates'  => SST_Certificates::get_certificates(),
			'template_path' => $template_path,
			'checkout'      => false
		), 'sst/checkout/', $template_path .'/checkout/' );
		echo "</form>";
	}

	/**
	 * Respond when user requests to delete a certificate.
	 *
	 * @since 5.0
	 */
	public function delete_certificate() {
		if ( ! isset( $_POST[ 'delete_certificate' ] ) || ! wp_verify_nonce( $_POST[ '_wpnonce' ], 'delete_certificate' ) ) {
			return;
		}

		// Delete certificate if it exists
		$deleted        = false;
		$certificate_id = esc_attr( $_POST[ 'certificate_id' ] );
		$certificates   = SST_Certificates::get_certificates();

		if ( isset( $certificates[ $certificate_id ] ) ) {
			$request = new TaxCloud\Request\DeleteExemptCertificate( SST_Settings::get( 'tc_id' ), SST_Settings::get( 'tc_key' ), $certificate_id );

			try {
				TaxCloud()->DeleteExemptCertificate( $request );
				unset( $certificates[ $certificate_id ] );
				SST_Certificates::set_certificates( $certificates );
				$deleted = true;
			} catch ( Exception $ex ) { /* Failed to delete */
				$deleted = false;
			}
		}

		// Show feedback
		if ( $deleted )
			wc_add_notice( 'Certificate deleted successfully.' );
		else
			wc_add_notice( 'Certificate deletion failed. Please try again.', 'error' );
	}
}

new SST_Account();