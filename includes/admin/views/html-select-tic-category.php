<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( $is_edit ) : ?>
	<tr class="form-field">
	<th><?php esc_html_e( 'Taxability Information Code', 'simple-sales-tax' ); ?></th>
	<td>
<?php else : ?>
	<div class="form-field">
	<label><?php esc_html_e( 'Taxability Information Code', 'simple-sales-tax' ); ?></label>
<?php endif; ?>

	<div class="sst-tic-select-wrap">
		<span class="sst-selected-tic"><?php esc_html_e( 'Using site default', 'simple-sales-tax' ); ?></span>
		<input type="hidden" name="wootax_tic" class="sst-tic-input" value="<?php echo esc_attr( $current_tic ); ?>">
		<button type="button" class="button sst-select-tic">
			<?php esc_html_e( 'Select', 'simple-sales-tax' ); ?>
		</button>
	</div>

	<p class="description">
		<?php
		esc_html_e(
			'This TIC will be used as the default for all products in this category.',
			'simple-sales-tax'
		);
		?>
	</p>

<?php if ( $is_edit ) : ?>
	</td>
	</tr>
<?php else : ?>
	</div>
<?php endif; ?>

<?php require __DIR__ . '/html-select-tic-modal.php'; ?>
