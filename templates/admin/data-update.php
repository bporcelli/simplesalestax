<h3>WooTax Data Update</h3>

<p>WooTax is working. Please do not close your browser window. You will be redirected away from this page when the data update is complete.</p>

<p><strong>Status: </strong><span id="wootax_status">Starting update...</span></p>

<script type="text/javascript">
	jQuery(function() {
		function send_update_request( lp, cp, tp ) {
			jQuery.ajax({
				type: 'POST',
				url: WT.ajaxURL,
				data: 'action=wootax-update-data&last_post='+ lp +'&current_page='+ cp +'&total_pages='+ tp,
				success: function( resp ) {
					resp = eval( '('+ resp +')' );

					if ( resp.status == 'done' ) {
						jQuery( '#wootax_status' ).text( resp.message );

						// Success! Redirect to show success message
						window.location.href = resp.redirect;
					} else {
						jQuery( '#wootax_status' ).text( 'Running... Step '+ resp.current_page +' out of '+ resp.total_pages );
						
						// Start next round of processing
						send_update_request( resp.last_post, resp.current_page, resp.total_pages );
					}
				}
			});
		}

		// Trigger update process on page load
		send_update_request( 0, 1, 0 )
	});
</script>