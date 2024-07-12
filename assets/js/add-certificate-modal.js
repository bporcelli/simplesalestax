var SST_Add_Certificate_Modal = {
	open( args ) {
		var address = args.address || {};
		var validationResult = SST_Add_Certificate_Modal.validateAddress( address );

		if ( true !== validationResult ) {
			alert( validationResult );
			return;
		}

		jQuery( document.body ).SSTBackboneModal( {
			template: 'sst-modal-add-certificate',
			variable: {
				CertificateID: 'new-' + ( new Date().getTime() )
			},
			callback: SST_Add_Certificate_Modal.validateForm
		} );

		// Enhance select boxes
		SST_Certificate_Form.enhanceSelectBoxes( {
			dropdownCssClass: 'sst-select2-dropdown',
		} );

		if ( args.onAddCertificate ) {
			var addCertificateHandler = function( event, target, posted_data ) {
				if ( 'sst-modal-add-certificate' === target ) {
					args.onAddCertificate( posted_data );
					jQuery( document.body ).off(
						'wc_backbone_modal_response',
						addCertificateHandler
					);
				}
			};

			jQuery( document.body ).on(
				'wc_backbone_modal_response',
				addCertificateHandler
			);
		}
	},
	validateAddress( address ) {
		var requiredFields = [
			'first_name',
			'last_name',
			'address_1',
			'country',
			'city',
			'state',
			'postcode',
		];

		var hasAllRequiredFields = requiredFields.every( function( field ) {
			return field in address && !! address[ field ];
		} );

		if ( ! hasAllRequiredFields ) {
			return SST_Add_Certificate_Data.strings.please_add_address;
		}

		if ( address['country'] !== 'US' ) {
			return SST_Add_Certificate_Data.strings.invalid_country;
		}

		return true;
	},
	validateForm( event ) {
		var $target = jQuery( event.target );
		var form    = $target.closest( '.wc-backbone-modal' ).find( 'form' );

		// Reset
		form
			.find( '.validate-required' )
			.removeClass( 'woocommerce-invalid-required-field woocommerce-invalid' );

		// Mark all required fields
		form.find( '.validate-required:visible' ).each( function() {
			var $field = jQuery( this ).find( 'input, select, textarea' );
			if ( $field.val() === '' ) {
				jQuery( this ).addClass(
					'woocommerce-invalid-required-field woocommerce-invalid'
				);
			}
		} );

		return form.find( '.woocommerce-invalid' ).length === 0;
	},
}
