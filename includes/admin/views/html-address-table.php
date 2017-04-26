<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
?>

</table>

<table id="address_table" class="wp-list-table striped widefat">
	<thead>
		<tr>
			<th><span>Address 1</span> <?php sst_tip( "Line 1 of your business address." ) ?></th>
			<th><span>Address 2</span> <?php sst_tip( "Line 2 of your business address." ) ?></th>
			<th><span>City</span> <?php sst_tip( "The city in which your business operates." ) ?></th>
			<th><span>State</span> <?php sst_tip( "The state where your business is located." ); ?></th>
			<th><span>ZIP Code</span> <?php sst_tip( "5 or 9-digit ZIP code of your business address." ); ?></th>
			<th><span>Default?</span> <?php sst_tip( "Check this if you want this address to be used as a default 'Shipment Origin Address' for your products. You must have at least one default address." ); ?></th>
		</tr>
	</thead>
	<tfoot>
		<tr>
			<th colspan="6">
				<button class="wp-core-ui button-secondary sst-address-add">Add Address</button>
			</th>
		</tr>
	</tfoot>
	<tbody></tbody>
</table>

<script type="text/html" id="tmpl-sst-address-row-blank">
	<tr id="sst-address-row-blank">
		<td colspan="6">
			<p class="main"><?php _e( 'No addresses added.', 'simplesalestax' ); ?></p>
		</td>
	</tr>
</script>

<script type="text/html" id="tmpl-sst-address-row">
	<tr data-id="{{ data.ID }}">
		<td>
			<input type="text" name="addresses[{{ data.ID }}][Address1]" data-attribute="Address1" value="{{ data.Address1 }}">
			<div class="row-actions">
				<a href="#" class="sst-address-delete"><?php _e( 'Remove', 'simplesalestax' ); ?></a>
			</div>
		</td>
		<td>
			<input type="text" name="addresses[{{ data.ID }}][Address2]" data-attribute="Address2" value="{{ data.Address2 }}" placeholder="(Optional)">
		</td>
		<td>
			<input type="text" name="addresses[{{ data.ID }}][City]" data-attribute="City" value="{{ data.City }}">
		</td>
		<td>
			<select name="addresses[{{ data.ID }}][State]" data-attribute="State">
				<?php
					$options = array_merge( array( 
						'' => __( 'Select One', 'simplesalestax' ),
					), WC()->countries->get_states( 'US' ) );

					foreach ( $options as $value => $text ) {
						printf( '<option value="%s">%s</option>', $value, $text );
					}
				?>
			</select>
		</td>
		<td>
			<input type="number" name="addresses[{{ data.ID }}][Zip5]" data-attribute="Zip5" value="{{ data.Zip5 }}"> - <input type="number" name="addresses[{{ data.ID }}][Zip4]" data-attribute="Zip4" value="{{ data.Zip4 }}">
		</td>
		<td>
			<input type="checkbox" name="addresses[{{ data.ID }}][Default]" data-attribute="Default" value="yes">
		</td>
	</tr>
</script>

<table class="form-table">