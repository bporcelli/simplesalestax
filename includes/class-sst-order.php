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
		'tax_item_id' => 0,
		'packages'    => array(),
		'exempt_cert' => '',
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
		} else if ( is_object( $order ) ) {
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
		return parent::ready_for_lookup( $package ) && 'pending' == $this->get_taxcloud_status() && 0 === $this->get_total_refunded();
	}

	/**
	 * Get saved packages for this order.
	 *
	 * @since 5.0
	 *
	 * @return array
	 */
	protected function get_packages() {
		return json_decode( $this->get_meta( 'packages' ), true );
	}

	/**
	 * Set saved packages for this order.
	 *
	 * @since 5.0
	 *
	 * @param $packages array (default: array())
	 */
	protected function set_packages( $packages = array() ) {
		$this->update_meta( 'packages', json_encode( $packages ) );
	}

	/**
	 * Create shipping packages for order.
	 *
	 * TODO: If we have existing packages, update them instead of
     * creating new ones.
     *
	 * @since 5.0
	 *
	 * @return array
	 */
	protected function create_packages() {
		$destination = SST_Addresses::get_destination_address( $this->order );

		if ( is_null( $destination ) ) {
			return array();
		}

		/* Create one package with all order items */
		$package = array(
			'contents'    => $this->get_items(),
			'destination' => array(
				'address'   => $destination->getAddress1(),
				'address_2' => $destination->getAddress2(),
				'city'      => $destination->getCity(),
				'state'     => $destination->getState(),
				'postcode'  => $destination->getZip5(),
			),
			'user'        => array(
				'ID' => $this->get_customer_id(),
			),
		);

		/* Split package by origin address */
		$packages = $this->split_package( $package );

		if ( empty( $packages ) ) {
			return array();
		}

		/* Add fees to first subpackage */
		$first = key( $packages );

		if ( apply_filters( 'wootax_add_fees', true ) ) {
			foreach ( $this->get_fees() as $item_id => $fee ) {
				$fee_id = sanitize_title( $fee['name'] );

				$packages[ $first ]['fees'][] = (object) array(
					'id'     => $fee_id,
					'amount' => $fee['total']
				);

				$packages[ $first ]['map'][] = array(
					'type'    => 'fee',
					'id'      => $fee_id,
					'cart_id' => $item_id,
				);
			}
		}

		/* If number of shipping methods equals number of packages, add one
		 * method to each package. Otherwise, add all shipping to first
		 * package. */
		$shipping_cost = $this->get_total_shipping();
		
		if ( $shipping_cost > 0 ) {
			$shipping_methods = $this->get_shipping_methods();

			if ( count( $shipping_methods ) == count( $packages ) ) {
				foreach ( $shipping_methods as $method ) {
					$packages[ key( $packages ) ]['shipping'] = array(
						'cost'      => $method['total'],
						'method_id' => $method['method_id']
					);
					next( $packages );
				}
			} else {
				/* Use first method as only method */
				$chosen_method = current( $shipping_methods );

				$packages[ $first ]['shipping'] = array(
					'cost'      => $shipping_cost,
					'method_id' => $chosen_method['method_id'],
				);
			}
		}

		/* Re-index packages by package hash */
		$packages = array();

		foreach ( $packages as $key => $package ) {
			$packages[ $this->get_package_hash( $package ) ] = $package;
			unset( $packages[ $key ] );
		}

		/* Give developers a final opportunity to change the packages */
		$packages = apply_filters( 'wootax_order_packages', $packages, $this->order );

		return $packages;
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
		$this->set_tax_totals( 0, 0 );
	}

	/**
	 * Set the cart and shipping tax totals.
	 *
	 * @since 5.0
	 *
	 * @param float $cart_tax (default: 0.0)
	 * @param float $shipping_tax (default: 0.0)
	 */
	protected function set_tax_totals( $cart_tax = 0.0, $shipping_tax = 0.0 ) {
		$tax_item_id = $this->find_tax_item();

		if ( $tax_item_id ) {
			wc_update_order_item_meta( $tax_item_id, 'tax_amount', $cart_tax );
			wc_update_order_item_meta( $tax_item_id, 'shipping_tax_amount', $shipping_tax );
			wc_update_order_item_meta( $tax_item_id, 'cart_tax', $cart_tax );
			wc_update_order_item_meta( $tax_item_id, 'shipping_tax', $shipping_tax );
		} else {
			$this->handle_error( __( "Failed to calculate sales tax: couldn't update tax totals.", 'simplesalestax' ) );
		}
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
			$tax_data = array( 'total' => 0, 'subtotal' => 0);
		}

		$tax_data['total'][ SST_RATE_ID ]    = $tax;
		$tax_data['subtotal'][ SST_RATE_ID ] = $tax;

		if ( version_compare( WC_VERSION, '3.0', '>=' ) ) {
			$item->set_taxes( $tax_data );
			$item->save();
		} else {
			wc_update_order_item_meta( $id, '_line_tax_data', $tax_data );
			wc_update_order_item_meta( $id, '_line_tax', array_sum( $tax_data['total'] ) );
			wc_update_order_item_meta( $id, '_line_subtotal_tax', array_sum( $tax_data['subtotal'] ) );
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
		/* On the backend, fees and products are both just 'items' */
		$this->set_product_tax( $id, $tax );
	}

	/**
	 * Get the exemption certificate for the customer.
	 *
	 * @since 5.0
	 *
	 * @return TaxCloud\ExemptionCertificateBase
	 */
	protected function get_certificate() {
		$exempt_cert = $this->get_meta( 'exempt_cert' );
		
		if ( ! empty( $exempt_cert ) ) {
			$certificate = json_decode( $exempt_cert, true );
			return new TaxCloud\ExemptionCertificateBase( $certificate['CertificateID'] );
		}

		return null;
	}

	/**
	 * Handle an error by logging it or displaying it to the user.
	 *
	 * @since 5.0
	 * 
     * @param string $message Message describing the error.
     */
	protected function handle_error( $message ) {
		if ( defined( 'DOING_AJAX' ) ) {
			wp_send_json_error( $message );
		} else if ( defined( 'DOING_CRON' ) ) {
			// TODO: LOG ERROR
		} else {
			SST_Admin_Notices::add( 'tax_error', $message, false, 'error' );
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
	 * Get order id for given package.
	 *
	 * @since 5.0
	 *
	 * @param  string $package_key
	 * @return string
	 */
	protected function get_package_order_id( $package_key ) {
		return $this->get_id() . str_replace( 'wc_ship_', '_', $package_key );
	}

	/**
	 * Send AuthorizedWithCapture request to capture order in TaxCloud.
	 *
	 * @since 5.0
	 *
	 * @return bool true on success, false on failure.
	 */
	public function do_capture() {
		$packages = $this->get_packages();

		// Can't capture if already refunded/captured
		if ( 'pending' !== $this->get_taxcloud_status() || empty( $packages ) ) {
			$this->handle_error( sprintf( __( "Failed to capture order %d: empty or already captured or refunded.", 'simplesalestax' ), $this_>get_id() ) );
			return false;
		}

		// Send AuthorizedWithCapture for all packages
		foreach ( $packages as $hash => $package ) {
			$now = date( 'c' );

			$request = new TaxCloud\Request\AuthorizedWithCapture(
				SST_Settings::get( 'tc_id' ),
				SST_Settings::get( 'tc_key' ),
				$this->get_customer_id(),
				$package['cart_id'],
				$this->get_package_order_id( $hash ),
				$now,
				$now
			);

			try {
				TaxCloud()->AuthorizedWithCapture( $request );
			} catch ( Exception $ex ) {
				$this->handle_error( sprintf( __( "Failed to capture order %d: %s.", 'simplesalestax' ), $this_>get_id(), $ex->getMessage() ) );
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
		// Can't refund if not captured yet
		if ( 'captured' !== $this->get_taxcloud_status() ) {
			$this->handle_error( sprintf( __( "Can't refund order %d: order must be completed first.", 'simplesalestax' ), $this->get_id() ) );
			return false;
		}

		// If full refund, add all items to array
		$full_refund = empty( $items );

		if ( $full_refund ) {
			$items = $this->get_items( array( 'fee', 'shipping', 'line_item' ) );
		}

		if ( ! empty( $items ) ) {
			foreach ( $this->get_packages() as $hash => $package ) {
				$refund_items = array();

				// Find matching items in package
				foreach ( $package['map'] as $key => $item ) {
					foreach ( $items as $akey => $aitem ) {
						$match = false;

					 	switch ( $aitem['type'] ) {
					 		case 'product':
					 			$id    = $aitem['variation_id'] ? $aitem['variation_id'] : $aitem['product_id'];
					 		 	$match = $id == $item['id'];
					 		break;
					 		case 'shipping': // TODO: improve matching logic
					 			$match = 'shipping' == $item['type'];
					 		break;
					 		case 'fee':
					 			$fee_id = sanitize_title( $aitem['name'] );
					 			$match  = $fee_id == $item['id'];
					 		break;
					 	}

					 	if ( $match ) {
					 		$refund_items[] = $package['request']['cartItems'][ $key ];
		 		 			unset( $items[ $akey ] );
		 		 			break;
					 	}
					}
				}

				if ( empty( $refund_items ) )
					continue;

				// Found items to refund -- send Returned request
				$cart_items = array();

				foreach ( $refund_items as $item ) {
					$cart_items[] = new TaxCloud\CartItem( $item['Index'], $item['ItemID'], $item['TIC'], $item['Price'], $item['Qty'] );
				}

				$request = new TaxCloud\Request\Returned(
					SST_Settings::get( 'tc_id' ),
					SST_Settings::get( 'tc_key' ),
					$this->get_package_order_id( $hash ),
					$cart_items,
					date( 'c' )
				);

				try {
					TaxCloud()->Returned( $request );
				} catch ( Exception $ex ) {
					$this->handle_error( sprintf( __( "Failed to refund order %d: %s.", 'simplesalestax' ), $this->get_id(), $ex->getMessage() ) );
					return false;
				}
			}
		}

		if ( $full_refund ) {
			$this->reset_taxes();
			$this->update_meta( 'status', 'refunded' );
			$this->save();
		}

		return true;
	}

	/**
	 * Return the ID of our tax item.
	 *
	 * @since 4.6
	 *
	 * @return int|null
	 */
	protected function find_tax_item() {
		global $wpdb;

		$tax_item_id = $this->get_meta( 'tax_item_id' );

		if ( $tax_item_id )
			return $tax_item_id;
		else {
			foreach ( $this->get_taxes() as $item_id => $tax ) {
				if ( $tax[ 'rate_id' ] == SST_RATE_ID ) {
					$tax_item_id = $item_id;
					break;
				}
			}
		}

		/* No tax item found? Add a new one. */
		if ( ! $tax_item_id ) {
			$wpdb->insert( "{$wpdb->prefix}woocommerce_order_items", array(
				'order_item_type' => 'tax', 
				'order_item_name' => apply_filters( 'wootax_rate_code', 'SALES-TAX' ), 
				'order_id'        => $this->get_id(),
			) );

			$tax_item_id = $wpdb->insert_id;

			if ( $tax_item_id ) {
				wc_update_order_item_meta( $tax_item_id, 'rate_id', SST_RATE_ID );
				wc_update_order_item_meta( $tax_item_id, 'label', apply_filters( 'wootax_rate_label', 'Sales Tax' ) );
				wc_update_order_item_meta( $tax_item_id, 'name', apply_filters( 'wootax_rate_code', 'SALES-TAX' ) );
				wc_update_order_item_meta( $tax_item_id, 'compound', true );
			}

			$this->update_meta( 'tax_item_id', $tax_item_id );
			$this->save();
		}

		return $tax_item_id;
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
		} else {
			$value = get_post_meta( $this->get_id(), self::$prefix . $key, $single );
		}

		if ( empty( $value ) && array_key_exists( $key, self::$defaults ) ) {
			$value = self::$defaults[ $key ];
		}

		return $value;
	}

	/**
	 * Reset meta data.
	 *
	 * @since 5.0
	 */
	public function reset_meta_data() {
		foreach ( self::$defaults as $key => $value ) {
			$this->update_meta( $key, $value );
		}
	}
}