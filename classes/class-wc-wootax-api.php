<?php

/**
 * Class for interfacing with the WooTax API
 *
 * @package WooTax
 * @author Brett Porcelli
 * @since 4.6
 */

require_once 'class-wc-wootax-api-error.php';

class WC_WooTax_API {
	/* Endpoint for API requests */
	private static $api_endpoint = 'https://wootax.com/?wt_api';

	/* Holds the single WC_WooTax_API instance */
	protected static $_instance = null;

	/* cURL handle */
	private $ch = null;

	/**
	 * Constructor; set up cURL object
	 *
	 * @since 4.6
	 */
	public function __construct() {
		if ( $this->ch == null ) {
			$ch = curl_init( self::$api_endpoint );
			
			curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
			curl_setopt( $ch, CURLOPT_TIMEOUT, 15 );
			curl_setopt( $ch, CURLOPT_POST, true );

			$this->ch = $ch;
		}
	}

	/**
	 * Get the single instance of the WC_WooTax_API object
	 *
	 * @since 4.6
	 */
	public static function instance() {
		if ( self::$_instance == null ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	/**
	 * Cloning is forbidden.
	 *
	 * @since 4.6
	 */
	public function __clone() {
		return;
	}

	/**
	 * Unserializing instances of this class is forbidden.
	 *
	 * @since 4.6
	 */
	public function __wakeup() {
		return;
	}

	/**
	 * Get version info/changelog for plugin with given slug 
	 * 
	 * @param (string) $slug - slug of plugin whose version info is being requested
	 * @return (array) array of plugin info
	 * @since 4.6
	 */
	public function get_version( $slug ) {
		return array(
			'new_version'   => '1.0',
			'name'          => 'WooTax Plus',
			'slug'          => $slug,
			'url'           => 'https://wootax.com?wt_api=get_changelog&slug='. $slug,
			'homepage'      => 'https://wootax.com',
			'package'       => 'https://wootax.com?wt_api=download_latest&slug='. $slug,
			'download_link' => 'https://wootax.com?wt_api=download_latest&slug='. $slug,
			'sections'      => serialize(
				array(
					'changelog' => wpautop( strip_tags( stripslashes( $changelog ), '<p><li><ul><ol><strong><a><em><span><br>' ) )
				)
			)
		);
	}

	/** 
	 * Request WooTax Plus member ID/start WooTax Plus trial
	 *
	 * @param (string) $first_name - first name of registrant
	 * @param (string) $last_name - last name of registrant
	 * @param (string) $email - email address of registrant
	 * @param (bool) $extended - are we requesting an extended trial?
	 * @return (string | array) member ID or error array
	 * @since 4.6 
	 */
	public function request_member_id( $first_name, $last_name, $email, $extended = false, $test_error = false ) {
		if ( $test_error ) {
			throw WC_WooTax_API_Error::get_error( 'Could not start trial: a trial has already been requested for this domain. Please <a href="#">contact WooTax</a> for assistance.' );
		} else if ( $extended ) {
			return '222222222222222222222222222';
		}

		return '111111111111111111111111111';
	} 

	/**
	 * Get a list of plugins that depend on WooTax Plus 
	 *
	 * @return (array) array of plugins dependent on Plus
	 * @since 4.6
	 */
	public function get_plugins_list( $test_error = false ) {
		if ( $test_error ) {
			throw WC_WooTax_API_Error::get_error( 'Could not fetch plugins list :(' );
		}

		return array(
			array(
				'slug'     => 'woocommerce-subscriptions.php',
				'required' => array( 'subscriptions' ),
			),
		);
	}

	/**
	 * Get information about a given member based on ID (subscription expiry time/start time/etc.) 
	 *
	 * @param (string) $id - member ID
	 * @return (array) array containing info about member
	 * @since 4.6 
	 */
	public function get_member_info( $id, $simulate_error = false ) {
		if ( $simulate_error ) {
			throw WC_WooTax_API_Error::get_error( 'Fetching member info failed' );
		}

		return array(
			'start_time'  => time() - 335 * DAY_IN_SECONDS,
			'expiry_time' => time() + 30 * DAY_IN_SECONDS,
		);
	}

	/**
	 * Validate a given member ID 
	 *
	 * @since 4.6
	 */
	public function validate_member_id( $id, $simulate_error = false ) {
		if ( $simulate_error ) {
			throw WC_WooTax_API_Error::get_error( 'Error validating member ID' );
		}

		$valid = array( '222222222222222222222222222', '111111111111111111111111111' );

		if ( in_array( $id, $valid ) ) {
			return true;
		}

		return false;
	}
}

/**
 * Return an instance of the WC_WooTax_API object
 *
 * @return (Object) WC_WooTax_API
 * @since 4.6
 */
if ( ! function_exists( 'WT_API' ) ):
	function WT_API() {
		return WC_WooTax_API::instance();
	}
endif;