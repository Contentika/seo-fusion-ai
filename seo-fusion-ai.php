<?php
/**
 * Plugin Name: SEO Fusion AI
 * Plugin URI: https://contentika.com
 * Description: Powerful WordPress SEO optimization powered by AI - Internal linking, content analysis, and on-page SEO.
 * Version: 1.0.0
 * Author: Contentika
 * Author URI: https://contentika.com
 * License: GPL2
 */

if (!defined('ABSPATH')) {
    exit; // Prevent direct access
}

// Include necessary files
require_once plugin_dir_path(__FILE__) . 'includes/admin-page.php';
require_once plugin_dir_path(__FILE__) . 'includes/link-scanner.php';

// Activate plugin
function seoai_activate() {
    add_option('seoai_settings', []);
}
register_activation_hook(__FILE__, 'seoai_activate');

// Deactivate plugin
function seoai_deactivate() {
    delete_option('seoai_settings');
}
register_deactivation_hook(__FILE__, 'seoai_deactivate');
?>