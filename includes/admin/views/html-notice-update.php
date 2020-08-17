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
		__( 'Simple Sales Tax data update.', 'simple-sales-tax' ),
		__( 'We need to update your database to the latest version.', 'simple-sales-tax' )
	);
	?>
</p>
<p class="submit">
	<a href="<?php echo esc_url( admin_url( '?do_sst_update=true' ) ); ?>" class="wc-update-now button-primary">
		<?php _e( 'Run the updater', 'simple-sales-tax' ); ?>
	</a>
</p>
