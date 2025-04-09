jQuery(document).ready(function ($) {
    $('.link-now').on('click', function () {
        var button = $(this);
        var postId = button.data('post');
        var keyword = button.data('keyword');
        var targetUrl = button.data('url');

        button.prop('disabled', true).text('Linking...');

        $.ajax({
            url: seoai_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'seoai_link_keyword',
                post_id: postId,
                keyword: keyword,
                target_url: targetUrl,
            },
            success: function (response) {
                if (response.success) {
                    button.text('Linked').css('background-color', '#4CAF50');
                    button.closest('li').remove(); // Remove the suggestion after linking
                } else {
                    alert('Error: ' + response.data);
                    button.prop('disabled', false).text('Link Now');
                }
            }
        });
    });
});
