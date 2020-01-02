<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
} ?>

<div>
    <h3>
		<?php _e( 'TaxCloud Status', 'simple-sales-tax' ); ?>
		<?php sst_tip(
			__(
				"Status of order in TaxCloud ('Pending', 'Captured', or 'Refunded'). All orders should eventually be 'Captured' or 'Refunded.'",
				'simple-sales-tax'
			)
		); ?>
    </h3>
	<?php echo esc_html( $status ); ?>
</div>
<div>
    <h3>
		<?php _e( 'Exemption Certificate', 'simple-sales-tax' ); ?>
		<?php sst_tip( __( "The customer's exemption certificate, if applicable.", 'simple-sales-tax' ) ); ?>
    </h3>
    <button type="button" class="button button-primary sst-view-certificate">
		<?php _e( 'View', 'simple-sales-tax' ); ?>
    </button>
</div>