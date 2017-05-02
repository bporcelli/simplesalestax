<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Logger.
 *
 * Used for logging error messages.
 *
 * @author 	Simple Sales Tax
 * @package SST
 * @since 	5.0
 */
class SST_Logger {

	/**
	 * @var string Log handle.
	 * @since 5.0
	 */
	protected static $handle = 'wootax';

	/**
	 * @var WC_Logger Logger instance.
	 * @since 5.0
	 */
	protected static $logger = null;

	/**
	 * Initialize the logger instance.
	 *
	 * @since 5.0
	 */
	public static function init() {
		if ( 'yes' == SST_Settings::get( 'log_requests' ) ) {
			self::$logger = function_exists( 'wc_get_logger' ) ? wc_get_logger() : new WC_Logger();
		}
	}

	/**
	 * Get log file path.
	 *
	 * @since 5.0
	 *
	 * @return string
	 */
	public static function get_log_path() {
		return wc_get_log_file_path( self::$handle );
	}

	/**
	 * Add a log entry.
	 *
	 * @since 5.0
	 *
	 * @param string $message Log message.
	 */
	public static function add( $message ) {
		if ( ! is_null( self::$logger ) )
			self::$logger->add( self::$handle, $message );
	}
}

SST_Logger::init();