var SST_Add_Certificate_Modal = {
	open( args ) {
		var address = args.address || {};

		if ( ! SST_Add_Certificate_Modal.isValidAddress( address ) ) {
			alert( SST_Add_Certificate_Data.strings.please_add_address );
			return;
		}

		jQuery( document.body ).SSTBackboneModal( {
			template: 'sst-modal-add-certificate',
			variable: {
				CertificateID: 'new-' + ( new Date().getTime() )
			},
			callback: SST_Add_Certificate_Modal.validateForm
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

		// Toggle visibility of certain form fields based on the value of a select box
		jQuery( '#PurchaserBusinessType' ).on( 'change', function() {
			var $toggle = jQuery( '#business-type-other_field' );

			if ( 'Other' == jQuery( this ).val() ) {
				$toggle.addClass( 'validate-required' ).show();
			} else {
				$toggle.removeClass( 'validate-required' ).hide();
			}
		} );

		jQuery( '#TaxType' ).on( 'change', function() {
			var $toggle = jQuery( '#issuing-state_field' );

			if ( 'StateIssued' == jQuery( this ).val() ) {
				$toggle.addClass( 'validate-required' ).show();
			} else {
				$toggle.removeClass( 'validate-required' ).hide();
			}
		} );

		jQuery( '#PurchaserExemptionReason' ).on( 'change', function() {
			var $toggle = jQuery( '#exempt-other-reason_field' ),
				$label  = $toggle.find( 'label' ),
				value   = jQuery( this ).val();

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
		jQuery( '.sst-input' ).on( 'input', function( e ) {
			var $this   = jQuery( this ),
				$parent = $this.closest( '.form-row' );

			$parent.removeClass(
				'woocommerce-invalid woocommerce-invalid-required-field woocommerce-invalid-email woocommerce-validated'
			);
		} );
	},
	isValidAddress( address ) {
		var required_fields = [
			'first_name',
			'last_name',
			'address_1',
			'city',
			'state',
			'postcode',
		];

		return required_fields.every(function(field) {
			return field in address && !!address[field];
		});
	},
	validateForm( event ) {
		var $target = jQuery( event.target );
		var form    = $target.closest( '.wc-backbone-modal' ).find( 'form' );

		// Reset
		form
			.find( '.validate-required' )
			.removeClass( 'woocommerce-invalid-required-field woocommerce-invalid' );

		// Mark all required fields
		form.find( '.validate-required' ).each( function() {
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
