<?php
if (!defined('ABSPATH')) {
    exit;
}

class SandCrime_Notification_Delivery {
    private static $push_providers = array();
    private static $email_templates = array();

    public static function init() {
        add_action('init', array(__CLASS__, 'register_hooks'));
        add_action('sandcrime_process_notification_queue', array(__CLASS__, 'process_queue'));
        add_action('sandcrime_process_notification_digests', array(__CLASS__, 'process_digests'));
    }

    public static function register_hooks() {
        // Schedule queue processing
        if (!wp_next_scheduled('sandcrime_process_notification_queue')) {
            wp_schedule_event(time(), 'every_minute', 'sandcrime_process_notification_queue');
        }

        // Schedule digest processing
        if (!wp_next_scheduled('sandcrime_process_notification_digests')) {
            wp_schedule_event(time(), 'hourly', 'sandcrime_process_notification_digests');
        }

        // Register default push providers
        self::register_push_provider('fcm', 'SandCrime_FCM_Provider');
        self::register_push_provider('apns', 'SandCrime_APNS_Provider');

        // Load email templates
        self::load_email_templates();
    }

    public static function register_push_provider($type, $class_name) {
        if (class_exists($class_name)) {
            self::$push_providers[$type] = new $class_name();
        }
    }

    public static function process_queue() {
        global $wpdb;

        // Get pending notifications
        $pending = $wpdb->get_results("
            SELECT nq.*, n.* 
            FROM {$wpdb->prefix}sandcrime_notification_queue nq
            JOIN {$wpdb->prefix}sandcrime_notifications n ON nq.notification_id = n.id
            WHERE nq.status = 'pending'
            AND nq.scheduled_for <= NOW()
            AND nq.attempts < 3
            LIMIT 50
        ");

        foreach ($pending as $item) {
            try {
                $success = false;

                if (strpos($item->delivery_type, 'push_') === 0) {
                    $provider_type = substr($item->delivery_type, 5);
                    $success = self::send_push_notification($item, $provider_type);
                } elseif ($item->delivery_type === 'email') {
                    $success = self::send_email_notification($item);
                }

                self::update_queue_status(
                    $item->id, 
                    $success ? 'delivered' : 'failed'
                );
            } catch (Exception $e) {
                self::update_queue_status(
                    $item->id, 
                    'failed', 
                    $e->getMessage()
                );
            }
        }
    }

    public static function process_digests() {
        global $wpdb;

        // Get pending digests
        $pending = $wpdb->get_results("
            SELECT * 
            FROM {$wpdb->prefix}sandcrime_notification_digests
            WHERE sent_at IS NULL
            AND scheduled_for <= NOW()
        ");

        foreach ($pending as $digest) {
            try {
                $notifications = json_decode($digest->notifications, true);
                $user_data = get_userdata($digest->user_id);

                if (!$user_data || empty($notifications)) {
                    continue;
                }

                // Get notification details
                $notification_details = $wpdb->get_results($wpdb->prepare("
                    SELECT *
                    FROM {$wpdb->prefix}sandcrime_notifications
                    WHERE id IN (" . implode(',', array_fill(0, count($notifications), '%d')) . ")
                    ORDER BY created_at DESC
                ", $notifications));

                // Send digest email
                $success = self::send_digest_email($user_data, $notification_details, $digest->frequency);

                if ($success) {
                    $wpdb->update(
                        $wpdb->prefix . 'sandcrime_notification_digests',
                        array('sent_at' => current_time('mysql')),
                        array('id' => $digest->id)
                    );
                }
            } catch (Exception $e) {
                // Log error
                error_log("Digest processing failed for digest ID {$digest->id}: " . $e->getMessage());
            }
        }
    }

    private static function send_push_notification($notification, $provider_type) {
        if (!isset(self::$push_providers[$provider_type])) {
            throw new Exception("Push provider not found: $provider_type");
        }

        $provider = self::$push_providers[$provider_type];
        
        // Get user's device token
        global $wpdb;
        $device = $wpdb->get_row($wpdb->prepare("
            SELECT device_token, device_type 
            FROM {$wpdb->prefix}sandcrime_notification_devices
            WHERE user_id = %d AND device_type = %s
            ORDER BY last_active DESC
            LIMIT 1
        ", $notification->user_id, $provider_type));

        if (!$device) {
            return false;
        }

        $payload = array(
            'title' => $notification->title,
            'body' => $notification->message,
            'data' => json_decode($notification->data, true),
            'priority' => $notification->priority,
            'notification_id' => $notification->id
        );

        return $provider->send($device->device_token, $payload);
    }

    private static function send_email_notification($notification) {
        $user_data = get_userdata($notification->user_id);
        if (!$user_data) {
            return false;
        }

        $template = self::get_email_template($notification->type);
        if (!$template) {
            return false;
        }

        $content = self::render_email_template($template, array(
            'user' => $user_data,
            'notification' => $notification,
            'data' => json_decode($notification->data, true)
        ));

        return wp_mail(
            $user_data->user_email,
            $notification->title,
            $content,
            array(
                'Content-Type: text/html; charset=UTF-8',
                'From: ' . get_option('blogname') . ' <' . get_option('admin_email') . '>'
            )
        );
    }

    private static function send_digest_email($user, $notifications, $frequency) {
        $template = self::get_email_template('digest_' . $frequency);
        if (!$template) {
            return false;
        }

        $content = self::render_email_template($template, array(
            'user' => $user,
            'notifications' => $notifications,
            'frequency' => $frequency
        ));

        return wp_mail(
            $user->user_email,
            sprintf(
                '%s Notification Digest - %s',
                get_option('blogname'),
                ucfirst($frequency)
            ),
            $content,
            array(
                'Content-Type: text/html; charset=UTF-8',
                'From: ' . get_option('blogname') . ' <' . get_option('admin_email') . '>'
            )
        );
    }

    private static function update_queue_status($queue_id, $status, $error = null) {
        global $wpdb;

        $wpdb->update(
            $wpdb->prefix . 'sandcrime_notification_queue',
            array(
                'status' => $status,
                'attempts' => new Raw('attempts + 1'),
                'last_attempt' => current_time('mysql'),
                'error' => $error
            ),
            array('id' => $queue_id)
        );
    }

    private static function load_email_templates() {
        $template_dir = SANDCRIME_PLUGIN_DIR . 'templates/email/';
        
        // Load all template files
        foreach (glob($template_dir . '*.php') as $file) {
            $template_name = basename($file, '.php');
            self::$email_templates[$template_name] = $file;
        }
    }

    private static function get_email_template($type) {
        return isset(self::$email_templates[$type]) ? 
            self::$email_templates[$type] : 
            self::$email_templates['default'];
    }

    private static function render_email_template($template_file, $data) {
        ob_start();
        extract($data);
        include $template_file;
        return ob_get_clean();
    }
}

// Initialize the notification delivery system
SandCrime_Notification_Delivery::init();