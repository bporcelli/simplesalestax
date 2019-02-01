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
		__( 'Simple Sales Tax data update.', 'simplesalestax' ),
		__(
			'Your database is being updated in the background. This notice will disappear when the update is complete.',
			'simplesalestax'
		)
	);
	?>
</p>
