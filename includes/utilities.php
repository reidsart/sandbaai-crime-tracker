<?php
if (!defined('ABSPATH')) {
    exit;
}

class SandcrimeCrimeUtilities {
    /**
     * Get all crime categories
     */
    public static function get_categories() {
        global $wpdb;
        return $wpdb->get_results("
            SELECT * FROM {$wpdb->prefix}sandcrime_categories 
            ORDER BY name ASC
        ");
    }

    /**
     * Get formatted date/time
     */
    public static function format_datetime($mysql_datetime, $format = 'Y-m-d H:i') {
        return date($format, strtotime($mysql_datetime));
    }

    /**
     * Get status badge HTML
     */
    public static function get_status_badge($status) {
        $status_classes = array(
            'Pending Review' => 'status-pending',
            'In Progress' => 'status-progress',
            'Resolved' => 'status-resolved',
            'Closed' => 'status-closed',
            'Approved' => 'status-approved'
        );

        $class = isset($status_classes[$status]) ? $status_classes[$status] : 'status-default';
        return sprintf(
            '<span class="status-badge %s">%s</span>',
            esc_attr($class),
            esc_html($status)
        );
    }

    /**
     * Get user display name with avatar
     */
    public static function get_user_display($user_id, $size = 32) {
        if (!$user_id) {
            return 'Anonymous';
        }

        $user = get_userdata($user_id);
        if (!$user) {
            return 'Unknown User';
        }

        $avatar = get_avatar($user_id, $size);
        return sprintf(
            '<div class="user-display">%s <span>%s</span></div>',
            $avatar,
            esc_html($user->display_name)
        );
    }

    /**
     * Get security groups for dropdown
     */
    public static function get_security_groups_dropdown() {
        global $wpdb;
        $groups = $wpdb->get_results("
            SELECT id, title 
            FROM {$wpdb->prefix}sandcrime_groups 
            ORDER BY title ASC
        ");

        $options = array();
        foreach ($groups as $group) {
            $options[$group->id] = $group->title;
        }
        return $options;
    }

    /**
     * Sanitize and validate coordinates
     */
    public static function sanitize_coordinates($lat, $lng) {
        $lat = floatval($lat);
        $lng = floatval($lng);

        if ($lat < -90 || $lat > 90) {
            return false;
        }
        if ($lng < -180 || $lng > 180) {
            return false;
        }

        return array(
            'lat' => $lat,
            'lng' => $lng
        );
    }

    /**
     * Generate report reference number
     */
    public static function generate_reference_number() {
        $prefix = 'SCT';
        $date = date('Ymd');
        $random = strtoupper(substr(uniqid(), -4));
        return $prefix . $date . $random;
    }

    /**
     * Get notification recipients
     */
    public static function get_notification_recipients($report_id) {
        global $wpdb;
        
        // Get report details
        $report = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM {$wpdb->prefix}sandcrime_reports 
            WHERE id = %d
        ", $report_id));

        if (!$report) {
            return array();
        }

        $recipients = array();

        // Add system notification emails
        $settings = get_option('sandcrime_settings');
        if (!empty($settings['notification_emails'])) {
            $emails = array_map('trim', explode("\n", $settings['notification_emails']));
            $recipients = array_merge($recipients, $emails);
        }

        // Add security group admins
        if (!empty($report->security_groups)) {
            $group_ids = explode(',', $report->security_groups);
            foreach ($group_ids as $group_id) {
                $admins = $wpdb->get_results($wpdb->prepare("
                    SELECT u.user_email 
                    FROM {$wpdb->prefix}sandcrime_group_members m
                    JOIN {$wpdb->users} u ON m.user_id = u.ID
                    WHERE m.group_id = %d AND m.role = 'admin'
                ", $group_id));

                foreach ($admins as $admin) {
                    $recipients[] = $admin->user_email;
                }
            }
        }

        return array_unique($recipients);
    }

    /**
     * Log system activity
     */
    public static function log_activity($action, $user_id, $object_type, $object_id, $details = '') {
        global $wpdb;
        
        return $wpdb->insert(
            $wpdb->prefix . 'sandcrime_activity_log',
            array(
                'action' => $action,
                'user_id' => $user_id,
                'object_type' => $object_type,
                'object_id' => $object_id,
                'details' => $details,
                'created_at' => current_time('mysql')
            ),
            array('%s', '%d', '%s', '%d', '%s', '%s')
        );
    }

    /**
     * Get activity log entries
     */
    public static function get_activity_log($limit = 50, $offset = 0) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT l.*, u.display_name 
            FROM {$wpdb->prefix}sandcrime_activity_log l
            LEFT JOIN {$wpdb->users} u ON l.user_id = u.ID
            ORDER BY l.created_at DESC
            LIMIT %d OFFSET %d
        ", $limit, $offset));
    }

    /**
     * Format phone number
     */
    public static function format_phone_number($number) {
        // Remove all non-numeric characters
        $number = preg_replace('/[^0-9]/', '', $number);
        
        // Format for South African numbers
        if (strlen($number) === 10 && substr($number, 0, 1) === '0') {
            return '+27 ' . substr($number, 1, 2) . ' ' . 
                   substr($number, 3, 3) . ' ' . 
                   substr($number, 6, 4);
        }
        
        // Return original if not matching expected format
        return $number;
    }

    /**
     * Get map markers for reports
     */
    public static function get_map_markers($filters = array()) {
        global $wpdb;

        $where = array('1=1');
        $where_values = array();

        if (!empty($filters['status'])) {
            $where[] = 'result_status = %s';
            $where_values[] = $filters['status'];
        }

        if (!empty($filters['category'])) {
            $where[] = 'category = %s';
            $where_values[] = $filters['category'];
        }

        if (!empty($filters['date_from'])) {
            $where[] = 'date_time >= %s';
            $where_values[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[] = 'date_time <= %s';
            $where_values[] = $filters['date_to'];
        }

        $query = $wpdb->prepare("
            SELECT id, title, category, date_time, location, result_status,
                   SUBSTRING_INDEX(location, ',', 1) as lat,
                   SUBSTRING_INDEX(location, ',', -1) as lng
            FROM {$wpdb->prefix}sandcrime_reports
            WHERE " . implode(' AND ', $where) . "
            ORDER BY date_time DESC
        ", $where_values);

        return $wpdb->get_results($query);
    }

    /**
     * Generate statistics
     */
    public static function generate_statistics($period = '30days') {
        global $wpdb;
        
        $stats = array();
        
        // Date range
        switch ($period) {
            case '7days':
                $date_from = date('Y-m-d', strtotime('-7 days'));
                break;
            case '90days':
                $date_from = date('Y-m-d', strtotime('-90 days'));
                break;
            case 'year':
                $date_from = date('Y-m-d', strtotime('-1 year'));
                break;
            default: // 30 days
                $date_from = date('Y-m-d', strtotime('-30 days'));
        }

        // Reports by category
        $stats['categories'] = $wpdb->get_results($wpdb->prepare("
            SELECT category, COUNT(*) as count
            FROM {$wpdb->prefix}sandcrime_reports
            WHERE DATE(date_time) >= %s
            GROUP BY category
            ORDER BY count DESC
        ", $date_from));

        // Reports by status
        $stats['statuses'] = $wpdb->get_results($wpdb->prepare("
            SELECT result_status, COUNT(*) as count
            FROM {$wpdb->prefix}sandcrime_reports
            WHERE DATE(date_time) >= %s
            GROUP BY result_status
        ", $date_from));

        // Daily report counts
        $stats['daily'] = $wpdb->get_results($wpdb->prepare("
            SELECT DATE(date_time) as date, COUNT(*) as count
            FROM {$wpdb->prefix}sandcrime_reports
            WHERE DATE(date_time) >= %s
            GROUP BY DATE(date_time)
            ORDER BY date
        ", $date_from));

        return $stats;
    }
}

// Initialize activity logging table
function sandcrime_create_activity_log_table() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}sandcrime_activity_log (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        action VARCHAR(50) NOT NULL,
        user_id BIGINT(20) UNSIGNED NOT NULL,
        object_type VARCHAR(50) NOT NULL,
        object_id BIGINT(20) UNSIGNED NOT NULL,
        details TEXT,
        created_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        KEY user_id (user_id),
        KEY object_type (object_type),
        KEY object_id (object_id),
        KEY created_at (created_at)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

// Register activation hook for activity log
register_activation_hook(__FILE__, 'sandcrime_create_activity_log_table');