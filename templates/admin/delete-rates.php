<h3>Installation Step 2: Delete manually entered tax rates.</h3>
<p>If you do not delete tax rates that you have added in the past, your customers will be overtaxed.</p>

<?php 

	$this->display_class_table(); 

	// Mark rates as checked if user has accessed this page.
	update_option( 'wootax_rates_checked', true ); 

?>

<p>
	<a href="<?php echo admin_url( 'admin.php?page=wc-settings&tab=integration&section=wootax' ); ?>" class="wp-core-ui button-primary">Complete Installation</a>
</p>