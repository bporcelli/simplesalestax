<?php

/**
 * SST Cart Proxy.
 *
 * Provides a simple, backward-compatible interface to the WC Cart object.
 *
 * @author  Brett Porcelli
 * @package SST
 * @since   5.6
 */
class SST_Cart_Proxy {

	// the WC_Cart object being wrapped
	private $cart = null;

	/**
	 * Constructor.
	 *
	 * @since 5.6
	 */
	public function __construct( $cart ) {
		$this->cart = $cart;
	}

	/**
	 * Get cart taxes.
	 *
	 * @since  5.6
	 * @return array of cart taxes.
	 */
	public function get_cart_taxes() {
		if ( sst_woocommerce_gte_32() ) {
			return wc_array_merge_recursive_numeric(
				$this->cart->get_cart_contents_taxes(),
				$this->cart->get_fee_taxes()
			);
		} else {
			return $this->cart->taxes;
		}
	}

	/**
	 * Get shipping taxes.
	 *
	 * @since  5.6
	 * @return array of shipping taxes.
	 */
	public function get_shipping_taxes() {
		if ( sst_woocommerce_gte_32() ) {
			return $this->cart->get_shipping_taxes();
		} else {
			return $this->cart->shipping_taxes;
		}
	}

	/**
	 * Set cart tax amount.
	 *
	 * @since 5.6
	 *
	 * @param string $value Value to set.
	 */
	public function set_cart_tax( $value ) {
		if ( sst_woocommerce_gte_32() ) {
			$this->cart->set_cart_contents_tax( $value );
		} else {
			$this->cart->tax_total = wc_round_tax_total( $value );
		}
	}

	/**
	 * Set shipping tax.
	 *
	 * @since 3.2.0
	 *
	 * @param string $value Value to set.
	 */
	public function set_shipping_tax( $value ) {
		if ( sst_woocommerce_gte_32() ) {
			$this->cart->set_shipping_tax( $value );
		} else {
			$this->cart->shipping_tax_total = wc_round_tax_total( $value );
		}
	}

	/**
	 * Set the tax for a particular cart item.
	 *
	 * @since 5.0
	 *
	 * @param mixed $key cart item key.
	 * @param float $tax sales tax for cart item.
	 */
	public function set_cart_item_tax( $key, $tax ) {
		if ( sst_woocommerce_gte_32() ) {
			$cart_contents = $this->cart->get_cart_contents();
		} else {
			$cart_contents = $this->cart->cart_contents;
		}

		$tax_data = $cart_contents[ $key ]['line_tax_data'];

		$tax_data['subtotal'][ SST_RATE_ID ] = $tax;
		$tax_data['total'][ SST_RATE_ID ]    = $tax;

		$cart_contents[ $key ]['line_tax_data']     = $tax_data;
		$cart_contents[ $key ]['line_subtotal_tax'] = array_sum( $tax_data['subtotal'] );
		$cart_contents[ $key ]['line_tax']          = array_sum( $tax_data['total'] );

		if ( sst_woocommerce_gte_32() ) {
			$this->cart->set_cart_contents( $cart_contents );
		} else {
			$this->cart->cart_contents = $cart_contents;
		}
	}

	/**
	 * Set the tax for a particular fee.
	 *
	 * @since 5.0
	 *
	 * @param mixed $id  fee ID.
	 * @param float $tax sales tax for fee.
	 */
	public function set_fee_item_tax( $id, $tax ) {
		if ( sst_woocommerce_gte_32() ) {
			$fees = $this->cart->fees_api()->get_fees();
		} else {
			$fees = $this->cart->fees;
		}

		$fees[ $id ]->tax_data[ SST_RATE_ID ] = $tax;
		$fees[ $id ]->tax                     = array_sum( $this->cart->fees[ $id ]->tax_data );

		if ( sst_woocommerce_gte_32() ) {
			$this->cart->fees_api()->set_fees( $fees );
		} else {
			$this->cart->fees = $fees;
		}
	}

	/**
	 * Set the tax for a shipping package.
	 *
	 * @since 5.6
	 *
	 * @param mixed $key package key.
	 * @param float $tax sales tax for package.
	 */
	public function set_package_tax( $key, $tax ) {
		$this->cart->sst_shipping_taxes[ $key ] = $tax;
	}

	/**
	 * Set the tax amount for a given tax rate.
	 *
	 * @since 5.6
	 *
	 * @param string $tax_rate_id ID of the tax rate to set taxes for.
	 * @param float  $amount
	 */
	public function set_tax_amount( $tax_rate_id, $amount ) {
		if ( sst_woocommerce_gte_32() ) {
			$taxes                 = $this->cart->get_cart_contents_taxes();
			$taxes[ $tax_rate_id ] = $amount;
			$this->cart->set_cart_contents_taxes( $taxes );
		} else {
			$this->cart->taxes[ $tax_rate_id ] = $amount;
		}
	}

	/**
	 * Set the shipping tax amount for a given tax rate.
	 *
	 * @since 5.6
	 *
	 * @param string $tax_rate_id ID of the tax rate to set shipping taxes for.
	 * @param float  $amount
	 */
	public function set_shipping_tax_amount( $tax_rate_id, $amount ) {
		if ( sst_woocommerce_gte_32() ) {
			$taxes                 = $this->cart->get_shipping_taxes();
			$taxes[ $tax_rate_id ] = $amount;
			$this->cart->set_shipping_taxes( $taxes );
		} else {
			$this->cart->shipping_taxes[ $tax_rate_id ] = $amount;
		}
	}

	/**
	 * Update tax totals based on tax arrays.
	 *
	 * @since 5.6
	 */
	public function update_tax_totals() {
		$this->set_cart_tax( WC_Tax::get_tax_total( $this->get_cart_taxes() ) );
		$this->set_shipping_tax( WC_Tax::get_tax_total( $this->get_shipping_taxes() ) );
	}

	/**
	 * Reset package shipping taxes before running calculations.
	 *
	 * @since 5.6
	 */
	public function reset_shipping_taxes() {
		$this->cart->sst_shipping_taxes = array();
	}

	/**
	 * Forward calls to inaccessible methods to the underlying cart object.
	 *
	 * @since 5.6
	 *
	 * @param string $name name of method being called.
	 * @param array  $args parameters of method.
	 *
	 * @return mixed
	 */
	public function __call( $name, $args ) {
		return call_user_func_array( array( $this->cart, $name ), $args );
	}

	/**
	 * Forward read requests for inaccessible properties to the underlying cart object.
	 *
	 * @since 5.6
	 *
	 * @param string $name name of property being read.
	 *
	 * @return mixed
	 */
	public function __get( $name ) {
		return $this->cart->$name;
	}

	/**
	 * Forward write requests for inaccessible properties to the underlying cart object.
	 *
	 * @since 5.6
	 *
	 * @param string $name  name of property being written to.
	 * @param mixed  $value value being written.
	 */
	public function __set( $name, $value ) {
		$this->cart->$name = $value;
	}

}
