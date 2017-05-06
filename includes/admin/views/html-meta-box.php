<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
} ?>

<p><strong><?php _e( 'TaxCloud Status', 'simplesalestax' ); ?></strong> <?php sst_tip( __( "Status of order in TaxCloud ('Pending', 'Captured', or 'Refunded'). All orders should eventually be 'Captured' or 'Refunded.'", 'simplesalestax' ) ); ?> <br> <?php echo $status; ?></p>

<p><strong><?php _e( 'Exemption Certificate', 'simplesalestax' ); ?></strong> <?php sst_tip( __( "The customer's exemption certificate, if applicable.", 'simplesalestax' ) ); ?> <br> <button type="button" class="button button-primary sst-view-certificate"><?php _e( 'View', 'simplesalestax' ); ?></button></p>