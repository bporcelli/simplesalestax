<?php
/**
 * Admin View: Notice - Updating
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
		__(
			'Your database is being updated in the background. This notice will disappear when the update is complete.',
			'simple-sales-tax'
		)
	);
	?>
</p>
