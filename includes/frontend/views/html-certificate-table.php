<?php
/**
 * Template for tax exemption form. You may override this template by copying it
 * to THEME_PATH/sst/checkout/html-certificate-table.php.
 *
 * @since   5.0
 * @author  Brett Porcelli
 * @package Simple Sales Tax
 * @version 7.1.0
 */

// TODO: Consider deprecating and renaming template file.
// TODO: Alternate UI for full cert management on account page plus link there (parity with admin UI)
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

?>
<h3>
	<?php esc_html_e( 'Tax exemption', 'simple-sales-tax' ); ?>
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
		<?php
		woocommerce_form_field(
			'certificate_id',
			array(
				'type'        => 'select',
				'placeholder' => 'None',
				'options'     => $args['options'],
				'label'       => esc_html__( 'Exemption certificate', 'simple-sales-tax' ),
				'input_class' => array( 'sst-input' ),
			),
			$args['selected']
		);
		?>

		<div id="exempt_certificate_form" style="display: none;">
			<?php
			wc_get_template(
				'html-certificate-form.php',
				array(
					'allow_single' => true,
				),
				'sst/',
				SST()->path( 'includes/views/' )
			);
			?>
		</div>
	<?php else : ?>
		<p><?php esc_html_e( 'Please log in or register to apply an exemption certificate.' ); ?></p>
	<?php endif; ?>
</div>
