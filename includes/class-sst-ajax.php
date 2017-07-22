<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * SST Ajax.
 *
 * Ajax functions.
 *
 * @author 	Simple Sales Tax
 * @package SST
 * @since 	5.0
 */
class SST_Ajax {

	/**
	 * @var array Hooks.
	 * @since 5.0
	 */
	private static $hooks = array(
		'sst_verify_taxcloud'         => false,
		'sst_delete_certificate'      => false,
		'sst_add_certificate'         => false,
		'woocommerce_calc_line_taxes' => false,
	);

	/**
	 * Initialize hooks.
	 *
	 * @since 5.0
	 */
	public static function init() {
		foreach ( self::$hooks as $hook => $nopriv ) {
			$function = str_replace( array( 'woocommerce_', 'sst_' ), '', $hook );

			/* If we are overriding a woo hook, give ours higher priority */
			if ( 0 === strpos( $hook, 'woocommerce_' ) )
				$priority = 1;
			else
				$priority = 10;

			add_action( "wp_ajax_$hook", array( __CLASS__, $function ), $priority );

			if ( $nopriv ) {
				add_action( "wp_ajax_nopriv_$hook", array( __CLASS__, $function ), $priority );
			}
		}
	}

	/**
	 * Verify the user's TaxCloud API Login ID and API Key.
	 *
	 * @since 5.0
	 */
	public static function verify_taxcloud() {
		$taxcloud_id  = sanitize_text_field( $_POST[ 'wootax_tc_id' ] );
		$taxcloud_key = sanitize_text_field( $_POST[ 'wootax_tc_key' ] );

		if ( empty( $taxcloud_id ) || empty( $taxcloud_key ) ) {
			wp_send_json_error();
		} else {
			$ping = new TaxCloud\Request\Ping( $taxcloud_id, $taxcloud_key );

			try {
				TaxCloud()->Ping( $ping );
				wp_send_json_success();
			} catch ( Exception $ex ) {
				wp_send_json_error( $ex->getMessage() );
			}
		}
	}

	/**
	 * Respond when user requests to delete a certificate.
	 *
	 * @since 5.0
	 */
	public static function delete_certificate() {
		if ( ! isset( $_POST[ 'nonce' ] ) || ! wp_verify_nonce( $_POST[ 'nonce' ], 'sst_delete_certificate' ) ) {
			return;
		}

		$certificate_id = esc_attr( $_POST[ 'certificate_id' ] );

		$request = new TaxCloud\Request\DeleteExemptCertificate(
			SST_Settings::get( 'tc_id' ),
			SST_Settings::get( 'tc_key' ),
			$certificate_id
		);

		try {
			TaxCloud()->DeleteExemptCertificate( $request );
			
			// Invalidate cached certificates
			SST_Certificates::delete_certificates();

			wp_send_json_success( array(
				'certificates' => SST_Certificates::get_certificates_formatted(), 
			) );
		} catch ( Exception $ex ) { /* Failed to delete */
			wp_send_json_error( $ex->getMessage() );
		}
	}

	/**
	 * Add an exemption certificate for the customer.
	 *
	 * NOTE: Single purchase exemption certificates not supported at this
	 * time.
	 *
	 * @since 5.0
	 */
	public static function add_certificate() {
		// Handle invalid requests
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'sst_add_certificate' ) ) {
			return;
		}

		if ( ! isset( $_POST['form_data'] ) || ! isset( $_POST['certificate'] ) ) {
			wp_send_json_error( __( 'Invalid request.', 'simplesalestax' ) );
		}

		// Get data
		$form_data = array();
		parse_str( $_POST['form_data'], $form_data );
		$form_data = array_merge( $_POST['certificate'], $form_data );

		// Construct certificate
		$exempt_state = new TaxCloud\ExemptState(
			$form_data['ExemptState'],
			$form_data['PurchaserExemptionReason'],
			$form_data['IDNumber'] 
		);

		$tax_id = new TaxCloud\TaxID(
			$form_data['TaxType'],
			$form_data['IDNumber'],
			$form_data['StateOfIssue']
		);

		$certificate = new TaxCloud\ExemptionCertificate(
			array( $exempt_state ),
			false,
			'',
			$form_data['billing_first_name'],
			$form_data['billing_last_name'],
			'',
			$form_data['billing_address_1'],
			$form_data['billing_address_2'],
			$form_data['billing_city'],
			$form_data['billing_state'],
			$form_data['billing_postcode'],
			$tax_id,
			$form_data['PurchaserBusinessType'],
			$form_data['PurchaserBusinessTypeOtherValue'],
			$form_data['PurchaserExemptionReason'],
			$form_data['PurchaserExemptionReasonValue']
		);

		// Add certificate
		$user = wp_get_current_user();

		$request = new TaxCloud\Request\AddExemptCertificate(
			SST_Settings::get( 'tc_id' ),
			SST_Settings::get( 'tc_key' ),
			$user->user_login,	// TODO: USE ID?
			$certificate
		);

		$certificate_id = '';

		try {
			$certificate_id = TaxCloud()->AddExemptCertificate( $request );
			SST_Certificates::delete_certificates();  // Invalidate cache
		} catch ( Exception $ex ) {
			wp_send_json_error( $ex->getMessage() );
		}

		$data = array(
			'certificate_id' => $certificate_id,
			'certificates'   => SST_Certificates::get_certificates_formatted(),
		);

		wp_send_json_success( $data );
	}

	/**
	 * Recalculate sales tax via AJAX.
	 *
	 * @since 4.2
	 */
	public static function calc_line_taxes() {
		check_ajax_referer( 'calc-totals', 'security' );

		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			wp_die( -1 );
		}

		$items        = array();
		$order_id     = absint( $_POST['order_id'] );
		$tax_based_on = get_option( 'woocommerce_tax_based_on' );
		$country      = strtoupper( esc_attr( $_POST['country'] ) );
		$state        = strtoupper( esc_attr( $_POST['state'] ) );
		$postcode     = strtoupper( esc_attr( $_POST['postcode'] ) );
		$city         = wc_clean( esc_attr( $_POST['city'] ) );
		$woo_3_0      = version_compare( WC_VERSION, '3.0', '>=' );

		/* Let Woo take the reins if the customer is international */
		if ( 'US' != $country ) {
			return;
		}

		$order  = wc_get_order( $order_id );
		$_order = new SST_Order( $order );

		/* Parse jQuery serialized items */
		parse_str( $_POST['items'], $items );

		/* Set customer billing/shipping address as needed */
		$address = array(
			'country'   => 'US',
			'address_1' => '',
			'address_2' => '',
			'city'      => $city,
			'state'     => $state,
			'postcode'  => $postcode
		);

		if ( 'shipping' === $tax_based_on || empty( $_order->get_shipping_country() ) ) {
			$_order->set_address( $address, 'shipping' );
		}
		if ( 'billing' === $tax_based_on || empty( $_order->get_billing_country() )) {
			$_order->set_address( $address, 'billing' );
		}

		/* Save items and recalc taxes */
		wc_save_order_items( $order_id, $items );

		try {
			$_order->calculate_taxes();
			$_order->calculate_totals( false );
		} catch ( Exception $ex ) {
			wp_die( $ex->getMessage() );
		}

		/* Send back response */
		if ( ! $woo_3_0 ) {
			$data = get_post_meta( $order_id );
		}

		include WC()->plugin_path() . '/includes/admin/meta-boxes/views/html-order-items.php';
		
		wp_die();
	}

}

SST_Ajax::init();