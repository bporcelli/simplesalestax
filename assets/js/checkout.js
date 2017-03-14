/**
 * WooTax checkout JavaScripts
 */

jQuery( function() {

	// Toggle visibility of tax details form based on value of "Tax exempt?" checkbox
	jQuery( '#tax_exempt_checkbox' ).change( function() {
		var checked = jQuery( '#tax_exempt_checkbox' ).is( ':checked' );

		if ( checked ) {
			jQuery( '#tax_details' ).fadeIn();
		} else {
			jQuery( '#tax_details' ).hide();
		}

		jQuery( document.body ).trigger( 'update_checkout' );
	} );

	// Toggle visibility of "new certificate" form based on the value of the "certificate ID" field
	jQuery( document ).on( 'change', 'input[name=certificate_id]', function() {
		var value = jQuery( 'input[name=certificate_id]:checked' ).val();

		if ( value == "new" ) {
			jQuery( '#new_certificate_form' ).fadeIn();
		} else {
			jQuery( '#new_certificate_form' ).hide();
		}

		jQuery( document.body ).trigger( 'update_checkout' );
	} );

	// Toggle visibility of certain form fields based on the value of a select box
	jQuery( 'select.toggle-visibility' ).each( function() {
		var toggle_class = jQuery( this ).data( 'toggle-class' );

		// Initially hide all elements with toggle class
		jQuery( '.' + toggle_class ).hide();

		// Selectively show hidden elements when select value changes
		jQuery( this ).change( function() {
			jQuery( '.' + jQuery( this ).data( 'toggle-class' ) ).hide();

			var show = jQuery( this ).find( 'option:selected' ).data( 'show' );
			if ( show )
				jQuery( '#' + show ).fadeIn();
		} );
	} );

	// Initialize
	jQuery( 'input[name=certificate_id], #tax_exempt_checkbox' ).change();

} );