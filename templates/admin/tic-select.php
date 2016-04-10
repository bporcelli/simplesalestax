<?php 
/**
 * Displays a TIC select box for a product or variation
 *
 * @var $current_tic - selected TIC
 * @var $tic_list - list of TICs that can be selected
 * @var $product_id - ID of product or variation for which TIC will apply
 * @var $is_variation - boolean; true if TIC select is for a product variation
 */
global $current_tic, $tic_list, $product_id, $is_variation; 

$field = "wootax_set_tic_" . $product_id; 

if ( $is_variation ) {
	$label = 'TIC: <a href="https://taxcloud.net/tic/default.aspx" target="_blank">View Full List</a>';
	$class = 'form-row form-row-full';
} else {
	$label = 'TIC';
	$class = 'form-field';
} ?>

<p class="<?php echo $class; ?> wootax_tic">
	<label for="wootax_tic"><?php echo $label; ?></label>

	<input id="<?php echo $field; ?>" name="<?php echo $field; ?>" value="<?php echo $current_tic; ?>" />
	
	<?php if ( !$is_variation ): ?>
	<span class="description">
		<a href="https://taxcloud.net/tic/default.aspx" target="_blank">View Full List</a>
	</span>
	<?php endif; ?>

	<script type="text/javascript">
		jQuery(function() {
			var data = eval('(<?php echo json_encode($tic_list); ?>)');
			var is_variation = '<?php echo $is_variation; ?>';

			jQuery('#<?php echo $field; ?>').select2({
				placeholder: is_variation == '1' ? 'Same as parent' : 'Use site default',
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
</p>