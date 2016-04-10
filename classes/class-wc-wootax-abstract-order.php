<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Abstract class representing a WooTax/TaxCloud order
 * 
 * @package Simple Sales Tax
 * @author Brett Porcelli
 * @since 4.7
 */
abstract class WC_WooTax_Abstract_Order {
	/**
	 * @var \TaxCloud\Client TaxCloud Client object
	 */
	private $client;

	/**
	 * @var string TaxCloud ID for API requests
	 */
	private $tc_id;

	/**
	 * @var string TaxCloud Key for API requests
	 */
	private $tc_key;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->tc_id = get_taxcloud_id();
		$this->tc_key = get_taxcloud_key();
		$this->client = new \TaxCloud\Client();
	}

	/**
	 * Calculate the tax due on this order by executing a Lookup request.
	 */
	public function calculate_tax() {
		// TODO: implementation
	}

	/**
	 * Refund one or more items with a Refunded request.
	 * @param mixed cart_items array of CartItems to refund (null if all items should be refunded)
	 */
	public function refund( $cart_items = null ) {
		// TODO: implementation
	}

	/**
	 * Send a Captured request to capture the order in TaxCloud.
	 */
	public function capture() {
		// TODO: implementation
	}

	/** 
	 * Determines if an order is ready for a Lookup request
	 * For an order to be "ready," two criteria must be met:
	 * - At least one origin address is added to the site
	 * - The customer's full address is available (and in the United States)
	 *
	 * @since 4.2
	 * @return bool
	 */
	private function ready_for_lookup() {
		// TODO: order should be ready if all parts are ready
		if ( !is_array( $this->addresses ) || count( $this->addresses ) == 0 ) {
			return false;
		} else if ( !wootax_is_valid_address( $this->destination_address, true ) ) {
			return false;
		}
		
		return true;
	}

	/**
	 * Determines whether the order is ready for a refund.
	 */
	private function ready_for_refund() {
		// TODO: order should be ready if it has been captured and refunded amount < order amount
		return false;
	}

	/**
	 * Determines whether the order is ready to be captured.
	 */
	private function ready_for_capture() {
		// TODO: order should be ready if all of its parts are ready (must have customer id, cart id, and order id)
		return false;
	}

	// This method should return the shipping method for this order (we assume one per order)
	public abstract function get_shipping_method() {}

	// This method should return the customer ID for the order
	public abstract function get_customer_id() {}

	// This method should return the exemption certificate for the order, if an exemption was applied
	public abstract function get_exemption_certificate() {}

	// This method should return the order's status (Pending Capture, Captured, Refunded, etc.)
	public abstract function get_status() {}

	// This method should return true if the order needs a Lookup, else false
	public abstract function needs_lookup() {}

	// This method should return an array of WC_WooTax_Partial_Orders that make up this order
	public abstract function get_parts() {}

	// This method should allow the tax total for the order to be updated
	public abstract function update_tax_total( $tax_total ) {}

	// This method should allow the shipping tax total for the order to be updated
	public abstract function update_shipping_tax_total( $tax_total ) {}
}