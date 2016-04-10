// Admin Scripts
jQuery(function() {
    // Verify TaxCloud settings
    jQuery('#verifySettings').click(function() {

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

    // Hide notifications
    jQuery('.wootax_disable_notices').click(function() {

        var $this = jQuery(this);

        jQuery.ajax({
            type: 'POST',
            url: WT.ajaxURL,
            data: 'action=wootax-disable-notifications',
            success: function(resp) {
                if (resp == 1) {
                    $this.closest('.error').slideUp('fast');
                } else {
                    alert('An error prevented WooTax from disabling notifications. Please try again.');
                }
            }
        });

        return false;

    });

    // Verify origin addresses using USPS shipping assistant
    jQuery('#verifyAddress').click(function(e) {

        e.preventDefault();

        // Reset errors
        jQuery('.form-error').removeClass('form-error');

        // Set up some vars
        var loginID = jQuery('#woocommerce_wootax_tc_id').val();
        var apiKey = jQuery('#woocommerce_wootax_tc_key').val();
        var uspsID = jQuery('#woocommerce_wootax_usps_id').val();
        var missing = 0;

        // Check configuration
        if (loginID == '') {
            jQuery('#woocommerce_wootax_tc_id').addClass('form-error');
            missing++;
        }

        if (apiKey == '') {
            jQuery('#woocommerce_wootax_tc_key').addClass('form-error');
            missing++;
        }

        if (uspsID == '') {
            jQuery('#woocommerce_wootax_usps_id').addClass('form-error');
            missing++;
        }

        // Check address fields and build query string with address data
        var required = ['address1', 'state', 'city', 'zip5'];
        var address_str = '';
        var x = 0;

        jQuery('#address_table tbody tr').each(function() {
            var $this = jQuery(this);

            for (var i = 0; i < required.length; i++) {
                var el = $this.find('.wootax_'+ required[i]);

                if ((el.is('select') && el.find('option:selected').length == 0 || el.find('option:selected').val() == '') || el.is('input') && el.val() == '') {
                    el.addClass('form-error');
                    missing++;
                } 
            }

            address_str += '&wootax_address1['+ x +']='+ $this.find('.wootax_address1').val() +'&wootax_address2['+ x +']='+ $this.find('.wootax_address2').val() + '&wootax_city['+ x +']='+ $this.find('.wootax_city').val() +'&wootax_state['+ x +']='+ $this.find('.wootax_state').find('option:selected').val() +'&wootax_zip5['+ x +']='+ $this.find('.wootax_zip5').val() +'&wootax_zip4['+ x +']='+ $this.find('.wootax_zip4').val();

            x++;
        });

        // Show an error message if fields are missing
        if (jQuery('.form-error').length > 0) {
            var first = missing == 1 ? 'field' : 'fields';
            var second = missing == 1 ? 'is' : 'are';

            alert(missing + ' required ' + first + ' ' + second + ' missing.');
        } else {
            // Send request via AJAX
            jQuery.ajax({
                type: 'POST',
                url: WT.ajaxURL,
                data: 'action=wootax-verify-address'+ address_str +'&wootax_tc_id=' + loginID + '&wootax_tc_key=' + apiKey + '&wootax_usps_id=' + uspsID,
                success: function(resp) {
                    resp = eval('(' + resp + ')');

                    if (resp != false && resp != null) {
                        if (resp.status == 'success') {
                            var i = 0;

                            jQuery(resp.message).each(function() {
                                var row = jQuery('#address_table tbody tr').eq(i);

                                row.find('.wootax_address1').val(this.address_1);
                                row.find('.wootax_address2').val(this.address_2);
                                row.find('.wootax_city').val(this.city);
                                row.find('.wootax_zip5').val(this.zip5);
                                row.find('.wootax_zip4').val(this.zip4);

                                i++;
                            });

                            alert('Addresses verified successfully. Don\'t forget to save changes.');
                        } else {
                            if (resp.status == 'error') {
                                alert('An error occurred. TaxCloud said: ' + resp.message);
                            } else {
                                alert('An error prevented WooTax from verifying your business address(es). Please try again.');
                            }
                        }
                    } else {
                        alert('An error prevented WooTax from verifying your business address(es). Please try again.');
                    }
                }
            });
        }

    });

    // Reset settings
    jQuery('#resetSettings').click(function(e) {

        // Verify that user wants to delete settings
        var msg = 'Are you sure you want to clear your settings? This action is not reversible.';

        if (confirm(msg)) {
            jQuery.ajax({
                type: 'POST',
                url: WT.ajaxURL,
                data: 'action=wootax-clear-settings',
                success: function(resp) {
                    resp = eval('(' + resp + ')');
                    if (resp.status == 'success') {
                        window.location.reload();
                    } else {
                        alert('Error: ' + resp);
                    }
                }
            });
        }

        // Stop page jitter
        e.preventDefault();
    });

    // Uninstall WooTax
    jQuery( '#wootax_uninstall' ).click( function( e ) {

        // Stop page jitter
        e.preventDefault();

        // Verify user action
        var msg = 'Are you sure you want to uninstall WooTax? All of your settings will be erased.';

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
                        alert( 'WooTax was uninstalled successfully. The page will now reload.' );
                        
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

    // Set cookie function
    function setCookie(cname, cvalue, exdays) {

        var d = new Date();
        d.setTime(d.getTime() + (exdays * 24 * 60 * 60 * 1000));

        var expires = "expires=" + d.toGMTString();

        document.cookie = cname + "=" + cvalue + "; " + expires;

    }

    // Get cookie function
    function getCookie(cname) {

        var name = cname + "=";
        var ca = document.cookie.split(';');

        for (var i = 0; i < ca.length; i++) {
            var c = ca[i].trim();
            if (c.indexOf(name) == 0) return c.substring(name.length, c.length);
        }

        return "";

    }

    // Remove product TIC
    jQuery('#wootax-remove-tic').click(function(e) {

        // Reset
        jQuery(this).parent('span').html('Using Site Default');
        jQuery('input[name="wootax_tic"], input[name="wootax_tic_desc"]').val('');

        // Notify the user they must save
        alert('The TIC for this product has been reset. Please remember to save your changes.');

        // Prevent page movement
        e.preventDefault();

    });

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
            alert('This action is not permitted. You must have at least one business address entered for WooTax to work properly.');
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
     * Initialize tipTip on multivendor settings pages
     */
    if ( jQuery('.wootax-vendor-settings').length == 1 ) {
        jQuery(".tips, .help_tip").tipTip({
            'attribute' : 'data-tip',
            'fadeIn' : 50,
            'fadeOut' : 50,
            'delay' : 200
        });
    }

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