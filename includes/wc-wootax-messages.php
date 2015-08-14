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
 * @param (boolean) $dismissable should we give the user an option to dismiss the message?
 * @param (string) $id - an id for the message; used to programatically dismiss messages
 * @param (int) $dismissal_time - amount of time before dimissed message should appear again; 0 for no reappearance
 * @return void
 */
function wootax_add_message( $content, $type = "error", $dismissable = false, $id = "", $dismissal_time = 0 ) {
	$all_messages = get_transient( 'wootax_messages' );
	$all_messages = $all_messages == false ? array() : $all_messages;

	// 4.6: merge messages into a single array
	if ( isset( $all_messages['normal'] ) && isset( $all_messages['persistent'] ) ) {
		$new_messages = $all_messages['persistent']; // Must maintain key-value pairs for persistent messages

		foreach ( $all_messages['normal'] as $message ) {
			array_push( $new_messages, $message );
		}

		$all_messages = $new_messages;
	}

	// If no ID is provided for this message, use the greatest numeric array key + 1
	if ( empty( $id ) ) {
		$id = 0;

		if ( count( $all_messages ) > 0 ) {
			foreach ( array_keys( $all_messages ) as $key ) {
				if ( is_numeric( $key ) && $key > $id ) {
					$id = $key;
				}
			}

			$id += 1;	
		}
	}

	// Reset last displayed time to avoid issues with dismissable messages that do not have unique IDs
	if ( is_numeric( $id ) ) {
		wootax_set_last_displayed( $id, false );
	}

	$all_messages[ $id ] = array(
		'content'        => $content,
		'type'           => $type,
		'dismissable'    => $dismissable,
		'dismissal_time' => $dismissal_time,
	);

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

	if ( ! $all_messages ) { // Transient is not set, or messages array is empty
		return;
	} else {
		if ( isset( $all_messages[ $id ] ) ) {
			if ( $all_messages[ $id ]['dismissal_time'] > 0 ) {
				wootax_set_last_displayed( $id, false ); // Reset last displayed time
			}

			unset( $all_messages[ $id ] );

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

	if ( 0 < count( $all_messages ) && is_array( $all_messages ) ) {
		foreach ( $all_messages as $id => $message ) {
			$message_class = $message['dismissable'] ? ' dismissable' : '';

			$dismiss_time = $message['dismissal_time'];
			$current_time = time();
			$last_time    = wootax_get_last_displayed( $id );

			$dismiss_url    = wp_nonce_url( add_query_arg( 'mid', $id, add_query_arg( 'action', 'wt-dismiss-message', $_SERVER['REQUEST_URI'] ) ), 'wt-dismiss-message' );
			$dismiss_button = '<a href="'. $dismiss_url .'" class="wp-core-ui button-secondary wootax-button">Dismiss'. ( $dismiss_time > 0 ? ' ('. human_time_diff( 0, $dismiss_time ) .')' : '' ) .'</a>';

			if ( $dismiss_time == 0 || ! $last_time || $current_time - $last_time >= $dismiss_time ) {
				echo '<div class="wootax-message '. $message['type'] . $message_class .'" data-id="'. $id .'"><p>'. $message['content'] . ( $message['dismissable'] ? $dismiss_button : '' ) .'</p></div>';
			}
		}
	}
}

add_action( 'admin_notices', 'wootax_display_messages' );

/**
 * Get the last time a message was displayed
 *
 * @since 4.6
 * @param (mixed) $id - message ID
 * @return int | bool
 */
function wootax_get_last_displayed( $id ) {
	return get_transient( "wootax_message_{$id}_last_displayed" );
}

/**
 * Set the last time a message was displayed
 *
 * @since 4.6
 * @param (mixed) $id - message ID
 * @param (int) $time - timestamp when message was displayed
 * @return int | bool
 */
function wootax_set_last_displayed( $id, $time ) {
	set_transient( "wootax_message_{$id}_last_displayed", $time );
}

/**
 * Removes non-persistent messages after page load
 *
 * @since 4.2
 * @return void
 */
function wootax_remove_flash_messages() {
	$all_messages = get_transient( 'wootax_messages' );

	if ( ! $all_messages ) { // Transient is not set or the array is empty
		return;
	} else {
		foreach ( $all_messages as $id => $message ) {
			if ( ! $message['dismissable'] && is_numeric( $id ) ) { // Flash messages will have numeric IDs and be non-dismissable
				unset( $all_messages[ $id ] );
			}
		}

		set_transient( 'wootax_messages', $all_messages );
	}
}

add_action( 'shutdown', 'wootax_remove_flash_messages' );

/**
 * Dismiss a message if requested
 *
 * @since 4.6
 * @return void
 */
function wootax_maybe_dismiss_message() {
	if ( isset( $_REQUEST['action'] ) && $_REQUEST['action'] == 'wt-dismiss-message' && wp_verify_nonce( $_REQUEST['_wpnonce'], 'wt-dismiss-message' ) ) {
		$message_id   = $_REQUEST['mid'];
		$all_messages = get_transient( 'wootax_messages' );

		if ( isset( $all_messages[ $message_id ] ) && $message = $all_messages[ $message_id ] ) {
			if ( $message['dismissal_time'] == 0 ) {
				unset( $all_messages[ $message_id ] );
			} else {
				wootax_set_last_displayed( $message_id, time() );
			}
		}

		set_transient( 'wootax_messages', $all_messages );
	}
}

add_action( 'admin_init', 'wootax_maybe_dismiss_message' );