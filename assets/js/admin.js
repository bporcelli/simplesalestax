/**
 * Simple Sales Tax admin scripts.
 */

jQuery( function() {

    // Verify TaxCloud settings
    jQuery( '#verifySettings' ).click( function( e ) {

        e.preventDefault();

        var apiID = jQuery( '#woocommerce_wootax_tc_id' ).val().trim();
        var apiKey = jQuery( '#woocommerce_wootax_tc_key' ).val().trim();

        if ( ! apiID || ! apiKey ) {
            alert( 'Please enter your API Login ID and API Key.' );
        } else {
            jQuery.post(
                ajaxurl,
                {
                    action: 'wootax-verify-taxcloud',
                    wootax_tc_id: apiID,
                    wootax_tc_key: apiKey
                },
                function( resp ) {
                    if ( resp.success == true ) {
                        alert( 'Success! Your TaxCloud settings are valid.' );
                    } else {
                        alert( 'Connection to TaxCloud failed. ' + resp.data + '.' );
                    }
                }
            );
        }

    } );

    // Uninstall WooTax
    // todo: use anchor tag instead of uninstall button; use JS only to confirm the action
    jQuery( '#wootax_uninstall' ).click( function( e ) {

        e.preventDefault();

        // Verify user action
        var msg = 'Are you sure you want to uninstall Simple Sales Tax? All of your settings will be erased.';

        if ( confirm( msg ) ) {

            // Show loader
            jQuery( '#wootax-loader' ).css( 'display', 'inline-block' );
            
            jQuery.post(
                ajaxurl,
                {
                    action: 'wootax-uninstall'
                },
                function( resp ) {

                    resp = eval( '(' + resp + ')' );

                    if ( resp.success ) {
                        alert( 'Simple Sales Tax was uninstalled successfully. The page will now reload.' );
                        
                        // Don't use window.location.reload(); this will cause form re-submission on the settings page
                        window.location.href = '/wp-admin/admin.php?page=wc-settings&tab=integration&section=wootax';
                    } else {
                        alert( 'Error: ' + resp );
                    }

                    // Hide loader
                    jQuery( '#wootax-loader' ).hide();

                }
            );

        }

    } );
    
    // Download log file
    // todo: rewrite to use anchor tag! This is stupid.
    jQuery( '#wootax_download_log' ).click( function( e ) {

        // Stop jitter
        e.preventDefault();

        // Redirect to initiate file download
        window.location.href = window.location.href + '&download_log=true';

    } );

    // Remove manually added tax rates
    // todo: rewrite to NOT use ajax. doesn't add to user experience and is unecessary.
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
            alert('Please select the tax rate classes you would like to remove.');
        } else if (confirm('You are about to remove '+ count +' tax rates from '+ toRemove.length +' tax class(es). Are you sure you want to proceed?')) {
            jQuery.post(
                ajaxurl,
                {
                    action: 'wootax-delete-rates',
                    rates: toRemove.join()
                },
                function(resp) {
                    if (resp == true) {
                        jQuery(toRemove).each(function() {
                            $table.find('input[data-val="'+ this +'"]').attr('checked', false).closest('tr').find('td').eq(1).text('0');
                        });

                        $table.find('.check_column input').trigger('click');

                        alert('The selected tax rates were removed successfully. Click "Save Changes" to complete the installation process.');
                    } else {
                        alert(resp);
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
            alert( 'This action is not permitted. You must have at least one business address entered for Simple Sales Tax to work properly.' );
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
     */
    // todo: better approach than this warning.
    jQuery('#woocommerce-order-items').on('click', 'button.refund-items', function(e) {
        e.preventDefault();

        alert( 'IMPORTANT: Before issuing a refund, you must set this order\'s status to "completed" and save it. If you have already done so, you may ignore this message.' );
    });

    /**
     * Replace 'WOOTAX-RATE-DO-NOT-REMOVE' with custom rate code in recurring tax select box
     */
    jQuery( '.tax_rows_group select' ).each( function() {
        jQuery( this ).find( 'option' ).each( function() {
            jQuery( this ).text( jQuery( this ).text().replace( 'WOOTAX-RATE-DO-NOT-REMOVE', WT.rateCode ) );
        } );
    } );

} );