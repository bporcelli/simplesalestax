<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
} ?>

<label class="alignleft">
	<span class="title"><?php _e( 'TIC', 'simplesalestax' ); ?></span>
	<span class="input-text-wrap">
		<span class="sst-selected-tic"><?php esc_html_e( 'Using site default', 'simplesalestax' ); ?></span>
		<input type="hidden" name="wootax_tic" class="sst-tic-input" value="">
		<button type="button" class="button sst-select-tic"><?php esc_html_e( 'Select', 'simplesalestax' ); ?></button>
   	</span>
</label>

<?php include SST()->plugin_path() . '/includes/admin/views/html-select-tic-modal.php'; ?>