<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
?>

</table>

<table id="address_table" class="wp-list-table striped widefat">
	<thead>
	<tr>
		<th>
			<?php esc_html_e( 'Address 1', 'simple-sales-tax' ); ?>
			<?php sst_tip( __( 'Line 1 of your business address.', 'simple-sales-tax' ) ); ?>
		</th>
		<th>
			<?php esc_html_e( 'Address 2', 'simple-sales-tax' ); ?>
			<?php sst_tip( __( 'Line 2 of your business address.', 'simple-sales-tax' ) ); ?>
		</th>
		<th>
			<?php esc_html_e( 'City', 'simple-sales-tax' ); ?>
			<?php sst_tip( __( 'The city in which your business operates.', 'simple-sales-tax' ) ); ?>
		</th>
		<th>
			<?php esc_html_e( 'State', 'simple-sales-tax' ); ?>
			<?php sst_tip( __( 'The state where your business is located.', 'simple-sales-tax' ) ); ?>
		</th>
		<th>
			<?php esc_html_e( 'ZIP Code', 'simple-sales-tax' ); ?>
			<?php sst_tip( __( '5 or 9-digit ZIP code of your business address.', 'simple-sales-tax' ) ); ?>
		</th>
		<th>
			<?php esc_html_e( 'Default?', 'simple-sales-tax' ); ?>
			<?php
			sst_tip(
				esc_html__(
					'Check this if you want this address to be used as a default "Shipment Origin Address" for your products. You must have at least one default address.',
					'simple-sales-tax'
				)
			);
			?>
		</th>
	</tr>
	</thead>
	<tfoot>
	<tr>
		<th colspan="6">
			<button class="wp-core-ui button-secondary sst-address-add">
				<?php esc_html_e( 'Add Address', 'simple-sales-tax' ); ?>
			</button>
		</th>
	</tr>
	</tfoot>
	<tbody></tbody>
</table>

<script type="text/html" id="tmpl-sst-address-row-blank">
	<tr id="sst-address-row-blank">
		<td colspan="6">
			<p class="main"><?php esc_html_e( 'No addresses added.', 'simple-sales-tax' ); ?></p>
		</td>
	</tr>
</script>

<script type="text/html" id="tmpl-sst-address-row">
	<tr data-id="{{ data.ID }}">
		<td>
			<input type="text" name="addresses[{{ data.ID }}][Address1]" data-attribute="Address1"
				   value="{{ data.Address1 }}">
			<div class="row-actions">
				<a href="#" class="sst-address-delete"><?php esc_html_e( 'Remove', 'simple-sales-tax' ); ?></a>
			</div>
		</td>
		<td>
			<input type="text" name="addresses[{{ data.ID }}][Address2]" data-attribute="Address2"
				   value="{{ data.Address2 }}" placeholder="(Optional)">
		</td>
		<td>
			<input type="text" name="addresses[{{ data.ID }}][City]" data-attribute="City" value="{{ data.City }}">
		</td>
		<td>
			<select name="addresses[{{ data.ID }}][State]" data-attribute="State">
				<?php
				$options = array_merge(
					array(
						'' => __( 'Select One', 'simple-sales-tax' ),
					),
					WC()->countries->get_states( 'US' )
				);

				foreach ( $options as $value => $text ) {
					printf( '<option value="%s">%s</option>', esc_attr( $value ), esc_attr( $text ) );
				}
				?>
			</select>
		</td>
		<td>
			<input type="number" name="addresses[{{ data.ID }}][Zip5]" data-attribute="Zip5" value="{{ data.Zip5 }}"> -
			<input type="number" name="addresses[{{ data.ID }}][Zip4]" data-attribute="Zip4" value="{{ data.Zip4 }}">
		</td>
		<td>
			<input type="checkbox" name="addresses[{{ data.ID }}][Default]" data-attribute="Default" value="yes">
		</td>
	</tr>
</script>

<table class="form-table">
