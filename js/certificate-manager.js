/**
 * Certificate management script
 * Inspired by the Certificate Management utility produced by TaxCloud
 */

// Global vars
var certObj;
var tcsURL = "taxcloud.net";
var tcsProtocol = (("https:" == document.location.protocol) ? "https:" : "http:");
var clickMe = '#' + clickTarget;

// Determines which window we should display when the target element is clicked 
function certificateInit() {

    jQuery(clickMe).unbind("click");

    if (certObj.length > 0) {
        jQuery(clickMe).click(function() {
            jQuery.magnificPopup.open({
                items: {
                    src: lbPath + '/manage-certificates.php?pluginPath=' + pluginPath
                },
                type: 'iframe',
                mainClass: 'mfp-fade'
            });

            return false;
        });
    } else {
        jQuery(clickMe).click(function() {
            jQuery.magnificPopup.open({
                items: {
                    src: lbPath + '/add-certificate.php?pluginPath=' + pluginPath
                },
                type: 'iframe',
                mainClass: 'mfp-fade'
            });

            return false;
        });
    }

}

/**
 * Fetches the latest certificates
 * Certificates returned as JSON object
 */
function fetchCertificates(callback) {

    var date = new Date();

    jQuery.ajax({
        type: 'POST',
        url: listURL,
        data: 'action=wootax-list-certificates',
        success: function(resp) {
            var certs = eval('(' + resp + ')');
            certObj = certs.cert_list;

            // Bind "Are you exempt?" link
            if (callback != null) {
                window[callback]();
            }
        }
    });

}

/**
 * Applys a certificate to the current purchase
 */
function applyCertificate() {

    // Close popup
    jQuery.magnificPopup.close();

    // Show the "applied certificate" readout
    jQuery('#wooTaxApplied').fadeIn('fast');

    // Trigger update of totals
    jQuery('body').trigger('update_checkout');

}

/**
 * Stores information about the certificate to be applied to the session before calling applyCertificate
 */
function setCertificate(cb, cert) {

    jQuery.ajax({
        type: 'POST',
        url: saveURL,
        data: 'action=wootax-update-certificate&act=set&cert=' + cert,
        success: function(resp) {
            if (resp == true) {
                if (cb != null) {
                    window[cb]();
                }
            } else {
                alert(resp);
            }
        }
    });

}

/**
 * Switches the lightbox that is currently being viewed
 * If we are switching to the "manage-certificates" screen, we will also call refreshCertificates to fetch the latest certificates
 */
function switchView(lb) {

    // Close current lightbox
    jQuery.magnificPopup.close();

    // Open new lightbox
    jQuery.magnificPopup.open({
        items: {
            src: lbPath + '/' + lb + '?pluginPath=' + pluginPath,
        },
        type: 'iframe',
        class: 'mfp-fade'
    });

}

/**
 * Trigger update of cart totals
 */
function trigger_update_totals() {
    jQuery('body').trigger('update_checkout')
}

/**
 * Remove certificate when #removeCert is clicked
 */
jQuery('#removeCert').click(function() {

    // Apply empty certificate
    setCertificate('trigger_update_totals', '');

    // Hide the "applied certificate" readout
    jQuery('#wooTaxApplied').fadeOut('fast');

    return false;

});

/**
 * Load certificates on page load if ajaxLoad set to true
 */
jQuery(window).ready(function() {
    
    if (ajaxLoad == true) {
        // Fetch certificates
        fetchCertificates('certificateInit');
    }
    
});