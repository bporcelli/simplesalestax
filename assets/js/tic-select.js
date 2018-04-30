/* global jQuery, SST, ticSelectLocalizeScript */
(function($, SST, data) {
    $(function() {
        var $row_template = wp.template( 'sst-tic-row' ),
            SelectView    = Backbone.View.extend( {
                rowTemplate: $row_template,
                input: null,
                readout: null,
                initialize: function() {
                    this.input   = this.$el.siblings( '.sst-tic-input' );
                    this.readout = this.$el.siblings( '.sst-selected-tic' );
                    this.$el.click( { view: this }, this.openModal );
                },
                render: function() {
                    this.selectTIC( this.input.val() );
                },
                bindEvents: function() {
                    $( document.body ).on( 'click', '.sst-select-done', { view: this }, this.updateSelection );
                    $( document.body ).on( 'wc_backbone_modal_response', { view: this }, this.completeSelection );
                },
                unbindEvents: function() {
                    $( document.body ).off( 'click', '.sst-select-done', this.updateSelection );
                    $( document.body ).off( 'wc_backbone_modal_response', this.completeSelection );
                },
                openModal: function( event ) {
                    var view = event.data.view;

                    event.preventDefault();
                    
                    $( this ).SSTBackboneModal( {
                        'template': 'sst-tic-select-modal',
                    } );

                    view.bindEvents();
                    view.initModal();
                },
                initModal: function( event ) {
                    var view  = this,
                        $list = $( '.sst-tic-list' );

                    $list.empty();

                    _.each( data.tic_list, function( rowData, id ) {
                        $list.append( view.rowTemplate( rowData ) );
                    } );

                    $( '.sst-tic-search' ).hideseek();
                },
                updateSelection: function( event ) {
                    var $target = $( event.target ),
                        $tr     = $target.closest( 'tr' );

                    $( 'input[name="tic"]' ).val( $tr.data( 'id' ) );
                    $( '#btn-ok' ).trigger( 'click' );
                },
                completeSelection: function( event, target, posted ) {
                    if ( 'sst-tic-select-modal' === target ) {
                        event.data.view.selectTIC( posted['tic'] );
                        event.data.view.unbindEvents();
                    }
                },
                selectTIC: function( tic_id ) {
                    if ( '' == tic_id ) {
                        this.readout.text( data.strings.default );
                    } else {
                        var tic = data.tic_list[ parseInt( tic_id ) ];
                        this.readout.text( tic['description'] + ' (' + tic['id'] + ')' );
                        this.input.val( tic_id ).trigger( 'change' );
                    }
                },
            } );

        function initialize() {
            $( '.sst-select-tic:not(.initialized)' ).each( function() {
                var selectView = new SelectView( {
                    el: $( this ),
                } );

                selectView.render();

                $( this ).addClass( 'initialized' );
            } );
        }

        initialize();

        $( '#woocommerce-product-data' ).on( 'woocommerce_variations_loaded', initialize );
    });
})(jQuery, SST, ticSelectLocalizeScript);