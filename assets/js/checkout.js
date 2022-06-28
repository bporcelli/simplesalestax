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
                        $( this ).SSTBackboneModal({
                            template: 'sst-modal-view-certificate',
                            variable: certificate,
                        });
                    }
                },
                onAddCertificate: function( event ) {
                    event.preventDefault();

                    var view = event.data.view;

                    SST_Add_Certificate_Modal.open( {
                        onAddCertificate: view.addCertificateHandler,
                    } );
                },
                addCertificateHandler: function( posted_data ) {
                    certificateTableView.block();

                    var $form = $( 'form.woocommerce-checkout' );

                    // Add certificate via ajax call
                    $.post( data.ajaxurl + '?action=sst_add_certificate', {
                        nonce: SST_Add_Certificate_Data.nonce,
                        certificate: posted_data,
                        address: {
                            first_name: $form.find( '[name="billing_first_name"]' ).val(),
                            last_name: $form.find( '[name="billing_last_name"]' ).val(),
                            address_1: $form.find( '[name="billing_address_1"]' ).val(),
                            address_2: $form.find( '[name="billing_address_2"]' ).val(),
                            city: $form.find( '[name="billing_city"]' ).val(),
                            state: $form.find( '[name="billing_state"]' ).val(),
                            postcode: $form.find( '[name="billing_postcode"]' ).val(),
                        },
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

        // Initialize exemption form
        $( '#tax_exempt_checkbox' ).change();
    });
})( jQuery, SSTCertData, wp );