<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

use TaxCloud\Address;

/**
 * Origin Address.
 *
 * Represents an origin address.
 *
 * @author 	Simple Sales Tax
 * @package SST
 * @since 	5.0
 */
class SST_Origin_Address extends Address {

	/**
	 * @var int Address ID.
	 */
	protected $ID;

	/**
	 * @var bool Is this a default address?
	 */
	protected $Default;

	/**
	 * Constructor.
	 *
	 * @since 5.0
	 */
	public function __construct( $ID, $Default, $Address1, $Address2, $City, $State, $Zip5, $Zip4 = NULL ) {
		$this->setID( $ID );
		$this->setDefault( $Default );

		parent::__construct( $Address1, $Address2, $City, $State, $Zip5, $Zip4 );
	}

	/**
	 * Set ID.
	 *
	 * @since 5.0
	 *
	 * @param int $ID
	 */
	public function setID( $ID ) {
		$this->ID = $ID;
	}

	/**
	 * Get ID.
	 *
	 * @since 5.0
	 *
	 * @return int
	 */
	public function getID() {
		return $this->ID;
	}

	/**
	 * Set Default.
	 *
	 * @since 5.0
	 *
	 * @param bool $Default
	 */
	public function setDefault( $Default ) {
		$this->Default = $Default;
	}

	/**
	 * Get Default.
	 *
	 * @since 5.0
	 *
	 * @return bool
	 */
	public function getDefault() {
		return $this->Default;
	}

}
