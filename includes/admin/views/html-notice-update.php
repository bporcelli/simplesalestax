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
    <?php
    printf(
        '<a href="%1$s" class="wc-update-now button-primary">%2$s</a>',
        esc_url( admin_url( '?do_sst_update=true' ) ),
        esc_html__( 'Run the updater', 'simple-sales-tax' ),
    );
    ?>
</p>
