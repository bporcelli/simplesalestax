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
	 * List of all blocks to register.
	 */
	protected static $blocks = array(
		'tax-exemption',
	);

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
		add_action( 'init', array( $this, 'register_blocks' ) );
	}

	/**
	 * Registers all SST blocks.
	 */
	public function register_blocks() {
		$build_dir = SST_DIR . '/build';
		$blocks    = array(
			'tax-exemption',
		);

		foreach ( $blocks as $block ) {
			register_block_type( "{$build_dir}/{$block}" );
		}
	}

}

SST_Blocks::instance();
