<?php
// Add menu page and submenus 
function seoai_add_admin_menu() {
    add_menu_page(
        'SEO Fusion AI', 
        'SEO Fusion', 
        'manage_options', 
        'seoai_admin', 
        'seoai_admin_page', 
        'dashicons-admin-links',
        2
    );

    add_submenu_page(
        'seoai_admin',  
        'Scanned Posts', 
        'Scanned Posts', 
        'manage_options', 
        'seoai_scanned_posts', 
        'seoai_scanned_posts_page'
    );
    add_submenu_page(
        'seoai_admin',  
        'Orphan Pages', 
        'Orphan Pages', 
        'manage_options', 
        'seoai_orphan_pages', 
        'seoai_orphan_pages_page'
    );
    add_submenu_page(
        'seoai_admin',  
        'Broken Links', 
        'Broken Links', 
        'manage_options', 
        'seoai_broken_links', 
        'seoai_broken_links_page'
    );
}
add_action('admin_menu', 'seoai_add_admin_menu');

// Add script and style enqueuing
function seoai_enqueue_admin_scripts($hook) {
    if ($hook === 'toplevel_page_seoai_admin' || strpos($hook, 'seoai_admin') !== false) {
        wp_enqueue_script('seoai-admin', plugins_url('includes/seoai-admin.js', dirname(__FILE__)), ['jquery'], '1.0.0', true);
        wp_enqueue_script('seoai-script', plugins_url('assets/script.js', dirname(__FILE__)), ['jquery'], '1.0.0', true);
        wp_enqueue_style('seoai-style', plugins_url('assets/style.css', dirname(__FILE__)));
        wp_localize_script('seoai-admin', 'seoai_ajax', ['ajax_url' => admin_url('admin-ajax.php')]);
    }
    
    if ($hook === 'post.php' || $hook === 'post-new.php') {
        wp_enqueue_script('seoai-editor', plugins_url('includes/editor-suggestions.js', dirname(__FILE__)), ['jquery'], '1.0.0', true);
        wp_localize_script('seoai-editor', 'seoai_ajax', ['ajax_url' => admin_url('admin-ajax.php')]);
    }
}
add_action('admin_enqueue_scripts', 'seoai_enqueue_admin_scripts');

// Admin page content
function seoai_admin_page() {
    if (isset($_POST['seoai_save_settings'])) {
        update_option('seoai_ai_provider', sanitize_text_field($_POST['seoai_ai_provider']));
        update_option('seoai_ai_api_key', sanitize_text_field($_POST['seoai_ai_api_key']));
        echo '<div class="notice notice-success is-dismissible"><p>✅ Settings saved successfully!</p></div>';
    }

    global $wpdb;
    $total_posts = wp_count_posts()->publish;
    $table_name = $wpdb->prefix . 'seoai_scanned_posts';
    $total_scanned_posts = $wpdb->get_var("SELECT COUNT(DISTINCT post_id) FROM $table_name");
    $scan_status = get_option('seoai_scan_status', 'not_started');
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline">SEO Fusion AI</h1>
        <p class="description">Manage internal links, orphan pages, and broken links with AI assistance.</p>

        <div class="card">
        <br>
            <div style="display: flex; flex-direction: column; gap: 10px; font-family: Arial, sans-serif;">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <span style="color: #0073aa; font-size: 18px;">📌</span>
                    <strong style="color: #0073aa;">Total Posts:</strong>
                    <span style="margin-left: auto; font-weight: bold;"><?php echo esc_html($total_posts); ?></span>
                </div>

                <div style="display: flex; align-items: center; gap: 8px;">
                    <span style="color: #008000; font-size: 18px;">✅</span>
                    <strong style="color: #008000;">Scanned Posts/Pages:</strong>
                    <span style="margin-left: auto; font-weight: bold;"><?php echo esc_html($total_scanned_posts ? $total_scanned_posts : 0); ?></span>
                </div>
            </div><br>

            <button id="start-scan" class="button button-primary" <?php echo $scan_status === 'scanning' ? 'disabled' : ''; ?>>
                <?php echo $scan_status === 'scanning' ? '🔄 Scanning in Progress...' : '🚀 Start Content Analysis'; ?>
            </button>
            <button id="stop-scan" class="button button-secondary" <?php echo $scan_status !== 'scanning' ? 'disabled' : ''; ?>>
                🛑 Stop Scanning
            </button><br>
            
            <br>
            <a href="<?php echo admin_url('admin.php?page=seoai_scanned_posts'); ?>" class="button button-secondary">
                📄 View Scanned Posts Suggestions
            </a>
            <br><br>

            <p id="scan-message"></p>
        </div>

        <div class="card">
            <h2>Additional Checks</h2>
            
            <button id="start-orphan-scan" class="button button-primary">
                🚀 Check for Orphan Pages
            </button>
            
            
            <button id="start-scan2" class="button button-primary">🔍 Check for Broken Links</button>
            <p id="scan-message2"></p>

            <p id="orphan-message"></p>
            <p id="broken-message"></p>

            <form style='display:none;' method="post">
                <input type="hidden" name="seoai_scan_orphans" value="1">
                <button type="submit" class="button button-primary">Check for Orphan Pages</button>
            </form><br>
            <form style='display:none;' method="post">
                <input type="hidden" name="seoai_scan_broken" value="1">
                <button type="submit" class="button button-primary">Check posts for Broken Links</button>
            </form>
            
            
            <?php
                if (isset($_POST['seoai_scan_orphans'])) {
                    $results = seoai_scan_orphan_pages();
                    echo '<pre>';
                    print_r($results);
                    echo '</pre>';
                }

                if (isset($_POST['seoai_suggest_links'])) {
                    $results = seoai_suggest_internal_links();
                    echo '<h3>Suggested Internal Links:</h3><pre>';
                    print_r($results);
                    echo '</pre>';
                }

                if (isset($_POST['seoai_scan_broken'])) {
                    $results = seoai_scan_broken_links();
                    echo '<pre>';
                    print_r($results);
                    echo '</pre>';
                }
                ?>
        
        </div>

        <div class="card">
            <h2>AI Settings</h2>
            <form method="post">
                <?php $selected_provider = get_option('seoai_ai_provider', 'openai'); ?>
                <label><input type="radio" name="seoai_ai_provider" value="openai" <?php checked($selected_provider, 'openai'); ?>> OpenAI</label><br>
                <label><input type="radio" name="seoai_ai_provider" value="deepseek" <?php checked($selected_provider, 'deepseek'); ?>> DeepSeek</label><br><br>
                <label>API Key:</label>
                <input type="password" id="seoai_ai_api_key" name="seoai_ai_api_key" value="<?php echo esc_attr(get_option('seoai_ai_api_key', '')); ?>" class="regular-text">
                <button style='display:none' type="button" id="toggle-api-key" class="button button-secondary">Show</button>
                <br><br>
                <button type="submit" name="seoai_save_settings" class="button button-primary">Save Settings</button>
            </form>
        </div>
    </div>
    
    <script>
        document.addEventListener("DOMContentLoaded", function () {
            function startScan(offset = 0, reset = false) {
                fetch(ajaxurl, {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/x-www-form-urlencoded",
                    },
                    body: new URLSearchParams({
                        action: "seoai_scan_broken",
                        offset: offset,
                        reset: reset ? "1" : "0",
                    }),
                })
                .then(response => response.json())
                .then(data => {
                    const scanMessage = document.getElementById("scan-message2");

                    if (data.success) {
                        scanMessage.textContent = data.data.message;
                        if (!data.data.done) {
                            // Continue with the next batch
                            startScan(data.data.next_offset);
                        } else {
                            // Redirect after finishing
                            window.location.href = data.data.redirect;
                        }
                    } else {
                        scanMessage.textContent = "❌ Scan failed: " + data.data.message;
                    }
                })
                .catch(error => {
                    document.getElementById("scan-message2").textContent = "❌ Scan failed: " + error.message;
                });
            }

            document.getElementById("start-scan2").addEventListener("click", function () {
                let button = this;
                button.disabled = true;
                button.innerText = "🔄 Checking for Broken Links...";
                document.getElementById("scan-message2").textContent = "Scan in progress...";
                startScan(0, true); // Start with reset = true
            });
        });
        </script>

    <script>
    document.getElementById('start-orphan-scan').addEventListener('click', function() {
        let button = this;
        button.disabled = true;
        button.innerText = "🔄 Checking for Orphan Pages...";
        fetch('<?php echo admin_url('admin-ajax.php?action=seoai_scan_orphans'); ?>')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.href = data.data.redirect;
                } else {
                    document.getElementById('orphan-message').innerText = "❌ Scan failed. Please try again.";
                }
            });
    });
    </script>

    <script>
    document.getElementById('check-orphan-pages').addEventListener('click', function() {
        let button = this;
        button.disabled = true;
        button.innerText = "🔄 Checking for Orphan Pages...";

        fetch('<?php echo admin_url('admin-ajax.php'); ?>?action=seoai_run_orphan_scan')
            .then(response => response.json())
            .then(data => {
                document.getElementById('scan-message').innerText = data.message;
                setTimeout(() => {
                    window.location.href = "<?php echo admin_url('admin.php?page=seoai_orphan_pages'); ?>";
                }, 2000); // Redirect after 2 seconds
            })
            .catch(error => {
                document.getElementById('scan-message').innerText = "❌ Scan failed. Please try again.";
            });
    });
    </script>

    <script>
        document.getElementById('toggle-api-key').addEventListener('click', function() {
            let input = document.getElementById('seoai_ai_api_key');
            if (input.type === "password") {
                input.type = "text";
                this.textContent = "Hide";
            } else {
                input.type = "password";
                this.textContent = "Show";
            }
        });
    </script>

    <script>
        document.getElementById('start-scan').addEventListener('click', function() {
            let button = this;
            button.disabled = true;
            button.innerText = "🔄 Scanning in Progress...";
            document.getElementById('stop-scan').disabled = false;
            fetch('<?php echo admin_url('admin-ajax.php'); ?>?action=seoai_start_scan')
                .then(response => response.json())
                .then(data => {
                    document.getElementById('scan-message').innerText = data.message;
                })
                .catch(error => {
                    document.getElementById('scan-message').innerText = "❌ Scan failed. Please try again.";
                })
                .finally(() => {
                    location.reload(); // Always reload after fetch completes
                });
            
                setTimeout(() => location.reload(), 1000);
                
        });
        
        document.getElementById('stop-scan').addEventListener('click', function() {
            let button = this;
            button.disabled = true;
            document.getElementById('start-scan').disabled = false;
            document.getElementById('start-scan').innerText = "🚀 Start Scanning";
            fetch('<?php echo admin_url('admin-ajax.php'); ?>?action=seoai_stop_scan')
                .then(response => response.json())
                .then(data => {
                    document.getElementById('scan-message').innerText = data.data;
                });
                
                setTimeout(() => location.reload(), 1000);
        });
    </script>
    <?php
}
