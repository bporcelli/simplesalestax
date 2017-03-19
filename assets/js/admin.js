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
                        action: 'wootax-verify-taxcloud',
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
        
        // Remove manually added tax rates
        jQuery( '#remove-rates' ).click(function() {
            var toRemove = [];
            var count = 0;
            var $table = jQuery('table#delete-rates');

            $table.find('tr').each(function() {
                var rates = jQuery(this).find('td').eq(1).text();
                var check = jQuery(this).find('th').find('input');
                var rate = check.data('val');

                if (check.is(':checked') && typeof(rate) != 'undefined') {
                    count += parseInt(rates);
                    toRemove.push(rate);
                }
            });

            if (toRemove.length == 0) {
                alert( data.strings.select_classes );
            } else if (confirm(data.strings.confirm_rate_removal)) {
                jQuery.post(
                    ajaxurl,
                    {
                        action: 'wootax-delete-rates',
                        rates: toRemove.join()
                    },
                    function(resp) {
                        if (resp.success) {
                            jQuery(toRemove).each(function() {
                                $table.find('input[data-val="'+ this +'"]').attr('checked', false).closest('tr').find('td').eq(1).text('0');
                            });

                            $table.find('.check_column input').trigger('click');

                            alert( data.strings.rates_removed );
                        } else {
                            alert( resp.data );
                        }
                    }
                );
            }
        });

        /**
         * Add row to address table
         */
        jQuery( 'button.add-address-row' ).click( function( e ) {
            e.preventDefault();

            var tbody = jQuery( this ).closest( 'table' ).find( 'tbody' );
            var rows = tbody.find( 'tr' );
            var old_row = rows.last();
            var old_is_default = old_row.find( 'input[type=radio]' ).is( ':checked' );
            var new_row = old_row.clone();

            // Reset field values
            new_row.find( 'input, select' ).each( function() {
                jQuery( this ).val( '' );
            } );

            // Update row index
            var old_index = old_row.find( 'input[type=radio]' ).val();
            new_row.find( 'input[type=radio]' ).val( parseInt( old_index ) + 1 ); 

            // Enable "remove" button
            new_row.find( '.remove_address' ).removeClass( 'disabled' );

            // Insert row
            new_row.appendTo( tbody );

            // Fix default radio button
            if ( old_is_default ) {
                old_row.find( 'input[type=radio]' ).attr( 'checked', 'checked' );
            }
        } );

        /**
         * Remove row from address table
         */
        jQuery( document ).on( 'click', '.remove_address', function( e ) {
            e.preventDefault();

            if ( jQuery( this ).is( '.disabled' ) ) {
                alert( data.strings.cant_remove_address );
            } else {
                // All rows
                var rows = jQuery( this ).closest( 'tbody' ).find( 'tr' );

                // The row to remove
                var row = jQuery( this ).closest( 'tr' );

                // If the address to be deleted is the current default, reset the default address
                if ( row.find( 'input[type=radio]' ).is( ':checked' ) ) {
                    jQuery( rows[ 0 ] ).find( 'input[type=radio]' ).attr( 'checked', 'checked' );
                }

                // Decrement indices for all addresses that come after this one
                var index = rows.index( row );

                for ( var i = index + 1; i < rows.length; i++ ) {
                    var $radio = jQuery( rows[ i ] ).find( 'input[type=radio]' );
                    $radio.val( parseInt( $radio.val() ) - 1 );
                }

                // Remove the address
                row.hide().remove();
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
         */
        jQuery( '.tax_rows_group select' ).each( function() {
            jQuery( this ).find( 'option' ).each( function() {
                jQuery( this ).text( jQuery( this ).text().replace( 'WOOTAX-RATE-DO-NOT-REMOVE', WT.rateCode ) );
            } );
        } );
    } );
})(SST);