<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use TaxCloud\Address;

/**
 * Origin Address.
 *
 * Represents an origin address.
 *
 * @author  Simple Sales Tax
 * @package SST
 * @since   5.0
 */
class SST_Origin_Address extends Address {

	/**
	 * Address ID.
	 *
	 * @var int
	 */
	protected $ID;

	/**
	 * Is this a default address?
	 *
	 * @var bool
	 */
	protected $Default;

	/**
	 * Constructor.
	 *
	 * @param int    $ID       Address ID.
	 * @param bool   $Default  Whether this is a default address.
	 * @param string $Address1 Street address 1.
	 * @param string $Address2 Street address 2.
	 * @param string $City     City.
	 * @param string $State    State.
	 * @param string $Zip5     5 digit component of ZIP code.
	 * @param string $Zip4     Optional 4 digit component of ZIP code.
	 *
	 * @since 5.0
	 */
	public function __construct( $ID, $Default, $Address1, $Address2, $City, $State, $Zip5, $Zip4 = null ) {
		$this->setID( $ID );
		$this->setDefault( $Default );

		parent::__construct( $Address1, $Address2, $City, $State, $Zip5, $Zip4 );
	}

	/**
	 * Set ID.
	 *
	 * @param int $ID Address ID.
	 *
	 * @since 5.0
	 */
	public function setID( $ID ) {
		$this->ID = $ID;
	}

	/**
	 * Get ID.
	 *
	 * @return int
	 * @since 5.0
	 */
	public function getID() {
		return $this->ID;
	}

	/**
	 * Set Default.
	 *
	 * @param bool $Default Whether the address is a default origin address.
	 *
	 * @since 5.0
	 */
	public function setDefault( $Default ) {
		$this->Default = $Default;
	}

	/**
	 * Get Default.
	 *
	 * @return bool
	 * @since 5.0
	 */
	public function getDefault() {
		return $this->Default;
	}

}
