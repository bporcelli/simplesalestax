<?php

/**
 * Uninstalls Simple Sales Tax.
 *
 * Uninstalling removes all user roles, scheduled events, and options.
 *
 * @author 	Simple Sales Tax
 * @package SST
 * @since 	5.0
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

wp_clear_scheduled_hook( 'wootax_update_recurring_tax' );

// Remove data, but only if "Remove Data on Delete" option is enabled 
$settings    = get_option( 'woocommerce_wootax_settings' );
$remove_data = isset( $settings[ 'remove_all_data' ] ) ? 'yes' === $settings[ 'remove_all_data' ] : false;

if ( $remove_data ) {
	global $wpdb;

	// Roles
	require_once 'includes/class-sst-install.php';
	SST_Install::remove_roles();

	// Options
	$wpdb->query( "
		DELETE FROM {$wpdb->options} 
		WHERE option_name LIKE 'woocommerce\_wootax%' 
		OR option_name LIKE 'wootax\_%'
		OR option_name LIKE 'tic\_%';
	" );

	// Product settings
	$wpdb->query( "
		DELETE FROM {$wpdb->postmeta} pm, {$wpdb->posts} p
		WHERE p.post_type IN ( 'product', 'product_variation' )
		AND p.ID = pm.post_id
		AND meta_key LIKE 'wootax_\%'
		OR meta_key LIKE '\_wootax\_%';
	" );

	// Database tables
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}sst_tics" );
}