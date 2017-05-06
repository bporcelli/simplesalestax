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
    } );
})(SST);