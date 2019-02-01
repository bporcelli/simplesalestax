<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Simple Sales Tax.
 *
 * Main plugin class.
 *
 * @author  Simple Sales Tax
 * @package SST
 * @since   6.0.0
 */
final class SimpleSalesTax extends \WordFrame\v1_1_2\Plugin {

	/**
	 * @var string Plugin version.
	 */
	public $version = '6.0.4';

	/**
	 * Bootstraps the plugin when all requirements are met.
	 */
	public function load() {
		parent::load();

		$this->define_constants();
		$this->includes();
		$this->check_updates();
	}

	/**
	 * Defines plugin constants.
	 */
	protected function define_constants() {
		define( 'SST_DEFAULT_SHIPPING_TIC', 11010 );
		define( 'SST_SHIPPING_ITEM', 'SHIPPING' );
		define( 'SST_DEFAULT_FEE_TIC', 10010 );
		define( 'SST_RATE_ID', get_option( 'wootax_rate_id' ) );
		define( 'SST_PLUGIN_BASENAME', plugin_basename( $this->file ) );
	}

	/**
	 * Runs on plugin activation.
	 *
	 * Adjusts WooCommerce settings for optimal plugin performance.
	 *
	 * @since 6.0.0
	 */
	public function activate() {
		update_option( 'woocommerce_calc_taxes', 'yes' );
		update_option( 'woocommerce_prices_include_tax', 'no' );
		update_option( 'woocommerce_tax_based_on', 'shipping' );
		update_option( 'woocommerce_default_customer_address', 'base' );
		update_option( 'woocommerce_shipping_tax_class', '' );
		update_option( 'woocommerce_tax_round_at_subtotal', false );
		update_option( 'woocommerce_tax_display_shop', 'excl' );
		update_option( 'woocommerce_tax_display_cart', 'excl' );
		update_option( 'woocommerce_tax_total_display', 'itemized' );
	}

	/**
	 * Runs on plugin deactivation.
	 *
	 * @since 6.0.0
	 */
	public function deactivate() {
		SST_Install::deactivate();
	}

	/**
	 * Include required plugin files.
	 *
	 * @since 4.4
	 */
	private function includes() {
		/**
		 * Abstract classes.
		 */
		include_once __DIR__ . '/abstracts/class-sst-abstract-cart.php';

		/**
		 * Core classes.
		 */
		include_once __DIR__ . '/sst-functions.php';
		include_once __DIR__ . '/sst-compatibility-functions.php';
		include_once __DIR__ . '/class-sst-install.php';
		include_once __DIR__ . '/class-sst-settings.php';
		include_once __DIR__ . '/class-sst-logger.php';
		include_once __DIR__ . '/class-sst-plugin-updater.php';
		include_once __DIR__ . '/class-sst-ajax.php';
		include_once __DIR__ . '/class-sst-tic.php';
		include_once __DIR__ . '/class-sst-product.php';
		include_once __DIR__ . '/class-sst-shipping.php';
		include_once __DIR__ . '/class-sst-addresses.php';
		include_once __DIR__ . '/class-sst-origin-address.php';
		include_once __DIR__ . '/class-sst-certificates.php';
		include_once __DIR__ . '/class-sst-order.php';
		include_once __DIR__ . '/class-sst-order-controller.php';
		include_once __DIR__ . '/class-sst-assets.php';

		/**
		 * Subscriptions support.
		 */
		if ( sst_subs_active() ) {
			include_once __DIR__ . '/class-sst-subscriptions.php';
		}

		/**
		 * Admin only.
		 */
		if ( $this->is_request( 'admin' ) ) {
			include_once __DIR__ . '/admin/class-sst-admin.php';
		}

		/**
		 * Frontend only.
		 */
		if ( $this->is_request( 'frontend' ) ) {
			include_once __DIR__ . '/frontend/class-sst-cart-proxy.php';
			include_once __DIR__ . '/frontend/class-sst-checkout.php';
		}
	}

	/**
	 * Check for updates.
	 *
	 * @since 5.0
	 */
	private function check_updates() {
		new SST_Plugin_Updater(
			'https://simplesalestax.com',
			$this->file,
			[
				'version' => $this->version, // current version number
			]
		);
	}

	/**
	 * What type of request is this?
	 *
	 * @since 4.4
	 *
	 * @param string $type ajax, frontend or admin
	 *
	 * @return bool
	 */
	private function is_request( $type ) {
		switch ( $type ) {
			case 'admin' :
				return is_admin();
			case 'ajax' :
				return defined( 'DOING_AJAX' );
			case 'cron' :
				return defined( 'DOING_CRON' );
			case 'frontend' :
				return ( ! is_admin() || defined( 'DOING_AJAX' ) ) && ! defined( 'DOING_CRON' );
		}

		return false;
	}

	/**
	 * Loads the plugin text domain.
	 */
	public function load_text_domain() {
		load_plugin_textdomain( 'simplesalestax', false, basename( dirname( $this->file ) ) . '/languages' );
	}

	/**
	 * Returns the text to display in the notice when a required plugin is missing.
	 *
	 * @param array $violation
	 *
	 * @return string
	 */
	public function get_plugin_notice( array $violation ) {
		switch ( $violation['type'] ) {
			case 'wrong_version':
				return sprintf(
				/* translators: 1: required plugin name, 2: minimum version */
					__(
						'<strong>%1$s needs to be updated.</strong> Simple Sales Tax requires %1$s %2$s+.',
						'simplesalestax'
					),
					$violation['data']['name'],
					$violation['data']['version']
				);
			case 'inactive':
			case 'not_installed':
				return sprintf(
				/* translators: 1: required plugin name */
					__(
						'<strong>%1$s not detected.</strong> Please install or activate %1$s to use Simple Sales Tax.',
						'simplesalestax'
					),
					$violation['data']['name']
				);
		}

		return '';
	}

	/**
	 * Returns the text to display when a PHP requirement is not met.
	 *
	 * @param array $violation Information about the missing requirement.
	 *
	 * @return string
	 */
	public function get_php_notice( $violation ) {
		if ( 'extensions' === $violation['type'] ) {
			$ext_list = implode( ', ', $violation['data']['required'] );

			/* translators: 1 - list of required PHP extensions */
			return sprintf(
				__(
					'<strong>Required PHP extensions are missing.</strong> Simple Sales Tax requires %1$s.',
					'simplesalestax'
				),
				$ext_list
			);
		} elseif ( 'version' === $violation['type'] ) {
			/* translators: 1 - required php version */
			return sprintf(
				__(
					'<strong>PHP needs to be updated.</strong> Simple Sales Tax requires PHP %1$s+.',
					'simplesalestax'
				),
				$violation['data']['required']
			);
		}

		return '';
	}

}
