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

// TODO: ONLY RUN FOLLOWING IF USER SELECTS "REMOVE DATA ON UNINSTALL" OPTION
if ( $setting ) {
	global $wpdb;

	// Roles
	require_once 'includes/class-sst-install.php';
	SST_Install::remove_roles();

	// Options
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'woocommerce\_wootax%' OR option_name LIKE 'wootax\_%';" );

	// Default TICs
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'tic\_%';" );

	// Product settings
	// TODO: remove product settings (shipping origin addresses, tic, etc.)
}