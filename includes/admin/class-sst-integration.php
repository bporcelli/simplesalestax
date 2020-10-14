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
		add_action( 'admin_init', array( $this, 'maybe_dismiss_address_notice' ) );
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
	 * Output HTML for field of type 'origin_address_select'.
	 *
	 * @param string $key  Field key.
	 * @param array  $data Field data.
	 *
	 * @since 6.2
	 */
	public function generate_origin_address_select_html( $key, $data ) {
		$field            = "{$this->plugin_id}{$this->id}_{$key}";
		$origin_addresses = SST_Addresses::get_origin_addresses();
		$api_id           = SST_Settings::get( 'tc_id' );
		$api_key          = SST_Settings::get( 'tc_key' );

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
					<?php if ( ! empty( $origin_addresses ) ): ?>
						<select id="<?php echo esc_attr( $field ); ?>" name="<?php echo esc_attr( $field ); ?>[]"
							    class="wc-enhanced-select origin-address-select" multiple="multiple"
							    data-placeholder="<?php esc_attr_e( 'Select origin addresses', 'simple-sales-tax' ); ?>">
							<?php
							foreach ( $origin_addresses as $origin_address ) {
								printf(
									'<option value="%1$s"%2$s>%3$s</option>',
									esc_attr( $origin_address->getID() ),
									selected( $origin_address->getDefault(), true, false ),
									esc_html( SST_Addresses::format( $origin_address ) )
								);
							}
							?>
						</select>
					<?php elseif ( empty( $api_id ) || empty( $api_key ) ): ?>
						<div class="notice notice-info inline sst-settings-notice">
							<p>
								<?php
								_e(
									'Enter your TaxCloud API credentials and click <strong>Save changes</strong> to configure your Origin Addresses.',
									'simple-sales-tax'
								);
								?>
							</p>
						</div>
					<?php else: ?>
						<div class="notice notice-warning inline sst-settings-notice">
							<p>
								<?php
								// todo: fix redirect location. appears to be broken.
								_e(
									'Oops! It appears there are no addresses in your TaxCloud account. Please add at least one address on the <a href="https://simplesalestax.com/taxcloud/locations/" target="_blank">Locations</a> page in TaxCloud and then save your settings to refresh the address list.',
									'simple-sales-tax'
								);
								?>
							</p>
						</div>
					<?php endif; ?>
					<?php echo $this->get_description_html( $data ); ?>
				</fieldset>
			</td>
		</tr>
		<?php

		return ob_get_clean();
	}

	/**
	 * Validate and save default origin addresses when options are saved.
	 *
	 * @param string $key Settings field key.
	 * @param array $value Selected origin address IDs.
	 *
	 * @return array
	 * @since 6.2
	 * @throws Exception
	 */
	public function validate_default_origin_addresses_field( $key, $value ) {
		$addresses = SST_Addresses::get_origin_addresses();

		// You need addresses to have default addresses
		if ( empty( $addresses ) ) {
			return array();
		}

		if ( empty( $value ) ) {
			throw new Exception( __( 'Please select at least one origin address.', 'simple-sales-tax' ) );
		}

		$selected_addresses = array_map( 'sanitize_title', (array) $value );

		foreach ( $addresses as $address ) {
			$address->setDefault( in_array( $address->getID(), $selected_addresses ) );
		}

		SST_Settings::set( 'addresses', array_map( 'json_encode', $addresses ) );

		return $selected_addresses;
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

	/**
	 * Processes and saves options, refreshing the origin address list in the process.
	 *
	 * If there is an error thrown, will continue to save and validate fields, but will leave the erroring field out.
	 *
	 * @return bool was anything saved?
	 */
	public function process_admin_options() {
		$result = parent::process_admin_options();

		/**
		 * Refresh the origin address list.
		 *
		 * Hold on refreshing if the user hasn't applied the 6.2 data update yet,
		 * since refreshing under these conditions can break sales tax calcs for
		 * products that have their origin addresses set with the old address keys.
		 */
		// todo: revert
		if ( true ) {//version_compare( get_option( 'wootax_version' ), '6.2', '>=' ) ) {
			SST_Settings::set(
				'addresses',
				array_map( 'json_encode', SST_Addresses::get_origin_addresses( true ) )
			);
		}

		return $result;
	}

	/**
	 * Dismisses the 6.2 update address mismatch notice if the appropriate query var is set.
	 */
	public function maybe_dismiss_address_notice() {
		if ( isset( $_GET['dismiss_sst_address_notice'] ) ) {
			WC_Admin_Notices::remove_notice( 'sst_address_mismatch' );
		}
	}

}
