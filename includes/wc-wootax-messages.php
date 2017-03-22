<?php

/**
 * Functions for displaying admin notices.
 *				
 * @author 	Simple Sales Tax
 * @package SST
 * @since 	4.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Adds an admin message.
 *
 * @since 4.4
 *
 * @param string $content Message content.
 * @param string $type Message type (updated, error, update-nag).
 * @param string $id Unique identifier for the message (used to programatically dismiss messages).
 * @param boolean $persistent Should this message persist through page loads?
 * @param boolean $dismissable Should we give the user an option to dismiss the message?
 */
function wootax_add_message( $content, $type = "error", $id = "", $persistent = false, $dismissable = false ) {
	$all_messages = get_transient( 'wootax_messages' );

	// Fetch all WooTax messages
	$all_messages = $all_messages == false ? array() : $all_messages;

	// Determine what class of messages (normal, persistent) need to be fetched
	$class = $persistent ? 'persistent' : 'normal';	

	// Fetch em
	$type_messages = isset( $all_messages[ $class ] ) && is_array( $all_messages[ $class ] ) ? $all_messages[ $class ] : array();

	// Add new message
	$new = array(
		'content' => $content,
		'type'    => $type,
	);

	if ( $persistent ) {
		$new['dismissable'] = $dismissable;
	}

	$id = empty( $id ) ? count( $type_messages ) : $id;
	$type_messages[ $id ] = $new;

	$all_messages[ $class ] = $type_messages;

	set_transient( 'wootax_messages', $all_messages );
}

/**
 * Removes a persistent admin message.
 *
 * @since 4.4
 *
 * @param string $id ID of message to remove.
 */
function wootax_remove_message( $id ) {
	$all_messages = get_transient( 'wootax_messages' );

	if ( $all_messages != false && isset( $all_messages[ 'persistent' ] ) ) {
		if ( isset( $all_messages[ 'persistent' ][ $id ] ) ) {
			unset( $all_messages[ 'persistent' ][ $id ] );
			
			set_transient( 'wootax_messages', $all_messages );
		}
	}
}

/**
 * AJAX handler to remove a persistent message.
 *
 * @since 4.4
 */
function wootax_ajax_remove_message() {
	wootax_remove_message( $_POST['message_id'] );
	wp_send_json_success();
}

add_action( 'wp_ajax_wootax-remove-message', 'wootax_ajax_remove_message' );

/**
 * Display admin messages. 
 *
 * @since 4.4
 */
function wootax_display_messages() {
	$all_messages = get_transient( 'wootax_messages' );
		
	// Loop through messages and output
	if ( isset( $all_messages[ 'persistent' ] ) ) {
		foreach ( $all_messages[ 'persistent' ] as $id => $message ) {
			$dismissable = $message[ 'dismissable' ] ? ' dismissable' : '';
			echo '<div class="wootax-message '. $message['type'] . $dismissable .'" data-id="'. $id .'"><p>'. $message[ 'content' ] . ( $message[ 'dismissable' ] ? ' <button type="button" class="wp-core-ui button-secondary wootax-button">Dismiss</button>' : '' ) .'</p></div>';
		}
	}

	if ( isset( $all_messages[ 'normal' ] ) ) {
		foreach ( $all_messages[ 'normal' ] as $message ) {
			echo '<div class="wootax-message '. $message[ 'type' ] .'"><p>'. $message[ 'content' ] .'</p></div>';
		}
	}
}

add_action( 'admin_notices', 'wootax_display_messages' );

/**
 * Removes non-persistent messages after page load.
 *
 * @since 4.2
 */
function wootax_remove_flash_messages() {
	$all_messages = get_transient( 'wootax_messages' );

	if ( $all_messages && isset( $all_messages[ 'normal' ] ) ) {
		$all_messages[ 'normal' ] = array();
		set_transient( 'wootax_messages', $all_messages );
	}
}

add_action( 'shutdown', 'wootax_remove_flash_messages' );

/**
 * Enqueue scripts and styles for admin notices.
 *
 * @since 5.0
 */
function wootax_enqueue_notices_scripts() {
	$_assets_url = SST()->plugin_url() . '/assets';

	wp_enqueue_script( 'wootax-admin-notices', "$_assets_url/js/notices.js", array( 'jquery' ), '1.0' );
	wp_enqueue_style( 'wootax-admin-notices', "$_assets_url/css/admin-notices.css" );
}

add_action( 'admin_enqueue_scripts', 'wootax_enqueue_notices_scripts' );
