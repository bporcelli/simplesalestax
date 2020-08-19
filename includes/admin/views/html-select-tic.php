<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( $is_variation ) {
	$class = 'form-row form-field form-row-full';
} else {
	$class = 'form-field';
}

?>

	<p class="<?php echo esc_attr( $class ); ?> wootax_tic">
		<label for="wootax_tic[<?php echo esc_attr( $product_id ); ?>]">
			<?php esc_html_e( 'TIC', 'simple-sales-tax' ); ?>
		</label>

		<?php
		if ( $is_variation ) :
			?>
			<br><?php endif; ?>

		<span class="sst-selected-tic"><?php esc_html_e( 'Using site default', 'simple-sales-tax' ); ?></span>
		<button type="button" class="button sst-select-tic">
			<?php esc_html_e( 'Select', 'simple-sales-tax' ); ?>
		</button>
		<input type="hidden" name="wootax_tic[<?php echo esc_attr( $product_id ); ?>]" class="sst-tic-input"
			   value="<?php echo esc_attr( $current_tic ); ?>">
	</p>

<?php require __DIR__ . '/html-select-tic-modal.php'; ?>
