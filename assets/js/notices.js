/**
 * JavaScripts for displaying and dismissing admin notices.
 */

jQuery( function() {

	// Dismiss a persistent admin notice
	jQuery( '.dismissable button' ).click( function() {
	    var $parent = jQuery( this ).closest( '.wootax-message' );
	    var id      = $parent.data( 'id' );

	    jQuery.post(
	    	ajaxurl,
	        {
	        	action: 'wootax-remove-message',
	        	message_id: id
	        },
	        function( resp ) {
	            if ( resp.success ) {
	                $parent.fadeOut( 'fast', function() {
	                    jQuery( this ).remove();
	                } );
	            }
	        }
	    );
	} );

} );