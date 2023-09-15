jQuery( function( $ ) {
	function toggleField( selector, show ) {
		$( selector )
			.toggleClass( 'validate-required', show )
			.toggleClass( 'sst-hidden-field', !show );
	}

	// Toggle visibility of certain form fields based on the value of a select box
	$( document ).on( 'change', '#purchaser_business_type', function() {
		toggleField(
			'#purchase_business_type_other_value_field',
			'Other' === $( this ).val()
		);
	} );

	$( document ).on( 'change', '#tax_type', function() {
		toggleField(
			'#state_of_issue_field',
			'StateIssued' === $( this ).val()
		);
	} );

	$( document ).on( 'change', '#purchaser_exemption_reason', function() {
		var value = $( this ).val();
		var showField = '' !== value;

		toggleField(
			'#purchaser_exemption_reason_value_field',
			showField,
		);

		if ( showField ) {
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

			$( 'label[for="purchaser_exemption_reason_value"]' ).text(
				labels[ value ]
			);
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
	function enhanceSelectBoxes(args = {}) {
		$( 'select.sst-input' ).each( function() {
			$( this ).selectWoo( {
				minimumResultsForSearch: 10,
				allowClear: $( this ).data( 'allow_clear' ) ? true : false,
				placeholder: $( this ).data( 'placeholder' ),
				...args,
			} );
		} );
	}

	enhanceSelectBoxes();

	window.SST_Certificate_Form = {
		enhanceSelectBoxes,
	};
} );
