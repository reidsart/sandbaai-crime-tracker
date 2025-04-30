<?php
if (!defined('ABSPATH')) {
    exit;
}

class SandCrime_Notification_Handler {
    public static function init() {
        add_action('init', array(__CLASS__, 'register_hooks'));
        add_action('wp_ajax_get_notifications', array(__CLASS__, 'handle_get_notifications'));
        add_action('wp_ajax_mark_notification_read', array(__CLASS__, 'handle_mark_read'));
        add_action('wp_ajax_mark_all_notifications_read', array(__CLASS__, 'handle_mark_all_read'));
        add_action('wp_ajax_save_notification_settings', array(__CLASS__, 'handle_save_settings'));
        add_action('wp_ajax_register_device', array(__CLASS__, 'handle_register_device'));
    }

    public static function register_hooks() {
        // Report related notifications
        add_action('sandcrime_report_created', array(__CLASS__, 'notify_report_created'), 10, 2);
        add_action('sandcrime_report_updated', array(__CLASS__, 'notify_report_updated'), 10, 3);
        add_action('sandcrime_report_comment_added', array(__CLASS__, 'notify_report_comment'), 10, 3);

        // Group related notifications
        add_action('sandcrime_group_message_sent', array(__CLASS__, 'notify_group_message'), 10, 3);
        add_action('sandcrime_group_alert_created', array(__CLASS__, 'notify_group_alert'), 10, 3);
        add_action('sandcrime_group_member_added', array(__CLASS__, 'notify_group_member_added'), 10, 3);

        // System notifications
        add_action('sandcrime_system_maintenance', array(__CLASS__, 'cleanup_old_notifications'), 10);
        add_action('sandcrime_daily_digest', array(__CLASS__, 'process_daily_digests'), 10);
    }

    public static function create_notification($user_id, $data) {
        global $wpdb;

        // Validate user preferences before creating notification
        if (!self::should_create_notification($user_id, $data)) {
            return false;
        }

        $result = $wpdb->insert(
            $wpdb->prefix . 'sandcrime_notifications',
            array(
                'user_id' => $user_id,
                'type' => $data['type'],
                'title' => $data['title'],
                'message' => $data['message'],
                'data' => json_encode($data['data'] ?? array()),
                'reference_id' => $data['reference_id'] ?? null,
                'reference_type' => $data['reference_type'] ?? null,
                'priority' => $data['priority'] ?? 'normal',
                'created_at' => current_time('mysql'),
                'expires_at' => $data['expires_at'] ?? null
            ),
            array(
                '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s'
            )
        );

        if ($result) {
            $notification_id = $wpdb->insert_id;
            self::queue_notification_delivery($notification_id, $user_id, $data);
            return $notification_id;
        }

        return false;
    }

    private static function should_create_notification($user_id, $data) {
        $settings = self::get_user_settings($user_id);
        
        // Check if notification type is enabled
        if (isset($settings['notification_types'])) {
            $types = json_decode($settings['notification_types'], true);
            if (!in_array($data['type'], $types)) {
                return false;
            }
        }

        // Check priority threshold
        if (isset($settings['priority_threshold'])) {
            $priority_levels = array(
                'low' => 1,
                'normal' => 2,
                'high' => 3,
                'urgent' => 4
            );

            if ($priority_levels[$data['priority']] < 
                $priority_levels[$settings['priority_threshold']]) {
                return false;
            }
        }

        // Check quiet hours
        if ($settings['quiet_hours_enabled']) {
            $current_time = current_time('H:i:s');
            $start = $settings['quiet_hours_start'];
            $end = $settings['quiet_hours_end'];

            if (self::is_time_between($current_time, $start, $end)) {
                // Queue for delivery after quiet hours
                $data['delayed_delivery'] = true;
                $data['delivery_time'] = self::get_next_active_time($end);
            }
        }

        return true;
    }

    private static function queue_notification_delivery($notification_id, $user_id, $data) {
        global $wpdb;

        $settings = self::get_user_settings($user_id);
        $delivery_types = array();

        // Queue email notification
        if ($settings['email_enabled']) {
            switch ($settings['email_frequency']) {
                case 'immediate':
                    self::queue_immediate_email($notification_id, $user_id);
                    break;
                case 'hourly':
                case 'daily':
                case 'weekly':
                    self::add_to_digest($notification_id, $user_id, $settings['email_frequency']);
                    break;
            }
        }

        // Queue push notification
        if ($settings['push_enabled']) {
            self::queue_push_notification($notification_id, $user_id, $data);
        }
    }

    private static function queue_immediate_email($notification_id, $user_id) {
        global $wpdb;

        return $wpdb->insert(
            $wpdb->prefix . 'sandcrime_notification_queue',
            array(
                'notification_id' => $notification_id,
                'user_id' => $user_id,
                'delivery_type' => 'email',
                'status' => 'pending',
                'scheduled_for' => current_time('mysql'),
                'created_at' => current_time('mysql')
            ),
            array('%d', '%d', '%s', '%s', '%s', '%s')
        );
    }

    private static function add_to_digest($notification_id, $user_id, $frequency) {
        global $wpdb;

        // Find or create digest
        $digest = $wpdb->get_row($wpdb->prepare("
            SELECT id, notifications 
            FROM {$wpdb->prefix}sandcrime_notification_digests
            WHERE user_id = %d 
            AND frequency = %s 
            AND sent_at IS NULL
            ORDER BY created_at DESC 
            LIMIT 1
        ", $user_id, $frequency));

        if ($digest) {
            // Add to existing digest
            $notifications = json_decode($digest->notifications, true);
            $notifications[] = $notification_id;
            
            $wpdb->update(
                $wpdb->prefix . 'sandcrime_notification_digests',
                array('notifications' => json_encode($notifications)),
                array('id' => $digest->id),
                array('%s'),
                array('%d')
            );
        } else {
            // Create new digest
            $schedule_time = self::get_next_digest_time($frequency);
            
            $wpdb->insert(
                $wpdb->prefix . 'sandcrime_notification_digests',
                array(
                    'user_id' => $user_id,
                    'frequency' => $frequency,
                    'notifications' => json_encode(array($notification_id)),
                    'scheduled_for' => $schedule_time,
                    'created_at' => current_time('mysql')
                ),
                array('%d', '%s', '%s', '%s', '%s')
            );
        }
    }

    private static function queue_push_notification($notification_id, $user_id, $data) {
        global $wpdb;

        // Get user's registered devices
        $devices = $wpdb->get_results($wpdb->prepare("
            SELECT id, device_token, device_type 
            FROM {$wpdb->prefix}sandcrime_notification_devices
            WHERE user_id = %d 
            AND last_active > DATE_SUB(NOW(), INTERVAL 30 DAY)
        ", $user_id));

        foreach ($devices as $device) {
            $wpdb->insert(
                $wpdb->prefix . 'sandcrime_notification_queue',
                array(
                    'notification_id' => $notification_id,
                    'user_id' => $user_id,
                    'delivery_type' => 'push_' . $device->device_type,
                    'status' => 'pending',
                    'scheduled_for' => isset($data['delivery_time']) ? 
                        $data['delivery_time'] : current_time('mysql'),
                    'created_at' => current_time('mysql')
                ),
                array('%d', '%d', '%s', '%s', '%s', '%s')
            );
        }
    }

    public static function get_notifications($user_id, $params = array()) {
        global $wpdb;

        $where = array('user_id = %d');
        $where_values = array($user_id);

        if (!empty($params['type'])) {
            $where[] = 'type = %s';
            $where_values[] = $params['type'];
        }

        if (isset($params['unread']) && $params['unread']) {
            $where[] = 'read_at IS NULL';
        }

        if (!empty($params['after'])) {
            $where[] = 'created_at > %s';
            $where_values[] = $params['after'];
        }

        $limit = isset($params['limit']) ? intval($params['limit']) : 50;
        $offset = isset($params['offset']) ? intval($params['offset']) : 0;

        $notifications = $wpdb->get_results($wpdb->prepare("
            SELECT *
            FROM {$wpdb->prefix}sandcrime_notifications
            WHERE " . implode(' AND ', $where) . "
            ORDER BY created_at DESC
            LIMIT %d OFFSET %d
        ", array_merge($where_values, array($limit, $offset))));

        // Get total unread count
        $unread_count = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*)
            FROM {$wpdb->prefix}sandcrime_notifications
            WHERE user_id = %d AND read_at IS NULL
        ", $user_id));

        return array(
            'notifications' => $notifications,
            'unread_count' => $unread_count,
            'has_more' => count($notifications) === $limit
        );
    }

    public static function mark_as_read($notification_id, $user_id) {
        global $wpdb;

        return $wpdb->update(
            $wpdb->prefix . 'sandcrime_notifications',
            array('read_at' => current_time('mysql')),
            array('id' => $notification_id, 'user_id' => $user_id),
            array('%s'),
            array('%d', '%d')
        );
    }

    public static function mark_all_as_read($user_id) {
        global $wpdb;

        return $wpdb->update(
            $wpdb->prefix . 'sandcrime_notifications',
            array('read_at' => current_time('mysql')),
            array('user_id' => $user_id, 'read_at' => null),
            array('%s'),
            array('%d', null)
        );
    }

    public static function save_user_settings($user_id, $settings) {
        global $wpdb;

        $existing = self::get_user_settings($user_id);

        if ($existing) {
            return $wpdb->update(
                $wpdb->prefix . 'sandcrime_notification_settings',
                array_merge($settings, array(
                    'updated_at' => current_time('mysql')
                )),
                array('user_id' => $user_id),
                null,
                array('%d')
            );
        } else {
            return $wpdb->insert(
                $wpdb->prefix . 'sandcrime_notification_settings',
                array_merge($settings, array(
                    'user_id' => $user_id,
                    'updated_at' => current_time('mysql')
                )),
                null
            );
        }
    }

    public static function register_device($user_id, $device_data) {
        global $wpdb;

        // Update if device exists, otherwise insert
        $existing = $wpdb->get_var($wpdb->prepare("
            SELECT id 
            FROM {$wpdb->prefix}sandcrime_notification_devices
            WHERE device_token = %s
        ", $device_data['token']));

        if ($existing) {
            return $wpdb->update(
                $wpdb->prefix . 'sandcrime_notification_devices',
                array(
                    'user_id' => $user_id,
                    'device_name' => $device_data['name'],
                    'last_active' => current_time('mysql')
                ),
                array('device_token' => $device_data['token']),
                array('%d', '%s', '%s'),
                array('%s')
            );
        } else {
            return $wpdb->insert(
                $wpdb->prefix . 'sandcrime_notification_devices',
                array(
                    'user_id' => $user_id,
                    'device_token' => $device_data['token'],
                    'device_type' => $device_data['type'],
                    'device_name' => $device_data['name'],
                    'last_active' => current_time('mysql'),
                    'created_at' => current_time('mysql')
                ),
                array('%d', '%s', '%s', '%s', '%s', '%s')
            );
        }
    }

    // AJAX Handlers
    public static function handle_get_notifications() {
        check_ajax_referer('sandcrime_notifications', 'nonce');

        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error('Unauthorized');
        }

        $params = array(
            'type' => isset($_GET['type']) ? sanitize_text_field($_GET['type']) : null,
            'unread' => isset($_GET['unread']) ? (bool)$_GET['unread'] : false,
            'after' => isset($_GET['after']) ? sanitize_text_field($_GET['after']) : null,
            'limit' => isset($_GET['limit']) ? intval($_GET['limit']) : 50,
            'offset' => isset($_GET['offset']) ? intval($_GET['offset']) : 0
        );

        $result = self::get_notifications($user_id, $params);
        wp_send_json_success($result);
    }

    public static function handle_mark_read() {
        check_ajax_referer('sandcrime_notifications', 'nonce');

        $notification_id = isset($_POST['notification_id']) ? intval($_POST['notification_id']) : 0;
        $user_id = get_current_user_id();

        if (!$notification_id || !$user_id) {
            wp_send_json_error('Invalid request');
        }

        $result = self::mark_as_read($notification_id, $user_id);
        
        if ($result) {
            wp_send_json_success();
        } else {
            wp_send_json_error('Failed to mark notification as read');
        }
    }

    public static function handle_mark_all_read() {
        check_ajax_referer('sandcrime_notifications', 'nonce');

        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error('Unauthorized');
        }

        $result = self::mark_all_as_read($user_id);
        
        if ($result) {
            wp_send_json_success();
        } else {
            wp_send_json_error('Failed to mark notifications as read');
        }
    }

    public static function handle_save_settings() {
        check_ajax_referer('sandcrime_notifications', 'nonce');

        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error('Unauthorized');
        }

        $settings = array(
            'email_enabled' => isset($_POST['email_enabled']),
            'email_frequency' => sanitize_text_field($_POST['email_frequency']),
            'push_enabled' => isset($_POST['push_enabled']),
            'notification_types' => json_encode(array_map('sanitize_text_field', $_POST['notification_types'] ?? array())),
            'priority_threshold' => sanitize_text_field($_POST['priority_threshold']),
            'quiet_hours_enabled' => isset($_POST['quiet_hours_enabled']),
            'quiet_hours_start' => sanitize_text_field($_POST['quiet_hours_start']),
            'quiet_hours_end' => sanitize_text_field($_POST['quiet_hours_end']),
            'timezone' => sanitize_text_field($_POST['timezone'])
        );

        $result = self::save_user_settings($user_id, $settings);
        
        if ($result) {
            wp_send_json_success();
        } else {
            wp_send_json_error('Failed to save settings');
        }
    }

    public static function handle_register_device() {
        check_ajax_referer('sandcrime_notifications', 'nonce');

        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error('Unauthorized');
        }

        $device_data = array(
            'token' => sanitize_text_field($_POST['device_token']),
            'type' => sanitize_text_field($_POST['device_type']),
            'name' => sanitize_text_field($_POST['device_name'])
        );

        $result = self::register_device($user_id, $device_data);
        
        if ($result) {
            wp_send_json_success();
        } else {
            wp_send_json_error('Failed to register device');
        }
    }
}

// Initialize the notification handler
SandCrime_Notification_Handler::init();