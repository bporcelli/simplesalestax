<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
?>

	<label class="alignleft">
		<span class="title"><?php esc_html_e( 'TIC', 'simple-sales-tax' ); ?></span>
		<span class="input-text-wrap">
			<span class="sst-selected-tic"><?php esc_html_e( 'Using site default', 'simple-sales-tax' ); ?></span>
			<input type="hidden" name="wootax_tic" class="sst-tic-input" value="">
			<button type="button" class="button sst-select-tic">
				<?php esc_html_e( 'Select', 'simple-sales-tax' ); ?>
			</button>
		</span>
	</label>

<?php require __DIR__ . '/html-select-tic-modal.php'; ?>
