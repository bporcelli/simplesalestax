<?php
/**
 * Certificate list table template.
 *
 * @author  Brett Porcelli
 * @package Simple Sales Tax
 * @version 6.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// todo: consider b.w. compat with overridden templates. may need to make this a major release if not easily doable.
// todo: template that renders list of certificates with radio inputs.
// may want to keep add certificate button separate since it will behave differently in modal as compared to checkout page.
?>
<table id="sst-certificates" class="shop_table">
	<thead>
	<tr>
		<th><!-- Radio button column --></th>
		<th><?php esc_html_e( 'ID', 'simple-sales-tax' ); ?></th>
		<th><?php esc_html_e( 'Issued To', 'simple-sales-tax' ); ?></th>
		<th><?php esc_html_e( 'Date', 'simple-sales-tax' ); ?></th>
		<th><?php esc_html_e( 'Actions', 'simple-sales-tax' ); ?></th>
	</tr>
	</thead>
	<tfoot>
	<tr>
		<td colspan="5">
			<a href="#" class="button sst-certificate-add">
				<?php esc_html_e( 'Add Certificate', 'simple-sales-tax' ); ?>
			</a>
		</td>
	</tr>
	</tfoot>
	<tbody></tbody>
</table>

<script type="text/html" id="tmpl-sst-certificate-row-blank">
	<tr>
		<td colspan="5">
			<span>
				<?php
				esc_html_e(
					"There are no certificates to display. Click 'Add Certificate' to add one.",
					'simple-sales-tax'
				);
				?>
			</span>
		</td>
	</tr>
</script>

<script type="text/html" id="tmpl-sst-certificate-row">
	<tr data-id="{{ data.CertificateID }}">
		<td>
			<input type="radio" name="certificate_id" value="{{ data.CertificateID }}">
		</td>
		<td>{{ data.Index }}</td>
		<td>{{ data.PurchaserName }}</td>
		<td>{{ data.CreatedDate }}</td>
		<td>
			<a href="#" class="sst-certificate-view">View</a> | <a href="#"
																   class="sst-certificate-delete">Delete</a>
		</td>
	</tr>
</script>

<?php // todo: consider better separation of templates ?>
<?php require_once dirname( __DIR__ ) . '/frontend/views/html-view-certificate.php'; ?>
