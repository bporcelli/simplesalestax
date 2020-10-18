jQuery(function($) {
    $(document).ajaxComplete(function(event, jqXHR, options) {
        var events = [
            'wcv_json_add_variation',
            'wcv_json_link_all_variations'
        ];

        events.forEach(function(event) {
            if (options.data.indexOf(event) >= 0) {
                setTimeout(function() {
                    $(document.body).trigger('woocommerce_variations_loaded');
                });
            }
        });
    });
});