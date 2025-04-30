<?php
if (!defined('ABSPATH')) {
    exit;
}

class SandCrime_Notification_Queue {
    private $table_name;
    private $settings;

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'sandcrime_notification_queue';
        $this->settings = new SandCrime_Notification_Settings();
        
        $this->init_hooks();
    }

    private function init_hooks() {
        add_action('init', array($this, 'schedule_cron_events'));
        add_action('sandcrime_process_queue', array($this, 'process_queue'));
        add_action('sandcrime_cleanup_queue', array($this, 'cleanup_queue'));
        add_filter('cron_schedules', array($this, 'add_cron_intervals'));
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

    public function schedule_cron_events() {
        if (!wp_next_scheduled('sandcrime_process_queue')) {
            wp_schedule_event(time(), 'every_minute', 'sandcrime_process_queue');
        }

        if (!wp_next_scheduled('sandcrime_cleanup_queue')) {
            wp_schedule_event(time(), 'daily', 'sandcrime_cleanup_queue');
        }
    }

    public function add($notification) {
        global $wpdb;

        $data = array(
            'notification_id' => $notification['id'],
            'type' => $notification['type'],
            'recipient' => $notification['recipient'],
            'recipient_id' => $notification['recipient_id'],
            'title' => $notification['title'],
            'message' => $notification['message'],
            'priority' => $notification['priority'],
            'metadata' => json_encode($notification['metadata'] ?? array()),
            'retry_count' => 0,
            'status' => 'pending',
            'created_at' => current_time('mysql'),
            'scheduled_for' => isset($notification['scheduled_for']) ? 
                $notification['scheduled_for'] : 
                current_time('mysql')
        );

        $result = $wpdb->insert(
            $this->table_name,
            $data,
            array(
                '%s', '%s', '%s', '%d', '%s', '%s', '%s', 
                '%s', '%d', '%s', '%s', '%s'
            )
        );

        if (!$result) {
            throw new Exception(__('Failed to queue notification', 'sandcrime'));
        }

        do_action('sandcrime_notification_queued', $wpdb->insert_id, $notification);

        return $wpdb->insert_id;
    }

    public function get_batch($limit = null) {
        global $wpdb;

        if ($limit === null) {
            $limit = $this->settings->get_setting('general.batch_size');
        }

        $current_time = current_time('mysql');

        $sql = $wpdb->prepare(
            "SELECT * FROM {$this->table_name}
            WHERE status IN ('pending', 'retry')
            AND scheduled_for <= %s
            AND retry_count < %d
            ORDER BY 
                CASE priority
                    WHEN 'high' THEN 1
                    WHEN 'normal' THEN 2
                    WHEN 'low' THEN 3
                END,
                created_at ASC
            LIMIT %d",
            $current_time,
            $this->settings->get_setting('general.retry_attempts'),
            $limit
        );

        $notifications = $wpdb->get_results($sql, ARRAY_A);

        // Mark notifications as processing
        if (!empty($notifications)) {
            $ids = array_column($notifications, 'id');
            $this->update_status($ids, 'processing');
        }

        return array_map(array($this, 'prepare_notification'), $notifications);
    }

    public function get_failed_notifications($retry_limit = null) {
        global $wpdb;

        if ($retry_limit === null) {
            $retry_limit = $this->settings->get_setting('general.retry_attempts');
        }

        $sql = $wpdb->prepare(
            "SELECT * FROM {$this->table_name}
            WHERE status = 'failed'
            AND retry_count < %d
            ORDER BY last_attempt ASC",
            $retry_limit
        );

        return array_map(
            array($this, 'prepare_notification'),
            $wpdb->get_results($sql, ARRAY_A)
        );
    }

    public function mark_completed($id) {
        global $wpdb;

        $result = $wpdb->update(
            $this->table_name,
            array(
                'status' => 'completed',
                'completed_at' => current_time('mysql')
            ),
            array('id' => $id),
            array('%s', '%s'),
            array('%d')
        );

        if ($result) {
            do_action('sandcrime_notification_completed', $id);
        }

        return $result !== false;
    }

    public function mark_failed($id, $error = '') {
        global $wpdb;

        $notification = $this->get_notification($id);
        if (!$notification) {
            return false;
        }

        $retry_count = $notification['retry_count'] + 1;
        $retry_limit = $this->settings->get_setting('general.retry_attempts');
        $status = $retry_count >= $retry_limit ? 'failed' : 'retry';

        $result = $wpdb->update(
            $this->table_name,
            array(
                'status' => $status,
                'retry_count' => $retry_count,
                'last_error' => $error,
                'last_attempt' => current_time('mysql'),
                'next_attempt' => $this->calculate_next_attempt($retry_count)
            ),
            array('id' => $id),
            array('%s', '%d', '%s', '%s', '%s'),
            array('%d')
        );

        if ($result) {
            do_action('sandcrime_notification_failed', $id, $error, $retry_count);
        }

        return $result !== false;
    }

    public function schedule_retry($notification) {
        global $wpdb;

        $retry_count = $notification['retry_count'] + 1;
        $next_attempt = $this->calculate_next_attempt($retry_count);

        $result = $wpdb->update(
            $this->table_name,
            array(
                'status' => 'retry',
                'retry_count' => $retry_count,
                'next_attempt' => $next_attempt,
                'last_attempt' => current_time('mysql')
            ),
            array('id' => $notification['id']),
            array('%s', '%d', '%s', '%s'),
            array('%d')
        );

        if ($result) {
            do_action('sandcrime_notification_scheduled_retry', $notification['id'], $retry_count, $next_attempt);
        }

        return $result !== false;
    }

    public function cleanup_queue() {
        global $wpdb;

        $retention_days = $this->settings->get_setting('general.log_retention');
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$retention_days} days"));

        $result = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->table_name}
            WHERE status IN ('completed', 'failed')
            AND created_at < %s",
            $cutoff_date
        ));

        if ($result !== false) {
            do_action('sandcrime_queue_cleaned', $result);
        }

        return $result;
    }

    private function prepare_notification($data) {
        return array(
            'id' => $data['notification_id'],
            'queue_id' => $data['id'],
            'type' => $data['type'],
            'recipient' => $data['recipient'],
            'recipient_id' => $data['recipient_id'],
            'title' => $data['title'],
            'message' => $data['message'],
            'priority' => $data['priority'],
            'metadata' => json_decode($data['metadata'], true),
            'retry_count' => $data['retry_count'],
            'status' => $data['status'],
            'created_at' => $data['created_at'],
            'scheduled_for' => $data['scheduled_for']
        );
    }

    private function calculate_next_attempt($retry_count) {
        $base_delay = $this->settings->get_setting('general.retry_interval');
        $delay = $base_delay * pow(2, $retry_count - 1); // Exponential backoff
        
        return date('Y-m-d H:i:s', strtotime("+{$delay} seconds"));
    }

    private function update_status($ids, $status) {
        global $wpdb;

        if (!is_array($ids)) {
            $ids = array($ids);
        }

        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        
        return $wpdb->query($wpdb->prepare(
            "UPDATE {$this->table_name}
            SET status = %s
            WHERE id IN ($placeholders)",
            array_merge(array($status), $ids)
        ));
    }

    private function get_notification($id) {
        global $wpdb;

        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d",
            $id
        ), ARRAY_A);

        return $result ? $this->prepare_notification($result) : null;
    }
}