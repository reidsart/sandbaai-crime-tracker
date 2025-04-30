<?php
if (!defined('ABSPATH')) {
    exit;
}

class SandCrime_Notification_Installer {
    public static function install() {
        self::create_tables();
        self::add_default_settings();
        self::generate_vapid_keys();
    }

    private static function create_tables() {
        global $wpdb;
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        $charset_collate = $wpdb->get_charset_collate();

        // Notifications table
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}sandcrime_notifications (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            type varchar(50) NOT NULL,
            title varchar(255) NOT NULL,
            message text NOT NULL,
            data longtext,
            priority varchar(20) DEFAULT 'normal',
            read_at datetime DEFAULT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY type (type),
            KEY read_status (read_at),
            KEY created_at (created_at)
        ) $charset_collate;";
        dbDelta($sql);

        // Notification queue table
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}sandcrime_notification_queue (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            notification_id bigint(20) NOT NULL,
            user_id bigint(20) NOT NULL,
            delivery_type varchar(50) NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            attempts int(11) NOT NULL DEFAULT 0,
            error text,
            scheduled_for datetime NOT NULL,
            last_attempt datetime DEFAULT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY notification_id (notification_id),
            KEY user_id (user_id),
            KEY status (status),
            KEY scheduled_for (scheduled_for)
        ) $charset_collate;";
        dbDelta($sql);

        // Notification digests table
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}sandcrime_notification_digests (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            frequency varchar(20) NOT NULL,
            notifications longtext NOT NULL,
            scheduled_for datetime NOT NULL,
            sent_at datetime DEFAULT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY frequency (frequency),
            KEY scheduled_for (scheduled_for)
        ) $charset_collate;";
        dbDelta($sql);

        // Push subscriptions table
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}sandcrime_push_subscriptions (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            endpoint varchar(500) NOT NULL,
            public_key varchar(255) NOT NULL,
            auth_token varchar(255) NOT NULL,
            user_agent varchar(255),
            platform varchar(50),
            language varchar(10),
            last_used datetime NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY endpoint (endpoint),
            KEY user_id (user_id),
            KEY last_used (last_used)
        ) $charset_collate;";
        dbDelta($sql);

        // Notification settings table
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}sandcrime_notification_settings (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            email_enabled tinyint(1) NOT NULL DEFAULT 1,
            email_frequency varchar(20) NOT NULL DEFAULT 'immediate',
            push_enabled tinyint(1) NOT NULL DEFAULT 1,
            notification_types longtext,
            priority_threshold varchar(20) DEFAULT 'all',
            quiet_hours_enabled tinyint(1) NOT NULL DEFAULT 0,
            quiet_hours_start time DEFAULT '22:00:00',
            quiet_hours_end time DEFAULT '07:00:00',
            timezone varchar(100) DEFAULT 'UTC',
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY user_id (user_id)
        ) $charset_collate;";
        dbDelta($sql);
    }

    private static function add_default_settings() {
        $notification_types = array(
            'report',
            'group_message',
            'group_alert',
            'comment',
            'system'
        );

        add_option('sandcrime_notification_types', json_encode($notification_types));
        add_option('sandcrime_notification_priorities', json_encode(array(
            'urgent',
            'high',
            'normal',
            'low'
        )));

        add_option('sandcrime_email_frequencies', json_encode(array(
            'immediate',
            'hourly',
            'daily',
            'weekly'
        )));

        // Default email templates
        self::install_email_templates();
    }

    private static function generate_vapid_keys() {
        if (!get_option('sandcrime_vapid_public_key')) {
            try {
                $keys = SandCrime_Push_Notification_Manager::generate_vapid_keys();
                add_option('sandcrime_vapid_public_key', $keys['publicKey']);
                add_option('sandcrime_vapid_private_key', $keys['privateKey']);
            } catch (Exception $e) {
                error_log('Failed to generate VAPID keys: ' . $e->getMessage());
            }
        }
    }

    private static function install_email_templates() {
        $template_dir = SANDCRIME_PLUGIN_DIR . 'templates/email/';
        if (!file_exists($template_dir)) {
            wp_mkdir_p($template_dir);
        }

        // Copy default email templates
        $default_templates = array(
            'base.php',
            'notification.php',
            'digest.php'
        );

        foreach ($default_templates as $template) {
            $source = SANDCRIME_PLUGIN_DIR . 'includes/notifications/templates/' . $template;
            $destination = $template_dir . $template;

            if (!file_exists($destination) && file_exists($source)) {
                copy($source, $destination);
            }
        }
    }

    public static function uninstall() {
        global $wpdb;

        // Drop tables
        $tables = array(
            'sandcrime_notifications',
            'sandcrime_notification_queue',
            'sandcrime_notification_digests',
            'sandcrime_push_subscriptions',
            'sandcrime_notification_settings'
        );

        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}{$table}");
        }

        // Remove options
        delete_option('sandcrime_notification_types');
        delete_option('sandcrime_notification_priorities');
        delete_option('sandcrime_email_frequencies');
        delete_option('sandcrime_vapid_public_key');
        delete_option('sandcrime_vapid_private_key');
    }
}