<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
} ?>

<div>
    <h3>
		<?php _e( 'TaxCloud Status', 'simplesalestax' ); ?>
		<?php sst_tip(
			__(
				"Status of order in TaxCloud ('Pending', 'Captured', or 'Refunded'). All orders should eventually be 'Captured' or 'Refunded.'",
				'simplesalestax'
			)
		); ?>
    </h3>
	<?php echo $status; ?>
</div>
<div>
    <h3>
		<?php _e( 'Exemption Certificate', 'simplesalestax' ); ?>
		<?php sst_tip( __( "The customer's exemption certificate, if applicable.", 'simplesalestax' ) ); ?>
    </h3>
    <button type="button" class="button button-primary sst-view-certificate">
		<?php _e( 'View', 'simplesalestax' ); ?>
    </button>
</div>