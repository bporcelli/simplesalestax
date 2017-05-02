<?php 
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( $is_variation ) {
	$class = 'form-row form-field form-row-full';
} else {
	$class = 'form-field';
} ?>

<p class="<?php echo $class; ?> wootax_tic">
	<label for="wootax_tic[<?php echo $product_id; ?>]"><?php _e( 'TIC', 'simplesalestax' ); ?></label>
	
	<?php if ( $is_variation ): ?><br><?php endif; ?>
	
	<span class="sst-selected-tic"><?php esc_html_e( 'Using site default', 'simplesalestax' ); ?></span>
	<button type="button" class="button sst-select-tic"><?php esc_html_e( 'Select', 'simplesalestax' ); ?></button>
	<input type="hidden" name="wootax_tic[<?php echo $product_id; ?>]" class="sst-tic-input" value="<?php echo $current_tic; ?>">
</p>

<?php include SST()->plugin_path() . '/includes/admin/views/html-select-tic-modal.php'; ?>