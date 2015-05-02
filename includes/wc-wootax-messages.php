<?php

/**
 * Helper methods for displaying WooTax admin messages
 *
 * @package WooTax
 * @since 4.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Do not all direct access 
}

/**
 * Adds a WooTax admin message
 *
 * @since 4.4
 * @param (string) $content the message content
 * @param (string) $type the message type (updated, error, update-nag) @see https://codex.wordpress.org/Plugin_API/Action_Reference/admin_notices
 * @param (string) $id a unique identifier for the message (used to programatically dismiss messages)
 * @param (boolean) $persistent should this message persist through page loads?
 * @param (boolean) $dismissable should we give the user an option to dismiss the message?
 * @return void
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
 * Remove a persistent WooTax admin message
 *
 * @since 4.4
 * @param (string) $id unique identifier for message to remove
 * @return void
 */
function wootax_remove_message( $id ) {
	$all_messages = get_transient( 'wootax_messages' );

	// Exit if not persistent messages exist
	if ( $all_messages == false || !isset( $all_messages['persistent'] ) ) {
		return;
	} else {
		if ( isset( $all_messages['persistent'][ $id ] ) ) {
			unset( $all_messages['persistent'][ $id ] );
			
			set_transient( 'wootax_messages', $all_messages );
		}
	}
}

/**
 * Remove persistent message via AJAX
 *
 * @since 4.4
 * @return void
 */
function wootax_ajax_remove_message() {
	wootax_remove_message( $_POST['message_id'] );

	die( true );
} 

add_action( 'wp_ajax_wootax-remove-message', 'wootax_ajax_remove_message' );

/**
 * Display admin messages 
 *
 * @since 4.4
 * @return void
 */
function wootax_display_messages() {
	$all_messages = get_transient( 'wootax_messages' );
		
	// Loop through messages and output
	if ( isset( $all_messages['persistent'] ) ) {
		foreach ( $all_messages['persistent'] as $id => $message ) {
			$dismissable = $message['dismissable'] ? ' dismissable' : '';
			echo '<div class="wootax-message '. $message['type'] . $dismissable .'" data-id="'. $id .'"><p>'. $message['content'] . ( $message['dismissable'] ? ' <button type="button" class="wp-core-ui button-secondary wootax-button">Dismiss</button>' : '' ) .'</p></div>';
		}
	}

	if ( isset( $all_messages['normal'] ) ) {
		foreach ( $all_messages['normal'] as $message ) {
			echo '<div class="wootax-message '. $message['type'] .'"><p>'. $message['content'] .'</p></div>';
		}
	}
}

add_action( 'admin_notices', 'wootax_display_messages' );

/**
 * Removes non-persistent messages after page load
 *
 * @since 4.2
 * @return void
 */
function wootax_remove_flash_messages() {
	$all_messages = get_transient( 'wootax_messages' );

	if ( !$all_messages || !isset( $all_messages['normal'] ) ) {
		return;
	} else {
		$all_messages['normal'] = array();
		set_transient( 'wootax_messages', $all_messages );
	}
}

add_action( 'shutdown', 'wootax_remove_flash_messages' );