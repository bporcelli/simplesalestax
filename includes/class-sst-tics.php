<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * TICs.
 *
 * Functionality related to getting, updating, and searching the list of TICs.
 *
 * @author 	Simple Sales Tax
 * @package SST
 * @since 	5.0
 */
class SST_TICS {

	/**
	 * Insert a list of TICs into the database.
	 *
	 * @since 5.0
	 *
	 * @param array $tics List of TICs.
	 */
	protected static function insert_tics( $tics ) {
		global $wpdb;

		foreach ( $tics as $tic ) {
			$_tic = $tic->tic;

			/* Add parent */
			$wpdb->insert( $wpdb->prefix . 'sst_tics', array(
				'id'     => $_tic->id,
				'ssuta'  => 'true' == $_tic->ssuta,
				'parent' => $_tic->parent,
				'title'  => $_tic->title,
				'label'  => $_tic->label
			) );

			/* Add descendents recursively */
			if ( isset( $_tic->children ) && is_array( $_tic->children ) ) {
				self::insert_tics( $_tic->children );
			}
		}
	}

	/**
	 * Update the list of TICs if necessary. The list is considered to be stale
	 * if a week or more has passed since the last update.
	 *
	 * @since 5.0
	 */
	protected static function update_tic_list() {
		global $wpdb;

		$last_update = get_option( 'wootax_last_update', 0 );

		if ( time() - $last_update >= WEEK_IN_SECONDS ) {
			/* Fetch new TICs */
			$ch = curl_init( 'https://taxcloud.net/tic/?format=json' );
			curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
			$res = json_decode( curl_exec( $ch ) );
			curl_close( $ch );

			/* Remove existing TICs */
			$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}sst_tics" );

			/* Insert new TICs */
			self::insert_tics( $res->tic_list );

			update_option( 'wootax_last_update', time() );
		}
	}

	/**
	 * Get a list of all available TICs.
	 *
	 * @since 5.0
	 *
	 * @return SST_TIC[]
	 */
	public static function get_tics() {
		global $wpdb;

		self::update_tic_list();

		$tics     = array();
		$raw_tics = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}sst_tics WHERE ssuta = 1" );

		foreach ( $raw_tics as $tic ) {
			$tics[ $tic->id ] = new SST_TIC( $tic ); 
		}

		return $tics;
	}

}