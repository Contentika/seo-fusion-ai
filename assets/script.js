jQuery(document).ready(function($) {
    // SEO Analysis Progress Bar
    function updateProgressBar(progress) {
        $('#seoai-progress-bar').css('width', progress + '%').text(progress + '%');
    }

    // Handle scan status updates
    function checkScanStatus() {
        $.ajax({
            url: seoai_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'seoai_check_scan_status'
            },
            success: function(response) {
                if (response.success) {
                    updateProgressBar(response.data.progress);
                    if (response.data.status === 'scanning') {
                        setTimeout(checkScanStatus, 5000); // Check again in 5 seconds
                    }
                }
            }
        });
    }

    // Initialize tooltips
    $('.seoai-tooltip').tooltip();

    // Handle suggestion filtering
    $('#seoai-filter-suggestions').on('input', function() {
        var searchTerm = $(this).val().toLowerCase();
        $('.seoai-suggestion-item').each(function() {
            var text = $(this).text().toLowerCase();
            $(this).toggle(text.includes(searchTerm));
        });
    });

    // Sort suggestions
    $('#seoai-sort-suggestions').on('change', function() {
        var sortBy = $(this).val();
        var $container = $('#seoai-suggestions-list');
        var $items = $container.children('.seoai-suggestion-item').get();

        $items.sort(function(a, b) {
            if (sortBy === 'relevance') {
                return $(b).data('relevance') - $(a).data('relevance');
            } else {
                return $(a).find('.seoai-keyword').text().localeCompare($(b).find('.seoai-keyword').text());
            }
        });

        $container.empty();
        $items.forEach(function(item) {
            $container.append(item);
        });
    });
});
