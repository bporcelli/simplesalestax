<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Class representing part of a Simple Sales Tax order. A partial order is created
 * for every origin address-destination address combination in an order.
 *
 * @package Simple Sales Tax
 * @author Brett Porcelli
 * @since 4.7
 */
abstract class WC_WooTax_Partial_Order {
	private $order_id;  // String
	private $cart_id; // String
	private $cart_items; // Array of CartItems
	private $origin; // Address
	private $destination;

	// TODO: implement; make this an abstract class (methods like update_item_tax will need to be abstract)
	// Make sure we have an update_tax_total(cartItem) method here
}