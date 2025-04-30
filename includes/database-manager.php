<?php
if (!defined('ABSPATH')) {
    exit;
}

class SandCrime_Database_Manager {
    private static $db_version = '1.0.0';
    private static $option_name = 'sandcrime_db_version';

    /**
     * Initialize database manager
     */
    public static function init() {
        add_action('admin_init', array(__CLASS__, 'check_version'));
        add_action('plugins_loaded', array(__CLASS__, 'update_db_check'));
    }

    /**
     * Check database version and update if necessary
     */
    public static function check_version() {
        if (get_option(self::$option_name) != self::$db_version) {
            self::install();
        }
    }

    /**
     * Database installation
     */
    public static function install() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        // Crime Reports Table
        $sql_reports = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}sandcrime_reports (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            title VARCHAR(255) NOT NULL,
            description TEXT NOT NULL,
            category VARCHAR(100) NOT NULL,
            date_time DATETIME NOT NULL,
            location TEXT NOT NULL,
            coordinates POINT,
            result_status VARCHAR(50) NOT NULL DEFAULT 'Pending Review',
            security_groups TEXT,
            photo_attachments TEXT,
            user_id BIGINT(20) DEFAULT NULL,
            user_login VARCHAR(60) DEFAULT NULL,
            last_edited_by VARCHAR(60) DEFAULT NULL,
            last_edited_at DATETIME DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY category (category),
            KEY result_status (result_status),
            KEY user_id (user_id),
            SPATIAL KEY coordinates (coordinates)
        ) $charset_collate;";

        // Security Groups Table
        $sql_groups = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}sandcrime_groups (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            title VARCHAR(255) NOT NULL,
            logo_id BIGINT(20) DEFAULT NULL,
            logo_url VARCHAR(255) DEFAULT NULL,
            email VARCHAR(100) DEFAULT NULL,
            address TEXT DEFAULT NULL,
            website VARCHAR(255) DEFAULT NULL,
            description TEXT DEFAULT NULL,
            coverage_area POLYGON,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY title (title),
            SPATIAL KEY coverage_area (coverage_area)
        ) $charset_collate;";

        // Phone Numbers Table
        $sql_phones = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}sandcrime_group_phones (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            group_id BIGINT(20) UNSIGNED NOT NULL,
            phone_number VARCHAR(20) NOT NULL,
            label VARCHAR(50) NOT NULL DEFAULT 'Main',
            sort_order INT NOT NULL DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY group_id (group_id)
        ) $charset_collate;";

        // Group Members Table
        $sql_members = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}sandcrime_group_members (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            group_id BIGINT(20) UNSIGNED NOT NULL,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            role VARCHAR(20) NOT NULL DEFAULT 'member',
            added_by BIGINT(20) UNSIGNED NOT NULL,
            added_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY group_user (group_id, user_id),
            KEY user_id (user_id)
        ) $charset_collate;";

        // Comments Table
        $sql_comments = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}sandcrime_comments (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            report_id BIGINT(20) UNSIGNED NOT NULL,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            comment TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY report_id (report_id),
            KEY user_id (user_id)
        ) $charset_collate;";

        // Categories Table
        $sql_categories = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}sandcrime_categories (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(100) NOT NULL,
            description TEXT,
            parent_id BIGINT(20) UNSIGNED DEFAULT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY name (name),
            KEY parent_id (parent_id)
        ) $charset_collate;";

        // System Logs Table
        $sql_logs = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}sandcrime_logs (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            type VARCHAR(50) NOT NULL,
            message TEXT NOT NULL,
            context TEXT,
            user_id BIGINT(20) UNSIGNED,
            ip_address VARCHAR(45),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY type (type),
            KEY user_id (user_id),
            KEY created_at (created_at)
        ) $charset_collate;";

        // Execute table creation
        dbDelta($sql_reports);
        dbDelta($sql_groups);
        dbDelta($sql_phones);
        dbDelta($sql_members);
        dbDelta($sql_comments);
        dbDelta($sql_categories);
        dbDelta($sql_logs);

        // Update database version
        update_option(self::$option_name, self::$db_version);
    }

    /**
     * Database update check
     */
    public static function update_db_check() {
        if (get_option(self::$option_name) != self::$db_version) {
            self::update();
        }
    }

    /**
     * Database update procedure
     */
    public static function update() {
        global $wpdb;
        $installed_version = get_option(self::$option_name);

        // Version-specific updates
        if (version_compare($installed_version, '1.0.0', '<')) {
            // Add new columns or modify existing ones
            $wpdb->query("ALTER TABLE {$wpdb->prefix}sandcrime_reports 
                         ADD COLUMN coordinates POINT AFTER location");
            
            $wpdb->query("ALTER TABLE {$wpdb->prefix}sandcrime_groups 
                         ADD COLUMN coverage_area POLYGON AFTER description");
        }

        update_option(self::$option_name, self::$db_version);
    }

    /**
     * Database maintenance tasks
     */
    public static function maintenance() {
        global $wpdb;

        // Clean up old logs
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}sandcrime_logs 
             WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            30
        ));

        // Optimize tables
        $tables = array(
            'sandcrime_reports',
            'sandcrime_groups',
            'sandcrime_group_phones',
            'sandcrime_group_members',
            'sandcrime_comments',
            'sandcrime_categories',
            'sandcrime_logs'
        );

        foreach ($tables as $table) {
            $wpdb->query("OPTIMIZE TABLE {$wpdb->prefix}{$table}");
        }
    }

    /**
     * Schedule maintenance tasks
     */
    public static function schedule_maintenance() {
        if (!wp_next_scheduled('sandcrime_maintenance')) {
            wp_schedule_event(time(), 'daily', 'sandcrime_maintenance');
        }
    }

    /**
     * Unschedule maintenance tasks
     */
    public static function unschedule_maintenance() {
        $timestamp = wp_next_scheduled('sandcrime_maintenance');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'sandcrime_maintenance');
        }
    }
}

// Initialize database manager
SandCrime_Database_Manager::init();

// Register maintenance hook
add_action('sandcrime_maintenance', array('SandCrime_Database_Manager', 'maintenance'));

// Schedule maintenance on plugin activation
register_activation_hook(__FILE__, array('SandCrime_Database_Manager', 'schedule_maintenance'));

// Unschedule maintenance on plugin deactivation
register_deactivation_hook(__FILE__, array('SandCrime_Database_Manager', 'unschedule_maintenance'));