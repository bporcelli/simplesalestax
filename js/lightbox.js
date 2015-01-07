var tcsURL = "taxcloud.net";
var tcsProtocol = (("https:" == document.location.protocol) ? "https:" : "http:");
var useBlanket = window.parent.useBlanket;
var saveURL = window.parent.saveURL;
var listURL = window.parent.listURL;

function saveCertificate() {
    jQuery('#feedback').slideUp(function() {
        jQuery(this).removeClass('errorMsg').removeClass('successMsg');
        jQuery(this).text('')
    });
    jQuery.ajax({
        type: 'POST',
        url: saveURL,
        data: jQuery('form').serialize(),
        success: function(resp) {
            jQuery('#loader').hide();
            resp = eval('(' + resp + ')');
            if (resp.status == 'error') {
                jQuery('#feedback').addClass('errorMsg').text(resp.message).slideDown();
                window.location.href = '#feedback'
            } else {
                if (jQuery('[name="SinglePurchase"]').val() == 'false') {
                    window.parent.switchView('manage-certificates.php')
                } else {
                    window.parent.setCertificate('applyCertificate', true)
                }
            }
        }
    });
    parent.certificateInit()
}

function removeCertificate(certID, single) {
    jQuery('#loader').show();
    jQuery('#feedback').slideUp(function() {
        jQuery(this).removeClass('errorMsg').removeClass('successMsg');
        jQuery(this).text('')
    });
    jQuery.ajax({
        type: 'POST',
        url: saveURL,
        data: 'certificateID=' + certID + '&single=' + single + '&act=remove&action=wootax-update-certificate',
        success: function(resp) {
            resp = eval('(' + resp + ')');
            if (resp.status == 'error') {
                jQuery('#feedback').addClass('errorMsg').text(resp.message).slideDown();
                window.location.href = '#feedback'
            } else {
                jQuery('#' + certID).fadeOut('fast', function() {
                    jQuery(this).remove();
                    if (jQuery('tr').length == 0) {
                        jQuery('#manageCertificates').hide();
                        jQuery('#addCertificateSection').fadeIn('fast')
                    }
                });
                var count = parseInt(jQuery('#certCount').text()) - 1;
                jQuery('#certCount').text(count);
                jQuery('#feedback').addClass('successMsg').text(resp.message).slideDown();
                window.location.href = '#feedback'
            }
            jQuery('#loader').hide()
        }
    })
}

function parseDate(datestr) {
    var yy = datestr.substring(0, 4);
    var mo = datestr.substring(5, 7);
    var dd = datestr.substring(8, 10);
    var hh = datestr.substring(11, 13);
    var mi = datestr.substring(14, 16);
    var ss = datestr.substring(17, 19);
    var tzs = datestr.substring(19, 20);
    var tzhh = datestr.substring(20, 22);
    var tzmi = datestr.substring(23, 25);
    var myutc = Date.UTC(yy - 0, mo - 1, dd - 0, hh - 0, mi - 0, ss - 0);
    var tzos = (tzs + (tzhh * 60 + tzmi * 1)) * 60000;
    return new Date(myutc - tzos)
}

function getOptions(cert) {
    var certID = cert.CertificateID;
    var out = '';
    out += '<button class="grey removeButton smallButton' + (cert.Detail.SinglePurchase == true ? ' single' : '') + '" type="button" class="removeCert" title="Remove Certificate ' + certID + '">Remove</button>';
    out += '<button class="grey viewButton smallButton" type="button" class="viewCert" title="View Certificate ' + certID + '">View</button>';
    if (cert.Detail.SinglePurchase != true) out += '<button type="button" class="useCert smallButton" title="Use Certificate ' + certID + '">Use</button>';
    return out
}

function buildCertDisplay() {
    jQuery('#certs').html('');
    var URL = pluginPath;
    var certObj = window.parent.certObj;
    var newHTML = '';
    for (var i = 0; i < certObj.length; i++) {
        var date = parseDate(certObj[i].Detail.CreatedDate);
        newHTML += '<tr id="' + certObj[i].CertificateID + '" data-ind="' + i + '" class="certificateWrap">';
        newHTML += '<td valign="middle" align="center" width="160"><img width="150" height="120" src="' + (certObj[i].Detail.SinglePurchase == false ? URL + 'img/exemption_certificate150x120.png' : URL + 'img/sp_exemption_certificate_150x120.png') + '" /></td>';
        newHTML += '<td valign="middle" width="340">Issued To: ' + certObj[i].Detail.PurchaserFirstName + ' ' + certObj[i].Detail.PurchaserLastName + '<br /> Exempt State(s): ' + certObj[i].Detail.ExemptStates.ExemptState.StateAbbr + '<br /> Date: ' + (parseInt(date.getMonth()) + 1) + '/' + date.getDate() + '/' + date.getFullYear() + '<br /> Purpose: ' + pretty(certObj[i].Detail.PurchaserExemptionReason) + '<br />' + getOptions(certObj[i]) + '</td>';
        newHTML += '</tr>'
    }
    jQuery('#certCount').text(certObj.length);
    jQuery('#certs').html(newHTML)
}

function loadCertificates() {
    var certs = parent.certObj;
    if (certs.length > 0 && certs[0] != null) {
        buildCertDisplay();
        jQuery('#loading').hide();
        jQuery('#manageCertificates').show()
    } else {
        jQuery('#loading').hide();
        jQuery('#addCertificateSection').show()
    }
}

function previewCertificate(certIndex) {
    parent.jQuery.magnificPopup.open({
        'items': {
            src: window.parent.lbPath + '/preview-certificate.php?pluginPath=' + pluginPath + '&certIndex=' + certIndex + '&company=' + window.parent.merchantName
        },
        'type': 'iframe',
        'class': 'mfp-fade'
    })
}

function loadPreview() {
    var certArr = jQuery.map(parent.certObj, function(value, key) {
        return value
    });

    var cert = certArr[certInd];
    var date = parseDate(cert.Detail.CreatedDate);

    jQuery('.certID').text(cert.CertificateID);

    if (cert.Detail.SinglePurchase == true) {
        jQuery('#certificatePreview').css('background-image', 'url(' + pluginPath + 'img/sp_exemption_certificate_750x600.png)')
    }

    jQuery('#PurchaserName').text(cert.Detail.PurchaserFirstName + ' ' + cert.Detail.PurchaserLastName);
    jQuery('#PurchaserAddress').text(cert.Detail.PurchaserAddress1 + ', ' + cert.Detail.PurchaserCity + ', ' + cert.Detail.PurchaserState + ' ' + cert.Detail.PurchaserZip);
    jQuery('#PurchaserState').text(pretty(cert.Detail.PurchaserState));
    jQuery('#PurchaserExemptionReason').text(pretty(cert.Detail.PurchaserExemptionReason) + ' : ' + cert.Detail.PurchaserExemptionReasonValue);
    jQuery('#Date').text((parseInt(date.getMonth()) + 1) + '/' + date.getDate() + '/' + date.getFullYear());
    jQuery('#TaxType').text(cert.Detail.PurchaserTaxID.TaxType);
    jQuery('#IDNumber').text(cert.Detail.PurchaserTaxID.IDNumber);

    var bType = pretty(cert.Detail.PurchaserBusinessType);
    jQuery('#PurchaserBusinessType').text(bType.length > 20 ? bType.substr(0, 20) + '...' : bType);

    var mName = merchantName.length > 20 ? merchantName.substr(0, 20) + '...' : merchantName;
    jQuery('#MerchantName').text(mName);

    if (cert.Detail.SinglePurchaseOrderNumber != '') {
        jQuery('#OrderID').text(cert.Detail.SinglePurchaseOrderNumber.substr(0, 20) + '...');
    }
}

function fetchCertificates(callback) {
    var date = new Date();
    jQuery('#loading').show();
    jQuery.ajax({
        type: 'POST',
        url: listURL + '?t=' + date.getTime(),
        data: 'action=wootax-list-certificates',
        success: function(resp) {
            var certs = eval('(' + resp + ')');
            parent.certObj = certs.cert_list;
            if (callback != null) {
                window[callback]()
            }
        }
    })
}

function pretty(keyWord) {
    switch (keyWord) {
        case "AccommodationAndFoodServices":
            keyWord = "Accommodation and Food Services";
            break;
        case "Agricultural_Forestry_Fishing_Hunting":
            keyWord = "Agricultural/Forestry/Fishing/Hunting";
            break;
        case "Construction":
            break;
        case "FinanceAndInsurance":
            keyWord = "Finance and Insurance";
            break;
        case "Information_PublishingAndCommunications":
            keyWord = "Information Publishing and Communications";
            break;
        case "Manufacturing":
            break;
        case "Mining":
            break;
        case "RealEstate":
            keyWord = "Real Estate";
            break;
        case "RentalAndLeasing":
            keyWord = "Rental and Leasing";
            break;
        case "RetailTrade":
            keyWord = "Retail Trade";
            break;
        case "TransportationAndWarehousing":
            keyWord = "Transportation and Warehousing";
            break;
        case "Utilities":
            break;
        case "WholesaleTrade":
            keyWord = "Wholesale Trade";
            break;
        case "BusinessServices":
            keyWord = "Business Services";
            break;
        case "ProfessionalServices":
            keyWord = "Professional Services";
            break;
        case "EducationAndHealthCareServices":
            keyWord = "Education and Health Care Services";
            break;
        case "NonprofitOrganization":
            keyWord = "Nonprofit Organization";
            break;
        case "Government":
            break;
        case "NotABusiness":
            keyWord = "Not a Business";
            break;
        case "FederalGovernmentDepartment":
            keyWord = "Federal Government Department";
            break;
        case "StateOrLocalGovernmentName":
            keyWord = "State or Local Government";
            break;
        case "TribalGovernmentName":
            keyWord = "Tribal Government";
            break;
        case "ForeignDiplomat":
            keyWord = "Foreign Diplomat";
            break;
        case "CharitableOrganization":
            keyWord = "Charitable Organization";
            break;
        case "ReligiousOrEducationalOrganization":
            keyWord = "Religious or Educational Organization";
            break;
        case "Resale":
            break;
        case "AgriculturalProduction":
            keyWord = "Agricultural Production";
            break;
        case "IndustrialProductionOrManufacturing":
            keyWord = "Industrial Production or Manufacturing";
            break;
        case "DirectPayPermit":
            keyWord = "Direct Pay Permit";
            break;
        case "DirectMail":
            keyWord = "Direct Mail";
            break;
        case "Other":
            break;
        case "DirectMail":
            keyWord = "Direct Mail";
            break;
        case "AL":
            keyWord = "Alabama";
            break;
        case "AK":
            keyWord = "Alaska";
            break;
        case "AZ":
            keyWord = "Arizona";
            break;
        case "AR":
            keyWord = "Arkansas";
            break;
        case "CA":
            keyWord = "California";
            break;
        case "CO":
            keyWord = "Colorado";
            break;
        case "CT":
            keyWord = "Connecticut";
            break;
        case "DE":
            keyWord = "Delaware";
            break;
        case "FL":
            keyWord = "Florida";
            break;
        case "GA":
            keyWord = "Georgia";
            break;
        case "HI":
            keyWord = "Hawaii";
            break;
        case "ID":
            keyWord = "Idaho";
            break;
        case "IL":
            keyWord = "Illinois";
            break;
        case "IN":
            keyWord = "Indiana";
            break;
        case "IA":
            keyWord = "Iowa";
            break;
        case "KS":
            keyWord = "Kansas";
            break;
        case "KY":
            keyWord = "Kentucky";
            break;
        case "LA":
            keyWord = "Louisiana";
            break;
        case "ME":
            keyWord = "Maine";
            break;
        case "MD":
            keyWord = "Maryland";
            break;
        case "MA":
            keyWord = "Massachusetts";
            break;
        case "MI":
            keyWord = "Michigan";
            break;
        case "MN":
            keyWord = "Minnesota";
            break;
        case "MS":
            keyWord = "Mississippi";
            break;
        case "MO":
            keyWord = "Missouri";
            break;
        case "MT":
            keyWord = "Montana";
            break;
        case "NE":
            keyWord = "Nebraska";
            break;
        case "NV":
            keyWord = "Nevada";
            break;
        case "NH":
            keyWord = "New Hampshire";
            break;
        case "NJ":
            keyWord = "New Jersey";
            break;
        case "NM":
            keyWord = "New Mexico";
            break;
        case "NY":
            keyWord = "New York";
            break;
        case "NC":
            keyWord = "North Carolina";
            break;
        case "ND":
            keyWord = "North Dakota";
            break;
        case "OH":
            keyWord = "Ohio";
            break;
        case "OK":
            keyWord = "Oklahoma";
            break;
        case "OR":
            keyWord = "Oregon";
            break;
        case "PA":
            keyWord = "Pennsylvania";
            break;
        case "RI":
            keyWord = "Rhode Island";
            break;
        case "SC":
            keyWord = "South Carolina";
            break;
        case "SD":
            keyWord = "South Dakota";
            break;
        case "TN":
            keyWord = "Tennessee";
            break;
        case "TX":
            keyWord = "Texas";
            break;
        case "UT":
            keyWord = "Utah";
            break;
        case "VT":
            keyWord = "Vermont";
            break;
        case "VA":
            keyWord = "Virginia";
            break;
        case "WA":
            keyWord = "Washington";
            break;
        case "DC":
            keyWord = "Washington DC";
            break;
        case "WV":
            keyWord = "West Virginia";
            break;
        case "WI":
            keyWord = "Wisconsin";
            break;
        case "WY":
            keyWord = "Wyoming";
            break
    }
    return keyWord
}

jQuery(function() {
    jQuery('#certificateType').change(function() {
        var val = jQuery(this).find('option:selected').attr('data-text');
        jQuery('#certExpl').html('<em>' + val + '</em>');
    });

    jQuery('select').change(function() {
        var toShow = jQuery(this).attr('data-show-class');
        if (typeof toShow != 'undefined' && toShow != '') {
            var val = jQuery(this).find('option:selected').attr('data-show');
            jQuery('.' + toShow).hide();
            if (val != '') {
                jQuery('#' + val).fadeIn('fast');
            }
        }
    });

    jQuery('input.required, select.required').change(function() {
        if (jQuery(this).closest('.form-row').find('.error').length != 0) {
            var val = jQuery(this).is('select') ? jQuery(this).find('option:selected').val() : jQuery(this).val();
            if (val != 'None' && val != '') {
                jQuery(this).closest('.form-row').find('.error').slideUp('fast');
            } else {
                jQuery(this).closest('.form-row').find('.error').slideDown('fast');
            }
        }
    });

    jQuery('form').submit(function(e) {
        e.preventDefault();

        if (jQuery(this).closest('body').is('#add-certificate')) {
            jQuery('.error').remove();
            jQuery('#loader').show();
            var empty = 0;
            jQuery('.required').each(function() {
                var val = jQuery(this).is('select') ? jQuery(this).find('option:selected').val() : jQuery(this).val();
                if (jQuery(this).closest('.form-row').css('display') != 'none' && (val == '' || val == 'None')) {
                    var error = jQuery('<span class="error hidden" />');
                    error.text('This field is required.');
                    error.appendTo(jQuery(this).closest('.form-row'));
                    error.slideDown('fast');
                    empty++
                }
            });
            if (empty == 0) {
                jQuery('input').each(function() {
                    if (jQuery(this).closest('.form-row').css('display') == 'none') {
                        jQuery(this).remove();
                    }
                });
                saveCertificate();
            }
        }
        
        return false
    });

    jQuery(window).ready(function() {
        if (jQuery('body').is('#manage-certificates')) {
            fetchCertificates('loadCertificates')
        }
        if (jQuery('body').is('#previewCert')) {
            loadPreview()
        }
        if (jQuery('body').is('#add-certificate')) {
            if (useBlanket != '1') {
                jQuery('#certificateType option[value="None"]').remove();
                jQuery('#certificateType option[value="false"]').remove();
                jQuery('#certificateType option[value="true"]').attr('selected', true);
                jQuery('#certificateType').trigger('change');
            }
        }
    });

    jQuery('.addCert').click(function() {
        parent.switchView('add-certificate.php');
    });

    jQuery('#manageCertsBtn').click(function() {
        parent.switchView('manage-certificates.php');
    });

    jQuery(document).on('click', '.removeButton', function() {
        var cID = jQuery(this).closest('tr').attr('id');
        var single = jQuery(this).is('.single');
        removeCertificate(cID, single);
    });

    jQuery(document).on('click', '.viewButton', function() {
        var cID = jQuery(this).closest('tr').attr('data-ind');
        previewCertificate(cID);
    });

    jQuery(document).on('click', '.useCert', function() {
        var cID = jQuery(this).closest('tr').attr('id');
        parent.setCertificate('applyCertificate', cID);
    })
});