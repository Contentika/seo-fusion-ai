<?php


// Schedule scanning cron job
function seoai_schedule_scanning_cron() {
    if (!wp_next_scheduled('seoai_scan_posts_cron')) {
        wp_schedule_event(time(), 'every_five_minutes', 'seoai_scan_posts_cron');
    }
}
add_action('wp', 'seoai_schedule_scanning_cron');

// Add custom cron interval
function seoai_custom_cron_intervals($schedules) {
    $schedules['every_five_minutes'] = [
        'interval' => 300, // 5 minutes
        'display'  => __('Every 5 Minutes')
    ];
    return $schedules;
}
add_filter('cron_schedules', 'seoai_custom_cron_intervals');

// Scan posts in batches
function seoai_scan_posts_batch() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'seoai_scanned_posts';

    // Check if scanning was stopped
    if (seoai_get_scan_status() === 'stopped') {
        return;
    }

    do {
        // Stop if scanning was manually stopped
        if (seoai_get_scan_status() === 'stopped') {
            return;
        }

        // Call the function to suggest internal links
        seoai_analyze_post_content();

        // Get the updated total scanned posts
        $total_scanned_posts = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
        $total_site_posts = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type = 'post'");

    } while ($total_scanned_posts < $total_site_posts);

    // Mark scanning as completed
    seoai_set_scan_status('completed');
}
add_action('seoai_scan_posts_cron', 'seoai_scan_posts_batch');

// AJAX handler for starting scan
function seoai_start_scan_ajax() {
    seoai_set_scan_status('scanning'); // Set status to scanning
    update_option('seoai_scan_progress', 0); // Reset progress

    seoai_scan_posts_batch(); // Run the first batch immediately
    seoai_schedule_scanning_cron(); // Ensure WP Cron is scheduled

    wp_send_json_success(['message' => '🔄 SEO Analysis started...']);
}
add_action('wp_ajax_seoai_start_scan', 'seoai_start_scan_ajax');

// AJAX handler for stopping scan
function seoai_stop_scan_ajax() {
    seoai_set_scan_status('stopped'); // Set scan status to stopped
    wp_send_json_success('✅ SEO Analysis stopped.');
}
add_action('wp_ajax_seoai_stop_scan', 'seoai_stop_scan_ajax');

// Show scan status in admin
function seoai_show_scan_status() {
    $status = get_option('seoai_scan_status', 'not_started');

    if ($status === 'scanning') {
        echo '<div class="notice notice-info"><p>🔄 Scanning in progress... Please wait.</p></div>';
    }
    
    if ($status === 'completed') {
        ?>
        <div class="notice notice-success">
            <p>✅ Scanning completed! <a id="seoai-reset-scan" style='cursor:pointer;' class="">Cancel</a></p>
            
        </div>
        <script type="text/javascript">
            document.getElementById('seoai-reset-scan').addEventListener('click', function() {
                var data = {
                    action: 'seoai_reset_scan_status',
                    security: '<?php echo wp_create_nonce("seoai_reset_scan"); ?>'
                };

                fetch(ajaxurl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams(data)
                }).then(response => response.json()).then(response => {
                    if (response.success) {
                        location.reload(); // Refresh to update status
                    }
                });
            });
        </script>
        <?php
    }
}
add_action('admin_notices', 'seoai_show_scan_status');

function seoai_reset_scan_status() {
    check_ajax_referer('seoai_reset_scan', 'security');

    update_option('seoai_scan_status', 'not_started');

    wp_send_json_success();
}
add_action('wp_ajax_seoai_reset_scan_status', 'seoai_reset_scan_status');

// Get scan status
function seoai_get_scan_status() {
    return get_option('seoai_scan_status', 'not_started');
}

// Set scan status
function seoai_set_scan_status($status) {
    update_option('seoai_scan_status', $status);
}

// Analyze post content
function seoai_analyze_post_content($post) {
    // Logic to analyze the post and find internal link opportunities
    global $wpdb;

    // Example: Store the scanned post ID (you might already have this)
    $table_name = $wpdb->prefix . 'seoai_scanned_posts';
    $wpdb->insert($table_name, ['post_id' => $post->ID]);
}

// Get all published posts
function seoai_get_published_posts() {
    global $wpdb;
    return $wpdb->get_results("SELECT * FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type = 'post'");
}

// Create database tables on plugin activation
function seoai_create_tables() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE IF NOT EXISTS " . $wpdb->prefix . "seoai_scanned_posts (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        post_id bigint(20) NOT NULL,
        scan_date datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}
register_activation_hook(__FILE__, 'seoai_create_tables');

function seoai_extract_keywords($text) {
    $api_key = get_option('seoai_ai_api_key', ''); 
    $url = 'https://api.openai.com/v1/chat/completions';

    // Convert text to lowercase and clean
    $text = strtolower(strip_tags($text));
    $text = preg_replace('/[^a-z\s]/', '', $text);

    $prompt = "Extract the most meaningful words, phrases, and sentences from the following text that can be used for internal linking. Provide a maximum of 5 suggestions, including longer phrases where appropriate:\n\n$text";

    $data = [
        "model" => "gpt-4-turbo",
        "messages" => [["role" => "user", "content" => $prompt]],
        "temperature" => 0.3
    ];

    $args = [
        'headers' => [
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer ' . $api_key
        ],
        'body'    => json_encode($data),
        'timeout' => 15
    ];

    $response = wp_remote_post($url, $args);
    // ...rest of the existing function code...
}

function seoai_find_related_posts($keyword, $exclude_id) {
    global $wpdb;
    
    $escaped_keyword = addslashes($keyword);

    $related_posts = $wpdb->get_results($wpdb->prepare("
        SELECT ID, post_title FROM {$wpdb->posts}
        WHERE post_status = 'publish' AND post_type = 'post'
        AND (post_content REGEXP %s OR post_title REGEXP %s OR post_content LIKE %s OR post_title LIKE %s)
        AND ID != %d
        LIMIT 5
    ", $escaped_keyword, $escaped_keyword, "%{$keyword}%", "%{$keyword}%", $exclude_id));

    error_log("Checking related posts for: $keyword - Found: " . count($related_posts));
    return $related_posts;
}

function seoai_check_link($url) {
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return false;
    }

    $headers = @get_headers($url);
    if (!$headers || strpos($headers[0], '200') === false) {
        return false;
    }

    return true;
}

function seoai_is_valid_url($url) {
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return false;
    }

    $response = wp_remote_head($url, ['timeout' => 5]);
    if (is_wp_error($response)) {
        return false;
    }

    $status_code = wp_remote_retrieve_response_code($response);
    return ($status_code >= 200 && $status_code < 400);
}

function seoai_is_internal_link($url) {
    $site_url = get_site_url();
    return strpos($url, $site_url) === 0;
}

// AJAX handler for suggestions
add_action('wp_ajax_seoai_link_keyword', 'seoai_link_keyword');
function seoai_link_keyword() {
    if (!isset($_POST['post_id'], $_POST['keyword'], $_POST['target_url'])) {
        wp_send_json_error(['message' => 'Missing parameters']);
    }

    $post_id = intval($_POST['post_id']);
    $keyword = sanitize_text_field($_POST['keyword']);
    $target_url = esc_url_raw($_POST['target_url']);

    // Get post content
    $post = get_post($post_id);
    if (!$post) {
        wp_send_json_error(['message' => 'Post not found']);
    }

    $content = $post->post_content;

    // Check if keyword exists in content
    if (strpos($content, $keyword) === false) {
        wp_send_json_error(['message' => "Keyword '$keyword' not found in post"]);
    }

    // Replace first occurrence of the keyword with a link
    $linked_keyword = "<a href='$target_url'>$keyword</a>";
    $content = preg_replace('/\b' . preg_quote($keyword, '/') . '\b/', $linked_keyword, $content, 1);

    // Update the post with the new content
    $updated = wp_update_post([
        'ID' => $post_id,
        'post_content' => $content
    ], true);

    if (is_wp_error($updated)) {
        wp_send_json_error(['message' => 'Failed to update post']);
    }

    wp_send_json_success(['message' => 'Link inserted successfully']);
}

// AJAX handler for real-time suggestions
add_action('wp_ajax_seoai_real_time_suggestions', 'seoai_real_time_suggestions');
function seoai_real_time_suggestions() {
    if (!isset($_POST['content'])) {
        wp_send_json_error(['message' => 'No content received']);
    }

    $content = wp_unslash($_POST['content']); // Keep HTML intact
    $keywords = seoai_extract_keywords($content);

    // Find already linked keywords
    preg_match_all('/<a[^>]*>(.*?)<\/a>/', $content, $matches);
    $linked_keywords = array_map('strip_tags', $matches[1]); // Extract linked text

    // Filter out already linked keywords
    $unlinked_keywords = array_diff($keywords, $linked_keywords);

    $suggestions = [];
    foreach ($unlinked_keywords as $keyword) {
        $related_posts = seoai_find_related_posts($keyword, 0);
        foreach ($related_posts as $related) {
            $suggestions[] = [
                'keyword' => esc_html($keyword),
                'target_title' => esc_html($related->post_title),
                'target_url' => esc_url(get_permalink($related->ID))
            ];
        }
    }

    if (empty($suggestions)) {
        wp_send_json_success(['html' => '✅ No internal link suggestions yet. Keep writing!']);
    }

    // Build HTML for the suggestions
    $html = '<h3>🔗 Internal Link Suggestions:</h3><ul>';
    foreach ($suggestions as $suggestion) {
        $html .= "<li>
                    <strong>{$suggestion['keyword']}</strong> 
                    ➝ <a href='{$suggestion['target_url']}' target='_blank'>{$suggestion['target_title']}</a> 
                    <button class='seoai-insert-link' data-keyword='{$suggestion['keyword']}' data-url='{$suggestion['target_url']}'>Link</button>
                  </li>";
    }
    $html .= '</ul>';

    wp_send_json_success(['html' => $html]);
}

// Scan for broken links
function seoai_scan_broken_links() {
    global $wpdb;
    $posts = $wpdb->get_results("SELECT ID, post_title, post_content FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type = 'post'");

    $broken_links = [];

    foreach ($posts as $post) {
        if (preg_match_all('/<a href="([^"]+)">/', $post->post_content, $matches)) {
            foreach ($matches[1] as $url) {
                if (!seoai_check_link($url)) {
                    $broken_links[] = "⚠️ Broken link detected in '{$post->post_title}' (ID: {$post->ID}): $url";
                }
            }
        }
    }

    if (empty($broken_links)) {
        echo "✅ No broken links found!";
    } else {
        echo implode("<br>", $broken_links);
    }
}

// Scan for orphan pages
function seoai_scan_orphan_pages() {
    global $wpdb;

    // Get all published posts
    $posts = $wpdb->get_results("SELECT ID, post_title, post_content FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type = 'post'");
    $all_post_ids = wp_list_pluck($posts, 'ID');
    $linked_posts = [];

    // Base URL of the site
    $site_url = get_site_url();

    // Check if any post is being linked to by other posts
    foreach ($posts as $post) {
        $absolute_url = get_permalink($post->ID);
        $relative_url = str_replace($site_url, '', $absolute_url);
        $post_id_pattern = "/<a\s+[^>]*href=['\"](" . preg_quote($absolute_url, '/') . "|" . preg_quote($relative_url, '/') . ")['\"][^>]*>/i";

        foreach ($posts as $other_post) {
            if ($other_post->ID !== $post->ID && preg_match($post_id_pattern, $other_post->post_content)) {
                $linked_posts[] = $post->ID;
                break;
            }
        }
    }

    // Find orphan posts
    $orphan_posts = array_diff($all_post_ids, array_unique($linked_posts));

    if (empty($orphan_posts)) {
        echo "✅ No orphan pages found!";
    } else {
        echo "🏴 Orphan pages detected:<br>";
        foreach ($orphan_posts as $orphan_id) {
            $orphan_post = get_post($orphan_id);
            echo "- <a href='" . get_permalink($orphan_id) . "' target='_blank'>{$orphan_post->post_title}</a> (ID: {$orphan_id})<br>";
        }
    }
}

// Add meta box for suggestions
function seoai_add_meta_box() {
    add_meta_box(
        'seoai_suggestions_meta_box',
        'SEO Fusion AI Suggestions',
        'seoai_display_meta_box',
        'post',
        'side'
    );
}
add_action('add_meta_boxes', 'seoai_add_meta_box');

function seoai_display_meta_box() {
    echo '<div id="seoai-link-suggestions">Start typing to get internal link suggestions...</div>';
}
