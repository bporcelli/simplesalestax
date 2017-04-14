<?php

/**
 * SST functions.
 *
 * Utility functions used throughout the plugin.
 *
 * @author 	Simple Sales Tax
 * @package SST
 * @since 	5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Output HTML for a help tip.
 *
 * @since 5.0
 *
 * @param string $tip Tooltip content.
 */
function sst_tip( $tip ) {
	if ( function_exists( 'wc_help_tip' ) ) {
		echo wc_help_tip( $tip );
	} else {
		$img_path = WC()->plugin_url() . '/assets/images/help.png';
		$format = '<img class="help_tip" data-tip="%s" src="%s" height="16" width="16" />';
		printf( $format, $tip, $img_path );
	}
}
