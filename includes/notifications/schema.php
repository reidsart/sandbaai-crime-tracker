<?php
if (!defined('ABSPATH')) {
    exit;
}

class SandCrime_Notification_Schema {
    public static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        // Notifications table
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}sandcrime_notifications (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            type varchar(50) NOT NULL,
            title varchar(255) NOT NULL,
            message text NOT NULL,
            data longtext,
            reference_id bigint(20),
            reference_type varchar(50),
            priority varchar(20) DEFAULT 'normal',
            read_at datetime DEFAULT NULL,
            created_at datetime NOT NULL,
            expires_at datetime DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY type (type),
            KEY reference_id (reference_id),
            KEY read_at (read_at),
            KEY created_at (created_at)
        ) $charset_collate;";

        // Notification settings table
        $sql .= "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}sandcrime_notification_settings (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            email_enabled tinyint(1) DEFAULT 1,
            email_frequency varchar(20) DEFAULT 'immediate',
            push_enabled tinyint(1) DEFAULT 1,
            notification_types text,
            priority_threshold varchar(20) DEFAULT 'all',
            quiet_hours_enabled tinyint(1) DEFAULT 0,
            quiet_hours_start time DEFAULT NULL,
            quiet_hours_end time DEFAULT NULL,
            timezone varchar(100) DEFAULT 'UTC',
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY user_id (user_id)
        ) $charset_collate;";

        // Notification devices table
        $sql .= "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}sandcrime_notification_devices (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            device_token varchar(255) NOT NULL,
            device_type varchar(50) NOT NULL,
            device_name varchar(255),
            last_active datetime NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY device_token (device_token),
            KEY user_id (user_id)
        ) $charset_collate;";

        // Notification queue table
        $sql .= "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}sandcrime_notification_queue (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            notification_id bigint(20) NOT NULL,
            user_id bigint(20) NOT NULL,
            delivery_type varchar(20) NOT NULL,
            status varchar(20) DEFAULT 'pending',
            attempts int DEFAULT 0,
            last_attempt datetime DEFAULT NULL,
            scheduled_for datetime NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY notification_id (notification_id),
            KEY user_id (user_id),
            KEY status (status),
            KEY scheduled_for (scheduled_for)
        ) $charset_collate;";

        // Notification digests table
        $sql .= "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}sandcrime_notification_digests (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            frequency varchar(20) NOT NULL,
            notifications text NOT NULL,
            scheduled_for datetime NOT NULL,
            sent_at datetime DEFAULT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY frequency (frequency),
            KEY scheduled_for (scheduled_for)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    public static function create_indexes() {
        global $wpdb;

        // Add additional indexes for performance optimization
        $indexes = array(
            "{$wpdb->prefix}sandcrime_notifications" => array(
                'idx_user_type' => '(user_id, type)',
                'idx_user_created' => '(user_id, created_at)',
                'idx_type_reference' => '(type, reference_id)',
                'idx_unread_notifications' => '(user_id, read_at, created_at)'
            ),
            "{$wpdb->prefix}sandcrime_notification_queue" => array(
                'idx_pending_notifications' => '(status, scheduled_for)',
                'idx_user_delivery' => '(user_id, delivery_type)'
            ),
            "{$wpdb->prefix}sandcrime_notification_digests" => array(
                'idx_pending_digests' => '(frequency, scheduled_for, sent_at)'
            )
        );

        foreach ($indexes as $table => $table_indexes) {
            foreach ($table_indexes as $index_name => $columns) {
                $wpdb->query("CREATE INDEX IF NOT EXISTS $index_name ON $table $columns");
            }
        }
    }

    public static function drop_tables() {
        global $wpdb;

        $tables = array(
            'sandcrime_notifications',
            'sandcrime_notification_settings',
            'sandcrime_notification_devices',
            'sandcrime_notification_queue',
            'sandcrime_notification_digests'
        );

        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}$table");
        }
    }
}