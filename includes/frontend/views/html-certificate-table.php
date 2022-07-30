<?php
/**
 * Template for tax exemption form. You may override this template by copying it
 * to THEME_PATH/sst/checkout/html-certificate-table.php.
 *
 * @since   5.0
 * @author  Brett Porcelli
 * @package Simple Sales Tax
 * @version 7.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

?>
<h3>
	<?php esc_html_e( 'Tax exempt?', 'simple-sales-tax' ); ?>
	<input
		type="checkbox"
		name="tax_exempt"
		id="tax_exempt_checkbox"
		class="input-checkbox"
	 	value="1"
	 	<?php checked( $checked ); ?>>
</h3>
<div id="tax_details">
	<noscript>
		<p>
			<?php
			printf(
				'<strong>%s</strong> %s',
				esc_html__( 'Warning:', 'simple-sales-tax' ),
				esc_html__(
					'This interface will not function properly with JavaScript disabled. Please enable JavaScript to continue.',
					'simple-sales-tax'
				)
			);
			?>
		</p>
	</noscript>

	<?php if ( is_user_logged_in() ) : ?>
		<p>
			<?php
			esc_html_e(
				'Select an exemption certificate from the table below, or click "Add Certificate" and fill out the provided form.',
				'simple-sales-tax'
			);
			?>
		</p>
		<?php sst_render_certificate_table(); ?>
	<?php else : ?>
		<p><?php esc_html_e( 'Please log in or register.' ); ?></p>
	<?php endif; ?>
</div>
