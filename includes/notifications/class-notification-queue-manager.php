<?php
if (!defined('ABSPATH')) {
    exit;
}

class SandCrime_Notification_Queue_Manager {
    private $max_retries = 3;
    private $backoff_schedule = [300, 900, 3600]; // 5 mins, 15 mins, 1 hour

    public function __construct() {
        add_action('init', array($this, 'register_hooks'));
        add_filter('cron_schedules', array($this, 'add_cron_intervals'));
    }

    public function register_hooks() {
        add_action('sandcrime_retry_failed_notifications', array($this, 'retry_failed_notifications'));
        add_action('sandcrime_monitor_queue_health', array($this, 'monitor_queue_health'));
    }

    public function add_cron_intervals($schedules) {
        $schedules['every_minute'] = array(
            'interval' => 60,
            'display' => __('Every Minute', 'sandcrime')
        );

        $schedules['every_five_minutes'] = array(
            'interval' => 300,
            'display' => __('Every Five Minutes', 'sandcrime')
        );

        return $schedules;
    }

    public function enqueue_notification($notification_id, $user_id, $delivery_type, $options = array()) {
        global $wpdb;

        $default_options = array(
            'priority' => 'normal',
            'delay' => 0,
            'retry_strategy' => 'default',
            'batch_id' => null
        );

        $options = wp_parse_args($options, $default_options);

        try {
            $wpdb->insert(
                $wpdb->prefix . 'sandcrime_notification_queue',
                array(
                    'notification_id' => $notification_id,
                    'user_id' => $user_id,
                    'delivery_type' => $delivery_type,
                    'status' => 'pending',
                    'priority' => $options['priority'],
                    'batch_id' => $options['batch_id'],
                    'retry_strategy' => $options['retry_strategy'],
                    'scheduled_for' => date('Y-m-d H:i:s', time() + $options['delay']),
                    'created_at' => current_time('mysql')
                )
            );

            return $wpdb->insert_id;
        } catch (Exception $e) {
            error_log('Failed to enqueue notification: ' . $e->getMessage());
            return false;
        }
    }

    public function bulk_enqueue_notifications($notifications) {
        global $wpdb;

        $batch_id = uniqid('batch_', true);
        $values = array();
        $placeholders = array();

        foreach ($notifications as $notification) {
            $values = array_merge($values, array(
                $notification['notification_id'],
                $notification['user_id'],
                $notification['delivery_type'],
                'pending',
                $notification['priority'] ?? 'normal',
                $batch_id,
                $notification['retry_strategy'] ?? 'default',
                date('Y-m-d H:i:s', time() + ($notification['delay'] ?? 0)),
                current_time('mysql')
            ));

            $placeholders[] = '(%d, %d, %s, %s, %s, %s, %s, %s, %s)';
        }

        $query = $wpdb->prepare(
            "INSERT INTO {$wpdb->prefix}sandcrime_notification_queue 
            (notification_id, user_id, delivery_type, status, priority, batch_id, retry_strategy, scheduled_for, created_at) 
            VALUES " . implode(', ', $placeholders),
            $values
        );

        try {
            return $wpdb->query($query);
        } catch (Exception $e) {
            error_log('Failed to bulk enqueue notifications: ' . $e->getMessage());
            return false;
        }
    }

    public function retry_failed_notifications() {
        global $wpdb;

        $failed_items = $wpdb->get_results($wpdb->prepare("
            SELECT * 
            FROM {$wpdb->prefix}sandcrime_notification_queue
            WHERE status = 'failed'
            AND attempts < %d
            AND scheduled_for <= %s
        ", $this->max_retries, current_time('mysql')));

        foreach ($failed_items as $item) {
            $retry_delay = $this->calculate_retry_delay($item);
            
            $wpdb->update(
                $wpdb->prefix . 'sandcrime_notification_queue',
                array(
                    'status' => 'pending',
                    'scheduled_for' => date('Y-m-d H:i:s', time() + $retry_delay),
                    'last_retry' => current_time('mysql')
                ),
                array('id' => $item->id)
            );
        }
    }

    private function calculate_retry_delay($item) {
        $attempt = $item->attempts;
        
        switch ($item->retry_strategy) {
            case 'aggressive':
                return min(60 * pow(2, $attempt), 3600); // Max 1 hour
            
            case 'conservative':
                return min(300 * pow(2, $attempt), 86400); // Max 24 hours
            
            case 'default':
            default:
                return $this->backoff_schedule[min($attempt, count($this->backoff_schedule) - 1)];
        }
    }

    public function monitor_queue_health() {
        global $wpdb;

        // Check for stuck items
        $stuck_items = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*)
            FROM {$wpdb->prefix}sandcrime_notification_queue
            WHERE status = 'processing'
            AND last_attempt < DATE_SUB(%s, INTERVAL 5 MINUTE)
        ", current_time('mysql')));

        if ($stuck_items > 0) {
            $this->handle_stuck_items();
        }

        // Check queue size
        $queue_size = $wpdb->get_var("
            SELECT COUNT(*)
            FROM {$wpdb->prefix}sandcrime_notification_queue
            WHERE status IN ('pending', 'processing')
        ");

        if ($queue_size > 10000) {
            $this->notify_queue_overflow($queue_size);
        }

        // Check failure rate
        $this->monitor_failure_rate();
    }

    private function handle_stuck_items() {
        global $wpdb;

        $wpdb->query($wpdb->prepare("
            UPDATE {$wpdb->prefix}sandcrime_notification_queue
            SET status = 'failed',
                error = %s,
                attempts = attempts + 1
            WHERE status = 'processing'
            AND last_attempt < DATE_SUB(%s, INTERVAL 5 MINUTE)
        ", 'Processing timeout', current_time('mysql')));

        $this->log_queue_issue('stuck_items_detected');
    }

    private function monitor_failure_rate() {
        global $wpdb;

        $total_processed = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*)
            FROM {$wpdb->prefix}sandcrime_notification_queue
            WHERE last_attempt > DATE_SUB(%s, INTERVAL 1 HOUR)
        ", current_time('mysql')));

        if ($total_processed < 1) {
            return;
        }

        $failed_count = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*)
            FROM {$wpdb->prefix}sandcrime_notification_queue
            WHERE status = 'failed'
            AND last_attempt > DATE_SUB(%s, INTERVAL 1 HOUR)
        ", current_time('mysql')));

        $failure_rate = ($failed_count / $total_processed) * 100;

        if ($failure_rate > 20) {
            $this->notify_high_failure_rate($failure_rate);
        }
    }

    private function notify_queue_overflow($size) {
        wp_mail(
            get_option('admin_email'),
            'Notification Queue Alert - Overflow',
            sprintf(
                'The notification queue has exceeded safe limits. Current size: %d items. ' .
                'Please check the system for potential issues.',
                $size
            )
        );
    }

    private function notify_high_failure_rate($rate) {
        wp_mail(
            get_option('admin_email'),
            'Notification Queue Alert - High Failure Rate',
            sprintf(
                'The notification delivery system is experiencing a high failure rate (%.2f%%). ' .
                'Please check the system logs for more information.',
                $rate
            )
        );
    }

    private function log_queue_issue($type) {
        error_log(sprintf(
            'Notification queue issue detected: %s at %s',
            $type,
            current_time('mysql')
        ));
    }
}

// Initialize the queue manager
new SandCrime_Notification_Queue_Manager();