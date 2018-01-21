/* global SSTCertData, wp */
( function( $, data, wp ) {
    $( function() {
        var $table          = $( '#sst-certificates' ),
            $tbody          = $( '#sst-certificates tbody' ),
            $row_template   = wp.template( 'sst-certificate-row' ),
            $blank_template = wp.template( 'sst-certificate-row-blank' ),

            // Backbone model
            CertificateTable = Backbone.Model.extend({
                certificates: {},
                selected: ''
            } ),

            // Backbone view
            CertificateTableView = Backbone.View.extend({
                rowTemplate: $row_template,
                initialize: function() {
                    this.listenTo( this.model, 'change:certificates', this.render );

                    $( document.body ).on( 'click', '.sst-certificate-add', { view: this }, this.onAddCertificate );
                    $( document.body ).on( 'wc_backbone_modal_response', this.onAddCertificateSubmitted );
                },
                render: function() {
                    var certificates = _.indexBy( this.model.get( 'certificates' ), 'CertificateID' ),
                        selected     = this.model.get( 'selected' ),
                        view         = this,
                        index        = 1;

                    // Blank out the contents.
                    this.$el.empty();

                    if ( _.size( certificates ) ) {
                        // Populate $tbody with the current certificates
                        $.each( certificates, function( id, rowData ) {
                            rowData.Index = index++;
                            view.$el.append( view.rowTemplate( rowData ) );
                        } );

                        // Make the rows function
                        view.$el.find( '.sst-certificate-delete' ).on( 'click', { view: this }, this.onDeleteRow );
                        view.$el.find( '.sst-certificate-view' ).on( 'click', { view: this }, this.onViewCertificate );
                        view.$el.find( 'input[name="certificate_id"]' ).on( 'change', this.updateCheckout );

                        // Select certificate (first certificate selected by default)
                        if ( selected ) {
                            $( 'input[name="certificate_id"][value="' + selected + '"]' ).prop( 'checked', true );
                        } else {
                            var first = $( 'input[name="certificate_id"]' ).first();
                            if ( first ) {
                                first.prop( 'checked', true );
                                this.model.set( 'selected', first.val() );
                            }
                        }
                    } else {
                        view.$el.append( $blank_template );
                    }
                },
                block: function() {
                    $( this.el ).block({
                        message: null,
                        overlayCSS: {
                            background: '#fff',
                            opacity: 0.6
                        }
                    });
                },
                unblock: function() {
                    $( this.el ).unblock();
                },
                updateCheckout: function( event ) {
                    $( document.body ).trigger( 'update_checkout' );
                },
                onDeleteRow: function( event ) {
                    var view           = event.data.view,
                        model          = view.model,
                        certificates   = _.indexBy( model.get( 'certificates' ), 'CertificateID' ),
                        selected       = view.model.get( 'selected' ),
                        certificate_id = $( this ).closest( 'tr' ).data( 'id' );

                    event.preventDefault();
                    
                    if ( ! confirm( data.strings.delete_certificate ) ) {
                        return;
                    }

                    view.block();

                    // Delete certificate via ajax call
                    $.post( data.ajaxurl + '?action=sst_delete_certificate', {
                        nonce: data.delete_certificate_nonce,
                        certificate_id: certificate_id,
                    }, function( response, textStatus ) {
                        view.unblock();

                        if ( 'success' === textStatus && response.success ) {
                            // Re-render
                            if ( selected == certificate_id ) {
                                certificateTableView.model.set( 'selected', '' );
                            }

                            certificateTableView.model.set( 'certificates', response.data.certificates );
                            certificateTableView.model.trigger( 'change:certificates' );
                            certificateTableView.updateCheckout();
                        } else {
                            alert( data.strings.delete_failed + ': ' + response.data );
                        }
                    }, 'json' );
                },
                onViewCertificate: function( event ) {
                    var view           = event.data.view,
                        model          = view.model,
                        certificates   = _.indexBy( model.get( 'certificates' ), 'CertificateID' ),
                        certificate_id = $( this ).closest( 'tr' ).data( 'id' ),
                        certificate    = certificates[ certificate_id ];

                    event.preventDefault();

                    if ( certificate ) {
                        // Set background image based on certificate type
                        if ( certificate.SinglePurchase ) {
                            certificate.backgroundImage = data.images.single_cert;
                        } else {
                            certificate.backgroundImage = data.images.blanket_cert;
                        }

                        $( this ).SSTBackboneModal({
                            template: 'sst-modal-view-certificate',
                            variable: certificate,
                        });
                    }
                },
                onAddCertificate: function( event ) {
                    var view = event.data.view;

                    event.preventDefault();

                    $( this ).SSTBackboneModal({
                        template: 'sst-modal-add-certificate',
                        variable: {
                            CertificateID: 'new-' + (new Date().getTime())
                        },
                        callback: view.validateAddCertificate
                    });
                },
                validateAddCertificate: function( event, target, posted_data ) {
                    var $target = $( event.target ),
                        form    = $target.closest( '.wc-backbone-modal' ).find( 'form' );
                    
                    // Reset
                    form.find( '.validate-required' ).removeClass( 'woocommerce-invalid-required-field woocommerce-invalid' );
                    
                    // Mark all required fields
                    form.find( '.validate-required' ).each( function() {
                        var $field = $( this ).find( 'input, select, textarea' );
                        if ( $field.val() === '' ) {
                            $( this ).addClass( 'woocommerce-invalid-required-field woocommerce-invalid' );
                        }
                    } );

                    return form.find( '.woocommerce-invalid' ).length === 0;
                },
                onAddCertificateSubmitted: function( event, target, posted_data ) {
                    if ( 'sst-modal-add-certificate' === target ) {
                        certificateTableView.block();

                        // Add certificate via ajax call
                        $.post( data.ajaxurl + '?action=sst_add_certificate', {
                            nonce: data.add_certificate_nonce,
                            certificate: posted_data,
                            form_data: $( 'form.woocommerce-checkout' ).serialize(),
                        }, function( response, textStatus ) {
                            certificateTableView.unblock();

                            if ( 'success' === textStatus && response.success ) {
                                // Re-render
                                certificateTableView.model.set( 'selected', response.data.certificate_id );
                                certificateTableView.model.set( 'certificates', response.data.certificates );
                                certificateTableView.model.trigger( 'change:certificates' );
                                certificateTableView.updateCheckout();
                            } else {
                                alert( data.strings.add_failed + ': ' + response.data );
                            }
                        }, 'json' );
                    }
                },
            } ),
            certificateTable = new CertificateTable( {
                certificates: data.certificates,
                selected: data.selected
            } ),
            certificateTableView = new CertificateTableView({
                model: certificateTable,
                el: $tbody
            } );

        certificateTableView.render();

        // Toggle visibility of tax details form based on value of "Tax exempt?" checkbox
        $( document ).on( 'change', '#tax_exempt_checkbox', function() {
            var checked = $( '#tax_exempt_checkbox' ).is( ':checked' );
            
            if ( checked )
                $( '#tax_details' ).fadeIn();
            else
                $( '#tax_details' ).hide();
            
            $( document.body ).trigger( 'update_checkout' );
        } );

        // Toggle visibility of certain form fields based on the value of a select box
        $( document ).on( 'change', '#PurchaserBusinessType', function() {
            var $toggle = $( '#business-type-other_field' );

            if ( 'Other' == $( this ).val() )
                $toggle.addClass( 'validate-required' ).show();
            else
                $toggle.removeClass( 'validate-required' ).hide();
        } );

        $( document ).on( 'change', '#TaxType', function() {
            var $toggle = $( '#issuing-state_field' );

            if ( 'StateIssued' == $( this ).val() )
                $toggle.addClass( 'validate-required' ).show();
            else
                $toggle.removeClass( 'validate-required' ).hide();
        } );

        $( document ).on( 'change', '#PurchaserExemptionReason', function() {
            var $toggle = $( '#exempt-other-reason_field' ),
                $label  = $toggle.find( 'label' ),
                value   = $( this ).val();

            if ( value !== '' ) {
                $toggle.addClass( 'validate-required' ).show();

                // Set label depending on value
                var labels = {
                    'FederalGovernmentDepartment': 'Dept. Name',
                    'StateOrLocalGovernmentName': 'Govt. Name',
                    'TribalGovernmentName': 'Tribe Name',
                    'ForeignDiplomat': 'Diplomat ID',
                    'CharitableOrganization': 'Organization ID',
                    'ReligiousOrEducationalOrganization': 'Organization ID',
                    'Resale': 'Resale ID',
                    'AgriculturalProduction': 'Agricultural Prod. ID',
                    'IndustrialProductionOrManufacturing': 'Production ID',
                    'DirectPayPermit': 'Permit ID',
                    'DirectMail': 'Direct Mail ID',
                    'Other': 'Please explain'
                };

                $label.text( labels[ value ] );
            } else {
                $toggle.removeClass( 'validate-required' ).hide();
            }
        } );

        // Remove red border from invalid fields when value changes
        $( document ).on( 'input', '.sst-input', function( e ) {
            var $this   = $( this ),
                $parent = $this.closest( '.form-row' );

            $parent.removeClass( 'woocommerce-invalid woocommerce-invalid-required-field woocommerce-invalid-email woocommerce-validated' );
        } );

        // Initialize exemption form
        $( '#tax_exempt_checkbox' ).change();
    });
})( jQuery, SSTCertData, wp );