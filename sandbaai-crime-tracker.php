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

// Debug message
add_action('admin_notices', function() {
    echo '<div class="notice notice-info"><p>Sandbaai Crime Tracker main plugin file loaded at: ' . current_time('Y-m-d H:i:s') . '</p></div>';
});

// Load admin functions
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
    sandcrime_update_tables();
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

    // Crime Reports Table
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
        user_id BIGINT(20) DEFAULT NULL,
        user_login VARCHAR(60) DEFAULT NULL,
        last_edited_by VARCHAR(60) DEFAULT NULL,
        last_edited_at DATETIME DEFAULT NULL,
        PRIMARY KEY (id)
    ) $charset_collate;";

    // Security Groups Table
    $security_groups_table = $wpdb->prefix . 'sandcrime_groups';
    $sql_groups = "CREATE TABLE $security_groups_table (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        title VARCHAR(255) NOT NULL,
        logo_id BIGINT(20) DEFAULT NULL,
        logo_url VARCHAR(255) DEFAULT NULL,
        email VARCHAR(100) DEFAULT NULL,
        address TEXT DEFAULT NULL,
        website VARCHAR(255) DEFAULT NULL,
        description TEXT DEFAULT NULL,
        PRIMARY KEY (id)
    ) $charset_collate;";

    // Phone Numbers Table
    $phone_numbers_table = $wpdb->prefix . 'sandcrime_group_phones';
    $sql_phones = "CREATE TABLE $phone_numbers_table (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        group_id BIGINT(20) UNSIGNED NOT NULL,
        phone_number VARCHAR(255) NOT NULL,
        label VARCHAR(255) NOT NULL,
        sort_order INT NOT NULL DEFAULT 0,
        PRIMARY KEY (id),
        KEY group_id (group_id)
    ) $charset_collate;";

    // Group Members Table
    $memberships_table = $wpdb->prefix . 'sandcrime_group_members';
    $sql_memberships = "CREATE TABLE $memberships_table (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        group_id BIGINT(20) UNSIGNED NOT NULL,
        user_id BIGINT(20) UNSIGNED NOT NULL,
        role VARCHAR(50) NOT NULL DEFAULT 'member',
        added_by BIGINT(20) UNSIGNED NOT NULL,
        added_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY group_user (group_id, user_id),
        KEY user_id (user_id),
        KEY group_id (group_id)
    ) $charset_collate;";

    // Categories Table
    $categories_table = $wpdb->prefix . 'sandcrime_categories';
    $sql_categories = "CREATE TABLE $categories_table (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        name VARCHAR(255) NOT NULL,
        PRIMARY KEY (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql_reports);
    dbDelta($sql_groups);
    dbDelta($sql_phones);
    dbDelta($sql_memberships);
    dbDelta($sql_categories);
}

function sandcrime_update_tables() {
    global $wpdb;
    
    // Update reports table
    $reports_table = $wpdb->prefix . 'sandcrime_reports';
    $reports_columns = $wpdb->get_col("DESC {$reports_table}");
    
    $reports_updates = array(
        'user_id' => "ALTER TABLE {$reports_table} ADD COLUMN user_id BIGINT(20) DEFAULT NULL",
        'user_login' => "ALTER TABLE {$reports_table} ADD COLUMN user_login VARCHAR(60) DEFAULT NULL",
        'last_edited_by' => "ALTER TABLE {$reports_table} ADD COLUMN last_edited_by VARCHAR(60) DEFAULT NULL",
        'last_edited_at' => "ALTER TABLE {$reports_table} ADD COLUMN last_edited_at DATETIME DEFAULT NULL"
    );
    
    foreach ($reports_updates as $column => $sql) {
        if (!in_array($column, $reports_columns)) {
            $wpdb->query($sql);
        }
    }
    
    // Update security groups table
    $groups_table = $wpdb->prefix . 'sandcrime_groups';
    $groups_columns = $wpdb->get_col("DESC {$groups_table}");
    
    if (!in_array('logo_id', $groups_columns)) {
        $wpdb->query("ALTER TABLE {$groups_table} ADD COLUMN logo_id BIGINT(20) DEFAULT NULL");
    }
    
    if (!in_array('logo_url', $groups_columns)) {
        $wpdb->query("ALTER TABLE {$groups_table} ADD COLUMN logo_url VARCHAR(255) DEFAULT NULL");
    }
    
    // Create phones table if it doesn't exist
    $phone_table = $wpdb->prefix . 'sandcrime_group_phones';
    if($wpdb->get_var("SHOW TABLES LIKE '$phone_table'") != $phone_table) {
        sandcrime_create_tables();
        
        // Migrate existing phone numbers
        $existing_groups = $wpdb->get_results("SELECT id, contact_numbers FROM $groups_table");
        foreach ($existing_groups as $group) {
            if (!empty($group->contact_numbers)) {
                $wpdb->insert(
                    $phone_table,
                    array(
                        'group_id' => $group->id,
                        'phone_number' => $group->contact_numbers,
                        'label' => 'Main',
                        'sort_order' => 0
                    ),
                    array('%d', '%s', '%s', '%d')
                );
            }
        }
    }
    
    // Create memberships table if it doesn't exist
    $memberships_table = $wpdb->prefix . 'sandcrime_group_members';
    if($wpdb->get_var("SHOW TABLES LIKE '$memberships_table'") != $memberships_table) {
        sandcrime_create_tables();
    }
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
        
        // Add Select2 if available
        wp_enqueue_style('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css');
        wp_enqueue_script('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', ['jquery'], null, true);
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

register_activation_hook(__FILE__, array('SandCrime_Notification_Installer', 'install'));
register_uninstall_hook(__FILE__, array('SandCrime_Notification_Installer', 'uninstall'));