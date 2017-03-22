<?php

/**
 * Template for certificates table. You may override this template by copying it
 * to THEME_PATH/sst/checkout/certificate-table.php.
 *
 * @since 5.0
 * @author Brett Porcelli
 * @package Simple Sales Tax
 * @version 1.0
 */ 

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
} 

$certificates_url = trailingslashit( get_permalink( get_option( 'woocommerce_myaccount_page_id' ) ) ) . '#saved-certificates'; ?>

<table id="certificates" class="shop_table">
	<thead>
		<tr>
			<th><!-- Radio button column --></th>
			<th>ID</th>
			<th>Issued To</th>
			<th>Date</th>
			<th>Actions</th>
		</tr>
	</thead>
	<tbody>
		<?php
			if ( count( $certificates ) == 0 ) {
				wc_get_template( 'certificate-list-empty.php', array(), 'sst/checkout/', $template_path . '/checkout/' );
			} else {
				wc_get_template( 'certificate-list.php', array( 
					'certificates' => $certificates,
					'checkout'     => $checkout
				), 'sst/checkout/', $template_path . '/checkout/' );
			}
		?>
	</tbody>
	<?php if ( $checkout && count( $certificates ) > 0 ): ?>
	<tfoot>
		<tr>
			<td colspan="5"><a target="_blank" href="<?php echo $certificates_url; ?>" class="button">Manage Certificates</a></td>
		</tr>
	</tfoot>
	<?php elseif ( count( $certificates ) > 0 ): ?>
	<tfoot>
		<tr>
			<td colspan="5">
				<?php wp_nonce_field( 'delete_certificate' ); ?>
				<input type="submit" class="button" name="delete_certificate" value="Delete Selected">
			</td>
		</tr>
	</tfoot>
	<?php endif; ?>
</table>