<?php
/**
 * Admin View: Notice - Update
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<p>
	<?php
	printf(
		'<strong>%s</strong> %s',
		__( 'Simple Sales Tax data update.', 'simplesalestax' ),
		__( 'We need to update your database to the latest version.', 'simplesalestax' )
	);
	?>
</p>
<p class="submit">
    <a href="<?php echo esc_url( admin_url( '?do_sst_update=true' ) ); ?>" class="wc-update-now button-primary">
		<?php _e( 'Run the updater', 'simplesalestax' ); ?>
    </a>
</p>
