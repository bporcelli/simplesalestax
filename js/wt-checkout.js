/**
 * Scripts for the checkout and order pages
 */

jQuery(function() {
    
    // Mark the "Sales Tax" row with the ".tax-total" class so it is hidden on the order review page
    if (jQuery('table.order_details').length != 0) {

        jQuery('table.order_details tr').each(function() {
            var text = jQuery(this).find('th').text().trim();

            if (text == 'Sales Tax' || text == 'Sales Tax:') {
                jQuery(this).addClass('tax-total');
            }
        });

    }

    // Make sure that tax is calculated on page load
    jQuery(document).load(function() {
       // jQuery('body').trigger('updated_checkout');
    });
    
});