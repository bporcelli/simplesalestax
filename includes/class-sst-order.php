<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Order.
 *
 * Extends WC_Order to add extra functionality.
 *
 * @author  Simple Sales Tax
 * @package SST
 * @since   5.0
 */
class SST_Order extends SST_Abstract_Cart {

	/**
	 * @var string Prefix for meta keys.
	 * @since 5.0
	 */
	protected static $prefix = '_wootax_';

	/**
	 * @var array Default values for order meta fields.
	 * @since 4.4
	 */
	protected static $defaults = [
		'packages'      => [],
		'package_cache' => [],
		'exempt_cert'   => null,
		'status'        => 'pending',
	];

	/**
	 * @var WC_Order WooCommerce order.
	 * @since 5.0
	 */
	protected $order = null;

	/**
	 * Constructor.
	 *
	 * @since 5.0
	 *
	 * @param mixed $order Order ID or WC_Order instance.
	 */
	public function __construct( $order ) {
		if ( is_numeric( $order ) ) {
			$this->order = wc_get_order( $order );
		} else {
			if ( is_a( $order, 'WC_Order' ) ) {
				$this->order = $order;
			}
		}

		parent::__construct();
	}

	/**
	 * Forward method calls to the encapsulated WC_Order instance.
	 *
	 * @since 5.0
	 *
	 * @param string $name
	 * @param array  $args
	 *
	 * @return mixed
	 */
	public function __call( $name, $args = [] ) {
		if ( is_callable( array( $this->order, $name ) ) ) {
			return call_user_func_array( array( $this->order, $name ), $args );
		}

		return null;
	}

	/**
	 * Can we perform a lookup for the given package?
	 *
	 * @since 5.0
	 *
	 * @param array $package
	 *
	 * @return bool
	 */
	protected function ready_for_lookup( $package ) {
		if ( 'pending' !== $this->get_taxcloud_status() || 0 < $this->order->get_total_refunded() ) {
			return false;
		}

		return parent::ready_for_lookup( $package );
	}

	/**
	 * Get saved packages for this order.
	 *
	 * @since 5.0
	 *
	 * @return array
	 */
	public function get_packages() {
		// use array_values so package keys are integers (we want nice order IDs like 9004_0, 9004_1,... in TaxCloud)
		$packages = $this->get_meta( 'packages' );

		if ( ! is_array( $packages ) ) {
			return [];
		}

		return array_values( $packages );
	}

	/**
	 * Set saved packages for this order.
	 *
	 * @since 5.0
	 *
	 * @param $packages array (default: array())
	 */
	public function set_packages( $packages = array() ) {
		if ( ! is_array( $packages ) ) {
			$packages = [];
		}

		$this->update_meta( 'packages', $packages );
	}

	/**
	 * Transform an array of cart items to match the format expected during
	 * checkout.
	 *
	 * @since 5.0
	 *
	 * @param array $cart_items
	 *
	 * @return array
	 */
	protected function transform_items( $cart_items ) {
		$new_items = [];

		foreach ( $cart_items as $item_id => $item ) {
			$product_id = $item['variation_id'] ? $item['variation_id'] : $item['product_id'];

			if ( ( $product = wc_get_product( $product_id ) ) ) {
				$new_items[ $item_id ] = [
					'product_id'    => $item['product_id'],
					'variation_id'  => $item['variation_id'],
					'quantity'      => $item['qty'],
					'line_total'    => $item['line_total'],
					'line_subtotal' => $item['line_subtotal'],
					'data'          => $product,
				];
			}
		}

		return $new_items;
	}

	/**
	 * Get base shipping packages for order.
	 *
	 * @since 5.5
	 *
	 * @return array
	 */
	protected function get_base_packages() {
		$packages = [];
		$items    = $this->transform_items( $this->order->get_items() );

		/* Create a virtual package for all items that don't need shipping */
		if ( ( $virtual_package = $this->create_virtual_package( $items ) ) ) {
			$packages[] = $virtual_package;
		}

		/* Create an additional package for each shipping method. */
		$ship_methods = $this->order->get_shipping_methods();

		if ( $ship_methods && $items ) {
			$items_per_package = ceil( count( $items ) / count( $ship_methods ) );
			$package_items     = array_chunk( $items, $items_per_package, true );

			foreach ( $package_items as $contents ) {
				$method = current( $ship_methods );

				/* Assign shipping method to package. */
				$package = sst_create_package(
					[
						'contents' => $contents,
						'shipping' => new WC_Shipping_Rate(
							key( $ship_methods ),   // id
							'',               // name
							$method['cost'],        // cost
							[],                     // taxes
							$method['method_id']    // method id
						),
						'user'     => [
							'ID' => $this->order->get_user_id(),
						],
					]
				);

				/* Set destination based on shipping method. */
				if ( SST_Shipping::is_local_pickup( [ $package['shipping']->method_id ] ) ) {
					$pickup_address = apply_filters(
						'wootax_pickup_address',
						SST_Addresses::get_default_address(),
						$this->order
					);

					$package['destination'] = [
						'country'   => 'US',
						'address'   => $pickup_address->getAddress1(),
						'address_2' => $pickup_address->getAddress2(),
						'city'      => $pickup_address->getCity(),
						'state'     => $pickup_address->getState(),
						'postcode'  => $pickup_address->getZip5(),
					];
				} else {
					$package['destination'] = $this->get_shipping_address();
				}

				$packages[] = $package;

				next( $ship_methods );
			}
		}

		return $packages;
	}

	/**
	 * Creates a virtual shipping package containing all items that don't need
	 * shipping.
	 *
	 * @param array $items Items from order.
	 *
	 * @return array|false Package, or false if all items need shipping.
	 */
	protected function create_virtual_package( &$items ) {
		$virtual_items = [];

		foreach ( $items as $key => $item ) {
			if ( isset( $item['data'] ) && ! $item['data']->needs_shipping() ) {
				$virtual_items[ $key ] = $item;
				unset( $items[ $key ] );
			}
		}

		if ( ! empty( $virtual_items ) ) {
			return sst_create_package(
				[
					'contents'    => $virtual_items,
					'destination' => $this->get_billing_address(),
					'user'        => [
						'ID' => $this->order->get_user_id(),
					],
				]
			);
		}

		return [];
	}

	/**
	 * Create shipping packages for order.
	 *
	 * @since 5.0
	 *
	 * @return array
	 */
	public function create_packages() {
		$packages = [];

		/* Let devs change the packages before we split them. */
		$raw_packages = apply_filters(
			'wootax_order_packages_before_split',
			$this->get_filtered_packages(),
			$this->order
		);

		/* Split packages by origin address. */
		foreach ( $raw_packages as $raw_package ) {
			$packages = array_merge( $packages, $this->split_package( $raw_package ) );
		}

		/* Add fees to first package. */
		if ( apply_filters( 'wootax_add_fees', true ) ) {
			$fees = [];

			foreach ( $this->order->get_fees() as $item_id => $fee ) {
				$name   = empty( $fee['name'] ) ? __( 'Fee', 'simplesalestax' ) : $fee['name'];
				$fee_id = sanitize_title( $name );

				$fees[ $item_id ] = (object) [
					'id'     => $fee_id,
					'amount' => $fee['line_total'],
				];
			}

			$packages[ key( $packages ) ]['fees'] = $fees;
		}

		return apply_filters( 'wootax_order_packages', $packages, $this->order );
	}

	/**
	 * Reset sales tax totals.
	 *
	 * @since 5.0
	 */
	protected function reset_taxes() {
		/* Remove tax from products and fees */
		foreach ( $this->order->get_items( [ 'line_item', 'fee' ] ) as $item_id => $item ) {
			$tax_data = $item['line_tax_data'];

			if ( isset( $tax_data['total'][ SST_RATE_ID ] ) ) {
				$item['line_tax'] -= $tax_data['total'][ SST_RATE_ID ];
				unset( $tax_data['total'][ SST_RATE_ID ] );
			}

			if ( isset( $tax_data['subtotal'][ SST_RATE_ID ] ) ) {
				$item['line_subtotal_tax'] -= $tax_data['subtotal'][ SST_RATE_ID ];
				unset( $tax_data['subtotal'][ SST_RATE_ID ] );
			}

			$item['line_tax_data'] = $tax_data;
		}

		/* Remove shipping tax */
		foreach ( $this->order->get_shipping_methods() as $method ) {
			if ( ! isset( $method['taxes'] ) ) {
				continue;
			}

			$tax_data = $method['taxes'];

			if ( isset( $tax_data['total'][ SST_RATE_ID ] ) ) {
				$item['total_tax'] -= $tax_data['total'][ SST_RATE_ID ];
				unset( $tax_data['total'][ SST_RATE_ID ] );
			}

			$method['taxes'] = $tax_data;
		}

		/* Reset totals */
		$this->update_taxes();
	}

	/**
	 * Update sales tax totals.
	 *
	 * @since 5.0
	 */
	protected function update_taxes() {
		$this->order->update_taxes();
	}

	/**
	 * Set the tax for a product.
	 *
	 * @since 5.0
	 *
	 * @param mixed $id  Item ID.
	 * @param float $tax Sales tax for product.
	 */
	protected function set_product_tax( $id, $tax ) {
		$item     = $this->order->get_item( $id );
		$tax_data = $item->get_taxes( 'edit' );

		if ( ! is_array( $tax_data ) ) {
			$tax_data = [ 'total' => [], 'subtotal' => [] ];
		}

		$tax_data['total'][ SST_RATE_ID ]    = $tax;
		$tax_data['subtotal'][ SST_RATE_ID ] = $tax;

		$item->set_taxes( $tax_data );
		$item->save();

		// Add the modified item to the order so the changes take effect
		$this->order->add_item( $item );
	}

	/**
	 * Set the tax for a shipping item.
	 *
	 * @since 5.0
	 *
	 * @param mixed $id  Item ID.
	 * @param float $tax Sales tax for item.
	 */
	protected function set_shipping_tax( $id, $tax ) {
		$this->set_product_tax( $id, $tax );
	}

	/**
	 * Set the tax for a fee.
	 *
	 * @since 5.0
	 *
	 * @param mixed $id  Fee ID.
	 * @param float $tax Sales tax for fee.
	 */
	protected function set_fee_tax( $id, $tax ) {
		$this->set_product_tax( $id, $tax );
	}

	/**
	 * Get the exemption certificate for the customer.
	 *
	 * @since 5.0
	 *
	 * @return TaxCloud\ExemptionCertificateBase
	 */
	public function get_certificate() {
		$certificate = $this->get_meta( 'exempt_cert' );

		if ( ! is_a( $certificate, 'TaxCloud\ExemptionCertificateBase' ) ) {
			return null;
		}

		return $certificate;
	}

	/**
	 * Sets the exemption certificate for the order.
	 *
	 * @since 6.0.7
	 *
	 * @param TaxCloud\ExemptionCertificateBase $certificate
	 */
	public function set_certificate( $certificate ) {
		if ( ! is_a( $certificate, 'TaxCloud\ExemptionCertificateBase' ) ) {
			$certificate = null;
		}

		$this->update_meta( 'exempt_cert', $certificate );
	}

	/**
	 * Handle an error by logging it or displaying it to the user.
	 *
	 * @since 5.0
	 *
	 * @param string $message Message describing the error.
	 *
	 * @throws Exception
	 */
	protected function handle_error( $message ) {
		SST_Logger::add( $message );

		if ( ! defined( 'DOING_AJAX' ) && function_exists( 'sst_add_message' ) ) {
			sst_add_message( $message, 'error' );
		}
	}

	/**
	 * Get TaxCloud status.
	 *
	 * @since 5.0
	 *
	 * @param string $context (default: 'edit')
	 *
	 * @return string
	 */
	public function get_taxcloud_status( $context = 'edit' ) {
		$status = $this->get_meta( 'status' );

		if ( 'view' == $context ) {
			$status = ucfirst( $status );
		}

		return $status;
	}

	/**
	 * Get billing address.
	 *
	 * @since 5.5
	 *
	 * @return array
	 */
	protected function get_billing_address() {
		return [
			'country'   => $this->order->get_billing_country(),
			'address'   => $this->order->get_billing_address_1(),
			'address_2' => $this->order->get_billing_address_2(),
			'city'      => $this->order->get_billing_city(),
			'state'     => $this->order->get_billing_state(),
			'postcode'  => $this->order->get_billing_postcode(),
		];
	}

	/**
	 * Get shipping address.
	 *
	 * @since 5.5
	 *
	 * @return array
	 */
	protected function get_shipping_address() {
		return [
			'country'   => $this->order->get_shipping_country(),
			'address'   => $this->order->get_shipping_address_1(),
			'address_2' => $this->order->get_shipping_address_2(),
			'city'      => $this->order->get_shipping_city(),
			'state'     => $this->order->get_shipping_state(),
			'postcode'  => $this->order->get_shipping_postcode(),
		];
	}

	/**
	 * Get destination address.
	 *
	 * NOTE: This method is solely maintained for backward compatibility with
	 * the sst_update_50_order_data update routine. It should generally not be
	 * used elsewhere.
	 *
	 * @since 5.0
	 *
	 * @return TaxCloud\Address|NULL
	 */
	public function get_destination_address() {
		if ( 'billing' === get_option( 'woocommerce_tax_based_on' ) ) {
			$raw_address = $this->get_billing_address();
		} else {
			$raw_address = $this->get_shipping_address();
		}

		try {
			$address = new TaxCloud\Address(
				$raw_address['address'],
				$raw_address['address_2'],
				$raw_address['city'],
				$raw_address['state'],
				substr( $raw_address['postcode'], 0, 5 )
			);

			return SST_Addresses::verify_address( $address );
		} catch ( Exception $ex ) {
			return null;
		}
	}

	/**
	 * Get order id for given package.
	 *
	 * @since 5.0
	 *
	 * @param string $package_key
	 * @param array  $package (default: array())
	 *
	 * @return string
	 */
	protected function get_package_order_id( $package_key, $package = array() ) {
		if ( isset( $package['order_id'] ) ) { /* Legacy (pre 5.0) order */
			return $package['order_id'];
		}

		return $this->order->get_id() . '_' . $package_key;
	}

	/**
	 * Send AuthorizedWithCapture request to capture order in TaxCloud.
	 *
	 * @since 5.0
	 *
	 * @return bool true on success, false on failure.
	 *
	 * @throws Exception
	 */
	public function do_capture() {
		$taxcloud_status = $this->get_taxcloud_status();
		$packages        = $this->get_packages();

		// Handle error cases
		if ( 'captured' == $taxcloud_status ) {
			if ( 'no' == SST_Settings::get( 'capture_immediately' ) ) {
				$this->handle_error(
					sprintf(
						__( "Failed to capture order %d: already captured.", 'simplesalestax' ),
						$this->order->get_id()
					)
				);
			}

			return false;
		} else {
			if ( 'refunded' == $taxcloud_status ) {
				$this->handle_error(
					sprintf(
						__( "Failed to capture order %d: order was refunded.", 'simplesalestax' ),
						$this->order->get_id()
					)
				);

				return false;
			}
		}

		// Send AuthorizedWithCapture for all packages
		foreach ( $packages as $key => $package ) {
			$now = date( 'c' );

			try {
				$request = new TaxCloud\Request\AuthorizedWithCapture(
					$this->api_id,
					$this->api_key,
					$package['request']->getCustomerID(),
					$package['cart_id'],
					$this->get_package_order_id( $key ),
					$now,
					$now
				);

				TaxCloud()->AuthorizedWithCapture( $request );
			} catch ( Exception $ex ) {
				$this->handle_error(
					sprintf(
						__( "Failed to capture order %d: %s.", 'simplesalestax' ),
						$this->order->get_id(),
						$ex->getMessage()
					)
				);

				return false;
			}
		}

		$this->update_meta( 'status', 'captured' );
		$this->order->save();

		return true;
	}

	/**
	 * Send Returned request to fully or partially refund an order.
	 *
	 * @since 5.0
	 *
	 * @param array $items Array of items to refund (default: array())
	 *
	 * @return bool True on success, false on failure.
	 *
	 * @throws Exception
	 */
	public function do_refund( $items = [] ) {
		if ( 'captured' !== $this->get_taxcloud_status() ) {
			$this->handle_error(
				sprintf(
					__( "Can't refund order %d: order must be completed first.", 'simplesalestax' ),
					$this->order->get_id()
				)
			);

			return false;
		}

		// For full refunds, refund all fees, line items, and shipping charges
		if ( empty( $items ) ) {
			$items = $this->order->get_items( [ 'fee', 'shipping', 'line_item' ] );
		}

		$this->prepare_refund_items( $items );

		// Process refunds while items remain
		$packages = $this->get_packages();

		while ( ! empty( $items ) ) {
			$package = current( $packages );

			if ( false === $package ) {
				break;
			}

			$cart_items   = $package['request']->getCartItems();
			$refund_items = [];

			foreach ( $cart_items as $cart_item_key => $pitem ) {
				$to_match = $package['map'][ $cart_item_key ];

				if ( 'shipping' == $to_match['type'] ) {
					$to_match['id'] = $this->process_method_id( $package['shipping']->method_id );
				}

				foreach ( $items as $item_key => $item ) {
					if ( $item['id'] != $to_match['id'] ) {
						continue; // No match
					}

					$qty = min( $item['qty'], $pitem->getQty() );

					$refund_items[] = new TaxCloud\CartItem(
						sizeof( $refund_items ),
						$pitem->getItemID(),
						$pitem->getTIC(),
						$item['price'],
						$qty
					);

					$item['qty'] -= $qty;

					if ( $item['qty'] == 0 ) {
						unset( $items[ $item_key ] );
					}
				}
			}

			if ( ! empty( $refund_items ) ) {
				try {
					$request = new TaxCloud\Request\Returned(
						$this->api_id,
						$this->api_key,
						$this->get_package_order_id( key( $packages ), $package ),
						$refund_items,
						date( 'c' )
					);

					TaxCloud()->Returned( $request );
				} catch ( Exception $ex ) {
					$this->handle_error(
						sprintf(
							__( "Failed to refund order %d: %s.", 'simplesalestax' ),
							$this->order->get_id(),
							$ex->getMessage()
						)
					);

					return false;
				}
			}

			next( $packages );
		}

		// If order was fully refunded, set status accordingly
		if ( 0 >= $this->order->get_remaining_refund_amount() ) {
			$this->update_meta( 'status', 'refunded' );
			$this->order->save();
		}

		return true;
	}

	/**
	 * Shipping methods like WooCommerce FedEx Drop Shipping Pro use nonstandard
	 * method IDs. This method converts those nonstandard IDs into the standard
	 * format.
	 *
	 * @since 5.3
	 *
	 * @param  string $method_id
	 *
	 * @return string
	 */
	protected function process_method_id( $method_id ) {
		if ( class_exists( 'IgniteWoo_Shipping_Fedex_Drop_Shipping_Pro' ) ) {
			$method_id = preg_replace( '/FedEx - (.*)/', 'fedex_wsdl:$1', $method_id );
		}
		if ( class_exists( 'ups_drop_shipping_rate' ) ) {
			$method_id = preg_replace( '/ups_drop_shipping_rate_UPS (.*)/', 'ups_drop_shipping_rate:$1', $method_id );
		}

		return current( explode( ':', $method_id ) );
	}

	/**
	 * Prepare items for refund.
	 *
	 * @since 5.0
	 *
	 * @param array $items Refund items.
	 */
	protected function prepare_refund_items( &$items ) {
		$tax_based_on = SST_Settings::get( 'tax_based_on' );

		foreach ( $items as $item_id => $item ) {

			$quantity   = isset( $item['qty'] ) ? $item['qty'] : 1;
			$line_total = isset( $item['line_total'] ) ? $item['line_total'] : $item['cost'];
			$unit_price = round( $line_total / $quantity, wc_get_price_decimals() );

			/* Set quantity and price according to 'Tax Based On' setting */
			if ( 'line-subtotal' == $tax_based_on ) {
				$quantity = 1;
				$price    = $line_total;
			} else {
				$price = $unit_price;
			}

			/* Set item ID */
			switch ( $item['type'] ) {
				case 'line_item':
					$id = $item['variation_id'] ? $item['variation_id'] : $item['product_id'];
					break;
				case 'shipping':  // TODO: handle packages w/ same method
					$id = $this->process_method_id( $item['method_id'] );
					break;
				case 'fee':
					$name = empty( $item['name'] ) ? __( 'Fee', 'simplesalestax' ) : $item['name'];
					$id   = sanitize_title( $name );
			}

			$items[ $item_id ] = [
				'qty'   => $quantity,
				'price' => $price,
				'id'    => $id,
			];
		}
	}

	/**
	 * Update order meta.
	 *
	 * @since 5.0
	 *
	 * @param string $key   Meta key.
	 * @param mixed  $value Meta value.
	 */
	public function update_meta( $key, $value ) {
		$key = self::$prefix . $key;

		if ( ! is_string( $value ) ) {
			$value = serialize( $value ); /* $value must be a string */

			if ( version_compare( WC_VERSION, '3.1', '<' ) ) {
				$value = wp_slash( $value );
			}
		}

		$this->order->update_meta_data( $key, $value );
	}

	/**
	 * Get meta value.
	 *
	 * @since 5.0
	 *
	 * @param string $key
	 * @param bool   $single
	 * @param string $context
	 *
	 * @return mixed empty string if key doesn't exist, otherwise value.
	 */
	public function get_meta( $key = '', $single = true, $context = 'view' ) {
		$value = $this->order->get_meta( self::$prefix . $key, $single, $context );

		if ( is_string( $value ) ) {
			$value = maybe_unserialize( sst_unslash( $value ) ); /* for WC 3.1.0+ */
		}

		if ( empty( $value ) && array_key_exists( $key, self::$defaults ) ) {
			$value = self::$defaults[ $key ];
		}

		return $value;
	}

	/**
	 * Gets a saved package by its package hash.
	 *
	 * @param string $hash
	 *
	 * @return array|bool The saved package with the given hash, or false if no such package exists.
	 */
	protected function get_saved_package( $hash ) {
		$saved_packages = $this->get_meta( 'package_cache' );

		if ( is_array( $saved_packages ) && isset( $saved_packages[ $hash ] ) ) {
			return $saved_packages[ $hash ];
		}

		return false;
	}

	/**
	 * Saves a package.
	 *
	 * @param string $hash
	 * @param array  $package
	 */
	protected function save_package( $hash, $package ) {
		$saved_packages = $this->get_meta( 'package_cache' );

		if ( ! is_array( $saved_packages ) ) {
			$saved_packages = [];
		}

		$saved_packages[ $hash ] = $package;

		$this->update_meta( 'package_cache', $saved_packages );
	}

}
