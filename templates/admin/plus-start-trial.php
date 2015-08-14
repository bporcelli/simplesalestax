<!-- Template for "Start Plus Trial" page -->
<?php global $trial_duration; ?>
<div class="wrap get-plus-wrap">
	<h2>WooTax Plus</h2>
	<h3>Enter your name and email address to start your <?php echo $trial_duration; ?> trial</h3>
		
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
</div>