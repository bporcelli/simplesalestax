<?php
/**
 * Blocks.
 *
 * Registers Simple Sales Tax blocks.
 *
 * @package simple-sales-tax
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SST_Blocks.
 *
 * @package simple-sales-tax
 * @since 8.1
 */
class SST_Blocks {

	/**
	 * Singleton instance.
	 *
	 * @var SST_Blocks
	 */
	protected static $_instance = null;

	/**
	 * Singleton instance accessor.
	 *
	 * @return SST_Blocks
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	/**
	 * SST_Blocks constructor.
	 */
	protected function __construct() {
		add_action(
			'woocommerce_blocks_loaded',
			array( $this, 'register_blocks' )
		);
		add_action(
			'woocommerce_blocks_loaded',
			array( $this, 'register_update_callback' )
		);
	}

	/**
	 * Registers all SST blocks.
	 */
	public function register_blocks() {
		require_once __DIR__ . '/class-sst-blocks-integration.php';

		add_action(
			'woocommerce_blocks_checkout_block_registration',
			function( $integration_registry ) {
				$integration_registry->register( new SST_Blocks_Integration() );
			}
		);
	}

	/**
	 * Register Store API update callback.
	 */
	public function register_update_callback() {
		woocommerce_store_api_register_update_callback(
			array(
				'namespace' => 'simple-sales-tax',
				'callback'  => array( $this, 'handle_cart_update' ),
			)
		);
	}

		/**
	 * Handle cart updates triggered by `extensionCartUpdate`.
	 *
	 * @param array $data Data payload from `extensionCartUpdate`
	 */
	public function handle_cart_update( $data ) {
		$action = $data['action'] ?? '';

		switch ( $action ) {
			case 'recalculate':
				// Set certificate_id so SST_Checkout knows to remove tax
				$_POST['certificate_id'] = $data['certificate_id'];
				break;
		}
	}

}

SST_Blocks::instance();
