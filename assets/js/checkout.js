/* global jQuery, SSTCertData, wp */
jQuery( function( $ ) {
    // Render exemption certificate table.
    renderCertificateTable( {
        certificates: SSTCertData.certificates,
        selected: SSTCertData.selected,
    } );

    // Update checkout totals when certificate changes.
    function updateCheckoutTotals() {
        $( document.body ).trigger( 'update_checkout' );
    }

    wp.hooks.addAction(
        'sst_certificate_changed',
        'sst-checkout',
        updateCheckoutTotals
    );

    // Toggle visibility of tax details form based on value of "Tax exempt?" checkbox.
    $( document ).on( 'change', '#tax_exempt_checkbox', function() {
        var checked = $( '#tax_exempt_checkbox' ).is( ':checked' );

        if ( checked ) {
            $( '#tax_details' ).fadeIn();
        } else {
            $( '#tax_details' ).hide();
        }

        updateCheckoutTotals();
    } );

    // Initialize exemption form.
    $( '#tax_exempt_checkbox' ).change();
} );
