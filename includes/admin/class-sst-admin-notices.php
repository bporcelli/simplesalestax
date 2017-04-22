<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Admin notices.
 *
 * Contains methods for adding, removing, and displaying admin notices.
 *
 * @author 	Simple Sales Tax
 * @package SST
 * @since 	5.0
 */
class SST_Admin_Notices extends WC_Admin_Notices {

	/**
	 * Add a custom notice.
	 * @param string $name
	 * @param string $notice_html
	 * @param bool $persistent (default: false)
	 * @param string $class (default: 'updated')
	 */
	public static function add( $name, $notice_html, $persistent = false, $class = 'updated' ) {
		self::add_notice( $name );

		update_option( 'woocommerce_admin_notice_' . $name, array(
			'message'    => wp_kses_post( $notice_html ),
			'persistent' => $persistent,
			'class'      => $class,
		) );
	}

	/**
	 * Output any stored custom notices. Remove temporary notices.
	 */
	public static function output_custom_notices() {
		$notices = self::get_notices();

		if ( ! empty( $notices ) ) {
			foreach ( $notices as $notice ) {
				if ( empty( self::$core_notices[ $notice ] ) ) {
					$data         = get_option( 'woocommerce_admin_notice_' . $notice );
					$persistent   = $data[ 'persistent' ];
					$notice_html  = $data[ 'message' ];
					$notice_class = $data[ 'class' ];
					
					if ( $notice_html ) {
						include( 'views/html-notice-custom.php' );
					}

					// Remove transient notices
					if ( ! $persistent ) {
						self::remove_notice( $notice );
					}
				}
			}
		}
	}
}

SST_Admin_Notices::init();