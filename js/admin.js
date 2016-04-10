// Admin Scripts
jQuery(function() {
    // Verify TaxCloud settings
    jQuery( '#verifySettings' ).click(function() {

        var loginID = jQuery('#woocommerce_wootax_tc_id').val();
        var apiKey = jQuery('#woocommerce_wootax_tc_key').val();

        // Check for empty fields
        if (loginID == null || loginID == '' || apiKey == '' || apiKey == null) {
            alert('You must enter a login ID and API Key before validating your settings.');
            jQuery('#wootax_tc_id').focus();
        } else {
            // Send AJAX request
            jQuery.ajax({
                type: 'POST',
                url: WT.ajaxURL,
                data: 'action=wootax-verify-taxcloud&wootax_tc_id=' + loginID + '&wootax_tc_key=' + apiKey,
                success: function(resp) {
                    if (resp == 1) {
                        alert('Success! We were able to connect to TaxCloud using these settings.');
                    } else {
                        alert('Connection to TaxCloud failed. Please verify that your API credentials are correct and try again. ' + resp);
                    }
                }
            });
        }

        return false;

    });

    // Uninstall WooTax
    jQuery( '#wootax_uninstall' ).click( function( e ) {

        // Stop page jitter
        e.preventDefault();

        // Verify user action
        var msg = 'Are you sure you want to uninstall Simple Sales Tax? All of your settings will be erased.';

        if ( confirm( msg ) ) {

            // Show loader
            jQuery( '#wootax-loader' ).css( 'display', 'inline-block' );
            
            jQuery.ajax({
                type: 'POST',
                url: WT.ajaxURL,
                data: 'action=wootax-uninstall',
                success: function(resp) {

                    resp = eval( '(' + resp + ')' );

                    if (resp.status == 'success') {
                        alert( 'Simple Sales Tax was uninstalled successfully. The page will now reload.' );
                        
                        // Don't use window.location.reload(); this will cause form re-submission on the settings page
                        window.location.href = '/wp-admin/admin.php?page=wc-settings&tab=integration&section=wootax';
                    } else {
                        alert( 'Error: ' + resp );
                    }

                    // Hide loader
                    jQuery( '#wootax-loader' ).hide();

                }
            });

        }

    } );
    
    // Download log file
    jQuery( '#wootax_download_log' ).click( function( e ) {

        // Stop jitter
        e.preventDefault();

        // Redirect to initiate file download
        window.location.href = window.location.href + '&download_log=true';

    } );

    // Remove manually added tax rates
    jQuery('#remove-rates').click(function() {

        var toRemove = [];
        var count = 0;
        var $table = jQuery('table.shippingrows');

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
            jQuery.ajax({
                type: 'POST',
                url: WT.ajaxURL,
                data: 'action=wootax-delete-rates&rates='+ toRemove.join(),
                success: function(resp) {
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
            });
        }

    });

    /**
     * Add row to address table
     */
    jQuery('button.add-address-row').click(function(e) {

        e.preventDefault();

        var table = jQuery(this).closest('table');
        var old_row = jQuery('#address_table tr').last();
        var old_is_default = old_row.find('input[type=radio]').is(':checked');
        var new_row = jQuery('#address_table tr').last().clone();
        var old_index = parseInt(new_row.find('input').eq(0).attr('name').split('[')[1].replace(']', ''));
        var new_index = old_index + 1;

        // Update row index
        new_row.find('input, select').each(function() {
            var $this = jQuery(this);

            if ($this.is('select')) 
                $this.find('option:selected').attr('selected', null);
            
            if ($this.is('input'))
                $this.val('');

            $this.attr('name', $this.attr('name').replace('['+ old_index +']', '['+ new_index +']'));
        });

        // Enable "remove" button
        new_row.find('.remove_address').removeClass('disabled');

        // Insert row
        new_row.appendTo(table.find('tbody'));

        // Fix default radio button
        if (old_is_default) {
            old_row.find('input[type=radio]').attr('checked', 'checked');
        }

    });

    /**
     * Remove row from address table
     */
    jQuery(document).on('click', '.remove_address', function(e) {

        e.preventDefault();

        if (jQuery(this).is('.disabled')) {
            alert('This action is not permitted. You must have at least one business address entered for Simple Sales Tax to work properly.');
        } else {
            var toHide = jQuery(this).closest('tr');

            if (toHide.find('input[name=wootax_default_address]').is(':checked'))
                jQuery('#address_table tbody tr').eq(0).find('input[name="wootax_default_address"]').attr('checked', 'checked');

            toHide.hide().remove();
        }

    });

    /**
     * Warn user to "complete" order before processing a partial refund
     */
    jQuery('#woocommerce-order-items').on('click', 'button.refund-items', function(e) {

        e.preventDefault();
        alert('IMPORTANT: Before issuing a refund, you must set this order\'s status to "completed" and save it. If you have already done so, you may ignore this message.');
   
    });

    /**
     * Dismiss persistent admin message
     */
    jQuery( '.dismissable button' ).click( function() {
        var $parent = jQuery(this).closest( '.wootax-message' );
        var id      = $parent.data('id');

        jQuery.ajax({
            type: 'POST',
            url: WT.ajaxURL,
            data: 'action=wootax-remove-message&message_id='+ id,
            success: function(resp) {
                if ( resp == true ) {
                    $parent.fadeOut( 'fast', function() {
                        jQuery(this).remove();
                    } );
                }
            }
        });
    } );

    /**
     * Replace 'WOOTAX-RATE-DO-NOT-REMOVE' with custom rate code in recurring tax select box
     */
    if ( jQuery('.tax_rows_group').length != 0 ) {
        jQuery('.tax_rows_group select').each(function() {
            jQuery(this).find('option').each(function() {
                jQuery(this).text(jQuery(this).text().replace('WOOTAX-RATE-DO-NOT-REMOVE', WT.rateCode));
            });
        });
    }
});