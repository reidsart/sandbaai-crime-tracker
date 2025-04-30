<?php
/**
 * Plugin Name: Sandbaai Crime Tracker
 * Description: A WordPress plugin for crime reporting and statistics tracking in Sandbaai.
 * Version: 1.0.0
 * Author: Christopher Reid
 * Last Updated: 2025-04-30 13:50:08
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Define constants
define('SANDCRIME_VERSION', '1.0.0'); // Add this line
define('SANDCRIME_PLUGIN_VERSION', '1.0.0'); // Keep this for backward compatibility
define('SANDCRIME_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SANDCRIME_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SANDCRIME_LAST_UPDATE', '2025-04-30 13:56:49');
define('SANDCRIME_LAST_UPDATED_BY', 'reidsart2');

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
require_once SANDCRIME_PLUGIN_DIR . 'admin/security-groups/security-groups-page.php';
require_once SANDCRIME_PLUGIN_DIR . 'admin/security-groups/ajax-handlers.php';

// Create database tables
function sandcrime_create_tables() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    // Security Groups Table - Changed table name to match security-groups-page.php
    $security_groups_table = $wpdb->prefix . 'sandcrime_security_groups';
    $sql_groups = "CREATE TABLE IF NOT EXISTS $security_groups_table (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        name varchar(255) NOT NULL,
        description text,
        enabled tinyint(1) NOT NULL DEFAULT 1,
        created_at datetime NOT NULL DEFAULT '2025-04-30 13:50:08',
        updated_at datetime NOT NULL DEFAULT '2025-04-30 13:50:08',
        created_by varchar(60) NOT NULL DEFAULT 'reidsart2',
        updated_by varchar(60) NOT NULL DEFAULT 'reidsart2',
        PRIMARY KEY (id),
        KEY name (name)
    ) $charset_collate;";

 // Group Members Table - updated to include indexes in creation
    $memberships_table = $wpdb->prefix . 'sandcrime_group_members';
    $sql_memberships = "CREATE TABLE IF NOT EXISTS $memberships_table (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        group_id bigint(20) unsigned NOT NULL,
        user_id bigint(20) unsigned NOT NULL,
        added_by bigint(20) unsigned NOT NULL,
        created_at datetime NOT NULL DEFAULT '2025-04-30 13:56:49',
        PRIMARY KEY (id),
        UNIQUE KEY group_user (group_id, user_id),
        KEY group_id (group_id),
        KEY user_id (user_id)
    ) $charset_collate;";

    // Phone Numbers Table
 $phone_numbers_table = $wpdb->prefix . 'sandcrime_group_phones';
    $sql_phones = "CREATE TABLE IF NOT EXISTS $phone_numbers_table (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        group_id bigint(20) unsigned NOT NULL,
        phone_number varchar(255) NOT NULL,
        label varchar(255) NOT NULL,
        sort_order int NOT NULL DEFAULT 0,
        created_at datetime NOT NULL DEFAULT '2025-04-30 13:56:49',
        updated_at datetime NOT NULL DEFAULT '2025-04-30 13:56:49',
        created_by varchar(60) NOT NULL DEFAULT 'reidsart2',
        updated_by varchar(60) NOT NULL DEFAULT 'reidsart2',
        PRIMARY KEY (id),
        KEY group_id (group_id)
    ) $charset_collate;";

    // Crime Reports Table
    $crime_reports_table = $wpdb->prefix . 'sandcrime_reports';
    $sql_reports = "CREATE TABLE IF NOT EXISTS $crime_reports_table (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        title varchar(255) NOT NULL,
        description text NOT NULL,
        category varchar(100) NOT NULL,
        date_time datetime NOT NULL,
        location text NOT NULL,
        result_status varchar(50) NOT NULL,
        security_groups text NOT NULL,
        photo_attachments text DEFAULT NULL,
        user_id bigint(20) DEFAULT NULL,
        user_login varchar(60) DEFAULT NULL,
        last_edited_by varchar(60) DEFAULT NULL,
        last_edited_at datetime DEFAULT NULL,
        created_at datetime NOT NULL DEFAULT '2025-04-30 13:50:08',
        updated_at datetime NOT NULL DEFAULT '2025-04-30 13:50:08',
        created_by varchar(60) NOT NULL DEFAULT 'reidsart2',
        updated_by varchar(60) NOT NULL DEFAULT 'reidsart2',
        PRIMARY KEY (id)
    ) $charset_collate;";

    // Categories Table
    $categories_table = $wpdb->prefix . 'sandcrime_categories';
    $sql_categories = "CREATE TABLE IF NOT EXISTS $categories_table (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        name varchar(255) NOT NULL,
        created_at datetime NOT NULL DEFAULT '2025-04-30 13:50:08',
        updated_at datetime NOT NULL DEFAULT '2025-04-30 13:50:08',
        created_by varchar(60) NOT NULL DEFAULT 'reidsart2',
        updated_by varchar(60) NOT NULL DEFAULT 'reidsart2',
        PRIMARY KEY (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    
    // Create tables
    dbDelta($sql_groups);
    dbDelta($sql_memberships);
    dbDelta($sql_phones);
    dbDelta($sql_reports);
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
        'last_edited_at' => "ALTER TABLE {$reports_table} ADD COLUMN last_edited_at DATETIME DEFAULT NULL",
        'created_at' => "ALTER TABLE {$reports_table} ADD COLUMN created_at DATETIME NOT NULL DEFAULT '2025-04-30 13:50:08'",
        'updated_at' => "ALTER TABLE {$reports_table} ADD COLUMN updated_at DATETIME NOT NULL DEFAULT '2025-04-30 13:50:08'",
        'created_by' => "ALTER TABLE {$reports_table} ADD COLUMN created_by VARCHAR(60) NOT NULL DEFAULT 'reidsart2'",
        'updated_by' => "ALTER TABLE {$reports_table} ADD COLUMN updated_by VARCHAR(60) NOT NULL DEFAULT 'reidsart2'"
    );
    
    foreach ($reports_updates as $column => $sql) {
        if (!in_array($column, $reports_columns)) {
            $wpdb->query($sql);
        }
    }
    
    // Create phones table if it doesn't exist
    $phone_table = $wpdb->prefix . 'sandcrime_group_phones';
    if($wpdb->get_var("SHOW TABLES LIKE '$phone_table'") != $phone_table) {
        sandcrime_create_tables();
    }
    
    // Create memberships table if it doesn't exist
    $memberships_table = $wpdb->prefix . 'sandcrime_group_members';
    if($wpdb->get_var("SHOW TABLES LIKE '$memberships_table'") != $memberships_table) {
        sandcrime_create_tables();
    }

    // Update audit fields for existing records
    $tables = array(
        'sandcrime_security_groups',
        'sandcrime_categories',
        'sandcrime_reports',
        'sandcrime_group_phones'
    );

    foreach ($tables as $table) {
        $table_name = $wpdb->prefix . $table;
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name) {
            // Add audit fields if they don't exist
            $columns = $wpdb->get_col("DESC {$table_name}");
            
            if (!in_array('created_by', $columns)) {
                $wpdb->query("ALTER TABLE {$table_name} ADD COLUMN created_by VARCHAR(60) NOT NULL DEFAULT 'reidsart2'");
            }
            if (!in_array('updated_by', $columns)) {
                $wpdb->query("ALTER TABLE {$table_name} ADD COLUMN updated_by VARCHAR(60) NOT NULL DEFAULT 'reidsart2'");
            }
            if (!in_array('created_at', $columns)) {
                $wpdb->query("ALTER TABLE {$table_name} ADD COLUMN created_at DATETIME NOT NULL DEFAULT '2025-04-30 13:50:08'");
            }
            if (!in_array('updated_at', $columns)) {
                $wpdb->query("ALTER TABLE {$table_name} ADD COLUMN updated_at DATETIME NOT NULL DEFAULT '2025-04-30 13:50:08'");
            }
        }
    }
}

function sandcrime_admin_notices() {
    $screen = get_current_screen();
    if ($screen->id === 'plugins') {
        if (get_transient('sandcrime_activation_notice')) {
            echo '<div class="notice notice-success is-dismissible">';
            echo '<p>Sandbaai Crime Tracker has been successfully activated.</p>';
            echo '<p>Installation Date: ' . SANDCRIME_LAST_UPDATE . '</p>';
            echo '<p>Installed By: ' . SANDCRIME_LAST_UPDATED_BY . '</p>';
            echo '</div>';
            delete_transient('sandcrime_activation_notice');
        }
    }
}
add_action('admin_notices', 'sandcrime_admin_notices');

// Plugin activation
register_activation_hook(__FILE__, 'sandcrime_activate');
function sandcrime_activate() {
    sandcrime_create_tables();
    sandcrime_update_tables();
    flush_rewrite_rules();
    set_transient('sandcrime_activation_notice', true, 5);
    update_option('sandcrime_installed_at', SANDCRIME_LAST_UPDATE);
    update_option('sandcrime_installed_by', SANDCRIME_LAST_UPDATED_BY);
}

// Plugin deactivation
register_deactivation_hook(__FILE__, 'sandcrime_deactivate');
function sandcrime_deactivate() {
    flush_rewrite_rules();
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

// Add admin menu
add_action('admin_menu', 'sandcrime_admin_menu');
function sandcrime_admin_menu() {
    add_menu_page(
        'Sandbaai Crime Tracker',
        'Crime Tracker',
        'manage_options',
        'sandcrime',
        'sandcrime_dashboard_page',
        'dashicons-shield',
        30
    );

    add_submenu_page(
        'sandcrime',
        'Security Groups',
        'Security Groups',
        'manage_options',
        'sandcrime-security-groups',
        'sandcrime_security_groups_page'
    );
}

// Add this function near your other admin functions
function sandcrime_dashboard_page() {
    ?>
    <div class="wrap">
        <h1><?php echo esc_html__('Sandbaai Crime Tracker Dashboard', 'sandcrime'); ?></h1>
        <div class="sandcrime-dashboard-wrapper">
            <!-- Dashboard content will go here -->
            <p><?php echo esc_html__('Welcome to the Sandbaai Crime Tracker Dashboard.', 'sandcrime'); ?></p>
        </div>
    </div>
    <?php
}

// Enqueue Admin Scripts and Styles
add_action('admin_enqueue_scripts', 'sandcrime_admin_enqueue_scripts');
function sandcrime_admin_enqueue_scripts($hook) {
    if (strpos($hook, 'sandcrime') !== false) {
        wp_enqueue_style('sandcrime-admin-style', SANDCRIME_PLUGIN_URL . 'assets/css/admin-style.css', array(), SANDCRIME_VERSION);
        wp_enqueue_script('sandcrime-admin-scripts', SANDCRIME_PLUGIN_URL . 'assets/js/admin-scripts.js', ['jquery'], SANDCRIME_VERSION, true);
        
        // Add Select2
        wp_enqueue_style('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css', array(), '4.1.0-rc.0');
        wp_enqueue_script('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', ['jquery'], '4.1.0-rc.0', true);
    }
}

// Enqueue Frontend Scripts and Styles
add_action('wp_enqueue_scripts', 'sandcrime_frontend_enqueue_scripts');
function sandcrime_frontend_enqueue_scripts() {
    if (is_page() || is_single()) {
        wp_enqueue_style('sandcrime-form-style', SANDCRIME_PLUGIN_URL . 'assets/css/form-style.css', array(), SANDCRIME_VERSION);
        wp_enqueue_script('sandcrime-form-scripts', SANDCRIME_PLUGIN_URL . 'assets/js/form-scripts.js', ['jquery'], SANDCRIME_VERSION, true);
    }
}
