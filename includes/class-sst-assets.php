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
			[
				'type'    => 'script',
				'slug'    => 'simplesalestax.jquery.hideseek',
				'context' => 'admin',
				'options' => [
					'deps' => [ 'jquery' ],
					'ver'  => '0.7.1',
				],
			],
			[
				'type'    => 'script',
				'slug'    => 'simplesalestax.backbone-modal',
				'context' => 'both',
				'options' => [ 'deps' => [ 'underscore', 'backbone', 'wp-util' ] ],
			],
			[
				'type'    => 'script',
				'slug'    => 'simplesalestax.tic-select',
				'context' => 'admin',
				'options' => [
					'deps' => [
						'jquery',
						'simplesalestax.jquery.hideseek',
						'simplesalestax.backbone-modal',
					],
				],
			],
			[
				'type'    => 'script',
				'slug'    => 'simplesalestax.admin',
				'context' => 'admin',
				'options' => [
					'deps'     => [ 'jquery' ],
					'localize' => [
						'SST' => [
							'strings' => [
								'enter_id_and_key' => __(
									'Please enter your API Login ID and API Key.',
									'simplesalestax'
								),
								'settings_valid'   => __(
									'Success! Your TaxCloud settings are valid.',
									'simplesalestax'
								),
								'verify_failed'    => __( 'Connection to TaxCloud failed.', 'simplesalestax' ),
							],
						],
					],
				],
			],
			[
				'type'    => 'script',
				'slug'    => 'simplesalestax.checkout',
				'context' => 'frontend',
				'options' => [
					'deps'     => [
						'jquery',
						'wp-util',
						'underscore',
						'backbone',
						'simplesalestax.backbone-modal',
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
								'single_cert'  => SST()->url( '/assets/img/sp_exemption_certificate750x600.png' ),
								'blanket_cert' => SST()->url( '/assets/img/exemption_certificate750x600.png' ),
							],
							'strings'                  => [
								'delete_failed'      => __( 'Failed to delete certificate', 'simplesalestax' ),
								'add_failed'         => __( 'Failed to add certificate', 'simplesalestax' ),
								'delete_certificate' => __(
									'Are you sure you want to delete this certificate? This action is irreversible.',
									'simplesalestax'
								),
							],
						],
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
		foreach ( $this->assets as $asset ) {
			$defaults = [
				'type'    => '',
				'slug'    => '',
				'context' => 'both',
				'options' => [],
			];

			$asset = wp_parse_args( $asset, $defaults );

			if ( 'both' === $asset['context'] || $context === $asset['context'] ) {
				SST()->assets->register( $asset['type'], $asset['slug'], $asset['options'] );
			}
		}
	}

}

new SST_Assets();
