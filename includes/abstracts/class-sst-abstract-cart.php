<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Abstract Cart.
 *
 * Provides a consistent interface for performing tax lookups for a cart.
 * Extended by both SST_Checkout and SST_Order.
 *
 * @author 	Simple Sales Tax
 * @package SST
 * @since 	5.0
 */
abstract class SST_Abstract_Cart {

	/**
	 * Perform a tax lookup and update the sales tax for all items.
	 *
	 * @since 5.0
	 *
	 * @return bool True on success, false on error.
	 */
	public function calculate_taxes() {
		/* Reset */
		$this->reset_taxes();

		/* Perform tax lookup(s) */
		foreach ( $this->do_lookup() as $package ) {
			$response = $package['response'];

			if ( ! is_wp_error( $response ) ) {
				$cart_items = current( $package['response'] );

				foreach ( $cart_items as $index => $tax_total ) {
					$info = $package['map'][ $index ];
					
					switch ( $info['type'] ) {
						case 'shipping':
							$this->set_shipping_tax( $info['cart_id'], $tax_total );
						break;
						case 'line_item':
							$this->set_product_tax( $info['cart_id'], $tax_total );
						break;
						case 'fee':
							$this->set_fee_tax( $info['cart_id'], $tax_total );
					}
				}
			} else {
				$this->handle_error( sprintf( __( "Failed to calculate sales tax: %s", 'simplesalestax' ), $response->get_error_message() ) );
				return;
			}
		}

		/* Update tax totals */
		$this->update_taxes();
	}

	/**
	 * Perform a tax lookup for each cart shipping package.
	 *
	 * @since 5.0
	 *
	 * @return array Array of packages.
	 */
	protected function do_lookup() {
		$packages = array();
		
		/* Get saved packages */
		$saved_pkgs = $this->get_packages();

		/* Perform Lookup for each package */
		foreach ( $this->create_packages() as $key => $package ) {
			$hash = $this->get_package_hash( $package );

			if ( array_key_exists( $hash, $saved_pkgs ) ) {
				$packages[ $key ] = $saved_pkgs[ $hash ]; /* Use cached result */
			} else if ( $this->ready_for_lookup( $package ) ) {
				try {
					$package['request']  = $this->get_lookup_for_package( $package );
					$package['response'] = TaxCloud()->Lookup( $package['request'] );
					$package['cart_id']  = key( $package['response'] );
				} catch ( Exception $ex ) {
					$package['response'] = new WP_Error( 'lookup_error', $ex->getMessage() );
				}

				$packages[ $key ] = $package;
			}
		}

		/* Updated saved packages */
		$this->set_packages( $packages );

		return $packages;
	}

	/**
	 * Generate a Lookup request for a given package.
	 *
	 * @since 5.0
	 *
	 * @param  $package array
	 * @return TaxCloud\Request\Lookup
	 */
	protected function get_lookup_for_package( &$package ) {

		$cart_items = array();
		$based_on   = SST_Settings::get( 'tax_based_on' );

		/* Add products */
		foreach ( $package['contents'] as $cart_id => $item ) {
			
			$line_total       = $item['line_total'];
			$discounted_price = round( $line_total / $item['quantity'], wc_get_price_decimals() );

			/* Set quantity and price according to 'Tax Based On' setting. */
			if ( 'line-subtotal' == $based_on ) {
				$quantity = 1;
				$price    = $line_total;
			} else {
				$quantity = $item['quantity'];
				$price    = $discounted_price;
			}

			/* Give devs a chance to change the taxable product price. */
			$price = apply_filters( 'wootax_product_price', $price, $item['data'] );

			$cart_items[] = new TaxCloud\CartItem(
				sizeof( $cart_items ),
				$item['variation_id'] ? $item['variation_id'] : $item['product_id'],
				SST_Product::get_tic( $item['product_id'], $item['variation_id'] ),
				$price,
				$quantity
			);
			$package['map'][] = array(
				'type'    => 'line_item',
				'id'      => $item['data']->get_id(),
				'cart_id' => isset( $item['shipping_item_key'] ) ? $item['shipping_item_key'] : $cart_id,
			);
		}

		/* Add fees */
		foreach ( $package['fees'] as $cart_id => $fee ) {
			$cart_items[] = new TaxCloud\CartItem(
				sizeof( $cart_items ),
				$fee->id,
				apply_filters( 'wootax_fee_tic', SST_DEFAULT_FEE_TIC ),
				apply_filters( 'wootax_fee_price', $fee->amount, $fee ),
				1
			);
			$package['map'][] = array(
				'type'    => 'fee',
				'id'      => $fee->id,
				'cart_id' => $cart_id,
			);
		}

		/* Add shipping */
		$shipping_rate  = $package['shipping'];
		$local_delivery = false;

		if ( ! is_null( $shipping_rate ) ) {
			$local_delivery = SST_Shipping::is_local_delivery( $shipping_rate->method_id );

			$cart_items[] = new TaxCloud\CartItem(
				sizeof( $cart_items ),
				SST_SHIPPING_ITEM,
				apply_filters( 'wootax_shipping_tic', SST_DEFAULT_SHIPPING_TIC ),
				apply_filters( 'wootax_shipping_price', $shipping_rate->cost, $shipping_rate ),
				1
			);
			$package['map'][] = array(
				'type'    => 'shipping',
				'id'      => SST_SHIPPING_ITEM,
				'cart_id' => $shipping_rate->id,
			);
		}

		/* Build Lookup */
		$request = new TaxCloud\Request\Lookup(
			SST_Settings::get( 'tc_id' ),
			SST_Settings::get( 'tc_key' ),
			$package['user']['ID'],
			NULL, 								/* CartID */
			$cart_items,
			$package['origin'],
			$package['destination'],
			$local_delivery,
			$package['certificate']
		);

		return $request;
	}

	/**
	 * Split package into one or more subpackages, one for each product
	 * origin address.
	 *
	 * @since 5.0
	 *
	 * @param  array Package.
	 * @return array Subpackages (empty on error).
	 */
	protected function split_package( $package ) {
		$packages = array();

		/* Convert destination address to Address object */
		try {
			$destination = new TaxCloud\Address(
				isset( $package['destination']['address_1'] ) ? $package['destination']['address_1'] : $package['destination']['address'],
				$package['destination']['address_2'],
				$package['destination']['city'],
				$package['destination']['state'],
				substr( $package['destination']['postcode'], 0, 5)
			);

			$package['destination'] = SST_Addresses::verify_address( $destination );
		} catch ( Exception $ex ) {
			return array();
		}

		/* Split package into subpackages */
		foreach ( $package['contents'] as $cart_key => $item ) {
			$origin = $this->get_origin_for_product( $item, $package['destination'] );

			if ( ! $origin ) {
				$this->handle_error( sprintf( __( "Failed to calculate sales tax: no origin address for product %d.", 'simplesalestax' ), $item['product_id'] ) );
				return array();
			}

			$origin_id = $origin->getID();

			/* Create subpackage for origin if need be */
			if ( ! array_key_exists( $origin_id, $packages ) ) {
				$packages[ $origin_id ] = sst_create_package( array(
					'origin'      => SST_Addresses::to_address( $origin ),
					'destination' => $package['destination'],
					'certificate' => $this->get_certificate(),
					'user'        => isset( $package['user'] ) ? $package['user'] : array( 'ID' => get_current_user_id() )
				) );

				/* Associate shipping with first subpackage */
				if ( isset( $package['shipping'] ) ) {
					$packages[ $origin_id ]['shipping'] = $package['shipping'];
					unset( $package['shipping'] );
				}
			}

			/* Update package contents */
			$packages[ $origin_id ]['contents'][ $cart_key ] = $item;
		}

		return $packages;
	}

	/**
	 * Get the hash of a shipping package.
	 *
	 * @since 5.0
	 *
	 * @param  array $package
	 * @return string
	 */
	private function get_package_hash( $package ) {
		$package_to_hash = $package;

		// Remove data objects so hashes are consistent
		foreach ( $package_to_hash['contents'] as $item_id => $item ) {
			unset( $package_to_hash['contents'][ $item_id ]['data'] );
		}

		return 'sst_pack_' . md5( json_encode( $package_to_hash ) . WC_Cache_Helper::get_transient_version( 'shipping' ) );
	}

	/**
	 * Get the origin address to use for a given product.
	 *
	 * By default, we use the following procedure to determine the address
	 * to use:
	 *
	 * 	1) If there is only one shipment origin for the product, use it.
	 *  2) If there are multiple shipment origins, use one in the customer's state.
	 *  3) If there are no origins in the customers state, use the first  origin.
	 *
	 * @since 5.0
	 *
	 * @param  array $item
	 * @param  TaxCloud\Address $destination
	 * @return SST_Origin_Address
	 */
	protected function get_origin_for_product( $item, $destination ) {
		$origins = SST_Product::get_origin_addresses( $item['product_id'] );
		$origin  = null;

		if ( ! empty( $origins ) ) {
			$origin = current( $origins ); 

			foreach ( $origins as $candidate ) {
				if ( $candidate->getState() == $destination->getState() ) {
					$origin = $candidate;
					break;
				}
			}
		}

		return apply_filters( 'wootax_origin_address', $origin, $item, $destination );
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
		return isset( $package['destination'] ) && SST_Addresses::is_valid( $package['destination'] );
	}

	/**
	 * Get the base packages for the cart, filtering out all packages destined
	 * for places abroad.
	 *
	 * @since 5.5
	 *
	 * @return array
	 */
	protected function get_filtered_packages() {
		$packages = $this->get_base_packages();

		foreach ( $packages as $key => $package ) {
			if ( ! isset( $package['destination'], $package['destination']['country'] ) ) {
				unset( $packages[ $key ] );
			} else if ( 'US' !== $package['destination']['country'] ) {
				unset( $packages[ $key ] );
			}
		}

		return $packages;
	}

	/**
	 * Get saved packages for this cart.
	 *
	 * @since 5.0
	 *
	 * @return array
	 */
	abstract protected function get_packages();

	/**
	 * Set saved packages for this cart.
	 *
	 * @since 5.0
	 *
	 * @param $packages array (default: array())
	 */
	abstract protected function set_packages( $packages = array() );

	/**
	 * Get the base packages for the cart. The base packages are split by origin
	 * and otherwise processed to generate the packages sent to TaxCloud.
	 *
	 * The packages returned by this method must satisfy the following criteria.
	 *
	 * Completeness: The keys 'contents', 'user', 'shipping', and 'destination'
	 * must be defined.
	 * 
	 * Inclusiveness: Every item in the cart must be included in exactly one
	 * package.
	 *
	 * @since 5.5
	 *
	 * @return array
	 */
	abstract protected function get_base_packages();

	/**
	 * Create shipping packages for this cart.
	 *
	 * Every package should have the following structure:
	 *
	 * 	array(
	 *		'contents'      => array(
	 *			...
	 *			array(
	 * 				'variation_id' => 123,
	 * 				'product_id'   => 456,
	 * 				'quantity'     => 1,
	 *              'data'         => WC_Product,
	 *			)
	 *			...  	
	 *		),
	 *		'fees'          => array(
	 *			...
	 *			object(
	 * 				'id'     => 123,
	 * 				'amount' => 2.50,
	 *			)
	 *			...
	 * 		),
	 *		'shipping'      => array(
	 *			'method_id' => 'local_delivery',
	 * 			'cost'      => 9.99,
	 *		),
	 *		'map'           => array(
	 *			...
	 *			array(
	 * 				'type'    => 'cart'|'shipping'|'fee',
	 * 				'id'      => 456,
	 * 				'cart_id' => 'abc',
	 *			)
	 *			...
	 * 		),
	 *      'user'          => array(
	 * 			'ID' => 1,
	 * 		),
	 *		'request'       => null,
	 *		'response'      => null,
	 *		'origin'        => TaxCloud\Address,
	 *		'destination'   => TaxCloud\Address,
	 *      'certificate'   => TaxCloud\ExemptCertificate
	 * 	)
	 *
	 * @since 5.0
	 *
	 * @return array
	 */
	abstract protected function create_packages();

	/**
	 * Reset sales tax totals.
	 *
	 * @since 5.0
	 */
	abstract protected function reset_taxes();

	/**
	 * Update sales tax totals.
	 *
	 * @since 5.0
	 */
	abstract protected function update_taxes();

	/**
	 * Set the tax for a product.
	 *
	 * @since 5.0
	 *
	 * @param mixed $id Product ID.
	 * @param float $tax Sales tax for product.
	 */
	abstract protected function set_product_tax( $id, $tax );

	/**
	 * Set the tax for a shipping item.
	 *
	 * @since 5.0
	 *
	 * @param mixed $id Item ID.
	 * @param float $tax Sales tax for item.
	 */
	abstract protected function set_shipping_tax( $id, $tax );

	/**
	 * Set the tax for a fee.
	 *
	 * @since 5.0
	 *
	 * @param mixed $id Fee ID.
	 * @param float $tax Sales tax for fee.
	 */
	abstract protected function set_fee_tax( $id, $tax );
	
	/**
	 * Get the exemption certificate for the customer.
	 *
	 * @since 5.0
	 *
	 * @return TaxCloud\ExemptionCertificateBase
	 */
	abstract public function get_certificate();

	/**
	 * Handle an error by logging it or displaying it to the user.
	 *
	 * @since 5.0
	 * 
     * @param string $message Message describing the error.
     */
	abstract protected function handle_error( $message );
}