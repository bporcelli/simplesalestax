<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * TIC.
 *
 * Represents a Taxability Information Code (TIC).
 *
 * @author 	Simple Sales Tax
 * @package SST
 * @since 	5.0
 */
class SST_TIC implements JsonSerializable {

	/**
	 * @var int TIC ID.
	 * @since 5.0
	 */
	protected $id;

	/**
	 * @var int Parent ID.
	 * @since 5.0
	 */
	protected $parent;

	/**
	 * @var bool Is this TIC an SSUTA TIC?
	 * @since 5.0
	 */
	protected $ssuta;

	/**
	 * @var string TIC Title.
	 * @since 5.0
	 */
	protected $title;

	/**
	 * @var string TIC Label.
	 * @since 5.0
	 */
	protected $label;

	/**
	 * Constructor.
	 *
	 * @since 5.0
	 *
	 * @param mixed $tic TIC ID or object from database.
	 */
	public function __construct( $tic ) {
		if ( is_object( $tic ) ) {
			$this->read_object( $tic );
		} else {
			$this->read( $tic );
		}
	}

	/**
	 * Read TIC from TIC object.
	 *
	 * @since 5.0
	 *
	 * @param array $tic
	 */
	protected function read_object( $tic ) {
		$this->id     = absint( $tic->id );
		$this->parent = absint( $tic->parent );
		$this->ssuta  = '1' == $tic->ssuta;
		$this->title  = $tic->title;
		$this->label  = $tic->label;
	}

	/**
	 * Read TIC from database.
	 *
	 * @since 5.0
	 *
	 * @param int $id ID of TIC to read.
	 */
	protected function read( $id ) {
		global $wpdb;

		if ( ( $tic = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}sst_tics WHERE id = %d", $id ) ) ) ) {
			$this->read_object( $tic );
		}
	}

	/**
	 * Get ID.
	 *
	 * @since 5.0
	 *
	 * @return int
	 */
	public function get_id() {
		return $this->id;
	}

	/**
	 * Get parent ID.
	 *
	 * @since 5.0
	 *
	 * @return int
	 */
	public function get_parent() {
		return $this->parent;
	}

	/**
	 * Get SSUTA.
	 *
	 * @since 5.0
	 *
	 * @return bool
	 */
	public function get_ssuta() {
		return $this->ssuta;
	}

	/**
	 * Get title.
	 *
	 * @since 5.0
	 *
	 * @return string
	 */
	public function get_title() {
		return $this->title;
	}

	/**
	 * Get label.
	 *
	 * @since 5.0
	 *
	 * @return string
	 */
	public function get_label() {
		return $this->label;
	}

	/**
	 * Return in format that can be JSON serialized.
	 *
	 * @since 5.0
	 *
	 * @return array
	 */
	public function jsonSerialize() {
		return get_object_vars( $this );
	}

}