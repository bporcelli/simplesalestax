<?php

/**
 * Template for certificate table body. You may override this template by copying it
 * to THEME_PATH/sst/checkout/certificate-list.php.
 *
 * @since 5.0
 * @author Brett Porcelli
 * @package Simple Sales Tax
 * @version 1.0
 */ 

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

// Get the ID of the selected certificate
$selected = isset( $_POST[ 'certificate_id' ] ) ? $_POST[ 'certificate_id' ] : '';

if ( empty( $selected ) ) {
	$ids = array_keys( $certificates );
	$selected = $ids[0];
}

// Output a row for each certificate
$i = 1;

foreach ( $certificates as $id => $certificate ):

	$view_url = $certificate->get_view_url(); ?>

	<tr id="<?php echo $id; ?>">
		<td>
			<input type="radio" name="certificate_id" value="<?php echo $id; ?>" <?php checked( $id, $selected ); ?>>
		</td>
		<td>
			<abbr title="<?php echo $id; ?>"><?php echo $i++; ?></abbr>
		</td>
	    <td>
	    	<?php echo $certificate->PurchaserName ?>
	    </td>
	    <td>
			<?php echo date( 'm/d/Y', strtotime( $certificate->CreatedDate ) ); ?>
		</td>
	    <td>
	    	<?php echo "<a href='$view_url' class='sst-action popup-link mfp-iframe' target='_blank'>View</a>"; ?>
	    </td>
	</tr>

<?php endforeach; ?>