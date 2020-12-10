/* global SSTCertData */
(function(data) {
    jQuery( function() {
        jQuery( '.sst-view-certificate' ).click( function( e ) {
            e.preventDefault();

            var certificate = data.certificate;

            if ( certificate ) {
                if ( certificate.SinglePurchase ) {
                    certificate.backgroundImage = data.images.single_cert;
                } else {
                    certificate.backgroundImage = data.images.blanket_cert;
                }

                jQuery( this ).SSTBackboneModal({
                    template: 'sst-modal-view-certificate',
                    variable: certificate,
                });
            }
        } );

        // Disable "View" button if no certificate is available
        if ( ! data.certificate ) {
            jQuery( '.sst-view-certificate' ).prop( 'disabled', true );
        }
    } );
})(SSTCertData);