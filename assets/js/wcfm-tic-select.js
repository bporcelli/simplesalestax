/* global jQuery, wcfm_tic_select_data */
jQuery(function($) {
    function reindexInput($input, index) {
        var oldId = $input.attr('id');
        var newId = 'variations_wootax_tic_' + index;

        $input.attr('name', 'variations[' + index +'][wootax_tic]');
        $input.attr('id', newId);

        $('label[for="' + oldId + '"]').attr('for', newId);
    }

    function initVariationInputs() {
        $('#variations .sst-tic-input:not(.value-initialized)').each(function() {
            var $input = $(this);
            var index = $input
                .closest('.multi_input_block')
                .find('input.variation_id')
                .attr('id')
                .substr('variation_id_'.length + 1);

            reindexInput($input, index);

            if (index in wcfm_tic_select_data.variation_tics) {
                $input
                    .val(wcfm_tic_select_data.variation_tics[index])
                    .trigger('change');
            }

            $input.addClass('value-initialized');
        });
    }

    initVariationInputs();

    // Initialize new TIC select inputs when a variation is added.
    $(document).on('click', '#variations .add_multi_input_block', function() {
        // Wrap callback in setTimeout to give WCFM time to render the
        // new TIC select field.
        setTimeout(function() {
            $('#variations .multi_input_block:last-child .sst-select-tic').removeClass('initialized');

            if ('function' === typeof initiateTip) {
                initiateTip();
            }

            initVariationInputs();

            $(document.body).trigger('wcfm_variation_added');
        });
    });

    // Reindex TIC select inputs after variation sort order changes. 
    $('#variations').on('sortupdate', function() {
        $(this).children('.multi_input_block').each(function() {
            reindexInput(
                $(this).find('.sst-tic-input'),
                $(this).index()
            );
        });
    });
});
