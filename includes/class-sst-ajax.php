<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * SST Ajax.
 *
 * Ajax functions.
 *
 * @author  Simple Sales Tax
 * @package SST
 * @since   5.0
 */
class SST_Ajax {

	/**
	 * AJAX Hooks.
	 *
	 * @var array
	 * @since 5.0
	 */
	private static $hooks = array(
		'sst_verify_taxcloud'         => false,
		'sst_delete_certificate'      => false,
		'sst_add_certificate'         => false,
		'woocommerce_calc_line_taxes' => false,
		'sst_get_certificates'        => false,
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
			if ( 0 === strpos( $hook, 'woocommerce_' ) ) {
				$priority = 1;
			} else {
				$priority = 10;
			}

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
		$taxcloud_id  = '';
		$taxcloud_key = '';

		if ( isset( $_POST['wootax_tc_id'] ) ) {
			$taxcloud_id = sanitize_text_field( wp_unslash( $_POST['wootax_tc_id'] ) ); // phpcs:ignore WordPress.CSRF.NonceVerification
		}
		if ( isset( $_POST['wootax_tc_key'] ) ) {
			$taxcloud_key = sanitize_text_field( wp_unslash( $_POST['wootax_tc_key'] ) ); // phpcs:ignore WordPress.CSRF.NonceVerification
		}

		if ( empty( $taxcloud_id ) || empty( $taxcloud_key ) ) {
			wp_send_json_error();
		} else {
			try {
				TaxCloud()->Ping( new TaxCloud\Request\Ping( $taxcloud_id, $taxcloud_key ) );
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
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, 'sst_delete_certificate' ) ) {
			return;
		}

		$certificate_id = '';

		if ( isset( $_POST['certificate_id'] ) ) {
			$certificate_id = sanitize_text_field( wp_unslash( $_POST['certificate_id'] ) );
		}

		$user_id = isset( $_POST['user_id'] ) ? absint( wp_unslash( $_POST['user_id'] ) ) : 0;

		if ( 0 === $user_id ) {
			$user_id = get_current_user_id();
		}

		if ( ! self::user_can_delete_certificate( $user_id, $certificate_id ) ) {
			wp_send_json_error(
				__( 'Unauthorized', 'simple-sales-tax' ),
				403
			);
		}

		try {
			$request = new TaxCloud\Request\DeleteExemptCertificate(
				SST_Settings::get( 'tc_id' ),
				SST_Settings::get( 'tc_key' ),
				$certificate_id
			);

			TaxCloud()->DeleteExemptCertificate( $request );

			// Invalidate cached certificates.
			SST_Certificates::delete_certificates( $user_id );

			$new_certificates = SST_Certificates::get_certificates_formatted( $user_id );
			wp_send_json_success(
				array('certificates' => $new_certificates)
			);
		} catch ( Exception $ex ) { /* Failed to delete */
			wp_send_json_error( $ex->getMessage() );
		}
	}

	/**
	 * Checks whether the current user can delete an exemption certificate.
	 *
	 * @param int    $user_id        User ID of certificate owner.
	 * @param string $certificate_id Certificate ID.
	 *
	 * @return bool Can the user delete the certificate?
	 */
	protected static function user_can_delete_certificate( $user_id, $certificate_id ) {
		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			return false;
		}

		$user_certificates = SST_Certificates::get_certificates( $user_id );

		foreach ( $user_certificates as $certificate ) {
			if ( $certificate->getCertificateID() === $certificate_id ) {
				return true;
			}
		}

		return false;
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
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';

		// Handle invalid requests.
		if ( ! wp_verify_nonce( $nonce, 'sst_add_certificate' ) ) {
			return;
		}

		if ( ! isset( $_POST['address'], $_POST['certificate'] ) ) {
			wp_send_json_error( __( 'Invalid request.', 'simple-sales-tax' ) );
		}

		// Get data.
		$certificate = wp_unslash( $_POST['certificate'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$address = wp_unslash( $_POST['address'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$form_data = array_map(
			'sanitize_text_field',
			array_merge( $certificate, $address )
		);

		// Construct certificate.
		try {
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
				$form_data['first_name'],
				$form_data['last_name'],
				'',
				$form_data['address_1'],
				$form_data['address_2'],
				$form_data['city'],
				$form_data['state'],
				$form_data['postcode'],
				$tax_id,
				$form_data['PurchaserBusinessType'],
				$form_data['PurchaserBusinessTypeOtherValue'],
				$form_data['PurchaserExemptionReason'],
				$form_data['PurchaserExemptionReasonValue']
			);
		} catch ( Throwable $ex ) {
			SST_Logger::add(
				sprintf(
					__(
						'Failed to add exemption certificate. Error was: %1$s',
						'simple-sales-tax'
					),
					$ex->getMessage()
				)
			);

			wp_send_json_error( __( 'Invalid request.', 'simple-sales-tax' ), 400 );
		}

		// Add certificate.
		if ( isset( $_POST['user_id'] ) ) {
			$user_id = absint( sanitize_text_field( $_POST['user_id'] ) );
			$user    = get_user_by( 'id', $user_id );

			if ( ! $user ) {
				wp_send_json_error( __( 'Invalid request.', 'simple-sales-tax' ) );
			}

			if ( ! current_user_can( 'edit_user', $user_id ) ) {
				wp_send_json_error(
					__(
						"You don't have permission to add a certificate for this user.",
						'simple-sales-tax'
					)
				);
			}
		} else {
			$user = wp_get_current_user();
		}

		$certificate_id = '';

		try {
			$request = new TaxCloud\Request\AddExemptCertificate(
				SST_Settings::get( 'tc_id' ),
				SST_Settings::get( 'tc_key' ),
				$user->user_login,  // todo: use user ID instead?
				$certificate
			);

			$certificate_id = TaxCloud()->AddExemptCertificate( $request );

			SST_Certificates::delete_certificates( $user->ID );  // Invalidate cache.
		} catch ( Exception $ex ) {
			wp_send_json_error( $ex->getMessage() );
		}

		$data = array(
			'certificate_id' => $certificate_id,
			'certificates'   => SST_Certificates::get_certificates_formatted( $user->ID ),
		);

		wp_send_json_success( $data );
	}

	/**
	 * Lists all exemption certificates available for a customer.
	 * Used to populate the Exemption Certificate dropdown on the
	 * Edit Order screen.
	 *
	 * @since 7.0.0
	 */
	public static function get_certificates() {
		check_ajax_referer( 'sst_get_certificates', 'nonce' );

		$user_id      = intval( wp_unslash( $_REQUEST['customerId'] ) );
		$certificates = array();

		if ( current_user_can( 'edit_user', $user_id ) ) {
			// Get certificates in select2 data format.
			$certificates = SST_Certificates::get_certificates_formatted(
				$user_id
			);
		}

		wp_send_json_success( $certificates );
	}

	/**
	 * Gets a string describing an exemption certificate.
	 *
	 * @param array $certificate Certificate data.
	 *
	 * @return string
	 */
	protected static function get_certificate_text( $certificate ) {
		return $certificate['CertificateID'];
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

		if ( ! isset( $_POST['order_id'], $_POST['country'], $_POST['state'], $_POST['postcode'], $_POST['city'], $_POST['items'] ) ) {
			wp_die( -1 );
		}

		$items          = array();
		$order_id       = absint( $_POST['order_id'] );
		$country        = strtoupper( sanitize_text_field( wp_unslash( $_POST['country'] ) ) );
		$state          = strtoupper( sanitize_text_field( wp_unslash( $_POST['state'] ) ) );
		$postcode       = strtoupper( sanitize_text_field( wp_unslash( $_POST['postcode'] ) ) );
		$city           = sanitize_text_field( wp_unslash( $_POST['city'] ) );
		$certificate_id = sanitize_text_field(
			wp_unslash( $_POST['exemption_certificate'] ?? '' )
		);

		// Let Woo take the reins if the customer is international.
		if ( 'US' !== $country ) {
			return;
		}

		// Parse jQuery serialized items.
		$raw_items = sanitize_text_field( wp_unslash( $_POST['items'] ) );
		parse_str( $raw_items, $items );

		$items = wc_clean( $items );

		// Save items and recalc taxes.
		wc_save_order_items( $order_id, $items );

		$order          = wc_get_order( $order_id );
		$posted_address = compact(
			'country',
			'state',
			'city',
			'postcode'
		);

		self::ensure_order_address( $order, 'billing', $posted_address );
		self::ensure_order_address( $order, 'shipping', $posted_address );

		$order->update_meta_data( '_wootax_exempt_cert', $certificate_id );

		$result = sst_order_calculate_taxes( $order );

		if ( is_wp_error( $result ) ) {
			wp_die( $result->get_error_message() ); // phpcs:ignore WordPress.Security.EscapeOutput
		}

		include WC()->plugin_path() . '/includes/admin/meta-boxes/views/html-order-items.php';

		wp_die();
	}

	/**
	 * Ensures that an order has an address set before taxes are calculated.
	 *
	 * @param WC_Order $order   Order object.
	 * @param string   $type    Address type. Can be 'billing' or 'shipping'.
	 * @param array    $address POSTed address data.
	 */
	protected static function ensure_order_address( $order, $type, $address ) {
		try {
			foreach ( $address as $field => $value ) {
				if ( empty( $order->{"get_{$type}_{$field}"}() ) ) {
					$order->{"set_{$type}_{$field}"}( $value );
				}
			}
		} catch ( WC_Data_Exception $ex ) {
			wc_get_logger()->error(
				sprintf( 'Failed to set %s address for order #%d: %s', $type, $order->get_id(), $ex->getMessage() )
			);
		}
	}

}

SST_Ajax::init();
