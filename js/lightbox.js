/**
 * JS for TaxCloud certificate management lightbox
 * Part of the WooTax plugin by Brett Porcelli
 */

var certManagerBox = {
    certManager: window.parent.certManager,
    ajaxURL: window.parent.certManager.ajaxURL,
    useBlanket: window.parent.certManager.useBlanket,

    // Save a certificate after form validation
    saveCertificate: function() {
        certManagerBox.resetFeedback();

        jQuery.ajax( {
            type: 'POST',
            url: certManagerBox.ajaxURL,
            data: jQuery( 'form' ).serialize(),
            success: function( resp ) {
                jQuery( '#wootax-loader' ).hide();

                resp = eval('(' + resp + ')');

                if ( resp.status == 'error' ) {
                    certManagerBox.displayFeedback( resp.message, 'error' );
                } else if ( jQuery( '[name="SinglePurchase"]' ).val() == 'false' ) {
                    certManagerBox.switchView( 'manage-certificates' )
                } else {
                    certManagerBox.setCertificate( true )
                }
            }
        } );
    },

    // Remove certificate 
    removeCertificate: function( certID, single ) {
        jQuery('#wootax-loader').show();

        // Hide error messages
        certManagerBox.resetFeedback();

        jQuery.ajax({
            type: 'POST',
            url: certManagerBox.ajaxURL,
            data: 'certificateID=' + certID + '&single=' + single + '&act=remove&action=wootax-update-certificate',
            success: function(resp) {
                resp = eval('(' + resp + ')');

                if (resp.status == 'error') {
                    certManagerBox.displayFeedback( resp.message, 'error' );
                } else {
                    jQuery('#' + certID).fadeOut('fast', function() {
                        jQuery(this).remove();
                        if (jQuery('tr').length == 0) {
                            jQuery('#manageCertificates').hide();
                            jQuery('#addCertificateSection').fadeIn('fast')
                        }
                    });

                    jQuery('#certCount').text( parseInt( jQuery( '#certCount' ).text() ) - 1 );
                    certManagerBox.displayFeedback( resp.message, 'success' );
                }

                jQuery('#wootax-loader').hide();
            },
        });
    },

    // Parse date from string
    parseDate: function( datestr ) {
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
    },

    // Display exemption certificates
    displayCertificates: function() {
        jQuery( '#certs' ).html('');

        var certObj = certManagerBox.certManager.certificates;
        var newHTML = '';

        for ( var i = 0; i < certObj.length; i++ ) {
            var date = certManagerBox.parseDate( certObj[i].Detail.CreatedDate );

            newHTML += '<tr id="' + certObj[i].CertificateID + '" data-ind="' + i + '" class="certificateWrap">';
            newHTML += '<td valign="middle" align="center" width="160"><img width="150" height="120" src="' + (certObj[i].Detail.SinglePurchase == false ? window.parent.wt_exempt_params.pluginPath + 'img/exemption_certificate150x120.png' : window.parent.wt_exempt_params.pluginPath + 'img/sp_exemption_certificate_150x120.png') + '" /></td>';
            newHTML += '<td valign="middle" width="340">Issued To: ' + certObj[i].Detail.PurchaserFirstName + ' ' + certObj[i].Detail.PurchaserLastName + '<br /> Exempt State(s): ' + certObj[i].Detail.ExemptStates.ExemptState.StateAbbr + '<br /> Date: ' + (parseInt(date.getMonth()) + 1) + '/' + date.getDate() + '/' + date.getFullYear() + '<br /> Purpose: ' + certManagerBox.getPrettyWord( certObj[i].Detail.PurchaserExemptionReason ) + '<br />' + certManagerBox.displayCertOptions( certObj[i] ) + '</td>';
            newHTML += '</tr>'
        }

        jQuery('#certCount').text( certObj.length );
        jQuery('#certs').html( newHTML );
    },

    // Display the options (use/remove/view) for a certificate
    displayCertOptions: function( certificate ) {
        var certID = certificate.CertificateID;

        var out = '<button class="grey removeButton smallButton' + (certificate.Detail.SinglePurchase == true ? ' single' : '') + '" type="button" ccertificatelass="removeCert" title="Remove Certificate ' + certID + '">Remove</button>';
        out += '<button class="grey viewButton smallButton" type="button" class="viewCert" title="View Certificate ' + certID + '">View</button>';
       
        if ( certificate.Detail.SinglePurchase != true ) {
            out += '<button type="button" class="useCert smallButton" title="Use Certificate ' + certID + '">Use</button>';
        }

        return out;
    },

    // Display a single certificate preview
    previewInit: function() {
        var certArr = jQuery.map( certManagerBox.certManager.certificates, function(value, key) { return value });
        var cert    = certArr[ certInd ];
        var date    = certManagerBox.parseDate( cert.Detail.CreatedDate );

        jQuery( '.certID' ).text( cert.CertificateID );

        if ( cert.Detail.SinglePurchase == true )
            jQuery( '#certificatePreview' ).css( 'background-image', 'url(' + window.parent.wt_exempt_params.pluginPath + 'img/sp_exemption_certificate_750x600.png)' )

        jQuery( '#PurchaserName' ).text( cert.Detail.PurchaserFirstName + ' ' + cert.Detail.PurchaserLastName );
        jQuery( '#PurchaserAddress' ).text( cert.Detail.PurchaserAddress1 + ', ' + cert.Detail.PurchaserCity + ', ' + cert.Detail.PurchaserState + ' ' + cert.Detail.PurchaserZip );
        jQuery( '#PurchaserState' ).text( certManagerBox.getPrettyWord( cert.Detail.PurchaserState ) );
        jQuery( '#PurchaserExemptionReason' ).text( certManagerBox.getPrettyWord( cert.Detail.PurchaserExemptionReason ) + ' : ' + cert.Detail.PurchaserExemptionReasonValue );
        jQuery( '#Date' ).text( ( parseInt( date.getMonth() ) + 1 ) + '/' + date.getDate() + '/' + date.getFullYear() );
        jQuery( '#TaxType' ).text( cert.Detail.PurchaserTaxID.TaxType );
        jQuery( '#IDNumber' ).text( cert.Detail.PurchaserTaxID.IDNumber );

        var bType = certManagerBox.getPrettyWord( cert.Detail.PurchaserBusinessType );
        jQuery ( '#PurchaserBusinessType' ).text( bType.length > 20 ? bType.substr( 0, 20 )  + '...' : bType );

        var merchantName = window.parent.wt_exempt_params.merchantName;
        merchantName = merchantName.length > 20 ? merchantName.substr( 0, 20 ) + '...' : merchantName;
        jQuery( '#MerchantName' ).text( merchantName );

        if ( cert.Detail.SinglePurchaseOrderNumber != '' ) {
            jQuery( '#OrderID' ).text( cert.Detail.SinglePurchaseOrderNumber.substr( 0, 20 ) + '...' );
        }
    },

    // Initialize the manage-certificates screen
    manageInit: function() {
        var date = new Date();

        jQuery( '#wootax-loading' ).show();

        jQuery.ajax({
            type: 'POST',
            url: certManagerBox.ajaxURL + '?t=' + date.getTime(),
            data: 'action=wootax-list-certificates',
            success: function( resp ) {
                var certs = eval('(' + resp + ')');
                certManagerBox.certManager.certificates = certs.cert_list;

                if ( certManagerBox.certManager.certificates.length > 0 && certManagerBox.certManager.certificates[0] != null ) {
                    certManagerBox.displayCertificates();
                    
                    jQuery( '#wootax-loading' ).hide();
                    jQuery( '#manageCertificates' ).show()
                } else {
                    jQuery( '#wootax-loading' ).hide();
                    jQuery( '#addCertificateSection' ).show()
                }
            }
        });
    },

    // Convert ugly keywords to pretty words
    getPrettyWord: function( keyWord ) {
        switch ( keyWord ) {
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

        return keyWord;
    },

    // Reset feedback readout
    resetFeedback: function() {
        jQuery( '#feedback' ).slideUp( function() {
            jQuery( this ).removeClass( 'errorMsg' ).removeClass( 'successMsg' ).text( '' );
        } );
    },

    // Show feedback (i.e. form error messages)
    displayFeedback: function( message, message_type ) {
        jQuery( '#feedback' ).addClass( message_type + 'Msg' ).text( message ).slideDown();
        window.location.href = '#feedback';
    },

    // Switch lightbox view (conveniency wrapper for certManager.switchView)
    switchView: function( lightbox ) {
        certManagerBox.certManager.switchView( lightbox );
    },

    // Set certificate in session (conveniency wrapper for certManager.setCertificate)
    setCertificate: function( certificate ) {
        certManagerBox.certManager.setCertificate( certificate );
    },
};

// JS event bindings
jQuery( function() {

    // Animate blanket/single certificate select
    jQuery( '#certificateType' ).change( function() {
        var val = jQuery( this ).find( 'option:selected' ).attr( 'data-text' );
        jQuery( '#certExpl' ).html( '<em>' + val + '</em>' );
    } );

    // Show additional options based on selected value
    jQuery( 'select' ).change( function() {
        var toShow = jQuery( this ).attr( 'data-show-class' );
        
        if ( typeof toShow != 'undefined' && toShow != '' ) {
            var val = jQuery( this ).find( 'option:selected' ).attr( 'data-show' );
            
            jQuery( '.' + toShow ).hide();

            if ( val != '' )
                jQuery( '#' + val ).fadeIn( 'fast' );
        }
    } );

    // Show error feedback if user does not fill out required field
    jQuery( 'input.required, select.required' ).change( function() {
        if ( jQuery( this ).closest( '.form-row' ).find( '.error' ).length != 0 ) {
            var val = jQuery( this ).is( 'select' ) ? jQuery( this ).find( 'option:selected' ).val() : jQuery( this ).val();
           
            if ( val != 'None' && val != '' ) {
                jQuery( this ).closest( '.form-row' ).find( '.error' ).slideUp( 'fast' );
            } else {
                jQuery( this ).closest( '.form-row' ).find( '.error' ).slideDown( 'fast' );
            }
        }
    } );

    // Validate form submission
    jQuery( 'form' ).submit( function( e ) {
        e.preventDefault();

        if ( jQuery( this ).closest( 'body' ).is( '#add-certificate' ) ) {
            jQuery( '.error' ).remove();
            jQuery( '#wootax-loader' ).show();
            
            var empty = 0;

            // Check required fields
            jQuery( '.required' ).each( function() {
                var val = jQuery( this ).is( 'select' ) ? jQuery( this ).find( 'option:selected' ).val() : jQuery( this ).val();
                 
                if ( jQuery( this ).closest( '.form-row' ).css( 'display' ) != 'none' && ( val == '' || val == 'None' ) ) {
                    var error = jQuery( '<span class="error hidden" />' );
                    
                    error.text( 'This field is required.' );
                    error.appendTo( jQuery( this ).closest( '.form-row' ) );
                    error.slideDown( 'fast' );

                    empty++;
                }
            } );

            if ( empty == 0 ) {
                jQuery( 'input' ).each( function() {
                    if ( jQuery( this ).closest( '.form-row' ).css( 'display' ) == 'none' ) {
                        jQuery( this ).remove();
                    }
                } );

                certManagerBox.saveCertificate();
            } else {
                jQuery( '#wootax-loader' ).hide();
            }
        }
    } );
    
    // Initialize manage/preview/add screens when the lightbox is loaded
    jQuery( window ).ready( function() {
        if ( jQuery( 'body' ).is( '#manage-certificates' ) ) {
            certManagerBox.manageInit();
        } else if ( jQuery( 'body' ).is( '#previewCert' ) ) {
            certManagerBox.previewInit();
        } else if ( jQuery( 'body' ).is( '#add-certificate' ) ) {
            if ( certManagerBox.useBlanket != '1' ) {
                jQuery( '#certificateType option[value="None"]' ).remove();
                jQuery( '#certificateType option[value="false"]' ).remove();
                jQuery( '#certificateType option[value="true"]' ).attr( 'selected', true );
                jQuery( '#certificateType' ).trigger( 'change' );
            }
        }
    } );

    // Switch to add certificate view when button is clicked
    jQuery( '.addCert' ).click( function() {
        certManagerBox.switchView( 'add-certificate' );
    } );

    // Switch to manage certificates view when button is clicked
    jQuery( '#manageCertsBtn' ).click( function() {
        certManagerBox.switchView( 'manage-certificates' );
    } );

    // Trigger removal of certificate
    jQuery( document ).on( 'click', '.removeButton', function() {
        var cID    = jQuery( this ).closest( 'tr' ).attr( 'id' );
        var single = jQuery( this ).is( '.single' );

        certManagerBox.removeCertificate( cID, single );
    } );

    // Trigger certificate preview
    jQuery( document ).on( 'click', '.viewButton', function() {
        var cID = jQuery( this ).closest( 'tr' ).attr( 'data-ind' );
        certManagerBox.switchView( 'preview-certificate?certIndex=' + cID );
    } );

    // Trigger certificate application
    jQuery( document ).on( 'click', '.useCert', function() {
        var cID = jQuery( this ).closest( 'tr' ).attr( 'id' );
        certManagerBox.setCertificate( cID );
    } );

} );