<?php

/**
 * Plugin Name:          Simple Sales Tax
 * Description:          Harness the power of TaxCloud to accurately calculate sales tax for your WooCommerce store.
 * Author:               TaxCloud
 * Author URI:           https://taxcloud.com
 * GitHub Plugin URI:    https://github.com/bporcelli/simplesalestax
 * Version:              6.0.10
 * Text Domain:          simplesalestax
 * Domain Path:          /languages/
 *
 * Requires at least:    4.5.0
 * Tested up to:         5.3.0
 * WC requires at least: 3.0.0
 * WC tested up to:      3.8.0
 *
 * @category             Plugin
 * @copyright            Copyright © 2019 The Federal Tax Authority, LLC
 * @author               Brett Porcelli
 * @license              GPL2
 *
 * Simple Sales Tax is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by the
 * Free Software Foundation, either version 2 of the License, or any later
 * version.
 *
 * Simple Sales Tax is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
 * General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with
 * Simple Sales Tax. If not, see http://www.gnu.org/licenses/gpl-2.0.txt.
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
