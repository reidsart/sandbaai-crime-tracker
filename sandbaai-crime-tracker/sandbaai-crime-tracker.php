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

// Activation hook
register_activation_hook(__FILE__, 'sandcrime_activate');
function sandcrime_activate() {
    // Install database tables and default options
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

    // Crime reports table
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

    // Security groups table
    $security_groups_table = $wpdb->prefix . 'sandcrime_groups';
    $sql_groups = "CREATE TABLE $security_groups_table (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        title VARCHAR(255) NOT NULL,
        logo VARCHAR(255) DEFAULT NULL,
        contact_numbers TEXT NOT NULL,
        email VARCHAR(100) DEFAULT NULL,
        address TEXT DEFAULT NULL,
        website VARCHAR(255) DEFAULT NULL,
        description TEXT DEFAULT NULL,
        PRIMARY KEY (id)
    ) $charset_collate;";

    // Crime categories table
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
include_once SANDCRIME_PLUGIN_DIR . 'includes/security-groups.php';          // Security Groups Management
include_once SANDCRIME_PLUGIN_DIR . 'includes/crime-reporting-form.php';     // Crime Reporting Form
include_once SANDCRIME_PLUGIN_DIR . 'includes/crime-statistics.php';         // Crime Statistics Dashboard
include_once SANDCRIME_PLUGIN_DIR . 'includes/crime-category-management.php';// Crime Category Management
include_once SANDCRIME_PLUGIN_DIR . 'includes/report-approval.php';          // Report Approval Workflow
include_once SANDCRIME_PLUGIN_DIR . 'includes/whatsapp-integration.php';     // WhatsApp Integration

// Enqueue Admin Scripts and Styles
add_action('admin_enqueue_scripts', function($hook) {
    if (strpos($hook, 'sandcrime') !== false) {
        wp_enqueue_style('sandcrime-admin-style', SANDCRIME_PLUGIN_URL . 'assets/css/admin-style.css');
        wp_enqueue_script('sandcrime-admin-scripts', SANDCRIME_PLUGIN_URL . 'assets/js/admin-scripts.js', ['jquery'], null, true);
    }
});

// Enqueue Frontend Scripts and Styles
add_action('wp_enqueue_scripts', function() {
    if (is_page() || is_single()) { // Restrict to specific pages if needed
        wp_enqueue_style('sandcrime-form-style', SANDCRIME_PLUGIN_URL . 'assets/css/form-style.css');
        wp_enqueue_script('sandcrime-form-scripts', SANDCRIME_PLUGIN_URL . 'assets/js/form-scripts.js', ['jquery'], null, true);
    }
});

// Add admin menu
add_action('admin_menu', 'sandcrime_create_admin_menu');
function sandcrime_create_admin_menu() {
    add_menu_page(
        'Sandbaai Crime Tracker',
        'Crime Tracker',
        'manage_options',
        'sandcrime_dashboard',
        'sandcrime_dashboard_page',
        'dashicons-chart-pie',
        6
    );

    add_submenu_page(
        'sandcrime_dashboard',
        'Crime Reports',
        'Crime Reports',
        'manage_options',
        'edit.php?post_type=crime_reports'
    );

    add_submenu_page(
        'sandcrime_dashboard',
        'Security Groups',
        'Security Groups',
        'manage_options',
        'edit.php?post_type=security_groups'
    );

    add_submenu_page(
        'sandcrime_dashboard',
        'Settings',
        'Settings',
        'manage_options',
        'sandcrime_settings',
        'sandcrime_settings_page'
    );
}

// Dashboard Page
function sandcrime_dashboard_page() {
    echo '<div class="wrap"><h1>Sandbaai Crime Tracker Dashboard</h1></div>';
}

// Settings Page
function sandcrime_settings_page() {
    echo '<div class="wrap"><h1>Sandbaai Crime Tracker Settings</h1></div>';
}