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
                url: MyAjax.ajaxURL,
                data: 'action=wootax-verify-taxcloud&wootax_tc_id=' + loginID + '&wootax_tc_key=' + apiKey,
                success: function(resp) {
                    if (resp == 1) {
                        alert('Success! We were able to connect to TaxCloud using these settings.');
                    } else {
                        alert('Connection to TaxCloud failed. Please verify that your API credentials are correct and try again. TaxCloud error description: ' + resp);
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
            url: MyAjax.ajaxURL,
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
                url: MyAjax.ajaxURL,
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
                url: MyAjax.ajaxURL,
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

    // Deactivate license on this domain
    jQuery('#deactivateLicense').click(function(e) {

        // Verify user action
        var msg = 'Are you sure you want to deactivate your license? You will have to enter a valid license key to use WooTax on this site again.';

        if (confirm(msg)) {
            jQuery.ajax({
                type: 'POST',
                url: MyAjax.ajaxURL,
                data: 'action=wootax-deactivate-license',
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

    // Get information about item quantities
    /*var originalQuantities = [];

    function getOriginalQuantities() {

        // Reset array
        orderQuantities = [];
        var orderTable = jQuery('table.woocommerce_order_items');

        // Collect quantity values in array
        orderTable.find('.item').each(function() {
            var $item = jQuery(this);
            // Fetch item ID
            var id = $item.data('order_item_id');
            // Fetch quantity
            var qty = $item.find('.quantity').find('.view').text();
            // Push to quantity array
            originalQuantities.push({
                id: id,
                qty: qty
            });
        });

    }

    // Get original quantity for a given item
    function getItemQuantity(id) {

        var qty = 1;

        for (var i = 0; i < originalQuantities.length; i++) {
            if (originalQuantities[i].id == id)
                qty = originalQuantities[i].qty == null ? 1 : originalQuantities[i].qty;
        }

        return qty;

    }

    // Collect information about the original item quantities upon page load
    jQuery(window).load(function() {
        getOriginalQuantities();
    });

    // Collect information about the items currently added to the order
    function getOrderItems() {

        // Initialize array
        var orderItems = [];
        var orderTable = jQuery('table.woocommerce_order_items');
        var orderTotals = jQuery('#woocommerce-order-totals');

        // Add cart items
        orderTable.find('.item').each(function() {
            var $item = jQuery(this);

            // Fetch item ID
            var id = $item.data('order_item_id');

            // Fetch quantity
            var qty = $item.find('input.quantity').val();

            // Fetch total
            var total = $item.find('input.line_total').val();

            // Push values to array
            if (id != null && id != '' && !(typeof id == 'undefined')) {
                orderItems.push({
                    type: 'cart',
                    id: id,
                    qty: qty,
                    total: total
                });
            }
        });

        // Add fees
        orderTable.find('.fee').each(function() {
            var $item = jQuery(this);

            // Fetch item ID
            var id = $item.data('order_item_id');

            // Fetch fee title
            var title = $item.find('.name input').length > 0 ? $item.find('.name input').eq(0).val() : $item.find('.name .view').text();

            // Fetch total
            var total = $item.find('input.line_total').val();

            // Fetch tax class
            var taxClass = $item.find('.tax_class div.view').text().toLowerCase();

            // Push values to array
            if (id != null && id != '' && !(typeof id == 'undefined')) {
                orderItems.push({
                    type: 'fee',
                    id: id,
                    qty: 1,
                    tax_class: taxClass,
                    total: total,
                    title: title,
                });
            }
        });*/

        /**
         * Add shipping methods
         * Note: method varies according to version of WooCommerce
         */

        // WooCommerce 2.2 only
        /*if (MyAjax.woo22) {
            orderTable.find('.shipping').each(function() {
                var $item = jQuery(this);

                // Fetch item ID
                var id = $item.data('order_item_id');

                // Fetch quantity
                var qty = $item.find('input.quantity').val();

                // Fetch total
                var total = $item.find('input.line_total').val();
                
                // Push values to array
                if (id != null && id != '' && !(typeof id == 'undefined'))
                    orderItems.push({
                        type: 'shipping',
                        id: id,
                        qty: qty,
                        total: total
                    });
            });
        } else {
            // Legacy versions of Woo
            if (jQuery('#woocommerce-order-totals').find('.totals_group').eq(1).find('ul.totals').length != 0) {
                var $item = jQuery('#woocommerce-order-totals').find('.totals_group').eq(1).find('ul.totals');
                // Fetch id
                var id = 'shipping';
                // Fetch price
                var total = $item.find('#_order_shipping').val();
                // Push value to array
                orderItems.push({
                    type: 'shipping',
                    id: id,
                    qty: 1,
                    total: total
                });

            // Woo 2.1 - 2.1.12
            } else {
                orderTotals.find('#shipping_rows').find('.shipping_row').each(function() {
                    var $item = jQuery(this);
                    // Fetch item ID
                    var id = $item.data('order_item_id') == null ? 'new_shipping_method' : $item.data('order_item_id');
                    // Fetch price
                    var total = $item.find('.shipping_cost').val();
                    // Push values to array
                    orderItems.push({
                        type: 'shipping',
                        id: id,
                        qty: 1,
                        total: total
                    });
                });
            }

        }

        // Convert array into data that can be sent to the server
        var orderString = '';

        if (orderItems.length != 0) {
            for (var i = 0; i < orderItems.length; i++) {
                orderString += '&type_' + i + '=' + orderItems[i].type + '&id_' + i + '=' + orderItems[i].id + '&qty_' + i + '=' + orderItems[i].qty + '&total_' + i + '=' + orderItems[i].total;

                // Add extra fields if we are dealing with a fee
                if (orderItems[i].type == 'fee') {
                    orderString += '&class_' + i + '=' + orderItems[i].tax_class + '&title_' + i + '=' + orderItems[i].title;
                }
            }

            // Append a parameter indicating the number of items sent
            orderString += '&itemNum=' + i;
        }

        return orderString;

    }

    // Fetch shipping method names
    function getShippingMethods() {

        // Initialize some vars
        var orderTotals = jQuery('#woocommerce-order-totals');
        var shippingMethods = [];
        var methodString = '&shipping_methods=';

        // Loop through shipping items and pull names
        if (jQuery('#woocommerce-order-totals').find('.totals_group').eq(1).find('ul.totals').length != 0) {
            // WC 2.0
            var $item = jQuery('#woocommerce-order-totals').find('.totals_group').eq(1).find('ul.totals');
            // Fetch method name
            var name = $item.find('select').find('option:selected').val();
            // Push name to array
            shippingMethods.push(name);
        } else {
            // WC 2.1
            orderTotals.find('#shipping_rows').find('.shipping_row').each(function() {
                var $item = jQuery(this);
                // Fetch method name
                var name = $item.find('select').find('option:selected').val();
                // Push name to array
                shippingMethods.push(name);
            });
        }

        // Convert array to string
        for (var i = 0; i < shippingMethods.length; i++) {
            methodString += shippingMethods[i] + ',';
        }

        // Trim string
        methodString = methodString.substr(0, methodString.length - 1);

        return methodString;

    }

    // Fetch order discount
    // This might get tricky when it comes to performing lookups -- discount has to be distributed evenly amongst items (if it isn't already)
    function getOrderDiscount() {
        return jQuery('#_order_discount').val();
    }

    // Fetch orderID
    function getOrderID() {
        return parseInt(MyAjax.orderID);
    }

    // Update tax values given array of calculated tax values
    function updateOrderTaxes(taxes) {

        taxes = eval('(' + taxes + ')');

        // Fetch tax item id from the end of the array
        var taxID = MyAjax.taxItemID;

        // We want to total the taxes as we loop through them
        var taxTotal = 0;
        var shippingTaxTotal = 0;

        // Update front end to display new tax values
        for (var i = 0; i < taxes.length; i++) {
            var type = taxes[i].type;
            var tax = taxes[i].tax;
            var old = taxes[i].oldtax;
            var id = taxes[i].id;

            if (type == 'cart' || MyAjax.woo22 && type == 'shipping') {
                // Fetch correct item
                var $itemRow = jQuery('tr[data-order_item_id="' + id + '"]');

                // Fetch current tax values
                var currentSubTax = $itemRow.find('input.line_subtotal_tax').val();
                var currentTax = $itemRow.find('input.line_tax').val();

                // Correct "old" value for changes in quantity
                old = ($itemRow.find('input.quantity').val() / getItemQuantity(id)) * old;
                old = typeof old == 'undefined' ? 0 : old;

                // Calculate new tax values
                var newSubTax = (currentSubTax != '' && currentSubTax >= old ? currentSubTax - old : 0) + tax;
                var newTax = (currentTax != '' && currentTax >= old ? currentTax - old : 0) + tax;

                // Update fields with new value
                $itemRow.find('input.line_subtotal_tax').val(newSubTax).data('subtotal_tax', newSubTax);
                $itemRow.find('input.line_tax').val(newTax).data('total_tax', newTax);
                $itemRow.find('.line_tax').find('.amount').text('$' + newTax.toFixed(2));
                $itemRow.find('.line_subtotal_tax').find('.amount').text('$' + newSubTax.toFixed(2));

                // Add to tax total
                taxTotal += tax;
            } else if (type == 'fee') {
                // Fetch correct item
                var $itemRow = jQuery('tr[data-order_item_id="' + id + '"]');

                // Fetch current tax values
                var currentTax = $itemRow.find('.line_tax').val();

                // Calculate new tax values
                var newTax = (currentTax <= old ? currentTax - old : 0) + tax;

                // Update fields with new value
                $itemRow.find('input.line_tax').val(newTax);
                $itemRow.find('.line_tax').find('.amount').text('$' + newTax.toFixed(2));

                // Add to tax total
                taxTotal += tax;
            } else {
                // Add to shipping tax total
                shippingTaxTotal += tax;
            }
        }

        // Fetch tax item (if possible)
        var $taxItem = jQuery('#tax_rows').find('.tax_row[data-order_item_id="' + taxID + '"]');

        // Fetch old tax totals (older versions of WooCommerce)
        if (jQuery('#_order_tax').length == 1) {
            // Fetch old tax values
            var oldTaxTotal = $taxItem.length != 0 ? $taxItem.find('input[type="number"]').eq(0).val() : 0;
            var oldShippingTaxTotal = $taxItem.length != 0 ? $taxItem.find('input[type="number"]').eq(1).val() : 0;
        }

        // Calculate total tax added by WooTax (limit precision to 2 decimal places and round up, TaxCloud style)
        var raw_tax_total = taxTotal + shippingTaxTotal;
        var wootax_tax_total = raw_tax_total.toFixed(2);

        // Update WooTax tax total
        jQuery('#sales_tax_meta').find('.amount').text('$' + wootax_tax_total);

        // Update the tax row
        if (taxID != '' && taxID != 0) {

            if (MyAjax.woo22 == true) {
                // WooCommerce 2.2+
                alert('The tax for this order has been calculated successfully. Your changes will now be saved.');
            
                // Search for tax row and update it's amount
                jQuery('.wc-order-totals tbody tr').each(function() {
                    if (jQuery(this).find('.label').text().replace(':', '') == 'Sales Tax') 
                        jQuery(this).find('.amount').text('$' + wootax_tax_total);
                });

                // Save updated tax values
                jQuery('.save-action').trigger('click');
                jQuery('.calculate-action').trigger('click');
            } else if ($taxItem.length > 0) {
                // WooCommerce 2.0.2 - 2.1.12
                alert('The tax for this order has been calculated successfully. You will now be prompted to recalculate the total for this order. Afterwards, you should save this order to avoid losing your changes.');
                
                // Identify the correct fields to update (varies depending on version of Woo we are dealing with)
                var $taxTotal = $taxItem.find('input[type="number"]').length != 0 ? $taxItem.find('input[type="number"]').eq(0) : $taxItem.find('input.order_taxes_amount');
                var $shippingTaxTotal = $taxItem.find('input[type="number"]').length != 0 ? $taxItem.find('input[type="number"]').eq(1) : $taxItem.find('input.order_taxes_shipping_amount');
                
                // Update total fields for tax row
                $taxTotal.val(taxTotal.toFixed(2));
                $shippingTaxTotal.val(shippingTaxTotal.toFixed(2));

                // Calculate new order tax total
                var currentTaxTotal = jQuery('#_order_tax').val();
                var newTaxTotal = currentTaxTotal == 0 ? taxTotal : (currentTaxTotal - oldTaxTotal) + taxTotal;

                // Update order tax total
                jQuery('#_order_tax').val(newTaxTotal.toFixed(2));

                // Calculate new order shipping tax total
                var currentShippingTaxTotal = jQuery('#_order_shipping_tax').val();
                var newShippingTaxTotal = currentShippingTaxTotal == 0 ? shippingTaxTotal : (currentShippingTaxTotal - oldShippingTaxTotal) + shippingTaxTotal;

                // Update shipping tax total
                jQuery('#_order_shipping_tax').val(newShippingTaxTotal.toFixed(2));

                // Trigger calculation of totals
                jQuery('.calc_totals').trigger('click');
            } else {
                // Alert the user
                alert('The tax for this order has been calculated successfully. The page will now refresh.');
                
                // Set cookie such that totals are re-calculated after page reloads
                setCookie('wootax_recalculate_totals', true, 1);
                
                // Trigger save so that the new tax row is displayed
                jQuery('.save_order').trigger('click');
            }

        } else {
            // Alert the user
            alert('The tax for this order has been calculated successfully. The page will now refresh.');
            
            // Set cookie such that totals are re-calculated after page reloads
            setCookie('wootax_recalculate_totals', true, 1);
            
            // Trigger save so that the new tax row is displayed
            jQuery('.save_order').trigger('click');
        }

    }*/

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

    // Check if totals should be re-calculated on page load
    /*if (jQuery('.woocommerce_order_items').length != 0) {

        jQuery(window).ready(function() {
            if ( getCookie( 'wootax_recalculate_totals' ) == true || getCookie( 'wootax_recalculate_totals' ) == 'true' ) {
                // Force calculation of tax again for Woo22
                if ( MyAjax.woo22 ) {
                    // Calculate taxes
                    jQuery('#calculateTax').trigger('click');

                    // Reset cookie
                    setCookie( 'wootax_recalculate_totals', false, 1 );
                } else {
                    // Alert user
                    alert('Since you just calculated the tax for this order, you will now be prompted to re-calculate order totals. After doing so, save the order to update the total.');
                    
                    // Trigger calculation of totals
                    jQuery('.calc_totals').trigger('click');

                    // Reset cookie
                    setCookie( 'wootax_recalculate_totals', false, 1 );
                }
            }
        });

    }

    // Get shipping address for lookup requests
    function getShippingAddress() {

        var shipping_address_1 = jQuery('#_shipping_address_1').val();
        var shipping_address_2 = jQuery('#_shipping_address_2').val();
        var shipping_country = jQuery('#_shipping_country').find('option:selected').val();
        var shipping_state = jQuery('#_shipping_state').val();
        var shipping_city = jQuery('#_shipping_city').val();
        var shipping_postcode = jQuery('#_shipping_postcode').val();

        // Return formatted for query
        return '&shipping_address_1=' + shipping_address_1 + '&shipping_address_2=' + shipping_address_2 + '&shipping_country=' + shipping_country + '&shipping_state=' + shipping_state + '&shipping_city=' + shipping_city + '&shipping_postcode=' + shipping_postcode;

    }

    // Get billing address for lookup requests
    function getBillingAddress() {

        var billing_address_1 = jQuery('#_billing_address_1').val();
        var billing_address_2 = jQuery('#_billing_address_2').val();
        var billing_country = jQuery('#_billing_country').find('option:selected').val();
        var billing_state = jQuery('#_billing_state').val();
        var billing_city = jQuery('#_billing_city').val();
        var billing_postcode = jQuery('#_billing_postcode').val();

        // Return formatted for query
        return '&billing_address_1=' + billing_address_1 + '&billing_address_2=' + billing_address_2 + '&billing_country=' + billing_country + '&billing_state=' + billing_state + '&billing_city=' + billing_city + '&billing_postcode=' + billing_postcode;

    }

    // Calculate tax on demand
    jQuery('#calculateTax').click(function(e) {

        // Show loader
        jQuery('#wooLoader').show();

        // Force "edit mode" on products so user can see the fields being edited (2.2+)
        if (MyAjax.woo22) {
            if (MyAjax.woo22) {
                jQuery('.woocommerce_order_items tr.item, .woocommerce_order_items tr.shipping, .woocommerce_order_items tr.fee').each(function() {
                    jQuery(this).find('.edit-order-item').trigger('click');
                });
            }
        } 

        // Send request to calculate tax amounts
        var items = getOrderItems();
        if (items != '') {
            jQuery.ajax({
                type: 'POST',
                url: MyAjax.ajaxURL,
                data: 'action=wootax-update-tax' + items + '&discount=' + getOrderDiscount() + '&orderID=' + getOrderID() + getShippingAddress() + getBillingAddress() + getShippingMethods(),
                success: function(resp) {
                    resp = eval('(' + resp + ')');
                    if (resp.status == 'success') {
                        updateOrderTaxes(resp.message);
                    } else if (resp.status == 'error') {
                        alert(resp.message);
                    } else {
                        alert(resp);
                    }
                    // Hide loader
                    jQuery('#wooLoader').hide();
                }
            });
        } else {
            alert('You have not added any items to this order add. Please add at least one item and try again.');
            // Hide loader
            jQuery('#wooLoader').hide();
        }
        
        // Stop page jitter
        e.preventDefault();

    });*/

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
                url: MyAjax.ajaxURL,
                data: 'action=wootax-delete-rates&rates='+ toRemove.join(),
                success: function(resp) {
                    if (resp == true) {
                        jQuery(toRemove).each(function() {
                            $table.find('input[data-val="'+ this +'"]').attr('checked', false).closest('tr').find('td').eq(1).text('0');
                        });

                        $table.find('.check_column input').trigger('click');

                        alert('The selected tax rates were removed successfully. Click next to complete the installation process.');
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
                jQuery('#wootax_form table.widefat tbody tr').eq(0).find('input[name="wootax_default_address"]').attr('checked', 'checked');

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
});

var currentTic = '';
var fieldID = 'wootax_set_tic';