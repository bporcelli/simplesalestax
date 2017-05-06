<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
} ?>

</div> <!-- close "shipping class" options group -->

<div class="options_group">
	<p class="form-field" id="shipping_origin_field">
		<label for="_wootax_origin_addresses[]"><?php _e( 'Origin addresses', 'simplesalestax' ); ?></label>
		<select class="wc-enhanced-select" name="_wootax_origin_addresses[]" multiple="multiple">
		<?php
			// Output select box
			$origin_addresses = SST_Product::get_origin_addresses( $post->ID );
			$selected_ids     = array_keys( $origin_addresses );

			if ( is_array( $addresses ) && count( $addresses ) > 0 ) {
				foreach ( $addresses as $id => $address ) {
					echo '<option value="'. $id .'" '. selected( in_array( $id, $selected_ids ) ) .'>'. SST_Addresses::format( $address ) .'</option>';
				}
			} else {
				echo sprintf( '<option value="">%s</option>', __( 'There are no addresses to select.', 'simplesalestax' ) );
			}
		?>
		</select>
		<?php sst_tip( __( 'Used by Simple Sales Tax for tax calculations. These are the addresses from which this product will be shipped.', 'simplesalestax' ) ); ?>
	</p>
