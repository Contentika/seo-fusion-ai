jQuery(document).ready(function ($) {
    let typingTimer;
    let doneTypingInterval = 1000; // 1-second delay

    function getEditorContent() {
        if (window.wp && wp.data) {
            return wp.data.select("core/editor").getEditedPostContent();
        }
        return "";
    }

    function setEditorContent(content) {
        if (window.wp && wp.data && wp.data.dispatch) {
            wp.data.dispatch("core/editor").editPost({ content: content });
        }
    }

    function fetchLinkSuggestions() {
        let content = getEditorContent();

        if (content.length > 20) {
            $.ajax({
                url: seoai_ajax.ajax_url,
                type: "POST",
                data: {
                    action: "seoai_real_time_suggestions",
                    content: content
                },
                success: function (response) {
                    if (response.success) {
                        $("#seoai-link-suggestions").html(response.data.html);
                    }
                }
            });
        }
    }

    // Detect typing in Gutenberg
    wp.data.subscribe(function () {
        clearTimeout(typingTimer);
        typingTimer = setTimeout(fetchLinkSuggestions, doneTypingInterval);
    });

    // Insert link when "Link" button is clicked and remove from list
    $(document).on("click", ".seoai-insert-link", function () {
        let keyword = $(this).data("keyword");
        let url = $(this).data("url");
        let content = getEditorContent();

        // Replace first occurrence of the keyword with a linked version
        let linkedText = `<a href="${url}" target="_blank">${keyword}</a>`;
        let newContent = content.replace(new RegExp(`\\b${keyword}\\b`, "i"), linkedText);

        setEditorContent(newContent);

        // Remove the linked keyword from the suggestion list
        $(this).closest("li").remove();

        // If no more suggestions, display a message
        if ($("#seoai-link-suggestions ul li").length === 0) {
            $("#seoai-link-suggestions").html("✅ No more link suggestions.");
        }
    });
});
