jQuery( function( $ ) {
	// Toggle visibility of certain form fields based on the value of a select box
	$( document ).on( 'change', '#purchaser_business_type', function() {
		var $toggle = $( '#purchase_business_type_other_value_field' );

		if ( 'Other' == $( this ).val() ) {
			$toggle.addClass( 'validate-required' ).show();
		} else {
			$toggle.removeClass( 'validate-required' ).hide();
		}
	} );

	$( document ).on( 'change', '#tax_type', function() {
		var $toggle = $( '#state_of_issue_field' );

		if ( 'StateIssued' == $( this ).val() ) {
			$toggle.addClass( 'validate-required' ).show();
		} else {
			$toggle.removeClass( 'validate-required' ).hide();
		}
	} );

	$( document ).on( 'change', '#purchaser_exemption_reason', function() {
		var $toggle = $( '#purchaser_exemption_reason_value_field' ),
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
		var $parent = $( this ).closest( '.form-row' );

		$parent.removeClass(
			'woocommerce-invalid woocommerce-invalid-required-field woocommerce-invalid-email woocommerce-validated'
		);
	} );

	// Enhance select boxes
	$( 'select.sst-input' ).each( function() {
		$( this ).selectWoo( {
			minimumResultsForSearch: 10,
			allowClear: $( this ).data( 'allow_clear' ) ? true : false,
			placeholder: $( this ).data( 'placeholder' )
		} );
	} );
} );
