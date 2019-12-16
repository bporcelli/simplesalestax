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
 * @author  Simple Sales Tax
 * @package SST
 * @since   5.0
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
	public function __construct( $ID, $Default, $Address1, $Address2, $City, $State, $Zip5, $Zip4 = null ) {
		$this->setID( $ID );
		$this->setDefault( $Default );

		parent::__construct( $Address1, $Address2, $City, $State, $Zip5, $Zip4 );
	}

	/**
	 * Set ID.
	 *
	 * @param int $ID
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
	 * @param bool $Default
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
