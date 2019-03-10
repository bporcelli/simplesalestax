<?php

/**
 * Plugin Name:          Simple Sales Tax
 * Plugin URI:           https://simplesalestax.com
 * Description:          Harness the power of TaxCloud to accurately calculate sales tax for your WooCommerce store.
 * Author:               Simple Sales Tax
 * Author URI:           https://simplesalestax.com
 * Version:              6.0.5
 * Requires at least:    4.5.0
 * Tested up to:         5.0.0
 * WC requires at least: 3.0.0
 * WC tested up to:      3.5.0
 * Text Domain:          simplesalestax
 * Domain Path:          /languages
 * License:              GPL-3.0+
 * License URI:          https://www.gnu.org/licenses/gpl-3.0.txt
 *
 * @copyright FedTax (email: support@simplesalestax.com)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require __DIR__ . '/includes/vendor/autoload.php';

require __DIR__ . '/includes/class-simple-sales-tax.php';

/**
 * Get the singleton SST instance.
 *
 * @since 4.2
 *
 * @return SimpleSalesTax
 */
function SST() {
	return SimpleSalesTax::init(
		__FILE__,
		[
			'requires' => [
				'plugins' => [
					'woocommerce/woocommerce.php' => [
						'name'    => __( 'WooCommerce', 'simplesalestax' ),
						'version' => '3.0',
					],
				],
				'php'     => '5.5',
			],
		]
	);
}

SST();
