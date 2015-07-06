<?php 
/**
 * Displays a TIC select box for a product category
 *
 * @var $current_tic - selected TIC
 * @var $tic_list - list of TICs that can be selected
 * @var $is_edit - boolean; true if we are on the "Edit Category" screen
 */
global $current_tic, $tic_list, $is_edit; ?>

<?php if ( $is_edit ): ?>
<tr class="form-field">
	<th>TIC (Taxability Information Code)</th>
	<td>
<?php endif; ?>

<div class="form-field">
	<?php if ( !$is_edit ): ?>
	<label>TIC (Taxability Information Code)</label>
	<?php endif; ?>
	<input type="text" name="wootax_set_tic" id="wootax_set_tic" value="<?php echo $current_tic; ?>" />
    <p class="description">This TIC will be assigned to all products in this category. <a href="https://taxcloud.net/tic/default.aspx" target="_blank">Full TIC List</a></p>
</div>

<?php if ( $is_edit ): ?>
	</td>
</tr>
<?php endif; ?>

<script type="text/javascript">
	jQuery(function() {
		var data = eval('(<?php echo json_encode($tic_list); ?>)');

		jQuery('#wootax_set_tic').select2({
			placeholder: 'Use site default',
			allowClear: true,
			width: 'resolve',
		    dropdownAutoWidth: true,
		    width: '470px',
			data: { 
				results: data, 
				text: "name" 
			}, 
		    formatSelection: function(item) { 
		        return item.name 
		    }, 
		    formatResult: function(item) { 
		        return item.name 
		    }
		}).on('select2-selecting', function(e) {
			/* Prevent user from selecting non-SSUTA TICs */
			if (e.val == 'disallowed')
				return false;
		});
	});
</script>