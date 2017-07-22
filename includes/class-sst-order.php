<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Order.
 *
 * Extends WC_Order to add extra functionality.
 *
 * @author 	Simple Sales Tax
 * @package SST
 * @since 	5.0
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
	protected static $defaults = array(
		'packages'    => array(),
		'exempt_cert' => null,
		'status'      => 'pending'
	);

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
		} else if ( is_a( $order, 'WC_Order' ) ) {
			$this->order = $order;
		}
	}

	/**
	 * Forward method calls to the encapsulated WC_Order instance.
	 *
	 * @since 5.0
	 *
	 * @param  string $name
	 * @param  array $args
	 * @return mixed
	 */
	public function __call( $name, $args = array() ) {
		if ( is_callable( array( $this->order, $name ) ) ) {
			return call_user_func_array( array( $this->order, $name ), $args );
		} else if ( 0 === strpos( $name, 'get_' ) ) {
			/* For backward compatibility with Woo 2.6.x */
			$prop_name = substr( $name, 4 );
			return $this->order->$prop_name;
		}

		return NULL;
	}

	/**
	 * Can we perform a lookup for the given package?
	 *
	 * @since 5.0
	 *
	 * @param  array $package
	 * @return bool
	 */
	protected function ready_for_lookup( $package ) {
		return parent::ready_for_lookup( $package ) && 'pending' == $this->get_taxcloud_status() && ! $this->get_total_refunded();
	}

	/**
	 * Get saved packages for this order.
	 *
	 * @since 5.0
	 *
	 * @return array
	 */
	protected function get_packages() {
		return $this->get_meta( 'packages' );
	}

	/**
	 * Set saved packages for this order.
	 *
	 * @since 5.0
	 *
	 * @param $packages array (default: array())
	 */
	protected function set_packages( $packages = array() ) {
		$this->update_meta( 'packages', $packages );
	}

	/**
	 * Transform an array of cart items to match the format expected during
	 * checkout.
	 *
	 * @since 5.0
	 *
	 * @param  array $cart_items
	 * @return array
	 */
	protected function transform_items( $cart_items ) {
		$new_items = array();

		foreach ( $cart_items as $item_id => $item ) {
			$product_id = $item['variation_id'] ? $item['variation_id'] : $item['product_id'];

			$new_items[ $item_id ] = array(
				'product_id'    => $item['product_id'],
				'variation_id'  => $item['variation_id'],
				'quantity'      => $item['qty'],
				'line_total'    => $item['line_total'],
				'line_subtotal' => $item['line_subtotal'],  
				'data'          => wc_get_product( $product_id ),
			);
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
		
		$packages = array();
		$items    = $this->transform_items( $this->get_items() );

		/* If WC 3.0 or later, create a package exclusively for digital items. */
		if ( version_compare( WC_VERSION, '3.0', '>=' ) ) {
			$digital_items = array();

			foreach ( $items as $key => $item ) {
				if ( isset( $item['data'] ) && ! $item['data']->needs_shipping() ) {
					$digital_items[ $key ] = $item;
					unset( $items[ $key ] );
				}
			}

			if ( ! empty( $digital_items ) ) {
				$packages[]	= sst_create_package( array(
					'contents'    => $digital_items,
					'destination' => $this->get_billing_address(),
					'user'        => array(
						'ID' => $this->get_user_id(),
					),
				) );
			}
		}
		
		/* Create an additional package for each shipping method. */
		$ship_methods = $this->get_shipping_methods();

		if ( $ship_methods && $items ) {
			$items_per_package = ceil( count( $items ) / count( $ship_methods ) );
			$package_items     = array_chunk( $items, $items_per_package, true );
			
			foreach ( $package_items as $contents ) {
				$method = current( $ship_methods );

				/* Assign shipping method to package. */
				$package = sst_create_package( array(
					'contents' => $contents,
					'shipping' => new WC_Shipping_Rate(
						key( $ship_methods ),	// id
						'',						// name
						$method['cost'],		// cost
						array(),				// taxes
						$method['method_id']	// method id
					),
					'user'     => array(
						'ID' => $this->get_user_id(),
					),
				) );

				/* Set destination based on shipping method. */
				if ( SST_Shipping::is_local_pickup( array( $package['shipping']->method_id ) ) ) {
					$pickup_address = apply_filters( 'wootax_pickup_address', SST_Addresses::get_default_address(), $this->order );
			
					$package['destination'] = array(
						'country'   => 'US',
						'address'   => $pickup_address->getAddress1(),
						'address_2' => $pickup_address->getAddress2(),
						'city'      => $pickup_address->getCity(),
						'state'     => $pickup_address->getState(),
						'postcode'  => $pickup_address->getZip5(),
					);
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
	 * Create shipping packages for order.
     *
	 * @since 5.0
	 *
	 * @return array
	 */
	protected function create_packages() {
		$packages = array();

		/* Let devs change the packages before we split them. */
		$raw_packages = apply_filters( 'wootax_order_packages_before_split', $this->get_filtered_packages(), $this->order );

		/* Split packages by origin address. */
		foreach ( $raw_packages as $raw_package ) {
			$packages = array_merge( $packages, $this->split_package( $raw_package ) );
		}

		/* Add fees to first package. */
		if ( apply_filters( 'wootax_add_fees', true ) ) {
			$fees = array();
			
			foreach ( $this->get_fees() as $item_id => $fee ) {
				$name   = empty( $fee['name'] ) ? __( 'Fee', 'simplesalestax' ) : $fee['name'];
				$fee_id = sanitize_title( $name );
				
				$fees[ $item_id ] = (object) array(
					'id'     => $fee_id,
					'amount' => $fee['line_total'],
				);
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
		foreach ( $this->get_items() + $this->get_fees() as $item_id => $item ) {
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
		foreach ( $this->get_shipping_methods() as $method ) {
			if ( ! isset( $method['taxes'] ) )
				continue;

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
	 * @param mixed $id Item ID.
	 * @param float $tax Sales tax for product.
	 */
	protected function set_product_tax( $id, $tax ) {
		$item = NULL;

		if ( version_compare( WC_VERSION, '3.0', '>=' ) ) {
			$item     = $this->get_item( $id );
			$tax_data = $item->get_taxes( 'edit' );
		} else {
			$tax_data = wc_get_order_item_meta( $id, '_line_tax_data' );
		}

		if ( ! is_array( $tax_data ) ) {
			$tax_data = array( 'total' => array(), 'subtotal' => array() );
		}

		$tax_data['total'][ SST_RATE_ID ]    = $tax;
		$tax_data['subtotal'][ SST_RATE_ID ] = $tax;

		if ( version_compare( WC_VERSION, '3.0', '>=' ) ) {
			$item->set_taxes( $tax_data );
			$item->save();

			/* Must re-add item for changes to take effect */
			$this->add_item( $item );
		} else {
			wc_update_order_item_meta( $id, '_line_tax_data', $tax_data );
			wc_update_order_item_meta( $id, '_line_tax', array_sum( $tax_data['total'] ) );
			wc_update_order_item_meta( $id, '_line_subtotal_tax', array_sum( $tax_data['subtotal'] ) );
		}
	}

	/**
	 * Set the tax for a shipping item.
	 *
	 * @since 5.0
	 *
	 * @param mixed $id Item ID.
	 * @param float $tax Sales tax for item.
	 */
	protected function set_shipping_tax( $id, $tax ) {
		if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
			/* Taxes stored under key 'taxes' instead of '_line_tax_data' */
			$taxes = wc_get_order_item_meta( $id, 'taxes' );
			
			if ( ! is_array( $taxes ) )
				$taxes = array();
			
			$taxes[ SST_RATE_ID ] = $tax;

			wc_update_order_item_meta( $id, 'taxes', $taxes );
		} else {
			$this->set_product_tax( $id, $tax );
		}
	}

	/**
	 * Set the tax for a fee.
	 *
	 * @since 5.0
	 *
	 * @param mixed $id Fee ID.
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
		return $this->get_meta( 'exempt_cert' );
	}

	/**
	 * Handle an error by logging it or displaying it to the user.
	 *
	 * @since 5.0
	 * 
     * @param string $message Message describing the error.
     */
	protected function handle_error( $message ) {
		SST_Logger::add( $message );

		if ( defined( 'DOING_AJAX' ) ) {
			throw new Exception( $message );
		} else if ( function_exists( 'sst_add_message' ) ) {
			sst_add_message( $message, 'error' );
		}
	}

	/**
	 * Get TaxCloud status.
	 *
	 * @since 5.0
	 *
	 * @param  string $context (default: 'edit')
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
		return array(
			'country'   => $this->get_billing_country(),
			'address'   => $this->get_billing_address_1(),
			'address_2' => $this->get_billing_address_2(),
			'city'      => $this->get_billing_city(),
			'state'     => $this->get_billing_state(),
			'postcode'  => $this->get_billing_postcode(),
		);
	}

	/**
	 * Get shipping address.
	 *
	 * @since 5.5
	 *
	 * @return array
	 */
	protected function get_shipping_address() {
		return array(
			'country'   => $this->get_shipping_country(),
			'address'   => $this->get_shipping_address_1(),
			'address_2' => $this->get_shipping_address_2(),
			'city'      => $this->get_shipping_city(),
			'state'     => $this->get_shipping_state(),
			'postcode'  => $this->get_shipping_postcode(),
		);
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
				substr( $raw_address['postcode'] , 0, 5 )
			);

			return SST_Addresses::verify_address( $address );
		} catch ( Exception $ex ) {
			return NULL;
		}
	}

	/**
	 * Get order id for given package.
	 *
	 * @since 5.0
	 *
	 * @param  string $package_key
	 * @param  array $package (default: array())
	 * @return string
	 */
	protected function get_package_order_id( $package_key, $package = array() ) {
		if ( isset( $package['order_id'] ) ) { /* Legacy (pre 5.0) order */
			return $package['order_id'];
		}
		return $this->get_id() . '_' . $package_key;
	}

	/**
	 * Send AuthorizedWithCapture request to capture order in TaxCloud.
	 *
	 * @since 5.0
	 *
	 * @return bool true on success, false on failure.
	 */
	public function do_capture() {
		$taxcloud_status = $this->get_taxcloud_status();
		$packages        = $this->get_packages();

		// Handle error cases
		if ( 'captured' == $taxcloud_status ) {
			if ( 'no' == SST_Settings::get( 'capture_immediately' ) ) {
				$this->handle_error( sprintf( __( "Failed to capture order %d: already captured.", 'simplesalestax' ), $this->get_id() ) );
			}
			return false;
		} else if ( 'refunded' == $taxcloud_status ) {
			$this->handle_error( sprintf( __( "Failed to capture order %d: order was refunded.", 'simplesalestax' ), $this->get_id() ) );
			return false;
		}

		// Send AuthorizedWithCapture for all packages
		foreach ( $packages as $key => $package ) {
			$now = date( 'c' );

			$request = new TaxCloud\Request\AuthorizedWithCapture(
				SST_Settings::get( 'tc_id' ),
				SST_Settings::get( 'tc_key' ),
				$package['request']->getCustomerID(),
				$package['cart_id'],
				$this->get_package_order_id( $key ),
				$now,
				$now
			);

			try {
				TaxCloud()->AuthorizedWithCapture( $request );
			} catch ( Exception $ex ) {
				$this->handle_error( sprintf( __( "Failed to capture order %d: %s.", 'simplesalestax' ), $this->get_id(), $ex->getMessage() ) );
				return false;
			}
		}

		$this->update_meta( 'status', 'captured' );
		$this->save();

		return true;
	}

	/**
	 * Send Returned request to fully or partially refund an order.
	 *
	 * @since 5.0
	 *
	 * @param  array $items Array of items to refund (default: array())
	 * @return bool True on success, false on failure.
	 */
	public function do_refund( $items = array() ) {

		if ( 'captured' !== $this->get_taxcloud_status() ) {
			$this->handle_error( sprintf( __( "Can't refund order %d: order must be completed first.", 'simplesalestax' ), $this->get_id() ) );
			return false;
		}

		// For full refunds, refund all fees, line items, and shipping charges
		if ( empty( $items ) ) {
			$items = $this->get_items( array( 'fee', 'shipping', 'line_item' ) );
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
			$refund_items = array();

			foreach ( $cart_items as $cart_item_key => $pitem ) {
				$to_match = $package['map'][ $cart_item_key ];

				if ( 'shipping' == $to_match['type'] ) {
					$to_match['id'] = $package['shipping']->method_id;
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
				$request = new TaxCloud\Request\Returned(
					SST_Settings::get( 'tc_id' ),
					SST_Settings::get( 'tc_key' ),
					$this->get_package_order_id( key( $packages ), $package ),
					$refund_items,
					date( 'c' )
				);

				try {
					TaxCloud()->Returned( $request );
				} catch ( Exception $ex ) {
					$this->handle_error( sprintf( __( "Failed to refund order %d: %s.", 'simplesalestax' ), $this->get_id(), $ex->getMessage() ) );
					return false;
				}
			}

			next( $packages );
		}

		// If order was fully refunded, set status accordingly
		if ( 0 >= $this->get_remaining_refund_amount() ) {
			$this->update_meta( 'status', 'refunded' );
			$this->save();
		}

		return true;
	}

	/**
	 * Shipping methods like WooCommerce FedEx Drop Shipping Pro use nonstandard
	 * method IDs. This method converts those nonstandard IDs into standard IDs
	 * of the form METHOD_ID:INSTANCE_ID.
	 *
	 * @since 5.3
	 *
	 * @param  string $method_id
	 * @return string
	 */
	protected function process_method_id( $method_id ) {
		if ( class_exists( 'IgniteWoo_Shipping_Fedex_Drop_Shipping_Pro' ) ) {
			$method_id = preg_replace( '/FedEx - (.*)/', 'fedex_wsdl:$1', $method_id );
		}
		if ( class_exists( 'ups_drop_shipping_rate' ) ) {
			$method_id = preg_replace( '/ups_drop_shipping_rate_UPS (.*)/', 'ups_drop_shipping_rate:$1', $method_id );
		}
		return $method_id;
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
					$id = current( explode( ':', $this->process_method_id( $item['method_id'] ) ) );
				break;
				case 'fee':
					$name = empty( $item['name'] ) ? __( 'Fee', 'simplesalestax' ) : $item['name'];
					$id   = sanitize_title( $name );
			}

			$items[ $item_id ] = array(
				'qty'   => $quantity,
				'price' => $price,
				'id'    => $id,
			);
		}
	}

	/**
	 * Get the order ID.
	 *
	 * Note: This function was implemented for compatibility with 2.6.x and should
	 * eventually be removed.
	 *
	 * @since 5.0
	 *
	 * @return int
	 */
	public function get_id() {
		if ( version_compare( WC_VERSION, '3.0', '<' ) )
			return $this->order->id;
		return $this->order->get_id();
	}

	/**
	 * Update order meta.
	 *
	 * @since 5.0
	 *
	 * @param string $key Meta key.
	 * @param mixed $value Meta value.
	 */
	public function update_meta( $key, $value ) {
		$key = self::$prefix . $key;

		if ( version_compare( WC_VERSION, '3.0', '>=' ) ) {
			if ( ! is_string( $value ) ) {
				$value = serialize( $value ); /* $value must be a string */

				if ( version_compare( WC_VERSION, '3.1', '<' ) ) {
					$value = wp_slash( $value );
				}
			}
			$this->order->update_meta_data( $key, $value );
		} else {
			update_post_meta( $this->get_id(), $key, $value );
		}
	}

	/**
	 * Get meta value.
	 *
	 * @since 5.0
	 *
	 * @param  string $key
	 * @return mixed empty string if key doesn't exist, otherwise value.
	 */
	public function get_meta( $key = '', $single = true, $context = 'view' ) {
		$value = '';

		if ( version_compare( WC_VERSION, '3.0', '>=' ) ) {
			$value = $this->order->get_meta( self::$prefix . $key, $single, $context );

			if ( is_string( $value ) ) {
				$value = maybe_unserialize( sst_unslash( $value ) ); /* for WC 3.1.0+ */
			}
		} else {
			$value = get_post_meta( $this->get_id(), self::$prefix . $key, $single );
		}

		if ( empty( $value ) && array_key_exists( $key, self::$defaults ) ) {
			$value = self::$defaults[ $key ];
		}

		return $value;
	}
}