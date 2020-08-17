<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SST Integration.
 *
 * WooCommerce integration for Simple Sales Tax.
 *
 * @author  Simple Sales Tax
 * @package SST
 * @since   5.0
 */
class SST_Integration extends WC_Integration {

	/**
	 * Constructor. Initialize the integration.
	 *
	 * @since 4.5
	 */
	public function __construct() {
		$this->id                 = 'wootax';
		$this->method_title       = __( 'Simple Sales Tax', 'simple-sales-tax' );
		$this->method_description = __(
			'<p>Simple Sales Tax makes sales tax easy by connecting your store with <a href="https://taxcloud.net" target="_blank">TaxCloud</a>. If you have trouble with Simple Sales Tax, please consult the <a href="https://simplesalestax.com/#faq" target="_blank">FAQ</a> and the <a href="https://simplesalestax.com/installation-guide/" target="_blank">Installation Guide</a> before contacting support.</p><p>Need help? <a href="https://simplesalestax.com/contact-us/" target="_blank">Contact us</a>.</p>',
			'simple-sales-tax'
		);

		// Load the settings.
		$this->init_form_fields();

		// Register action hooks.
		add_action( 'woocommerce_update_options_integration_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'admin_init', array( $this, 'maybe_download_log_file' ) );
	}

	/**
	 * Initialize form fields for integration settings.
	 *
	 * @since 4.5
	 */
	public function init_form_fields() {
		$this->form_fields = SST_Settings::get_form_fields();
	}

	/**
	 * Display admin options.
	 *
	 * @since 5.0
	 */
	public function admin_options() {
		$this->display_errors();
		parent::admin_options();
	}

	/**
	 * Output HTML for field of type 'button.'
	 *
	 * @param string $key  Field key.
	 * @param array  $data Field data.
	 *
	 * @since 4.5
	 */
	public function generate_button_html( $key, $data ) {
		$field = $this->plugin_id . $this->id . '_' . $key;

		ob_start();
		?>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label for="<?php echo esc_attr( $field ); ?>">
					<?php echo wp_kses_post( $data['title'] ); ?><?php echo $this->get_tooltip_html( $data ); ?>
				</label>
			</th>
			<td class="forminp">
				<fieldset>
					<legend class="screen-reader-text">
						<span><?php echo wp_kses_post( $data['title'] ); ?></span>
					</legend>
					<button class="wp-core-ui button button-secondary" type="button" id="<?php echo esc_attr( $data['id'] ); ?>">
						<?php echo wp_kses_post( $data['label'] ); ?>
					</button>
					<?php echo $this->get_description_html( $data ); ?>
				</fieldset>
			</td>
		</tr>
		<?php
		return ob_get_clean();
	}

	/**
	 * Output HTML for field of type 'anchor.'
	 *
	 * @param string $key  Field key.
	 * @param array  $data Field data.
	 *
	 * @since 5.0
	 */
	public function generate_anchor_html( $key, $data ) {
		$field = $this->plugin_id . $this->id . '_' . $key;

		ob_start();
		?>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label for="<?php echo esc_attr( $field ); ?>">
					<?php echo wp_kses_post( $data['title'] ); ?><?php echo $this->get_tooltip_html( $data ); ?>
				</label>
			</th>
			<td class="forminp">
				<fieldset>
					<legend class="screen-reader-text">
						<span><?php echo wp_kses_post( $data['title'] ); ?></span>
					</legend>
					<a href="<?php echo esc_url( $data['url'] ); ?>" target="_blank"
					   class="wp-core-ui button button-secondary" id="<?php echo esc_attr( $data['id'] ); ?>">
						<?php echo wp_kses_post( $data['label'] ); ?>
					</a>
					<?php echo $this->get_description_html( $data ); ?>
				</fieldset>
			</td>
		</tr>
		<?php
		return ob_get_clean();
	}

	/**
	 * Output HTML for 'address_table' field.
	 *
	 * @param string $key  Field key.
	 * @param array  $data Field data.
	 *
	 * @since 4.5
	 */
	public function generate_address_table_html( $key, $data ) {
		wp_enqueue_script( 'sst-address-table' );

		wp_localize_script(
			'sst-address-table',
			'addressesLocalizeScript',
			array(
				'addresses'       => $this->get_addresses(),
				'strings'         => array(
					'one_default_required' => __( 'At least one default address is required.', 'simple-sales-tax' ),
				),
				'default_address' => array(
					'ID'       => '',
					'Address1' => '',
					'Address2' => '',
					'City'     => '',
					'State'    => '',
					'Zip5'     => '',
					'Zip4'     => '',
					'Default'  => false,
				),
			)
		);

		ob_start();
		include dirname( __FILE__ ) . '/views/html-address-table.php';

		return ob_get_clean();
	}

	/**
	 * Get addresses formatted for output.
	 *
	 * @since 5.0
	 */
	private function get_addresses() {
		$addresses     = array();
		$raw_addresses = $this->get_option( 'addresses', array() );

		foreach ( $raw_addresses as $raw_address ) {
			$addresses[] = json_decode( $raw_address, true );
		}

		return $addresses;
	}

	/**
	 * Validate addresses when options are saved.
	 *
	 * @param string $key   Key for addresses field.
	 * @param string $value Value for addresses field.
	 *
	 * @return array
	 * @since 5.0
	 */
	public function validate_addresses_field( $key, $value ) {
		if ( ! isset( $_POST['addresses'] ) || ! is_array( $_POST['addresses'] ) ) { // phpcs:ignore WordPress.CSRF.NonceVerification
			return array();
		}

		$taxcloud_id  = '';
		$taxcloud_key = '';

		if ( isset( $_POST['woocommerce_wootax_tc_id'] ) ) {
			$taxcloud_id = sanitize_text_field( wp_unslash( $_POST['woocommerce_wootax_tc_id'] ) ); // phpcs:ignore WordPress.CSRF.NonceVerification
		}
		if ( isset( $_POST['woocommerce_wootax_tc_key'] ) ) {
			$taxcloud_key = sanitize_text_field( wp_unslash( $_POST['woocommerce_wootax_tc_key'] ) ); // phpcs:ignore WordPress.CSRF.NonceVerification
		}

		$default_address = array(
			'Address1' => '',
			'Address2' => '',
			'City'     => '',
			'State'    => '',
			'Zip5'     => '',
			'Zip4'     => '',
			'ID'       => '',
			'Default'  => 'no',
		);

		$has_default   = false;
		$addresses     = array();
		$raw_addresses = isset( $_POST['addresses'] ) ? wc_clean( $_POST['addresses'] ) : array(); // phpcs:ignore WordPress.Security

		foreach ( $raw_addresses as $raw_address ) {
			// Use defaults for missing fields.
			$raw_address = array_map( 'sanitize_text_field', array_merge( $default_address, $raw_address ) );

			try {
				$address = new TaxCloud\Address(
					$raw_address['Address1'],
					$raw_address['Address2'],
					$raw_address['City'],
					$raw_address['State'],
					$raw_address['Zip5'],
					$raw_address['Zip4']
				);
			} catch ( Exception $ex ) {
				// Leave out address with error.
				$this->add_error(
					sprintf(
						/* translators: 1 - street address, 2 - error message */
						__( 'Failed to save address <em>%1$s</em>: %2$s', 'simple-sales-tax' ),
						$raw_address['Address1'],
						$ex->getMessage()
					)
				);
				continue;
			}

			try {
				$request = new TaxCloud\Request\VerifyAddress( $taxcloud_id, $taxcloud_key, $address );
				$address = TaxCloud()->VerifyAddress( $request );
			} catch ( Exception $ex ) {
				// Use original address.
				SST_Logger::add(
					/* translators: street address that failed validation */
					sprintf( __( 'Failed to validate address: %s.', 'simple-sales-tax' ), $ex->getMessage() )
				);
			}

			// Convert verified address to SST_Origin_Address.
			$is_default = 'yes' === $raw_address['Default'];

			$addresses[] = new SST_Origin_Address(
				count( $addresses ), // ID.
				$is_default,
				$address->getAddress1(),
				$address->getAddress2(),
				$address->getCity(),
				$address->getState(),
				$address->getZip5(),
				$address->getZip4()
			);

			$has_default = $has_default | $is_default;
		}

		// Ensure that a default address is configured.
		if ( ! $has_default && ! empty( $addresses ) ) {
			$addresses[0]->setDefault( true );
		}

		// JSON serialize for storage in DB.
		foreach ( $addresses as $key => $address ) {
			$addresses[ $key ] = wp_json_encode( $address );
		}

		return $addresses;
	}

	/**
	 * Force download log file if "Download Log" was clicked.
	 *
	 * @since 5.0
	 */
	public function maybe_download_log_file() {
		if ( ! isset( $_GET['download_log'] ) ) { // phpcs:ignore WordPress.CSRF.NonceVerification
			return;
		}

		// If file doesn't exist, create it.
		$log_path = SST_Logger::get_log_path();

		if ( ! file_exists( $log_path ) ) {
			$fh = @fopen( $log_path, 'a' );
			fclose( $fh );
		}

		// Force download.
		header( 'Content-Description: File Transfer' );
		header( 'Content-Type: application/octet-stream' );
		header( 'Content-Disposition: attachment; filename=' . basename( $log_path ) );
		header( 'Expires: 0' );
		header( 'Cache-Control: must-revalidate' );
		header( 'Pragma: public' );
		header( 'Content-Length: ' . filesize( $log_path ) );

		readfile( $log_path );
		exit;
	}

}
