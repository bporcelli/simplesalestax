<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SST_Assets
 *
 * Registers common assets.
 */
class SST_Assets {

	/**
	 * Plugin assets.
	 *
	 * @var array
	 */
	protected $assets = array();

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'init_assets' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'register_admin_assets' ) );
	}

	/**
	 * Initializes the assets array.
	 */
	public function init_assets() {
		$this->assets = array(
			'sst-hideseek'              => array(
				'type'       => 'script',
				'file'       => 'jquery.hideseek.min.js',
				'context'    => 'both',
				'compressed' => true,
				'options'    => array(
					'deps' => array( 'jquery' ),
					'ver'  => '0.7.1',
				),
			),
			'sst-backbone-modal'        => array(
				'type'    => 'script',
				'file'    => 'backbone-modal.js',
				'context' => 'both',
				'options' => array( 'deps' => array( 'underscore', 'backbone', 'wp-util' ) ),
			),
			'sst-tic-select'            => array(
				'type'    => 'script',
				'file'    => 'tic-select.js',
				'context' => 'both',
				'options' => array(
					'deps' => array(
						'jquery',
						'sst-hideseek',
						'sst-backbone-modal',
					),
				),
			),
			'sst-wcv-tic-select'        => array(
				'type'    => 'script',
				'file'    => 'wcv-tic-select.js',
				'context' => 'frontend',
				'options' => array(
					'deps' => array(
						'jquery',
					),
				),
			),
			'sst-wcfm-tic-select'       => array(
				'type'    => 'script',
				'file'    => 'wcfm-tic-select.js',
				'context' => 'frontend',
				'options' => array(
					'deps' => array(
						'jquery',
					),
				),
			),
			'sst-tic-select-css'            => array(
				'type'    => 'style',
				'file'    => 'tic-select.css',
				'context' => 'frontend',
				'options' => array(
					'deps' => array( 'sst-modal-css' ),
				),
			),
			'sst-admin-js'              => array(
				'type'    => 'script',
				'file'    => 'admin.js',
				'context' => 'admin',
				'options' => array(
					'deps'     => array( 'jquery' ),
					'localize' => array(
						'SST' => array(
							'strings' => array(
								'enter_id_and_key' => __(
									'Please enter your API Login ID and API Key.',
									'simple-sales-tax'
								),
								'settings_valid'   => __(
									'Success! Your TaxCloud settings are valid.',
									'simple-sales-tax'
								),
								'verify_failed'    => __( 'Connection to TaxCloud failed.', 'simple-sales-tax' ),
							),
						),
					),
				),
			),
			'sst-admin-css'             => array(
				'type'    => 'style',
				'file'    => 'admin.css',
				'context' => 'admin',
			),
			'sst-checkout'              => array(
				'type'    => 'script',
				'file'    => 'checkout.js',
				'context' => 'frontend',
				'options' => array(
					'deps'     => array(
						'jquery',
						'wp-util',
						'underscore',
						'backbone',
						'sst-backbone-modal',
						'jquery-blockui',
					),
					'localize' => array(
						'SSTCertData' => array(
							'certificates'             => SST_Certificates::get_certificates_formatted(),
							'add_certificate_nonce'    => wp_create_nonce( 'sst_add_certificate' ),
							'delete_certificate_nonce' => wp_create_nonce( 'sst_delete_certificate' ),
							'ajaxurl'                  => admin_url( 'admin-ajax.php' ),
							'seller_name'              => SST_Settings::get( 'company_name' ),
							'images'                   => array(
								'single_cert'  => SST()->url( 'assets/img/sp_exemption_certificate750x600.png' ),
								'blanket_cert' => SST()->url( 'assets/img/exemption_certificate750x600.png' ),
							),
							'strings'                  => array(
								'delete_failed'      => __( 'Failed to delete certificate', 'simple-sales-tax' ),
								'add_failed'         => __( 'Failed to add certificate', 'simple-sales-tax' ),
								'delete_certificate' => __(
									'Are you sure you want to delete this certificate? This action is irreversible.',
									'simple-sales-tax'
								),
							),
						),
					),
				),
			),
			'sst-modal-css'             => array(
				'type'    => 'style',
				'file'    => 'modal.css',
				'context' => 'both',
			),
			'sst-certificate-modal-css' => array(
				'type'    => 'style',
				'file'    => 'certificate-modal.css',
				'context' => 'both',
			),
			'sst-view-certificate'      => array(
				'type'    => 'script',
				'file'    => 'view-certificate.js',
				'context' => 'admin',
				'options' => array(
					'deps' => array(
						'jquery',
						'sst-backbone-modal',
					),
				),
			),
			'sst-address-table'         => array(
				'type'    => 'script',
				'file'    => 'address-table.js',
				'context' => 'admin',
				'options' => array(
					'deps' => array(
						'jquery',
						'wp-util',
						'underscore',
						'backbone',
					),
				),
			),
		);
	}

	/**
	 * Registers frontend assets.
	 */
	public function register_assets() {
		$this->register_assets_for_context( 'frontend' );
	}

	/**
	 * Registers admin assets.
	 */
	public function register_admin_assets() {
		$this->register_assets_for_context( 'admin' );
	}

	/**
	 * Helper for registering assets.
	 *
	 * @param string $context Context to register assets for. Can be 'admin' or 'frontend'.
	 */
	private function register_assets_for_context( $context ) {
		$js_base_url   = SST()->url( 'assets/js/' );
		$css_base_url  = SST()->url( 'assets/css/' );
		$load_minified = ! defined( 'SCRIPT_DEBUG' ) || ! SCRIPT_DEBUG;
		$defaults      = array(
			'type'       => '',
			'file'       => '',
			'compressed' => false,
			'context'    => 'both',
			'options'    => array(),
		);

		foreach ( $this->assets as $handle => $asset ) {
			$asset   = wp_parse_args( $asset, $defaults );
			$options = $asset['options'];
			$ver     = isset( $options['ver'] ) ? $options['ver'] : false;
			$deps    = array();

			if ( isset( $options['deps'] ) ) {
				$deps = $options['deps'];
			}

			if ( 'both' === $asset['context'] || $context === $asset['context'] ) {
				if ( 'script' === $asset['type'] ) {
					$src = $js_base_url . $asset['file'];

					if ( ! $asset['compressed'] && $load_minified ) {
						$src = str_replace( '.js', '.min.js', $src );
					}

					wp_register_script( $handle, $src, $deps, $ver, true );

					if ( isset( $options['localize'] ) ) {
						foreach ( $options['localize'] as $object_name => $data ) {
							wp_localize_script( $handle, $object_name, $data );
						}
					}
				} else {
					$src = $css_base_url . $asset['file'];

					if ( ! $asset['compressed'] && $load_minified ) {
						$src = str_replace( '.css', '.min.css', $src );
					}

					wp_register_style( $handle, $src, $deps, $ver );
				}
			}
		}
	}

}

new SST_Assets();
