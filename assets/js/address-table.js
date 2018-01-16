/* global addressesLocalizeScript, ajaxurl */
( function( $, data, wp, ajaxurl ) {
    $( function() {
        var $table          = $( '#address_table' ),
            $tbody          = $( '#address_table tbody' ),
            $row_template   = wp.template( 'sst-address-row' ),
            $blank_template = wp.template( 'sst-address-row-blank' ),

            // Backbone model
            AddressTable = Backbone.Model.extend( {
                addresses: {},
            } ),

            // Backbone view
            AddressTableView = Backbone.View.extend({
                rowTemplate: $row_template,
                initialize: function() {
                    $( document.body ).on( 'click', '.sst-address-add', { view: this }, this.onAddNewRow );
                },
                render: function() {
                    var addresses = _.indexBy( this.model.get( 'addresses' ), 'ID' ),
                        view      = this;

                    view.$el.empty();

                    if ( _.size( addresses ) ) {
                        // Populate $tbody with the current addresses
                        $.each( addresses, function( id, rowData ) {
                            view.renderRow( rowData );
                        } );
                    } else {
                        view.$el.append( $blank_template );
                    }

                    view.initRows();
                },
                renderRow: function( rowData ) {
                    var view = this;
                    view.$el.append( view.rowTemplate( rowData ) );
                    view.initRow( rowData );
                },
                initRow: function( rowData ) {
                    var view = this;
                    var $tr = view.$el.find( 'tr[data-id="' + rowData.ID + '"]');

                    // Select state
                    $tr.find( 'option[value="' + rowData.State + '"]' ).prop( 'selected', true );

                    // Tick 'default' checkbox
                    if ( rowData.Default ) {
                        $tr.find( 'input[type="checkbox"]' ).prop( 'checked', true );
                    }

                    // Set up event handlers
                    $tr.find( '.sst-address-delete' ).on( 'click', { view: this }, this.onDeleteRow );
                    $tr.find( 'input[type="checkbox"]' ).on( 'change', { view: this }, this.defaultsChanged );
                },
                initRows: function() {
                    var view      = this,
                        model     = view.model,
                        addresses = _.indexBy( model.get( 'addresses' ), 'ID' ),
                        tbody     = view.$el;

                    // Force the first address to be a default address
                    if ( _.size( addresses ) == 1 ) {
                        var firstID = Object.keys( addresses )[0];
                        addresses[ firstID ].Default = 'yes';
                        model.set( 'addresses', addresses );

                        // Manually update to prevent infinite recursion
                        tbody.find( 'input[type="checkbox"]' ).prop( 'checked', true );
                    }
                },
                defaultsChanged: function( event ) {
                    var view      = event.data.view,
                        model     = view.model,
                        addresses = _.indexBy( model.get( 'addresses' ), 'ID' )
                        tbody     = view.$el;

                    // Ensure that at least one address is a default address at all times
                    if ( tbody.find( 'input[type="checkbox"]:checked' ).length == 0 ) {
                        $( this ).prop( 'checked', true );
                        alert( data.strings.one_default_required );
                    }
                },
                onAddNewRow: function( event ) {
                    var view        = event.data.view,
                        model       = view.model,
                        addresses   = _.indexBy( model.get( 'addresses' ), 'ID' );
                        address_id  = 'new-' + new Date().getTime(),
                        address     = _.defaults( { 'ID': address_id }, data.default_address ),

                    event.preventDefault();

                    addresses[ address_id ] = address;
                    model.set( 'addresses', addresses );
                    
                    // Update view
                    $( '#sst-address-row-blank' ).remove();
                    
                    view.renderRow( address );
                    view.initRows();
                },
                onDeleteRow: function( event ) {
                    var view       = event.data.view,
                        model      = view.model,
                        addresses  = _.indexBy( model.get( 'addresses' ), 'ID' ),
                        row        = $( this ).closest('tr'),
                        address_id = row.data('id');

                    event.preventDefault();

                    if ( addresses[ address_id ] ) {
                        delete addresses[ address_id ];
                        model.set( 'addresses', addresses );
                        
                        // Update view
                        row.remove();

                        if ( $tbody.find( 'tr' ).length == 0 ) {
                            view.$el.append( $blank_template );
                        }

                        view.initRows();
                    }
                },
            } ),
            addressTable = new AddressTable({
                addresses: data.addresses
            } ),
            addressTableView = new AddressTableView({
                model: addressTable,
                el:    $tbody
            } );

        addressTableView.render();
    });
})( jQuery, addressesLocalizeScript, wp, ajaxurl );
