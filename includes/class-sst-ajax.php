<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
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
	 * @var array Hooks.
	 * @since 5.0
	 */
	private static $hooks = [
		'sst_verify_taxcloud'         => false,
		'sst_delete_certificate'      => false,
		'sst_add_certificate'         => false,
		'woocommerce_calc_line_taxes' => false,
	];

	/**
	 * Initialize hooks.
	 *
	 * @since 5.0
	 */
	public static function init() {
		foreach ( self::$hooks as $hook => $nopriv ) {
			$function = str_replace( [ 'woocommerce_', 'sst_' ], '', $hook );

			/* If we are overriding a woo hook, give ours higher priority */
			if ( 0 === strpos( $hook, 'woocommerce_' ) ) {
				$priority = 1;
			} else {
				$priority = 10;
			}

			add_action( "wp_ajax_$hook", [ __CLASS__, $function ], $priority );

			if ( $nopriv ) {
				add_action( "wp_ajax_nopriv_$hook", [ __CLASS__, $function ], $priority );
			}
		}
	}

	/**
	 * Verify the user's TaxCloud API Login ID and API Key.
	 *
	 * @since 5.0
	 */
	public static function verify_taxcloud() {
		$taxcloud_id  = sanitize_text_field( $_POST['wootax_tc_id'] );
		$taxcloud_key = sanitize_text_field( $_POST['wootax_tc_key'] );

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
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'sst_delete_certificate' ) ) {
			return;
		}

		$certificate_id = sanitize_text_field( $_POST['certificate_id'] );

		try {
			$request = new TaxCloud\Request\DeleteExemptCertificate(
				SST_Settings::get( 'tc_id' ),
				SST_Settings::get( 'tc_key' ),
				$certificate_id
			);

			TaxCloud()->DeleteExemptCertificate( $request );

			// Invalidate cached certificates
			SST_Certificates::delete_certificates();

			wp_send_json_success(
				[
					'certificates' => SST_Certificates::get_certificates_formatted(),
				]
			);
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

		if ( ! isset( $_POST['form_data'], $_POST['certificate'] ) ) {
			wp_send_json_error( __( 'Invalid request.', 'simple-sales-tax' ) );
		}

		// Get data
		$form_data = [];
		parse_str( $_POST['form_data'], $form_data );
		$form_data = array_map( 'sanitize_text_field', array_merge( $_POST['certificate'], $form_data ) );

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
			[ $exempt_state ],
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

		$certificate_id = '';

		try {
			$request = new TaxCloud\Request\AddExemptCertificate(
				SST_Settings::get( 'tc_id' ),
				SST_Settings::get( 'tc_key' ),
				$user->user_login,  // todo: use user ID instead?
				$certificate
			);

			$certificate_id = TaxCloud()->AddExemptCertificate( $request );

			SST_Certificates::delete_certificates();  // Invalidate cache
		} catch ( Exception $ex ) {
			wp_send_json_error( $ex->getMessage() );
		}

		$data = [
			'certificate_id' => $certificate_id,
			'certificates'   => SST_Certificates::get_certificates_formatted(),
		];

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

		$items        = [];
		$order_id     = absint( $_POST['order_id'] );
		$tax_based_on = get_option( 'woocommerce_tax_based_on' );
		$country      = strtoupper( sanitize_text_field( $_POST['country'] ) );
		$state        = strtoupper( sanitize_text_field( $_POST['state'] ) );
		$postcode     = strtoupper( sanitize_text_field( $_POST['postcode'] ) );
		$city         = sanitize_text_field( $_POST['city'] );

		// Let Woo take the reins if the customer is international
		if ( 'US' !== $country ) {
			return;
		}

		// Parse jQuery serialized items
		parse_str( $_POST['items'], $items );

		$items = wc_clean( $items );

		// Save items and recalc taxes
		wc_save_order_items( $order_id, $items );

		$order = wc_get_order( $order_id );

		self::ensure_order_address( $order, $tax_based_on, compact( 'country', 'state', 'city', 'postcode' ) );

		$result = sst_order_calculate_taxes( $order );

		if ( is_wp_error( $result ) ) {
			wp_die( $result->get_error_message() );
		}

		include WC()->plugin_path() . '/includes/admin/meta-boxes/views/html-order-items.php';

		wp_die();
	}

	/**
	 * Ensures that the order billing or shipping address is set before taxes
	 * are calculated.
	 *
	 * @param WC_Order $order   Order object.
	 * @param string   $type    Type of address - can be 'billing' or 'shipping'.
	 * @param array    $address POSTed address data.
	 */
	protected static function ensure_order_address( $order, $type, $address ) {
		if ( ! in_array( $type, [ 'billing', 'shipping' ] ) ) {
			return;
		}

		try {
			foreach ( $address as $field => $value ) {
				if ( empty( $order->{"get_{$type}_{$field}"}() ) ) {
					$order->{"set_{$type}_{$field}"}( $value );
				}
			}
		} catch ( WC_Data_Exception $ex ) {
			wc_get_logger()->error(
				sprintf(
					"Failed to set %s address for order #%d: %s",
					$type,
					$order->get_id(),
					$ex->getMessage()
				)
			);
		}
	}

}

SST_Ajax::init();