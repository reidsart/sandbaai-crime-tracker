<?php
if (!defined('ABSPATH')) {
    exit;
}

class SandCrime_Notification_Scheduler {
    private $batch_size = 50;

    public function __construct() {
        add_action('init', array($this, 'register_schedules'));
        add_action('sandcrime_process_notification_queue', array($this, 'process_queue'));
        add_action('sandcrime_process_notification_digests', array($this, 'process_digests'));
        add_action('sandcrime_cleanup_notifications', array($this, 'cleanup_old_notifications'));
    }

    public function register_schedules() {
        if (!wp_next_scheduled('sandcrime_process_notification_queue')) {
            wp_schedule_event(time(), 'every_minute', 'sandcrime_process_notification_queue');
        }

        if (!wp_next_scheduled('sandcrime_process_notification_digests')) {
            wp_schedule_event(time(), 'hourly', 'sandcrime_process_notification_digests');
        }

        if (!wp_next_scheduled('sandcrime_cleanup_notifications')) {
            wp_schedule_event(time(), 'daily', 'sandcrime_cleanup_notifications');
        }
    }

    public function schedule_notification($notification_id, $user_id, $delivery_type, $delay = 0) {
        global $wpdb;

        $scheduled_for = date('Y-m-d H:i:s', time() + $delay);

        return $wpdb->insert(
            $wpdb->prefix . 'sandcrime_notification_queue',
            array(
                'notification_id' => $notification_id,
                'user_id' => $user_id,
                'delivery_type' => $delivery_type,
                'status' => 'pending',
                'scheduled_for' => $scheduled_for,
                'created_at' => current_time('mysql')
            ),
            array('%d', '%d', '%s', '%s', '%s', '%s')
        );
    }

    public function process_queue() {
        global $wpdb;

        // Get pending notifications
        $pending = $wpdb->get_results($wpdb->prepare("
            SELECT q.*, n.* 
            FROM {$wpdb->prefix}sandcrime_notification_queue q
            JOIN {$wpdb->prefix}sandcrime_notifications n ON q.notification_id = n.id
            WHERE q.status = 'pending'
            AND q.scheduled_for <= %s
            AND q.attempts < 3
            LIMIT %d
        ", current_time('mysql'), $this->batch_size));

        foreach ($pending as $item) {
            try {
                $success = $this->deliver_notification($item);
                $this->update_queue_status(
                    $item->id,
                    $success ? 'delivered' : 'failed'
                );
            } catch (Exception $e) {
                $this->update_queue_status(
                    $item->id,
                    'failed',
                    $e->getMessage()
                );
            }
        }
    }

    private function deliver_notification($item) {
        // Check quiet hours
        if (!$this->should_deliver_during_quiet_hours($item)) {
            $this->reschedule_for_quiet_hours($item);
            return true;
        }

        switch ($item->delivery_type) {
            case 'email':
                return $this->send_email_notification($item);

            case 'push':
                return $this->send_push_notification($item);

            default:
                throw new Exception('Invalid delivery type: ' . $item->delivery_type);
        }
    }

    private function should_deliver_during_quiet_hours($item) {
        $settings = SandCrime_Notification_Handler::get_user_settings($item->user_id);
        
        if (!$settings['quiet_hours_enabled']) {
            return true;
        }

        $user_timezone = new DateTimeZone($settings['timezone']);
        $current_time = new DateTime('now', $user_timezone);
        $start_time = DateTime::createFromFormat('H:i:s', $settings['quiet_hours_start'], $user_timezone);
        $end_time = DateTime::createFromFormat('H:i:s', $settings['quiet_hours_end'], $user_timezone);

        // Check if current time is within quiet hours
        if ($start_time > $end_time) {
            // Quiet hours span midnight
            return !($current_time >= $start_time || $current_time < $end_time);
        } else {
            return !($current_time >= $start_time && $current_time < $end_time);
        }
    }

    private function reschedule_for_quiet_hours($item) {
        $settings = SandCrime_Notification_Handler::get_user_settings($item->user_id);
        $user_timezone = new DateTimeZone($settings['timezone']);
        $current_time = new DateTime('now', $user_timezone);
        $end_time = DateTime::createFromFormat('H:i:s', $settings['quiet_hours_end'], $user_timezone);

        // Set to end of quiet hours
        $end_time->setDate(
            $current_time->format('Y'),
            $current_time->format('m'),
            $current_time->format('d')
        );

        if ($current_time > $end_time) {
            $end_time->modify('+1 day');
        }

        $delay = $end_time->getTimestamp() - time();
        $this->schedule_notification(
            $item->notification_id,
            $item->user_id,
            $item->delivery_type,
            $delay
        );
    }

    private function send_email_notification($item) {
        $user = get_userdata($item->user_id);
        if (!$user) {
            throw new Exception('User not found');
        }

        $template = SandCrime_Notification_Handler::get_email_template($item->type);
        if (!$template) {
            throw new Exception('Email template not found');
        }

        $content = SandCrime_Notification_Handler::render_email_template($template, array(
            'user' => $user,
            'notification' => $item,
            'data' => json_decode($item->data, true)
        ));

        return wp_mail(
            $user->user_email,
            $item->title,
            $content,
            array(
                'Content-Type: text/html; charset=UTF-8',
                'From: ' . get_option('blogname') . ' <' . get_option('admin_email') . '>'
            )
        );
    }

    private function send_push_notification($item) {
        $push_manager = new SandCrime_Push_Notification_Manager();
        
        return $push_manager->send_notification_to_user($item->user_id, array(
            'id' => $item->id,
            'title' => $item->title,
            'message' => $item->message,
            'type' => $item->type,
            'priority' => $item->priority,
            'data' => json_decode($item->data, true)
        ));
    }

    private function update_queue_status($queue_id, $status, $error = null) {
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

    public function process_digests() {
        $frequencies = array('hourly', 'daily', 'weekly');

        foreach ($frequencies as $frequency) {
            $this->process_frequency_digests($frequency);
        }
    }

    private function process_frequency_digests($frequency) {
        global $wpdb;

        // Get users with digest frequency
        $users = $wpdb->get_results($wpdb->prepare("
            SELECT user_id 
            FROM {$wpdb->prefix}sandcrime_notification_settings
            WHERE email_enabled = 1 
            AND email_frequency = %s
        ", $frequency));

        foreach ($users as $user) {
            $this->create_user_digest($user->user_id, $frequency);
        }
    }

    private function create_user_digest($user_id, $frequency) {
        global $wpdb;

        // Get unprocessed notifications
        $notifications = $wpdb->get_results($wpdb->prepare("
            SELECT n.* 
            FROM {$wpdb->prefix}sandcrime_notifications n
            LEFT JOIN {$wpdb->prefix}sandcrime_notification_digests d 
                ON FIND_IN_SET(n.id, d.notifications)
            WHERE n.user_id = %d
            AND d.id IS NULL
            AND n.created_at >= DATE_SUB(NOW(), INTERVAL 1 %s)
        ", $user_id, $frequency));

        if (empty($notifications)) {
            return;
        }

        $notification_ids = wp_list_pluck($notifications, 'id');

        // Create digest
        $wpdb->insert(
            $wpdb->prefix . 'sandcrime_notification_digests',
            array(
                'user_id' => $user_id,
                'frequency' => $frequency,
                'notifications' => implode(',', $notification_ids),
                'scheduled_for' => current_time('mysql'),
                'created_at' => current_time('mysql')
            )
        );
    }

    public function cleanup_old_notifications() {
        global $wpdb;

        // Remove notifications older than 90 days
        $wpdb->query($wpdb->prepare("
            DELETE FROM {$wpdb->prefix}sandcrime_notifications
            WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)
        "));

        // Remove queue items older than 30 days
        $wpdb->query($wpdb->prepare("
            DELETE FROM {$wpdb->prefix}sandcrime_notification_queue
            WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
        "));

        // Remove digests older than 90 days
        $wpdb->query($wpdb->prepare("
            DELETE FROM {$wpdb->prefix}sandcrime_notification_digests
            WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)
        "));

        // Clean up push subscriptions
        $push_manager = new SandCrime_Push_Notification_Manager();
        $push_manager->cleanup_old_subscriptions();
    }
}

// Initialize the scheduler
new SandCrime_Notification_Scheduler();