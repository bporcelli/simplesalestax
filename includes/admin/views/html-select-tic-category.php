<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
} 

$label = esc_html( 'Taxability Information Code', 'simplesalestax' );

if ( $is_edit ): ?>
<tr class="form-field">
	<th><?php echo $label; ?></th>
	<td>
<?php else: ?>
<div class="form-field">
	<label><?php echo $label; ?></label>
<?php endif; ?>

	<div class="sst-tic-select-wrap">
		<span class="sst-selected-tic"><?php esc_html_e( 'Using site default', 'simplesalestax' ); ?></span>
		<input type="hidden" name="wootax_tic" class="sst-tic-input" value="<?php echo $current_tic; ?>">
		<button type="button" class="button sst-select-tic"><?php esc_html_e( 'Select', 'simplesalestax' ); ?></button>
	</div>

    <p class="description"><?php esc_html_e( 'This TIC will be used as the default for all products in this category.', 'simplesalestax' ); ?></p>

<?php if ( $is_edit ): ?>
	</td>
</tr>
<?php else: ?>
</div>
<?php endif; ?>

<?php include SST()->plugin_path() . '/includes/admin/views/html-select-tic-modal.php'; ?>