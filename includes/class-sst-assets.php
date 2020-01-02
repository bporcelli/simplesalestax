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
	 * @var array Plugin assets.
	 */
	protected $assets = [];

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'init', [ $this, 'init_assets' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'register_assets' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'register_admin_assets' ] );
	}

	/**
	 * Initializes the assets array.
	 */
	public function init_assets() {
		$this->assets = [
			'sst-hideseek'              => [
				'type'       => 'script',
				'file'       => 'jquery.hideseek.min.js',
				'context'    => 'admin',
				'compressed' => true,
				'options'    => [
					'deps' => [ 'jquery' ],
					'ver'  => '0.7.1',
				],
			],
			'sst-backbone-modal'        => [
				'type'    => 'script',
				'file'    => 'backbone-modal.js',
				'context' => 'both',
				'options' => [ 'deps' => [ 'underscore', 'backbone', 'wp-util' ] ],
			],
			'sst-tic-select'            => [
				'type'    => 'script',
				'file'    => 'tic-select.js',
				'context' => 'admin',
				'options' => [
					'deps' => [
						'jquery',
						'sst-hideseek',
						'sst-backbone-modal',
					],
				],
			],
			'sst-admin-js'              => [
				'type'    => 'script',
				'file'    => 'admin.js',
				'context' => 'admin',
				'options' => [
					'deps'     => [ 'jquery' ],
					'localize' => [
						'SST' => [
							'strings' => [
								'enter_id_and_key' => __(
									'Please enter your API Login ID and API Key.',
									'simple-sales-tax'
								),
								'settings_valid'   => __(
									'Success! Your TaxCloud settings are valid.',
									'simple-sales-tax'
								),
								'verify_failed'    => __( 'Connection to TaxCloud failed.', 'simple-sales-tax' ),
							],
						],
					],
				],
			],
			'sst-admin-css'             => [
				'type'    => 'style',
				'file'    => 'admin.css',
				'context' => 'admin',
			],
			'sst-checkout'              => [
				'type'    => 'script',
				'file'    => 'checkout.js',
				'context' => 'frontend',
				'options' => [
					'deps'     => [
						'jquery',
						'wp-util',
						'underscore',
						'backbone',
						'sst-backbone-modal',
						'jquery-blockui',
					],
					'localize' => [
						'SSTCertData' => [
							'certificates'             => SST_Certificates::get_certificates_formatted(),
							'add_certificate_nonce'    => wp_create_nonce( 'sst_add_certificate' ),
							'delete_certificate_nonce' => wp_create_nonce( 'sst_delete_certificate' ),
							'ajaxurl'                  => admin_url( 'admin-ajax.php' ),
							'seller_name'              => SST_Settings::get( 'company_name' ),
							'images'                   => [
								'single_cert'  => SST()->url( 'assets/img/sp_exemption_certificate750x600.png' ),
								'blanket_cert' => SST()->url( 'assets/img/exemption_certificate750x600.png' ),
							],
							'strings'                  => [
								'delete_failed'      => __( 'Failed to delete certificate', 'simple-sales-tax' ),
								'add_failed'         => __( 'Failed to add certificate', 'simple-sales-tax' ),
								'delete_certificate' => __(
									'Are you sure you want to delete this certificate? This action is irreversible.',
									'simple-sales-tax'
								),
							],
						],
					],
				],
			],
			'sst-modal-css'             => [
				'type'    => 'style',
				'file'    => 'modal.css',
				'context' => 'both',
			],
			'sst-certificate-modal-css' => [
				'type'    => 'style',
				'file'    => 'certificate-modal.css',
				'context' => 'both',
			],
			'sst-view-certificate'      => [
				'type'    => 'script',
				'file'    => 'view-certificate.js',
				'context' => 'admin',
				'options' => [
					'deps' => [
						'jquery',
						'sst-backbone-modal',
					],
				],
			],
			'sst-address-table'         => [
				'type'    => 'script',
				'file'    => 'address-table.js',
				'context' => 'admin',
				'options' => [
					'deps' => [
						'jquery',
						'wp-util',
						'underscore',
						'backbone',
					],
				],
			],
		];
	}

	/**
	 * Registers frontend assets.
	 */
	public function register_assets() {
		$this->_register_assets( 'frontend' );
	}

	/**
	 * Registers admin assets.
	 */
	public function register_admin_assets() {
		$this->_register_assets( 'admin' );
	}

	/**
	 * Helper for registering assets.
	 *
	 * @param string $context 'admin' or 'frontend'
	 */
	private function _register_assets( $context ) {
		$js_base_url   = SST()->url( 'assets/js/' );
		$css_base_url  = SST()->url( 'assets/css/' );
		$load_minified = ! defined( 'SCRIPT_DEBUG' ) || ! SCRIPT_DEBUG;
		$defaults      = [
			'type'       => '',
			'file'       => '',
			'compressed' => false,
			'context'    => 'both',
			'options'    => [],
		];

		foreach ( $this->assets as $handle => $asset ) {
			$asset   = wp_parse_args( $asset, $defaults );
			$options = $asset['options'];
			$ver     = isset( $options['ver'] ) ? $options['ver'] : false;
			$deps    = [];

			if ( isset( $options['deps'] ) ) {
				$deps = $options['deps'];
			}

			if ( 'both' === $asset['context'] || $context === $asset['context'] ) {
				if ( 'script' === $asset['type'] ) {
					$src = $js_base_url . $asset['file'];

					if ( ! $asset['compressed'] && $load_minified ) {
						$src = str_replace( '.js', '.min.js', $src );
					}

					wp_register_script( $handle, $src, $deps, $ver );

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
