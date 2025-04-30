<?php
/**
 * Plugin Name: Sandbaai Crime Tracker
 * Description: A WordPress plugin for crime reporting and statistics tracking in Sandbaai.
 * Version: 1.0.0
 * Author: reidsart2
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Define constants
define('SANDCRIME_PLUGIN_VERSION', '1.0.0');
define('SANDCRIME_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SANDCRIME_PLUGIN_URL', plugin_dir_url(__FILE__));

// ADD THIS DEBUG MESSAGE RIGHT HERE - after constants
add_action('admin_notices', function() {
    echo '<div class="notice notice-info"><p>Sandbaai Crime Tracker main plugin file loaded at: ' . current_time('Y-m-d H:i:s') . '</p></div>';
});

// REPLACE your existing admin-functions.php include with this
add_action('plugins_loaded', function() {
    if (file_exists(SANDCRIME_PLUGIN_DIR . 'admin/admin-functions.php')) {
        require_once SANDCRIME_PLUGIN_DIR . 'admin/admin-functions.php';
    } else {
        add_action('admin_notices', function() {
            echo '<div class="notice notice-error"><p>Sandbaai Crime Tracker: admin-functions.php file not found!</p></div>';
        });
    }
});

// Include utilities file first
require_once SANDCRIME_PLUGIN_DIR . 'includes/utilities.php';

// Activation hook
register_activation_hook(__FILE__, 'sandcrime_activate');
function sandcrime_activate() {
    sandcrime_create_tables();
    flush_rewrite_rules();
}

// Deactivation hook
register_deactivation_hook(__FILE__, 'sandcrime_deactivate');
function sandcrime_deactivate() {
    flush_rewrite_rules();
}

// Create database tables
function sandcrime_create_tables() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    $crime_reports_table = $wpdb->prefix . 'sandcrime_reports';
    $sql_reports = "CREATE TABLE $crime_reports_table (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        title VARCHAR(255) NOT NULL,
        description TEXT NOT NULL,
        category VARCHAR(100) NOT NULL,
        date_time DATETIME NOT NULL,
        location TEXT NOT NULL,
        result_status VARCHAR(50) NOT NULL,
        security_groups TEXT NOT NULL,
        photo_attachments TEXT DEFAULT NULL,
        PRIMARY KEY (id)
    ) $charset_collate;";

    $security_groups_table = $wpdb->prefix . 'sandcrime_groups';
    $sql_groups = "CREATE TABLE $security_groups_table (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        title VARCHAR(255) NOT NULL,
        logo_id BIGINT(20) DEFAULT NULL,      /* Add this line */
        logo_url VARCHAR(255) DEFAULT NULL,    /* Add this line */
        contact_numbers TEXT NOT NULL,
        email VARCHAR(100) DEFAULT NULL,
        address TEXT DEFAULT NULL,
        website VARCHAR(255) DEFAULT NULL,
        description TEXT DEFAULT NULL,
        PRIMARY KEY (id)
    ) $charset_collate;";

    $categories_table = $wpdb->prefix . 'sandcrime_categories';
    $sql_categories = "CREATE TABLE $categories_table (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        name VARCHAR(255) NOT NULL,
        PRIMARY KEY (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql_reports);
    dbDelta($sql_groups);
    dbDelta($sql_categories);
}

// Include feature files
$required_files = [
    'includes/security-groups.php',
    'includes/crime-reporting-form.php',
    'includes/crime-statistics.php',
    'includes/crime-category-management.php',
    'includes/report-approval.php',
    'includes/whatsapp-integration.php'
];

foreach ($required_files as $file) {
    $file_path = SANDCRIME_PLUGIN_DIR . $file;
    if (file_exists($file_path)) {
        require_once $file_path;
    } else {
        add_action('admin_notices', function() use ($file) {
            echo '<div class="notice notice-error"><p>Sandbaai Crime Tracker: Missing required file: ' . esc_html($file) . '</p></div>';
        });
    }
}

// Enqueue Admin Scripts and Styles
add_action('admin_enqueue_scripts', 'sandcrime_admin_enqueue_scripts');
function sandcrime_admin_enqueue_scripts($hook) {
    if (strpos($hook, 'sandcrime') !== false) {
        wp_enqueue_style('sandcrime-admin-style', SANDCRIME_PLUGIN_URL . 'assets/css/admin-style.css');
        wp_enqueue_script('sandcrime-admin-scripts', SANDCRIME_PLUGIN_URL . 'assets/js/admin-scripts.js', ['jquery'], null, true);
    }
}

// Enqueue Frontend Scripts and Styles
add_action('wp_enqueue_scripts', 'sandcrime_frontend_enqueue_scripts');
function sandcrime_frontend_enqueue_scripts() {
    if (is_page() || is_single()) {
        wp_enqueue_style('sandcrime-form-style', SANDCRIME_PLUGIN_URL . 'assets/css/form-style.css');
        wp_enqueue_script('sandcrime-form-scripts', SANDCRIME_PLUGIN_URL . 'assets/js/form-scripts.js', ['jquery'], null, true);
    }
}