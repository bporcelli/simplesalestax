<?php 
/**
 * Displays a TIC select box for the bulk editor
 *
 * @var $tic_list - list of TICs that can be selected
 */
global $tic_list;

// Add "use site default" option
array_unshift( $tic_list, array( 'id' => 'default', 'name' => 'Use site default' ) );
?>

<label class="alignleft">
	<span class="title">TIC</span>
	<span class="input-text-wrap">
    	<input id="wootax_set_tic" name="wootax_set_tic" value="" />
   	</span>
</label>

<script type="text/javascript">
	jQuery(function() {
		var data = eval('(<?php echo json_encode($tic_list); ?>)');

		jQuery('#wootax_set_tic').select2({
			placeholder: '— No Change —',
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