<?php
/**
 * Admin View: Notice - Update
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<p><strong><?php _e( 'Simple Sales Tax data update.', 'simplesalestax' ); ?></strong> <?php _e( 'We need to update your database to the latest version.', 'simplesalestax' ); ?></p>
<p class="submit"><a href="<?php echo esc_url( admin_url( '?do_sst_update=true' ) ); ?>" class="wc-update-now button-primary"><?php _e( 'Run the updater', 'simplesalestax' ); ?></a></p>
