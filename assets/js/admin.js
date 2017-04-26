/* global SST */
(function(data) {
    jQuery( function() {
        // Verify TaxCloud settings
        jQuery( '#verifySettings' ).click( function( e ) {
            e.preventDefault();

            var apiID = jQuery( '#woocommerce_wootax_tc_id' ).val();
            var apiKey = jQuery( '#woocommerce_wootax_tc_key' ).val();

            if ( ! apiID || ! apiKey ) {
                alert( data.strings.enter_id_and_key );
            } else {
                jQuery.post(
                    ajaxurl,
                    {
                        action: 'sst_verify_taxcloud',
                        wootax_tc_id: apiID,
                        wootax_tc_key: apiKey
                    },
                    function( resp ) {
                        if ( resp.success ) {
                            alert( data.strings.settings_valid );
                        } else {
                            alert( data.strings.verify_failed  + ' ' + resp.data + '.' );
                        }
                    }
                );
            }
        } );

        /**
         * Warn user to "complete" order before processing a partial refund
        jQuery('#woocommerce-order-items').on('click', 'button.refund-items', function(e) {
            e.preventDefault();

            alert( 'IMPORTANT: Before issuing a refund, you must set this order\'s status to "completed" and save it. If you have already done so, you may ignore this message.' );
        });*/

        /**
         * Replace 'WOOTAX-RATE-DO-NOT-REMOVE' with custom rate code in recurring tax select box
         
        jQuery( '.tax_rows_group select' ).each( function() {
            jQuery( this ).find( 'option' ).each( function() {
                jQuery( this ).text( jQuery( this ).text().replace( 'WOOTAX-RATE-DO-NOT-REMOVE', WT.rateCode ) );
            } );
        } );*/
    } );
})(SST);