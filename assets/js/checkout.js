/* global jQuery */
jQuery( function( $ ) {
    // Update checkout totals when certificate changes.
    function updateCheckoutTotals() {
        $( document.body ).trigger( 'update_checkout' );
    }

    // Toggle visibility of tax details form based on value of "Tax exempt?" checkbox.
    $( document ).on( 'change', '#certificate_id', function() {
        $( '#exempt_certificate_form' ).toggle(
            $( '#certificate_id' ).val() === 'new'
        );

        updateCheckoutTotals();
    } );

    $( '#certificate_id' ).trigger( 'change' );
} );
