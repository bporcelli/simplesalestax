<!-- Template for "Start Plus Trial" page -->
<div class="wrap get-plus-wrap">
	<h2>WooTax Plus</h2>
	<?php if ( get_option( 'wootax_plus_member_id' ) ): ?>
		<h3>Thank you!</h3>
		<p>Thank you for trying WooTax Plus. Your 30 day trial has now begun.</p>
		<p>Click "Install Plus" to download the WooTax Plus plugin and start reaping the benefits of your membership.</p>
		<p><a class="wp-core-ui button-primary" href="<?php echo wp_nonce_url( self_admin_url( 'update.php?action=install-plugin&plugin=wootax-plus' ), 'install-plugin_wootax-plus' ); ?>">Install Plus</a></p>
	<?php else: ?>
		<h3>Please enter your name and email address to start your 30 day trial</h3>
		
		<form action="" method="POST">
			<table class="form-table wootax-settings">
				<tr>
					<th>First Name <?php wootax_tip( 'Your first name.' ); ?></th>
					<td>
						<input type="text" name="first_name" value="<?php echo isset( $_REQUEST['first_name'] ) ? $_REQUEST['first_name'] : ''; ?>" />
					</td>
				</tr>
				<tr>
					<th>Last Name <?php wootax_tip( 'Your last name.' ); ?></th>
					<td>
						<input type="text" name="last_name" value="<?php echo isset( $_REQUEST['last_name'] ) ? $_REQUEST['last_name'] : ''; ?>" />
					</td>
				</tr>
				<tr>
					<th>Email <?php wootax_tip( 'Your email address. This will be associated with your WooTax Plus account if you choose to continue your membership after your trial expires.' ); ?></th>
					<td>
						<input type="text" name="email" value="<?php echo isset( $_REQUEST['email'] ) ? $_REQUEST['email'] : wootax_get_notification_email(); ?>" />
					</td>
				</tr>
			</table>
			<p class="submit">
				<input type="hidden" name="wootax-start-trial" value="1" />
				<input type="hidden" name="_wpnonce" value="<?php echo wp_create_nonce( 'wootax-start-trial' ); ?>" />
				<button type="submit" class="wp-core-ui button-primary">Submit</button>
			</p>
		</form>
	<?php endif; ?>
</div>